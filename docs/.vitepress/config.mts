import { existsSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig } from 'vitepress';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Resolve base URL dynamically:
 * 1. Explicit DOCS_BASE env var (e.g. DOCS_BASE=/ in GitHub Actions Variables)
 * 2. Automatic '/' when a custom domain CNAME file exists in docs/public/CNAME
 * 3. Default GitHub Pages project subpath '/laravel-inertia-crud/'
 */
const hasCustomDomain = existsSync(resolve(__dirname, '../public/CNAME'));
const rawBase = process.env.DOCS_BASE?.trim() || (hasCustomDomain ? '/' : '/laravel-inertia-crud/');
const base = rawBase.startsWith('/') ? (rawBase.endsWith('/') ? rawBase : `${rawBase}/`) : `/${rawBase}/`;

export default defineConfig({
  lang: 'en-US',
  title: 'Laravel Inertia CRUD',
  description:
    'Config-driven CRUD scaffolding for Laravel + Inertia.js (React/TypeScript) with interactive generator, built-in search, filters, export, import, native chunked uploads, and role-based permissions.',
  base,
  cleanUrls: true,
  lastUpdated: true,
  ignoreDeadLinks: false,

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: `${base}logo.svg` }],
    ['meta', { name: 'theme-color', content: '#0ea5e9' }],
    ['meta', { property: 'og:type', content: 'website' }],
    ['meta', { property: 'og:title', content: 'Laravel Inertia CRUD (v2)' }],
    [
      'meta',
      {
        property: 'og:description',
        content: 'Config-driven CRUD scaffolding for Laravel + Inertia.js (React/TypeScript).',
      },
    ],
  ],

  themeConfig: {
    logo: '/logo.svg',
    siteTitle: 'Laravel Inertia CRUD',

    nav: [
      { text: 'Guide', link: '/guide/getting-started', activeMatch: '/guide/' },
      { text: 'Chunked Uploads', link: '/guide/chunked-uploads' },
      { text: 'Reference', link: '/reference/configuration', activeMatch: '/reference/' },
      {
        text: 'v2.x',
        items: [
          { text: 'Upgrade from v1.x', link: '/upgrade/v1-to-v2' },
          { text: 'v1.x Docs (GitHub)', link: 'https://github.com/nagibmahfuj/laravel-inertia-crud/tree/v1.1.2' },
          { text: 'Releases', link: 'https://github.com/nagibmahfuj/laravel-inertia-crud/releases' },
          { text: 'Packagist', link: 'https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud' },
        ],
      },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Installation & Setup', link: '/guide/getting-started' },
          { text: 'Interactive Generator', link: '/guide/generator' },
          { text: 'Normalized Resource Config', link: '/guide/resource-config' },
        ],
      },
      {
        text: 'Core Features',
        items: [
          { text: 'Columns, Search & Filters', link: '/guide/columns-and-filters' },
          { text: 'Form Fields & Validation', link: '/guide/form-fields' },
          { text: 'Chunked File Uploads', link: '/guide/chunked-uploads' },
          { text: 'Actions, Export & Import', link: '/guide/actions-import-export' },
          { text: 'Permissions & Roles', link: '/guide/permissions' },
          { text: 'Navigation, Layouts & Theming', link: '/guide/navigation-and-theming' },
          { text: 'Customization & Hooks', link: '/guide/customization' },
        ],
      },
      {
        text: 'Reference',
        items: [
          { text: 'Configuration (config/crud.php)', link: '/reference/configuration' },
          { text: 'React Components & Types', link: '/reference/components-and-types' },
          { text: 'Artisan Commands & Publish Tags', link: '/reference/artisan-commands' },
        ],
      },
      {
        text: 'Migration',
        items: [{ text: 'Upgrading v1.x → v2.0', link: '/upgrade/v1-to-v2' }],
      },
    ],

    socialLinks: [{ icon: 'github', link: 'https://github.com/nagibmahfuj/laravel-inertia-crud' }],

    search: {
      provider: 'local',
    },

    editLink: {
      pattern: 'https://github.com/nagibmahfuj/laravel-inertia-crud/edit/main/docs/:path',
      text: 'Edit this page on GitHub',
    },

    outline: {
      level: [2, 3],
      label: 'On this page',
    },

    footer: {
      message: 'Released under the MIT License.',
      copyright: 'Copyright © Nagib Mahfuj',
    },
  },
});

