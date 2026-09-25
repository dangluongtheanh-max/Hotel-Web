<?php

namespace App\DanhGia\Routes;

use App\DanhGia\Controllers\DanhGiaController;

/**
 * Dinh nghia route cho module DanhGia.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class DanhGiaRoute
{
    public static function register($router): void
    {
    $router->post('/danhgia/themdanhgia', [DanhGiaController::class, 'themDanhGia']);
    $router->post('/danhgia/xoadanhgia', [DanhGiaController::class, 'xoaDanhGia']);
    $router->post('/danhgia/xemdanhgiatheophieu', [DanhGiaController::class, 'xemDanhGiaTheoPhieu']);
    }
}
