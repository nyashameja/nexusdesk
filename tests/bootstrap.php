<?php

declare(strict_types=1);

/**
 * Test bootstrap. Registers the App\ and Tests\ autoloaders without booting the
 * full HTTP kernel, so unit tests run fast and DB-free.
 */

$root = dirname(__DIR__);

if (is_file($root . '/vendor/autoload.php')) {
    require $root . '/vendor/autoload.php';
}

require $root . '/app/Core/Autoloader.php';

$autoloader = new App\Core\Autoloader();
$autoloader->addNamespace('App', $root . '/app');
$autoloader->addNamespace('Tests', $root . '/tests');
$autoloader->register();

require $root . '/app/Support/helpers.php';
