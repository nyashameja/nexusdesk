<?php

declare(strict_types=1);

/**
 * Application bootstrap. Wires autoloading, env, config, container, error
 * handling, view path, static service singletons, and routes; returns the
 * ready Kernel. Both public/index.php and the test suite boot through here.
 */

use App\Core\App;
use App\Core\Autoloader;
use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Env;
use App\Core\ErrorHandler;
use App\Core\Kernel;
use App\Core\Router;
use App\Core\View;
use App\Infrastructure\Logging\LoggerInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Services\Settings\Settings;

$basePath = dirname(__DIR__);

// --- Autoloading -----------------------------------------------------------
if (is_file($basePath . '/vendor/autoload.php')) {
    require $basePath . '/vendor/autoload.php';
}
if (!class_exists(Autoloader::class, false)) {
    require $basePath . '/app/Core/Autoloader.php';
    $autoloader = new Autoloader();
    $autoloader->addNamespace('App', $basePath . '/app');
    $autoloader->register();
}
if (!function_exists('app')) {
    require $basePath . '/app/Support/helpers.php';
}

// --- Environment & config --------------------------------------------------
Env::load($basePath . '/.env');

$config = new Config($basePath . '/config');
$config->loadAll();

date_default_timezone_set((string) $config->get('app.timezone', 'UTC'));

// --- Container -------------------------------------------------------------
$container = new Container();
$container->instance(Container::class, $container);
(require $basePath . '/config/services.php')($container);

App::boot($container, $config, $basePath);

// --- Error handling --------------------------------------------------------
$logger = $container->get(LoggerInterface::class);
(new ErrorHandler((bool) $config->get('app.debug', false), $logger))->register();

// --- Views -----------------------------------------------------------------
View::setViewPath($basePath . '/resources/views');
View::share('appName', (string) ($config->get('app.name', 'NexusDesk')));

// --- Runtime settings (loaded lazily; safe if DB not yet installed) --------
try {
    Settings::boot($container->get(SettingRepositoryInterface::class));
} catch (\Throwable) {
    // Settings unavailable before installation — helpers fall back to defaults.
}

// --- Router ----------------------------------------------------------------
$router = new Router();
$container->instance(Router::class, $router);
require $basePath . '/config/routes/web.php';
require $basePath . '/config/routes/api.php';

return new Kernel($container, $router);
