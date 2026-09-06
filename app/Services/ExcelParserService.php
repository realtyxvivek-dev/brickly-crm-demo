<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use Illuminate\Support\Facades\Log;

class ExcelParserService
{
    /**
     * Parse Excel file (.xlsx, .xls) and return array of rows
     */
    public function parseExcelFile($file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = [];

            // Get highest row and column
            $highestRow = $worksheet->getHighestRow();
            $highestColumn = $worksheet->getHighestColumn();
            $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);

            // Read all rows
            for ($row = 1; $row <= $highestRow; $row++) {
                $rowData = [];
                for ($col = 1; $col <= $highestColumnIndex; $col++) {
                    $cellValue = $worksheet->getCellByColumnAndRow($col, $row)->getValue();
                    $rowData[] = $cellValue ?? '';
                }
                
                // Skip completely empty rows
                if (!empty(array_filter($rowData, function($val) {
                    return trim($val) !== '';
                }))) {
                    $rows[] = $rowData;
                }
            }

            return $rows;

        } catch (\Exception $e) {
            Log::error("Excel parsing error: " . $e->getMessage());
            throw new \Exception("Failed to parse Excel file: " . $e->getMessage());
        }
    }

    /**
     * Get first N rows for preview
     */
    public function getPreviewRows($file, int $limit = 10): array
    {
        $allRows = $this->parseExcelFile($file);
        return array_slice($allRows, 0, $limit + 1); // +1 for header
    }

    /**
     * Detect file type and parse accordingly
     */
    public function parseFile($file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseExcelFile($file);
        } elseif ($extension === 'csv') {
            return $this->parseCsvFile($file);
        } else {
            throw new \Exception("Unsupported file type: {$extension}");
        }
    }

    /**
     * Parse CSV file
     */
    public function parseCsvFile($file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        
        while (($row = fgetcsv($handle)) !== false) {
            // Skip completely empty rows
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }
        
        fclose($handle);
        return $rows;
    }
}

