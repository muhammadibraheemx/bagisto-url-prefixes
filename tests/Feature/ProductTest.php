<?php

use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Verasole\Storefront\Http\Controllers\ProductController;
use Verasole\Storefront\Tests\StorefrontTestCase;
use Webkul\Product\Enums\SearchContextEnum;
use Webkul\Product\Models\Product;
use Webkul\Product\Repositories\ProductRepository;

uses(StorefrontTestCase::class);

// ============================================================================
// Product Routes And Rendering
// ============================================================================

it('uses the prefixed route for existing product links and keeps unique route names', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->match(Request::create('/products/test-shoe?quantity=2'));
    $names = collect($routes)->map(fn ($route) => $route->getName())->filter();

    expect($route->getName())->toBe('shop.product_or_category.index')
        ->and($route->getActionName())->toBe(ProductController::class.'@show')
        ->and($route->gatherMiddleware())->toContain('web', 'shop', 'cache.response')
        ->and(route('shop.product_or_category.index', ['slug' => 'test-shoe', 'quantity' => 2]))->toEndWith('/products/test-shoe?quantity=2')
        ->and($names->duplicates()->all())->toBe([])
        ->and($routes->match(Request::create('/collection/shoes'))->getName())->toBe('shop.collection.show');
});

it('uses the configured product prefix in all existing named product links', function () {
    config(['storefront.product_prefix' => 'items']);
    $routes = new RouteCollection;

    foreach (app('router')->getRoutes() as $route) {
        if (! in_array($route->getName(), ['shop.collection.show', 'shop.product_or_category.index'])) {
            $routes->add($route);
        }
    }

    app('router')->setRoutes($routes);

    require __DIR__.'/../../src/Routes/web.php';

    $routes->refreshNameLookups();
    app('url')->setRoutes($routes);

    expect($routes->match(Request::create('/items/chaussure'))->getName())->toBe('shop.product_or_category.index')
        ->and(route('shop.product_or_category.index', 'chaussure'))->toEndWith('/items/chaussure');
});

it('renders the existing product view with the storefront product', function () {
    $product = Mockery::mock(Product::class)->makePartial();
    $product->setRawAttributes(['url_key' => 'test-shoe', 'visible_individually' => 1, 'status' => 1]);
    $product->shouldReceive('isAvailableInChannel')->once()->andReturnTrue();
    $repository = Mockery::mock(ProductRepository::class);
    $repository->shouldReceive('setSearchContext')->once()->with(SearchContextEnum::STOREFRONT)->andReturnSelf();
    $repository->shouldReceive('findBySlug')->once()->with('test-shoe')->andReturn($product);

    $view = (new ProductController($repository))->show('test-shoe');

    expect($view->name())->toBe('shop::products.view')
        ->and($view->getData()['product'])->toBe($product);
});

it('rejects missing disabled hidden and unavailable products', function (string $state) {
    $product = null;

    if ($state !== 'missing') {
        $product = Mockery::mock(Product::class)->makePartial();
        $product->setRawAttributes([
            'url_key' => 'test-shoe',
            'visible_individually' => $state !== 'hidden',
            'status' => $state !== 'disabled',
        ]);
        $product->shouldReceive('isAvailableInChannel')->andReturn(false);
    }

    $repository = Mockery::mock(ProductRepository::class);
    $repository->shouldReceive('setSearchContext')->once()->with(SearchContextEnum::STOREFRONT)->andReturnSelf();
    $repository->shouldReceive('findBySlug')->once()->with('test-shoe')->andReturn($product);

    try {
        (new ProductController($repository))->show('test-shoe');
        test()->fail('Expected a product 404.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }
})->with(['missing', 'disabled', 'hidden', 'unavailable']);
