<?php

namespace App\Services;

use App\Models\PayrollPayslip;
use App\Models\PayrollPayslipSetting;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class PayslipPdfService
{
    public function resolvePdfBinary(): ?string
    {
        $configuredBinary = config('hr.pdf.binary');
        $fallbacks = config('hr.pdf.fallback_paths', []);
        $candidates = array_filter(array_merge(
            $configuredBinary ? [$configuredBinary] : [],
            is_array($fallbacks) ? $fallbacks : []
        ));

        foreach ($candidates as $candidate) {
            if (is_string($candidate) && file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    public function storePayslipPdf(PayrollPayslip $payslip, PayrollPayslipSetting $settings): array
    {
        $html = view('finance-manager.payslips.pdf', [
            'payslip' => $payslip->load('user'),
            'settings' => $settings,
            'snapshot' => $payslip->snapshot_json,
        ])->render();

        return $this->storeHtmlAsPdf($html, 'attendance/payslips/' . now()->format('Y/m'), $payslip->payslip_number);
    }

    public function storeHtmlAsPdf(string $html, string $directory, string $baseName): array
    {
        $disk = Storage::disk('local');
        $directory = trim($directory, '/');
        $htmlPath = $directory . '/' . Str::slug($baseName) . '.html';
        $pdfPath = $directory . '/' . Str::slug($baseName) . '.pdf';

        $disk->put($htmlPath, $html);
        $absolutePdf = storage_path('app/' . $pdfPath);

        if (class_exists(Dompdf::class)) {
            $options = new Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultPaperSize', 'a4');

            $dompdf = new Dompdf($options);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4');
            $dompdf->render();

            $disk->put($pdfPath, $dompdf->output());

            return [
                'disk' => 'local',
                'path' => $pdfPath,
                'mime_type' => 'application/pdf',
                'generated_with' => 'dompdf',
            ];
        }

        $absoluteHtml = storage_path('app/' . $htmlPath);

        $binary = $this->resolvePdfBinary();
        if ($binary) {
            $process = new Process([$binary, '--enable-local-file-access', $absoluteHtml, $absolutePdf]);
            $process->setTimeout((int) config('hr.pdf.timeout', 60));
            $process->run();

            if ($process->isSuccessful() && file_exists($absolutePdf)) {
                return [
                    'disk' => 'local',
                    'path' => $pdfPath,
                    'mime_type' => 'application/pdf',
                    'generated_with' => 'wkhtmltopdf',
                ];
            }
        }

        return [
            'disk' => 'local',
            'path' => $htmlPath,
            'mime_type' => 'text/html; charset=UTF-8',
            'generated_with' => 'html_fallback',
        ];
    }
}
