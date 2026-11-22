# AGENTS.md — `nagibmahfuj/laravel-inertia-crud`

Guidance for AI coding agents working directly inside this package repository.

## Repository Layout

- `src/` — Core PHP package (`CrudController`, `CrudService`, `BaseCrudPolicy`, `CrudServiceProvider`, `Services/`, `Http/Controllers/`, `Jobs/`, `Events/`, `Rules/`, `Commands/`, `Support/`, `Contracts/`, `Facades/`).
- `config/crud.php` — Default package configuration (permissions, chunk upload, async options, CSV import, generator defaults).
- `resources/js/` — Publishable React/TypeScript/shadcn components (`components/crud/`), hooks (`hooks/use-nav-items.ts`), and theming (`lib/theme.ts`).
- `resources/boost/` — Laravel Boost AI guidelines (`guidelines/core.blade.php`) and canonical package skill (`skills/laravel-inertia-crud/`).
- `skills/` — Portable Agent Skill mirror for `npx skills add nagibmahfuj/laravel-inertia-crud`.
- `stubs/` — Generator templates (`CrudController.stub`, `CrudPolicy.stub`, `IndexPage.stub`, `FormPage.stub`, `ShowPage.stub`).
- `docs/` — Isolated VitePress v2 documentation site deployed to GitHub Pages (auto-generates `llms.txt` and `llms-full.txt` on build).
- `tests/` — Pest + Orchestra Testbench unit & feature test suite.

## Development & Verification Commands

- **Run PHP tests:** `composer test` (Pest + Orchestra Testbench, SQLite in-memory).
- **Lint PHP syntax:** `find src config tests -name '*.php' -exec php -l {} +`
- **Build & verify docs (+ dead-link check & `llms.txt` generation):** `cd docs && npm run build`
- **Preview docs locally:** `cd docs && npm run dev`

## Coding Conventions

- Keep PHP compatible with **PHP ^8.2** and **Laravel 11 / 12 / 13**.
- Preserve subclass method compatibility on `CrudController` (`create()` parameterless; `show/edit/update/destroy/restore/forceDestroy` use `int|string $id`).
- Strip all `Closure` values before passing arrays to Inertia responses or dispatching queued jobs.
- Keep all React components strict TypeScript (`strict: true`, `noImplicitAny: true`), add `type="button"` to non-submit buttons, and avoid wrapping `<AppLayout>` inside `ResourceIndex`, `ResourceForm`, or `ResourceShow`.
- Whenever changing `resources/boost/skills/laravel-inertia-crud/`, keep `skills/laravel-inertia-crud/` in sync.
- Keep `.gitattributes` excluding `/docs`, `/.github`, `/tests`, `/skills`, `/context7.json`, `/AGENTS.md`, and `/phpunit.xml` from Packagist dist archives (while keeping `resources/boost/` included for Laravel Boost discovery).

