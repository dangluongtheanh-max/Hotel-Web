<?php

namespace App\Phong\Controllers;

use App\Phong\Services\PhongService;
use Shared\Response;

/**
 * Controller cho module Phong.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class PhongController
{
    private PhongService $service;

    public function __construct(PhongService $service)
    {
        $this->service = $service;
    }

    public function xemDSPhong($request, $response)
    {
        $result = $this->service->xemDSPhong($request->all());
        return Response::json($response, $result);
    }

    public function xemCTPhong($request, $response)
    {
        $result = $this->service->xemCTPhong($request->all());
        return Response::json($response, $result);
    }

    public function themPhong($request, $response)
    {
        $result = $this->service->themPhong($request->all());
        return Response::json($response, $result);
    }

    public function capNhatThongTinPhong($request, $response)
    {
        $result = $this->service->capNhatThongTinPhong($request->all());
        return Response::json($response, $result);
    }

    public function timKiemPhong($request, $response)
    {
        $result = $this->service->timKiemPhong($request->all());
        return Response::json($response, $result);
    }

    public function kiemTraTrangThaiPhong($request, $response)
    {
        $result = $this->service->kiemTraTrangThaiPhong($request->all());
        return Response::json($response, $result);
    }

    public function kiemTraPhongTrong($request, $response)
    {
        $result = $this->service->kiemTraPhongTrong($request->all());
        return Response::json($response, $result);
    }

}
