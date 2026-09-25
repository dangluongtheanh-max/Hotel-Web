<?php

namespace App\ThanhToan\Controllers;

use App\ThanhToan\Services\ThanhToanService;
use Shared\Response;

/**
 * Controller cho module ThanhToan.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class ThanhToanController
{
    private ThanhToanService $service;

    public function __construct(ThanhToanService $service)
    {
        $this->service = $service;
    }

    public function taoThanhToan($request, $response)
    {
        $result = $this->service->taoThanhToan($request->all());
        return Response::json($response, $result);
    }

    public function xemThanhToan($request, $response)
    {
        $result = $this->service->xemThanhToan($request->all());
        return Response::json($response, $result);
    }

    public function capNhatTrangThaiThanhToan($request, $response)
    {
        $result = $this->service->capNhatTrangThaiThanhToan($request->all());
        return Response::json($response, $result);
    }

}
