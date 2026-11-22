<?php

namespace NagibMahfuj\Crud\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\ValidationException;
use NagibMahfuj\Crud\Services\OptionsService;

/**
 * Native package controller serving async searchable relationship options
 * using server-signed `options_config` tokens.
 */
class CrudOptionsController extends Controller
{
    public function __construct(protected OptionsService $options)
    {
    }

    public function search(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'options' => $this->options->searchFromRequest($request),
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => collect($e->errors())->flatten()->first() ?? $e->getMessage(),
                'options' => [],
            ], 422);
        }
    }
}

