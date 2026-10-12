<?php

namespace App\TaiKhoan\Controllers;

use App\TaiKhoan\Services\TaiKhoanService;
use Shared\Response;

/**
 * Controller cho module TaiKhoan.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class TaiKhoanController
{
    private TaiKhoanService $service;

    public function __construct(TaiKhoanService $service)
    {
        $this->service = $service;
    }

    public function dangNhap($request, $response)
    {
        return $this->respond($response, $this->service->dangNhap($request->all()));
    }

    public function quenMatKhau($request, $response)
    {
        return $this->respond($response, $this->service->quenMatKhau($request->all()));
    }

    public function xemDSTaiKhoan($request, $response)
    {
        return $this->respond($response, $this->service->xemDSTaiKhoan($request->all()));
    }

    public function xemCTTaiKhoan($request, $response)
    {
        return $this->respond($response, $this->service->xemCTTaiKhoan($request->all()));
    }

    public function khoaTaiKhoan($request, $response)
    {
        return $this->respond($response, $this->service->khoaTaiKhoan($request->all()));
    }

    public function moTaiKhoan($request, $response)
    {
        return $this->respond($response, $this->service->moTaiKhoan($request->all()));
    }

    public function capNhatVaiTro($request, $response)
    {
        return $this->respond($response, $this->service->capNhatVaiTro($request->all()));
    }

    public function xemVaiTroVaQuyen($request, $response)
    {
        return $this->respond($response, $this->service->xemVaiTroVaQuyen());
    }

    private function respond($response, array $result)
    {
        $status = $result['statusCode'] ?? 200;
        unset($result['statusCode']);

        return Response::json($response, $result, $status);
    }
}
