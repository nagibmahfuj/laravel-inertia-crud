<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use Illuminate\Http\Request;
use NagibMahfuj\Crud\CrudController;

class SoftArticleController extends CrudController
{
    public static int $lazyOptionsCalls = 0;

    public static ?string $importModeOverride = null;

    public static ?int $queueThresholdOverride = null;

    protected function resourceConfig(): array
    {
        return [
            'model'         => SoftArticle::class,
            'resource_name' => 'Article',
            'route_prefix'  => 'articles',
            'page_prefix'   => 'Dashboard/Articles',
            'form_mode'     => 'modal',
            'form_columns'  => 2,
            'row_selection' => true,
            'actions'       => ['create', 'edit', 'delete', 'show', 'export', 'import'],
            'stats'         => fn ($query) => [
                ['title' => 'Total Articles', 'value' => (clone $query)->count(), 'icon' => 'FileText'],
                ['title' => 'Featured', 'value' => (clone $query)->where('is_featured', true)->count(), 'icon' => 'Star'],
            ],
            'columns' => [
                ['key' => 'id', 'label' => 'ID', 'sortable' => true, 'type' => 'number'],
                ['key' => 'title', 'label' => 'Title', 'sortable' => true, 'type' => 'text'],
                ['key' => 'slug', 'label' => 'Slug', 'sortable' => true, 'type' => 'copyable'],
                ['key' => 'is_featured', 'label' => 'Featured', 'sortable' => true, 'type' => 'toggle'],
                ['key' => 'price', 'label' => 'Price', 'sortable' => true, 'type' => 'currency', 'currency' => 'USD', 'default_hidden' => true],
            ],
            'show_fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'section' => 'Overview'],
                ['key' => 'slug', 'label' => 'Slug', 'type' => 'copyable', 'section' => 'Overview'],
                ['key' => 'cover_path', 'label' => 'Cover', 'type' => 'image', 'section' => 'Media'],
                ['key' => 'body', 'label' => 'Content', 'type' => 'rich-text', 'section' => 'Content', 'col_span' => 2, 'display_format' => fn ($r) => strtoupper((string) $r->body)],
            ],
            'filters' => [
                [
                    'key'     => 'featured_filter',
                    'label'   => 'Featured',
                    'type'    => 'select',
                    'options' => function () {
                        self::$lazyOptionsCalls++;
                        return [
                            ['label' => 'Yes', 'value' => '1'],
                            ['label' => 'No', 'value' => '0'],
                        ];
                    },
                ],
            ],
            'fields' => [
                ['key' => 'title', 'label' => 'Title', 'type' => 'text', 'rules' => 'required|string|max:255', 'section' => 'Basic', 'help' => 'Public article headline'],
                ['key' => 'slug', 'label' => 'Slug', 'type' => 'slug', 'slug_from' => 'title', 'rules' => 'nullable|string|max:255', 'section' => 'Basic'],
                [
                    'key'          => 'category_id',
                    'label'        => 'Category',
                    'type'         => 'select',
                    'rules'        => 'nullable|integer',
                    'section'      => 'Basic',
                    'relationship' => [
                        'model'          => Category::class,
                        'value'          => 'id',
                        'label'          => 'name',
                        'search_columns' => ['name'],
                        'async'          => true,
                    ],
                ],
                [
                    'key'               => 'cover',
                    'label'             => 'Cover Image',
                    'type'              => 'image',
                    'rules'             => 'nullable|image|max:2048',
                    'disk'              => 'public',
                    'directory'         => 'articles/covers',
                    'preserve_filename' => true,
                    'delete_old'        => true,
                    'path_column'       => 'cover_path',
                    'section'           => 'Media',
                ],
                ['key' => 'is_featured', 'label' => 'Featured', 'type' => 'checkbox', 'rules' => 'nullable|boolean', 'section' => 'Basic'],
                ['key' => 'body', 'label' => 'Body', 'type' => 'rich-text', 'rules' => 'nullable|string', 'col_span' => 2, 'section' => 'Content', 'visible_when' => ['field' => 'is_featured', 'truthy' => true]],
            ],
            'custom_actions' => [
                [
                    'key'        => 'feature_one',
                    'label'      => 'Feature',
                    'scope'      => 'row',
                    'icon'       => 'Star',
                    'permission' => 'update',
                    'handler'    => function (SoftArticle $article, Request $request) {
                        $article->update(['is_featured' => true]);
                    },
                ],
                [
                    'key'        => 'feature_many',
                    'label'      => 'Feature Selected',
                    'scope'      => 'bulk',
                    'icon'       => 'Star',
                    'permission' => 'update',
                    'handler'    => function ($articles, Request $request) {
                        foreach ($articles as $article) {
                            $article->update(['is_featured' => true]);
                        }
                    },
                ],
            ],
            'import' => [
                'mode'               => self::$importModeOverride ?? 'sync',
                'queue_threshold_kb' => self::$queueThresholdOverride ?? 1024,
                'chunked_upload'     => true,
                'chunk_size'         => 2,
                'strategy'           => 'upsert',
                'unique_by'          => ['slug'],
                'columns'            => [
                    'Title' => ['attribute' => 'title', 'rules' => 'required|string|max:255'],
                    'Slug'  => ['attribute' => 'slug', 'rules' => 'required|string|max:255'],
                    'Price' => ['attribute' => 'price', 'rules' => 'nullable|numeric|min:0', 'cast' => 'float', 'default' => 0.0],
                ],
                'transform' => fn (array $row) => [
                    ...$row,
                    'slug' => strtolower((string) $row['slug']),
                ],
                'sample_rows' => [
                    ['Hello World', 'hello-world', '9.99'],
                ],
            ],
        ];
    }
}

