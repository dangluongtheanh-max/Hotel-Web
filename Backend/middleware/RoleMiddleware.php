<?php

namespace Middleware;

use Shared\Request;

/**
 * Kiem tra vai tro (VaiTro trong TaiKhoan) co duoc phep truy cap route hay khong.
 * Vi du: chi Admin moi duoc goi cac route trong module Admin.
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
        $user = $request instanceof Request ? $request->user() : null;
        $role = $user['VaiTro'] ?? null;
        if (!in_array($role, $this->allowedRoles, true)) {
            return ['success' => false, 'statusCode' => 403, 'error' => 'Bạn không có quyền truy cập chức năng này.'];
        }
        return $next($request);
    }

    public static function requiredRoles(string $method, string $path): ?array
    {
        if (
            str_starts_with($path, '/admin/')
            || str_starts_with($path, '/thongke/')
            || str_starts_with($path, '/taikhoan/')
            || str_starts_with($path, '/phong/')
            || str_starts_with($path, '/loaiphong/')
            || str_starts_with($path, '/khuyenmai/admin/')
            || $path === '/khuyenmai'
        ) {
            return null;
        }

        if (
            in_array($path, ['/admin', '/admin/dashboard', '/gui', '/dashboard', '/client', '/guest', '/booking', '/khachhang'], true)
            || $path === '/khachhang/danhsachphong'
            || $path === '/khachhang/xemdsphong'
            || $path === '/khachhang/datphong'
            || $path === '/khachhang/tracuu'
            || $path === '/khachhang/lichsu'
            || $path === '/khachhang/xemlichsudatphong'
            || $path === '/phong/xemdsphong'
            || $path === '/phong/xemctphong'
            || $path === '/phong/timkiemphong'
            || $path === '/phong/kiemtratrangthaiphong'
            || $path === '/phong/kiemtraphongtrong'
            || $path === '/loaiphong/xemdsloaiphong'
            || $path === '/loaiphong/xemctloaiphong'
            || $path === '/dichvu'
            || $path === '/dichvu/ui'
            || $path === '/dichvu/xemds'
            || $path === '/dichvu/timkiem'
            || $path === '/dichvu/dat'
            || $path === '/thanhtoan/taothanhtoan'
            || $path === '/thanhtoan/tao'
            || $path === '/thanhtoan/xem'
            || $path === '/thanhtoan/xemthanhtoan'
            || $path === '/thanhtoan/webhook'
        ) {
            return null;
        }
        if (
            $path === '/taikhoan/dangnhap'
            || $path === '/taikhoan/quenmatkhau'
        ) {
            return null;
        }
        if (str_starts_with($path, '/letan/')) {
            return ['Admin', 'LeTan'];
        }
        if (str_starts_with($path, '/datphong/')) {
            return ['Admin', 'LeTan'];
        }
        if (str_starts_with($path, '/hoadon/')) {
            return ['Admin', 'LeTan'];
        }
        if (str_starts_with($path, '/thanhtoan/')) {
            return ['Admin', 'LeTan'];
        }
        if ($path === '/thanhtoan') {
            return ['Admin', 'LeTan'];
        }
        if (str_starts_with($path, '/danhgia/')) {
            return ['Admin', 'LeTan'];
        }
        if (str_starts_with($path, '/dichvu/')) {
            return ['Admin', 'LeTan'];
        }
        if ($path === '/hoadon') {
            return ['Admin', 'LeTan'];
        }
        if (in_array($path, ['/khuyenmai/danhsach', '/khuyenmai/apdung'], true)) {
            return null;
        }
        return null;
    }

    public static function allows(?array $user, array $allowedRoles): bool
    {
        return $user !== null && in_array($user['VaiTro'] ?? null, $allowedRoles, true);
    }
}
