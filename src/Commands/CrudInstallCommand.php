<?php

namespace NagibMahfuj\Crud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use NagibMahfuj\Crud\Support\StarterBootstrapper;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class CrudInstallCommand extends Command
{
    protected $signature = 'crud:install
        {--starter : Run the guided starter bootstrapper (roles, starter CRUD resources, app wiring, and optional dependency install)}
        {--roles= : Role system to configure (column, spatie, none)}
        {--examples= : Comma-separated starter CRUD resources to scaffold (users, posts, none)}
        {--wire-app : Wire HandleInertiaRequests, AppLayout, app.tsx, AppSidebar, and routes/console.php}
        {--install-deps : Automatically install missing npm packages, shadcn/ui components, and Spatie (when --roles=spatie)}
        {--stubs : Also publish customizable generator stubs}
        {--ai : Also publish the portable AI Agent Skill to .agents/skills/}
        {--force : Overwrite existing published config/assets/starter files}
        {--no-assets : Skip publishing frontend React/TypeScript assets}';

    protected $description = 'Install Laravel Inertia CRUD config, frontend components, and optionally bootstrap a working starter CRUD setup';

    protected array $requiredNpmPackages = [
        'date-fns',
        'lodash',
        'lucide-react',
        'react-day-picker',
    ];

    protected array $requiredShadcnComponents = [
        'badge',
        'button',
        'calendar',
        'card',
        'checkbox',
        'command',
        'dialog',
        'dropdown-menu',
        'input',
        'label',
        'popover',
        'progress',
        'select',
        'separator',
        'table',
        'textarea',
    ];

