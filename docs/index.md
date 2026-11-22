---
layout: home

hero:
  name: Laravel Inertia CRUD
  text: Config-driven CRUD for Laravel & Inertia React
  tagline: Define columns, fields, filters, chunked uploads, background CSV imports, soft deletes, stats, and actions in a single PHP resourceConfig() array — get production-ready React/TypeScript UI automatically.
  image:
    src: /logo.svg
    alt: Laravel Inertia CRUD
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started
    - theme: alt
      text: Upgrade from v1.x
      link: /upgrade/v1-to-v2
    - theme: alt
      text: View on GitHub
      link: https://github.com/nagibmahfuj/laravel-inertia-crud

features:
  - icon: ⚙️
    title: Normalized Index, Form & Show UI
    details: Define titles, routes, breadcrumbs, sections, 2-column grids, conditional fields, KPI stats, and detail views in resourceConfig(). Opt into page, modal, or slide-over drawer forms per resource.
  - icon: 📦
    title: Native Chunked & Standard File Uploads
    details: Built-in chunked-file (/crud/chunk-upload) plus auto-handled file & image fields with live previews, signed server validation, atomic reassembly, disk persistence, and delete cleanup.
  - icon: 🚀
    title: Sync & Background Queued CSV Imports
    details: Config-driven streaming CSV import engine with sync/queued/auto modes, chunked-upload CSV support, real-time Cache progress polling, failed-row error CSV reports, sample templates, and events.
  - icon: 🎯
    title: Actions, Soft Deletes & Custom Handlers
    details: Policy-filtered create, edit, delete, show, export, import, restore, forceDelete, bulk actions, inline toggles, and declarative custom row/bulk actions from one config array.
  - icon: 🔍
    title: Lazy & Async Relationship Selects
    details: Lazy Closure options and signed native /crud/options async search, plus column visibility toggles, relationship search, faceted multi-select, text, boolean, date, and date-range filters.
  - icon: ⚡
    title: Full Artisan CLI Toolkit
    details: Run crud:install for guided setup, crud:generate to scaffold controllers/policies/Index+Form+Show pages, crud:doctor to audit health, and crud:cleanup-chunks for housekeeping.
---

## Quick Install

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
php artisan crud:install
php artisan crud:generate
php artisan crud:doctor
```

::: tip Compatible Stack
Supports **PHP ^8.2**, **Laravel 11 / 12 / 13**, **Inertia.js v1 / v2 / v3**, **React 18 / 19**, and **shadcn/ui**.
:::
