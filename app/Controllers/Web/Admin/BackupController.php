<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\HttpException;
use App\Core\RedirectResponse;
use App\Core\Request;
use App\Core\Response;
use App\Services\Audit\AuditService;
use App\Services\Backup\BackupService;

final class BackupController extends Controller
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly AuditService $audit,
    ) {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.backups', [
            'title'   => 'Backups',
            'active'  => 'backups',
            'backups' => $this->backups->list(),
        ]);
    }

    public function create(Request $request): Response
    {
        try {
            $file = $this->backups->create();
            $this->backups->prune(10);
            $this->audit->log('backup.created', new: ['file' => $file]);
            return (new RedirectResponse('/admin/backups'))->with('status', "Backup created: {$file}");
        } catch (\Throwable $e) {
            return (new RedirectResponse('/admin/backups'))->withErrors(['backup' => ['Backup failed: ' . $e->getMessage()]]);
        }
    }

    public function download(Request $request, string $file): Response
    {
        $path = $this->backups->path($file);
        if ($path === null) {
            throw HttpException::notFound('Backup not found.');
        }
        return (new Response((string) file_get_contents($path)))
            ->header('Content-Type', 'application/gzip')
            ->header('Content-Disposition', 'attachment; filename="' . $file . '"');
    }

    public function delete(Request $request, string $file): Response
    {
        $this->backups->delete($file);
        $this->audit->log('backup.deleted', new: ['file' => $file]);
        return (new RedirectResponse('/admin/backups'))->with('status', 'Backup deleted.');
    }
}
