<?php

namespace Verasole\Storefront\Models;

use Webkul\Category\Models\Category as BaseCategory;
use Webkul\Category\Models\CategoryTranslationProxy;

class Category extends BaseCategory
{
    /**
     * Preserve the core translation model when substituting the category.
     */
    public function getTranslationModelName(): string
    {
        return CategoryTranslationProxy::modelClass();
    }

    /**
     * Generate the collection URL using the existing translation fallback.
     */
    public function getUrlAttribute()
    {
        $translation = $this->translate(core()->getCurrentLocale()->code)
            ?? $this->translate(core()->getDefaultLocaleCodeFromDefaultChannel());

        return $translation?->slug
            ? route('shop.collection.show', ['slug' => $translation->slug])
            : url('/');
    }
}
