<?php

use Illuminate\Http\Request;
use Illuminate\Routing\CompiledRouteCollection;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Verasole\Storefront\Http\Controllers\CollectionController;
use Verasole\Storefront\Models\Category;
use Verasole\Storefront\Tests\StorefrontTestCase;
use Webkul\Category\Contracts\Category as CategoryContract;
use Webkul\Category\Models\CategoryProxy;
use Webkul\Category\Models\CategoryTranslation;
use Webkul\Category\Repositories\CategoryRepository;
use Webkul\Core\Core;

uses(StorefrontTestCase::class);

// ============================================================================
// Route And Model Registration
// ============================================================================

it('registers the collection route ahead of the product fallback', function () {
    $routes = app('router')->getRoutes();
    $route = $routes->match(Request::create('/collection/shoes?sort=price-asc&page=2'));

    expect($route->getName())->toBe('shop.collection.show')
        ->and($route->getActionName())->toBe(CollectionController::class.'@show')
        ->and($route->gatherMiddleware())->toContain('web', 'shop', 'cache.response')
        ->and(route('shop.collection.show', ['slug' => 'shoes', 'page' => 2]))->toEndWith('/collection/shoes?page=2');
});

it('preserves legacy fallback access and existing concrete routes', function () {
    $routes = app('router')->getRoutes();

    expect($routes->match(Request::create('/shoe-product'))->getName())->toBe('shop.slug.fallback')
        ->and($routes->match(Request::create('/search'))->getName())->toBe('shop.search.index')
        ->and(route('shop.product_or_category.index', 'shoe-product'))->toEndWith('/products/shoe-product');
});

it('preserves prefixed routes and the fallback in the compiled route cache', function () {
    $compiled = app('router')->getRoutes()->compile();
    $routes = (new CompiledRouteCollection($compiled['compiled'], $compiled['attributes']))
        ->setRouter(app('router'))
        ->setContainer(app());

    expect($routes->match(Request::create('/collection/shoes'))->getName())->toBe('shop.collection.show')
        ->and($routes->match(Request::create('/products/shoe'))->getName())->toBe('shop.product_or_category.index')
        ->and($routes->match(Request::create('/shoe'))->getName())->toBe('shop.slug.fallback');
});

it('uses the configured prefix for category routing and localized URLs', function () {
    config(['storefront.category_prefix' => 'collections']);

    $routes = new RouteCollection;

    foreach (app('router')->getRoutes() as $route) {
        if (! in_array($route->getName(), ['shop.collection.show', 'shop.product_or_category.index'])) {
            $routes->add($route);
        }
    }

    app('router')->setRoutes($routes);

    require __DIR__.'/../../src/Routes/web.php';

    app('router')->getRoutes()->refreshNameLookups();
    app('url')->setRoutes(app('router')->getRoutes());

    $core = Mockery::mock(Core::class);
    $core->shouldReceive('getCurrentLocale')->andReturn((object) ['code' => 'fr']);
    $core->shouldReceive('getDefaultLocaleCodeFromDefaultChannel')->andReturn('en');
    Webkul\Core\Facades\Core::swap($core);

    $category = new Category;
    $category->setRelation('translations', collect([
        (new CategoryTranslation)->forceFill(['locale' => 'fr', 'slug' => 'chaussures']),
    ]));

    expect(app('router')->getRoutes()->match(Request::create('/collections/chaussures'))->getName())->toBe('shop.collection.show')
        ->and($category->url)->toEndWith('/collections/chaussures')
        ->and(route('shop.collection.show', ['slug' => 'chaussures', 'page' => 2]))->toEndWith('/collections/chaussures?page=2')
        ->and(route('shop.product_or_category.index', 'shoe-product'))->toEndWith('/products/shoe-product');
});

it('substitutes categories through Concord', function () {
    expect(app(CategoryContract::class))->toBeInstanceOf(Category::class)
        ->and(CategoryProxy::modelClass())->toBe(Category::class);
});

it('preserves the core category translation relationship', function () {
    expect((new Category)->translations()->getRelated())->toBeInstanceOf(CategoryTranslation::class);
});

it('uses the collection carousel for anonymous category card components', function () {
    $compiled = app('blade.compiler')->compileString('<x-shop::categories.carousel src="/categories" title="Collections" />');

    expect($compiled)->toContain('storefront::components.categories.carousel');
});

it('generates localized category URLs without changing the slug', function () {
    $core = Mockery::mock(Core::class);
    $core->shouldReceive('getCurrentLocale')->andReturn((object) ['code' => 'fr']);
    $core->shouldReceive('getDefaultLocaleCodeFromDefaultChannel')->andReturn('en');
    Webkul\Core\Facades\Core::swap($core);
    $category = new Category;
    $category->setRelation('translations', collect([
        (new CategoryTranslation)->forceFill(['locale' => 'fr', 'slug' => 'chaussures']),
    ]));

    expect($category->url)->toEndWith('/collection/chaussures')
        ->and($category->translate('fr')->slug)->toBe('chaussures');
});

it('returns 404 for an unknown collection without falling back to products', function () {
    $repository = Mockery::mock(CategoryRepository::class);
    $repository->shouldReceive('findBySlug')->once()->with('missing')->andReturnNull();
    (new CollectionController($repository))->show(Request::create('/collection/missing'), 'missing');
})->throws(HttpException::class);

// ============================================================================
// Isolated Storefront Rendering
// ============================================================================

beforeEach(function () {
    if (! str_contains($this->name(), 'isolated')) {
        return;
    }

    $database = config('database.connections.'.config('database.default').'.database');

    if (! preg_match('/(?:_test(?:ing)?(?:_\d+)?$|:memory:)/', (string) $database)) {
        $this->markTestSkipped('Rendering requires a seeded dedicated database ending in _test or _testing.');
    }

    DB::beginTransaction();
    $this->beforeApplicationDestroyed(fn () => DB::rollBack());
});

it('renders an isolated category with collection links and one canonical', function () {
    $root = core()->getCurrentChannel()->root_category;
    $locale = core()->getCurrentLocale()->code;
    $category = app(CategoryRepository::class)->create([
        'parent_id' => $root->id,
        'status' => 1,
        'position' => 1,
        $locale => ['name' => 'Collection Test', 'slug' => 'verasole-test-shoes', 'description' => 'Test collection'],
    ]);

    $response = $this->get('/collection/verasole-test-shoes?sort=name-asc&limit=12&mode=list');
    $response->assertOk()->assertViewIs('shop::categories.view')->assertSee('Collection Test');

    expect($category->url)->toEndWith('/collection/verasole-test-shoes')
        ->and(substr_count($response->getContent(), 'rel="canonical"'))->toBe(1);
    $response->assertSee('href="'.url('/collection/verasole-test-shoes').'"', false);

    $this->getJson(route('shop.api.categories.tree'))
        ->assertOk()->assertJsonFragment(['url' => $category->url]);
});

it('returns an isolated missing category as 404', function () {
    $this->get('/collection/verasole-missing-'.uniqid())->assertNotFound();
});
