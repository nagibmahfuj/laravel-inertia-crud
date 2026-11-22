<?php

namespace NagibMahfuj\Crud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NagibMahfuj\Crud\Commands\CrudCleanupChunksCommand;
use NagibMahfuj\Crud\Commands\CrudGenerateCommand;
use NagibMahfuj\Crud\Contracts\RoleResolver;
use NagibMahfuj\Crud\Http\Controllers\ChunkUploadController;
use NagibMahfuj\Crud\Services\ChunkUploadService;
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
        $this->app->singleton(CrudService::class, function ($app) {
            return new CrudService($app->make(ChunkUploadService::class));
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
        $this->registerPublishing();
        $this->registerCommands();
    }

    /**
     * Register the role resolver binding.
     *
     * Priority:
     * 1. Custom class from config('crud.role_resolver')
     * 2. Already bound by the host application
     * 3. Auto-detect Spatie laravel-permission
     * 4. Fallback to EloquentRoleResolver
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

            if (method_exists($controller, 'bulkDestroy')) {
                Route::post("{$uri}/bulk-destroy", [$controller, 'bulkDestroy'])->name("{$name}.bulk-destroy");
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
     * Register publishable assets.
     */
    protected function registerPublishing(): void
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        // Config
        $this->publishes([
            __DIR__ . '/../config/crud.php' => config_path('crud.php'),
        ], 'crud-config');

        // React components
        $this->publishes([
            __DIR__ . '/../resources/js/components/crud' => resource_path('js/components/crud'),
        ], 'crud-components');

        // Theme & utilities
        $this->publishes([
            __DIR__ . '/../resources/js/lib/theme.ts' => resource_path('js/lib/crud-theme.ts'),
            __DIR__ . '/../resources/js/hooks'        => resource_path('js/hooks'),
        ], 'crud-lib');

        // All frontend assets at once
        $this->publishes([
            __DIR__ . '/../resources/js/components/crud' => resource_path('js/components/crud'),
            __DIR__ . '/../resources/js/lib/theme.ts'    => resource_path('js/lib/crud-theme.ts'),
            __DIR__ . '/../resources/js/hooks'           => resource_path('js/hooks'),
        ], 'crud-assets');

        // Stubs
        $this->publishes([
            __DIR__ . '/../stubs' => base_path('stubs/crud'),
        ], 'crud-stubs');
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
        ]);
    }
}
