<?php

namespace NagibMahfuj\Crud\Commands;

use Composer\InstalledVersions;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use NagibMahfuj\Crud\Contracts\RoleResolver;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

class CrudGenerateCommand extends Command
{
    protected $signature = 'crud:generate
        {--M|model= : Model class name (e.g., Product or Billing/Invoice)}
        {--route-prefix= : Route prefix (e.g., products)}
        {--page-prefix= : Inertia page prefix (e.g., Dashboard/Products)}
        {--controller-namespace= : Controller namespace}
        {--actions= : Comma-separated actions (create,edit,delete,show,export,import)}
        {--policy : Also generate a policy}
        {--pages : Also generate Inertia TSX pages}
        {--permissions : Also add entry to crud config}
        {--all : Generate controller + policy + pages + config entry}
        {--force : Overwrite existing files}';

    protected $description = 'Generate config-driven CRUD scaffolding (controller, policy, pages) for a model';

    protected Filesystem $files;

    /**
     * Track generated artifact outcomes for the final summary table.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    protected array $results = [];

    /**
     * Column type mapping from DB to CRUD field types.
     */
    protected array $typeMap = [
        'varchar'    => 'text',
        'char'       => 'text',
        'string'     => 'text',
        'text'       => 'textarea',
        'longtext'   => 'textarea',
        'mediumtext' => 'textarea',
        'int'        => 'number',
        'integer'    => 'number',
        'bigint'     => 'number',
        'smallint'   => 'number',
        'tinyint'    => 'number',
        'float'      => 'number',
        'double'     => 'number',
        'decimal'    => 'number',
        'boolean'    => 'toggle',
        'bool'       => 'toggle',
        'date'       => 'date',
        'datetime'   => 'datetime',
        'timestamp'  => 'datetime',
        'json'       => 'textarea',
        'enum'       => 'select',
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
        $this->files = new Filesystem();
    }

    public function handle(): int
    {
        $this->results = [];
        $this->renderBanner();

        $isInteractive = !$this->option('model');

        if ($isInteractive) {
            return $this->handleInteractive();
        }

        return $this->handleNonInteractive();
    }

    /**
     * Render a rich, informative CLI package banner & active configuration panel.
     */
    protected function renderBanner(): void
    {
        $version      = $this->resolvePackageVersion();
        $laravelVer   = app()->version();
        $phpVer       = PHP_VERSION;
        $resolver     = app()->bound(RoleResolver::class) ? class_basename(app(RoleResolver::class)) : 'EloquentRoleResolver';
        $controllerNs = config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
        $pagesRoot    = $this->resolvePagesRoot();
        $chunkEnabled = (bool) config('crud.chunk_upload.enabled', true);
        $chunkRoute   = '/' . ltrim((string) config('crud.chunk_upload.route', '/crud/chunk-upload'), '/');
        $configExists = $this->files->exists(config_path('crud.php'));

        $chunkDisplay  = $chunkEnabled ? "<fg=green>Enabled</> <fg=gray>(POST {$chunkRoute})</>" : '<fg=yellow>Disabled</>';
        $configDisplay = $configExists ? '<fg=green>Published</> <fg=gray>(config/crud.php)</>' : '<fg=yellow>Using defaults</> <fg=gray>(run vendor:publish --tag=crud-config)</>';

        $this->newLine();
        $this->line('  <bg=blue;fg=white;options=bold>  LARAVEL INERTIA CRUD  </> <fg=cyan;options=bold>' . $version . '</>');
        $this->line('  <fg=gray>nagibmahfuj/laravel-inertia-crud — Config-driven CRUD scaffolding for Laravel + Inertia (React/TS)</>');
        $this->line('  <fg=gray>────────────────────────────────────────────────────────────────────────────────────────────</>');
        $this->line("  <fg=cyan>Environment</>   PHP <options=bold>{$phpVer}</>  <fg=gray>•</>  Laravel <options=bold>{$laravelVer}</>  <fg=gray>•</>  Role Resolver: <options=bold>{$resolver}</>");
        $this->line("  <fg=cyan>Defaults</>      Controllers: <options=bold>{$controllerNs}</>  <fg=gray>•</>  Pages: <options=bold>{$pagesRoot}</>");
        $this->line("  <fg=cyan>Chunk Upload</>  {$chunkDisplay}  <fg=gray>•</>  Config: {$configDisplay}");
        $this->line('  <fg=cyan>Docs & Repo</>   <fg=gray>https://nagibmahfuj.github.io/laravel-inertia-crud/</>  <fg=gray>•</>  <fg=gray>https://github.com/nagibmahfuj/laravel-inertia-crud</>');
        $this->line('  <fg=gray>────────────────────────────────────────────────────────────────────────────────────────────</>');
        $this->newLine();
    }

