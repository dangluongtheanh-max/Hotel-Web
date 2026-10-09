<?php

namespace App\HoaDon\Routes;

use App\HoaDon\Controllers\HoaDonController;

/**
 * Định nghĩa các routes cho module Hóa Đơn (BE4).
 */
class HoaDonRoute
{
    public static function register($router): void
    {
        // Giao diện trực quan Dashboard Hóa Đơn / Folio
        $router->get('/hoadon', [HoaDonController::class, 'viewDashboard']);

        // Xem danh sách hóa đơn
        $router->get('/hoadon/danhsach', [HoaDonController::class, 'danhSachHoaDon']);

        // Xem chi tiết một hóa đơn
        $router->get('/hoadon/xem', [HoaDonController::class, 'xemHoaDon']);
        $router->post('/hoadon/xemhoadon', [HoaDonController::class, 'xemHoaDon']);

        // Xem trước bảng kê chi phí trước khi xuất hóa đơn (Preview)
        $router->get('/hoadon/preview', [HoaDonController::class, 'previewHoaDon']);
        $router->post('/hoadon/preview', [HoaDonController::class, 'previewHoaDon']);

        // Lập hóa đơn chính thức
        $router->post('/hoadon/tao', [HoaDonController::class, 'taoHoaDon']);
        $router->post('/hoadon/taohoadon', [HoaDonController::class, 'taoHoaDon']);
    }
}
