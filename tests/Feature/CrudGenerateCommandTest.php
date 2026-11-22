<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    File::deleteDirectory(base_path('app/Http/Controllers/Dashboard'));
    File::deleteDirectory(base_path('app/Policies'));
    File::deleteDirectory(base_path('resources/js/pages/Admin'));
    File::deleteDirectory(base_path('resources/js/Pages/Dashboard'));
    File::deleteDirectory(resource_path('js/components/crud'));
    File::deleteDirectory(base_path('.agents'));
    File::delete(config_path('crud.php'));
});

it('displays package banner and generates controller, policy, normalized Index/Form/Show pages, and config entry', function () {
    File::ensureDirectoryExists(config_path());
    File::copy(__DIR__ . '/../../config/crud.php', config_path('crud.php'));
    File::ensureDirectoryExists(base_path('resources/js/pages'));

    $this->artisan('crud:generate', [
        '--model'        => 'Invoice',
        '--route-prefix' => 'invoices',
        '--page-prefix'  => 'Admin/Invoices',
        '--actions'      => 'create,edit,delete,show,export,import',
        '--all'          => true,
        '--force'        => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('LARAVEL INERTIA CRUD')
        ->expectsOutputToContain('nagibmahfuj/laravel-inertia-crud')
        ->expectsOutputToContain('Chunk Upload')
        ->expectsOutputToContain("Route::crudResource('invoices'");

    $controllerPath = base_path('app/Http/Controllers/Dashboard/InvoiceController.php');
    $policyPath     = base_path('app/Policies/InvoicePolicy.php');
    $indexPagePath  = base_path('resources/js/pages/Admin/Invoices/Index.tsx');
    $formPagePath   = base_path('resources/js/pages/Admin/Invoices/Form.tsx');
    $showPagePath   = base_path('resources/js/pages/Admin/Invoices/Show.tsx');

    expect(File::exists($controllerPath))->toBeTrue()
        ->and(File::get($controllerPath))->toContain("'actions'       => ['create', 'edit', 'delete', 'show', 'export', 'import']")
        ->and(File::exists($policyPath))->toBeTrue()
        ->and(File::get($policyPath))->toContain("return 'invoices';")
        ->and(File::exists($indexPagePath))->toBeTrue()
        ->and(File::get($indexPagePath))->toContain('page.props?.meta?.breadcrumbs')
        ->and(File::exists($formPagePath))->toBeTrue()
        ->and(File::get($formPagePath))->toContain('<ResourceForm {...props} />')
        ->and(File::exists($showPagePath))->toBeTrue()
        ->and(File::get($showPagePath))->toContain('<ResourceShow {...props} />')
        ->and(File::get(config_path('crud.php')))->toContain("'invoices' => ['roles' => ['super_admin', 'admin']]");
});

it('runs crud:install and crud:doctor cleanly', function () {
    $this->artisan('crud:install', ['--no-assets' => true, '--force' => true])
        ->assertSuccessful()
        ->expectsOutputToContain('LARAVEL INERTIA CRUD — INSTALLER')
        ->expectsOutputToContain('Installation complete');

    expect(File::exists(config_path('crud.php')))->toBeTrue();

    $this->artisan('crud:doctor')
        ->assertSuccessful()
        ->expectsOutputToContain('LARAVEL INERTIA CRUD — DOCTOR')
        ->expectsOutputToContain('All critical CRUD checks passed');
});

it('publishes portable AI agent skill when running crud:install --ai', function () {
    $this->artisan('crud:install', ['--no-assets' => true, '--ai' => true, '--force' => true])
        ->assertSuccessful();

    expect(File::exists(base_path('.agents/skills/laravel-inertia-crud/SKILL.md')))->toBeTrue()
        ->and(File::exists(base_path('.agents/skills/laravel-inertia-crud/references/resource-config.md')))->toBeTrue()
        ->and(File::exists(base_path('.agents/skills/laravel-inertia-crud/references/uploads-and-imports.md')))->toBeTrue();
});
