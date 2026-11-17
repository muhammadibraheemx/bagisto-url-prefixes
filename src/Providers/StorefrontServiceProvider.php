<?php

namespace Verasole\Storefront\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;
use LogicException;
use Verasole\Storefront\Models\Category;
use Webkul\Category\Contracts\Category as CategoryContract;
use Webkul\Core\Http\Middleware\PreventRequestsDuringMaintenance;
use Webkul\Theme\ViewRenderEventManager;

class StorefrontServiceProvider extends ServiceProvider
{
    /**
     * Register the storefront configuration defaults.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../Config/storefront.php', 'storefront');
    }

    /**
     * Bootstrap the storefront extensions after the core providers.
     */
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../Config/storefront.php' => config_path('storefront.php'),
        ], 'verasole-storefront-config');

        $this->app->booted(function () {
            $this->registerStorefront();
        });
    }

    /**
     * Extend Bagisto after its routes, views and Concord models have loaded.
     */
    protected function registerStorefront(): void
    {
        $this->validatePrefixes();

        $this->app->concord->registerModel(CategoryContract::class, Category::class, false);

        $this->loadViewsFrom(__DIR__.'/../Resources/views', 'storefront');

        $this->app->make('view')->prependNamespace('shop', __DIR__.'/../Resources/views');

        Blade::component('storefront::components.categories.carousel', 'shop::categories.carousel');

        if (! $this->app->routesAreCached()) {
            Route::getRoutes()->refreshNameLookups();
            $fallback = Route::getRoutes()->getByName('shop.product_or_category.index');

            if (! $fallback) {
                throw new LogicException('Bagisto URL Prefixes requires the Bagisto shop routes.');
            }

            $fallback->setAction(array_replace($fallback->getAction(), ['as' => 'shop.slug.fallback']));

            Route::middleware(['web', 'shop', PreventRequestsDuringMaintenance::class, 'cache.response'])
                ->group(__DIR__.'/../Routes/web.php');

            Route::getRoutes()->refreshNameLookups();
        }

        Event::listen('bagisto.shop.layout.head.after', function (ViewRenderEventManager $manager) {
            if (
                request()->routeIs('shop.collection.show', 'shop.product_or_category.index')
                && config('storefront.canonical', true)
                && ! in_array(core()->getCurrentChannel()->theme, config('storefront.canonical_excluded_themes', []), true)
            ) {
                $manager->addTemplate('storefront::partials.canonical');
            }
        });
    }

    /**
     * Require distinct static URL prefixes so package routes cannot shadow one another.
     */
    protected function validatePrefixes(): void
    {
        $prefixes = [];

        foreach (['category_prefix', 'product_prefix'] as $key) {
            $value = config('storefront.'.$key);

            if (
                ! is_string($value)
                || ! preg_match('/^[a-z0-9]+(?:[-_\/][a-z0-9]+)*$/', trim($value, '/'))
            ) {
                throw new InvalidArgumentException('storefront.'.$key.' must be a non-empty static URL prefix.');
            }

            $prefixes[] = trim($value, '/');
        }

        if ($prefixes[0] === $prefixes[1]) {
            throw new InvalidArgumentException('The category and product URL prefixes must differ.');
        }
    }
}
