# Bagisto URL Prefixes

Configurable product and category URL prefixes for Bagisto storefronts.

```text
Products:    /running-shoe → /products/running-shoe
Categories:  /shoes        → /collection/shoes
```

The package reuses Bagisto's product and category pages, preserves translated slugs, and updates links generated through Bagisto's product route and category URL accessor. It also supplies a category carousel override for the default Shop component, whose original links use bare slugs.

## Why use it?

Bagisto normally resolves product and category slugs through one storefront fallback route. Stores that want separate URL namespaces can use this package to distinguish products from categories without editing Bagisto's core routes, controllers, or templates.

Use it when you want URLs such as `/products/running-shoe` and `/collections/shoes`, configurable prefixes across your store, and continued access to existing bare-slug URLs. Prefixing URLs alone does not improve search rankings; redirects, canonical URLs, sitemaps, and theme links still need a migration review.

## Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [How it works](#how-it-works)
- [Behavior and integration](#behavior-and-integration)
- [Using routes in your theme](#using-routes-in-your-theme)
- [Migrating an existing store](#migrating-an-existing-store)
- [Troubleshooting](#troubleshooting)
- [Removing the package](#removing-the-package)
- [Local package development](#local-package-development)
- [Tests](#tests)
- [Package layout](#package-layout)
- [Contributing](#contributing)
- [License](#license)

## Requirements

- An existing Bagisto 2.5 application using Laravel 13 and PHP 8.4 or later.
- Bagisto's Shop, Category, Product, Core, and Theme packages enabled.
- Composer package discovery enabled.

This is a Bagisto extension, not a standalone Laravel storefront. Bagisto 2.4 and earlier are not supported by this version. The extension adds no tables or migrations and no frontend build step of its own.

## Installation

The intended Composer package name is `verasole/bagisto-url-prefixes`. Until the repository is published and submitted to Packagist, the public installation command below is not available.

After publication on GitHub, register the repository in your Bagisto application:

```bash
composer config repositories.bagisto-url-prefixes vcs https://github.com/verasole/bagisto-url-prefixes
composer require verasole/bagisto-url-prefixes:dev-main
php artisan optimize:clear
```

After a stable release has been tagged and indexed on Packagist, installation can use just `composer require verasole/bagisto-url-prefixes` followed by `php artisan optimize:clear`.

Laravel discovers the service provider from the package's Composer metadata. No application PSR-4 mapping, `bootstrap/providers.php` entry, `config/concord.php` entry, or `phpunit.xml` edit is required. The extension substitutes Bagisto's existing Category contract through Concord after the core modules boot; it introduces no new entity or Concord module.

If your application disables package discovery, explicitly register `Verasole\Storefront\Providers\StorefrontServiceProvider` in `bootstrap/providers.php`.

## Configuration

Publish the optional configuration:

```bash
php artisan vendor:publish --tag=verasole-storefront-config
```

Edit `config/storefront.php`:

```php
return [
    'category_prefix' => 'collection',
    'product_prefix' => 'products',
    'canonical' => true,
    'canonical_excluded_themes' => [],
];
```

| Option | Default | Purpose |
| --- | --- | --- |
| `category_prefix` | `collection` | Static path before a category slug. |
| `product_prefix` | `products` | Static path before a product URL key. |
| `canonical` | `true` | Add a canonical tag on package product and category routes. |
| `canonical_excluded_themes` | `[]` | Theme codes for which the package omits its canonical tag. |

For example, set `category_prefix` to `collections` and `product_prefix` to `items` to produce `/collections/shoes` and `/items/running-shoe`.

Prefixes must be distinct, non-empty static paths containing lowercase ASCII letters, digits, dashes, underscores, or separating slashes. Leading and trailing slashes are removed. Choose prefixes that do not conflict with application or extension routes. Prefixes apply to every channel and locale; the product and category slugs remain localized.

Run `php artisan optimize:clear` after changing prefixes, then rebuild the route cache if your deployment uses it.

```bash
php artisan optimize:clear
php artisan route:cache
```

Use `route:cache` only when route caching is part of your deployment. The package supports Laravel's compiled route collection; changing configuration does not rewrite routes already stored in the route cache.

## How it works

### 1. Composer discovers the provider

`composer.json` exposes the `Verasole\Storefront\` namespace and declares `StorefrontServiceProvider` in `extra.laravel.providers`. Laravel registers it automatically. The provider merges configuration defaults and exposes the optional publish command.

### 2. Setup waits for Bagisto

Composer-discovered providers can load before the application's Bagisto providers. The package defers its route and model changes until the application has booted, when Bagisto's routes, view namespaces, and Concord registrations are available.

### 3. Categories receive prefixed URLs

The package extends Bagisto's existing Category model and registers that implementation for the existing Category contract through Concord. Its URL accessor resolves the current locale's translation, with the default channel locale as a fallback, and generates a URL through `shop.collection.show`. It preserves the core translation model and relationship.

No category slugs are rewritten in the database. API resources and components that read the category's `url` attribute receive the prefixed URL; clients constructing URLs from the raw `slug` need to use the route or the supplied URL instead. A category without a usable translated slug falls back to the application home URL.

### 4. Routes receive separate responsibilities

| Route name | Default path | Responsibility |
| --- | --- | --- |
| `shop.collection.show` | `/collection/{slug}` | Render a visible category in the current channel's tree. |
| `shop.product_or_category.index` | `/products/{slug}` | Render an enabled, individually visible product available in the current channel. |
| `shop.slug.fallback` | Original Bagisto fallback | Preserve the original core fallback behavior for legacy paths. |

The existing product route name is intentionally reused because Bagisto already calls it throughout the storefront. Although its name mentions categories, after installation it generates the package's product URLs. Category links should use `$category->url` or `shop.collection.show`.

The category controller passes `sort`, `limit`, and `mode` query values to the existing category view. Listing, filtering, pagination, product data, and page presentation remain responsibilities of Bagisto and the active theme.

### 5. Existing views render the pages

Controllers return `shop::categories.view` and `shop::products.view`; the package does not ship replacement product or category pages. Its small view layer contains the canonical partial and a default Shop category carousel adapter whose links use the collection route.

This repository contains the reusable package only. It does not include the VeraSole application, PacePair theme, store-specific headers and footers, homepage, assets, database contents, or application configuration.

## Behavior and integration

- `shop.product_or_category.index` generates prefixed product URLs, preserving existing product link calls.
- `shop.collection.show` generates prefixed category URLs; the substituted category model uses it for `$category->url`.
- The original Bagisto fallback remains available as `shop.slug.fallback`. Existing bare-slug URLs remain reachable; the package does not issue automatic redirects from them.
- Product pages require an enabled, individually visible product available in the current channel. Category pages require an enabled category within the current channel's category tree.
- Standard web, shop, maintenance, and response-cache middleware remain attached to the prefixed routes.
- The default category carousel uses the configured category route. Custom themes that build URLs directly from slugs must update those links themselves.

The package adds a canonical tag on its prefixed product and category pages. Set `canonical` to `false` if your theme or SEO extension supplies canonical tags, or list the relevant theme codes in `canonical_excluded_themes`. Existing legacy pages, sitemap implementations, URL rewrites, feeds, hardcoded links, and other extensions need review when migrating an established store. This package does not provide a complete SEO migration or guarantee search ranking changes.

Another extension replacing the Category model can conflict with this package's substitution. Integrate the URL accessor into a combined model when both extensions are needed. Switching URL prefixes on an established store also requires a redirect plan for previously indexed prefixed URLs.

## Using routes in your theme

Use the existing product route for products:

```blade
<a href="{{ route('shop.product_or_category.index', ['slug' => $product->url_key]) }}">
    {{ $product->name }}
</a>
```

Use the category URL accessor for categories:

```blade
<a href="{{ $category->url }}">
    {{ $category->name }}
</a>
```

Or generate a category URL explicitly:

```php
route('shop.collection.show', ['slug' => 'shoes']);
route('shop.collection.show', ['slug' => 'shoes', 'page' => 2]);
```

With default configuration these produce `/collection/shoes` and `/collection/shoes?page=2`, with the application URL prepended. Avoid hardcoding `/collection` or `/products`, so configuration changes continue to work.

For a theme that already emits canonical tags:

```php
'canonical' => false,
```

Or disable package canonical tags for selected theme codes:

```php
'canonical_excluded_themes' => ['custom-theme'],
```

The package canonical partial uses the current URL without query parameters. It does not redirect legacy URLs to prefixed URLs or establish canonical tags on the preserved legacy fallback. Review the canonical strategy for pagination, filters, and legacy pages with your theme or SEO integration.

## Migrating an existing store

1. Test installation on a staging copy running a supported Bagisto version.
2. Choose prefixes that do not overlap routes owned by your application or other extensions.
3. Check themes, menus, CMS content, feeds, emails, sitemap generation, integrations, and JavaScript for URLs built directly from slugs.
4. Configure canonical output so the theme and the package do not emit duplicate tags.
5. Plan redirects for old URLs where needed. Bare slugs remain reachable, but an old prefixed path is not automatically redirected when a prefix changes.
6. Clear application caches, rebuild deployment caches, and verify product and category requests across channels and locales.
7. Review generated links, query strings, visibility restrictions, and existing URL rewrite behavior before releasing.

The package introduces no schema migration and does not change stored product URL keys or category slugs. Existing rewrite records remain owned by Bagisto; the new prefixed controllers render their resolved entities directly rather than running the legacy fallback's entire rewrite pipeline.

## Troubleshooting

| Symptom | Checks |
| --- | --- |
| Route is missing after Composer installation | Run `php artisan package:discover`, inspect `extra.laravel.dont-discover`, then clear caches. The extension requires an installed Bagisto application with its Shop routes enabled. |
| Prefix changes have no effect | Confirm the published `config/storefront.php`, clear config and route caches, and rebuild the caches used by deployment. |
| A page returns 404 | Confirm the localized slug, enabled status, channel availability, and category tree membership. Products must also be individually visible. |
| Some links still use bare slugs | Update theme or integration code that concatenates a slug instead of using the named route or category URL accessor. |
| Duplicate canonical tags appear | Disable package canonical output globally or for the affected theme. |
| Category URLs revert after installing another extension | Check for a second Category contract substitution and combine the required model behavior. |
| Installation reports an invalid prefix | Use distinct, non-empty static prefixes; route parameters, spaces, repeated internal slashes, and punctuation outside the allowed characters are rejected. |
| Composer cannot find the package | Before Packagist publication, configure the GitHub VCS repository and require `dev-main`. The repository must be accessible to your Composer credentials. |
| Rendering tests skip | Configure a seeded dedicated database with a supported test database name; see [Tests](#tests). |

## Removing the package

Remove custom calls to `shop.collection.show` and other package-specific references from your application before uninstalling. Replace category links with Bagisto's supported URL generation, and decide how published prefixed URLs should redirect after removal.

```bash
composer remove verasole/bagisto-url-prefixes
php artisan optimize:clear
```

For an installation with discovery disabled, also remove the explicitly registered provider. Remove published `config/storefront.php` only if nothing else uses it, and rebuild deployment caches as needed. No database rollback is required.

## Local package development

Clone this package into `packages/Verasole/Storefront` in a Bagisto application. If the application already contains a path repository matching `packages/*/*`, use it; otherwise add one:

```bash
composer config repositories.bagisto-url-prefixes path packages/Verasole/Storefront
composer require verasole/bagisto-url-prefixes:dev-main
```

When migrating an existing manual installation, remove its duplicate `Verasole\Storefront\` application autoload mapping and manual provider entry after Composer manages the package. Keep existing published configuration. The source namespace and config filename deliberately remain compatible with the earlier local Storefront module.

## Tests

Tests run against a complete Bagisto application and its development dependencies. Installing this package does not register its tests in the consumer's test suite.

From the Bagisto application root, with this source checkout at `packages/Verasole/Storefront`:

```bash
vendor/bin/pest \
  --configuration=packages/Verasole/Storefront/phpunit.xml \
  --test-directory=packages/Verasole/Storefront/tests \
  --compact
```

For an external clone, set `BAGISTO_BASE_PATH` to the absolute application path and use the application's `vendor/bin/pest` executable from the application root. `--configuration` may be absolute, but Pest requires `--test-directory` to be relative to the application root. Alternatively, symlink the clone into `packages/Verasole/Storefront` and use the command above.

Routing and controller tests use mocks. Rendering tests require a migrated and seeded dedicated test database whose name ends in `_test` or `_testing` (optionally followed by a worker number); they skip otherwise. Never point rendering tests at a live store database. Configure the test database through the application's `.env.testing` or environment variables before running them.

The test case registers the provider before application providers to exercise Composer discovery ordering. Tests and test configuration are excluded from distribution archives; use a Git source checkout when contributing.

Style checks run with the application's Pint executable:

```bash
vendor/bin/pint --test packages/Verasole/Storefront/src packages/Verasole/Storefront/tests
composer validate --strict packages/Verasole/Storefront/composer.json
```

## Package layout

```text
composer.json                         Composer dependencies, autoloading, discovery
phpunit.xml                           Package-specific test suite
src/Config/storefront.php             Default configuration
src/Providers/StorefrontServiceProvider.php
src/Routes/web.php                    Prefixed storefront routes
src/Models/Category.php               Existing category model extension
src/Http/Controllers/                 Product and category page controllers
src/Resources/views/                  Carousel route adapter and canonical partial
tests/bootstrap.php                   Locate the host Bagisto application
tests/StorefrontTestCase.php           Exercise early provider registration
tests/Feature/                        Routing, configuration, controller, rendering tests
```

Runtime dependencies are declared in the package manifest. Bagisto itself supplies the Webkul modules; this extension is not designed to install those modules into a plain Laravel project. Development dependencies and a seeded application come from the host Bagisto checkout rather than from a separate standalone Testbench application.

## Contributing

Issues and pull requests are welcome once the repository is published. Include the Bagisto, PHP, and Laravel versions, active theme, configured prefixes, and a minimal reproduction. Run the package tests and Laravel Pint before proposing changes. Keep database access compatible with Bagisto's supported database engines and preserve channel visibility checks.

## License

MIT. See [LICENSE](LICENSE).
