<?php

namespace App\HoaDon\Controllers;

use App\HoaDon\Services\HoaDonService;
use Shared\Response;

/**
 * Controller cho module HoaDon.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class HoaDonController
{
    private HoaDonService $service;

    public function __construct(HoaDonService $service)
    {
        $this->service = $service;
    }

    public function taoHoaDon($request, $response)
    {
        $result = $this->service->taoHoaDon($request->all());
        return Response::json($response, $result);
    }

    public function xemHoaDon($request, $response)
    {
        $result = $this->service->xemHoaDon($request->all());
        return Response::json($response, $result);
    }

    public function xemCTHD($request, $response)
    {
        $result = $this->service->xemCTHD($request->all());
        return Response::json($response, $result);
    }

    public function timHoaDon($request, $response)
    {
        $result = $this->service->timHoaDon($request->all());
        return Response::json($response, $result);
    }

}
