<?php

namespace App\DichVu\Routes;

use App\DichVu\Controllers\DichVuController;

/**
 * Dinh nghia cac route cho module DichVu.
 * Duoc goi boi public/index.php.
 */
class DichVuRoutes
{
    public static function register($router): void
    {
        $router->get('/dichvu', [DichVuController::class, 'ui']);
        $router->get('/dichvu/ui', [DichVuController::class, 'ui']);

        $router->post('/dichvu/xemds', [DichVuController::class, 'xemDSDichVu']);
        $router->get('/dichvu/xemds', [DichVuController::class, 'xemDSDichVu']);

        $router->post('/dichvu/timkiem', [DichVuController::class, 'timKiemDichVu']);
        $router->get('/dichvu/timkiem', [DichVuController::class, 'timKiemDichVu']);

        $router->post('/dichvu/dat', [DichVuController::class, 'datDichVu']);
        $router->post('/dichvu/them', [DichVuController::class, 'themDichVu']);
        $router->post('/dichvu/capnhat', [DichVuController::class, 'capNhatDichVu']);
        $router->post('/dichvu/xoa', [DichVuController::class, 'xoaDichVu']);
    }
}
