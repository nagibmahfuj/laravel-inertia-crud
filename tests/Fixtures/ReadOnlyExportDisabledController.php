<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use NagibMahfuj\Crud\CrudController;

class ReadOnlyExportDisabledController extends CrudController
{
    protected function resourceConfig(): array
    {
        return [
            'model'         => Product::class,
            'resource_name' => 'Product',
            'route_prefix'  => 'no-export-products',
            'page_prefix'   => 'Dashboard/Products',
            'actions'       => ['show'], // No export, no import
            'columns'       => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true],
                ['key' => 'name', 'label' => 'Name', 'sortable' => true],
            ],
            'fields' => [],
        ];
    }
}