    public function handle(Filesystem $files): int
    {
        $this->newLine();
        $this->line('  <bg=blue;fg=white;options=bold>  LARAVEL INERTIA CRUD — INSTALLER  </>');
        $this->line('  <fg=gray>nagibmahfuj/laravel-inertia-crud</>');
        $this->newLine();

        $force                   = (bool) $this->option('force');
        $starterMode             = (bool) $this->option('starter');
        $hasExplicitStarterFlags = ($this->option('roles') !== null && $this->option('roles') !== '')
            || ($this->option('examples') !== null && $this->option('examples') !== '');
        $interactive             = $starterMode && !$hasExplicitStarterFlags && $this->input->isInteractive();

        // Resolve starter selections (either via CLI flags or interactive prompts when --starter is passed)
        $roleMode = $this->resolveRoleMode($starterMode, $interactive);
        if ($roleMode === null) {
            return self::FAILURE;
        }

        $examples = $this->resolveExamples($starterMode, $interactive);
        if ($examples === null) {
            return self::FAILURE;
        }

        $wireApp = (bool) $this->option('wire-app');
        if ($starterMode && !$wireApp) {
            $wireApp = $interactive
                ? confirm(
                    label: 'Wire HandleInertiaRequests, AppLayout, app.tsx, AppSidebar & console schedule automatically?',
                    default: true,
                    hint: 'Idempotent patching for stock Laravel React Starter Kit files.'
                )
                : true;
        }

        $installDeps = (bool) $this->option('install-deps');
        if ($interactive && !$installDeps) {
            $installDeps = confirm(
                label: 'Automatically install missing npm packages, shadcn/ui components' . ($roleMode === 'spatie' ? ', and Spatie Permission?' : '?'),
                default: false,
                hint: 'You can also install them manually using the printed commands.'
            );
        }

        $publishAi = (bool) $this->option('ai');
        if ($interactive && !$publishAi) {
            $publishAi = confirm(
                label: 'Publish the portable AI Agent Skill to .agents/skills/laravel-inertia-crud/?',
                default: true
            );
        }

        // 1. Publish config
        $this->call('vendor:publish', [
            '--tag'   => 'crud-config',
            '--force' => $force,
        ]);

        // 2. Publish frontend assets unless skipped
        if (!$this->option('no-assets')) {
            $this->call('vendor:publish', [
                '--tag'   => 'crud-assets',
                '--force' => $force,
            ]);
        }

        // 3. Optionally publish stubs
        if ($this->option('stubs')) {
            $this->call('vendor:publish', [
                '--tag'   => 'crud-stubs',
                '--force' => $force,
            ]);
        }

        // 4. Optionally publish portable AI Agent Skill (.agents/skills/)
        if ($publishAi) {
            $this->call('vendor:publish', [
                '--tag'   => 'crud-ai',
                '--force' => $force,
            ]);
        }

        // 5. Run starter bootstrapper if any starter option was selected
        $ranStarter = $roleMode !== 'none' || !empty($examples) || $wireApp;
        $bootstrapper = new StarterBootstrapper($files);

        if ($roleMode !== 'none') {
            $bootstrapper->setupRoles($roleMode, $force);
        }

        if (!empty($examples)) {
            $bootstrapper->scaffoldExamples($examples, $roleMode, $force);
        }

        if ($wireApp) {
            $bootstrapper->wireApplication($examples, $force);
        }

        if ($ranStarter && !empty($bootstrapper->results())) {
            $this->newLine();
            $this->line('  <fg=cyan;options=bold>Starter Bootstrap Summary</>');
            $this->table(['Artifact', 'Status', 'Details'], $bootstrapper->results());
        }

        // 6. Inspect (and optionally auto-install) frontend & Spatie prerequisites
        $requiredNpm = $this->requiredNpmPackages;
        $requiredUi  = $this->requiredShadcnComponents;

        if ($wireApp) {
            $requiredNpm[] = 'sonner';
            $requiredUi[]  = 'sonner';
        }

        $missingNpm = $this->findMissingNpmPackages($files, $requiredNpm);
        $missingUi  = $this->findMissingShadcnComponents($files, $requiredUi);
        $needsSpatie = $roleMode === 'spatie' && !class_exists(\Spatie\Permission\Traits\HasRoles::class);

        if ($installDeps) {
            $this->installDependencies($files, $missingNpm, $missingUi, $needsSpatie);
            // Re-evaluate after installation attempt
            $missingNpm  = $this->findMissingNpmPackages($files, $requiredNpm);
            $missingUi   = $this->findMissingShadcnComponents($files, $requiredUi);
            $needsSpatie = $roleMode === 'spatie' && !class_exists(\Spatie\Permission\Traits\HasRoles::class);
        }

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Prerequisites Check</>');
        $this->line('  <fg=gray>────────────────────────────────────────────────────────────</>');

        if ($needsSpatie) {
            $this->line('  <fg=yellow>!</> Spatie Permission package not yet installed.');
            $this->line('    Run: <fg=yellow>composer require spatie/laravel-permission</>');
            $this->line('    And: <fg=yellow>php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"</>');
        } elseif ($roleMode === 'spatie') {
            $this->line('  <fg=green>✓</> spatie/laravel-permission detected.');
        }

        if (empty($missingNpm)) {
            $this->line('  <fg=green>✓</> All required npm packages detected in package.json.');
        } else {
            $this->line('  <fg=yellow>!</> Missing npm packages: <options=bold>' . implode(', ', $missingNpm) . '</>');
            $this->line('    Run: <fg=yellow>npm install ' . implode(' ', $missingNpm) . '</>');
            if (in_array('lodash', $missingNpm, true)) {
                $this->line('    And: <fg=yellow>npm install -D @types/lodash</>');
            }
        }

        if (empty($missingUi)) {
            $this->line('  <fg=green>✓</> All required shadcn/ui components found in resources/js/components/ui/.');
        } else {
            $this->line('  <fg=yellow>!</> Missing shadcn/ui components (' . count($missingUi) . '): <fg=gray>' . implode(', ', $missingUi) . '</>');
            $this->line('    Run: <fg=yellow>npx shadcn@latest add ' . implode(' ', $missingUi) . '</>');
        }

        $this->newLine();
        $this->line('  <fg=green;options=bold>✓ Installation complete!</> Next steps:');
        $step = 1;

        if ($roleMode !== 'none' || in_array('posts', $examples, true)) {
            $this->line("  <fg=cyan>{$step}.</> Run migrations:                    <fg=yellow>php artisan migrate</>");
            $step++;
        }

        if ($roleMode !== 'none') {
            $this->line("  <fg=cyan>{$step}.</> Seed default admin & viewer users: <fg=yellow>php artisan db:seed --class=CrudAdminSeeder</>");
            $this->line('     <fg=gray>Default credentials: admin@example.com / password (super_admin)</>');
            $step++;
        }

        if ($ranStarter && in_array('posts', $examples, true)) {
            $this->line("  <fg=cyan>{$step}.</> Link public storage (for covers):  <fg=yellow>php artisan storage:link</>");
            $step++;
        }

        $this->line("  <fg=cyan>{$step}.</> Generate additional CRUDs anytime: <fg=yellow>php artisan crud:generate</>");
        $step++;
        $this->line("  <fg=cyan>{$step}.</> Verify your setup anytime with:    <fg=yellow>php artisan crud:doctor</>");
        $step++;
        $this->line("  <fg=cyan>{$step}.</> Full docs: <fg=gray>https://nagibmahfuj.github.io/laravel-inertia-crud/</>");
        $this->newLine();

        return self::SUCCESS;
    }

    protected function resolveRoleMode(bool $starterMode, bool $interactive): ?string
    {
        $raw = $this->option('roles');

        if ($raw !== null && $raw !== '') {
            $normalized = strtolower(trim((string) $raw));
            if (!in_array($normalized, ['column', 'spatie', 'none'], true)) {
                $this->components->error("Invalid --roles value '{$raw}'. Allowed values: column, spatie, none.");
                return null;
            }
            return $normalized;
        }

        if (!$starterMode) {
            return 'none';
        }

        if (!$interactive) {
            return class_exists(\Spatie\Permission\Traits\HasRoles::class) ? 'spatie' : 'column';
        }

        return (string) select(
            label: 'Which role system should be configured?',
            options: [
                'column' => 'Lightweight users.role column (zero extra packages — EloquentRoleResolver)',
                'spatie' => 'Spatie Laravel Permission (spatie/laravel-permission — multi-role HasRoles)',
                'none'   => 'None (keep my existing auth/role setup)',
            ],
            default: class_exists(\Spatie\Permission\Traits\HasRoles::class) ? 'spatie' : 'column',
            hint: 'Both options work out of the box with BaseCrudPolicy and config/crud.php.'
        );
    }

