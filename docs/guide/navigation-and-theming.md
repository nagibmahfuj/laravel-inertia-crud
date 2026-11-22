# Navigation, Layouts & Theming

Wire permission-aware sidebar navigation, Inertia layouts, flash toasts, and runtime brand colors.

## Normalized `AppLayout` & Flash Toasts

In v2.0, none of `ResourceIndex`, `ResourceForm`, or `ResourceShow` wraps `<AppLayout>` internally. Configure your `AppLayout` to read normalized `meta.breadcrumbs` as a fallback and display session flash messages:

```tsx
// resources/js/layouts/app-layout.tsx
import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import type { BreadcrumbItem } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import { toast } from '@/components/ui/sonner';

export default function AppLayout({
    breadcrumbs,
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    const { flash, meta } = usePage().props as any;
    const resolvedBreadcrumbs = breadcrumbs ?? meta?.breadcrumbs ?? [];

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success, { id: flash.success });
        }
        if (flash?.error) {
            toast.error(flash.error, { id: flash.error });
        }
    }, [flash]);

    return (
        <AppLayoutTemplate breadcrumbs={resolvedBreadcrumbs}>
            {children}
        </AppLayoutTemplate>
    );
}
```

Share `flash` (including `flash.data`, which powers the live background CSV import progress banner in `ResourceIndex`) in `app/Http/Middleware/HandleInertiaRequests.php`:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'flash' => [
            'success' => fn () => $request->session()->get('success'),
            'error'   => fn () => $request->session()->get('error'),
            'data'    => fn () => $request->session()->get('data'),
        ],
    ]);
}
```

---

## Toaster & Runtime Theme (`app.tsx`)

Publish the theme file (`resources/js/lib/crud-theme.ts`) via `php artisan vendor:publish --tag=crud-assets` and apply it in your Inertia entry file:

::: code-group

```tsx [Inertia v3]
import { Toaster } from '@/components/ui/sonner';
import { createInertiaApp } from '@inertiajs/react';
import { applyCustomTheme, theme, customThemeColors } from '@/lib/crud-theme';

createInertiaApp({
    // ...
    withApp(app) {
        return (
            <>
                {app}
                <Toaster {...theme.toast} />
            </>
        );
    },
    progress: {
        color: customThemeColors.primary,
    },
});

applyCustomTheme();
```

```tsx [Inertia v1 / v2]
import { Toaster } from '@/components/ui/sonner';
import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import { applyCustomTheme, theme, customThemeColors } from '@/lib/crud-theme';

createInertiaApp({
    // ...
    setup({ el, App, props }) {
        createRoot(el).render(
            <>
                <App {...props} />
                <Toaster {...theme.toast} />
            </>,
        );
    },
    progress: {
        color: customThemeColors.primary,
    },
});

applyCustomTheme();
```

:::

Customize colors in `resources/js/lib/crud-theme.ts`:

```ts
export const customThemeColors: CustomThemeColors = {
    primary: 'oklch(0.59 0.16 241.96)',
    // sidebarBackground: 'oklch(0.205 0 0)',
    // sidebarForeground: 'oklch(0.985 0 0)',
};
```

---

## Dynamic Permission-Aware Navigation

### 1. Filter Groups on the Backend (`NavBuilder`)

```php
use NagibMahfuj\Crud\Support\NavBuilder;
use App\Models\Product;
use App\Models\User;

public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'navGroups' => fn () => NavBuilder::filter([
            [
                'title' => 'General',
                'items' => [
                    ['title' => 'Dashboard', 'href' => '/dashboard', 'icon' => 'LayoutGrid'],
                ],
            ],
            [
                'title' => 'Management',
                'items' => [
                    ['title' => 'Users',    'href' => '/dashboard/users',    'icon' => 'Users',   'permission' => User::class],
                    ['title' => 'Products', 'href' => '/dashboard/products', 'icon' => 'Package', 'permission' => Product::class],
                ],
            ],
        ], $request->user()),
    ]);
}
```

- When `permission` is a Model class name, `NavBuilder` checks `Gate::forUser($user)->allows('viewAny', $modelClass)`.
- When `permission` is a string ability name, `NavBuilder` checks `Gate::forUser($user)->allows($ability)`.
- Empty groups (where all items are hidden) are automatically pruned.

### 2. Render in Sidebar (`useNavGroups`)

```tsx
import { useNavGroups } from '@/hooks/use-nav-items';
import { NavMain } from '@/components/nav-main';

export function AppSidebar() {
    const navGroups = useNavGroups();

    return (
        <SidebarContent>
            {navGroups.map((group) => (
                <NavMain key={group.title} title={group.title} items={group.items} />
            ))}
        </SidebarContent>
    );
}
```

---

## Built-In Icon Map (`icon-map.ts`)

KPI `stats` cards and declarative `custom_actions` accept string Lucide icon names (e.g. `'Package'`, `'Users'`, `'TrendingUp'`, `'CheckCircle'`, `'Send'`, `'Star'`, `'DollarSign'`, `'Trash2'`). These resolve via `resolveLucideIcon()` in your published `resources/js/components/crud/icon-map.ts`, which ships with 65+ common dashboard icons and can be extended freely with any icon from `lucide-react`.

