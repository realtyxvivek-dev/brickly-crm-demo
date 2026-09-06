<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\LoanPartnerBank;
use App\Services\LoanPartnerBankService;
use Illuminate\Http\Request;

class LoanPartnerBankController extends Controller
{
    protected LoanPartnerBankService $loanPartnerBankService;

    public function __construct(LoanPartnerBankService $loanPartnerBankService)
    {
        $this->middleware(['auth', 'role:admin,crm']);
        $this->loanPartnerBankService = $loanPartnerBankService;
    }

    public function index()
    {
        return view('admin.loan-partners.index', [
            'banks' => LoanPartnerBank::orderBy('display_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:loan_partner_banks,name'],
            'short_offer_text' => ['nullable', 'string', 'max:120'],
            'interest_rate_text' => ['nullable', 'string', 'max:60'],
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:1024'],
        ]);

        $nextOrder = (int) LoanPartnerBank::max('display_order');

        $this->loanPartnerBankService->createBank([
            'name' => $validated['name'],
            'short_offer_text' => $validated['short_offer_text'] ?? null,
            'interest_rate_text' => $validated['interest_rate_text'] ?? null,
            'status' => 'active',
            'display_order' => $nextOrder + 1,
        ], $request->file('logo'));

        return redirect()
            ->route('admin.loan-partners.index')
            ->with('success', 'Loan partner add ho gaya. Ab ye sab advisors ke public profile par dikhega.');
    }

    public function update(Request $request, LoanPartnerBank $loanPartner)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150', 'unique:loan_partner_banks,name,' . $loanPartner->id],
            'short_offer_text' => ['nullable', 'string', 'max:120'],
            'interest_rate_text' => ['nullable', 'string', 'max:60'],
            'status' => ['required', 'in:active,inactive'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp,svg', 'max:1024'],
        ]);

        $this->loanPartnerBankService->updateBank($loanPartner, [
            'name' => $validated['name'],
            'short_offer_text' => $validated['short_offer_text'] ?? null,
            'interest_rate_text' => $validated['interest_rate_text'] ?? null,
            'status' => $validated['status'],
        ], $request->file('logo'));

        return redirect()
            ->route('admin.loan-partners.index')
            ->with('success', 'Loan partner updated.');
    }

    public function destroy(LoanPartnerBank $loanPartner)
    {
        $this->loanPartnerBankService->deleteLogo($loanPartner);
        $loanPartner->delete();

        return redirect()
            ->route('admin.loan-partners.index')
            ->with('success', 'Loan partner removed.');
    }
}