    /**
     * @return string[]|null
     */
    protected function resolveExamples(bool $starterMode, bool $interactive): ?array
    {
        $raw = $this->option('examples');

        if ($raw !== null && $raw !== '') {
            $items = array_values(array_unique(array_filter(
                array_map(fn ($v) => strtolower(trim($v)), explode(',', (string) $raw)),
                fn ($v) => $v !== ''
            )));

            if ($items === ['none']) {
                return [];
            }

            $valid = ['users', 'posts'];
            foreach ($items as $item) {
                if (!in_array($item, $valid, true)) {
                    $this->components->error("Invalid --examples entry '{$item}'. Allowed values: users, posts, none.");
                    return null;
                }
            }

            return $items;
        }

        if (!$starterMode) {
            return [];
        }

        if (!$interactive) {
            return ['users'];
        }

        return array_values((array) multiselect(
            label: 'Which starter CRUD resource(s) would you like to scaffold?',
            options: [
                'users' => 'Users CRUD (minimal user & role management with self-delete guard & KPI stats)',
                'posts' => 'Posts Showcase CRUD (image upload, slug, rich-text, tags, SoftDeletes, custom actions & CSV import)',
            ],
            default: ['users'],
            hint: 'Leave all unchecked to skip starter CRUD scaffolding.'
        ));
    }

    protected function installDependencies(
        Filesystem $files,
        array $missingNpm,
        array $missingUi,
        bool $needsSpatie
    ): void {
        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Installing Dependencies</>');

        if ($needsSpatie) {
            $this->runShellProcess(['composer', 'require', 'spatie/laravel-permission'], 'Installing spatie/laravel-permission');
            $this->runShellProcess(
                [PHP_BINARY, 'artisan', 'vendor:publish', '--provider=Spatie\\Permission\\PermissionServiceProvider'],
                'Publishing Spatie Permission config & migrations'
            );
        }

        if (!empty($missingNpm) && $files->exists(base_path('package.json'))) {
            $this->runShellProcess(
                array_merge(['npm', 'install'], $missingNpm),
                'Installing npm packages (' . implode(', ', $missingNpm) . ')'
            );

            if (in_array('lodash', $missingNpm, true)) {
                $this->runShellProcess(['npm', 'install', '-D', '@types/lodash'], 'Installing @types/lodash');
            }
        }

        if (!empty($missingUi) && $files->exists(base_path('components.json'))) {
            $this->runShellProcess(
                array_merge(['npx', 'shadcn@latest', 'add', '--yes'], $missingUi),
                'Installing shadcn/ui components (' . count($missingUi) . ')'
            );
        }
    }

    /**
     * @param  string[]  $command
     */
    protected function runShellProcess(array $command, string $description): bool
    {
        $this->line("  <fg=gray>→</> {$description}...");

        try {
            $process = new Process($command, base_path());
            $process->setTimeout(300);
            $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if (!$process->isSuccessful()) {
                $this->components->warn("Command failed: " . implode(' ', $command));
                return false;
            }

            return true;
        } catch (\Throwable $e) {
            $this->components->warn("Could not run '" . implode(' ', $command) . "': {$e->getMessage()}");
            return false;
        }
    }

    protected function findMissingNpmPackages(Filesystem $files, ?array $required = null): array
    {
        $required        = $required ?? $this->requiredNpmPackages;
        $packageJsonPath = base_path('package.json');
        if (!$files->exists($packageJsonPath)) {
            return $required;
        }

        $decoded = json_decode((string) $files->get($packageJsonPath), true);
        if (!is_array($decoded)) {
            return $required;
        }

        $installed = array_merge(
            array_keys((array) ($decoded['dependencies'] ?? [])),
            array_keys((array) ($decoded['devDependencies'] ?? [])),
        );

        return array_values(array_diff($required, $installed));
    }

    protected function findMissingShadcnComponents(Filesystem $files, ?array $required = null): array
    {
        $required = $required ?? $this->requiredShadcnComponents;
        $uiDir    = resource_path('js/components/ui');
        if (!$files->isDirectory($uiDir)) {
            return $required;
        }

        $missing = [];
        foreach ($required as $component) {
            $tsx = "{$uiDir}/{$component}.tsx";
            $jsx = "{$uiDir}/{$component}.jsx";
            if (!$files->exists($tsx) && !$files->exists($jsx)) {
                $missing[] = $component;
            }
        }

        return $missing;
    }
}
