<?php

namespace NagibMahfuj\Crud\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use NagibMahfuj\Crud\Services\CsvImportService;

/**
 * Native package controller exposing Cache-backed import progress polling
 * and failed-row error CSV downloads.
 */
class CrudImportController extends Controller
{
    public function __construct(protected CsvImportService $imports)
    {
    }

    /**
     * Return JSON progress state for an import job owned by the current user.
     */
    public function status(string $importId): JsonResponse
    {
        $state = $this->imports->getStatus($importId);

        if (!$state) {
            return response()->json([
                'message' => 'Import session not found or expired.',
            ], 404);
        }

        // Never expose internal server file paths in JSON response
        unset($state['error_report_path'], $state['error_report_disk']);

        return response()->json($state);
    }

    /**
     * Stream the failed-rows CSV report for an import owned by the current user.
     */
    public function errors(string $importId)
    {
        $state = $this->imports->getStatus($importId);

        if (!$state || empty($state['error_report_path'])) {
            abort(404, 'Error report not found.');
        }

        $diskName = (string) ($state['error_report_disk'] ?? config('crud.import.disk', 'local'));
        $disk     = Storage::disk($diskName);

        if (!$disk->exists($state['error_report_path'])) {
            abort(404, 'Error report file has expired.');
        }

        $filename = ($state['resource'] ?? 'import') . "-errors-{$importId}.csv";

        return $disk->download($state['error_report_path'], $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
