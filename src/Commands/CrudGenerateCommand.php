<?php

namespace NagibMahfuj\Crud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CrudGenerateCommand extends Command
{
	protected $signature = 'crud:generate
		{--M|model= : Model class name (e.g., Product)}
		{--route-prefix= : Route prefix (e.g., products)}
		{--page-prefix= : Inertia page prefix (e.g., Dashboard/Products)}
		{--controller-namespace= : Controller namespace}
		{--policy : Also generate a policy}
		{--pages : Also generate Inertia TSX pages}
		{--permissions : Also add entry to crud config}
		{--all : Generate controller + policy + pages + config entry}
		{--force : Overwrite existing files}';

	protected $description = 'Generate config-driven CRUD scaffolding (controller, policy, pages) for a model';

	protected Filesystem $files;

	/**
	 * Column type mapping from DB to CRUD field types.
	 */
	protected array $typeMap = [
		'varchar'   => 'text',
		'char'      => 'text',
		'string'    => 'text',
		'text'      => 'textarea',
		'longtext'  => 'textarea',
		'mediumtext' => 'textarea',
		'int'       => 'number',
		'integer'   => 'number',
		'bigint'    => 'number',
		'smallint'  => 'number',
		'tinyint'   => 'number',
		'float'     => 'number',
		'double'    => 'number',
		'decimal'   => 'number',
		'boolean'   => 'toggle',
		'bool'      => 'toggle',
		'date'      => 'date',
		'datetime'  => 'datetime',
		'timestamp' => 'datetime',
		'json'      => 'textarea',
		'enum'      => 'select',
	];

	/**
	 * Columns to auto-exclude from index display.
	 */
	protected array $hiddenColumns = [
		'password',
		'remember_token',
		'email_verified_at',
		'updated_at',
		'deleted_at',
		'two_factor_secret',
		'two_factor_recovery_codes',
		'two_factor_confirmed_at',
	];

	/**
	 * Columns to exclude from form fields entirely.
	 */
	protected array $excludeFromFields = [
		'id',
		'created_at',
		'updated_at',
		'deleted_at',
		'remember_token',
		'email_verified_at',
		'two_factor_secret',
		'two_factor_recovery_codes',
		'two_factor_confirmed_at',
		'created_by',
		'updated_by',
	];

	public function __construct()
	{
		parent::__construct();
		$this->files = new Filesystem;
	}

	public function handle(): int
	{
		$isInteractive = !$this->option('model');

		if ($isInteractive) {
			return $this->handleInteractive();
		}

		return $this->handleNonInteractive();
	}

