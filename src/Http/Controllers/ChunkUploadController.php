<?php

namespace NagibMahfuj\Crud\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use NagibMahfuj\Crud\Services\ChunkUploadService;

/**
 * Native package controller handling chunked file upload slices and cancellation.
 * Registered automatically by CrudServiceProvider when config('crud.chunk_upload.enabled') is true.
 */
class ChunkUploadController extends Controller
{
    public function __construct(protected ChunkUploadService $chunkUploads)
    {
    }

    /**
     * Receive a single file chunk and reassemble once all chunks arrive.
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $result = $this->chunkUploads->handleChunk($request);

            return response()->json($result);
        } catch (ValidationException $e) {
            $errors = $e->errors();
            $firstMessage = collect($errors)->flatten()->first() ?? $e->getMessage();

            return response()->json([
                'success' => false,
                'message' => $firstMessage,
                'errors'  => $errors,
            ], 422);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => config('app.debug') ? $e->getMessage() : 'Unable to process uploaded chunk.',
            ], 500);
        }
    }

    /**
     * Cancel an in-progress upload and delete its partial chunk files.
     */
    public function cancel(Request $request, string $uploadId): JsonResponse
    {
        $deleted = $this->chunkUploads->cancelUpload($uploadId);

        return response()->json([
            'success' => true,
            'deleted' => $deleted,
        ]);
    }
}

