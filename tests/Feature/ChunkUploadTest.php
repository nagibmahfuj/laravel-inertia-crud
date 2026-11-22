<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use NagibMahfuj\Crud\Services\ChunkUploadService;
use NagibMahfuj\Crud\Tests\Fixtures\Product;

beforeEach(function () {
    Storage::fake('public');
    File::deleteDirectory(app(ChunkUploadService::class)->tempBasePath());
});

afterEach(function () {
    File::deleteDirectory(app(ChunkUploadService::class)->tempBasePath());
});

it('registers native chunk upload route and reassembles multi-chunk uploads atomically', function () {
    $user = $this->createUser('super_admin');

    // Fetch create form to obtain signed upload_config for the chunked-file field
    $createRes = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get('/dashboard/products/create');
    $createRes->assertOk();

    $fields = collect($createRes->json('props.fields'));
    $assetField = $fields->firstWhere('key', 'asset_file');

    expect($assetField)->not->toBeNull()
        ->and($assetField['endpoint'])->toBe('/crud/chunk-upload')
        ->and($assetField['chunk_size_mb'])->toBe(2)
        ->and($assetField['max_size_mb'])->toBe(10)
        ->and($assetField['upload_config'])->toBeString()
        ->and($assetField)->not->toHaveKey('directory'); // Closure stripped!

    $chunk1 = str_repeat('A', 1024);
    $chunk2 = str_repeat('B', 512);
    $totalSize = strlen($chunk1) + strlen($chunk2);
    $uploadId = 'up_test_' . uniqid();

    // Upload chunk 0
    $res1 = $this->actingAs($user)->post('/crud/chunk-upload', [
        'upload_id'     => $uploadId,
        'chunk_index'   => 0,
        'total_chunks'  => 2,
        'file_name'     => 'manual.txt',
        'file_size'     => $totalSize,
        'upload_config' => $assetField['upload_config'],
        'chunk'         => UploadedFile::fake()->createWithContent('slice0.bin', $chunk1),
    ]);

    $res1->assertOk()->assertJson([
        'success'      => true,
        'completed'    => false,
        'chunk_index'  => 0,
        'total_chunks' => 2,
    ]);

    // Upload chunk 1 (final slice -> triggers assembly)
    $res2 = $this->actingAs($user)->post('/crud/chunk-upload', [
        'upload_id'     => $uploadId,
        'chunk_index'   => 1,
        'total_chunks'  => 2,
        'file_name'     => 'manual.txt',
        'file_size'     => $totalSize,
        'upload_config' => $assetField['upload_config'],
        'chunk'         => UploadedFile::fake()->createWithContent('slice1.bin', $chunk2),
    ]);

    $res2->assertOk()->assertJson([
        'success'   => true,
        'completed' => true,
        'name'      => 'manual.txt',
        'size'      => $totalSize,
    ]);

    $token = $res2->json('token');
    expect($token)->toBeString();

    // Submit CRUD store form with the chunk token — CrudController persists file to disk automatically!
    $this->actingAs($user)
        ->post('/dashboard/products', [
            'name'       => 'Chunked Product',
            'sku'        => 'SKU-1',
            'asset_file' => [
                'token' => $token,
                'name'  => 'manual.txt',
                'size'  => $totalSize,
            ],
        ])
        ->assertRedirect('/dashboard/products');

    $product = Product::where('sku', 'SKU-1')->firstOrFail();

    expect($product->file_path)->toBe('assets/SKU-1/manual.txt')
        ->and($product->file_name)->toBe('manual.txt')
        ->and($product->file_size)->toBe($totalSize);

    Storage::disk('public')->assertExists('assets/SKU-1/manual.txt');
    expect(Storage::disk('public')->get('assets/SKU-1/manual.txt'))->toBe($chunk1 . $chunk2);

    // Edit page returns server-resolved existing_file metadata!
    $editRes = $this->actingAs($user)
        ->withHeader('X-Inertia', 'true')
        ->get("/dashboard/products/{$product->id}/edit");
    $editRes->assertOk();

    $editAssetField = collect($editRes->json('props.fields'))->firstWhere('key', 'asset_file');
    expect($editAssetField['existing_file'])->toMatchArray([
        'path' => 'assets/SKU-1/manual.txt',
        'name' => 'manual.txt',
        'size' => $totalSize,
    ]);

    // Removing file via { removed: true } deletes from disk and clears mapped columns
    $this->actingAs($user)
        ->put("/dashboard/products/{$product->id}", [
            'name'       => 'Chunked Product',
            'sku'        => 'SKU-1',
            'asset_file' => ['removed' => true],
        ])
        ->assertRedirect('/dashboard/products');

    $product->refresh();
    expect($product->file_path)->toBeNull()
        ->and($product->file_name)->toBeNull()
        ->and($product->file_size)->toBeNull();
    Storage::disk('public')->assertMissing('assets/SKU-1/manual.txt');
});

