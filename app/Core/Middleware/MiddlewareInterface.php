<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;
use Closure;

interface MiddlewareInterface
{
    /**
     * Handle the request and either short-circuit with a Response or call
     * $next to continue the pipeline.
     *
     * @param Closure(Request):Response $next
     */
    public function handle(Request $request, Closure $next): Response;
}