	/**
	 * Interactive mode — step-by-step wizard.
	 */
	protected function handleInteractive(): int
	{
		note('🚀 Laravel Inertia CRUD Generator');
		note('nagibmahfuj/laravel-inertia-crud');
		$this->newLine();

		// Step 1: Select Model
		$models = $this->discoverModels();

		if (empty($models)) {
			warning('No models found in ' . config('crud.generator.model_path', 'app/Models'));
			return self::FAILURE;
		}

		$modelName = search(
			label: 'Step 1/6 — Select a model',
			options: fn ($search) => collect($models)
				->filter(fn ($m) => str_contains(strtolower($m), strtolower($search)))
				->values()
				->toArray(),
			placeholder: 'Type to search models...',
		);

		// Step 2: Route Prefix
		$suggestedRoute = Str::kebab(Str::plural($modelName));
		$routePrefix = text(
			label: 'Step 2/6 — Route prefix',
			default: $suggestedRoute,
			hint: "URL segment: /dashboard/{$suggestedRoute}",
		);

		// Step 3: Page Prefix
		$suggestedPage = 'Dashboard/' . Str::plural($modelName);
		$pagePrefix = text(
			label: 'Step 3/6 — Inertia page prefix',
			default: $suggestedPage,
			hint: 'Path under resources/js/Pages/',
		);

		// Step 4: Detected Columns
		$columns = $this->detectColumns($modelName);
		if (empty($columns)) {
			warning("Could not detect columns for {$modelName}. Generating with default structure.");
			$selectedColumns = [];
		} else {
			$this->newLine();
			info('Step 4/6 — Detected columns from database:');

			$tableRows = collect($columns)->map(fn ($col) => [
				$col['name'],
				$col['crud_type'],
				$col['nullable'] ? 'nullable' : 'required',
				in_array($col['name'], $this->hiddenColumns) ? '✗' : '✓',
			])->toArray();

			table(
				headers: ['Column', 'Type', 'Nullable', 'Show in Index'],
				rows: $tableRows,
			);

			$selectedColumns = multiselect(
				label: 'Select columns to include in the index table',
				options: collect($columns)
					->pluck('name')
					->mapWithKeys(fn ($name) => [$name => $name])
					->toArray(),
				default: collect($columns)
					->filter(fn ($col) => !in_array($col['name'], $this->hiddenColumns))
					->pluck('name')
					->toArray(),
			);
		}

		// Step 5: What to generate
		$generateOptions = multiselect(
			label: 'Step 5/6 — What to generate?',
			options: [
				'controller'  => 'Controller',
				'policy'      => 'Policy',
				'pages'       => 'Inertia Pages (Index.tsx, Form.tsx)',
				'permissions' => 'Add to crud config permissions',
			],
			default: ['controller', 'policy', 'pages', 'permissions'],
		);

		// Step 6: Confirm
		$controllerNs = config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
		$policyNs     = config('crud.generator.policy_namespace', 'App\\Policies');
		$pagesDir     = config('crud.generator.pages_directory', 'resources/js/Pages/Dashboard');

		$this->newLine();
		info('Step 6/6 — Summary:');
		$this->line("  Model:       App\\Models\\{$modelName}");
		if (in_array('controller', $generateOptions)) {
			$this->line("  Controller:  {$controllerNs}\\{$modelName}Controller");
		}
		if (in_array('policy', $generateOptions)) {
			$this->line("  Policy:      {$policyNs}\\{$modelName}Policy");
		}
		if (in_array('pages', $generateOptions)) {
			$this->line("  Index Page:  {$pagesDir}/" . Str::plural($modelName) . '/Index.tsx');
			$this->line("  Form Page:   {$pagesDir}/" . Str::plural($modelName) . '/Form.tsx');
		}
		if (in_array('permissions', $generateOptions)) {
			$this->line("  Config:      config/crud.php → resources.{$routePrefix}");
		}
		$this->newLine();

		if (!confirm('Generate these files?', true)) {
			info('Cancelled.');
			return self::SUCCESS;
		}

		// Generate!
		$force = $this->option('force');

		if (in_array('controller', $generateOptions)) {
			$this->generateController($modelName, $routePrefix, $pagePrefix, $controllerNs, $columns, $selectedColumns, $force);
		}

		if (in_array('policy', $generateOptions)) {
			$this->generatePolicy($modelName, $routePrefix, $policyNs, $force);
		}

		if (in_array('pages', $generateOptions)) {
			$this->generatePages($modelName, $routePrefix, $pagePrefix, $force);
		}

		if (in_array('permissions', $generateOptions)) {
			$this->addToConfig($routePrefix);
		}

		$this->newLine();
		info("✅ Don't forget to add the route to your routes file:");
		$this->line("   Route::crudResource('{$routePrefix}', \\{$controllerNs}\\{$modelName}Controller::class);");

		return self::SUCCESS;
	}

	/**
	 * Non-interactive mode — all via options.
	 */
	protected function handleNonInteractive(): int
	{
		$modelName = $this->option('model');

		if (!$modelName) {
			$this->error('Model name is required. Use --model=ModelName');
			return self::FAILURE;
		}

		$routePrefix    = $this->option('route-prefix') ?: Str::kebab(Str::plural($modelName));
		$pagePrefix     = $this->option('page-prefix') ?: 'Dashboard/' . Str::plural($modelName);
		$controllerNs   = $this->option('controller-namespace') ?: config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
		$policyNs       = config('crud.generator.policy_namespace', 'App\\Policies');
		$generateAll    = $this->option('all');
		$force          = $this->option('force');
		$columns        = $this->detectColumns($modelName);
		$selectedColumns = collect($columns)
			->filter(fn ($col) => !in_array($col['name'], $this->hiddenColumns))
			->pluck('name')
			->toArray();

		// Always generate controller
		$this->generateController($modelName, $routePrefix, $pagePrefix, $controllerNs, $columns, $selectedColumns, $force);

		if ($generateAll || $this->option('policy')) {
			$this->generatePolicy($modelName, $routePrefix, $policyNs, $force);
		}

		if ($generateAll || $this->option('pages')) {
			$this->generatePages($modelName, $routePrefix, $pagePrefix, $force);
		}

		if ($generateAll || $this->option('permissions')) {
			$this->addToConfig($routePrefix);
		}

		$this->newLine();
		info("✅ CRUD generated for {$modelName}!");
		$this->line("   Route::crudResource('{$routePrefix}', \\{$controllerNs}\\{$modelName}Controller::class);");

		return self::SUCCESS;
	}

