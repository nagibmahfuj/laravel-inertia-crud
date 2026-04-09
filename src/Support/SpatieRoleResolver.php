<?php

namespace NagibMahfuj\Crud\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use NagibMahfuj\Crud\Contracts\RoleResolver;

/**
 * Spatie-compatible role resolver that uses the HasRoles trait methods.
 *
 * Automatically selected when `spatie/laravel-permission` is installed.
 * Falls back to the Eloquent `role` column if the Spatie trait is not
 * applied on the given user model.
 */
class SpatieRoleResolver implements RoleResolver
{
	/**
	 * Get all roles for the given user.
	 *
	 * @return string[]
	 */
	public function getRoles(Authenticatable $user): array
	{
		// Use Spatie's getRoleNames() if available
		if (method_exists($user, 'getRoleNames')) {
			$roles = $user->getRoleNames()->toArray();

			return !empty($roles) ? $roles : [$user->role ?? 'viewer'];
		}

		return [$user->role ?? 'viewer'];
	}

	/**
	 * Check if the user has any of the given role(s).
	 */
	public function hasRole(Authenticatable $user, string|array $roles): bool
	{
		// Use Spatie's hasRole() if available
		if (method_exists($user, 'hasRole')) {
			return $user->hasRole($roles);
		}

		$userRole = $user->role ?? 'viewer';

		return in_array($userRole, (array) $roles, true);
	}
}
