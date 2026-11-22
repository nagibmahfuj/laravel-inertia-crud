<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use NagibMahfuj\Crud\BaseCrudPolicy;

class LockedProductPolicy extends BaseCrudPolicy
{
    protected function resourceKey(): string
    {
        return 'locked-products';
    }
}