	// ── Discovery ────────────────────────────────────────────

	/**
	 * Discover all Eloquent models in the configured path.
	 */
	protected function discoverModels(): array
	{
		$modelPath = base_path(config('crud.generator.model_path', 'app/Models'));

		if (!is_dir($modelPath)) {
			return [];
		}

		$models = [];

		foreach ($this->files->allFiles($modelPath) as $file) {
			if ($file->getExtension() !== 'php') {
				continue;
			}

			$className = $file->getFilenameWithoutExtension();

			// Check if the class extends Eloquent Model
			$fqcn = 'App\\Models\\' . $className;
			if (class_exists($fqcn) && is_subclass_of($fqcn, \Illuminate\Database\Eloquent\Model::class)) {
				$models[] = $className;
			}
		}

		sort($models);

		return $models;
	}

	/**
	 * Detect database columns for the given model.
	 */
	protected function detectColumns(string $modelName): array
	{
		$fqcn = 'App\\Models\\' . $modelName;

		if (!class_exists($fqcn)) {
			return [];
		}

		try {
			$model = new $fqcn;
			$table = $model->getTable();

			if (!Schema::hasTable($table)) {
				return [];
			}

			$dbColumns = Schema::getColumns($table);

			return collect($dbColumns)->map(function ($col) {
				$typeName = strtolower($col['type_name'] ?? $col['type'] ?? 'varchar');

				return [
					'name'      => $col['name'],
					'type'      => $typeName,
					'crud_type' => $this->mapColumnType($typeName, $col['name']),
					'nullable'  => $col['nullable'] ?? false,
					'default'   => $col['default'] ?? null,
				];
			})->toArray();
		} catch (\Throwable $e) {
			return [];
		}
	}

	/**
	 * Map a database column type to a CRUD field type.
	 */
	protected function mapColumnType(string $dbType, string $columnName): string
	{
		// Special handling by column name
		if ($columnName === 'password') return 'password';
		if ($columnName === 'email') return 'email';
		if (Str::endsWith($columnName, '_id')) return 'select';

		// Extract base type (e.g., "varchar(255)" -> "varchar")
		$baseType = preg_replace('/\(.*\)/', '', $dbType);

		return $this->typeMap[$baseType] ?? 'text';
	}

	// ── Generators ───────────────────────────────────────────

	protected function generateController(
		string $modelName,
		string $routePrefix,
		string $pagePrefix,
		string $controllerNs,
		array $columns,
		array $selectedColumns,
		bool $force
	): void {
		$stubPath = $this->resolveStubPath('CrudController.stub');
		$stub     = $this->files->get($stubPath);

		// Build columns array
		$columnsStr = '';
		$fieldsStr  = '';
		$searchable = [];

		foreach ($columns as $col) {
			$name = $col['name'];

			// Build columns for index display
			if (in_array($name, $selectedColumns)) {
				$columnsStr .= "                ['key' => '{$name}', 'label' => '" . Str::title(str_replace('_', ' ', $name)) . "', 'sortable' => true, 'type' => '{$col['crud_type']}'],\n";
			}

			// Build fields for form
			if (!in_array($name, $this->excludeFromFields)) {
				$required = !$col['nullable'] ? 'required' : 'nullable';
				$ruleType = match ($col['crud_type']) {
					'number'   => 'integer',
					'email'    => 'email',
					'toggle'   => 'boolean',
					'textarea' => 'string',
					'password' => 'string|min:8',
					'date', 'datetime' => 'date',
					default    => 'string|max:255',
				};

				$isRequired = !$col['nullable'];
				$optionalOnUpdate = $col['crud_type'] === 'password' ? ", 'optional_on_update' => true" : '';

				$fieldsStr .= "                ['key' => '{$name}', 'label' => '" . Str::title(str_replace('_', ' ', $name)) . "', 'type' => '{$col['crud_type']}', 'rules' => '{$required}|{$ruleType}', 'required' => " . ($isRequired ? 'true' : 'false') . "{$optionalOnUpdate}],\n";
			}

			// Build searchable columns
			if (in_array($col['crud_type'], ['text', 'email']) && !in_array($name, $this->hiddenColumns) && $name !== 'id') {
				$searchable[] = "'{$name}'";
			}
		}

		// Fallback if no columns detected
		if (empty($columnsStr)) {
			$columnsStr = "                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'type' => 'number'],\n";
			$columnsStr .= "                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'type' => 'text'],\n";
		}
		if (empty($fieldsStr)) {
			$fieldsStr = "                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:255', 'required' => true],\n";
		}
		if (empty($searchable)) {
			$searchable = ["'name'"];
		}

		$replacements = [
			'{{ namespace }}'    => $controllerNs,
			'{{ class }}'        => $modelName . 'Controller',
			'{{ model }}'        => $modelName,
			'{{ resourceName }}' => Str::title(Str::snake($modelName, ' ')),
			'{{ routePrefix }}'  => $routePrefix,
			'{{ pagePrefix }}'   => $pagePrefix,
			'{{ searchable }}'   => implode(', ', $searchable),
			'{{ columns }}'      => rtrim($columnsStr, "\n"),
			'{{ fields }}'       => rtrim($fieldsStr, "\n"),
		];

		$content = str_replace(array_keys($replacements), array_values($replacements), $stub);

		$path = base_path(str_replace('\\', '/', $controllerNs) . '/' . $modelName . 'Controller.php');
		$path = str_replace('App/', 'app/', $path);

		$this->writeFile($path, $content, $force, 'Controller');
	}

