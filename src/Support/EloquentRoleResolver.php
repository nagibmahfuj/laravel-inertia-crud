<?php

namespace NagibMahfuj\Crud\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use NagibMahfuj\Crud\Contracts\RoleResolver;

/**
 * Default role resolver that reads from the user model's `role` column.
 *
 * This is suitable for applications that store a single role string
 * directly on the users table (e.g., 'admin', 'editor', 'viewer').
 */
class EloquentRoleResolver implements RoleResolver
{
	/**
	 * Get all roles for the given user.
	 *
	 * @return string[]
	 */
	public function getRoles(Authenticatable $user): array
	{
		return [$user->role ?? 'viewer'];
	}

	/**
	 * Check if the user has any of the given role(s).
	 */
	public function hasRole(Authenticatable $user, string|array $roles): bool
	{
		$userRole = $user->role ?? 'viewer';

		return in_array($userRole, (array) $roles, true);
	}
}
