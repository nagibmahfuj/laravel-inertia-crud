<?php

use Illuminate\Support\Facades\File;

function seedStarterFixtureFiles(): void
{
    File::ensureDirectoryExists(app_path('Models'));
    File::ensureDirectoryExists(app_path('Http/Middleware'));
    File::ensureDirectoryExists(base_path('routes'));
    File::ensureDirectoryExists(database_path('migrations'));
    File::ensureDirectoryExists(database_path('seeders'));
    File::ensureDirectoryExists(resource_path('js/layouts'));
    File::ensureDirectoryExists(resource_path('js/components'));
    File::ensureDirectoryExists(resource_path('js/pages'));

    File::put(app_path('Models/User.php'), <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    protected $fillable = [
        'name',
        'email',
        'password',
    ];
}
PHP);

    File::put(app_path('Http/Middleware/HandleInertiaRequests.php'), <<<'PHP'
<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
        ];
    }
}
PHP);

    File::put(base_path('routes/web.php'), <<<'PHP'
<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => 'home');
PHP);

    File::put(base_path('routes/console.php'), <<<'PHP'
<?php

use Illuminate\Support\Facades\Artisan;
PHP);

    File::put(resource_path('js/layouts/app-layout.tsx'), <<<'TSX'
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { type ReactNode } from 'react';

export default ({ children, breadcrumbs, ...props }: { children: ReactNode; breadcrumbs?: BreadcrumbItem[] }) => (
    <AppLayoutTemplate breadcrumbs={breadcrumbs} {...props}>
        {children}
    </AppLayoutTemplate>
);
TSX);

    File::put(resource_path('js/app.tsx'), <<<'TSX'
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

createInertiaApp({
    setup({ el, App, props }) {
        const root = createRoot(el);
        root.render(<App {...props} />);
    },
});
TSX);

    File::put(resource_path('js/components/app-sidebar.tsx'), <<<'TSX'
import { NavMain } from '@/components/nav-main';

const mainNavItems = [{ title: 'Dashboard', href: '/dashboard' }];

export function AppSidebar() {
    return (
        <div>
            <NavMain items={mainNavItems} />
        </div>
    );
}
TSX);
}

function cleanupStarterFixtures(): void
{
    File::deleteDirectory(app_path('Http/Controllers/Dashboard'));
    File::deleteDirectory(app_path('Http/Middleware'));
    File::deleteDirectory(app_path('Policies'));
    File::deleteDirectory(app_path('Models'));
    File::deleteDirectory(resource_path('js/pages/Dashboard'));
    File::deleteDirectory(resource_path('js/layouts'));
    File::deleteDirectory(resource_path('js/components'));
    File::deleteDirectory(base_path('.agents'));
    File::delete(resource_path('js/app.tsx'));
    File::delete(config_path('crud.php'));
    File::delete(database_path('seeders/CrudAdminSeeder.php'));
    File::delete(base_path('routes/web.php'));
    File::delete(base_path('routes/console.php'));

    foreach (File::glob(database_path('migrations/*_add_role_to_users_table.php')) as $m) {
        File::delete($m);
    }
    foreach (File::glob(database_path('migrations/*_create_posts_table.php')) as $m) {
        File::delete($m);
    }
}

beforeEach(fn () => seedStarterFixtureFiles());
afterEach(fn () => cleanupStarterFixtures());

