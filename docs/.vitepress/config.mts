import { existsSync, readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { defineConfig, type SiteConfig } from 'vitepress';

const __dirname = dirname(fileURLToPath(import.meta.url));

/**
 * Resolve base URL dynamically:
 * 1. Explicit DOCS_BASE env var (e.g. DOCS_BASE=/ in GitHub Actions Variables)
 * 2. Automatic '/' when a custom domain CNAME file exists in docs/public/CNAME
 * 3. Default GitHub Pages project subpath '/laravel-inertia-crud/'
 */
const cnamePath = resolve(__dirname, '../public/CNAME');
const hasCustomDomain = existsSync(cnamePath);
const rawBase = process.env.DOCS_BASE?.trim() || (hasCustomDomain ? '/' : '/laravel-inertia-crud/');
const base = rawBase.startsWith('/') ? (rawBase.endsWith('/') ? rawBase : `${rawBase}/`) : `/${rawBase}/`;

const resolveSiteOrigin = (): string => {
  if (process.env.DOCS_SITE_URL?.trim()) {
    return process.env.DOCS_SITE_URL.trim().replace(/\/+$/, '');
  }
  if (hasCustomDomain) {
    const domain = readFileSync(cnamePath, 'utf8')
      .split(/\r?\n/)
      .map((line) => line.trim())
      .find((line) => line.length > 0 && !line.startsWith('#'));
    if (domain) {
      return `https://${domain.replace(/^https?:\/\//, '').replace(/\/+$/, '')}`;
    }
  }
  return 'https://nagibmahfuj.github.io';
};

const siteBaseUrl = `${resolveSiteOrigin()}${base}`;

const sidebarGroups = [
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
];

function cleanMarkdownForLlm(raw: string): string {
  const withoutFrontmatter = raw.replace(/^---\r?\n[\s\S]*?\r?\n---\r?\n*/, '');
  const lines = withoutFrontmatter.split(/\r?\n/);
  const output: string[] = [];
  let inCodeFence = false;

  for (const line of lines) {
    if (/^\s*```/.test(line)) {
      inCodeFence = !inCodeFence;
      output.push(line);
      continue;
    }

    if (!inCodeFence) {
      if (/^:::\s*code-group\s*$/.test(line) || /^:::\s*$/.test(line)) {
        continue;
      }
      const containerMatch = line.match(/^:::\s*(tip|info|warning|danger|details)(?:\s+(.*))?$/);
      if (containerMatch) {
        const kind = containerMatch[1].toUpperCase();
        const heading = containerMatch[2]?.trim();
        output.push(`> **${kind}${heading ? ` — ${heading}` : ''}**`);
        continue;
      }
    }

    output.push(line);
  }

  return output.join('\n').replace(/\n{3,}/g, '\n\n').trim();
}

function extractSummary(cleanedMarkdown: string): string {
  const lines = cleanedMarkdown.split(/\r?\n/);
  let inCodeFence = false;

  for (const rawLine of lines) {
    const line = rawLine.trim();
    if (line.startsWith('```')) {
      inCodeFence = !inCodeFence;
      continue;
    }
    if (inCodeFence || !line) continue;
    if (line.startsWith('#') || line.startsWith('>') || line.startsWith('|') || line.startsWith('-') || line.startsWith('*')) {
      continue;
    }

    const plain = line
      .replace(/\[([^\]]+)\]\([^)]+\)/g, '$1')
      .replace(/[*`]/g, '')
      .replace(/\s+/g, ' ')
      .trim();

    if (plain.length > 0) {
      return plain.length > 180 ? `${plain.slice(0, 177).trimEnd()}...` : plain;
    }
  }

  return '';
}

function generateLlmsFiles(siteConfig: SiteConfig): void {
  const indexLines: string[] = [
    '# Laravel Inertia CRUD',
    '',
    '> Config-driven CRUD scaffolding for Laravel + Inertia.js (React/TypeScript) with interactive generator, built-in search, filters, export, sync & background queued CSV imports, native chunked uploads, KPI stats, SoftDeletes, custom actions, and role-based permissions.',
    '',
    `- Full single-file documentation: [llms-full.txt](${siteBaseUrl}llms-full.txt)`,
    `- Documentation home: ${siteBaseUrl}`,
    '- Source repository: https://github.com/nagibmahfuj/laravel-inertia-crud',
    '- Packagist: https://packagist.org/packages/nagibmahfuj/laravel-inertia-crud',
    '',
  ];

  const fullSections: string[] = [
    '# Laravel Inertia CRUD (v2) — Full Documentation',
    '',
    `> Source: ${siteBaseUrl}`,
    '> Package: nagibmahfuj/laravel-inertia-crud (https://github.com/nagibmahfuj/laravel-inertia-crud)',
    '',
  ];

  for (const group of sidebarGroups) {
    indexLines.push(`## ${group.text}`, '');

    for (const item of group.items) {
      const slug = item.link.replace(/^\//, '');
      const mdPath = resolve(siteConfig.srcDir, `${slug}.md`);
      if (!existsSync(mdPath)) {
        continue;
      }

      const url = `${siteBaseUrl}${slug}`;
      const cleaned = cleanMarkdownForLlm(readFileSync(mdPath, 'utf8'));
      const summary = extractSummary(cleaned);

      indexLines.push(summary ? `- [${item.text}](${url}): ${summary}` : `- [${item.text}](${url})`);

      fullSections.push('---', '', `<!-- Section: ${group.text} | URL: ${url} -->`, '', cleaned, '');
    }

    indexLines.push('');
  }

  writeFileSync(resolve(siteConfig.outDir, 'llms.txt'), `${indexLines.join('\n').trimEnd()}\n`, 'utf8');
  writeFileSync(resolve(siteConfig.outDir, 'llms-full.txt'), `${fullSections.join('\n').trimEnd()}\n`, 'utf8');
}

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

    sidebar: sidebarGroups,

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

  buildEnd(siteConfig) {
    generateLlmsFiles(siteConfig);
  },
});

