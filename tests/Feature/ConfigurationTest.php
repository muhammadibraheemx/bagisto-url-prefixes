<?php

use Illuminate\Support\Facades\Event;
use Verasole\Storefront\Providers\StorefrontServiceProvider;
use Verasole\Storefront\Tests\StorefrontTestCase;
use Webkul\Shop\Providers\ShopServiceProvider;
use Webkul\Theme\ViewRenderEventManager;

uses(StorefrontTestCase::class);

// ============================================================================
// Configuration And Theme Integration
// ============================================================================

it('rejects ambiguous or dynamic route prefixes', function (array $prefixes) {
    config($prefixes);

    (new StorefrontServiceProvider(app()))->boot();
})->with([
    'empty' => [['storefront.category_prefix' => '']],
    'dynamic' => [['storefront.category_prefix' => '{category}']],
    'identical' => [['storefront.category_prefix' => 'products']],
])->throws(InvalidArgumentException::class);

it('lets custom themes disable package canonical output', function () {
    config(['storefront.canonical' => false]);

    $route = app('router')->getRoutes()->getByName('shop.collection.show');
    request()->setRouteResolver(fn () => $route);

    $manager = Mockery::mock(ViewRenderEventManager::class);
    $manager->shouldNotReceive('addTemplate');

    $listeners = Event::getRawListeners()['bagisto.shop.layout.head.after'];
    end($listeners)($manager);
});

it('publishes configuration without requiring application provider edits', function () {
    expect(StorefrontServiceProvider::pathsToPublish(StorefrontServiceProvider::class, 'verasole-storefront-config'))
        ->toHaveCount(1);

    $providers = array_keys(app()->getLoadedProviders());

    expect(array_search(StorefrontServiceProvider::class, $providers, true))
        ->toBeLessThan(array_search(ShopServiceProvider::class, $providers, true));
});
