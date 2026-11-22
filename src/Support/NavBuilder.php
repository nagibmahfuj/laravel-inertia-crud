<?php

namespace NagibMahfuj\Crud\Support;

use Illuminate\Support\Facades\Gate;

class NavBuilder
{
    /**
     * Filter navigation groups and items based on policy permissions.
     */
    public static function filter(array $groups, $user): array
    {
        return collect($groups)
            ->map(function ($group) use ($user) {
                $group['items'] = self::filterItems($group['items'] ?? [], $user);
                return $group;
            })
            ->filter(fn ($group) => count($group['items'] ?? []) > 0)
            ->values()
            ->toArray();
    }

    /**
     * Recursively filter items and sub-items.
     */
    protected static function filterItems(array $items, $user): array
    {
        return collect($items)
            ->filter(fn ($item) => self::canAccess($user, $item))
            ->map(function ($item) use ($user) {
                if (isset($item['items'])) {
                    $item['items'] = self::filterItems($item['items'], $user);

                    // If all children are filtered out, discard the parent if it has no direct href
                    if (count($item['items']) === 0 && !isset($item['href'])) {
                        return null;
                    }
                }
                return $item;
            })
            ->filter()
            ->values()
            ->toArray();
    }

    /**
     * Check permissions for a single nav item.
     */
    protected static function canAccess($user, array $item): bool
    {
        if (!isset($item['permission'])) {
            return true;
        }

        if (!$user) {
            return false;
        }

        $permission = $item['permission'];

        if (is_string($permission) && class_exists($permission)) {
            return Gate::forUser($user)->allows('viewAny', $permission);
        }

        return Gate::forUser($user)->allows($permission);
    }
}
