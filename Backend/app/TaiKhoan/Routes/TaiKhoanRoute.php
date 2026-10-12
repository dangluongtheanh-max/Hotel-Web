<?php

namespace App\TaiKhoan\Routes;

use App\TaiKhoan\Controllers\TaiKhoanController;

/**
 * Dinh nghia route cho module TaiKhoan.
 * Duoc include boi public/index.php thong qua bo dinh tuyen chung.
 */
class TaiKhoanRoute
{
    public static function register($router): void
    {
        $router->post('/taikhoan/dangnhap', [TaiKhoanController::class, 'dangNhap']);
        $router->post('/taikhoan/quenmatkhau', [TaiKhoanController::class, 'quenMatKhau']);
        $router->get('/taikhoan/xemds', [TaiKhoanController::class, 'xemDSTaiKhoan']);
        $router->post('/taikhoan/xemds', [TaiKhoanController::class, 'xemDSTaiKhoan']);
        $router->get('/taikhoan/xemct', [TaiKhoanController::class, 'xemCTTaiKhoan']);
        $router->post('/taikhoan/xemct', [TaiKhoanController::class, 'xemCTTaiKhoan']);
        $router->post('/taikhoan/khoa', [TaiKhoanController::class, 'khoaTaiKhoan']);
        $router->post('/taikhoan/mo', [TaiKhoanController::class, 'moTaiKhoan']);
        $router->post('/taikhoan/capnhatvaitro', [TaiKhoanController::class, 'capNhatVaiTro']);
        $router->get('/taikhoan/vaitroquyen', [TaiKhoanController::class, 'xemVaiTroVaQuyen']);
    }
}