it('rejects disallowed extensions and enforces user ownership of upload tokens', function () {
    $userA = $this->createUser('super_admin');
    $userB = $this->createUser('super_admin');

    $service = app(ChunkUploadService::class);
    $signedConfig = $service->signFieldConfig(['accept' => '.txt,.zip', 'max_size_mb' => 5], $userA->id);

    // Disallowed extension (.exe)
    $this->actingAs($userA)->post('/crud/chunk-upload', [
        'upload_id'     => 'up_bad_ext',
        'chunk_index'   => 0,
        'total_chunks'  => 1,
        'file_name'     => 'malware.exe',
        'file_size'     => 100,
        'upload_config' => $signedConfig,
        'chunk'         => UploadedFile::fake()->createWithContent('slice.bin', str_repeat('X', 100)),
    ])->assertStatus(422);

    // Valid upload by User A
    $ok = $this->actingAs($userA)->post('/crud/chunk-upload', [
        'upload_id'     => 'up_owner_test',
        'chunk_index'   => 0,
        'total_chunks'  => 1,
        'file_name'     => 'notes.txt',
        'file_size'     => 10,
        'upload_config' => $signedConfig,
        'chunk'         => UploadedFile::fake()->createWithContent('slice.bin', '0123456789'),
    ]);
    $ok->assertOk();
    $token = $ok->json('token');

    // User B cannot resolve or claim User A's token
    $this->actingAs($userB);
    expect($service->resolveToken($token))->toBeNull();

    // Submitting User A's token as User B fails validation cleanly
    $this->actingAs($userB)
        ->post('/dashboard/products', [
            'name'       => 'Hijack Attempt',
            'sku'        => 'SKU-2',
            'asset_file' => ['token' => $token, 'name' => 'notes.txt', 'size' => 10],
        ])
        ->assertSessionHasErrors('asset_file');
});

it('deletes partial chunks on cancel endpoint and prunes stale files via crud:cleanup-chunks', function () {
    $user = $this->createUser('super_admin');
    $service = app(ChunkUploadService::class);

    // Upload only chunk 0 of 2
    $this->actingAs($user)->post('/crud/chunk-upload', [
        'upload_id'    => 'up_cancel_me',
        'chunk_index'  => 0,
        'total_chunks' => 2,
        'file_name'    => 'big.zip',
        'file_size'    => 200,
        'chunk'        => UploadedFile::fake()->createWithContent('slice.bin', str_repeat('Z', 100)),
    ])->assertOk();

    expect(File::isDirectory($service->uploadsPath() . '/up_cancel_me'))->toBeTrue();

    // Cancel deletes partial directory
    $this->actingAs($user)
        ->delete('/crud/chunk-upload/up_cancel_me')
        ->assertOk()
        ->assertJson(['success' => true, 'deleted' => true]);

    expect(File::isDirectory($service->uploadsPath() . '/up_cancel_me'))->toBeFalse();

    // Create a stale completed file older than 2 hours
    File::ensureDirectoryExists($service->completedPath());
    $staleFile = $service->completedPath() . '/old_upload.txt';
    File::put($staleFile, 'old');
    touch($staleFile, time() - 7200);

    $this->artisan('crud:cleanup-chunks', ['--hours' => 1])
        ->assertSuccessful()
        ->expectsOutputToContain('removed 0 partial upload(s) and 1 completed file(s)');

    expect(file_exists($staleFile))->toBeFalse();
});

