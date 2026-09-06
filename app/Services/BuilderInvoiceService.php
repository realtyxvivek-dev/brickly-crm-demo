<?php

namespace App\Services;

use App\Models\BuilderClaim;
use App\Models\BuilderInvoice;
use App\Models\BuilderInvoiceRevision;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class BuilderInvoiceService
{
    public function createFromClaims(array $claimIds, User $actor): BuilderInvoice
    {
        $claims = BuilderClaim::with(['postSaleCase.builder', 'postSaleCase.project', 'invoiceItems.invoice'])
            ->whereIn('id', $claimIds)->get();
        if ($claims->isEmpty() || $claims->count() !== count(array_unique($claimIds))) {
            throw new \RuntimeException('Select valid builder claims.');
        }
        if ($claims->pluck('postSaleCase.builder_id')->unique()->count() !== 1 || $claims->pluck('postSaleCase.project_id')->unique()->count() !== 1) {
            throw new \RuntimeException('One invoice can contain claims from one builder and one project only.');
        }
        if ($claims->contains(fn ($claim) => $claim->invoiceItems->contains(fn ($item) => $item->invoice && $item->invoice->status !== 'void'))) {
            throw new \RuntimeException('A selected claim is already included in an active invoice.');
        }

        return DB::transaction(function () use ($claims, $actor) {
            $first = $claims->first()->postSaleCase;
            $subtotal = round((float) $claims->sum('claim_amount'), 2);
            $gst = round((float) $claims->sum('gst_amount'), 2);
            $tds = round((float) $claims->sum('tds_amount'), 2);
            $invoice = BuilderInvoice::create([
                'builder_id' => $first->builder_id,
                'project_id' => $first->project_id,
                'invoice_number' => 'BI-TMP-'.uniqid(),
                'status' => 'draft',
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(15)->toDateString(),
                'seller_name' => brand_name(),
                'buyer_name' => $first->builder?->name ?: ($first->project_name ?: 'Builder'),
                'subtotal' => $subtotal,
                'gst_amount' => $gst,
                'tds_amount' => $tds,
                'net_receivable' => $subtotal + $gst - $tds,
                'terms' => 'Payment due as per agreed builder brokerage release terms.',
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);
            $invoice->update(['invoice_number' => 'BI-'.now()->format('Ym').'-'.str_pad((string) $invoice->id, 5, '0', STR_PAD_LEFT)]);
            foreach ($claims as $index => $claim) {
                $case = $claim->postSaleCase;
                $invoice->items()->create([
                    'builder_claim_id' => $claim->id,
                    'post_sale_case_id' => $case->id,
                    'description' => $case->customer_name.' - '.$case->project_name.' - Brokerage claim',
                    'quantity' => 1,
                    'rate' => $claim->claim_amount,
                    'amount' => $claim->claim_amount,
                    'sort_order' => $index + 1,
                ]);
            }
            return $invoice->fresh(['items', 'builder', 'project']);
        });
    }

    public function update(BuilderInvoice $invoice, array $data, User $actor): BuilderInvoice
    {
        return DB::transaction(function () use ($invoice, $data, $actor) {
            if ($invoice->status !== 'draft') {
                $reason = trim((string) ($data['revision_reason'] ?? ''));
                if (strlen($reason) < 5) {
                    throw new \RuntimeException('Issued invoice change ke liye revision reason required hai.');
                }
                $snapshot = $invoice->load('items')->toArray();
                $next = $invoice->revision_no + 1;
                BuilderInvoiceRevision::create([
                    'builder_invoice_id' => $invoice->id,
                    'revision_no' => $next,
                    'snapshot' => $snapshot,
                    'reason' => $reason,
                    'changed_by' => $actor->id,
                ]);
                $invoice->revision_no = $next;
                $invoice->status = 'draft';
                $invoice->issued_at = null;
                $invoice->issued_by = null;
                $invoice->pdf_path = null;
            }

            $invoice->fill(collect($data)->only([
                'invoice_date', 'due_date', 'seller_name', 'seller_address', 'seller_gstin',
                'buyer_name', 'buyer_address', 'buyer_gstin', 'bank_name', 'bank_account',
                'bank_ifsc', 'gst_amount', 'tds_amount', 'notes', 'terms',
            ])->all());
            $invoice->subtotal = round((float) $invoice->items()->sum('amount'), 2);
            $invoice->net_receivable = $invoice->subtotal + (float) $invoice->gst_amount - (float) $invoice->tds_amount;
            $invoice->updated_by = $actor->id;
            $invoice->save();
            return $invoice->fresh(['items', 'revisions']);
        });
    }

    public function issue(BuilderInvoice $invoice, User $actor, PayslipPdfService $pdfService): BuilderInvoice
    {
        if ($invoice->status !== 'draft') {
            throw new \RuntimeException('Only a draft invoice can be issued.');
        }
        $invoice->load(['items.postSaleCase', 'builder', 'project']);
        $file = $pdfService->storeHtmlAsPdf(
            view('post-sales.invoice-pdf', compact('invoice'))->render(),
            'post-sales/builder-invoices/'.now()->format('Y/m'),
            $invoice->invoice_number.'-r'.$invoice->revision_no
        );
        $invoice->update([
            'status' => 'issued', 'issued_at' => now(), 'issued_by' => $actor->id,
            'pdf_path' => $file['disk'].':'.$file['path'], 'updated_by' => $actor->id,
        ]);
        return $invoice->fresh();
    }
}
