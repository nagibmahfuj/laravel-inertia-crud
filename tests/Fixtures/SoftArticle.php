<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class SoftArticle extends Model
{
    use SoftDeletes;

    protected $table = 'soft_articles';

    protected $fillable = [
        'title',
        'slug',
        'category_id',
        'cover_path',
        'is_featured',
        'price',
        'body',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
    ];
}

