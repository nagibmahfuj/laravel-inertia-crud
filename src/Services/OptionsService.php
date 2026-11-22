<?php

namespace NagibMahfuj\Crud\Services;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;

/**
 * Resolves lazy Closure options, eager relationship options, and signed
 * async relationship search queries for `select` fields and filters.
 */
class OptionsService
{
    /**
     * Resolve the native async options search endpoint URL.
     */
    public function endpoint(?string $override = null): string
    {
        if ($override) {
            return $override;
        }

        $routeName = config('crud.options.route_name', 'crud.options');

        if ($routeName && Route::has($routeName)) {
            return route($routeName, [], false);
        }

        return '/' . ltrim((string) config('crud.options.route', '/crud/options'), '/');
    }

    /**
     * Resolve options for a field or filter when preparing frontend metadata.
     * Evaluates Closure options lazily, loads non-async relationship options,
     * and signs config tokens for async relationship options.
     *
     * @return array{options: array, options_endpoint?: string, options_config?: string}
     */
    public function prepareForFrontend(array $item, mixed $selectedValue = null): array
    {
        $result = [];

        // 1. Lazy closure options
        if (isset($item['options']) && $item['options'] instanceof Closure) {
            $result['options'] = $this->normalizeOptionList(call_user_func($item['options']));
            return $result;
        }

        // 2. Explicit array options
        if (isset($item['options']) && is_array($item['options']) && empty($item['relationship'])) {
            $result['options'] = $this->normalizeOptionList($item['options']);
            return $result;
        }

        // 3. Declarative relationship config
        if (!empty($item['relationship']) && is_array($item['relationship'])) {
            $rel   = $item['relationship'];
            $async = (bool) ($rel['async'] ?? false);

            if ($async && config('crud.options.enabled', true)) {
                $result['options_endpoint'] = $this->endpoint($rel['endpoint'] ?? null);
                $result['options_config']   = $this->signOptionsConfig($rel);
                $result['options']          = $this->hydrateSelectedOptions($rel, $selectedValue);
            } else {
                $result['options'] = $this->queryRelationshipOptions($rel, null, (int) ($rel['limit'] ?? 500));
            }

            return $result;
        }

        return $result;
    }

