<?php

namespace NagibMahfuj\Crud\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CrudImportCompleted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public string $importId,
        public string $resource,
        public mixed $userId,
        public array $summary,
    ) {
    }
}

