<?php

namespace Middleware;

/**
 * Kiem tra vai tro (VaiTro trong TaiKhoan) co duoc phep truy cap route hay khong.
 * Vi du: chi Admin moi duoc goi cac route trong module Admin/ChiNhanh.
 */
class RoleMiddleware
{
    private array $allowedRoles;

    public function __construct(array $allowedRoles)
    {
        $this->allowedRoles = $allowedRoles;
    }

    public function handle($request, callable $next)
    {
        $role = $request->user['VaiTro'] ?? null;
        if (!in_array($role, $this->allowedRoles, true)) {
            http_response_code(403);
            echo json_encode(['error' => 'Forbidden']);
            exit;
        }
        return $next($request);
    }
}
