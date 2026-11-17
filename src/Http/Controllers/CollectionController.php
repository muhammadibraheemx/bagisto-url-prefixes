<?php

namespace Verasole\Storefront\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Category\Repositories\CategoryRepository;

class CollectionController
{
    /**
     * Create the collection controller.
     */
    public function __construct(protected CategoryRepository $categoryRepository) {}

    /**
     * Render the existing category page within the current channel.
     */
    public function show(Request $request, string $slug): View
    {
        $category = $this->categoryRepository->findBySlug($slug);
        abort_unless($category, 404);

        $root = core()->getCurrentChannel()->root_category;

        abort_unless(
            $category->status
            && $root
            && $category->isSelfOrDescendantOf($root),
            404
        );

        return view('shop::categories.view', [
            'category' => $category,
            'params' => [
                'sort' => $request->query('sort'),
                'limit' => $request->query('limit'),
                'mode' => $request->query('mode'),
            ],
        ]);
    }
}
