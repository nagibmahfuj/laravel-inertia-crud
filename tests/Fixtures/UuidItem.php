<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

class UuidItem extends Model
{
    protected $table = 'uuid_items';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'title'];
}

