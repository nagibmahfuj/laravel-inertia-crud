<?php

namespace NagibMahfuj\Crud;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use NagibMahfuj\Crud\Commands\CrudGenerateCommand;
use NagibMahfuj\Crud\Contracts\RoleResolver;
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

		// Register the CrudService as a singleton
		$this->app->singleton(CrudService::class);

		// Bind the RoleResolver contract
		$this->registerRoleResolver();
	}

	/**
	 * Bootstrap package services.
	 */
	public function boot(): void
	{
		$this->registerRouteMacros();
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
		// Allow config-based override
		$customResolver = config('crud.role_resolver');
		if ($customResolver && class_exists($customResolver)) {
			$this->app->singleton(RoleResolver::class, $customResolver);
			return;
		}

		// Don't override if the host app already bound it
		if ($this->app->bound(RoleResolver::class)) {
			return;
		}

		// Auto-detect Spatie
		if (class_exists(\Spatie\Permission\Traits\HasRoles::class)) {
			$this->app->singleton(RoleResolver::class, SpatieRoleResolver::class);
		} else {
			$this->app->singleton(RoleResolver::class, EloquentRoleResolver::class);
		}
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

			return Route::resource($name, $controller, $options);
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
			__DIR__ . '/../resources/js/lib/theme.ts' => resource_path('js/lib/crud-theme.ts'),
			__DIR__ . '/../resources/js/hooks'        => resource_path('js/hooks'),
		], 'crud-lib');

		// All frontend assets at once
			__DIR__ . '/../resources/js/components/crud' => resource_path('js/components/crud'),
			__DIR__ . '/../resources/js/lib/theme.ts'     => resource_path('js/lib/crud-theme.ts'),
			__DIR__ . '/../resources/js/hooks'             => resource_path('js/hooks'),
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
		]);
	}
}
