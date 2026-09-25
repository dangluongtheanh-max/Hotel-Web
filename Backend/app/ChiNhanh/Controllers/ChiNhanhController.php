<?php

namespace App\ChiNhanh\Controllers;

use App\ChiNhanh\Services\ChiNhanhService;
use Shared\Response;

/**
 * Controller cho module ChiNhanh.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class ChiNhanhController
{
    private ChiNhanhService $service;

    public function __construct(ChiNhanhService $service)
    {
        $this->service = $service;
    }

    public function xemDSChiNhanh($request, $response)
    {
        $result = $this->service->xemDSChiNhanh($request->all());
        return Response::json($response, $result);
    }

    public function xemChiTietChiNhanh($request, $response)
    {
        $result = $this->service->xemChiTietChiNhanh($request->all());
        return Response::json($response, $result);
    }

    public function themChiNhanh($request, $response)
    {
        $result = $this->service->themChiNhanh($request->all());
        return Response::json($response, $result);
    }

    public function capNhatThongTinChiNhanh($request, $response)
    {
        $result = $this->service->capNhatThongTinChiNhanh($request->all());
        return Response::json($response, $result);
    }

    public function timKiemChiNhanh($request, $response)
    {
        $result = $this->service->timKiemChiNhanh($request->all());
        return Response::json($response, $result);
    }

}
