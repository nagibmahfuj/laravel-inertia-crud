<?php

namespace NagibMahfuj\Crud\Support;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

/**
 * Idempotent starter bootstrapper invoked by `php artisan crud:install --starter`
 * (or granular flags `--roles=`, `--examples=`, `--wire-app`).
 *
 * Scaffolds user-selected role setup (`column` or `spatie`), starter CRUD resources
 * (`users` and/or `posts`), and wires stock Laravel React Starter Kit files safely.
 */
class StarterBootstrapper
{
    /**
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    protected array $results = [];

    protected int $migrationOffset = 0;

    public function __construct(protected Filesystem $files) {}

    /**
     * Return accumulated artifact results: [Category, Status, Detail].
     *
     * @return array<int, array{0: string, 1: string, 2: string}>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Configure the selected role system ('column', 'spatie', or 'none').
     */
    public function setupRoles(string $roleMode, bool $force = false): void
    {
        if ($roleMode === 'none') {
            return;
        }

        if ($roleMode === 'column') {
            $this->publishMigration(
                'add_role_to_users_table',
                $this->starterStubPath('add_role_to_users_table.php.stub'),
                $force,
                'Role Migration'
            );
            $this->addRoleToUserFillable();
            $this->writeStubFile(
                $this->starterStubPath('CrudAdminSeederColumn.stub'),
                database_path('seeders/CrudAdminSeeder.php'),
                [],
                $force,
                'Admin Seeder'
            );
            return;
        }

        if ($roleMode === 'spatie') {
            $this->addHasRolesTraitToUser();
            $this->writeStubFile(
                $this->starterStubPath('CrudAdminSeederSpatie.stub'),
                database_path('seeders/CrudAdminSeeder.php'),
                [],
                $force,
                'Admin Seeder'
            );
        }
    }

    /**
     * Scaffold user-selected starter CRUD resources ('users', 'posts').
     *
     * @param  string[]  $examples
     */
    public function scaffoldExamples(array $examples, string $roleMode = 'column', bool $force = false): void
    {
        if (empty($examples)) {
            return;
        }

        $controllerNs = (string) config('crud.generator.controller_namespace', 'App\\Http\\Controllers\\Dashboard');
        $policyNs     = (string) config('crud.generator.policy_namespace', 'App\\Policies');
        $routesToAdd  = [];

        if (in_array('users', $examples, true)) {
            $useSpatie = $roleMode === 'spatie'
                || ($roleMode === 'none' && class_exists(\Spatie\Permission\Traits\HasRoles::class));

            $controllerStub = $useSpatie
                ? $this->starterStubPath('UserControllerSpatie.stub')
                : $this->starterStubPath('UserControllerColumn.stub');

            $this->writeStubFile(
                $controllerStub,
                $this->namespaceToPath($controllerNs, 'UserController.php'),
                [
                    '{{ namespace }}'  => $controllerNs,
                    '{{ pagePrefix }}' => 'Dashboard/Users',
                ],
                $force,
                'Users Controller'
            );

            $this->writeStubFile(
                $this->starterStubPath('UserPolicy.stub'),
                $this->namespaceToPath($policyNs, 'UserPolicy.php'),
                ['{{ namespace }}' => $policyNs],
                $force,
                'Users Policy'
            );

            $this->scaffoldCrudPages('Dashboard/Users', $force, 'Users');

            $this->addResourceConfigEntry(
                'users',
                "        'users' => [\n            'roles'     => ['super_admin', 'admin', 'manager', 'viewer'],\n            'read_only' => ['viewer'],\n            'no_delete' => ['manager'],\n        ],\n"
            );

            $routesToAdd['users'] = "\\{$controllerNs}\\UserController::class";
        }

        if (in_array('posts', $examples, true)) {
            $this->writeStubFile(
                $this->starterStubPath('PostModel.stub'),
                app_path('Models/Post.php'),
                [],
                $force,
                'Post Model'
            );

            $this->publishMigration(
                'create_posts_table',
                $this->starterStubPath('create_posts_table.php.stub'),
                $force,
                'Posts Migration'
            );

            $this->writeStubFile(
                $this->starterStubPath('PostController.stub'),
                $this->namespaceToPath($controllerNs, 'PostController.php'),
                [
                    '{{ namespace }}'  => $controllerNs,
                    '{{ pagePrefix }}' => 'Dashboard/Posts',
                ],
                $force,
                'Posts Controller'
            );

            $this->writeStubFile(
                $this->starterStubPath('PostPolicy.stub'),
                $this->namespaceToPath($policyNs, 'PostPolicy.php'),
                ['{{ namespace }}' => $policyNs],
                $force,
                'Posts Policy'
            );

            $this->scaffoldCrudPages('Dashboard/Posts', $force, 'Posts');

            $this->addResourceConfigEntry(
                'posts',
                "        'posts' => [\n            'roles'     => ['super_admin', 'admin', 'manager', 'viewer'],\n            'read_only' => ['viewer'],\n        ],\n"
            );

            $routesToAdd['posts'] = "\\{$controllerNs}\\PostController::class";
        }

        if (!empty($routesToAdd)) {
            $this->registerCrudRoutes($routesToAdd);
        }
    }

