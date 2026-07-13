<?php

declare(strict_types=1);

namespace App\Controllers\Web\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\Contracts\RoleRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;

final class UserController extends Controller
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly RoleRepositoryInterface $roles,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = max(1, (int) $request->query('page', 1));
        $search = $request->query('q') !== null ? (string) $request->query('q') : null;
        $role = $request->query('role') !== null ? (string) $request->query('role') : null;
        $perPage = 20;

        return $this->view('admin.users_index', [
            'title'   => 'Users',
            'active'  => 'users',
            'users'   => $this->users->paginate($page, $perPage, $search, $role),
            'total'   => $this->users->count($search, $role),
            'page'    => $page,
            'perPage' => $perPage,
            'roles'   => $this->roles->all(),
            'search'  => $search,
            'role'    => $role,
        ]);
    }
}
