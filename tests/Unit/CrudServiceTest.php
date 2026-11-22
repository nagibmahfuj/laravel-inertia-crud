<?php

use Illuminate\Http\Request;
use NagibMahfuj\Crud\CrudService;
use NagibMahfuj\Crud\Tests\Fixtures\Category;
use NagibMahfuj\Crud\Tests\Fixtures\Product;
use NagibMahfuj\Crud\Tests\Fixtures\UuidItem;

it('builds search, text, boolean, multi-select, relation, and date_range filters cleanly', function () {
    $catA = Category::create(['name' => 'Beverages']);
    $catB = Category::create(['name' => 'Snacks']);

    Product::create(['category_id' => $catA->id, 'name' => 'Cola Zero', 'sku' => 'SKU-1', 'status' => 'active', 'is_active' => true]);
    Product::create(['category_id' => $catB->id, 'name' => 'Chips', 'sku' => 'SKU-2', 'status' => 'draft', 'is_active' => false]);
    Product::create(['category_id' => $catA->id, 'name' => 'Sprite', 'sku' => 'SKU-3', 'status' => 'active', 'is_active' => true]);

    $service = app(CrudService::class);
    $config  = [
        'searchable' => ['name', 'category.name'],
        'columns'    => [
            ['key' => 'name', 'label' => 'Name', 'sortable' => true],
        ],
        'filters' => [
            ['key' => 'status', 'type' => 'select'],
            ['key' => 'is_active', 'type' => 'boolean'],
            ['key' => 'sku', 'type' => 'text'],
            ['key' => 'cat_name', 'type' => 'text', 'relation' => 'category', 'column' => 'name'],
        ],
    ];

    // Relationship search
    $req = Request::create('/dashboard/products', 'GET', ['search' => 'Beverages']);
    $results = $service->buildIndexQuery(Product::query(), $req, $config)->get();
    expect($results)->toHaveCount(2);

    // Multi-select + text + boolean filter
    $req2 = Request::create('/dashboard/products', 'GET', [
        'filter_status'    => 'active--draft',
        'filter_is_active' => 'true',
        'filter_sku'       => 'SKU-1',
    ]);
    $results2 = $service->buildIndexQuery(Product::query(), $req2, $config)->get();
    expect($results2)->toHaveCount(1)
        ->and($results2->first()->name)->toBe('Cola Zero');

    // Text filter via relation
    $req3 = Request::create('/dashboard/products', 'GET', ['filter_cat_name' => 'Snack']);
    $results3 = $service->buildIndexQuery(Product::query(), $req3, $config)->get();
    expect($results3)->toHaveCount(1)
        ->and($results3->first()->name)->toBe('Chips');
});

it('safely clamps per_page, whitelists sort direction, and supports non-timestamped models', function () {
    UuidItem::create(['id' => 'b-uuid', 'title' => 'Beta']);
    UuidItem::create(['id' => 'a-uuid', 'title' => 'Alpha']);

    $service = app(CrudService::class);

    // Non-timestamped model with invalid sort direction & non-sortable sort key should not crash
    $req = Request::create('/', 'GET', [
        'sort'      => 'non_existent; DROP TABLE users;--',
        'direction' => 'INVALID_DIR',
        'per_page'  => '999999',
    ]);

    $query = $service->buildIndexQuery(UuidItem::query(), $req, ['columns' => []]);
    $paginated = $service->paginate($query, $req, 10);

    expect($paginated->perPage())->toBe(100)
        ->and($paginated->items())->toHaveCount(2)
        ->and($paginated->items()[0]->id)->toBe('b-uuid'); // desc fallback on primary key `id`
});

it('builds normalized meta with policy-filtered actions, routes, and breadcrumbs', function () {
    $viewer = $this->createUser('viewer');
    $this->actingAs($viewer);

    $service = app(CrudService::class);
    $config  = [
        'model'             => Product::class,
        'resource_name'     => 'Product',
        'title'             => 'Products Catalog',
        'description'       => 'Manage items',
        'route_prefix'      => 'products',
        'actions'           => ['create', 'edit', 'delete', 'show', 'export', 'import'],
        'import_format_url' => '/samples/products.csv',
        'columns'           => [
            ['key' => 'name', 'label' => 'Name', 'display_format' => fn ($r) => $r->name],
        ],
    ];

    $meta = $service->buildMeta($config, 'index');

    // Viewer can only show & export (create/edit/delete/import filtered out by policy)
    expect($meta['actions'])->toEqual(['show', 'export'])
        ->and($meta['title'])->toBe('Products Catalog')
        ->and($meta['description'])->toBe('Manage items')
        ->and($meta['base_route'])->toBe('/dashboard/products')
        ->and($meta['routes']['index'])->toBe('/dashboard/products')
        ->and($meta['routes']['export'])->toBe('/dashboard/products/export')
        ->and($meta['routes']['import'])->toBeNull()
        ->and($meta['routes']['create'])->toBeNull()
        ->and($meta['routes']['chunk_upload'])->toBe('/crud/chunk-upload')
        ->and($meta['import_route'])->toBeNull()
        ->and($meta['import_format_url'])->toBe('/samples/products.csv')
        ->and($meta['breadcrumbs'])->toEqual([
            ['title' => 'Dashboard', 'href' => '/dashboard'],
            ['title' => 'Products Catalog', 'href' => '/dashboard/products'],
        ])
        ->and($meta['columns'][0])->not->toHaveKey('display_format');
});

it('builds context-aware breadcrumbs and routes for create and edit forms', function () {
    $superAdmin = $this->createUser('super_admin');
    $this->actingAs($superAdmin);

    $product = Product::create(['name' => 'Cola', 'sku' => 'SKU-1']);
    $service = app(CrudService::class);

    $config = [
        'model'         => Product::class,
        'resource_name' => 'Product',
        'route_prefix'  => 'products',
        'actions'       => ['create', 'edit', 'delete', 'export', 'import'],
    ];

    $createMeta = $service->buildMeta($config, 'create');
    expect($createMeta['title'])->toBe('Create Product')
        ->and($createMeta['breadcrumbs'])->toHaveCount(3)
        ->and($createMeta['breadcrumbs'][2]['title'])->toBe('Create');

    $editMeta = $service->buildMeta($config, 'edit', $product);
    expect($editMeta['title'])->toBe('Edit Product')
        ->and($editMeta['routes']['update'])->toBe("/dashboard/products/{$product->id}")
        ->and($editMeta['breadcrumbs'][2]['title'])->toBe('Edit');
});

it('strips empty validation rule segments cleanly', function () {
    $service = app(CrudService::class);
    $rules = $service->buildValidationRules([
        ['key' => 'notes', 'type' => 'text', 'rules' => ''],
        ['key' => 'name', 'type' => 'text', 'rules' => 'required|string'],
    ]);

    expect($rules['notes'])->toBe([])
        ->and($rules['name'])->toBe(['required', 'string']);
});

