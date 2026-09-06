<?php

namespace App\Http\Controllers;

use App\Models\Builder;
use App\Models\BuilderClaim;
use App\Models\BuilderInvoice;
use App\Models\BuilderReceipt;
use App\Models\BuilderReleaseScheme;
use App\Models\Incentive;
use App\Models\MailDeliveryLog;
use App\Models\PostSaleCase;
use App\Models\PostSaleDemand;
use App\Models\PostSaleDocument;
use App\Models\PostSalePlanTemplate;
use App\Models\PostSaleTransaction;
use App\Models\Project;
use App\Services\BuilderInvoiceService;
use App\Services\MailDeliveryLogger;
use App\Services\PayslipPdfService;
use App\Services\PostSalesService;
use Database\Seeders\PostSaleBuilderSlabSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class PostSalesController extends Controller
{
    public function initialize(Request $request, PostSalesService $service)
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Only Admin can initialize Post Sales.');
        @set_time_limit(0);

        $backupPath = $this->createDatabaseBackup();
        Artisan::call('migrate', ['--force' => true]);
        app(PostSaleBuilderSlabSeeder::class)->run();

        $eligible = 0;
        $created = 0;
        Incentive::query()
            ->where('type', 'closer')
            ->where('status', 'verified')
            ->whereNotNull('finance_manager_verified_by')
            ->orderBy('id')
            ->chunkById(100, function ($incentives) use ($service, &$eligible, &$created) {
                foreach ($incentives as $incentive) {
                    $eligible++;
                    $exists = PostSaleCase::where('site_visit_id', $incentive->site_visit_id)->exists();
                    $service->syncIncentive($incentive, true);
                    if (!$exists) $created++;
                }
            });

        Artisan::call('optimize:clear');

        return response()->json([
            'success' => true,
            'backup' => $backupPath,
            'eligible_verified_bookings' => $eligible,
            'historical_cases_created' => $created,
            'customer_mail_enabled' => (bool) config('post_sales.customer_mail_enabled', false),
            'message' => 'Post Sales initialized. Customer emails remain disabled.',
        ]);
    }

    public function index(Request $request, PostSalesService $service)
    {
        $tab = in_array($request->query('tab'), ['master', 'demands', 'documents', 'receivables', 'builder-desk', 'reports'], true)
            ? $request->query('tab') : 'master';
        $perPage = in_array((int) $request->query('per_page'), [100, 250, 500], true) ? (int) $request->query('per_page') : 100;
        $search = trim((string) $request->query('search'));
        $status = trim((string) $request->query('status'));
        $projectId = $request->integer('project_id') ?: null;

        $caseQuery = PostSaleCase::query()->with(['project.builder', 'builder', 'owner', 'transactions', 'slabs', 'claims.receipts'])
            ->when($search, fn ($query) => $query->where(function ($nested) use ($search) {
                $nested->where('customer_name', 'like', "%{$search}%")
                    ->orWhere('customer_phone', 'like', "%{$search}%")
                    ->orWhere('case_number', 'like', "%{$search}%")
                    ->orWhere('project_name', 'like', "%{$search}%");
            }))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($projectId, fn ($query) => $query->where('project_id', $projectId));

        $cases = null;
        $demands = null;
        $documents = null;
        $claims = null;
        $schemes = collect();
        $templates = collect();
        $invoices = collect();

        if ($tab === 'master' || $tab === 'receivables' || $tab === 'reports') {
            $cases = (clone $caseQuery)->latest('id')->paginate($perPage)->withQueryString();
            $cases->getCollection()->transform(function (PostSaleCase $case) use ($service) {
                $case->setAttribute('finance_metrics', $service->metrics($case));
                return $case;
            });
        }
        if ($tab === 'demands') {
            $demands = PostSaleDemand::with(['postSaleCase.project', 'transactions'])
                ->whereHas('postSaleCase', fn ($query) => $this->applyCaseFilters($query, $search, $status, $projectId))
                ->orderByRaw('due_date IS NULL')->orderBy('due_date')->paginate($perPage)->withQueryString();
        }
        if ($tab === 'documents') {
            $documents = PostSaleDocument::with('postSaleCase.project')
                ->whereHas('postSaleCase', fn ($query) => $this->applyCaseFilters($query, $search, $status, $projectId))
                ->latest('updated_at')->paginate($perPage)->withQueryString();
        }
        if ($tab === 'receivables') {
            $claims = BuilderClaim::with(['postSaleCase.builder', 'postSaleCase.project', 'receipts', 'invoiceItems.invoice'])
                ->latest('id')->limit(500)->get();
        }
        if ($tab === 'builder-desk') {
            $schemes = BuilderReleaseScheme::with(['builder', 'project', 'slabs'])->orderBy('builder_name')->get();
            $templates = PostSalePlanTemplate::with(['builder', 'project', 'items'])->latest()->get();
            $invoices = BuilderInvoice::with(['builder', 'project', 'items.postSaleCase', 'revisions'])->latest()->limit(200)->get();
        }

        $summary = [
            'total_cases' => PostSaleCase::count(),
            'active_cases' => PostSaleCase::where('status', 'active')->count(),
            'handover_pending' => PostSaleCase::where('status', 'handover_pending')->count(),
            'overdue_demands' => PostSaleDemand::where('status', 'overdue')->count(),
            'builder_outstanding' => max(0, (float) BuilderClaim::where('status', '!=', 'void')->sum('claim_amount') - (float) BuilderReceipt::sum('amount')),
            'mail_enabled' => (bool) config('post_sales.customer_mail_enabled', false),
            'test_recipient' => (string) config('post_sales.test_recipient'),
        ];

        return view('post-sales.index', [
            'tab' => $tab, 'perPage' => $perPage, 'search' => $search, 'status' => $status,
            'projectId' => $projectId, 'cases' => $cases, 'demands' => $demands,
            'documents' => $documents, 'claims' => $claims, 'schemes' => $schemes,
            'templates' => $templates, 'invoices' => $invoices, 'summary' => $summary,
            'projects' => Project::with('builder')->orderBy('name')->get(),
            'builders' => Builder::orderBy('name')->get(),
            'caseStatuses' => PostSalesService::CASE_STATUSES,
            'documentStatuses' => PostSalesService::DOCUMENT_STATUSES,
        ]);
    }

    public function data(Request $request, PostSalesService $service)
    {
        $cases = PostSaleCase::with(['project.builder', 'owner', 'transactions', 'slabs', 'claims.receipts'])->latest()->paginate(
            in_array($request->integer('per_page'), [100, 250, 500], true) ? $request->integer('per_page') : 100
        );
        $cases->getCollection()->transform(function (PostSaleCase $case) use ($service) {
            $case->setAttribute('finance_metrics', $service->metrics($case));
            return $case;
        });
        return response()->json($cases);
    }

    public function bulkUpdate(Request $request, PostSalesService $service)
    {
        $validated = $request->validate([
            'changes' => ['required', 'array', 'min:1', 'max:500'],
            'changes.*.type' => ['required', Rule::in(['case', 'demand', 'document', 'claim'])],
            'changes.*.id' => ['required', 'integer'],
            'changes.*.field' => ['required', 'string'],
            'changes.*.value' => ['nullable'],
        ]);
        $saved = [];
        DB::transaction(function () use ($validated, $request, $service, &$saved) {
            foreach ($validated['changes'] as $change) {
                $model = match ($change['type']) {
                    'case' => PostSaleCase::findOrFail($change['id']),
                    'demand' => PostSaleDemand::findOrFail($change['id']),
                    'document' => PostSaleDocument::findOrFail($change['id']),
                    'claim' => BuilderClaim::findOrFail($change['id']),
                };
                $allowed = match ($change['type']) {
                    'case' => ['customer_email', 'project_id', 'status', 'internal_remark'],
                    'demand' => ['title', 'due_date'],
                    'document' => ['status'],
                    'claim' => ['expected_date'],
                };
                abort_unless(in_array($change['field'], $allowed, true), 422, 'Protected financial field cannot be edited inline.');
                if ($model instanceof PostSaleCase && $change['field'] === 'status') {
                    abort_if($change['value'] === 'cancelled', 422, 'Cancellation requires the protected action and a reason.');
                    abort_unless(in_array($change['value'], PostSalesService::CASE_STATUSES, true), 422, 'Invalid case status.');
                    if ($change['value'] === 'active') {
                        $service->activate($model, $request->user());
                        $saved[] = ['type' => $change['type'], 'id' => $model->id, 'field' => $change['field']];
                        continue;
                    }
                }
                $before = $model->toArray();
                $model->{$change['field']} = $change['value'];
                if ($model instanceof PostSaleCase && $change['field'] === 'project_id') {
                    $project = Project::find($change['value']);
                    $model->builder_id = $project?->builder_id;
                    $model->project_name = $project?->name;
                    $model->needs_mapping = !$project;
                }
                if ($model instanceof PostSaleCase) $model->updated_by = $request->user()->id;
                if ($model instanceof PostSaleDemand) $model->updated_by = $request->user()->id;
                if ($model instanceof PostSaleDocument) $model->updated_by = $request->user()->id;
                if ($model instanceof BuilderClaim) $model->updated_by = $request->user()->id;
                $model->save();
                $case = $model instanceof PostSaleCase ? $model : ($model->postSaleCase ?? null);
                if ($case) $service->activity($case, 'inline_update', $before, $model->fresh()->toArray(), $request->user()->id, $change['field'].' updated.', $model);
                $saved[] = ['type' => $change['type'], 'id' => $model->id, 'field' => $change['field']];
            }
        });
        return response()->json(['success' => true, 'saved' => $saved]);
    }

    private function createDatabaseBackup(): string
    {
        abort_unless(DB::getDriverName() === 'mysql', 500, 'Automatic production backup requires MySQL.');

        $directory = storage_path('app/backups');
        File::ensureDirectoryExists($directory);
        $path = $directory.'/post-sales-predeploy-'.now()->format('Ymd-His').'.sql';
        $connection = config('database.connections.mysql');
        $command = sprintf(
            'mysqldump --single-transaction --quick --skip-lock-tables --host=%s --port=%s --user=%s %s > %s',
            escapeshellarg((string) ($connection['host'] ?? '127.0.0.1')),
            escapeshellarg((string) ($connection['port'] ?? '3306')),
            escapeshellarg((string) ($connection['username'] ?? '')),
            escapeshellarg((string) ($connection['database'] ?? '')),
            escapeshellarg($path)
        );
        $process = Process::fromShellCommandline($command, base_path(), [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);
        $process->setTimeout(600)->run();
        if (!$process->isSuccessful() || !File::exists($path) || File::size($path) === 0) {
            File::delete($path);
            abort(500, 'Database backup failed; migration was not started. '.$process->getErrorOutput());
        }

        return $path;
    }

    public function activate(PostSaleCase $postSaleCase, Request $request, PostSalesService $service)
    {
        try { $service->activate($postSaleCase, $request->user()); }
        catch (\RuntimeException $e) { return back()->withErrors(['case' => $e->getMessage()]); }
        return back()->with('success', 'Post Sales case activated.');
    }

    public function cancel(PostSaleCase $postSaleCase, Request $request, PostSalesService $service)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $before = $postSaleCase->toArray();
        $postSaleCase->update(['status' => 'cancelled', 'reminders_enabled' => false, 'cancelled_at' => now(), 'updated_by' => $request->user()->id]);
        $postSaleCase->demands()->where('status', '!=', 'paid')->update(['status' => 'cancelled']);
        $service->activity($postSaleCase, 'case_cancelled', $before, $postSaleCase->fresh()->toArray(), $request->user()->id, $data['reason']);
        return back()->with('success', 'Case cancelled; financial history preserved.');
    }

    public function storeDemand(PostSaleCase $postSaleCase, Request $request, PostSalesService $service)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'], 'amount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'percentage' => ['nullable', 'numeric', 'min:0.001', 'max:100'], 'amount' => ['nullable', 'numeric', 'min:0.01'],
            'due_rule' => ['required', Rule::in(['fixed_date', 'milestone'])], 'due_date' => ['nullable', 'date'],
            'milestone_name' => ['nullable', 'string', 'max:255'], 'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        if ($data['amount_type'] === 'percentage') {
            if (empty($data['percentage'])) {
                return back()->withErrors(['percentage' => 'Enter the demand percentage.'])->withInput();
            }

            if ((float) $postSaleCase->agreement_value <= 0) {
                return back()->withErrors(['percentage' => 'Set the Agreement Value before adding a percentage demand.'])->withInput();
            }

            $amount = round((float) $postSaleCase->agreement_value * ((float) $data['percentage'] / 100), 2);
        } else {
            if (empty($data['amount'])) {
                return back()->withErrors(['amount' => 'Enter the fixed demand amount.'])->withInput();
            }

            $amount = (float) $data['amount'];
        }

        // array_merge lets the calculated amount replace the blank optional form field.
        $demand = $postSaleCase->demands()->create(array_merge($data, [
            'amount' => $amount, 'status' => empty($data['due_date']) ? 'upcoming' : ($data['due_date'] <= now()->toDateString() ? 'due' : 'upcoming'),
            'sort_order' => ((int) $postSaleCase->demands()->max('sort_order')) + 1,
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]));
        $service->activity($postSaleCase, 'demand_created', null, $demand->toArray(), $request->user()->id, $data['remark'] ?? null, $demand);
        return back()->with('success', 'Customer demand added.');
    }

    public function storeTransaction(PostSaleCase $postSaleCase, Request $request, PostSalesService $service)
    {
        $data = $request->validate([
            'demand_id' => ['nullable', 'integer', Rule::exists('post_sale_demands', 'id')->where('post_sale_case_id', $postSaleCase->id)],
            'type' => ['required', Rule::in(['payment', 'refund', 'reversal'])], 'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date', 'before_or_equal:today'], 'payment_mode' => ['nullable', 'string', 'max:80'],
            'reference_no' => ['nullable', 'string', 'max:255'], 'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'remark' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $path = $request->file('proof')?->store('post-sales/customer-payments', 'public');
        $transaction = PostSaleTransaction::create(collect($data)->except('proof')->all() + [
            'post_sale_case_id' => $postSaleCase->id, 'proof_path' => $path, 'status' => 'pending', 'created_by' => $request->user()->id,
        ]);
        $service->activity($postSaleCase, 'transaction_recorded', null, $transaction->toArray(), $request->user()->id, $data['remark'], $transaction);
        return back()->with('success', 'Transaction saved as pending verification.');
    }

    public function verifyTransaction(PostSaleTransaction $transaction, Request $request, PostSalesService $service)
    {
        $data = $request->validate(['decision' => ['required', Rule::in(['verified', 'void'])], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $before = $transaction->toArray();
        $transaction->update(['status' => $data['decision'], 'verified_by' => $request->user()->id, 'verified_at' => now()]);
        $service->refreshDemandStatuses($transaction->postSaleCase);
        $service->activity($transaction->postSaleCase, 'transaction_'.$data['decision'], $before, $transaction->fresh()->toArray(), $request->user()->id, $data['reason'], $transaction);
        return back()->with('success', 'Transaction '.$data['decision'].'.');
    }

    public function updateDocument(PostSaleDocument $document, Request $request, PostSalesService $service)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(PostSalesService::DOCUMENT_STATUSES)],
            'file' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,doc,docx,webp', 'max:15360'],
            'remark' => ['nullable', 'string', 'max:1000'],
        ]);
        $before = $document->toArray();
        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('post-sales/documents', 'public');
            $data['uploaded_at'] = now();
            $data['status'] = $data['status'] === 'pending' ? 'uploaded' : $data['status'];
        }
        if ($data['status'] === 'sent' && !$document->sent_at) $data['sent_at'] = now();
        if (in_array($data['status'], ['signed', 'completed'], true) && !$document->signed_at) $data['signed_at'] = now();
        $data['updated_by'] = $request->user()->id;
        $document->update(collect($data)->except('file')->all());
        $service->activity($document->postSaleCase, 'document_updated', $before, $document->fresh()->toArray(), $request->user()->id, $data['remark'] ?? null, $document);
        return back()->with('success', 'Document tracker updated.');
    }

    public function storeClaim(PostSaleCase $postSaleCase, Request $request, PostSalesService $service)
    {
        $metrics = $service->metrics($postSaleCase);
        $data = $request->validate([
            'claim_amount' => ['required', 'numeric', 'min:0.01', 'max:'.$metrics['fresh_claimable']],
            'gst_amount' => ['nullable', 'numeric', 'min:0'], 'tds_amount' => ['nullable', 'numeric', 'min:0'],
            'claim_date' => ['required', 'date'], 'expected_date' => ['nullable', 'date', 'after_or_equal:claim_date'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'], 'remark' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $claim = $postSaleCase->claims()->create(collect($data)->except('proof')->all() + [
            'collection_percent' => $metrics['collection_percent'], 'release_percent' => $metrics['release_percent'],
            'eligible_amount' => $metrics['eligible_brokerage'], 'status' => 'claim_raised',
            'proof_path' => $request->file('proof')?->store('post-sales/builder-claims', 'public'),
            'created_by' => $request->user()->id, 'updated_by' => $request->user()->id,
        ]);
        $service->activity($postSaleCase, 'builder_claim_created', null, $claim->toArray(), $request->user()->id, $data['remark'], $claim);
        return back()->with('success', 'Builder claim raised.');
    }

    public function storeReceipt(BuilderClaim $claim, Request $request, PostSalesService $service)
    {
        $received = (float) $claim->receipts()->sum('amount');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.max(0, (float) $claim->claim_amount - $received)],
            'received_date' => ['required', 'date', 'before_or_equal:today'], 'payment_mode' => ['nullable', 'string', 'max:80'],
            'reference_no' => ['nullable', 'string', 'max:255'], 'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,pdf,webp', 'max:10240'],
            'remark' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $receipt = $claim->receipts()->create(collect($data)->except('proof')->all() + [
            'proof_path' => $request->file('proof')?->store('post-sales/builder-receipts', 'public'), 'created_by' => $request->user()->id,
        ]);
        $total = $received + (float) $receipt->amount;
        $claim->update(['status' => $total >= (float) $claim->claim_amount ? 'received' : 'part_received', 'updated_by' => $request->user()->id]);
        $service->activity($claim->postSaleCase, 'builder_receipt_recorded', null, $receipt->toArray(), $request->user()->id, $data['remark'], $receipt);
        return back()->with('success', 'Builder receipt saved.');
    }

    public function storeTemplate(Request $request)
    {
        $data = $request->validate([
            'builder_id' => ['nullable', 'exists:builders,id'], 'project_id' => ['required', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'], 'items' => ['required', 'array', 'min:1', 'max:30'],
            'items.*.title' => ['required', 'string', 'max:255'], 'items.*.amount_type' => ['required', Rule::in(['percentage', 'fixed'])],
            'items.*.percentage' => ['nullable', 'numeric', 'min:0.001', 'max:100'], 'items.*.fixed_amount' => ['nullable', 'numeric', 'min:0.01'],
            'items.*.due_rule' => ['required', Rule::in(['relative_days', 'fixed_date', 'milestone'])],
            'items.*.relative_days' => ['nullable', 'integer', 'min:0', 'max:3650'], 'items.*.fixed_date' => ['nullable', 'date'],
            'items.*.milestone_name' => ['nullable', 'string', 'max:255'],
        ]);
        DB::transaction(function () use ($data, $request) {
            PostSalePlanTemplate::where('project_id', $data['project_id'])->update(['is_active' => false]);
            $version = ((int) PostSalePlanTemplate::where('project_id', $data['project_id'])->max('version')) + 1;
            $template = PostSalePlanTemplate::create(collect($data)->except('items')->all() + ['version' => $version, 'is_active' => true, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id]);
            foreach ($data['items'] as $index => $item) {
                if (($item['amount_type'] ?? 'percentage') === 'fixed') {
                    $item['fixed_amount'] = $item['fixed_amount'] ?? $item['percentage'] ?? null;
                    $item['percentage'] = null;
                }
                $template->items()->create($item + ['sort_order' => $index + 1]);
            }
        });
        return back()->with('success', 'Project payment plan version saved. Existing cases remain unchanged.');
    }

    public function quickStoreProject(Request $request)
    {
        $data = $request->validate([
            'project_name' => [
                'required', 'string', 'max:255',
                Rule::unique('projects', 'name')->where(fn ($query) => $query->whereNull('deleted_at')),
            ],
            'builder_id' => ['nullable', 'integer', 'exists:builders,id', 'required_without:builder_name'],
            'builder_name' => ['nullable', 'string', 'max:255', 'required_without:builder_id'],
        ]);

        $project = DB::transaction(function () use ($data, $request) {
            $builder = !empty($data['builder_id'])
                ? Builder::findOrFail($data['builder_id'])
                : Builder::query()->whereRaw('LOWER(name) = ?', [mb_strtolower(trim($data['builder_name']))])->first();

            if (!$builder) {
                $builder = Builder::create(['name' => trim($data['builder_name']), 'status' => 'active']);
            }

            $project = Project::create([
                'builder_id' => $builder->id,
                'name' => trim($data['project_name']),
                'is_active' => true,
            ]);

            BuilderReleaseScheme::firstOrCreate(
                ['builder_id' => $builder->id, 'project_id' => $project->id],
                [
                    'builder_name' => $builder->name,
                    'project_name' => $project->name,
                    'name' => $builder->name.' '.$project->name.' Brokerage Release',
                    'is_active' => false,
                    'setup_status' => 'setup_pending',
                    'created_by' => $request->user()->id,
                    'updated_by' => $request->user()->id,
                ]
            );

            return $project;
        });

        return redirect()->route('post-sales.index', [
            'tab' => 'builder-desk',
            'selected_project' => $project->id,
        ])->with('success', 'Project added. Payment plan and builder release slabs can now be configured.');
    }

    public function updateScheme(BuilderReleaseScheme $scheme, Request $request)
    {
        $data = $request->validate([
            'builder_id' => ['nullable', 'exists:builders,id'], 'project_id' => ['nullable', 'exists:projects,id'],
            'is_active' => ['nullable', 'boolean'], 'slabs' => ['required', 'array', 'min:1', 'max:20'],
            'slabs.*.collection' => ['required', 'numeric', 'min:0.001', 'max:100'],
            'slabs.*.release' => ['required', 'numeric', 'min:0.001', 'max:100'],
        ]);
        DB::transaction(function () use ($scheme, $data, $request) {
            $scheme->update(['builder_id' => $data['builder_id'] ?? null, 'project_id' => $data['project_id'] ?? null, 'is_active' => (bool) ($data['is_active'] ?? false), 'setup_status' => ($data['is_active'] ?? false) ? 'ready' : 'setup_pending', 'updated_by' => $request->user()->id]);
            $scheme->slabs()->delete();
            foreach ($data['slabs'] as $index => $slab) $scheme->slabs()->create(['customer_collection_percent' => $slab['collection'], 'brokerage_release_percent' => $slab['release'], 'sort_order' => $index + 1]);
        });
        return back()->with('success', 'Builder release plan updated. Existing case snapshots remain unchanged.');
    }

    public function createInvoice(Request $request, BuilderInvoiceService $service)
    {
        $data = $request->validate(['claim_ids' => ['required', 'array', 'min:1'], 'claim_ids.*' => ['integer', 'exists:builder_claims,id']]);
        try { $invoice = $service->createFromClaims($data['claim_ids'], $request->user()); }
        catch (\RuntimeException $e) { return back()->withErrors(['invoice' => $e->getMessage()]); }
        return redirect()->route('post-sales.invoices.edit', $invoice)->with('success', 'Invoice draft created. Review before issue.');
    }

    public function editInvoice(BuilderInvoice $invoice)
    {
        return view('post-sales.invoice-edit', ['invoice' => $invoice->load(['items.postSaleCase', 'builder', 'project', 'revisions'])]);
    }

    public function updateInvoice(BuilderInvoice $invoice, Request $request, BuilderInvoiceService $service)
    {
        $data = $request->validate([
            'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date'],
            'seller_name' => ['required', 'string', 'max:255'], 'seller_address' => ['nullable', 'string', 'max:2000'], 'seller_gstin' => ['nullable', 'string', 'max:50'],
            'buyer_name' => ['required', 'string', 'max:255'], 'buyer_address' => ['nullable', 'string', 'max:2000'], 'buyer_gstin' => ['nullable', 'string', 'max:50'],
            'bank_name' => ['nullable', 'string', 'max:255'], 'bank_account' => ['nullable', 'string', 'max:100'], 'bank_ifsc' => ['nullable', 'string', 'max:50'],
            'gst_amount' => ['nullable', 'numeric', 'min:0'], 'tds_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'], 'terms' => ['nullable', 'string', 'max:2000'], 'revision_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        try { $service->update($invoice, $data, $request->user()); }
        catch (\RuntimeException $e) { return back()->withErrors(['revision_reason' => $e->getMessage()])->withInput(); }
        return back()->with('success', 'Invoice saved.');
    }

    public function issueInvoice(BuilderInvoice $invoice, Request $request, BuilderInvoiceService $service, PayslipPdfService $pdfService)
    {
        try { $service->issue($invoice, $request->user(), $pdfService); }
        catch (\RuntimeException $e) { return back()->withErrors(['invoice' => $e->getMessage()]); }
        return back()->with('success', 'Invoice issued and locked. Future edits require a revision reason.');
    }

    public function downloadInvoice(BuilderInvoice $invoice, BuilderInvoiceService $service, PayslipPdfService $pdfService)
    {
        if (!$invoice->pdf_path) $service->issue($invoice, request()->user(), $pdfService);
        [$disk, $path] = array_pad(explode(':', (string) $invoice->fresh()->pdf_path, 2), 2, null);
        abort_unless($disk && $path && Storage::disk($disk)->exists($path), 404);
        return Storage::disk($disk)->download($path, $invoice->invoice_number.'.'.(str_ends_with($path, '.pdf') ? 'pdf' : 'html'));
    }

    public function sendTestDemand(PostSaleDemand $demand, Request $request, MailDeliveryLogger $logger)
    {
        $recipient = (string) config('post_sales.test_recipient', 'realtyxvivek@gmail.com');
        $log = $logger->sendView(
            MailDeliveryLog::TYPE_POST_SALE_DEMAND_TEST,
            '[TEST] Payment demand - '.$demand->postSaleCase->customer_name,
            $recipient,
            'emails.post-sale-demand',
            ['demand' => $demand->load('postSaleCase'), 'isTest' => true],
            ['demand_id' => $demand->id, 'customer_delivery' => false, 'test_recipient' => $recipient],
            $demand,
            $request->user()->id
        );
        return back()->with($log->status === MailDeliveryLog::STATUS_SENT ? 'success' : 'error', $log->status === MailDeliveryLog::STATUS_SENT ? 'Test email sent only to '.$recipient : 'Test email failed: '.$log->statusLabel());
    }

    public function file(string $type, int $id)
    {
        $path = match ($type) {
            'document' => PostSaleDocument::findOrFail($id)->file_path,
            'transaction' => PostSaleTransaction::findOrFail($id)->proof_path,
            'claim' => BuilderClaim::findOrFail($id)->proof_path,
            'receipt' => BuilderReceipt::findOrFail($id)->proof_path,
            default => null,
        };
        abort_unless($path && Storage::disk('public')->exists($path), 404);
        return Storage::disk('public')->download($path);
    }

    public function export(Request $request, PostSalesService $service): StreamedResponse
    {
        $rows = PostSaleCase::with(['project', 'owner', 'transactions', 'slabs', 'claims.receipts'])->latest()->get();
        return response()->streamDownload(function () use ($rows, $service) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Case', 'Customer', 'Phone', 'Email', 'Project', 'Agreement Value', 'Collected', 'Collection %', 'Revenue Value', 'Eligible Brokerage', 'Claimed', 'Received', 'Status']);
            foreach ($rows as $case) {
                $m = $service->metrics($case);
                fputcsv($out, [$case->case_number, $case->customer_name, $case->customer_phone, $case->customer_email, $case->project_name, $case->agreement_value, $m['net_collected'], $m['collection_percent'], $case->revenue_value, $m['eligible_brokerage'], $m['claimed'], $m['received'], $case->status]);
            }
            fclose($out);
        }, 'post-sales-'.now()->format('Y-m-d-His').'.csv', ['Content-Type' => 'text/csv']);
    }

    private function applyCaseFilters($query, string $search, string $status, ?int $projectId): void
    {
        $query->when($search, fn ($q) => $q->where(function ($nested) use ($search) {
            $nested->where('customer_name', 'like', "%{$search}%")->orWhere('case_number', 'like', "%{$search}%")->orWhere('project_name', 'like', "%{$search}%");
        }))->when($status, fn ($q) => $q->where('status', $status))->when($projectId, fn ($q) => $q->where('project_id', $projectId));
    }
}
