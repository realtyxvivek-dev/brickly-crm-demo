<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MetaWabaAccount;
use App\Models\WhatsAppTemplate;
use App\Services\MetaWabaApiService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;

class TemplateManagementController extends Controller
{
    public function index(): View
    {
        $templates = WhatsAppTemplate::query()
            ->where('provider', 'meta_waba')
            ->with('metaWabaAccount')
            ->latest()
            ->paginate(20);

        return view('admin.template-management.index', compact('templates'));
    }

    public function create(Request $request): View
    {
        return $this->formView(new WhatsAppTemplate([
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $request->integer('meta_waba_account_id') ?: null,
            'language' => 'en_US',
            'category' => 'UTILITY',
            'status' => 'DRAFT',
            'is_active' => false,
            'components' => [],
            'raw_payload' => [],
        ]));
    }

    public function edit(string $template): View
    {
        return $this->formView($this->resolveTemplate($template));
    }

    public function store(Request $request, MetaWabaApiService $service): RedirectResponse
    {
        return $this->persist($request, $service);
    }

    public function update(Request $request, string $template, MetaWabaApiService $service): RedirectResponse
    {
        return $this->persist($request, $service, $this->resolveTemplate($template));
    }

    private function formView(WhatsAppTemplate $template): View
    {
        $accounts = MetaWabaAccount::query()->where('is_active', true)->orderByDesc('is_default')->orderBy('name')->get();
        $state = $this->stateFromTemplate($template);

        return view('admin.template-management.form', compact('template', 'accounts', 'state'));
    }

    private function persist(Request $request, MetaWabaApiService $service, ?WhatsAppTemplate $template = null): RedirectResponse
    {
        $validated = $request->validate([
            'intent' => 'required|string|in:draft,submit',
            'meta_waba_account_id' => 'nullable|integer|exists:meta_waba_accounts,id',
            'name' => 'required|string|max:512|regex:/^[a-z0-9_]+$/',
            'language' => 'required|string|max:20',
            'sector' => 'nullable|string|max:80',
            'template_category' => 'nullable|string|max:80',
            'category' => 'required|string|in:MARKETING,UTILITY,AUTHENTICATION',
            'wallet_markup_override' => 'nullable|numeric|min:0|max:100',
            'header_type' => 'required|string|in:none,text,image,video,document,location',
            'header_text' => 'nullable|required_if:header_type,text|string|max:60',
            'body_text' => 'required|string|max:1024',
            'body_examples' => 'nullable|array',
            'body_examples.*' => 'nullable|string|max:200',
            'footer_text' => 'nullable|string|max:60',
            'button_mode' => 'required|string|in:none,cta,quick,all',
            'quick_reply_buttons' => 'nullable|array|max:3',
            'quick_reply_buttons.*' => 'nullable|string|max:25',
            'phone_button_text' => 'nullable|string|max:25',
            'phone_button_number' => 'nullable|string|max:20',
            'url_button_text' => 'nullable|string|max:25',
            'url_button_url' => 'nullable|url|max:2000',
        ]);

        $variables = $this->bodyVariables($validated['body_text']);
        $examples = $this->normalizedExamples($validated['body_examples'] ?? [], count($variables));
        if ($variables && count(array_filter($examples, fn ($value) => filled($value))) < count($variables)) {
            return back()
                ->withInput()
                ->withErrors(['body_examples' => 'Har body variable ke liye realistic example required hai.']);
        }

        $components = $this->buildComponents($validated, $examples);
        $account = filled($validated['meta_waba_account_id'] ?? null)
            ? MetaWabaAccount::query()->find((int) $validated['meta_waba_account_id'])
            : MetaWabaAccount::defaultAccount();

        $payload = [
            'name' => $validated['name'],
            'category' => $validated['category'],
            'language' => $validated['language'],
            'components' => $components,
            'builder' => Arr::only($validated, [
                'sector',
                'template_category',
                'wallet_markup_override',
                'header_type',
                'button_mode',
            ]) + ['body_examples' => $examples],
        ];

        $template ??= new WhatsAppTemplate();
        $template->fill([
            'provider' => 'meta_waba',
            'meta_waba_account_id' => $account?->id,
            'template_id' => $template->template_id ?: $validated['name'],
            'name' => $validated['name'],
            'content' => $validated['body_text'],
            'components' => $components,
            'raw_payload' => $payload,
            'category' => $validated['category'],
            'language' => $validated['language'],
            'status' => $validated['intent'] === 'submit' ? 'PENDING' : 'DRAFT',
            'is_active' => false,
        ]);
        $template->save();

        if ($validated['intent'] === 'submit') {
            try {
                $result = ($account ? $service->forAccount($account) : $service)->createTemplate(Arr::except($payload, ['builder']));
                if (!($result['success'] ?? false)) {
                    return back()
                        ->withInput()
                        ->withErrors(['submit' => $result['error'] ?? 'Template submission failed. Draft saved locally.']);
                }

                $status = strtoupper((string) (data_get($result, 'data.status') ?: 'PENDING'));
                $template->forceFill([
                    'template_id' => (string) (data_get($result, 'data.id') ?: $template->template_id),
                    'raw_payload' => array_merge((array) ($result['data'] ?? []), ['builder' => $payload['builder']]),
                    'status' => $status,
                    'is_active' => $status === 'APPROVED',
                    'rejection_reason' => null,
                ])->save();
            } catch (\Throwable $e) {
                Log::warning('Template management submit failed', ['error' => $e->getMessage(), 'template_id' => $template->id]);

                return back()
                    ->withInput()
                    ->withErrors(['submit' => 'Meta submit failed: ' . $e->getMessage() . ' Draft saved locally.']);
            }
        }

        return redirect()
            ->route('template-management.edit', $template->id)
            ->with('success', $validated['intent'] === 'submit'
                ? 'Template submitted to Meta. Sync templates after review to refresh status.'
                : 'Template draft saved.');
    }

