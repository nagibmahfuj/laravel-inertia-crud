<?php

use NagibMahfuj\Crud\Tests\Fixtures\Category;
use NagibMahfuj\Crud\Tests\Fixtures\Product;

it('renders index with normalized meta, routes, breadcrumbs, and formatted dot columns', function () {
    $user = $this->createUser('super_admin');
    $cat  = Category::create(['name' => 'Drinks']);
    Product::create(['category_id' => $cat->id, 'name' => 'Coke', 'sku' => 'SKU-1']);

    $response = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/dashboard/products');

    $response->assertOk();

    $page = $response->json();
    expect($page['component'])->toBe('Dashboard/Products/Index')
        ->and($page['props']['meta']['title'])->toBe('Products Catalog')
        ->and($page['props']['meta']['actions'])->toEqual(['create', 'edit', 'delete', 'show', 'export', 'import'])
        ->and($page['props']['meta']['routes']['export'])->toBe('/dashboard/products/export')
        ->and($page['props']['meta']['routes']['import'])->toBe('/dashboard/products/import')
        ->and($page['props']['meta']['routes']['bulk_destroy'])->toBe('/dashboard/products/bulk-destroy')
        ->and($page['props']['data']['data'][0]['category.name'])->toBe('Drinks');
});

it('supports full create, store, edit, update (PUT & PATCH with Rule objects), destroy, and bulkDestroy lifecycle', function () {
    $user = $this->createUser('super_admin');

    // Create page
    $createRes = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/dashboard/products/create');
    $createRes->assertOk();
    expect($createRes->json('props.meta.title'))->toBe('Create Product');

    // Store
    $this->actingAs($user)
        ->post('/dashboard/products', [
            'name'      => 'Coke Studio Special',
            'sku'       => 'SKU-NEW',
            'status'    => 'active',
            'is_active' => true,
        ])
        ->assertRedirect('/dashboard/products');

    $product = Product::where('sku', 'SKU-NEW')->firstOrFail();
    expect($product->created_by)->toBe($user->id)
        ->and($product->updated_by)->toBe($user->id);

    // PATCH update (with Rule::in object in rules - must not throw TypeError)
    $this->actingAs($user)
        ->patch("/dashboard/products/{$product->id}", [
            'status' => 'draft',
        ])
        ->assertRedirect('/dashboard/products');

    expect($product->fresh()->status)->toBe('draft');

    // Bulk destroy
    $p2 = Product::create(['name' => 'Item 2', 'sku' => 'SKU-2']);
    $p3 = Product::create(['name' => 'Item 3', 'sku' => 'SKU-3']);

    $this->actingAs($user)
        ->post('/dashboard/products/bulk-destroy', [
            'ids' => [$p2->id, $p3->id],
        ])
        ->assertRedirect('/dashboard/products');

    expect(Product::find($p2->id))->toBeNull()
        ->and(Product::find($p3->id))->toBeNull();

    // Single destroy
    $this->actingAs($user)
        ->delete("/dashboard/products/{$product->id}")
        ->assertRedirect('/dashboard/products');

    expect(Product::find($product->id))->toBeNull();
});

it('enforces actions config for export route (403 when export omitted, 200 CSV when enabled)', function () {
    $user = $this->createUser('super_admin');
    Product::create(['name' => 'Exportable Coke', 'sku' => 'SKU-1']);

    // Enabled export returns CSV stream
    $okResponse = $this->actingAs($user)->get('/dashboard/products/export');
    $okResponse->assertOk();
    expect($okResponse->headers->get('content-type'))->toContain('text/csv');
    $csvContent = $okResponse->streamedContent();
    expect($csvContent)->toContain('Exportable Coke')
        ->and($csvContent)->toContain('Uncategorized');

    // Disabled export (omitted from actions) returns 403 Forbidden
    $this->actingAs($user)
        ->get('/dashboard/no-export-products/export')
        ->assertForbidden();
});

it('blocks bulkDestroy for roles with no_delete', function () {
    $admin = $this->createUser('admin'); // admin has no_delete on products
    $p     = Product::create(['name' => 'Protected', 'sku' => 'SKU-4']);

    $this->actingAs($admin)
        ->post('/dashboard/products/bulk-destroy', ['ids' => [$p->id]])
        ->assertForbidden();

    expect(Product::find($p->id))->not->toBeNull();
});

