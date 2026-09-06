<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MetaLeadCheckImportParser
{
    public function parse(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $rows = in_array($extension, ['xlsx', 'xls'], true)
            ? $this->parseExcelRows($file->getRealPath())
            : $this->parseCsvRows($file->getRealPath());

        if (count($rows) < 2) {
            return [];
        }

        $headers = array_map(fn ($header) => $this->normalizeHeader((string) $header), array_shift($rows));

        return collect($rows)
            ->map(function (array $row, int $index) use ($headers) {
                $assoc = [];
                foreach ($headers as $column => $header) {
                    if ($header === '') {
                        continue;
                    }
                    $assoc[$header] = trim((string) ($row[$column] ?? ''));
                }

                return $this->normalizeRow($assoc, $index + 2);
            })
            ->filter(fn (array $row) => $row['raw_text'] !== '' || $row['leadgen_id'] || $row['phone'] || $row['email'] || $row['name'])
            ->take(1000)
            ->values()
            ->all();
    }

    public function filterByDate(array $rows, ?string $from, ?string $to): array
    {
        $fromDate = $from ? Carbon::parse($from)->startOfDay() : null;
        $toDate = $to ? Carbon::parse($to)->endOfDay() : null;

        if (!$fromDate && !$toDate) {
            return $rows;
        }

        return collect($rows)
            ->filter(function (array $row) use ($fromDate, $toDate) {
                $submittedAt = $this->parseDate($row['submitted_at'] ?? null);
                if (!$submittedAt) {
                    return false;
                }

                if ($fromDate && $submittedAt->lt($fromDate)) {
                    return false;
                }

                if ($toDate && $submittedAt->gt($toDate)) {
                    return false;
                }

                return true;
            })
            ->values()
            ->all();
    }

    private function parseExcelRows(string $path): array
    {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet();
        $highestColumnIndex = Coordinate::columnIndexFromString($worksheet->getHighestColumn());
        $rows = [];

        for ($row = 1; $row <= $worksheet->getHighestRow(); $row++) {
            $rowData = [];
            for ($col = 1; $col <= $highestColumnIndex; $col++) {
                $cell = $worksheet->getCell([$col, $row]);
                $value = $cell->getFormattedValue();
                $rowData[] = is_scalar($value) ? trim((string) $value) : '';
            }

            if (!empty(array_filter($rowData, fn ($value) => trim((string) $value) !== ''))) {
                $rows[] = $rowData;
            }
        }

        return $rows;
    }

    private function parseCsvRows(string $path): array
    {
        $content = $this->normalizeCsvContent((string) file_get_contents($path));
        $delimiter = $this->detectCsvDelimiter($content);
        $handle = fopen('php://temp', 'r+');
        if (!$handle) {
            return [];
        }

        fwrite($handle, $content);
        rewind($handle);

        $rows = [];
        while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
            $row = array_map(fn ($value) => trim((string) $value), $row);
            if (!empty(array_filter($row, fn ($value) => $value !== ''))) {
                $rows[] = $row;
            }
        }

        fclose($handle);

        return $rows;
    }

    private function normalizeRow(array $row, int $rowNumber): array
    {
        $leadgenId = $this->cleanIdentifier($this->firstValue($row, [
            'leadgen_id', 'lead_id', 'leadid', 'id', 'leadgenid', 'meta_lead_id', 'facebook_lead_id', 'fb_lead_id',
        ]));
        $pageId = $this->cleanIdentifier($this->firstValue($row, ['page_id', 'pageid', 'facebook_page_id']));
        $formId = $this->cleanIdentifier($this->firstValue($row, ['form_id', 'lead_form_id', 'formid', 'facebook_form_id']));
        $name = $this->firstValue($row, ['full_name', 'name', 'customer_name', 'lead_name', 'first_name']);
        $phone = $this->cleanIdentifier($this->firstValue($row, ['phone_number', 'phone', 'mobile', 'mobile_number', 'whatsapp_number']), false);
        $email = $this->firstValue($row, ['email', 'email_id', 'email_address']);
        $submittedAt = $this->firstValue($row, ['created_time', 'created_at', 'submitted_at', 'submission_time', 'date', 'time']);
        $rawText = collect($row)->filter(fn ($value) => trim((string) $value) !== '')->map(fn ($value, $key) => $key . ': ' . $value)->implode("\n");

        return [
            'row_number' => $rowNumber,
            'leadgen_id' => $leadgenId,
            'page_id' => $pageId,
            'form_id' => $formId,
            'name' => $name,
            'phone' => $phone,
            'email' => $email,
            'submitted_at' => $submittedAt,
            'created_time' => $submittedAt,
            'raw_text' => $rawText,
            'raw' => $row,
        ];
    }

    private function firstValue(array $row, array $keys): ?string
    {
        foreach ($keys as $key) {
            $normalized = $this->normalizeHeader($key);
            if (array_key_exists($normalized, $row) && trim((string) $row[$normalized]) !== '') {
                return trim((string) $row[$normalized]);
            }
        }

        return null;
    }

    private function normalizeHeader(string $header): string
    {
        return Str::of($header)->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->value();
    }

    private function cleanIdentifier(?string $value, bool $digitsOnly = true): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim(preg_replace('/^[a-z]+:/i', '', $value) ?? $value);
        if ($value === '') {
            return null;
        }

        return $digitsOnly ? (preg_replace('/\D+/', '', $value) ?: null) : $value;
    }

    private function parseDate(?string $value): ?Carbon
    {
        if (!$value) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeCsvContent(string $content): string
    {
        if (str_starts_with($content, "\xFF\xFE")) {
            $content = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
        } elseif (str_starts_with($content, "\xFE\xFF")) {
            $content = (string) @iconv('UTF-16BE', 'UTF-8//IGNORE', $content);
        } elseif (str_contains(substr($content, 0, 200), "\0")) {
            $content = (string) @iconv('UTF-16LE', 'UTF-8//IGNORE', $content);
        }

        return preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    }

    private function detectCsvDelimiter(string $content): string
    {
        $line = strtok($content, "\r\n") ?: '';
        $candidates = [
            "\t" => substr_count($line, "\t"),
            ',' => substr_count($line, ','),
            ';' => substr_count($line, ';'),
        ];
        arsort($candidates);
        $delimiter = array_key_first($candidates);

        return ($candidates[$delimiter] ?? 0) > 0 ? $delimiter : ',';
    }
}