    private function resolveTemplate(string $template): WhatsAppTemplate
    {
        return WhatsAppTemplate::query()
            ->whereKey($template)
            ->orWhere('template_id', $template)
            ->orWhere('name', $template)
            ->firstOrFail();
    }

    private function stateFromTemplate(WhatsAppTemplate $template): array
    {
        $components = $template->components ?: [];
        $builder = data_get($template->raw_payload, 'builder', []);
        $oldQuickReplies = old('quick_reply_buttons');
        $state = [
            'account_id' => old('meta_waba_account_id', $template->meta_waba_account_id),
            'name' => old('name', $template->name),
            'language' => old('language', $template->language ?: 'en_US'),
            'sector' => old('sector', data_get($builder, 'sector', 'General')),
            'template_category' => old('template_category', data_get($builder, 'template_category', 'Reminder')),
            'category' => old('category', $template->category ?: 'UTILITY'),
            'wallet_markup_override' => old('wallet_markup_override', data_get($builder, 'wallet_markup_override')),
            'header_type' => old('header_type', data_get($builder, 'header_type', 'none')),
            'header_text' => old('header_text', ''),
            'body_text' => old('body_text', $template->content),
            'body_examples' => old('body_examples', data_get($builder, 'body_examples', [])),
            'footer_text' => old('footer_text', ''),
            'button_mode' => old('button_mode', data_get($builder, 'button_mode', 'none')),
            'quick_reply_buttons' => $oldQuickReplies ?? [],
            'phone_button_text' => old('phone_button_text', ''),
            'phone_button_number' => old('phone_button_number', ''),
            'url_button_text' => old('url_button_text', ''),
            'url_button_url' => old('url_button_url', ''),
        ];

        foreach ($components as $component) {
            $type = strtoupper((string) ($component['type'] ?? ''));
            if ($type === 'HEADER') {
                $format = strtolower((string) ($component['format'] ?? 'text'));
                $state['header_type'] = $format === 'text' ? 'text' : $format;
                $state['header_text'] = old('header_text', (string) ($component['text'] ?? ''));
            }
            if ($type === 'FOOTER') {
                $state['footer_text'] = old('footer_text', (string) ($component['text'] ?? ''));
            }
            if ($type === 'BUTTONS') {
                foreach (($component['buttons'] ?? []) as $button) {
                    $buttonType = strtoupper((string) ($button['type'] ?? ''));
                    if ($buttonType === 'QUICK_REPLY') {
                        if ($oldQuickReplies === null) {
                            $state['quick_reply_buttons'][] = $button['text'] ?? '';
                        }
                    } elseif ($buttonType === 'PHONE_NUMBER') {
                        $state['phone_button_text'] = old('phone_button_text', $button['text'] ?? '');
                        $state['phone_button_number'] = old('phone_button_number', $button['phone_number'] ?? '');
                    } elseif ($buttonType === 'URL') {
                        $state['url_button_text'] = old('url_button_text', $button['text'] ?? '');
                        $state['url_button_url'] = old('url_button_url', $button['url'] ?? '');
                    }
                }
            }
        }

        if ($state['button_mode'] === 'none' && ($state['quick_reply_buttons'] || $state['phone_button_text'] || $state['url_button_text'])) {
            $hasQuick = count(array_filter($state['quick_reply_buttons'])) > 0;
            $hasCta = filled($state['phone_button_text']) || filled($state['url_button_text']);
            $state['button_mode'] = $hasQuick && $hasCta ? 'all' : ($hasQuick ? 'quick' : 'cta');
        }

        return $state;
    }

