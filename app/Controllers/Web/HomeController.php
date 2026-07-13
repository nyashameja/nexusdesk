<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Controller;
use App\Core\JsonResponse;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\DepartmentRepositoryInterface;

final class HomeController extends Controller
{
    public function __construct(private readonly DepartmentRepositoryInterface $departments)
    {
    }

    public function landing(Request $request): Response
    {
        return $this->view('home.landing', [
            'title'       => setting('general.app_name', 'NexusDesk'),
            'departments' => $this->safeDepartments(),
        ], 'layouts/guest');
    }

    public function health(Request $request): Response
    {
        return new JsonResponse([
            'status'  => 'ok',
            'app'     => setting('general.app_name', 'NexusDesk'),
            'time'    => date('c'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    private function safeDepartments(): array
    {
        try {
            return $this->departments->all(publicOnly: true);
        } catch (\Throwable) {
            return [];
        }
    }
}
