<?php

namespace NagibMahfuj\Crud\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class CrudInstallCommand extends Command
{
    protected $signature = 'crud:install
        {--stubs : Also publish customizable generator stubs}
        {--force : Overwrite existing published config/assets}
        {--no-assets : Skip publishing frontend React/TypeScript assets}';

    protected $description = 'Install Laravel Inertia CRUD config, frontend components, and inspect frontend prerequisites';

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

        $force = (bool) $this->option('force');

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

        // 4. Inspect package.json for npm prerequisites
        $missingNpm = $this->findMissingNpmPackages($files);
        $missingUi  = $this->findMissingShadcnComponents($files);

        $this->newLine();
        $this->line('  <fg=cyan;options=bold>Frontend Prerequisites Check</>');
        $this->line('  <fg=gray>────────────────────────────────────────────────────────────</>');

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
        $this->line('  <fg=cyan>1.</> Generate your first CRUD resource: <fg=yellow>php artisan crud:generate</>');
        $this->line('  <fg=cyan>2.</> Verify your setup anytime with:    <fg=yellow>php artisan crud:doctor</>');
        $this->line('  <fg=cyan>3.</> Full docs: <fg=gray>https://nagibmahfuj.github.io/laravel-inertia-crud/</>');
        $this->newLine();

        return self::SUCCESS;
    }

    protected function findMissingNpmPackages(Filesystem $files): array
    {
        $packageJsonPath = base_path('package.json');
        if (!$files->exists($packageJsonPath)) {
            return $this->requiredNpmPackages;
        }

        $decoded = json_decode((string) $files->get($packageJsonPath), true);
        if (!is_array($decoded)) {
            return $this->requiredNpmPackages;
        }

        $installed = array_merge(
            array_keys((array) ($decoded['dependencies'] ?? [])),
            array_keys((array) ($decoded['devDependencies'] ?? [])),
        );

        return array_values(array_diff($this->requiredNpmPackages, $installed));
    }

    protected function findMissingShadcnComponents(Filesystem $files): array
    {
        $uiDir = resource_path('js/components/ui');
        if (!$files->isDirectory($uiDir)) {
            return $this->requiredShadcnComponents;
        }

        $missing = [];
        foreach ($this->requiredShadcnComponents as $component) {
            $tsx = "{$uiDir}/{$component}.tsx";
            $jsx = "{$uiDir}/{$component}.jsx";
            if (!$files->exists($tsx) && !$files->exists($jsx)) {
                $missing[] = $component;
            }
        }

        return $missing;
    }
}

