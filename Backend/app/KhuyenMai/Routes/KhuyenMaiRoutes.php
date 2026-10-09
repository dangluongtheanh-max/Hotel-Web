<?php

namespace App\KhuyenMai\Routes;

use App\KhuyenMai\Controllers\KhuyenMaiController;

/**
 * Định nghĩa các Routes cho module Khuyến Mãi (BE4).
 */
class KhuyenMaiRoutes
{
    public static function register($router): void
    {
        // Giao diện trực quan Dashboard
        $router->get('/khuyenmai', [KhuyenMaiController::class, 'viewDashboard']);

        // Client APIs (Xem danh sách khuyến mãi & Áp dụng)
        $router->get('/khuyenmai/danhsach', [KhuyenMaiController::class, 'xemDSClient']);
        $router->post('/khuyenmai/apdung', [KhuyenMaiController::class, 'apDungKhuyenMai']);

        // Admin APIs (Quản trị toàn bộ danh sách & Thiết lập khuyến mãi)
        $router->get('/khuyenmai/admin/danhsach', [KhuyenMaiController::class, 'xemDSAdmin']);
        $router->post('/khuyenmai/admin/them', [KhuyenMaiController::class, 'themKhuyenMai']);
        $router->post('/khuyenmai/admin/capnhat', [KhuyenMaiController::class, 'capNhatKhuyenMai']);
        $router->post('/khuyenmai/admin/doitrangthai', [KhuyenMaiController::class, 'doiTrangThai']);
        $router->post('/khuyenmai/admin/xoa', [KhuyenMaiController::class, 'xoaKhuyenMai']);
    }
}
