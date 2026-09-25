<?php

namespace App\DatPhong\Controllers;

use App\DatPhong\Services\DatPhongService;
use Shared\Response;

/**
 * Controller cho module DatPhong.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class DatPhongController
{
    private DatPhongService $service;

    public function __construct(DatPhongService $service)
    {
        $this->service = $service;
    }

    public function xemDSPhieu($request, $response)
    {
        $result = $this->service->xemDSPhieu($request->all());
        return Response::json($response, $result);
    }

    public function xemCTPhieu($request, $response)
    {
        $result = $this->service->xemCTPhieu($request->all());
        return Response::json($response, $result);
    }

    public function timKiemPhieu($request, $response)
    {
        $result = $this->service->timKiemPhieu($request->all());
        return Response::json($response, $result);
    }

    public function checkin($request, $response)
    {
        $result = $this->service->checkin($request->all());
        return Response::json($response, $result);
    }

    public function checkout($request, $response)
    {
        $result = $this->service->checkout($request->all());
        return Response::json($response, $result);
    }

    public function taoPhieuNhanPhong($request, $response)
    {
        $result = $this->service->taoPhieuNhanPhong($request->all());
        return Response::json($response, $result);
    }

    public function capNhatPhieuNhanPhong($request, $response)
    {
        $result = $this->service->capNhatPhieuNhanPhong($request->all());
        return Response::json($response, $result);
    }

    public function apDungKhuyenMai($request, $response)
    {
        $result = $this->service->apDungKhuyenMai($request->all());
        return Response::json($response, $result);
    }

}
