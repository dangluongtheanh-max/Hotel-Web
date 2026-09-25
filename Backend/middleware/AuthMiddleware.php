<?php

namespace Middleware;

/**
 * Kiem tra nguoi dung da dang nhap (co token/session hop le) hay chua.
 * Ap dung cho cac route can dang nhap: KhachHang, LeTan, QuanLy, Admin.
 */
class AuthMiddleware
{
    public function handle($request, callable $next)
    {
        $token = $request->header('Authorization');
        if (empty($token)) {
            http_response_code(401);
            echo json_encode(['error' => 'Unauthorized']);
            exit;
        }
        // TODO: xac thuc token, gan $request->user
        return $next($request);
    }
}
