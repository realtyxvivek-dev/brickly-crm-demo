<?php

namespace App\Services;

use App\Models\LoanPartnerBank;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LoanPartnerBankService
{
    public function createBank(array $data, ?UploadedFile $logo = null): LoanPartnerBank
    {
        DB::beginTransaction();

        try {
            $bank = LoanPartnerBank::create($data);

            if ($logo) {
                $this->uploadLogo($bank, $logo);
            }

            DB::commit();

            return $bank;
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function updateBank(LoanPartnerBank $bank, array $data, ?UploadedFile $logo = null): LoanPartnerBank
    {
        DB::beginTransaction();

        try {
            $bank->update($data);

            if ($logo) {
                $this->uploadLogo($bank, $logo);
            }

            DB::commit();

            return $bank->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function uploadLogo(LoanPartnerBank $bank, UploadedFile $logo): string
    {
        if ($bank->logo) {
            $this->deleteLogo($bank);
        }

        $filename = time() . '_' . uniqid() . '.' . $logo->getClientOriginalExtension();

        $logo->storeAs('loan-partners/logos', $filename, 'public');
        $bank->update(['logo' => $filename]);

        return $filename;
    }

    public function deleteLogo(LoanPartnerBank $bank): void
    {
        if ($bank->logo && Storage::disk('public')->exists('loan-partners/logos/' . $bank->logo)) {
            Storage::disk('public')->delete('loan-partners/logos/' . $bank->logo);
        }

        $bank->update(['logo' => null]);
    }
}
