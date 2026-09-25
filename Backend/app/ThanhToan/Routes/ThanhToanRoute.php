<?php

namespace App\ThanhToan\Routes;

use App\ThanhToan\Controllers\ThanhToanController;

/**
 * Dinh nghia route cho module ThanhToan.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class ThanhToanRoute
{
    public static function register($router): void
    {
    $router->post('/thanhtoan/taothanhtoan', [ThanhToanController::class, 'taoThanhToan']);
    $router->post('/thanhtoan/xemthanhtoan', [ThanhToanController::class, 'xemThanhToan']);
    $router->post('/thanhtoan/capnhattrangthaithanhtoan', [ThanhToanController::class, 'capNhatTrangThaiThanhToan']);
    }
}
