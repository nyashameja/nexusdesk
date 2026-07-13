<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\DepartmentRepositoryInterface;

final class DepartmentController extends Controller
{
    public function __construct(private readonly DepartmentRepositoryInterface $departments)
    {
    }

    public function index(Request $request): Response
    {
        return $this->view('admin.departments_index', [
            'title'       => 'Departments',
            'active'      => 'departments',
            'departments' => $this->departments->all(),
        ]);
    }
}