	protected function generatePolicy(string $modelName, string $routePrefix, string $policyNs, bool $force): void
	{
		$stubPath = $this->resolveStubPath('CrudPolicy.stub');
		$stub     = $this->files->get($stubPath);

		$replacements = [
			'{{ namespace }}'   => $policyNs,
			'{{ class }}'       => $modelName . 'Policy',
			'{{ resourceKey }}' => $routePrefix,
		];

		$content = str_replace(array_keys($replacements), array_values($replacements), $stub);

		$path = base_path(str_replace('\\', '/', $policyNs) . '/' . $modelName . 'Policy.php');
		$path = str_replace('App/', 'app/', $path);

		$this->writeFile($path, $content, $force, 'Policy');
	}

	protected function generatePages(string $modelName, string $routePrefix, string $pagePrefix, bool $force): void
	{
		$pagesDir = config('crud.generator.pages_directory', 'resources/js/Pages/Dashboard');
		$modelPlural = Str::plural($modelName);

		// Index page
		$indexStub = $this->resolveStubPath('IndexPage.stub');
		$indexContent = str_replace(
			['{{ modelPlural }}', '{{ routePrefix }}'],
			[$modelPlural, $routePrefix],
			$this->files->get($indexStub)
		);

		$indexPath = base_path("{$pagesDir}/{$modelPlural}/Index.tsx");
		$this->writeFile($indexPath, $indexContent, $force, 'Index page');

		// Form page
		$formStub = $this->resolveStubPath('FormPage.stub');
		$formContent = str_replace(
			['{{ modelPlural }}', '{{ routePrefix }}'],
			[$modelPlural, $routePrefix],
			$this->files->get($formStub)
		);

		$formPath = base_path("{$pagesDir}/{$modelPlural}/Form.tsx");
		$this->writeFile($formPath, $formContent, $force, 'Form page');
	}

	protected function addToConfig(string $routePrefix): void
	{
		$configPath = config_path('crud.php');

		if (!$this->files->exists($configPath)) {
			warning('Config file not found. Run: php artisan vendor:publish --tag=crud-config');
			return;
		}

		$content = $this->files->get($configPath);

		// Check if already exists
		if (str_contains($content, "'{$routePrefix}'")) {
			info("Config entry '{$routePrefix}' already exists in config/crud.php");
			return;
		}

		// Add the entry after the 'resources' => [ line
		$entry = "\t\t'{$routePrefix}' => ['roles' => ['super_admin', 'admin']],\n";
		$content = str_replace(
			"'resources' => [\n",
			"'resources' => [\n{$entry}",
			$content
		);

		$this->files->put($configPath, $content);
		info("✅ Config updated: config/crud.php → resources.{$routePrefix}");
	}

	// ── Helpers ──────────────────────────────────────────────

	protected function resolveStubPath(string $stub): string
	{
		// Check if custom stub exists in the host app
		$customPath = base_path("stubs/crud/{$stub}");
		if ($this->files->exists($customPath)) {
			return $customPath;
		}

		return __DIR__ . '/../../stubs/' . $stub;
	}

	protected function writeFile(string $path, string $content, bool $force, string $label): void
	{
		if ($this->files->exists($path) && !$force) {
			warning("⏭️  {$label} already exists: {$path} (use --force to overwrite)");
			return;
		}

		$this->files->ensureDirectoryExists(dirname($path));
		$this->files->put($path, $content);
		info("✅ {$label} created: " . str_replace(base_path() . '/', '', $path));
	}
}
