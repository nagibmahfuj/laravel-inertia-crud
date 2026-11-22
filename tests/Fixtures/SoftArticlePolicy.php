<?php

namespace NagibMahfuj\Crud\Tests\Fixtures;

use NagibMahfuj\Crud\BaseCrudPolicy;

class SoftArticlePolicy extends BaseCrudPolicy
{
    protected function resourceKey(): string
    {
        return 'articles';
    }
}

