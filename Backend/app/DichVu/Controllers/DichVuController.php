<?php

namespace App\DichVu\Controllers;

use App\DichVu\Services\DichVuService;
use Shared\Response;

class DichVuController
{
    private DichVuService $service;

    public function __construct(DichVuService $service)
    {
        $this->service = $service;
    }

    public function xemDSDichVu($request, $response)
    {
        $result = $this->service->xemDSDichVu($request->all());
        return Response::json($response, $result);
    }

    public function datDichVu($request, $response)
    {
        $result = $this->service->datDichVu($request->all());
        return Response::json($response, $result);
    }

    public function timKiemDichVu($request, $response)
    {
        $result = $this->service->timKiemDichVu($request->all());
        return Response::json($response, $result);
    }

    public function themDichVu($request, $response)
    {
        $result = $this->service->themDichVu($request->all());
        return Response::json($response, $result);
    }

    public function capNhatDichVu($request, $response)
    {
        $result = $this->service->capNhatDichVu($request->all());
        return Response::json($response, $result);
    }

    public function xoaDichVu($request, $response)
    {
        $result = $this->service->xoaDichVu($request->all());
        return Response::json($response, $result);
    }
}
