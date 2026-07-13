<?php

declare(strict_types=1);

use App\Core\Container;
use App\Core\Database;

/**
 * Container bindings — the single seam where interfaces map to implementations.
 * Swapping a provider (mailer, AI, cache, a repository backend) happens here
 * with no changes to the code that depends on the interface.
 *
 * @return callable(Container):void
 */
return function (Container $container): void {
    // --- Infrastructure -----------------------------------------------------
    $container->singleton(Database::class, function (): Database {
        return new Database(require dirname(__DIR__) . '/config/database.php');
    });

    $container->singleton(
        \App\Infrastructure\Logging\LoggerInterface::class,
        fn (): \App\Infrastructure\Logging\LoggerInterface =>
            new \App\Infrastructure\Logging\FileLogger(dirname(__DIR__) . '/storage/logs', 'app')
    );

    $container->singleton(
        \App\Infrastructure\Mail\MailerInterface::class,
        fn (Container $c): \App\Infrastructure\Mail\MailerInterface =>
            new \App\Infrastructure\Mail\PhpMailerMailer(
                require dirname(__DIR__) . '/config/mail.php',
                $c->get(\App\Infrastructure\Logging\LoggerInterface::class)
            )
    );

    // --- AI provider (null driver now; real provider wired later) -----------
    $container->singleton(
        \App\Integrations\Ai\AiProviderInterface::class,
        fn (): \App\Integrations\Ai\AiProviderInterface => new \App\Integrations\Ai\NullAiProvider()
    );

    // --- Backups ------------------------------------------------------------
    $container->singleton(
        \App\Services\Backup\BackupService::class,
        fn (Container $c): \App\Services\Backup\BackupService =>
            new \App\Services\Backup\BackupService(
                $c->get(Database::class),
                dirname(__DIR__) . '/storage/backups'
            )
    );

    // --- Zoho Books integration --------------------------------------------
    $container->singleton(
        \App\Integrations\Zoho\ZohoBooksClientInterface::class,
        fn (Container $c): \App\Integrations\Zoho\ZohoBooksClientInterface =>
            new \App\Integrations\Zoho\ZohoBooksClient(
                require dirname(__DIR__) . '/config/zoho.php',
                $c->get(\App\Infrastructure\Http\HttpClient::class),
                $c->get(\App\Repositories\Contracts\ZohoRepositoryInterface::class),
                $c->get(\App\Infrastructure\Logging\LoggerInterface::class),
            )
    );

    // --- Repositories (interface => MySQL implementation) -------------------
    $repositories = [
        \App\Repositories\Contracts\UserRepositoryInterface::class        => \App\Repositories\MySql\MySqlUserRepository::class,
        \App\Repositories\Contracts\RoleRepositoryInterface::class        => \App\Repositories\MySql\MySqlRoleRepository::class,
        \App\Repositories\Contracts\DepartmentRepositoryInterface::class  => \App\Repositories\MySql\MySqlDepartmentRepository::class,
        \App\Repositories\Contracts\SettingRepositoryInterface::class     => \App\Repositories\MySql\MySqlSettingRepository::class,
        \App\Repositories\Contracts\TicketRepositoryInterface::class      => \App\Repositories\MySql\MySqlTicketRepository::class,
        \App\Repositories\Contracts\LookupRepositoryInterface::class      => \App\Repositories\MySql\MySqlLookupRepository::class,
        \App\Repositories\Contracts\AuditRepositoryInterface::class       => \App\Repositories\MySql\MySqlAuditRepository::class,
        \App\Repositories\Contracts\NotificationRepositoryInterface::class => \App\Repositories\MySql\MySqlNotificationRepository::class,
        \App\Repositories\Contracts\CompanyRepositoryInterface::class     => \App\Repositories\MySql\MySqlCompanyRepository::class,
        \App\Repositories\Contracts\KbRepositoryInterface::class          => \App\Repositories\MySql\MySqlKbRepository::class,
        \App\Repositories\Contracts\EmailTemplateRepositoryInterface::class => \App\Repositories\MySql\MySqlEmailTemplateRepository::class,
        \App\Repositories\Contracts\JobRepositoryInterface::class         => \App\Repositories\MySql\MySqlJobRepository::class,
        \App\Repositories\Contracts\SearchRepositoryInterface::class      => \App\Repositories\MySql\MySqlSearchRepository::class,
        \App\Repositories\Contracts\ZohoRepositoryInterface::class        => \App\Repositories\MySql\MySqlZohoRepository::class,
        \App\Repositories\Contracts\ReportRepositoryInterface::class      => \App\Repositories\MySql\MySqlReportRepository::class,
    ];
    foreach ($repositories as $interface => $implementation) {
        $container->singleton($interface, function (Container $c) use ($implementation) {
            return $c->build($implementation);
        });
    }
};
