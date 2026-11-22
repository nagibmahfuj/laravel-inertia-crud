<?php

namespace NagibMahfuj\Crud\Facades;

use Illuminate\Support\Facades\Facade;
use NagibMahfuj\Crud\CrudService;

/**
 * @method static \Illuminate\Database\Eloquent\Builder buildIndexQuery(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request, array $config)
 * @method static \Illuminate\Contracts\Pagination\LengthAwarePaginator paginate(\Illuminate\Database\Eloquent\Builder $query, \Illuminate\Http\Request $request, int $defaultPerPage = 10)
 * @method static array buildValidationRules(array $fields, ?\Illuminate\Database\Eloquent\Model $model = null, string $action = 'create', string $prefix = '')
 * @method static \Illuminate\Database\Eloquent\Model fillModel(\Illuminate\Database\Eloquent\Model $model, array $data, array $fields)
 * @method static void processChunkedFiles(\Illuminate\Database\Eloquent\Model $model, \Illuminate\Http\Request $request, array $data, array $fields)
 * @method static void deleteChunkedFiles(\Illuminate\Database\Eloquent\Model $model, array $fields)
 * @method static array prepareFieldsForFrontend(array $fields, string $mode = 'create', ?\Illuminate\Database\Eloquent\Model $record = null)
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse exportCsv(\Illuminate\Database\Eloquent\Builder $query, array $columns, string $filename)
 * @method static array buildMeta(array $config, string $context = 'index', ?\Illuminate\Database\Eloquent\Model $record = null)
 *
 * @see \NagibMahfuj\Crud\CrudService
 */
class Crud extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return CrudService::class;
    }
}