    /**
     * Idempotently wire stock Laravel React Starter Kit files for CRUD layouts,
     * flash toasts, dynamic navigation, runtime theming, and chunk cleanup schedule.
     *
     * @param  string[]  $examples
     */
    public function wireApplication(array $examples = [], bool $force = false): void
    {
        $this->wireHandleInertiaRequests($examples);
        $this->wireAppLayout($force);
        $this->wireAppTsx();
        $this->wireAppSidebar();
        $this->wireConsoleSchedule();
    }

    // ── Resource Scaffolding Helpers ─────────────────────────

    protected function scaffoldCrudPages(string $pagePrefix, bool $force, string $labelPrefix): void
    {
        $targetDir = $this->resolvePageTargetDirectory($pagePrefix);

        foreach (['Index' => 'IndexPage.stub', 'Form' => 'FormPage.stub', 'Show' => 'ShowPage.stub'] as $page => $stub) {
            $this->writeStubFile(
                $this->packageStubPath($stub),
                base_path("{$targetDir}/{$page}.tsx"),
                [],
                $force,
                "{$labelPrefix} {$page}.tsx"
            );
        }
    }

    protected function publishMigration(string $suffix, string $stubPath, bool $force, string $label): void
    {
        $migrationsDir = database_path('migrations');
        $existing      = $this->files->isDirectory($migrationsDir)
            ? $this->files->glob("{$migrationsDir}/*_{$suffix}.php")
            : [];

        if (!empty($existing) && !$force) {
            $this->results[] = [$label, 'EXISTS', $this->relativePath($existing[0])];
            return;
        }

        $targetPath = !empty($existing)
            ? $existing[0]
            : $migrationsDir . '/' . date('Y_m_d_His', time() + $this->migrationOffset++) . "_{$suffix}.php";

        $this->files->ensureDirectoryExists($migrationsDir);
        $this->files->put($targetPath, $this->files->get($stubPath));

        $status          = !empty($existing) ? 'OVERWRITTEN' : 'CREATED';
        $this->results[] = [$label, $status, $this->relativePath($targetPath)];
    }

    protected function addResourceConfigEntry(string $routePrefix, string $entry): void
    {
        $configPath = config_path('crud.php');

        if (!$this->files->exists($configPath)) {
            $this->results[] = ['Config', 'SKIPPED', "config/crud.php not found (skipped '{$routePrefix}')"];
            return;
        }

        $content = $this->files->get($configPath);

        if (preg_match("/['\"]" . preg_quote($routePrefix, '/') . "['\"]\s*=>/", $content)) {
            $this->results[] = ['Config', 'EXISTS', "config/crud.php → resources.{$routePrefix}"];
            return;
        }

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
            return;
        }

