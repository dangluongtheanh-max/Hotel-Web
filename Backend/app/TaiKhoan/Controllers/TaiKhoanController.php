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
        $result = $this->service->dangNhap($request->all());
        return Response::json($response, $result);
    }

    public function quenMatKhau($request, $response)
    {
        $result = $this->service->quenMatKhau($request->all());
        return Response::json($response, $result);
    }

}
