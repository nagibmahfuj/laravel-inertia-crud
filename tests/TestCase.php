<?php

namespace NagibMahfuj\Crud\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Inertia\ServiceProvider as InertiaServiceProvider;
use NagibMahfuj\Crud\CrudServiceProvider;
use NagibMahfuj\Crud\Tests\Fixtures\Product;
use NagibMahfuj\Crud\Tests\Fixtures\ProductController;
use NagibMahfuj\Crud\Tests\Fixtures\ProductPolicy;
use NagibMahfuj\Crud\Tests\Fixtures\ReadOnlyExportDisabledController;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticle;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticleController;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticlePolicy;
use NagibMahfuj\Crud\Tests\Fixtures\User;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            InertiaServiceProvider::class,
            CrudServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:2fl+Ktvkfl+Fuz4Qp/A75G2RTiWVA/ZoKZvp6fiiM10=');
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        // Disable Inertia page file existence check in tests and register test view
        $app['config']->set('inertia.testing.ensure_pages_exist', false);
        $app['view']->addLocation(__DIR__ . '/Fixtures/views');

        // Configure test CRUD permissions
        $app['config']->set('crud.resources', [
            'products' => [
                'roles'       => ['super_admin', 'admin', 'manager', 'viewer'],
                'create_only' => ['manager'],
                'no_delete'   => ['admin'],
            ],
            'locked-products' => [
                'roles'           => ['super_admin', 'admin', 'viewer'],
                'read_only_roles' => ['viewer'],
                'no_delete'       => true,
            ],
            'articles' => [
                'roles' => ['super_admin', 'admin', 'viewer'],
            ],
        ]);
        $app['config']->set('crud.read_only_roles', ['viewer']);
        $app['config']->set('crud.no_delete_roles', []);
        $app['config']->set('crud.chunk_upload.enabled', true);
        $app['config']->set('crud.chunk_upload.middleware', ['web', 'auth']);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password')->default('secret');
            $table->string('role')->default('viewer');
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->nullable();
            $table->string('name');
            $table->string('sku')->unique();
            $table->string('status')->default('active');
            $table->boolean('is_active')->default(true);
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::create('uuid_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
        });

        Schema::create('soft_articles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->nullable()->unique();
            $table->foreignId('category_id')->nullable();
            $table->string('cover_path')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->decimal('price', 10, 2)->default(0);
            $table->text('body')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(SoftArticle::class, SoftArticlePolicy::class);

        Route::middleware(['web', 'auth'])->prefix('dashboard')->group(function () {
            Route::crudResource('products', ProductController::class);
            Route::crudResource('no-export-products', ReadOnlyExportDisabledController::class);
            Route::crudResource('articles', SoftArticleController::class);
        });
    }

    protected function createUser(string $role = 'super_admin', array $overrides = []): User
    {
        return User::create(array_merge([
            'name'     => 'Test ' . ucfirst($role),
            'email'    => $role . '_' . uniqid() . '@example.com',
            'password' => 'secret',
            'role'     => $role,
        ], $overrides));
    }
}
