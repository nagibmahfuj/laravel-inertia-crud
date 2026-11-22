---
layout: home

hero:
  name: Laravel Inertia CRUD
  text: Config-driven CRUD for Laravel & Inertia React
  tagline: Define columns, fields, filters, chunked uploads, and actions in a single PHP resourceConfig() array — get production-ready React/TypeScript UI, policies, and endpoints automatically.
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
    title: Normalized Config-Driven UI
    details: Define titles, routes, breadcrumbs, columns, filters, fields, and actions once in resourceConfig(). ResourceIndex, ResourceForm, and AppLayout consume normalized meta automatically.
  - icon: 📦
    title: Native Chunked File Uploads
    details: Built-in chunked-file form field with an automatic /crud/chunk-upload endpoint, signed server validation, atomic reassembly, disk persistence, and stale chunk cleanup.
  - icon: 🎯
    title: Action-Driven Export & Import
    details: Control create, edit, delete, show, export, and import buttons and backend access from a single actions array filtered by BaseCrudPolicy.
  - icon: ⚡
    title: Interactive Generator Wizard
    details: Run php artisan crud:generate to auto-detect models and database columns, choose enabled actions, and scaffold controllers, policies, and TSX pages in seconds.
  - icon: 🔍
    title: Rich Search, Filters & Bulk Destroy
    details: Relationship search, select/multi-select, boolean, text, date, and date-range filters, whitelisted sorting, safe pagination, and built-in bulkDestroy.
  - icon: 🔐
    title: Zero-Config Permissions & Spatie
    details: Matrix-based resource permissions with read_only, create_only, and no_delete overrides, auto-detecting spatie/laravel-permission or Eloquent roles.
---

## Quick Install

```bash
composer require nagibmahfuj/laravel-inertia-crud:^2.0
php artisan vendor:publish --tag=crud-config
php artisan vendor:publish --tag=crud-assets
php artisan crud:generate
```

::: tip Compatible Stack
Supports **PHP ^8.2**, **Laravel 11 / 12 / 13**, **Inertia.js v1 / v2 / v3**, **React 18 / 19**, and **shadcn/ui**.
:::

