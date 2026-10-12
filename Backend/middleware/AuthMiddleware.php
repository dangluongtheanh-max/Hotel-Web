<?php

namespace Middleware;

use App\TaiKhoan\Services\TaiKhoanService;
use Shared\Request;

/**
 * Xac thuc Bearer token va gan tai khoan hien hanh vao request.
 */
class AuthMiddleware
{
    private TaiKhoanService $service;

    public function __construct(TaiKhoanService $service)
    {
        $this->service = $service;
    }

    public function authenticate(Request $request): array
    {
        $result = $this->service->authenticateToken($request->header('Authorization') ?? '');
        if ($result['success'] ?? false) {
            $request->setUser($result['user']);
        }

        return $result;
    }

    public function handle($request, callable $next)
    {
        if (!$request instanceof Request) {
            return ['success' => false, 'statusCode' => 500, 'error' => 'Invalid request object.'];
        }

        $result = $this->authenticate($request);
        if (!($result['success'] ?? false)) {
            return $result;
        }

        return $next($request);
    }
}
