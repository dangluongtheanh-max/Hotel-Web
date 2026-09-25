<?php

namespace App\LeTan\Controllers;

use App\LeTan\Services\LeTanService;
use Shared\Response;

/**
 * Controller cho module LeTan.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class LeTanController
{
    private LeTanService $service;

    public function __construct(LeTanService $service)
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

    public function timKiemPhong($request, $response)
    {
        $result = $this->service->timKiemPhong($request->all());
        return Response::json($response, $result);
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

    public function timHoaDon($request, $response)
    {
        $result = $this->service->timHoaDon($request->all());
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

}
