<?php

namespace NagibMahfuj\Crud\Commands;

use Illuminate\Console\Command;
use NagibMahfuj\Crud\Services\ChunkUploadService;

class CrudCleanupChunksCommand extends Command
{
    protected $signature = 'crud:cleanup-chunks
        {--hours= : Override the chunk upload TTL in hours}';

    protected $description = 'Delete expired temporary chunk slices and unclaimed completed uploads';

    public function handle(ChunkUploadService $chunkUploads): int
    {
        $hours = $this->option('hours') !== null ? (int) $this->option('hours') : null;
        $ttl   = $hours ?? (int) config('crud.chunk_upload.ttl_hours', 6);

        $result = $chunkUploads->cleanup($hours);

        $this->components->info(
            "Chunk cleanup complete (TTL: {$ttl}h) — removed {$result['partial_deleted']} partial upload(s) and {$result['completed_deleted']} completed file(s)."
        );

        return self::SUCCESS;
    }
}