it('bootstraps column roles, users + posts examples, and wires app files idempotently', function () {
    $this->artisan('crud:install', [
        '--starter'   => true,
        '--roles'     => 'column',
        '--examples'  => 'users,posts',
        '--wire-app'  => true,
        '--no-assets' => true,
        '--force'     => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Starter Bootstrap Summary')
        ->expectsOutputToContain('CrudAdminSeeder');

    // Role migration + User::$fillable + Seeder
    $roleMigrations = File::glob(database_path('migrations/*_add_role_to_users_table.php'));
    expect($roleMigrations)->toHaveCount(1)
        ->and(File::get(app_path('Models/User.php')))->toContain("'role'")
        ->and(File::get(database_path('seeders/CrudAdminSeeder.php')))->toContain("'role'              => 'super_admin'");

    // Users & Posts CRUD files
    $postMigrations = File::glob(database_path('migrations/*_create_posts_table.php'));
    expect($postMigrations)->toHaveCount(1)
        ->and(File::exists(app_path('Models/Post.php')))->toBeTrue()
        ->and(File::exists(app_path('Http/Controllers/Dashboard/UserController.php')))->toBeTrue()
        ->and(File::exists(app_path('Policies/UserPolicy.php')))->toBeTrue()
        ->and(File::exists(app_path('Http/Controllers/Dashboard/PostController.php')))->toBeTrue()
        ->and(File::exists(app_path('Policies/PostPolicy.php')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Users/Index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Users/Form.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Users/Show.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Posts/Index.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Posts/Form.tsx')))->toBeTrue()
        ->and(File::exists(resource_path('js/pages/Dashboard/Posts/Show.tsx')))->toBeTrue();

    // Config & Routes
    expect(File::get(config_path('crud.php')))
        ->toContain("'users' => [")
        ->toContain("'posts' => [");

    $web = File::get(base_path('routes/web.php'));
    expect($web)
        ->toContain("Route::crudResource('users', \\App\\Http\\Controllers\\Dashboard\\UserController::class);")
        ->toContain("Route::crudResource('posts', \\App\\Http\\Controllers\\Dashboard\\PostController::class);");

    // Host-app wiring
    expect(File::get(app_path('Http/Middleware/HandleInertiaRequests.php')))
        ->toContain('NavBuilder::filter')
        ->toContain("get('data')")
        ->toContain('\\App\\Models\\User::class')
        ->toContain('\\App\\Models\\Post::class');

    expect(File::get(resource_path('js/layouts/app-layout.tsx')))
        ->toContain('meta?.breadcrumbs')
        ->toContain('toast.success');

    expect(File::get(resource_path('js/app.tsx')))
        ->toContain('<Toaster {...theme.toast} />')
        ->toContain('applyCustomTheme();');

    expect(File::get(resource_path('js/components/app-sidebar.tsx')))
        ->toContain('useNavGroups()');

    expect(File::get(base_path('routes/console.php')))
        ->toContain("Schedule::command('crud:cleanup-chunks')->hourly();");

    // Re-running without --force must be idempotent (zero duplicated entries)
    $this->artisan('crud:install', [
        '--starter'   => true,
        '--roles'     => 'column',
        '--examples'  => 'users,posts',
        '--wire-app'  => true,
        '--no-assets' => true,
    ])->assertSuccessful();

    expect(substr_count(File::get(base_path('routes/web.php')), "Route::crudResource('users'"))->toBe(1)
        ->and(substr_count(File::get(base_path('routes/web.php')), "Route::crudResource('posts'"))->toBe(1)
        ->and(substr_count(File::get(base_path('routes/console.php')), 'crud:cleanup-chunks'))->toBe(1)
        ->and(substr_count(File::get(app_path('Models/User.php')), "'role'"))->toBe(1)
        ->and(File::glob(database_path('migrations/*_add_role_to_users_table.php')))->toHaveCount(1)
        ->and(File::glob(database_path('migrations/*_create_posts_table.php')))->toHaveCount(1);
});

it('bootstraps spatie role mode and users-only example cleanly', function () {
    $this->artisan('crud:install', [
        '--starter'   => true,
        '--roles'     => 'spatie',
        '--examples'  => 'users',
        '--no-assets' => true,
        '--force'     => true,
    ])->assertSuccessful();

    expect(File::get(app_path('Models/User.php')))
        ->toContain('use Spatie\\Permission\\Traits\\HasRoles;')
        ->toContain('use HasRoles;')
        ->and(File::get(app_path('Http/Controllers/Dashboard/UserController.php')))
        ->toContain('syncRoles')
        ->toContain('role_names')
        ->and(File::get(database_path('seeders/CrudAdminSeeder.php')))
        ->toContain('\\Spatie\\Permission\\Models\\Role::class')
        ->and(File::glob(database_path('migrations/*_add_role_to_users_table.php')))->toBeEmpty()
        ->and(File::exists(app_path('Models/Post.php')))->toBeFalse();
});

it('rejects invalid --roles or --examples option values', function () {
    $this->artisan('crud:install', [
        '--roles'     => 'invalid-mode',
        '--no-assets' => true,
    ])->assertFailed();

    $this->artisan('crud:install', [
        '--examples'  => 'unknown-resource',
        '--no-assets' => true,
    ])->assertFailed();
});
