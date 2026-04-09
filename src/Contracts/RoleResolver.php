<?php

namespace NagibMahfuj\Crud\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

interface RoleResolver
{
	/**
	 * Get all roles for the given user.
	 *
	 * @return string[]
	 */
	public function getRoles(Authenticatable $user): array;

	/**
	 * Check if the user has any of the given role(s).
	 */
	public function hasRole(Authenticatable $user, string|array $roles): bool;
}
