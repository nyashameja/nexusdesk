<?php

declare(strict_types=1);

use ParagonHostOps\Core\Config;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Core\Database;
use ParagonHostOps\Core\Env;
use ParagonHostOps\Core\Logger;
use ParagonHostOps\Core\Request;
use ParagonHostOps\Core\Session;
use ParagonHostOps\Core\View;
use ParagonHostOps\Middleware\AuthMiddleware;
use ParagonHostOps\Middleware\GuestMiddleware;
use ParagonHostOps\Middleware\VerifyCsrfMiddleware;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\AuditLogRepository;
use ParagonHostOps\Repositories\CapabilityRepository;
use ParagonHostOps\Repositories\ClientRepository;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Repositories\FinancialRepository;
use ParagonHostOps\Repositories\HealthRepository;
use ParagonHostOps\Repositories\AlertRepository;
use ParagonHostOps\Repositories\LoginAttemptRepository;
use ParagonHostOps\Repositories\NotificationRepository;
use ParagonHostOps\Repositories\ReportRepository;
use ParagonHostOps\Repositories\SecurityRepository;
use ParagonHostOps\Repositories\UptimeRepository;
use ParagonHostOps\Repositories\WordpressRepository;
use ParagonHostOps\Repositories\PackageRepository;
use ParagonHostOps\Repositories\ServerRepository;
use ParagonHostOps\Repositories\SettingsRepository;
use ParagonHostOps\Repositories\SslRepository;
use ParagonHostOps\Repositories\SyncRepository;
use ParagonHostOps\Repositories\UserRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Services\Alerts\AlertService;
use ParagonHostOps\Services\Alerts\EmailAlertChannel;
use ParagonHostOps\Services\Alerts\Mailer;
use ParagonHostOps\Services\Alerts\TelegramAlertChannel;
use ParagonHostOps\Services\Alerts\TelegramMessageFormatter;
use ParagonHostOps\Services\Domains\DomainExpiryChecker;
use ParagonHostOps\Services\Domains\DomainExpiryService;
use ParagonHostOps\Services\HealthScoreService;
use ParagonHostOps\Services\Uptime\UptimeChecker;
use ParagonHostOps\Services\Uptime\UptimeService;
use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\ConnectionTester;
use ParagonHostOps\Services\Whm\CurlTransport;
use ParagonHostOps\Services\Whm\MockTransport;
use ParagonHostOps\Services\Whm\SyncService;
use ParagonHostOps\Services\Whm\WhmApiClient;
use ParagonHostOps\Services\Whm\WhmNormalizer;
use ParagonHostOps\Services\Whm\WhmTransportInterface;

/**
 * Builds and returns the dependency-injection container.
 *
 * This is deliberately free of any web-specific side effects (no session
 * start, no headers, no routing) so it can be reused by CLI scripts and cron
 * endpoints as well as the HTTP front controller.
 *
 * @return Container
 */

require __DIR__ . '/autoload.php';

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
$container->bind(View::class, static fn (): View => new View($root . '/app/Views'));

// --- Repositories ---
$container->bind(UserRepository::class, static fn (Container $c): UserRepository => new UserRepository($c->get(Database::class)));
$container->bind(LoginAttemptRepository::class, static fn (Container $c): LoginAttemptRepository => new LoginAttemptRepository($c->get(Database::class)));
$container->bind(AuditLogRepository::class, static fn (Container $c): AuditLogRepository => new AuditLogRepository($c->get(Database::class)));
$container->bind(SettingsRepository::class, static fn (Container $c): SettingsRepository => new SettingsRepository($c->get(Database::class)));
$container->bind(AccountRepository::class, static fn (Container $c): AccountRepository => new AccountRepository($c->get(Database::class)));
$container->bind(ClientRepository::class, static fn (Container $c): ClientRepository => new ClientRepository($c->get(Database::class)));
$container->bind(DomainRepository::class, static fn (Container $c): DomainRepository => new DomainRepository($c->get(Database::class)));
$container->bind(FinancialRepository::class, static fn (Container $c): FinancialRepository => new FinancialRepository($c->get(Database::class)));
$container->bind(WordpressRepository::class, static fn (Container $c): WordpressRepository => new WordpressRepository($c->get(Database::class)));
$container->bind(HealthRepository::class, static fn (Container $c): HealthRepository => new HealthRepository($c->get(Database::class)));
$container->bind(UptimeRepository::class, static fn (Container $c): UptimeRepository => new UptimeRepository($c->get(Database::class)));
$container->bind(NotificationRepository::class, static fn (Container $c): NotificationRepository => new NotificationRepository($c->get(Database::class)));
$container->bind(SecurityRepository::class, static fn (Container $c): SecurityRepository => new SecurityRepository($c->get(Database::class)));
$container->bind(ReportRepository::class, static fn (Container $c): ReportRepository => new ReportRepository($c->get(Database::class)));
$container->bind(ServerRepository::class, static fn (Container $c): ServerRepository => new ServerRepository($c->get(Database::class)));
$container->bind(PackageRepository::class, static fn (Container $c): PackageRepository => new PackageRepository($c->get(Database::class)));
$container->bind(SslRepository::class, static fn (Container $c): SslRepository => new SslRepository($c->get(Database::class)));
$container->bind(CapabilityRepository::class, static fn (Container $c): CapabilityRepository => new CapabilityRepository($c->get(Database::class)));
$container->bind(SyncRepository::class, static fn (Container $c): SyncRepository => new SyncRepository($c->get(Database::class)));

