# Artisan Commands & Publish Tags

## Commands

### `php artisan crud:install`

Guided setup command that publishes `config/crud.php`, frontend components/hooks/theme, optional stubs, optional portable AI Agent Skill (`.agents/skills/`), and audits `package.json` + `resources/js/components/ui/` to report any missing npm packages or shadcn/ui components.

```bash
php artisan crud:install
php artisan crud:install --stubs --ai --force
```

---

### `php artisan crud:generate`

Generates config-driven CRUD scaffolding (controller, policy, normalized `Index.tsx`, `Form.tsx`, and `Show.tsx` when `'show'` is selected, plus `config/crud.php` permissions entry).

```bash
# Interactive 7-step wizard
php artisan crud:generate

# Non-interactive full scaffold (generates Show.tsx automatically when 'show' is in --actions)
php artisan crud:generate --model=Product --all --actions=create,edit,delete,show,export,import
```

See the [Interactive Generator guide](/guide/generator) for all CLI options.

---

### `php artisan crud:doctor`

Audits package health and every discovered `CrudController` subclass in your application:
- Config publish status & active `RoleResolver`
- Native endpoints (`/crud/chunk-upload`, `/crud/options`, `/crud/imports/{id}/status`)
- Queue connection vs `crud.import.mode` (warns if queued imports are enabled on `sync` driver)
- Chunk temp directory writability & published React components
- Per-controller checks: model class existence, policy registration, `crud.resources` key presence, route registration, and file storage disk validity

```bash
php artisan crud:doctor
```

---

### `php artisan crud:cleanup-chunks`

Deletes expired partial chunk upload directories and unclaimed completed uploads older than `config('crud.chunk_upload.ttl_hours', 6)`.

```bash
php artisan crud:cleanup-chunks
php artisan crud:cleanup-chunks --hours=2
```

---

## Publish Tags

```bash
php artisan vendor:publish --tag=<tag>
```

| Tag | Published Destination(s) |
| --- | --- |
| `crud-config` | `config/crud.php` |
| `crud-components` | `resources/js/components/crud/` |
| `crud-lib` | `resources/js/lib/crud-theme.ts` and `resources/js/hooks/use-nav-items.ts` |
| `crud-assets` | All frontend assets (`crud-components` + `crud-lib`) |
| `crud-stubs` | `stubs/crud/` |
| `crud-ai` | `.agents/skills/laravel-inertia-crud/` (portable AI Agent Skill + reference docs) |

---

## AI Code Editor & Laravel Boost Support

`nagibmahfuj/laravel-inertia-crud` ships native context for AI coding editors and agents (Antigravity, Claude Code, Cursor, Windsurf, GitHub Copilot, Codex, Laravel Boost, and Context7):

- **Laravel Boost (zero config):** Automatically discovers `resources/boost/guidelines/core.blade.php` and `resources/boost/skills/laravel-inertia-crud/` whenever you run `php artisan boost:install`.
- **Portable Agent Skill (any AI editor):**
  ```bash
  # Install via skills CLI (from GitHub)
  npx skills add nagibmahfuj/laravel-inertia-crud

  # Or publish directly from your installed Composer package into .agents/skills/
  php artisan crud:install --ai
  # or: php artisan vendor:publish --tag=crud-ai
  ```
- **`llms.txt` & `llms-full.txt`:** Generated automatically on every documentation build and published alongside the docs site:
  - Index: `https://nagibmahfuj.github.io/laravel-inertia-crud/llms.txt`
  - Single-file full docs: `https://nagibmahfuj.github.io/laravel-inertia-crud/llms-full.txt`
- **Context7 & `AGENTS.md`:** Includes `context7.json` and `AGENTS.md` at the repository root for automated doc indexing and repo conventions.
