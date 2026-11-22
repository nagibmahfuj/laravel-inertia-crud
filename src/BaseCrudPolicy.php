<?php

namespace NagibMahfuj\Crud;

use Illuminate\Contracts\Auth\Authenticatable;
use NagibMahfuj\Crud\Contracts\RoleResolver;

/**
 * Base policy for CRUD resources.
 *
 * Reads the permission matrix from config/crud.php and provides
 * default viewAny/view/create/update/delete/export/import checks.
 *
 * Extend this and implement resourceKey(). Override individual methods
 * only when custom logic is needed (e.g. UserPolicy).
 */
abstract class BaseCrudPolicy
{
    protected RoleResolver $roleResolver;

    public function __construct(RoleResolver $roleResolver)
    {
        $this->roleResolver = $roleResolver;
    }

    /**
     * The key used to look up this resource in config/crud.php.
     * Should match the route_prefix (e.g. 'users', 'stock-transfers').
     */
    abstract protected function resourceKey(): string;

    // ── Standard Policy Methods ──────────────────────────────

    public function viewAny(Authenticatable $user): bool
    {
        return $this->hasAccess($user);
    }

    public function view(Authenticatable $user, $model): bool
    {
        return $this->hasAccess($user);
    }

    public function create(Authenticatable $user): bool
    {
        return $this->hasCreateAccess($user);
    }

    public function update(Authenticatable $user, $model): bool
    {
        return $this->hasWriteAccess($user);
    }

    public function delete(Authenticatable $user, $model): bool
    {
        return $this->hasDeleteAccess($user);
    }

    public function restore(Authenticatable $user, $model): bool
    {
        return $this->hasDeleteAccess($user);
    }

    public function forceDelete(Authenticatable $user, $model): bool
    {
        return $this->hasDeleteAccess($user);
    }

    public function export(Authenticatable $user): bool
    {
        return $this->hasAccess($user);
    }

    public function import(Authenticatable $user): bool
    {
        return $this->hasWriteAccess($user);
    }

    // ── Access Helpers ───────────────────────────────────────

    /**
     * Can the user access this resource at all?
     */
    protected function hasAccess(Authenticatable $user): bool
    {
        $config = $this->resourceConfig();

        if (!$config) {
            return false;
        }

        return $this->roleResolver->hasRole($user, $config['roles'] ?? []);
    }

    /**
     * Can the user create records? (access + not read-only)
     */
    protected function hasCreateAccess(Authenticatable $user): bool
    {
        if (!$this->hasAccess($user)) {
            return false;
        }

        return !$this->isReadOnly($user);
    }

    /**
     * Can the user update records? (create access + not create-only)
     */
    protected function hasWriteAccess(Authenticatable $user): bool
    {
        if (!$this->hasCreateAccess($user)) {
            return false;
        }

        return !$this->isCreateOnly($user);
    }

    /**
     * Can the user delete records? (write access + not no-delete)
     */
    protected function hasDeleteAccess(Authenticatable $user): bool
    {
        if (!$this->hasWriteAccess($user)) {
            return false;
        }

        return !$this->isNoDelete($user);
    }

    // ── Config Readers ───────────────────────────────────────

    protected function resourceConfig(): ?array
    {
        return config("crud.resources.{$this->resourceKey()}");
    }

    /**
     * Is the user read-only for this resource?
     * Checks resource-level override first (`read_only` or `read_only_roles`), then global.
     */
    protected function isReadOnly(Authenticatable $user): bool
    {
        $config = $this->resourceConfig();
        $resourceReadOnly = $config['read_only'] ?? $config['read_only_roles'] ?? null;

        if ($resourceReadOnly !== null) {
            if (is_bool($resourceReadOnly)) {
                return $resourceReadOnly;
            }

            return $this->roleResolver->hasRole($user, $resourceReadOnly);
        }

        return $this->roleResolver->hasRole(
            $user,
            config('crud.read_only_roles', [])
        );
    }

    /**
     * Is the user limited to create-only for this resource?
     * Supports both `create_only` and `create_only_roles` keys.
     */
    protected function isCreateOnly(Authenticatable $user): bool
    {
        $config = $this->resourceConfig();
        $resourceCreateOnly = $config['create_only'] ?? $config['create_only_roles'] ?? null;

        if ($resourceCreateOnly === null) {
            return false;
        }

        if (is_bool($resourceCreateOnly)) {
            return $resourceCreateOnly;
        }

        return $this->roleResolver->hasRole($user, $resourceCreateOnly);
    }

    /**
     * Is the user prevented from deleting records on this resource?
     * Supports resource-level `no_delete` / `no_delete_roles` (bool or role array)
     * as well as global `crud.no_delete_roles`.
     */
    protected function isNoDelete(Authenticatable $user): bool
    {
        $config = $this->resourceConfig();
        $resourceNoDelete = $config['no_delete'] ?? $config['no_delete_roles'] ?? null;

        if ($resourceNoDelete !== null) {
            if (is_bool($resourceNoDelete)) {
                return $resourceNoDelete;
            }

            if ($this->roleResolver->hasRole($user, $resourceNoDelete)) {
                return true;
            }
        }

        return $this->roleResolver->hasRole(
            $user,
            config('crud.no_delete_roles', [])
        );
    }
}
