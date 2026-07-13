<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\Web\HomeController;
use App\Controllers\Web\Auth\LoginController;
use App\Controllers\Web\Auth\PasswordController;
use App\Controllers\Web\Guest\TicketController as GuestTicketController;
use App\Controllers\Web\Desk\DashboardController as DeskDashboard;
use App\Controllers\Web\Desk\TicketController as DeskTicketController;
use App\Controllers\Web\Portal\DashboardController as PortalDashboard;
use App\Controllers\Web\Portal\TicketController as PortalTicketController;
use App\Controllers\Web\Admin\DashboardController as AdminDashboard;
use App\Controllers\Web\Admin\UserController as AdminUsers;
use App\Controllers\Web\Admin\DepartmentController as AdminDepartments;
use App\Controllers\Web\Admin\SettingController as AdminSettings;

use App\Middleware\StartSessionMiddleware;
use App\Middleware\LoadUserMiddleware;
use App\Middleware\VerifyCsrfMiddleware;
use App\Middleware\AuthenticateMiddleware;
use App\Middleware\RedirectIfAuthenticatedMiddleware;
use App\Middleware\EnsureStaffMiddleware;
use App\Middleware\EnsureCustomerMiddleware;
use App\Middleware\EnsureAdminMiddleware;

/**
 * Web routes. The whole tree runs the session + user-loading + CSRF stack;
 * area subgroups add auth and role gates.
 *
 * @var Router $router
 */

$web = [StartSessionMiddleware::class, LoadUserMiddleware::class, VerifyCsrfMiddleware::class];

$router->group(['middleware' => $web], function (Router $router): void {

    // ---- Public -----------------------------------------------------------
    $router->get('/', [HomeController::class, 'landing'])->name('home');
    $router->get('/health', [HomeController::class, 'health']);

    $router->get('/submit', [GuestTicketController::class, 'create'])->name('submit');
    $router->post('/submit', [GuestTicketController::class, 'store']);
    $router->get('/submit/success', [GuestTicketController::class, 'success']);
    $router->get('/track', [GuestTicketController::class, 'trackForm'])->name('track');
    $router->post('/track', [GuestTicketController::class, 'track']);

    // ---- Guest-only auth --------------------------------------------------
    $router->group(['middleware' => [RedirectIfAuthenticatedMiddleware::class]], function (Router $router): void {
        $router->get('/login', [LoginController::class, 'show'])->name('login');
        $router->post('/login', [LoginController::class, 'login']);
        $router->get('/forgot-password', [PasswordController::class, 'showForgot']);
        $router->post('/forgot-password', [PasswordController::class, 'sendLink']);
        $router->get('/reset-password/{token}', [PasswordController::class, 'showReset']);
        $router->post('/reset-password', [PasswordController::class, 'reset']);
    });

    $router->post('/logout', [LoginController::class, 'logout'])->name('logout');

    // ---- Agent desk (staff) ----------------------------------------------
    $router->group(['prefix' => '/desk', 'middleware' => [AuthenticateMiddleware::class, EnsureStaffMiddleware::class]], function (Router $router): void {
        $router->get('', [DeskDashboard::class, 'index'])->name('desk');
        $router->get('/tickets', [DeskTicketController::class, 'index'])->name('desk.tickets');
        $router->get('/tickets/{id}', [DeskTicketController::class, 'show']);
        $router->post('/tickets/{id}/reply', [DeskTicketController::class, 'reply']);
        $router->post('/tickets/{id}/status', [DeskTicketController::class, 'updateStatus']);
        $router->post('/tickets/{id}/assign', [DeskTicketController::class, 'assign']);
        $router->post('/tickets/{id}/transfer', [DeskTicketController::class, 'transfer']);
        $router->post('/tickets/{id}/time', [DeskTicketController::class, 'logTime']);
    });

    // ---- Customer portal --------------------------------------------------
    $router->group(['prefix' => '/portal', 'middleware' => [AuthenticateMiddleware::class, EnsureCustomerMiddleware::class]], function (Router $router): void {
        $router->get('', [PortalDashboard::class, 'index'])->name('portal');
        $router->get('/tickets', [PortalTicketController::class, 'index'])->name('portal.tickets');
        $router->get('/tickets/new', [PortalTicketController::class, 'create']);
        $router->post('/tickets', [PortalTicketController::class, 'store']);
        $router->get('/tickets/{id}', [PortalTicketController::class, 'show']);
        $router->post('/tickets/{id}/reply', [PortalTicketController::class, 'reply']);
    });

    // ---- Administrator ----------------------------------------------------
    $router->group(['prefix' => '/admin', 'middleware' => [AuthenticateMiddleware::class, EnsureAdminMiddleware::class]], function (Router $router): void {
        $router->get('', [AdminDashboard::class, 'index'])->name('admin');
        $router->get('/users', [AdminUsers::class, 'index'])->name('admin.users');
        $router->get('/departments', [AdminDepartments::class, 'index'])->name('admin.departments');
        $router->get('/settings/branding', [AdminSettings::class, 'branding'])->name('admin.branding');
        $router->post('/settings/branding', [AdminSettings::class, 'updateBranding']);
        $router->get('/settings/general', [AdminSettings::class, 'general'])->name('admin.general');
        $router->post('/settings/general', [AdminSettings::class, 'updateGeneral']);
    });
});
