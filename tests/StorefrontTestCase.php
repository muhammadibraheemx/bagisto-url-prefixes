<?php

namespace Verasole\Storefront\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\TestCase;
use Verasole\Storefront\Providers\StorefrontServiceProvider;

class StorefrontTestCase extends TestCase
{
    /**
     * Register the package before application providers, as Composer discovery does.
     */
    public function createApplication()
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';

        $this->traitsUsedByTest = class_uses_recursive(static::class);

        $app->beforeBootstrapping(RegisterProviders::class, function ($app) {
            $app->register(StorefrontServiceProvider::class);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
