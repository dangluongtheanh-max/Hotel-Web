<?php

namespace App\ThanhToan\Routes;

use App\ThanhToan\Controllers\ThanhToanController;

/**
 * Định nghĩa các routes cho module Thanh Toán (BE4).
 */
class ThanhToanRoute
{
    public static function register($router): void
    {
        // Giao diện trực quan Cổng Thanh Toán & Quét VietQR MBBank
        $router->get('/thanhtoan', [ThanhToanController::class, 'viewDashboard']);

        // Xem danh sách giao dịch thanh toán
        $router->get('/thanhtoan/danhsach', [ThanhToanController::class, 'danhSachThanhToan']);

        // Tạo giao dịch thanh toán (sinh VietQR Napas 247)
        $router->post('/thanhtoan/taothanhtoan', [ThanhToanController::class, 'taoThanhToan']);
        $router->post('/thanhtoan/tao', [ThanhToanController::class, 'taoThanhToan']);

        // Tra cứu trạng thái thanh toán (hỗ trợ Polling)
        $router->get('/thanhtoan/xem', [ThanhToanController::class, 'xemThanhToan']);
        $router->post('/thanhtoan/xemthanhtoan', [ThanhToanController::class, 'xemThanhToan']);

        // Webhook đối soát tự động từ Ngân Hàng Napas 247
        $router->post('/thanhtoan/webhook', [ThanhToanController::class, 'xuLyWebhook']);

        // API Mô phỏng chuyển tiền ngân hàng (Test & Demo PTTK)
        $router->post('/thanhtoan/mophong', [ThanhToanController::class, 'moPhongChuyenKhoan']);

        // Yêu cầu hoàn tiền (Mô hình 1: Khách điền STK nhận lại tiền)
        $router->post('/thanhtoan/hoantien', [ThanhToanController::class, 'yeuCauHoanTien']);
    }
}
