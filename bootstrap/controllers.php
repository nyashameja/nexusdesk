<?php

declare(strict_types=1);

use ParagonHostOps\Controllers\AccountsController;
use ParagonHostOps\Controllers\AuthController;
use ParagonHostOps\Controllers\ClientsController;
use ParagonHostOps\Controllers\DashboardController;
use ParagonHostOps\Controllers\DomainsController;
use ParagonHostOps\Controllers\AuditController;
use ParagonHostOps\Controllers\EmailController;
use ParagonHostOps\Controllers\FinanceController;
use ParagonHostOps\Controllers\HealthController;
use ParagonHostOps\Controllers\NotificationsController;
use ParagonHostOps\Controllers\ReportsController;
use ParagonHostOps\Controllers\SecurityController;
use ParagonHostOps\Controllers\SettingsController;
use ParagonHostOps\Controllers\SslController;
use ParagonHostOps\Controllers\SyncController;
use ParagonHostOps\Controllers\UptimeController;
use ParagonHostOps\Controllers\WordpressController;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\AuditLogRepository;
use ParagonHostOps\Repositories\CapabilityRepository;
use ParagonHostOps\Repositories\ClientRepository;
use ParagonHostOps\Repositories\DomainRepository;
use ParagonHostOps\Repositories\FinancialRepository;
use ParagonHostOps\Repositories\HealthRepository;
use ParagonHostOps\Repositories\NotificationRepository;
use ParagonHostOps\Repositories\ReportRepository;
use ParagonHostOps\Repositories\SecurityRepository;
use ParagonHostOps\Repositories\SslRepository;
use ParagonHostOps\Repositories\SyncRepository;
use ParagonHostOps\Repositories\UptimeRepository;
use ParagonHostOps\Repositories\WordpressRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;
use ParagonHostOps\Services\HealthScoreService;
use ParagonHostOps\Services\Uptime\UptimeService;
use ParagonHostOps\Services\Whm\CapabilityChecker;
use ParagonHostOps\Services\Whm\ConnectionTester;
use ParagonHostOps\Services\Whm\SyncService;
use ParagonHostOps\Services\Whm\WhmApiClient;

/**
 * Controller container bindings. Kept separate to keep bootstrap/app.php lean.
 *
 * @var Container $container
 */

$container->bind(AuthController::class, static fn (Container $c): AuthController => new AuthController(
    $c->get(Auth::class),
    $c->get(AuditLogger::class),
));

$container->bind(DashboardController::class, static fn (Container $c): DashboardController => new DashboardController(
    $c->get(AccountRepository::class),
    $c->get(DomainRepository::class),
    $c->get(WhmApiClient::class),
    $c->get(Auth::class),
));

$container->bind(SettingsController::class, static fn (Container $c): SettingsController => new SettingsController(
    $c->get(WhmApiClient::class),
    $c->get(ConnectionTester::class),
    $c->get(CapabilityChecker::class),
    $c->get(AuditLogger::class),
));

$container->bind(AccountsController::class, static fn (Container $c): AccountsController => new AccountsController(
    $c->get(AccountRepository::class),
    $c->get(SyncService::class),
    $c->get(AuditLogger::class),
));

$container->bind(SyncController::class, static fn (Container $c): SyncController => new SyncController(
    $c->get(SyncService::class),
    $c->get(SyncRepository::class),
    $c->get(CapabilityRepository::class),
    $c->get(WhmApiClient::class),
    $c->get(AuditLogger::class),
));

$container->bind(ClientsController::class, static fn (Container $c): ClientsController => new ClientsController(
    $c->get(ClientRepository::class),
    $c->get(AuditLogger::class),
    $c->get(Auth::class),
));

$container->bind(DomainsController::class, static fn (Container $c): DomainsController => new DomainsController(
    $c->get(DomainRepository::class),
    $c->get(\ParagonHostOps\Services\Domains\DomainExpiryService::class),
    $c->get(AuditLogger::class),
));

$container->bind(SslController::class, static fn (Container $c): SslController => new SslController(
    $c->get(SslRepository::class),
));

$container->bind(EmailController::class, static fn (Container $c): EmailController => new EmailController(
    $c->get(AccountRepository::class),
));

$container->bind(WordpressController::class, static fn (Container $c): WordpressController => new WordpressController(
    $c->get(WordpressRepository::class),
    $c->get(AuditLogger::class),
));

$container->bind(FinanceController::class, static fn (Container $c): FinanceController => new FinanceController(
    $c->get(FinancialRepository::class),
    $c->get(ClientRepository::class),
    $c->get(DomainRepository::class),
    $c->get(AuditLogger::class),
));

$container->bind(HealthController::class, static fn (Container $c): HealthController => new HealthController(
    $c->get(HealthRepository::class),
    $c->get(HealthScoreService::class),
    $c->get(AuditLogger::class),
));

$container->bind(UptimeController::class, static fn (Container $c): UptimeController => new UptimeController(
    $c->get(UptimeRepository::class),
    $c->get(UptimeService::class),
    $c->get(DomainRepository::class),
    $c->get(AuditLogger::class),
));

$container->bind(SecurityController::class, static fn (Container $c): SecurityController => new SecurityController(
    $c->get(SecurityRepository::class),
    $c->get(AuditLogRepository::class),
));

$container->bind(NotificationsController::class, static fn (Container $c): NotificationsController => new NotificationsController(
    $c->get(NotificationRepository::class),
));

$container->bind(ReportsController::class, static fn (Container $c): ReportsController => new ReportsController(
    $c->get(ReportRepository::class),
    $c->get(Auth::class),
    $c->get(AuditLogger::class),
));

$container->bind(AuditController::class, static fn (Container $c): AuditController => new AuditController(
    $c->get(AuditLogRepository::class),
));

$container->bind(\ParagonHostOps\Controllers\AlertsController::class, static fn (Container $c): \ParagonHostOps\Controllers\AlertsController => new \ParagonHostOps\Controllers\AlertsController(
    $c->get(\ParagonHostOps\Services\Alerts\AlertService::class),
    $c->get(\ParagonHostOps\Services\Alerts\EmailAlertChannel::class),
    $c->get(AuditLogger::class),
));
