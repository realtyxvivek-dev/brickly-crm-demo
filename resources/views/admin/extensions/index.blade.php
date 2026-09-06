@extends('layouts.app')

@section('title', 'Extensions')
@section('page-title', 'Extensions')

@section('content')
<div class="w-full max-w-none space-y-6">
    <div class="rounded-2xl border border-[#DCE8E0] bg-gradient-to-br from-[#0A1F18] to-[#205A44] p-6 text-white shadow-sm">
        <div class="text-xs font-semibold uppercase tracking-[0.2em] text-white/60">Browser tools</div>
        <h2 class="mt-2 text-2xl font-bold">CRM Extensions</h2>
        <p class="mt-2 max-w-3xl text-sm leading-6 text-white/75">
            WhatsApp Web aur Facebook Lead Center extensions yahin se manage karo. Future extensions bhi isi page par add honge.
        </p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
        @include('admin.extensions.partials.extension-card', [
            'key' => 'waExt',
            'title' => 'WhatsApp Web Extension',
            'description' => 'WhatsApp Web messages ko CRM lead/re-enquiry workflow se connect karta hai.',
            'extension' => $whatsAppExtension,
            'generateRoute' => route('admin.mobile-app-update.whatsapp-extension.generate-token'),
            'testRoute' => route('admin.mobile-app-update.whatsapp-extension.test-connection'),
            'tokenHelp' => 'Generate karke WhatsApp extension popup me paste karo.',
        ])

        @include('admin.extensions.partials.extension-card', [
            'key' => 'fbLc',
            'title' => 'Facebook Lead Center Extension',
            'description' => 'Facebook Lead Center scan, CRM compare aur missing lead recovery ke liye.',
            'extension' => $facebookLeadCenterExtension,
            'generateRoute' => route('admin.mobile-app-update.facebook-lead-center-extension.generate-token'),
            'testRoute' => route('admin.mobile-app-update.facebook-lead-center-extension.test-connection'),
            'tokenHelp' => 'Generate karke Facebook extension popup me paste karo.',
        ])
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    async function postJson(url, payload = {}) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            body: JSON.stringify(payload),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok) {
            throw new Error(data.message || 'Request failed.');
        }
        return data;
    }

    function bindExtensionCard(config) {
        const tokenField = document.getElementById(`${config.key}TokenField`);
        const generateBtn = document.getElementById(`${config.key}GenerateTokenBtn`);
        const copyBtn = document.getElementById(`${config.key}CopyTokenBtn`);
        const testBtn = document.getElementById(`${config.key}TestConnectionBtn`);
        const resultBox = document.getElementById(`${config.key}TestResult`);
        if (!tokenField || !generateBtn || !copyBtn || !testBtn || !resultBox) return;

        function setResult(message, success) {
            resultBox.classList.remove('hidden', 'border-emerald-200', 'bg-emerald-50', 'text-emerald-800', 'border-red-200', 'bg-red-50', 'text-red-800');
            resultBox.classList.add(success ? 'border-emerald-200' : 'border-red-200');
            resultBox.classList.add(success ? 'bg-emerald-50' : 'bg-red-50');
            resultBox.classList.add(success ? 'text-emerald-800' : 'text-red-800');
            resultBox.textContent = message;
        }

        generateBtn.addEventListener('click', async function () {
            generateBtn.disabled = true;
            try {
                const data = await postJson(config.generateRoute);
                tokenField.value = data.token || '';
                setResult(data.message || 'Token generated successfully.', true);
            } catch (error) {
                setResult(error.message || 'Token generate failed.', false);
            } finally {
                generateBtn.disabled = false;
            }
        });

        copyBtn.addEventListener('click', async function () {
            const token = tokenField.value.trim();
            if (!token) {
                setResult('Pehle token generate karo.', false);
                return;
            }
            try {
                await navigator.clipboard.writeText(token);
                setResult('Token copied. Extension popup me paste kar do.', true);
            } catch (error) {
                setResult('Copy failed. Manual copy karo.', false);
            }
        });

        testBtn.addEventListener('click', async function () {
            const token = tokenField.value.trim();
            if (!token) {
                setResult('Token paste/generate karo, phir test karo.', false);
                return;
            }
            testBtn.disabled = true;
            try {
                const data = await postJson(config.testRoute, { token });
                const userLine = data.user ? ` User: ${data.user.name} (${data.user.role || 'No role'}).` : '';
                setResult((data.message || 'Connection successful.') + userLine, true);
            } catch (error) {
                setResult(error.message || 'Connection test failed.', false);
            } finally {
                testBtn.disabled = false;
            }
        });
    }

    bindExtensionCard({
        key: 'waExt',
        generateRoute: @json(route('admin.mobile-app-update.whatsapp-extension.generate-token')),
        testRoute: @json(route('admin.mobile-app-update.whatsapp-extension.test-connection')),
    });

    bindExtensionCard({
        key: 'fbLc',
        generateRoute: @json(route('admin.mobile-app-update.facebook-lead-center-extension.generate-token')),
        testRoute: @json(route('admin.mobile-app-update.facebook-lead-center-extension.test-connection')),
    });
});
</script>
@endsection
