<?php

namespace NagibMahfuj\Crud\Support;

use Illuminate\Http\Request;
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
        // No permission check required for this item
        if (!isset($item['permission'])) {
            return true;
        }

        $permission = $item['permission'];

        // If permission is a class name, check viewAny on that model
        if (is_string($permission) && class_exists($permission)) {
            return Gate::forUser($user)->allows('viewAny', new $permission());
        }

        // Otherwise, treat as a string-based ability
        return Gate::forUser($user)->allows($permission);
    }
}
