<?php

use Composer\Autoload\ClassLoader;

$bagistoPath = getenv('BAGISTO_BASE_PATH') ?: dirname(__DIR__, 4);

if (! is_file($bagistoPath.'/bootstrap/app.php')) {
    throw new RuntimeException('Set BAGISTO_BASE_PATH to an installed Bagisto 2.5 application.');
}

require $bagistoPath.'/vendor/autoload.php';

$loader = new ClassLoader;
$loader->addPsr4('Verasole\\Storefront\\', dirname(__DIR__).'/src/', true);
$loader->addPsr4('Verasole\\Storefront\\Tests\\', __DIR__.'/', true);
$loader->register(true);

$_ENV['APP_BASE_PATH'] = $_SERVER['APP_BASE_PATH'] = $bagistoPath;
putenv('APP_BASE_PATH='.$bagistoPath);
