<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use NagibMahfuj\Crud\BaseCrudPolicy;

class ProductPolicy extends BaseCrudPolicy
{
    protected function resourceKey(): string
    {
        return 'products';
    }
}

