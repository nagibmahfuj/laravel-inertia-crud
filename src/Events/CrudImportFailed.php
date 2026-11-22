<?php

namespace NagibMahfuj\Crud\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrudImportFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $importId,
        public string $resource,
        public mixed $userId,
        public string $errorMessage,
        public array $summary = [],
    ) {
    }
}

