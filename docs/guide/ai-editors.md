# AI Code Editors & Laravel Boost

`nagibmahfuj/laravel-inertia-crud` ships native AI coding context so AI assistants (Antigravity, Claude Code, Cursor, Windsurf, GitHub Copilot, Codex, Laravel Boost, and Context7) generate accurate v2 code automatically.

## 1. Laravel Boost (Zero Configuration)

The Composer package ships Boost guidelines and a structured package skill directly inside `resources/boost/`:

- `resources/boost/guidelines/core.blade.php` — concise v2 rules and conventions
- `resources/boost/skills/laravel-inertia-crud/SKILL.md` — workflow + detailed reference docs (`references/resource-config.md`, `references/uploads-and-imports.md`)

When your Laravel project runs `php artisan boost:install` (or `boost:update`), Laravel Boost automatically discovers and registers them with no extra configuration.

::: tip Fresh Laravel Starter Kits
If `composer require nagibmahfuj/laravel-inertia-crud` reports `Please set up Boost with [php artisan boost:install] first.` during `post-update-cmd`, run `php artisan boost:install` once and re-run `composer require nagibmahfuj/laravel-inertia-crud:^2.0`.
:::

---

## 2. Portable Agent Skill (`.agents/skills/`)

For projects not using Laravel Boost, install the same portable Agent Skill into any AI code editor:

::: code-group

```bash [Via Artisan (Recommended — matches installed version)]
php artisan crud:install --ai
# Or publish only the skill:
php artisan vendor:publish --tag=crud-ai
```

```bash [Via skills CLI (from GitHub)]
npx skills add nagibmahfuj/laravel-inertia-crud
```

:::

The Artisan tag publishes directly to `.agents/skills/laravel-inertia-crud/`.

---

## 3. `llms.txt` & `llms-full.txt`

Every VitePress documentation build automatically generates machine-readable LLM indexes deployed alongside the docs site:

- **Concise Docs Index:** `https://nagibmahfuj.github.io/laravel-inertia-crud/llms.txt`
- **Single-File Full Documentation:** `https://nagibmahfuj.github.io/laravel-inertia-crud/llms-full.txt`

Point any AI editor's docs/URL context feature at either URL to give it complete, up-to-date v2 documentation in one fetch.

---

## 4. Context7 & `AGENTS.md`

The repository root includes:

- `context7.json` — configures [Context7](https://context7.com) documentation indexing and concise package rules.
- `AGENTS.md` — repository conventions and verification commands for AI contributors working inside the package itself.