        $this->results[] = ['Config', 'MANUAL', "Add '{$routePrefix}' under resources in config/crud.php"];
    }

    /**
     * @param  array<string, string>  $routes  [routePrefix => ControllerFqcn::class]
     */
    protected function registerCrudRoutes(array $routes): void
    {
        $webPath = base_path('routes/web.php');

        if (!$this->files->exists($webPath)) {
            $this->results[] = ['Routes', 'SKIPPED', 'routes/web.php not found'];
            return;
        }

        $content = $this->files->get($webPath);
        $missing = [];

        foreach ($routes as $prefix => $controllerClass) {
            if (!preg_match("/crudResource\(\s*['\"]" . preg_quote($prefix, '/') . "['\"]/", $content)) {
                $missing[$prefix] = $controllerClass;
            }
        }

        if (empty($missing)) {
            $this->results[] = ['Routes', 'EXISTS', 'routes/web.php (CRUD routes already registered)'];
            return;
        }

        $lines = [];
        foreach ($missing as $prefix => $controllerClass) {
            $lines[] = "    Route::crudResource('{$prefix}', {$controllerClass});";
        }
        $routeBlock = implode("\n", $lines);

        // If a CRUD group marker already exists, append inside it cleanly
        if (str_contains($content, '// Laravel Inertia CRUD Resources')) {
            $updated = preg_replace(
                '/(^\s*\/\/ Laravel Inertia CRUD Resources\r?\n)/m',
                "$1{$routeBlock}\n",
                $content,
                1,
                $count
            );

            if ($count > 0 && $updated !== null) {
                $this->files->put($webPath, $updated);
                $this->results[] = ['Routes', 'UPDATED', 'routes/web.php (' . implode(', ', array_keys($missing)) . ')'];
                return;
            }
        }

        $groupSnippet = "\nRoute::middleware(['auth', 'verified'])->prefix('dashboard')->group(function () {\n"
            . "    // Laravel Inertia CRUD Resources\n"
            . "{$routeBlock}\n"
            . "});\n";

        $this->files->put($webPath, rtrim($content) . "\n" . $groupSnippet);
        $this->results[] = ['Routes', 'UPDATED', 'routes/web.php (' . implode(', ', array_keys($missing)) . ')'];
    }

    // ── User Model Role Helpers ──────────────────────────────

    protected function addRoleToUserFillable(): void
    {
        $userPath = app_path('Models/User.php');
        if (!$this->files->exists($userPath)) {
            $this->results[] = ['User Model', 'SKIPPED', 'app/Models/User.php not found'];
            return;
        }

        $content = $this->files->get($userPath);
        if (preg_match('/\$fillable\s*=\s*\[[^\]]*[\'"]role[\'"]/s', $content)) {
            $this->results[] = ['User Model', 'EXISTS', "app/Models/User.php already includes 'role' in \$fillable"];
            return;
        }

        $updated = preg_replace_callback(
            '/(\$fillable\s*=\s*\[)([^\]]*)(\];)/s',
            function (array $matches): string {
                $body = rtrim($matches[2]);
                if ($body !== '' && !str_ends_with($body, ',')) {
                    $body .= ',';
                }

                return "{$matches[1]}{$body}\n        'role',\n    {$matches[3]}";
            },
            $content,
            1,
            $count
        );

        if ($count > 0 && $updated !== null) {
            $this->files->put($userPath, $updated);
            $this->results[] = ['User Model', 'UPDATED', "Added 'role' to User::\$fillable"];
            return;
        }

        $this->results[] = ['User Model', 'MANUAL', "Add 'role' to \$fillable in app/Models/User.php"];
    }

    protected function addHasRolesTraitToUser(): void
    {
        $userPath = app_path('Models/User.php');
        if (!$this->files->exists($userPath)) {
            $this->results[] = ['User Model', 'SKIPPED', 'app/Models/User.php not found'];
            return;
        }

        $content = $this->files->get($userPath);
        if (str_contains($content, 'HasRoles')) {
            $this->results[] = ['User Model', 'EXISTS', 'app/Models/User.php already uses HasRoles'];
            return;
        }

        // Insert import after namespace declaration
        $updated = preg_replace(
            '/(namespace\s+App\\\\Models;\r?\n)/',
            "$1\nuse Spatie\\Permission\\Traits\\HasRoles;\n",
            $content,
            1,
            $importCount
        );

        // Insert trait use statement inside class body
        if ($importCount > 0 && $updated !== null) {
            $updated = preg_replace(
                '/(class\s+User\s+extends\s+[^{]+\{\r?\n)/',
                "$1    use HasRoles;\n",
                $updated,
                1,
                $traitCount
            );

            if ($traitCount > 0 && $updated !== null) {
                $this->files->put($userPath, $updated);
                $this->results[] = ['User Model', 'UPDATED', 'Added Spatie HasRoles trait to app/Models/User.php'];
                return;
            }
        }

        $this->results[] = ['User Model', 'MANUAL', 'Add `use Spatie\\Permission\\Traits\\HasRoles;` to app/Models/User.php'];
    }

    // ── Host Application Wiring Helpers ──────────────────────

    /**
     * @param  string[]  $examples
     */
    protected function wireHandleInertiaRequests(array $examples): void
    {
        $path = app_path('Http/Middleware/HandleInertiaRequests.php');
        if (!$this->files->exists($path)) {
            $this->results[] = ['Inertia Middleware', 'SKIPPED', 'app/Http/Middleware/HandleInertiaRequests.php not found'];
            return;
        }

        $content      = $this->files->get($path);
        $hasFlashData = str_contains($content, "'flash'") && str_contains($content, "get('data')");
        $hasNavGroups = str_contains($content, 'NavBuilder::filter');

        if ($hasFlashData && $hasNavGroups) {
            $this->results[] = ['Inertia Middleware', 'EXISTS', 'HandleInertiaRequests already shares flash & navGroups'];
            return;
        }

        $updated = $content;

        if (!str_contains($updated, 'NagibMahfuj\\Crud\\Support\\NavBuilder')) {
            $updated = preg_replace(
                '/(use\s+Inertia\\\\Middleware;\r?\n)/',
                "$1use NagibMahfuj\\Crud\\Support\\NavBuilder;\n",
                $updated,
                1
            ) ?? $updated;
        }

        $snippets = [];
        if (!$hasFlashData && !str_contains($updated, "'flash'")) {
            $snippets[] = <<<'PHP'
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
                'data'    => fn () => $request->session()->get('data'),
            ],
PHP;
        }

        if (!$hasNavGroups) {
            $managementItems = [];
            if (in_array('users', $examples, true)) {
                $managementItems[] = "                        ['title' => 'Users', 'href' => '/dashboard/users', 'icon' => 'Users', 'permission' => \\App\\Models\\User::class],";
            }
            if (in_array('posts', $examples, true)) {
                $managementItems[] = "                        ['title' => 'Posts', 'href' => '/dashboard/posts', 'icon' => 'FileText', 'permission' => \\App\\Models\\Post::class],";
            }

            $managementBlock = '';
            if (!empty($managementItems)) {
                $itemsStr        = implode("\n", $managementItems);
                $managementBlock = "\n                [\n                    'title' => 'Management',\n                    'items' => [\n{$itemsStr}\n                    ],\n                ],";
            }

            $snippets[] = <<<PHP
            'navGroups' => fn () => NavBuilder::filter([
                [
                    'title' => 'General',
                    'items' => [
                        ['title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'LayoutGrid'],
                    ],
                ],{$managementBlock}
            ], \$request->user()),
PHP;
        }

        if (empty($snippets)) {
            $this->results[] = ['Inertia Middleware', 'EXISTS', 'HandleInertiaRequests already configured'];
            return;
        }

        $insertion = "\n" . implode("\n", $snippets) . "\n";

        $patterns = [
            '/(\.\.\.parent::share\(\$request\),\r?\n)/',
            '/(return\s+array_merge\(parent::share\(\$request\),\s*\[\r?\n)/',
        ];

        $replaced = false;
        foreach ($patterns as $pattern) {
            $candidate = preg_replace($pattern, "$1{$insertion}", $updated, 1, $count);
            if ($count > 0 && $candidate !== null) {
                $updated  = $candidate;
                $replaced = true;
                break;
            }
        }

        if ($replaced) {
            $this->files->put($path, $updated);
            $this->results[] = ['Inertia Middleware', 'UPDATED', 'Added flash & NavBuilder navGroups in HandleInertiaRequests.php'];
            return;
        }

        $this->results[] = ['Inertia Middleware', 'MANUAL', 'Share flash (success, error, data) & navGroups in HandleInertiaRequests.php'];
    }

    protected function wireAppLayout(bool $force): void
    {
        $path = resource_path('js/layouts/app-layout.tsx');
        if (!$this->files->exists($path)) {
            $this->results[] = ['AppLayout', 'SKIPPED', 'resources/js/layouts/app-layout.tsx not found'];
            return;
        }

        $content = $this->files->get($path);
        if (str_contains($content, 'meta?.breadcrumbs') && str_contains($content, 'toast.')) {
            $this->results[] = ['AppLayout', 'EXISTS', 'resources/js/layouts/app-layout.tsx already wired'];
            return;
        }

        if (!$force && !str_contains($content, 'AppLayoutTemplate')) {
            $this->results[] = ['AppLayout', 'SKIPPED', 'Custom AppLayout detected (use --force or wire meta.breadcrumbs manually)'];
            return;
        }

        $normalizedLayout = <<<'TSX'
import { usePage } from '@inertiajs/react';
import { useEffect, type ReactNode } from 'react';
import { toast } from 'sonner';
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';

interface AppLayoutProps {
    children: ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function AppLayout({ children, breadcrumbs, ...props }: AppLayoutProps) {
    const { flash, meta } = usePage().props as any;
    const resolvedBreadcrumbs = breadcrumbs ?? meta?.breadcrumbs ?? [];

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash]);

    return (
        <AppLayoutTemplate breadcrumbs={resolvedBreadcrumbs} {...props}>
            {children}
        </AppLayoutTemplate>
    );
}
TSX;

        $this->files->put($path, $normalizedLayout . "\n");
        $this->results[] = ['AppLayout', 'UPDATED', 'Wired meta.breadcrumbs fallback & flash toasts in app-layout.tsx'];
    }

    protected function wireAppTsx(): void
    {
        $path = resource_path('js/app.tsx');
        if (!$this->files->exists($path)) {
            $this->results[] = ['Inertia Entry', 'SKIPPED', 'resources/js/app.tsx not found'];
            return;
        }

        $content = $this->files->get($path);
        if (str_contains($content, 'applyCustomTheme') && str_contains($content, '<Toaster')) {
            $this->results[] = ['Inertia Entry', 'EXISTS', 'resources/js/app.tsx already mounts Toaster & theme'];
            return;
        }

        $updated = $content;
        $changed = false;

        if (!str_contains($updated, '@/components/ui/sonner')) {
            $updated = "import { Toaster } from '@/components/ui/sonner';\n" . $updated;
            $changed = true;
        }

        if (!str_contains($updated, '@/lib/crud-theme')) {
            $updated = "import { applyCustomTheme, theme } from '@/lib/crud-theme';\n" . $updated;
            $changed = true;
        }

        // Inject <Toaster /> inside Inertia v3 `withApp` or Inertia v1/v2 `root.render`
        if (!str_contains($updated, '<Toaster')) {
            if (preg_match('/(<App\s+\{\.\.\.props\}\s*\/>)/', $updated)) {
                $updated = preg_replace(
                    '/(<App\s+\{\.\.\.props\}\s*\/>)/',
                    "<>\n                $1\n                <Toaster {...theme.toast} />\n            </>",
                    $updated,
                    1
                ) ?? $updated;
                $changed = true;
            } elseif (preg_match('/(\{app\})/', $updated)) {
                $updated = preg_replace(
                    '/(\{app\})/',
                    "$1\n                <Toaster {...theme.toast} />",
                    $updated,
                    1
                ) ?? $updated;
                $changed = true;
            }
        }

        if (!str_contains($updated, 'applyCustomTheme()')) {
            $updated = rtrim($updated) . "\n\napplyCustomTheme();\n";
            $changed = true;
        }

        if ($changed) {
            $this->files->put($path, $updated);
            $this->results[] = ['Inertia Entry', 'UPDATED', 'Added <Toaster /> & applyCustomTheme() to resources/js/app.tsx'];
            return;
        }

        $this->results[] = ['Inertia Entry', 'MANUAL', 'Mount <Toaster {...theme.toast} /> and call applyCustomTheme() in resources/js/app.tsx'];
    }

    protected function wireAppSidebar(): void
    {
        $path = resource_path('js/components/app-sidebar.tsx');
        if (!$this->files->exists($path)) {
            $this->results[] = ['AppSidebar', 'SKIPPED', 'resources/js/components/app-sidebar.tsx not found'];
            return;
        }

        $content = $this->files->get($path);
        if (str_contains($content, 'useNavGroups')) {
            $this->results[] = ['AppSidebar', 'EXISTS', 'resources/js/components/app-sidebar.tsx already uses useNavGroups()'];
            return;
        }

        if (!str_contains($content, '<NavMain items={mainNavItems} />')) {
            $this->results[] = ['AppSidebar', 'MANUAL', 'Wire useNavGroups() in resources/js/components/app-sidebar.tsx'];
            return;
        }

        $updated = "import { useNavGroups } from '@/hooks/use-nav-items';\n" . $content;

        // Add `const navGroups = useNavGroups();` inside AppSidebar component
        $updated = preg_replace(
            '/(export\s+function\s+AppSidebar\s*\([^)]*\)\s*\{\r?\n)/',
            "$1    const navGroups = useNavGroups();\n",
            $updated,
            1,
            $fnCount
        );

        if ($fnCount > 0 && $updated !== null) {
            $navReplacement = "{navGroups.length > 0 ? (\n                    navGroups.map((group) => (\n                        <NavMain key={group.title} items={group.items} />\n                    ))\n                ) : (\n                    <NavMain items={mainNavItems} />\n                )}";
            $updated = str_replace('<NavMain items={mainNavItems} />', $navReplacement, $updated);

            $this->files->put($path, $updated);
            $this->results[] = ['AppSidebar', 'UPDATED', 'Wired dynamic useNavGroups() in resources/js/components/app-sidebar.tsx'];
            return;
        }

        $this->results[] = ['AppSidebar', 'MANUAL', 'Wire useNavGroups() in resources/js/components/app-sidebar.tsx'];
    }

    protected function wireConsoleSchedule(): void
    {
        $path = base_path('routes/console.php');
        if (!$this->files->exists($path)) {
            $this->results[] = ['Console Schedule', 'SKIPPED', 'routes/console.php not found'];
            return;
        }

        $content = $this->files->get($path);
        if (str_contains($content, 'crud:cleanup-chunks')) {
            $this->results[] = ['Console Schedule', 'EXISTS', 'routes/console.php already schedules crud:cleanup-chunks'];
            return;
        }

        $updated = $content;
        if (!str_contains($updated, 'Illuminate\\Support\\Facades\\Schedule')) {
            $updated = preg_replace(
                '/(<\?php\r?\n)/',
                "$1\nuse Illuminate\\Support\\Facades\\Schedule;\n",
                $updated,
                1
            ) ?? $updated;
        }

        $updated = rtrim($updated) . "\n\nSchedule::command('crud:cleanup-chunks')->hourly();\n";
        $this->files->put($path, $updated);
        $this->results[] = ['Console Schedule', 'UPDATED', 'Scheduled crud:cleanup-chunks hourly in routes/console.php'];
    }

    // ── Low-Level File Utilities ─────────────────────────────

    /**
     * @param  array<string, string>  $replacements
     */
    protected function writeStubFile(
        string $stubPath,
        string $targetPath,
        array $replacements,
        bool $force,
        string $label
    ): void {
        $exists = $this->files->exists($targetPath);
        if ($exists && !$force) {
            $this->results[] = [$label, 'SKIPPED', $this->relativePath($targetPath) . ' (exists)'];
            return;
        }

        $content = $this->files->get($stubPath);
        if (!empty($replacements)) {
            $content = str_replace(array_keys($replacements), array_values($replacements), $content);
        }

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $content);

        $this->results[] = [$label, $exists ? 'OVERWRITTEN' : 'CREATED', $this->relativePath($targetPath)];
    }

    protected function resolvePageTargetDirectory(string $pagePrefix): string
    {
        $configured = config('crud.generator.pages_directory');
        if (!empty($configured)) {
            return rtrim((string) $configured, '/') . '/' . ltrim($pagePrefix, '/');
        }

        $base = $this->files->isDirectory(base_path('resources/js/pages'))
            ? 'resources/js/pages'
            : ($this->files->isDirectory(base_path('resources/js/Pages')) ? 'resources/js/Pages' : 'resources/js/pages');

        return "{$base}/" . ltrim($pagePrefix, '/');
    }

    protected function namespaceToPath(string $namespace, string $filename): string
    {
        $relative = Str::replaceFirst('App\\', '', trim($namespace, '\\'));
        $relative = str_replace('\\', '/', $relative);

        return app_path($relative . '/' . $filename);
    }

    protected function starterStubPath(string $name): string
    {
        return __DIR__ . '/../../stubs/starter/' . $name;
    }

    protected function packageStubPath(string $name): string
    {
        $published = base_path("stubs/crud/{$name}");
        if ($this->files->exists($published)) {
            return $published;
        }

        return __DIR__ . '/../../stubs/' . $name;
    }

    protected function relativePath(string $absolutePath): string
    {
        $base = rtrim(base_path(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        return str_starts_with($absolutePath, $base)
            ? substr($absolutePath, strlen($base))
            : $absolutePath;
    }
}