// --- Auth & audit ---
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

$container->bind(HealthScoreService::class, static fn (): HealthScoreService => new HealthScoreService());

$container->bind(AlertRepository::class, static fn (Container $c): AlertRepository => new AlertRepository($c->get(Database::class)));
$container->bind(Mailer::class, static fn (): Mailer => new Mailer());
$container->bind(EmailAlertChannel::class, static function (Container $c): EmailAlertChannel {
    return new EmailAlertChannel(
        $c->get(Mailer::class),
        (array) Config::get('alerts.email', []),
        (string) (parse_url((string) Config::get('app.url', ''), PHP_URL_HOST) ?: 'localhost'),
    );
});
$container->bind(TelegramMessageFormatter::class, static function (): TelegramMessageFormatter {
    return new TelegramMessageFormatter(
        (string) Config::get('app.url', ''),
        (string) Config::get('whm.host', '') ?: null,
    );
});
$container->bind(TelegramAlertChannel::class, static function (Container $c): TelegramAlertChannel {
    return new TelegramAlertChannel(
        (array) Config::get('alerts.telegram', []),
        null,
        $c->get(Logger::class),
        $c->get(TelegramMessageFormatter::class),
    );
});

// Shared channel list, reused by the alert service and the settings page.
$container->bind('alert.channels', static function (Container $c): array {
    return [
        $c->get(EmailAlertChannel::class),
        $c->get(TelegramAlertChannel::class),
    ];
});

$container->bind(AlertService::class, static function (Container $c): AlertService {
    return new AlertService(
        $c->get(AlertRepository::class),
        $c->get(NotificationRepository::class),
        $c->get('alert.channels'),
        (bool) Config::get('alerts.enabled', false),
    );
});

$container->bind(DomainExpiryChecker::class, static fn (): DomainExpiryChecker => new DomainExpiryChecker());
$container->bind(DomainExpiryService::class, static fn (Container $c): DomainExpiryService => new DomainExpiryService(
    $c->get(DomainRepository::class),
    $c->get(DomainExpiryChecker::class),
    $c->get(NotificationRepository::class),
));

$container->bind(UptimeChecker::class, static fn (): UptimeChecker => new UptimeChecker());
$container->bind(UptimeService::class, static fn (Container $c): UptimeService => new UptimeService(
    $c->get(UptimeRepository::class),
    $c->get(UptimeChecker::class),
    $c->get(NotificationRepository::class),
));

// --- WHM services ---
$container->bind(WhmTransportInterface::class, static function () use ($root): WhmTransportInterface {
    $whm = (array) Config::get('whm', []);
    if (!empty($whm['mock_mode'])) {
        return new MockTransport($root . '/tests/fixtures/whm');
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
$container->bind(WhmNormalizer::class, static fn (): WhmNormalizer => new WhmNormalizer());

$container->bind(SyncService::class, static function (Container $c): SyncService {
    return new SyncService(
        $c->get(WhmApiClient::class),
        $c->get(WhmNormalizer::class),
        $c->get(ServerRepository::class),
        $c->get(AccountRepository::class),
        $c->get(PackageRepository::class),
        $c->get(SslRepository::class),
        $c->get(CapabilityRepository::class),
        $c->get(SyncRepository::class),
        $c->get(Logger::class),
        (string) Config::get('whm.host', ''),
    );
});

// --- Middleware ---
$container->bind(AuthMiddleware::class, static fn (Container $c): AuthMiddleware => new AuthMiddleware($c->get(Auth::class)));
$container->bind(GuestMiddleware::class, static fn (Container $c): GuestMiddleware => new GuestMiddleware($c->get(Auth::class)));
$container->bind(VerifyCsrfMiddleware::class, static fn (): VerifyCsrfMiddleware => new VerifyCsrfMiddleware());

// --- Controllers ---
require __DIR__ . '/controllers.php';

return $container;