    /**
     * Encrypt relationship query parameters so the native `/crud/options` endpoint
     * can only search the exact server-approved model & columns.
     */
    public function signOptionsConfig(array $relationship, mixed $userId = null): string
    {
        $ttlHours = max(1, (int) config('crud.options.ttl_hours', 6));
        $labelCol = (string) ($relationship['label'] ?? 'name');

        $payload = [
            'user_id'        => $userId ?? Auth::id(),
            'model'          => (string) $relationship['model'],
            'value'          => (string) ($relationship['value'] ?? 'id'),
            'label'          => $labelCol,
            'search_columns' => array_values((array) ($relationship['search_columns'] ?? [$labelCol])),
            'order_by'       => (string) ($relationship['order_by'] ?? $labelCol),
            'order_dir'      => strtolower((string) ($relationship['order_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc',
            'limit'          => max(1, min((int) ($relationship['limit'] ?? config('crud.options.limit', 25)), 100)),
            'expires_at'     => now()->addHours($ttlHours)->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * Decrypt, verify ownership/expiry, and execute an async options search request.
     *
     * @throws ValidationException
     */
    public function searchFromRequest(Request $request): array
    {
        $encrypted = (string) $request->input('config', '');
        if ($encrypted === '') {
            throw ValidationException::withMessages([
                'config' => ['Missing options configuration token.'],
            ]);
        }

        try {
            $decoded = json_decode(Crypt::decryptString($encrypted), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'config' => ['Invalid or tampered options configuration.'],
            ]);
        }

        if (!is_array($decoded) || empty($decoded['model']) || !class_exists($decoded['model'])) {
            throw ValidationException::withMessages([
                'config' => ['Invalid options target model.'],
            ]);
        }

        if (isset($decoded['expires_at']) && time() > (int) $decoded['expires_at']) {
            throw ValidationException::withMessages([
                'config' => ['Options session has expired. Please refresh the page.'],
            ]);
        }

        $currentUserId = Auth::id();
        if (
            array_key_exists('user_id', $decoded)
            && $decoded['user_id'] !== null
            && $currentUserId !== null
            && (string) $decoded['user_id'] !== (string) $currentUserId
        ) {
            throw ValidationException::withMessages([
                'config' => ['Options configuration does not belong to the authenticated user.'],
            ]);
        }

        $search   = $request->filled('search') ? (string) $request->input('search') : null;
        $selected = $request->input('selected');
        $limit    = (int) ($decoded['limit'] ?? 25);

        $options = $this->queryRelationshipOptions($decoded, $search, $limit);

        // Ensure explicitly requested selected values are hydrated in the response
        if ($selected !== null && $selected !== '') {
            $selectedIds = is_array($selected) ? $selected : explode(',', (string) $selected);
            $hydrated    = $this->hydrateSelectedOptions($decoded, $selectedIds);
            $existing    = array_column($options, 'value');
            foreach ($hydrated as $opt) {
                if (!in_array($opt['value'], $existing, true)) {
                    array_unshift($options, $opt);
                }
            }
        }

        return $options;
    }

    /**
     * Query options directly from an Eloquent model based on relationship config.
     */
    public function queryRelationshipOptions(array $rel, ?string $search = null, int $limit = 25): array
    {
        $modelClass = $rel['model'] ?? null;
        if (!$modelClass || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $valueCol      = (string) ($rel['value'] ?? 'id');
        $labelCol      = (string) ($rel['label'] ?? 'name');
        $searchColumns = array_values((array) ($rel['search_columns'] ?? [$labelCol]));
        $orderBy       = (string) ($rel['order_by'] ?? $labelCol);
        $orderDir      = strtolower((string) ($rel['order_dir'] ?? 'asc')) === 'desc' ? 'desc' : 'asc';

        $query = $modelClass::query();

        if (isset($rel['query']) && is_callable($rel['query'])) {
            call_user_func($rel['query'], $query);
        }

        if ($search !== null && $search !== '') {
            $query->where(function ($q) use ($searchColumns, $search) {
                foreach ($searchColumns as $col) {
                    $q->orWhere($col, 'like', "%{$search}%");
                }
            });
        }

        return $query
            ->orderBy($orderBy, $orderDir)
            ->limit(max(1, $limit))
            ->get(array_values(array_unique([$valueCol, $labelCol])))
            ->map(fn ($row) => [
                'value' => (string) $row->{$valueCol},
                'label' => (string) $row->{$labelCol},
            ])
            ->values()
            ->toArray();
    }

    /**
     * Hydrate initial `{ value, label }` entries for pre-selected value(s) on edit/filter.
     */
    protected function hydrateSelectedOptions(array $rel, mixed $selectedValue): array
    {
        $modelClass = $rel['model'] ?? null;
        if (!$modelClass || !class_exists($modelClass) || !is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $ids = is_array($selectedValue)
            ? array_values(array_filter($selectedValue, fn ($v) => $v !== null && $v !== ''))
            : ($selectedValue !== null && $selectedValue !== '' ? [$selectedValue] : []);

        if (empty($ids)) {
            // Return initial page of options so dropdown isn't blank before typing
            return $this->queryRelationshipOptions($rel, null, min(15, (int) ($rel['limit'] ?? 25)));
        }

        $valueCol = (string) ($rel['value'] ?? 'id');
        $labelCol = (string) ($rel['label'] ?? 'name');

        return $modelClass::query()
            ->whereIn($valueCol, $ids)
            ->get(array_values(array_unique([$valueCol, $labelCol])))
            ->map(fn ($row) => [
                'value' => (string) $row->{$valueCol},
                'label' => (string) $row->{$labelCol},
            ])
            ->values()
            ->toArray();
    }

    /**
     * Normalize option arrays (supports both `[['label' => ..., 'value' => ...]]` and `['key' => 'Label']`).
     */
    protected function normalizeOptionList(mixed $options): array
    {
        if (!is_array($options)) {
            return [];
        }

        $normalized = [];
        foreach ($options as $key => $item) {
            if (is_array($item) && array_key_exists('value', $item) && array_key_exists('label', $item)) {
                $normalized[] = [
                    'value' => (string) $item['value'],
                    'label' => (string) $item['label'],
                ];
            } else {
                $normalized[] = [
                    'value' => (string) $key,
                    'label' => (string) $item,
                ];
            }
        }

        return $normalized;
    }
}

