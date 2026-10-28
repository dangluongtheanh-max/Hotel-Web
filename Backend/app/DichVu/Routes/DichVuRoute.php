<?php

declare(strict_types=1);

namespace App\DichVu\Routes;

use App\DichVu\Controllers\DatDoAnController;
use App\DichVu\Controllers\GiatUiController;
use App\DichVu\Controllers\ThueXeMayController;

class DichVuRoute
{
    public static function register($router): void
    {
        // Nhóm phân hệ Đặt Đồ Ăn & Dịch vụ chung
        $router->get('/dichvu/tatca', [DatDoAnController::class, 'layTatCaDichVu']);
        $router->post('/dichvu/tatca', [DatDoAnController::class, 'layTatCaDichVu']);
        $router->get('/dichvu/monan', [DatDoAnController::class, 'layDanhSachMonAn']);
        $router->post('/dichvu/monan', [DatDoAnController::class, 'layDanhSachMonAn']);
        $router->get('/dichvu/phong-dang-luu-tru', [DatDoAnController::class, 'layPhongDangLuuTru']);
        $router->post('/dichvu/phong-dang-luu-tru', [DatDoAnController::class, 'layPhongDangLuuTru']);
        $router->post('/dichvu/datmon', [DatDoAnController::class, 'datMonAn']);

        // Nhóm phân hệ Giặt Ủi
        $router->get('/dichvu/giatui', [GiatUiController::class, 'layDanhSachGiatUi']);
        $router->post('/dichvu/giatui', [GiatUiController::class, 'layDanhSachGiatUi']);
        $router->post('/dichvu/dat-giatui', [GiatUiController::class, 'datDichVuGiatUi']);

        // Nhóm phân hệ Thuê Xe Máy
        $router->get('/dichvu/xemay', [ThueXeMayController::class, 'layDanhSachXeMay']);
        $router->post('/dichvu/xemay', [ThueXeMayController::class, 'layDanhSachXeMay']);
        $router->post('/dichvu/dat-thuexe', [ThueXeMayController::class, 'datThueXeMay']);
    }
}
