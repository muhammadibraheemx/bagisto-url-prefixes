<?php

namespace Verasole\Storefront\Http\Controllers;

use Illuminate\View\View;
use Webkul\Product\Enums\SearchContextEnum;
use Webkul\Product\Repositories\ProductRepository;

class ProductController
{
    /**
     * Create the storefront product controller.
     */
    public function __construct(protected ProductRepository $productRepository) {}

    /**
     * Render the existing product page for a visible product in the current channel.
     */
    public function show(string $slug): View
    {
        $product = $this->productRepository
            ->setSearchContext(SearchContextEnum::STOREFRONT)
            ->findBySlug($slug);

        abort_unless(
            $product
            && $product->url_key
            && $product->visible_individually
            && $product->status
            && $product->isAvailableInChannel(),
            404
        );

        return view('shop::products.view', compact('product'));
    }
}
