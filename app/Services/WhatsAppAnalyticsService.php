<?php

namespace App\Services;

use App\Models\WabaCampaign;
use App\Models\WabaCampaignRecipient;
use App\Models\WhatsAppAutomationLog;
use App\Models\WhatsAppConversation;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WhatsAppAnalyticsService
{
    public function dashboard(Request $request): array
    {
        $messages = $this->messageQuery($request);
        $recipients = $this->recipientQuery($request);

        $sent = (clone $messages)->whereIn('direction', ['sent', 'outgoing'])->count();
        $delivered = (clone $messages)->where('status', 'delivered')->count();
        $read = (clone $messages)->where('status', 'read')->count();
        $failed = (clone $messages)->where('status', 'failed')->count();
        $replied = (clone $messages)->where('direction', 'received')->count();

        return [
            'totals' => compact('sent', 'delivered', 'read', 'failed', 'replied') + [
                'reply_rate' => $sent > 0 ? round(($replied / $sent) * 100, 1) : 0,
                'open_conversations' => WhatsAppConversation::query()->where('status', '!=', 'resolved')->count(),
            ],
            'campaigns' => (clone $recipients)
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'templates' => (clone $messages)
                ->select('template_id', DB::raw('count(*) as total'))
                ->whereNotNull('template_id')
                ->groupBy('template_id')
                ->orderByDesc('total')
                ->limit(10)
                ->get(),
            'automation' => WhatsAppAutomationLog::query()
                ->select('status', DB::raw('count(*) as total'))
                ->groupBy('status')
                ->pluck('total', 'status'),
            'campaign_list' => WabaCampaign::query()
                ->with('metaWabaAccount')
                ->latest()
                ->limit(10)
                ->get(),
        ];
    }

    private function messageQuery(Request $request): Builder
    {
        return WhatsAppMessage::query()
            ->when($request->filled('account_id'), fn ($query) => $query->where('meta_waba_account_id', $request->integer('account_id')))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')));
    }

    private function recipientQuery(Request $request): Builder
    {
        return WabaCampaignRecipient::query()
            ->whereHas('campaign', function ($query) use ($request) {
                $query->when($request->filled('account_id'), fn ($campaignQuery) => $campaignQuery->where('meta_waba_account_id', $request->integer('account_id')));
            })
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('created_at', '>=', $request->input('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('created_at', '<=', $request->input('date_to')));
    }
}
