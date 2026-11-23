<?php

use NagibMahfuj\Crud\Contracts\RoleResolver;
use NagibMahfuj\Crud\Tests\Fixtures\LockedProductPolicy;
use NagibMahfuj\Crud\Tests\Fixtures\Product;
use NagibMahfuj\Crud\Tests\Fixtures\ProductPolicy;

it('enforces roles, create_only, no_delete, and global read_only_roles on BaseCrudPolicy', function () {
    $policy = new ProductPolicy(app(RoleResolver::class));
    $product = new Product();

    $superAdmin = $this->createUser('super_admin');
    $admin      = $this->createUser('admin');
    $manager    = $this->createUser('manager');
    $viewer     = $this->createUser('viewer');
    $outsider   = $this->createUser('guest');

    // Super admin has full access
    expect($policy->viewAny($superAdmin))->toBeTrue()
        ->and($policy->create($superAdmin))->toBeTrue()
        ->and($policy->update($superAdmin, $product))->toBeTrue()
        ->and($policy->delete($superAdmin, $product))->toBeTrue()
        ->and($policy->export($superAdmin))->toBeTrue()
        ->and($policy->import($superAdmin))->toBeTrue();

    // Admin has resource-level no_delete => ['admin']
    expect($policy->viewAny($admin))->toBeTrue()
        ->and($policy->create($admin))->toBeTrue()
        ->and($policy->update($admin, $product))->toBeTrue()
        ->and($policy->delete($admin, $product))->toBeFalse()
        ->and($policy->export($admin))->toBeTrue()
        ->and($policy->import($admin))->toBeTrue();

    // Manager has create_only => ['manager']
    expect($policy->viewAny($manager))->toBeTrue()
        ->and($policy->create($manager))->toBeTrue()
        ->and($policy->update($manager, $product))->toBeFalse()
        ->and($policy->delete($manager, $product))->toBeFalse()
        ->and($policy->export($manager))->toBeTrue()
        ->and($policy->import($manager))->toBeFalse();

    // Viewer is in global read_only_roles
    expect($policy->viewAny($viewer))->toBeTrue()
        ->and($policy->create($viewer))->toBeFalse()
        ->and($policy->update($viewer, $product))->toBeFalse()
        ->and($policy->delete($viewer, $product))->toBeFalse()
        ->and($policy->export($viewer))->toBeTrue()
        ->and($policy->import($viewer))->toBeFalse();

    // Outsider has no access at all
    expect($policy->viewAny($outsider))->toBeFalse()
        ->and($policy->export($outsider))->toBeFalse();
});

it('supports read_only_roles alias and boolean no_delete => true at resource level', function () {
    $policy     = new LockedProductPolicy(app(RoleResolver::class));
    $product    = new Product();
    $superAdmin = $this->createUser('super_admin');
    $viewer     = $this->createUser('viewer');

    expect($policy->update($superAdmin, $product))->toBeTrue()
        ->and($policy->delete($superAdmin, $product))->toBeFalse()
        ->and($policy->create($viewer))->toBeFalse();
});

it('resolves Spatie roles via SpatieRoleResolver even when user has no role column', function () {
    $resolver = new \NagibMahfuj\Crud\Support\SpatieRoleResolver();
    $policy   = new ProductPolicy($resolver);
    $product  = new Product();

    $spatieAdmin = new class extends \Illuminate\Foundation\Auth\User {
        public function getRoleNames(): \Illuminate\Support\Collection
        {
            return collect(['super_admin']);
        }

        public function hasRole($roles): bool
        {
            return collect((array) $roles)->intersect(['super_admin'])->isNotEmpty();
        }
    };

    expect($resolver->getRoles($spatieAdmin))->toBe(['super_admin'])
        ->and($resolver->hasRole($spatieAdmin, []))->toBeFalse()
        ->and($resolver->hasRole($spatieAdmin, ['super_admin']))->toBeTrue()
        ->and($resolver->hasRole($spatieAdmin, ['viewer']))->toBeFalse()
        ->and($policy->viewAny($spatieAdmin))->toBeTrue()
        ->and($policy->create($spatieAdmin))->toBeTrue()
        ->and($policy->update($spatieAdmin, $product))->toBeTrue()
        ->and($policy->delete($spatieAdmin, $product))->toBeTrue()
        ->and($policy->import($spatieAdmin))->toBeTrue();
});

