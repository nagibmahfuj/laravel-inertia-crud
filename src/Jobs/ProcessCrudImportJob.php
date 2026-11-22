<?php

namespace NagibMahfuj\Crud\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use NagibMahfuj\Crud\Events\CrudImportFailed;
use NagibMahfuj\Crud\Services\CsvImportService;

/**
 * Queued worker job for large CSV imports.
 * Stores only serializable primitives and resolves the controller's
 * fresh `resourceConfig()` at runtime so transform closures work safely.
 */
class ProcessCrudImportJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public string $importId,
        public string $controllerClass,
        public string $disk,
        public string $filePath,
        public mixed $userId,
        public string $originalFilename,
    ) {
        $this->tries   = max(1, (int) config('crud.import.tries', 3));
        $this->timeout = max(30, (int) config('crud.import.timeout', 600));
    }

    public function handle(CsvImportService $importService): void
    {
        $controller = app($this->controllerClass);
        $config     = $controller->getResourceConfigForImport();

        $importService->processStoredFile(
            importId: $this->importId,
            resourceConfig: $config,
            diskName: $this->disk,
            filePath: $this->filePath,
            userId: $this->userId,
            originalFilename: $this->originalFilename,
        );
    }

    public function failed(\Throwable $exception): void
    {
        $importService = app(CsvImportService::class);
        $state         = $importService->getStatus($this->importId, $this->userId) ?? [
            'id'       => $this->importId,
            'resource' => class_basename($this->controllerClass),
            'user_id'  => $this->userId,
        ];

        $state['status']        = 'failed';
        $state['error_message'] = $exception->getMessage();
        $state['finished_at']   = now()->toIso8601String();
        $importService->saveStatus($this->importId, $state);

        CrudImportFailed::dispatch(
            $this->importId,
            (string) ($state['resource'] ?? 'resource'),
            $this->userId,
            $exception->getMessage(),
            $state,
        );
    }
}

