<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use NagibMahfuj\Crud\Tests\Fixtures\Category;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticle;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticleController;

beforeEach(function () {
    Storage::fake('public');
    SoftArticleController::$lazyOptionsCalls = 0;
});

it('returns stats, soft_deletes, form_mode, and lazily evaluated filter options on index', function () {
    $user = $this->createUser('super_admin');
    SoftArticle::create(['title' => 'First', 'slug' => 'first', 'is_featured' => true]);
    SoftArticle::create(['title' => 'Second', 'slug' => 'second', 'is_featured' => false]);

    // Store does NOT trigger lazy filter options closure!
    $this->actingAs($user)
        ->post('/dashboard/articles', ['title' => 'Third', 'slug' => 'third'])
        ->assertRedirect('/dashboard/articles');
    expect(SoftArticleController::$lazyOptionsCalls)->toBe(0);

    // Index evaluates lazy options closure once and returns KPI stats + metadata
    $res = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/dashboard/articles');
    $res->assertOk();

    expect(SoftArticleController::$lazyOptionsCalls)->toBe(1)
        ->and($res->json('props.stats'))->toHaveCount(2)
        ->and($res->json('props.stats.0.value'))->toBe(3)
        ->and($res->json('props.stats.1.value'))->toBe(1)
        ->and($res->json('props.meta.soft_deletes'))->toBeTrue()
        ->and($res->json('props.meta.form_mode'))->toBe('modal')
        ->and($res->json('props.meta.form_columns'))->toBe(2)
        ->and($res->json('props.meta.custom_actions'))->toHaveCount(2);
});

it('returns prepared showFields on show() with display_format applied', function () {
    $user    = $this->createUser('super_admin');
    $article = SoftArticle::create([
        'title' => 'Show Test',
        'slug'  => 'show-test',
        'body'  => 'hello world',
    ]);

    $res = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get("/dashboard/articles/{$article->id}");
    $res->assertOk();

    $showFields = collect($res->json('props.showFields'));
    expect($res->json('component'))->toBe('Dashboard/Articles/Show')
        ->and($showFields->firstWhere('key', 'body')['value'])->toBe('HELLO WORLD')
        ->and($showFields->firstWhere('key', 'title')['section'])->toBe('Overview');
});

it('auto-handles standard image/file upload, edit existing_file, removal, and forceDelete cleanup', function () {
    $user = $this->createUser('super_admin');

    // Create with uploaded image (no beforeSave needed!)
    $img = UploadedFile::fake()->image('hero.jpg', 100, 100);
    $this->actingAs($user)
        ->post('/dashboard/articles', [
            'title' => 'Image Post',
            'slug'  => 'image-post',
            'cover' => $img,
        ])
        ->assertRedirect('/dashboard/articles');

    $article = SoftArticle::where('slug', 'image-post')->firstOrFail();
    expect($article->cover_path)->toBe('articles/covers/hero.jpg');
    Storage::disk('public')->assertExists('articles/covers/hero.jpg');

    // Embedded modal edit request (X-Crud-Form: 1) returns JSON with existing_file & field metadata
    $editJson = $this->actingAs($user)
        ->withHeader('X-Crud-Form', '1')
        ->getJson("/dashboard/articles/{$article->id}/edit");
    $editJson->assertOk();

    $fields     = collect($editJson->json('fields'));
    $coverField = $fields->firstWhere('key', 'cover');
    $bodyField  = $fields->firstWhere('key', 'body');

    expect($coverField['existing_file']['path'])->toBe('articles/covers/hero.jpg')
        ->and($coverField['accept'])->toBe('image/*')
        ->and($bodyField['visible_when'])->toEqual(['field' => 'is_featured', 'truthy' => true]);

    // Soft-deleting preserves the file on disk (so restore works!)
    $this->actingAs($user)
        ->delete("/dashboard/articles/{$article->id}")
        ->assertRedirect('/dashboard/articles');

    expect(SoftArticle::find($article->id))->toBeNull()
        ->and(SoftArticle::withTrashed()->find($article->id))->not->toBeNull();
    Storage::disk('public')->assertExists('articles/covers/hero.jpg');

    // Restore brings it back
    $this->actingAs($user)
        ->post("/dashboard/articles/{$article->id}/restore")
        ->assertRedirect('/dashboard/articles');
    expect(SoftArticle::find($article->id))->not->toBeNull();

    // Explicit removal via `{ removed: true }` deletes file and nulls cover_path
    $this->actingAs($user)
        ->put("/dashboard/articles/{$article->id}", [
            'title' => 'Image Post Updated',
            'slug'  => 'image-post',
            'cover' => ['removed' => true],
        ])
        ->assertRedirect('/dashboard/articles');

    expect($article->fresh()->cover_path)->toBeNull();
    Storage::disk('public')->assertMissing('articles/covers/hero.jpg');

    // Re-attach image then forceDelete — file MUST be deleted on forceDelete!
    $img2 = UploadedFile::fake()->image('hero2.jpg', 100, 100);
    $this->actingAs($user)
        ->post("/dashboard/articles/{$article->id}", [
            '_method' => 'put',
            'title'   => 'Image Post Final',
            'slug'    => 'image-post',
            'cover'   => $img2,
        ])
        ->assertRedirect('/dashboard/articles');
    Storage::disk('public')->assertExists('articles/covers/hero2.jpg');

    $this->actingAs($user)
        ->delete("/dashboard/articles/{$article->id}/force")
        ->assertRedirect('/dashboard/articles');

    expect(SoftArticle::withTrashed()->find($article->id))->toBeNull();
    Storage::disk('public')->assertMissing('articles/covers/hero2.jpg');
});