    private function buildComponents(array $data, array $examples): array
    {
        $components = [];

        if ($data['header_type'] === 'text' && filled($data['header_text'] ?? null)) {
            $components[] = ['type' => 'HEADER', 'format' => 'TEXT', 'text' => $data['header_text']];
        } elseif (in_array($data['header_type'], ['image', 'video', 'document', 'location'], true)) {
            $components[] = ['type' => 'HEADER', 'format' => strtoupper($data['header_type'])];
        }

        $body = ['type' => 'BODY', 'text' => $data['body_text']];
        if ($examples) {
            $body['example'] = ['body_text' => [$examples]];
        }
        $components[] = $body;

        if (filled($data['footer_text'] ?? null)) {
            $components[] = ['type' => 'FOOTER', 'text' => $data['footer_text']];
        }

        $buttons = [];
        if (in_array($data['button_mode'], ['quick', 'all'], true)) {
            foreach (($data['quick_reply_buttons'] ?? []) as $label) {
                if (filled($label) && count($buttons) < 3) {
                    $buttons[] = ['type' => 'QUICK_REPLY', 'text' => Str::limit(trim($label), 25, '')];
                }
            }
        }
        if (in_array($data['button_mode'], ['cta', 'all'], true)) {
            if (filled($data['phone_button_text'] ?? null) && filled($data['phone_button_number'] ?? null) && count($buttons) < 3) {
                $buttons[] = [
                    'type' => 'PHONE_NUMBER',
                    'text' => Str::limit($data['phone_button_text'], 25, ''),
                    'phone_number' => preg_replace('/[^0-9+]/', '', (string) $data['phone_button_number']),
                ];
            }
            if (filled($data['url_button_text'] ?? null) && filled($data['url_button_url'] ?? null) && count($buttons) < 3) {
                $buttons[] = [
                    'type' => 'URL',
                    'text' => Str::limit($data['url_button_text'], 25, ''),
                    'url' => $data['url_button_url'],
                ];
            }
        }

        if ($buttons) {
            $components[] = ['type' => 'BUTTONS', 'buttons' => $buttons];
        }

        return $components;
    }

    private function bodyVariables(string $body): array
    {
        preg_match_all('/\{\{\s*(\d+)\s*\}\}/', $body, $matches);

        return collect($matches[1] ?? [])->map(fn ($value) => (int) $value)->unique()->sort()->values()->all();
    }

    private function normalizedExamples(array $examples, int $count): array
    {
        return collect(range(0, max(0, $count - 1)))
            ->map(fn ($index) => trim((string) ($examples[$index] ?? '')))
            ->all();
    }
}
