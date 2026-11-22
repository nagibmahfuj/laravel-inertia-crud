<?php

namespace NagibMahfuj\Crud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NagibMahfuj\Crud\Commands\CrudCleanupChunksCommand;
use NagibMahfuj\Crud\Commands\CrudDoctorCommand;
use NagibMahfuj\Crud\Commands\CrudGenerateCommand;
use NagibMahfuj\Crud\Commands\CrudInstallCommand;
use NagibMahfuj\Crud\Contracts\RoleResolver;
use NagibMahfuj\Crud\Http\Controllers\ChunkUploadController;
use NagibMahfuj\Crud\Http\Controllers\CrudImportController;
use NagibMahfuj\Crud\Http\Controllers\CrudOptionsController;
use NagibMahfuj\Crud\Services\ChunkUploadService;
use NagibMahfuj\Crud\Services\CsvImportService;
use NagibMahfuj\Crud\Services\FileUploadService;
use NagibMahfuj\Crud\Services\OptionsService;
use NagibMahfuj\Crud\Support\EloquentRoleResolver;
use NagibMahfuj\Crud\Support\SpatieRoleResolver;

class CrudServiceProvider extends ServiceProvider
{
    /**
     * Register package services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/crud.php', 'crud');

        $this->app->singleton(ChunkUploadService::class);
        $this->app->singleton(FileUploadService::class);
        $this->app->singleton(OptionsService::class);
        $this->app->singleton(CsvImportService::class, function ($app) {
            return new CsvImportService($app->make(ChunkUploadService::class));
        });
        $this->app->singleton(CrudService::class, function ($app) {
            return new CrudService(
                $app->make(ChunkUploadService::class),
                $app->make(FileUploadService::class),
                $app->make(OptionsService::class),
                $app->make(CsvImportService::class),
            );
        });

        $this->registerRoleResolver();
    }

    /**
     * Bootstrap package services.
     */
    public function boot(): void
    {
        $this->registerRouteMacros();
        $this->registerChunkUploadRoutes();
        $this->registerOptionsRoutes();
        $this->registerImportRoutes();
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the role resolver binding.
     */
    protected function registerRoleResolver(): void
    {
        $this->app->singletonIf(RoleResolver::class, function ($app) {
            $customResolver = config('crud.role_resolver');
            if ($customResolver && class_exists($customResolver)) {
                return $app->make($customResolver);
            }

            if (class_exists(\Spatie\Permission\Traits\HasRoles::class)) {
                return new SpatieRoleResolver();
            }

            return new EloquentRoleResolver();
        });
    }

    /**
     * Register the Route::crudResource() macro.
     */
    protected function registerRouteMacros(): void
    {
        if (Route::hasMacro('crudResource')) {
            return;
        }

        Route::macro('crudResource', function (string $name, string $controller, array $options = []) {
            $uri = $options['uri'] ?? $name;

            if (method_exists($controller, 'export')) {
                Route::get("{$uri}/export", [$controller, 'export'])->name("{$name}.export");
            }

            if (method_exists($controller, 'import')) {
                Route::post("{$uri}/import", [$controller, 'import'])->name("{$name}.import");
            }

            if (method_exists($controller, 'importSample')) {
                Route::get("{$uri}/import-sample", [$controller, 'importSample'])->name("{$name}.import-sample");
            }

            if (method_exists($controller, 'bulkDestroy')) {
                Route::post("{$uri}/bulk-destroy", [$controller, 'bulkDestroy'])->name("{$name}.bulk-destroy");
            }

            if (method_exists($controller, 'bulkRestore')) {
                Route::post("{$uri}/bulk-restore", [$controller, 'bulkRestore'])->name("{$name}.bulk-restore");
            }

            if (method_exists($controller, 'bulkForceDestroy')) {
                Route::post("{$uri}/bulk-force-destroy", [$controller, 'bulkForceDestroy'])->name("{$name}.bulk-force-destroy");
            }

            if (method_exists($controller, 'handleAction')) {
                Route::post("{$uri}/action/{action}", [$controller, 'handleAction'])->name("{$name}.action");
            }

            if (method_exists($controller, 'restore')) {
                Route::post("{$uri}/{id}/restore", [$controller, 'restore'])->name("{$name}.restore");
            }

            if (method_exists($controller, 'forceDestroy')) {
                Route::delete("{$uri}/{id}/force", [$controller, 'forceDestroy'])->name("{$name}.force-destroy");
            }

            return Route::resource($name, $controller, $options);
        });
    }

    /**
     * Register native package chunked upload endpoints when enabled.
     */
    protected function registerChunkUploadRoutes(): void
    {
        if (!config('crud.chunk_upload.enabled', true)) {
            return;
        }

        $route      = '/' . ltrim((string) config('crud.chunk_upload.route', '/crud/chunk-upload'), '/');
        $routeName  = (string) config('crud.chunk_upload.route_name', 'crud.chunk-upload');
        $middleware = (array) config('crud.chunk_upload.middleware', ['web', 'auth']);

        Route::middleware($middleware)->group(function () use ($route, $routeName) {
            Route::post($route, [ChunkUploadController::class, 'upload'])
                ->name($routeName);

            Route::delete("{$route}/{uploadId}", [ChunkUploadController::class, 'cancel'])
                ->where('uploadId', '[a-zA-Z0-9_\-]+')
                ->name("{$routeName}.cancel");
        });
    }

    /**
     * Register native async relationship options endpoint when enabled.
     */
    protected function registerOptionsRoutes(): void
    {
        if (!config('crud.options.enabled', true)) {
            return;
        }

        $route      = '/' . ltrim((string) config('crud.options.route', '/crud/options'), '/');
        $routeName  = (string) config('crud.options.route_name', 'crud.options');
        $middleware = (array) config('crud.options.middleware', ['web', 'auth']);

        Route::middleware($middleware)
            ->get($route, [CrudOptionsController::class, 'search'])
            ->name($routeName);
    }

    /**
     * Register native background import status & failed-rows download endpoints.
     */
    protected function registerImportRoutes(): void
    {
        if (!config('crud.import.enabled', true)) {
            return;
        }

        $route      = '/' . ltrim((string) config('crud.import.route', '/crud/imports'), '/');
        $routeName  = (string) config('crud.import.route_name', 'crud.import');
        $middleware = (array) config('crud.import.middleware', ['web', 'auth']);

        Route::middleware($middleware)->group(function () use ($route, $routeName) {
            Route::get("{$route}/{importId}/status", [CrudImportController::class, 'status'])
                ->where('importId', '[a-zA-Z0-9_\-]+')
                ->name("{$routeName}.status");

            Route::get("{$route}/{importId}/errors", [CrudImportController::class, 'errors'])
                ->where('importId', '[a-zA-Z0-9_\-]+')
                ->name("{$routeName}.errors");
        });
    }

    /**
     * Register publishable assets.
     */
    protected function registerPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../config/crud.php' => config_path('crud.php'),
        ], 'crud-config');

        $this->publishes([
            __DIR__ . '/../resources/js/components/crud' => resource_path('js/components/crud'),
        ], 'crud-components');

        $this->publishes([
            __DIR__ . '/../resources/js/lib/theme.ts' => resource_path('js/lib/crud-theme.ts'),
            __DIR__ . '/../resources/js/hooks'        => resource_path('js/hooks'),
        ], 'crud-lib');

        $this->publishes([
            __DIR__ . '/../resources/js/components/crud' => resource_path('js/components/crud'),
            __DIR__ . '/../resources/js/lib/theme.ts'    => resource_path('js/lib/crud-theme.ts'),
            __DIR__ . '/../resources/js/hooks'           => resource_path('js/hooks'),
        ], 'crud-assets');

        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/crud'),
        ], 'crud-stubs');

        $this->publishes([
            __DIR__ . '/../resources/boost/skills/laravel-inertia-crud' => base_path('.agents/skills/laravel-inertia-crud'),
        ], 'crud-ai');
    }

    /**
     * Register Artisan commands.
     */
    protected function registerCommands(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            CrudGenerateCommand::class,
            CrudCleanupChunksCommand::class,
            CrudInstallCommand::class,
            CrudDoctorCommand::class,
        ]);
    }
}
