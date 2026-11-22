<?php

use Illuminate\Support\Facades\File;

afterEach(function () {
    // Clean up generated test files inside Testbench skeleton
    File::deleteDirectory(base_path('app/Http/Controllers/Dashboard'));
    File::deleteDirectory(base_path('app/Policies'));
    File::deleteDirectory(base_path('resources/js/pages/Admin'));
    File::deleteDirectory(base_path('resources/js/Pages/Dashboard'));
    File::delete(config_path('crud.php'));
});

it('displays package banner and generates controller, policy, normalized pages, and config entry', function () {
    // Prepare published config file so --permissions updates it
    File::ensureDirectoryExists(config_path());
    File::copy(__DIR__ . '/../../config/crud.php', config_path('crud.php'));

    // Ensure lowercase resources/js/pages exists to test auto-detection
    File::ensureDirectoryExists(base_path('resources/js/pages'));

    $this->artisan('crud:generate', [
        '--model'       => 'Invoice',
        '--route-prefix'=> 'invoices',
        '--page-prefix' => 'Admin/Invoices',
        '--actions'     => 'create,edit,delete,export,import',
        '--all'         => true,
        '--force'       => true,
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

    expect(File::exists($controllerPath))->toBeTrue()
        ->and(File::get($controllerPath))->toContain("'actions'       => ['create', 'edit', 'delete', 'export', 'import']")
        ->and(File::exists($policyPath))->toBeTrue()
        ->and(File::get($policyPath))->toContain("return 'invoices';")
        ->and(File::exists($indexPagePath))->toBeTrue()
        ->and(File::get($indexPagePath))->toContain('page.props?.meta?.breadcrumbs')
        ->and(File::get($indexPagePath))->not->toContain('exportRoute=')
        ->and(File::exists($formPagePath))->toBeTrue()
        ->and(File::get($formPagePath))->toContain('<ResourceForm {...props} />')
        ->and(File::get(config_path('crud.php')))->toContain("'invoices' => ['roles' => ['super_admin', 'admin']]");
});

