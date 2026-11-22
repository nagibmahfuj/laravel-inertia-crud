<?php

namespace NagibMahfuj\Crud\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use NagibMahfuj\Crud\Services\ChunkUploadService;

/**
 * Validates `chunked-file` form field payloads (assembled token, UploadedFile,
 * explicit removal marker, or empty value preserving an existing file on edit).
 */
class ChunkedFileRule implements ValidationRule
{
    /**
     * Run this rule even when the field value is null or empty.
     */
    public bool $implicit = true;

    public function __construct(
        protected ChunkUploadService $chunkUploads,
        protected string $label,
        protected bool $required = false,
        protected bool $hasExistingFile = false,
    ) {
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $payload = $this->chunkUploads->normalizePayload($value);

        if ($payload['type'] === 'file') {
            return;
        }

        if ($payload['type'] === 'token') {
            $resolved = $this->chunkUploads->resolveToken($payload['token']);
            if (!$resolved) {
                $fail("The {$this->label} upload is invalid or has expired. Please upload the file again.");
            }
            return;
        }

        if ($payload['type'] === 'removed') {
            if ($this->required) {
                $fail("The {$this->label} field is required.");
            }
            return;
        }

        // Empty payload
        if ($this->required && !$this->hasExistingFile) {
            $fail("The {$this->label} field is required.");
        }
    }
}

