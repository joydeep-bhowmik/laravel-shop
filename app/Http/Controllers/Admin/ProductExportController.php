<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ProductCsvService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductExportController extends Controller
{
    /**
     * Stream every variant as a CSV download.
     */
    public function __invoke(ProductCsvService $csv): StreamedResponse
    {
        return response()->streamDownload(function () use ($csv): void {
            $output = fopen('php://output', 'w');

            foreach ($csv->export() as $row) {
                fputcsv($output, $row);
            }

            fclose($output);
        }, 'variants-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
