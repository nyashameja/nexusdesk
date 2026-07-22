<?php

declare(strict_types=1);

use ParagonHostOps\Controllers\AccountsController;
use ParagonHostOps\Controllers\AuthController;
use ParagonHostOps\Controllers\DashboardController;
use ParagonHostOps\Controllers\SettingsController;
use ParagonHostOps\Controllers\SyncController;
use ParagonHostOps\Core\Container;
use ParagonHostOps\Repositories\AccountRepository;
use ParagonHostOps\Repositories\CapabilityRepository;
use ParagonHostOps\Repositories\SyncRepository;
use ParagonHostOps\Services\AuditLogger;
use ParagonHostOps\Services\Auth;
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
    $c->get(WhmApiClient::class),
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
