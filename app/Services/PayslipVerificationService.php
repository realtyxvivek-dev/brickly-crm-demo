<?php

namespace App\Services;

use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipSetting;
use Illuminate\Support\Str;

class PayslipVerificationService
{
    public function ensureToken(PayrollPayslip $payslip): PayrollPayslip
    {
        if (!$payslip->verification_token) {
            $payslip->forceFill([
                'verification_token' => Str::random(48),
            ])->save();
        }

        return $payslip->fresh();
    }

    public function ensurePdfIsBranded(PayrollPayslip $payslip, PayslipPdfService $pdfService): PayrollPayslip
    {
        $payslip = $this->ensureToken($payslip);
        $settings = PayrollPayslipSetting::query()->firstOrCreate([], [
            'company_name' => config('app.name', 'Base CRM'),
        ]);

        $pdf = $pdfService->storePayslipPdf($payslip, $settings);
        $payslip->forceFill([
            'pdf_path' => $pdf['path'],
        ])->save();

        return $payslip->fresh();
    }

    public function verificationUrl(PayrollPayslip $payslip): string
    {
        $token = $this->ensureToken($payslip)->verification_token;

        return route('payslips.verify', $token);
    }

    public function qrImageUrl(PayrollPayslip $payslip): string
    {
        return 'https://quickchart.io/qr?size=150&margin=1&ecLevel=M&text=' . urlencode($this->verificationUrl($payslip));
    }
}
