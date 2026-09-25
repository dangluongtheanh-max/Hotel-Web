<?php

namespace App\HoaDon\Routes;

use App\HoaDon\Controllers\HoaDonController;

/**
 * Dinh nghia route cho module HoaDon.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class HoaDonRoute
{
    public static function register($router): void
    {
    $router->post('/hoadon/taohoadon', [HoaDonController::class, 'taoHoaDon']);
    $router->post('/hoadon/xemhoadon', [HoaDonController::class, 'xemHoaDon']);
    $router->post('/hoadon/xemcthd', [HoaDonController::class, 'xemCTHD']);
    $router->post('/hoadon/timhoadon', [HoaDonController::class, 'timHoaDon']);
    }
}
