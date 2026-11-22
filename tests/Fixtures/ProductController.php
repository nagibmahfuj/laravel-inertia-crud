<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use NagibMahfuj\Crud\CrudController;

class ProductController extends CrudController
{
    protected function resourceConfig(): array
    {
        return [
            'model'             => Product::class,
            'resource_name'     => 'Product',
            'title'             => 'Products Catalog',
            'description'       => 'All store products',
            'route_prefix'      => 'products',
            'page_prefix'       => 'Dashboard/Products',
            'per_page'          => 10,
            'default_sort'      => 'id',
            'default_direction' => 'desc',
            'searchable'        => ['name', 'sku', 'category.name'],
            'with'              => ['category'],
            'row_selection'     => true,
            'actions'           => ['create', 'edit', 'delete', 'show', 'export', 'import'],
            'import_format_url' => '/samples/products.csv',
            'columns'           => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'type' => 'number'],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true, 'type' => 'text'],
                ['key' => 'sku', 'label' => 'SKU', 'sortable' => true, 'type' => 'text'],
                ['key' => 'status', 'label' => 'Status', 'sortable' => true, 'type' => 'status'],
                ['key' => 'is_active', 'label' => 'Active', 'sortable' => true, 'type' => 'boolean'],
                [
                    'key'            => 'category.name',
                    'label'          => 'Category',
                    'sortable'       => false,
                    'display_format' => fn ($r) => $r->category?->name ?? 'Uncategorized',
                    'export_format'  => fn ($r) => $r->category?->name ?? 'Uncategorized',
                ],
            ],
            'filters' => [
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'multiple' => true, 'options' => [
                    ['label' => 'Active', 'value' => 'active'],
                    ['label' => 'Draft', 'value' => 'draft'],
                ]],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'boolean'],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text'],
                ['key' => 'created_at', 'label' => 'Created', 'type' => 'date_range'],
            ],
            'fields' => [
                ['key' => 'name', 'label' => 'Name', 'type' => 'text', 'rules' => 'required|string|max:255', 'required' => true],
                ['key' => 'sku', 'label' => 'SKU', 'type' => 'text', 'rules' => ['required', 'string', Rule::in(['SKU-1', 'SKU-2', 'SKU-3', 'SKU-4', 'SKU-5', 'SKU-NEW', 'SKU-UPD'])], 'required' => true],
                ['key' => 'status', 'label' => 'Status', 'type' => 'select', 'rules' => 'nullable|string'],
                ['key' => 'is_active', 'label' => 'Active', 'type' => 'checkbox', 'rules' => 'nullable|boolean', 'default' => true],
                [
                    'key'               => 'asset_file',
                    'label'             => 'Product Asset',
                    'type'              => 'chunked-file',
                    'rules'             => 'nullable',
                    'accept'            => '.txt,.bin,.zip',
                    'chunk_size_mb'     => 2,
                    'max_size_mb'       => 10,
                    'disk'              => 'public',
                    'directory'         => fn ($model, $data) => 'assets/' . ($data['sku'] ?? $model->sku ?? 'misc'),
                    'preserve_filename' => true,
                    'delete_old'        => true,
                    'path_column'       => 'file_path',
                    'name_column'       => 'file_name',
                    'size_column'       => 'file_size',
                ],
            ],
        ];
    }

    public function import(Request $request)
    {
        $this->authorize('import', Product::class);

        return redirect()->route('products.index')->with('success', 'Imported.');
    }
}

