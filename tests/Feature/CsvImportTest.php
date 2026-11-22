<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use NagibMahfuj\Crud\Events\CrudImportCompleted;
use NagibMahfuj\Crud\Jobs\ProcessCrudImportJob;
use NagibMahfuj\Crud\Services\ChunkUploadService;
use NagibMahfuj\Crud\Services\CsvImportService;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticle;
use NagibMahfuj\Crud\Tests\Fixtures\SoftArticleController;

beforeEach(function () {
    Storage::fake('local');
    SoftArticleController::$importModeOverride     = 'sync';
    SoftArticleController::$queueThresholdOverride = 1024;
    File::deleteDirectory(app(ChunkUploadService::class)->tempBasePath());
});

afterEach(function () {
    SoftArticleController::$importModeOverride     = null;
    SoftArticleController::$queueThresholdOverride = null;
    File::deleteDirectory(app(ChunkUploadService::class)->tempBasePath());
});

it('streams a downloadable sample CSV from GET {uri}/import-sample', function () {
    $user = $this->createUser('super_admin');

    $res = $this->actingAs($user)->get('/dashboard/articles/import-sample');
    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('text/csv');

    $content = $res->streamedContent();
    expect($content)->toContain('Title,Slug,Price')
        ->and($content)->toContain('"Hello World",hello-world,9.99');
});

it('runs synchronous CSV import with row validation, transform closure, progress status, error CSV, and event', function () {
    Event::fake([CrudImportCompleted::class]);

    $userA = $this->createUser('super_admin');
    $userB = $this->createUser('super_admin');

    $csv = implode("\n", [
        'Title,Slug,Price',
        'First Post,FIRST-POST,12.50',
        'Second Post,SECOND-POST,25',
        ',missing-title,10', // Invalid: Title required
        'Bad Price,bad-price,-5', // Invalid: min:0
        'Third Post,THIRD-POST,5.00',
    ]);

    $file = UploadedFile::fake()->createWithContent('articles.csv', $csv);

    $res = $this->actingAs($userA)->postJson('/dashboard/articles/import', [
        'file' => $file,
    ]);

    $res->assertOk()->assertJson([
        'queued' => false,
        'status' => [
            'status'          => 'completed',
            'total_rows'      => 5,
            'processed_rows'  => 5,
            'successful_rows' => 3,
            'failed_rows'     => 2,
            'progress'        => 100,
        ],
    ]);

    // Transform lowercased the slugs
    expect(SoftArticle::where('slug', 'first-post')->exists())->toBeTrue()
        ->and(SoftArticle::where('slug', 'second-post')->exists())->toBeTrue()
        ->and(SoftArticle::where('slug', 'third-post')->exists())->toBeTrue();

    Event::assertDispatched(CrudImportCompleted::class);

    $importId = $res->json('import_id');

    // Status polling endpoint returns state to User A
    $statusRes = $this->actingAs($userA)->getJson("/crud/imports/{$importId}/status");
    $statusRes->assertOk()->assertJson([
        'id'              => $importId,
        'status'          => 'completed',
        'successful_rows' => 3,
        'failed_rows'     => 2,
    ]);

    // Error report CSV download contains failed rows + _import_error column
    $errRes = $this->actingAs($userA)->get("/crud/imports/{$importId}/errors");
    $errRes->assertOk();
    $errCsv = $errRes->streamedContent();
    expect($errCsv)->toContain('_import_error')
        ->and($errCsv)->toContain('missing-title')
        ->and($errCsv)->toContain('bad-price');

    // User B cannot access User A's import status or errors
    $this->actingAs($userB)->getJson("/crud/imports/{$importId}/status")->assertNotFound();
    $this->actingAs($userB)->get("/crud/imports/{$importId}/errors")->assertNotFound();
});

it('dispatches ProcessCrudImportJob in queued mode and executes transform closures cleanly without serialization issues', function () {
    Queue::fake();
    Event::fake([CrudImportCompleted::class]);

    SoftArticleController::$importModeOverride = 'queued';
    $user = $this->createUser('super_admin');

    $csv  = "Title,Slug,Price\nQueued One,QUEUED-ONE,19.99\nQueued Two,QUEUED-TWO,29.99";
    $file = UploadedFile::fake()->createWithContent('big.csv', $csv);

    $res = $this->actingAs($user)->postJson('/dashboard/articles/import', [
        'file' => $file,
    ]);

    $res->assertStatus(202)->assertJson([
        'queued' => true,
        'status' => [
            'status' => 'queued',
        ],
    ]);

    $importId = $res->json('import_id');

    // Verify job was dispatched and can be serialized/unserialized cleanly
    Queue::assertPushed(ProcessCrudImportJob::class, function (ProcessCrudImportJob $job) use ($importId) {
        $roundTripped = unserialize(serialize($job));
        expect($roundTripped->importId)->toBe($importId);

        // Execute worker handle() directly to verify full runtime execution
        $roundTripped->handle(app(CsvImportService::class));
        return true;
    });

    expect(SoftArticle::where('slug', 'queued-one')->exists())->toBeTrue()
        ->and(SoftArticle::where('slug', 'queued-two')->exists())->toBeTrue();

    // Progress updated to 100% completed in Cache
    $this->actingAs($user)
        ->getJson("/crud/imports/{$importId}/status")
        ->assertOk()
        ->assertJson([
            'status'          => 'completed',
            'successful_rows' => 2,
            'progress'        => 100,
        ]);

    Event::assertDispatched(CrudImportCompleted::class);
});

it('auto-switches to queued mode when file exceeds queue_threshold_kb and supports chunked upload tokens', function () {
    Queue::fake();

    SoftArticleController::$importModeOverride     = 'auto';
    SoftArticleController::$queueThresholdOverride = 0; // Force any non-empty file over threshold

    $user = $this->createUser('super_admin');
    $csv  = "Title,Slug,Price\nChunked CSV Row,CHUNKED-ROW,5.50";

    // Upload CSV via native /crud/chunk-upload endpoint first
    $chunkRes = $this->actingAs($user)->post('/crud/chunk-upload', [
        'upload_id'    => 'up_csv_' . uniqid(),
        'chunk_index'  => 0,
        'total_chunks' => 1,
        'file_name'    => 'huge.csv',
        'file_size'    => strlen($csv),
        'chunk'        => UploadedFile::fake()->createWithContent('slice.bin', $csv),
    ]);
    $chunkRes->assertOk();
    $token = $chunkRes->json('token');

    // Submit chunk_token to import endpoint -> auto-queued!
    $importRes = $this->actingAs($user)->postJson('/dashboard/articles/import', [
        'chunk_token' => $token,
    ]);

    $importRes->assertStatus(202)->assertJson(['queued' => true]);
    Queue::assertPushed(ProcessCrudImportJob::class);
});