it('supports SoftDeletes trashed filter, bulkRestore, and bulkForceDestroy', function () {
    $user = $this->createUser('super_admin');
    $a1   = SoftArticle::create(['title' => 'Alive', 'slug' => 'alive']);
    $a2   = SoftArticle::create(['title' => 'Deleted 1', 'slug' => 'deleted-1']);
    $a3   = SoftArticle::create(['title' => 'Deleted 2', 'slug' => 'deleted-2']);
    $a2->delete();
    $a3->delete();

    // Only trashed filter
    $onlyTrashed = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/dashboard/articles?trashed=only');
    $onlyTrashed->assertOk();
    expect($onlyTrashed->json('props.data.data'))->toHaveCount(2);

    // Bulk restore
    $this->actingAs($user)
        ->post('/dashboard/articles/bulk-restore', ['ids' => [$a2->id, $a3->id]])
        ->assertRedirect('/dashboard/articles');
    expect(SoftArticle::count())->toBe(3);

    // Bulk force destroy
    $this->actingAs($user)
        ->post('/dashboard/articles/bulk-force-destroy', ['ids' => [$a2->id, $a3->id]])
        ->assertRedirect('/dashboard/articles');
    expect(SoftArticle::withTrashed()->count())->toBe(1)
        ->and(SoftArticle::find($a1->id))->not->toBeNull();
});

it('serves signed async relationship options via native /crud/options and enforces ownership', function () {
    $userA = $this->createUser('super_admin');
    $userB = $this->createUser('super_admin');

    Category::create(['name' => 'Technology']);
    Category::create(['name' => 'Music']);
    Category::create(['name' => 'Tech Gadgets']);

    // Get signed options_config from create form as User A
    $createJson = $this->actingAs($userA)
        ->withHeader('X-Crud-Form', '1')
        ->getJson('/dashboard/articles/create');
    $createJson->assertOk();

    $catField = collect($createJson->json('fields'))->firstWhere('key', 'category_id');
    expect($catField['options_endpoint'])->toBe('/crud/options')
        ->and($catField['options_config'])->toBeString();

    // Search via native /crud/options endpoint
    $searchRes = $this->actingAs($userA)->getJson('/crud/options?' . http_build_query([
        'config' => $catField['options_config'],
        'search' => 'Tech',
    ]));
    $searchRes->assertOk();
    expect($searchRes->json('options'))->toHaveCount(2);

    // User B cannot reuse User A's signed options_config token
    $this->actingAs($userB)->getJson('/crud/options?' . http_build_query([
        'config' => $catField['options_config'],
        'search' => 'Tech',
    ]))->assertStatus(422);
});

it('executes declarative row and bulk custom_actions and enforces permissions', function () {
    $admin  = $this->createUser('super_admin');
    $viewer = $this->createUser('viewer');

    $a1 = SoftArticle::create(['title' => 'One', 'slug' => 'one', 'is_featured' => false]);
    $a2 = SoftArticle::create(['title' => 'Two', 'slug' => 'two', 'is_featured' => false]);
    $a3 = SoftArticle::create(['title' => 'Three', 'slug' => 'three', 'is_featured' => false]);

    // Viewer does not have 'update' permission -> 403
    $this->actingAs($viewer)
        ->post('/dashboard/articles/action/feature_one', ['id' => $a1->id])
        ->assertForbidden();

    // Super admin runs row action
    $this->actingAs($admin)
        ->post('/dashboard/articles/action/feature_one', ['id' => $a1->id])
        ->assertRedirect();
    expect($a1->fresh()->is_featured)->toBeTrue();

    // Super admin runs bulk action
    $this->actingAs($admin)
        ->post('/dashboard/articles/action/feature_many', ['ids' => [$a2->id, $a3->id]])
        ->assertRedirect();
    expect($a2->fresh()->is_featured)->toBeTrue()
        ->and($a3->fresh()->is_featured)->toBeTrue();
});

