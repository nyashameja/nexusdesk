<?php

declare(strict_types=1);

use ParagonHostOps\Core\App;
use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Core\Csrf;
use ParagonHostOps\Core\Database;
use ParagonHostOps\Core\Env;
use ParagonHostOps\Core\Logger;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Router;
use ParagonHostOps\Core\Session;
use ParagonHostOps\Core\View;
use ParagonHostOps\Middleware\AuthMiddleware;
use ParagonHostOps\Middleware\GuestMiddleware;
use ParagonHostOps\Middleware\VerifyCsrfMiddleware;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\AuditLogRepository;
use ParagonHostOps\Repositories\LoginAttemptRepository;
use ParagonHostOps\Repositories\SettingsRepository;
use ParagonHostOps\Repositories\UserRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\ConnectionTester;
use ParagonHostOps\Services\Whm\CurlTransport;
use ParagonHostOps\Services\Whm\MockTransport;
use ParagonHostOps\Services\Whm\WhmApiClient;
use ParagonHostOps\Services\Whm\WhmTransportInterface;

/**
 * Application bootstrap.
 *
 * Loads the environment and configuration, wires the dependency-injection
 * container, starts the session and returns a ready-to-run App kernel.
 */

require __DIR__ . '/../vendor/autoload.php';

$root = dirname(__DIR__);

// --- Environment & configuration ---
Env::load($root . '/.env');
Config::load($root . '/config');

date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

$debug = (bool) Config::get('app.debug', false);
error_reporting($debug ? E_ALL : 0);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');

// --- Container ---
$container = new Container();
Container::setInstance($container);

$container->instanceValue(Container::class, $container);

$container->bind(Logger::class, static fn (): Logger => new Logger($root . '/storage/logs'));

$container->bind(Session::class, static function (): Session {
    $session = new Session((array) Config::get('app.session', []));
    $session->start();
    return $session;
});

$container->bind(Database::class, static fn (): Database => new Database((array) Config::get('database', [])));

$container->bind(Request::class, static fn (): Request => Request::capture());

$container->bind(View::class, static fn (): View => new View(__DIR__ . '/../app/Views'));

// --- Repositories ---
$container->bind(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(Database::class)));
$container->bind(LoginAttemptRepository::class, static fn (Container $c): LoginAttemptRepository => new LoginAttemptRepository($c->get(Database::class)));
$container->bind(AuditLogRepository::class, static fn (Container $c): AuditLogRepository => new AuditLogRepository($c->get(Database::class)));
$container->bind(SettingsRepository::class, static fn (Container $c): SettingsRepository => new SettingsRepository($c->get(Database::class)));
$container->bind(AccountRepository::class, static fn (Container $c): AccountRepository => new AccountRepository($c->get(Database::class)));

// --- Auth ---
$container->bind(Auth::class, static function (Container $c): Auth {
    return new Auth(
        $c->get(Session::class),
        $c->get(UserRepository::class),
        $c->get(LoginAttemptRepository::class),
        (int) Config::get('app.security.login_max_attempts', 5),
        (int) Config::get('app.security.login_lockout_minutes', 15),
    );
});

$container->bind(AuditLogger::class, static function (Container $c): AuditLogger {
    return new AuditLogger($c->get(AuditLogRepository::class), $c->get(Auth::class), $c->get(Request::class));
});

// --- WHM services ---
$container->bind(WhmTransportInterface::class, static function (): WhmTransportInterface {
    $whm = (array) Config::get('whm', []);
    if (!empty($whm['mock_mode'])) {
        return new MockTransport(dirname(__DIR__) . '/tests/fixtures/whm');
    }
    return new CurlTransport(
        (int) ($whm['timeout'] ?? 30),
        (int) ($whm['connect_timeout'] ?? 10),
        (bool) ($whm['verify_ssl'] ?? true),
    );
});

$container->bind(WhmApiClient::class, static function (Container $c): WhmApiClient {
    return new WhmApiClient(
        $c->get(WhmTransportInterface::class),
        (array) Config::get('whm', []),
        $c->get(Logger::class),
    );
});

$container->bind(ConnectionTester::class, static fn (Container $c): ConnectionTester => new ConnectionTester($c->get(WhmApiClient::class)));
$container->bind(CapabilityChecker::class, static fn (Container $c): CapabilityChecker => new CapabilityChecker($c->get(WhmApiClient::class)));

// --- Middleware ---
$container->bind(AuthMiddleware::class, static fn (Container $c): AuthMiddleware => new AuthMiddleware($c->get(Auth::class)));
$container->bind(GuestMiddleware::class, static fn (Container $c): GuestMiddleware => new GuestMiddleware($c->get(Auth::class)));
$container->bind(VerifyCsrfMiddleware::class, static fn (): VerifyCsrfMiddleware => new VerifyCsrfMiddleware());

// --- Controllers (constructor injection) ---
require __DIR__ . '/controllers.php';

// --- Session must be started before CSRF token generation ---
$container->get(Session::class);
Csrf::token();

// --- Router ---
$router = new Router($container);
(require __DIR__ . '/../routes/web.php')($router);
(require __DIR__ . '/../routes/api.php')($router);

return new App($container, $router);
