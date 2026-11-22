<?php

namespace NagibMahfuj\Crud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use NagibMahfuj\Crud\Contracts\RoleResolver;
use NagibMahfuj\Crud\CrudController;
use NagibMahfuj\Crud\Services\ChunkUploadService;
use ReflectionClass;

class CrudDoctorCommand extends Command
{
    protected $signature = 'crud:doctor';

    protected $description = 'Audit Laravel Inertia CRUD configuration, native routes, storage, queues, and registered CrudControllers';

    public function handle(Filesystem $files, ChunkUploadService $chunkUploads): int
    {
        $this->newLine();
        $this->line('  <bg=blue;fg=white;options=bold>  LARAVEL INERTIA CRUD — DOCTOR  </>');
        $this->line('  <fg=gray>Auditing package configuration, routes, storage, queues, and controllers...</>');
        $this->newLine();

        $rows     = [];
        $hasError = false;

        // 1. Config file published
        $configPublished = $files->exists(config_path('crud.php'));
        $rows[] = [
            'Config File',
            $configPublished ? 'OK' : 'WARN',
            $configPublished ? 'config/crud.php published' : 'Using package defaults (run vendor:publish --tag=crud-config)',
        ];

        // 2. Role Resolver
        $resolver = app()->bound(RoleResolver::class) ? get_class(app(RoleResolver::class)) : 'Not bound';
        $rows[]   = ['Role Resolver', $resolver !== 'Not bound' ? 'OK' : 'FAIL', $resolver];
        if ($resolver === 'Not bound') {
            $hasError = true;
        }

        // 3. Native package routes
        $chunkRouteName   = (string) config('crud.chunk_upload.route_name', 'crud.chunk-upload');
        $optionsRouteName = (string) config('crud.options.route_name', 'crud.options');
        $importRouteName  = (string) config('crud.import.route_name', 'crud.import') . '.status';

        $rows[] = [
            'Chunk Upload Route',
            config('crud.chunk_upload.enabled', true)
                ? (Route::has($chunkRouteName) ? 'OK' : 'FAIL')
                : 'DISABLED',
            config('crud.chunk_upload.enabled', true)
                ? (Route::has($chunkRouteName) ? 'POST ' . route($chunkRouteName, [], false) : "Missing route '{$chunkRouteName}'")
                : 'Disabled in config/crud.php',
        ];

        $rows[] = [
            'Async Options Route',
            config('crud.options.enabled', true)
                ? (Route::has($optionsRouteName) ? 'OK' : 'FAIL')
                : 'DISABLED',
            config('crud.options.enabled', true)
                ? (Route::has($optionsRouteName) ? 'GET ' . route($optionsRouteName, [], false) : "Missing route '{$optionsRouteName}'")
                : 'Disabled in config/crud.php',
        ];

        $rows[] = [
            'Import Status Route',
            config('crud.import.enabled', true)
                ? (Route::has($importRouteName) ? 'OK' : 'FAIL')
                : 'DISABLED',
            config('crud.import.enabled', true)
                ? (Route::has($importRouteName) ? "Registered ({$importRouteName})" : "Missing route '{$importRouteName}'")
                : 'Disabled in config/crud.php',
        ];

        // 4. Queue driver vs import mode
        $importMode  = (string) config('crud.import.mode', 'auto');
        $queueDriver = (string) config('queue.default', 'sync');
        $queueWarn   = in_array($importMode, ['queued', 'auto'], true) && $queueDriver === 'sync';
        $rows[] = [
            'Import Queue Driver',
            $queueWarn ? 'WARN' : 'OK',
            $queueWarn
                ? "import.mode='{$importMode}' but QUEUE_CONNECTION='sync' (jobs will run synchronously)"
                : "import.mode='{$importMode}', queue='{$queueDriver}'",
        ];

        // 5. Chunk temp path writability
        $tempDir = $chunkUploads->tempBasePath();
        $writable = is_dir($tempDir) ? is_writable($tempDir) : is_writable(dirname($tempDir));
        $rows[]   = [
            'Chunk Temp Storage',
            $writable ? 'OK' : 'FAIL',
            $tempDir . ($writable ? ' (writable)' : ' (not writable)'),
        ];
        if (!$writable) {
            $hasError = true;
        }

        // 6. Frontend published components check
        $crudUiPath = resource_path('js/components/crud/ResourceIndex.tsx');
        $rows[] = [
            'Published Components',
            $files->exists($crudUiPath) ? 'OK' : 'WARN',
            $files->exists($crudUiPath)
                ? 'resources/js/components/crud/ detected'
                : 'Not published (run vendor:publish --tag=crud-assets)',
        ];

        $this->table(['Check', 'Status', 'Details'], $rows);

        // 7. Audit discovered CrudController subclasses
        $controllerRows = $this->auditControllers($files, $hasError);
        if (!empty($controllerRows)) {
            $this->newLine();
            $this->line('  <fg=cyan;options=bold>Discovered CrudControllers</>');
            $this->table(['Controller', 'Status', 'Notes'], $controllerRows);
        }

        $this->newLine();
        if ($hasError) {
            $this->components->error('Doctor found one or more failing checks.');
            return self::FAILURE;
        }

        $this->components->info('All critical CRUD checks passed.');
        return self::SUCCESS;
    }

    protected function auditControllers(Filesystem $files, bool &$hasError): array
    {
        $controllersDir = app_path('Http/Controllers');
        if (!$files->isDirectory($controllersDir)) {
            return [];
        }

        $rows = [];

        foreach ($files->allFiles($controllersDir) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $relative = str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            $fqcn     = 'App\\Http\\Controllers\\' . $relative;

            if (!class_exists($fqcn)) {
                continue;
            }

            $ref = new ReflectionClass($fqcn);
            if ($ref->isAbstract() || !$ref->isSubclassOf(CrudController::class)) {
                continue;
            }

            $issues = [];
            try {
                /** @var CrudController $instance */
                $instance = app($fqcn);
                $config   = $instance->getResourceConfigForImport();

                $modelClass  = $config['model'] ?? null;
                $routePrefix = $config['route_prefix'] ?? null;

                if (!$modelClass || !class_exists($modelClass)) {
                    $issues[] = 'Missing/invalid model class';
                    $hasError = true;
                } else {
                    if (!Gate::getPolicyFor($modelClass)) {
                        $issues[] = 'No policy registered for ' . class_basename($modelClass);
                    }
                }

                if ($routePrefix) {
                    if (!Route::has("{$routePrefix}.index")) {
                        $issues[] = "Route '{$routePrefix}.index' not registered";
                    }
                    if (!config("crud.resources.{$routePrefix}")) {
                        $issues[] = "Missing config('crud.resources.{$routePrefix}')";
                    }
                }

                foreach ($config['fields'] ?? [] as $field) {
                    if (in_array($field['type'] ?? '', ['chunked-file', 'file', 'image'], true) && !empty($field['disk'])) {
                        if (!config("filesystems.disks.{$field['disk']}")) {
                            $issues[] = "Unknown disk '{$field['disk']}' on field '{$field['key']}'";
                            $hasError = true;
                        }
                    }
                }
            } catch (\Throwable $e) {
                $issues[] = 'Error inspecting config: ' . $e->getMessage();
                $hasError = true;
            }

            $rows[] = [
                class_basename($fqcn),
                empty($issues) ? 'OK' : 'WARN',
                empty($issues) ? 'Healthy' : implode('; ', $issues),
            ];
        }

        return $rows;
    }
}

