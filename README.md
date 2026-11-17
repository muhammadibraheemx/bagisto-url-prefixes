# Bagisto URL Prefixes

A small Bagisto module for configurable category and product URL prefixes.

| Page | Default URL |
| --- | --- |
| Category | `/collection/shoes` |
| Product | `/products/running-shoe` |

Uses Bagisto's existing pages and translated slugs. Requires Bagisto 2.5, Laravel 13, and PHP 8.4+.

## Install by cloning

Run from your Bagisto application root:

```bash
git clone https://github.com/muhammadibraheemx/bagisto-url-prefixes.git packages/Verasole/Storefront
```

Add this mapping to the application's `composer.json` under `autoload.psr-4`, alongside the existing mappings:

```json
"Verasole\\Storefront\\": "packages/Verasole/Storefront/src/"
```

Add the provider to the end of the array in `bootstrap/providers.php`:

```php
Verasole\Storefront\Providers\StorefrontServiceProvider::class,
```

Then run:

```bash
composer dump-autoload
php artisan vendor:publish --tag=verasole-storefront-config
php artisan optimize:clear
```

No migrations, npm installation, or asset compilation are needed. The module follows Bagisto's [package structure and service-provider workflow](https://bagisto.com/en/step-by-step-guide-for-bagisto-module-development/), using `src/Config`, `src/Providers`, `src/Routes`, `src/Http/Controllers`, and `src/Models`.

## Configure

Edit the prefixes in `config/storefront.php`:

```php
'category_prefix' => 'collections',
'product_prefix' => 'items',
```

These settings produce `/collections/shoes` and `/items/running-shoe`. Use different, non-empty static prefixes that do not conflict with other routes. The same prefixes apply across channels and locales; stored slugs are unchanged.

After changing configuration, run `php artisan optimize:clear`. Rebuild the route cache if your deployment uses it.

## Update

For a cloned installation:

```bash
git -C packages/Verasole/Storefront pull --ff-only origin main
composer dump-autoload
php artisan optimize:clear
```

Keep your configuration in the application's published `config/storefront.php`; pulling updates will not overwrite it. Commit or stash changes inside the clone before updating.

## How it works

The service provider loads the configuration and prefixed routes after Bagisto boots. A small Category model extension generates category URLs through `shop.collection.show`. Product links keep using Bagisto's existing `shop.product_or_category.index` route name, which now generates prefixed product URLs.

In custom themes, use `$category->url` for category links and `route('shop.product_or_category.index', ['slug' => $product->url_key])` for product links. The included default category carousel uses the category route.

Existing bare-slug URLs remain accessible through Bagisto's fallback; this module does not redirect them. Products and categories must be visible in the current channel. If your theme already adds canonical tags, set `'canonical' => false` in `config/storefront.php`. Another extension replacing Bagisto's Category model may require integration.

## Composer installation

Alternatively, install directly from GitHub without cloning or adding the manual autoload mapping and provider entry:

```bash
composer config repositories.bagisto-url-prefixes vcs https://github.com/muhammadibraheemx/bagisto-url-prefixes
composer require muhammadibraheemx/bagisto-url-prefixes:dev-main
php artisan vendor:publish --tag=verasole-storefront-config
php artisan optimize:clear
```

Update a Composer installation with `composer update muhammadibraheemx/bagisto-url-prefixes`, then clear caches. Choose one installation method. The package is not yet published on Packagist.

## Tests

From the Bagisto root, for a cloned installation:

```bash
vendor/bin/pest --configuration=packages/Verasole/Storefront/phpunit.xml --test-directory=packages/Verasole/Storefront/tests --compact
```

Rendering tests require a migrated, seeded test database ending in `_test` or `_testing`; they skip otherwise. Do not use a live store database. No entry in the application's `phpunit.xml` is needed.

## License

MIT. See [LICENSE](LICENSE).