    /**
     * Resolve the installed version of this package.
     */
    protected function resolvePackageVersion(): string
    {
        if (class_exists(InstalledVersions::class)) {
            try {
                if (InstalledVersions::isInstalled('nagibmahfuj/laravel-inertia-crud')) {
                    return InstalledVersions::getPrettyVersion('nagibmahfuj/laravel-inertia-crud') ?? 'v2.x-dev';
                }
            } catch (\Throwable) {
                // Fallback below
            }
        }

        return 'v2.x-dev';
    }

    /**
     * Interactive mode — step-by-step wizard.
     */
    protected function handleInteractive(): int
    {
        // Step 1: Select Model
        $models = $this->discoverModels();

        if (empty($models)) {
            warning('No models found in ' . config('crud.generator.model_path', 'app/Models'));
            return self::FAILURE;
        }

        $modelRef = search(
            label: 'Step 1/7 — Select a model',
            options: fn ($search) => collect($models)
                ->filter(fn ($m) => str_contains(strtolower($m), strtolower($search)))
                ->values()
                ->toArray(),
            placeholder: 'Type to search models...',
        );

        $modelBase = class_basename(str_replace('/', '\\', $modelRef));

        // Step 2: Route Prefix
        $suggestedRoute = Str::kebab(Str::plural($modelBase));
        $routePrefix = text(
            label: 'Step 2/7 — Route prefix',
            default: $suggestedRoute,
            hint: 'URL segment: ' . rtrim((string) config('crud.url_prefix', '/dashboard'), '/') . "/{$suggestedRoute}",
        );

        // Step 3: Page Prefix
        $suggestedPage = 'Dashboard/' . Str::plural($modelBase);
        $pagePrefix = text(
            label: 'Step 3/7 — Inertia page prefix',
            default: $suggestedPage,
            hint: 'Relative path under ' . $this->resolvePagesRoot() . '/',
        );

        // Step 4: Detected Columns
        $columns = $this->detectColumns($modelRef);
        if (empty($columns)) {
            warning("Could not detect columns for {$modelRef}. Generating with default structure.");
            $selectedColumns = [];
        } else {
            $this->newLine();
            info('Step 4/7 — Detected columns from database:');

            $tableRows = collect($columns)->map(fn ($col) => [
                $col['name'],
                $col['crud_type'],
                $col['nullable'] ? 'nullable' : 'required',
                in_array($col['name'], $this->hiddenColumns, true) ? '✗' : '✓',
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
                    ->filter(fn ($col) => !in_array($col['name'], $this->hiddenColumns, true))
                    ->pluck('name')
                    ->toArray(),
            );
        }

        // Step 5: Enabled Actions
        $selectedActions = multiselect(
            label: 'Step 5/7 — Enabled resource actions',
            options: [
                'create' => 'Create (Add button & form)',
                'edit'   => 'Edit (Row edit action & form)',
                'delete' => 'Delete (Row delete & bulk delete)',
                'show'   => 'Show (View details button)',
                'export' => 'Export (CSV export button & route)',
                'import' => 'Import (Import button & upload modal)',
            ],
            default: ['create', 'edit', 'delete', 'export'],
        );

        // Step 6: What to generate
        $generateOptions = multiselect(
            label: 'Step 6/7 — What to generate?',
            options: [
                'controller'  => 'Controller',
                'policy'      => 'Policy',
                'pages'       => 'Inertia Pages (Index.tsx, Form.tsx)',
                'permissions' => 'Add entry to config/crud.php permissions',
            ],
            default: ['controller', 'policy', 'pages', 'permissions'],
        );

        // Step 7: Confirm
        $controllerNs = config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
        $policyNs     = config('crud.generator.policy_namespace', 'App\\Policies');
        $pagesDir     = $this->resolvePageTargetDirectory($pagePrefix);
        $modelFqcn    = $this->resolveModelFqcn($modelRef);

        $summaryRows = [
            ['Model', $modelFqcn],
            ['Actions', empty($selectedActions) ? '(none)' : implode(', ', $selectedActions)],
        ];

        if (in_array('controller', $generateOptions, true)) {
            $summaryRows[] = ['Controller', "{$controllerNs}\\{$modelBase}Controller"];
        }
        if (in_array('policy', $generateOptions, true)) {
            $summaryRows[] = ['Policy', "{$policyNs}\\{$modelBase}Policy"];
        }
        if (in_array('pages', $generateOptions, true)) {
            $summaryRows[] = ['Index Page', "{$pagesDir}/Index.tsx"];
            $summaryRows[] = ['Form Page', "{$pagesDir}/Form.tsx"];
        }
        if (in_array('permissions', $generateOptions, true)) {
            $summaryRows[] = ['Permissions', "config/crud.php → resources.{$routePrefix}"];
        }

        $this->newLine();
        info('Step 7/7 — Generation Summary:');
        table(headers: ['Artifact', 'Target'], rows: $summaryRows);

        if (!confirm('Generate these files?', true)) {
            info('Cancelled.');
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');

        if (in_array('controller', $generateOptions, true)) {
            $this->generateController($modelRef, $routePrefix, $pagePrefix, $controllerNs, $columns, $selectedColumns, $selectedActions, $force);
        }

        if (in_array('policy', $generateOptions, true)) {
            $this->generatePolicy($modelBase, $routePrefix, $policyNs, $force);
        }

        if (in_array('pages', $generateOptions, true)) {
            $this->generatePages($pagePrefix, $force);
        }

        if (in_array('permissions', $generateOptions, true)) {
            $this->addToConfig($routePrefix);
        }

        $this->renderCompletionSummary($modelBase, $routePrefix, $controllerNs);

        return self::SUCCESS;
    }

    /**
     * Non-interactive mode — all via options.
     */
    protected function handleNonInteractive(): int
    {
        $modelRef = (string) $this->option('model');

        if ($modelRef === '') {
            $this->error('Model name is required. Use --model=ModelName');
            return self::FAILURE;
        }

        $modelBase       = class_basename(str_replace('/', '\\', $modelRef));
        $routePrefix     = $this->option('route-prefix') ?: Str::kebab(Str::plural($modelBase));
        $pagePrefix      = $this->option('page-prefix') ?: 'Dashboard/' . Str::plural($modelBase);
        $controllerNs    = $this->option('controller-namespace') ?: config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
        $policyNs        = config('crud.generator.policy_namespace', 'App\\Policies');
        $generateAll     = (bool) $this->option('all');
        $force           = (bool) $this->option('force');
        $columns         = $this->detectColumns($modelRef);
        $selectedColumns = collect($columns)
            ->filter(fn ($col) => !in_array($col['name'], $this->hiddenColumns, true))
            ->pluck('name')
            ->toArray();

        $actionsOption   = $this->option('actions');
        $selectedActions = $actionsOption !== null
            ? array_values(array_filter(array_map('trim', explode(',', (string) $actionsOption))))
            : ['create', 'edit', 'delete', 'export'];

        // Always generate controller
        $this->generateController($modelRef, $routePrefix, $pagePrefix, $controllerNs, $columns, $selectedColumns, $selectedActions, $force);

        if ($generateAll || $this->option('policy')) {
            $this->generatePolicy($modelBase, $routePrefix, $policyNs, $force);
        }

        if ($generateAll || $this->option('pages')) {
            $this->generatePages($pagePrefix, $force);
        }

        if ($generateAll || $this->option('permissions')) {
            $this->addToConfig($routePrefix);
        }

        $this->renderCompletionSummary($modelBase, $routePrefix, $controllerNs);

        return self::SUCCESS;
    }

    protected function renderCompletionSummary(string $modelBase, string $routePrefix, string $controllerNs): void
    {
        $this->newLine();

        if (!empty($this->results)) {
            $this->table(['Artifact', 'Status', 'Path'], $this->results);
            $this->newLine();
        }

        $this->line('  <fg=green;options=bold>✓ Scaffolding complete!</> Next steps:');
        $this->line("  <fg=cyan>1.</> Register the resource route in <options=bold>routes/web.php</>:");
        $this->line("     <fg=yellow>Route::crudResource('{$routePrefix}', \\{$controllerNs}\\{$modelBase}Controller::class);</>");

        if (!$this->files->exists(config_path('crud.php'))) {
            $this->line('  <fg=cyan>2.</> Publish config & components if not already published:');
            $this->line('     <fg=gray>php artisan vendor:publish --tag=crud-config && php artisan vendor:publish --tag=crud-assets</>');
        }

        $this->newLine();
    }

    // ── Discovery ────────────────────────────────────────────

    /**
     * Discover all Eloquent models in the configured path (including nested subdirectories).
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

            $relativeDir = trim(str_replace('\\', '/', $file->getRelativePath()), '/');
            $className   = $file->getFilenameWithoutExtension();
            $modelRef    = $relativeDir !== '' ? "{$relativeDir}/{$className}" : $className;
            $fqcn        = $this->resolveModelFqcn($modelRef);

            if (class_exists($fqcn) && is_subclass_of($fqcn, \Illuminate\Database\Eloquent\Model::class)) {
                $models[] = $modelRef;
            }
        }

        sort($models);

        return $models;
    }

    protected function resolveModelFqcn(string $modelRef): string
    {
        $normalized = str_replace('/', '\\', ltrim($modelRef, '\\'));
        if (str_starts_with($normalized, 'App\\')) {
            return $normalized;
        }

        return 'App\\Models\\' . $normalized;
    }

    /**
     * Detect database columns for the given model.
     */
    protected function detectColumns(string $modelRef): array
    {
        $fqcn = $this->resolveModelFqcn($modelRef);

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
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Map a database column type to a CRUD field type.
     */
    protected function mapColumnType(string $dbType, string $columnName): string
    {
        if ($columnName === 'password') {
            return 'password';
        }
        if ($columnName === 'email') {
            return 'email';
        }
        if (Str::endsWith($columnName, '_id')) {
            return 'select';
        }

        $baseType = preg_replace('/\(.*\)/', '', $dbType);

        return $this->typeMap[$baseType] ?? 'text';
    }

    // ── Generators ───────────────────────────────────────────

    protected function generateController(
        string $modelRef,
        string $routePrefix,
        string $pagePrefix,
        string $controllerNs,
        array $columns,
        array $selectedColumns,
        array $selectedActions,
        bool $force
    ): void {
        $stubPath  = $this->resolveStubPath('CrudController.stub');
        $stub      = $this->files->get($stubPath);
        $modelBase = class_basename(str_replace('/', '\\', $modelRef));
        $modelFqcn = $this->resolveModelFqcn($modelRef);

        $columnsStr = '';
        $fieldsStr  = '';
        $searchable = [];

        foreach ($columns as $col) {
            $name = $col['name'];

            if (in_array($name, $selectedColumns, true)) {
                $colType = $col['crud_type'] === 'toggle' ? 'boolean' : $col['crud_type'];
                $columnsStr .= "                ['key' => '{$name}', 'label' => '" . Str::title(str_replace('_', ' ', $name)) . "', 'sortable' => true, 'type' => '{$colType}'],\n";
            }

            if (!in_array($name, $this->excludeFromFields, true)) {
                $required = !$col['nullable'] ? 'required' : 'nullable';
                $ruleType = match ($col['crud_type']) {
                    'number'           => 'integer',
                    'email'            => 'email',
                    'toggle'           => 'boolean',
                    'textarea'         => 'string',
                    'password'         => 'string|min:8',
                    'date', 'datetime' => 'date',
                    default            => 'string|max:255',
                };

                $isRequired       = !$col['nullable'];
                $optionalOnUpdate = $col['crud_type'] === 'password' ? ", 'optional_on_update' => true" : '';

                $fieldsStr .= "                ['key' => '{$name}', 'label' => '" . Str::title(str_replace('_', ' ', $name)) . "', 'type' => '{$col['crud_type']}', 'rules' => '{$required}|{$ruleType}', 'required' => " . ($isRequired ? 'true' : 'false') . "{$optionalOnUpdate}],\n";
            }

            if (in_array($col['crud_type'], ['text', 'email'], true) && !in_array($name, $this->hiddenColumns, true) && $name !== 'id') {
                $searchable[] = "'{$name}'";
            }
        }

        if (empty($columnsStr)) {
            $columnsStr  = "                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'type' => 'number'],\n";
            $columnsStr .= "                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'type' => 'text'],\n";
        }
        if (empty($fieldsStr)) {
            $fieldsStr = "                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:255', 'required' => true],\n";
        }
        if (empty($searchable)) {
            $searchable = ["'name'"];
        }

        $actionsStr = implode(', ', array_map(fn ($a) => "'{$a}'", $selectedActions));

        $replacements = [
            '{{ namespace }}'    => $controllerNs,
            '{{ class }}'        => $modelBase . 'Controller',
            '{{ model }}'        => $modelBase,
            '{{ modelFqcn }}'    => $modelFqcn,
            '{{ resourceName }}' => Str::title(Str::snake($modelBase, ' ')),
            '{{ routePrefix }}'  => $routePrefix,
            '{{ pagePrefix }}'   => $pagePrefix,
            '{{ searchable }}'   => implode(', ', $searchable),
            '{{ actions }}'      => $actionsStr,
            '{{ columns }}'      => rtrim($columnsStr, "\n"),
            '{{ fields }}'       => rtrim($fieldsStr, "\n"),
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $stub);
        $path    = $this->namespaceToPath($controllerNs, "{$modelBase}Controller.php");

        $this->writeFile($path, $content, $force, 'Controller');
    }

    protected function generatePolicy(string $modelBase, string $routePrefix, string $policyNs, bool $force): void
    {
        $stubPath = $this->resolveStubPath('CrudPolicy.stub');
        $stub     = $this->files->get($stubPath);

        $replacements = [
            '{{ namespace }}'   => $policyNs,
            '{{ class }}'       => $modelBase . 'Policy',
            '{{ resourceKey }}' => $routePrefix,
        ];

        $content = str_replace(array_keys($replacements), array_values($replacements), $stub);
        $path    = $this->namespaceToPath($policyNs, "{$modelBase}Policy.php");

        $this->writeFile($path, $content, $force, 'Policy');
    }

    protected function generatePages(string $pagePrefix, bool $force): void
    {
        $targetDir = $this->resolvePageTargetDirectory($pagePrefix);

        // Index page
        $indexStub    = $this->resolveStubPath('IndexPage.stub');
        $indexContent = $this->files->get($indexStub);
        $indexPath    = base_path("{$targetDir}/Index.tsx");
        $this->writeFile($indexPath, $indexContent, $force, 'Index Page');

        // Form page
        $formStub    = $this->resolveStubPath('FormPage.stub');
        $formContent = $this->files->get($formStub);
        $formPath    = base_path("{$targetDir}/Form.tsx");
        $this->writeFile($formPath, $formContent, $force, 'Form Page');
    }

    protected function addToConfig(string $routePrefix): void
    {
        $configPath = config_path('crud.php');

        if (!$this->files->exists($configPath)) {
            $this->results[] = ['Config', 'SKIPPED', 'config/crud.php not published'];
            warning('Config file not found. Run: php artisan vendor:publish --tag=crud-config');
            return;
        }

        $content = $this->files->get($configPath);

        if (preg_match("/['\"]" . preg_quote($routePrefix, '/') . "['\"]\s*=>/", $content)) {
            $this->results[] = ['Config', 'EXISTS', "config/crud.php → resources.{$routePrefix}"];
            return;
        }

        $entry   = "        '{$routePrefix}' => ['roles' => ['super_admin', 'admin']],\n";
        $updated = preg_replace(
            "/('resources'\s*=>\s*\[\r?\n)/",
            "$1{$entry}",
            $content,
            1,
            $count
        );

        if ($count > 0 && $updated !== null) {
            $this->files->put($configPath, $updated);
            $this->results[] = ['Config', 'UPDATED', "config/crud.php → resources.{$routePrefix}"];
        } else {
            $this->results[] = ['Config', 'SKIPPED', "Could not locate 'resources' array in config/crud.php"];
        }
    }

    // ── Helpers ──────────────────────────────────────────────

    /**
     * Resolve the base Inertia pages root (`resources/js/pages` vs `resources/js/Pages`).
     */
    protected function resolvePagesRoot(): string
    {
        $configured = config('crud.generator.pages_directory');
        if (!empty($configured)) {
            return trim((string) $configured, '/');
        }

        if (is_dir(base_path('resources/js/pages'))) {
            return 'resources/js/pages';
        }

        return 'resources/js/Pages';
    }

    /**
     * Compute the full relative directory for generated TSX pages from `$pagePrefix`,
     * avoiding duplicate trailing segments when legacy `pages_directory` ends with e.g. `Dashboard`.
     */
    protected function resolvePageTargetDirectory(string $pagePrefix): string
    {
        $pagesRoot  = $this->resolvePagesRoot();
        $cleanPrefix = trim(str_replace('\\', '/', $pagePrefix), '/');

        if ($cleanPrefix === '') {
            return $pagesRoot;
        }

        $prefixParts = explode('/', $cleanPrefix);
        $rootParts   = explode('/', $pagesRoot);

        if (!empty($prefixParts) && !empty($rootParts) && end($rootParts) === $prefixParts[0]) {
            array_shift($prefixParts);
            $cleanPrefix = implode('/', $prefixParts);
        }

        return $cleanPrefix !== '' ? "{$pagesRoot}/{$cleanPrefix}" : $pagesRoot;
    }

    /**
     * Safely map a PSR-4 namespace under App\ to a filesystem path without corrupting base_path().
     */
    protected function namespaceToPath(string $namespace, string $filename): string
    {
        $relative = str_replace('\\', '/', trim($namespace, '\\')) . '/' . $filename;
        $relative = preg_replace('/^App\//', 'app/', $relative);

        return base_path($relative);
    }

    protected function resolveStubPath(string $stub): string
    {
        $customPath = base_path("stubs/crud/{$stub}");
        if ($this->files->exists($customPath)) {
            return $customPath;
        }

        return __DIR__ . '/../../stubs/' . $stub;
    }

    protected function writeFile(string $path, string $content, bool $force, string $label): void
    {
        $relativePath = ltrim(str_replace(base_path(), '', $path), '/');

        if ($this->files->exists($path) && !$force) {
            $this->results[] = [$label, 'SKIPPED', "{$relativePath} (exists; use --force)"];
            return;
        }

        $status = $this->files->exists($path) ? 'OVERWRITTEN' : 'CREATED';

        $this->files->ensureDirectoryExists(dirname($path));
        $this->files->put($path, $content);
        $this->results[] = [$label, $status, $relativePath];
    }
}
