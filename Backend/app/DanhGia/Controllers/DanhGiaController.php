<?php

namespace App\DanhGia\Controllers;

use App\DanhGia\Services\DanhGiaService;
use Shared\Response;

/**
 * Controller cho module DanhGia.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class DanhGiaController
{
    private DanhGiaService $service;

    public function __construct(DanhGiaService $service)
    {
        $this->service = $service;
    }

    public function themDanhGia($request, $response)
    {
        $result = $this->service->themDanhGia($request->all());
        return Response::json($response, $result);
    }

    public function xoaDanhGia($request, $response)
    {
        $result = $this->service->xoaDanhGia($request->all());
        return Response::json($response, $result);
    }

    public function xemDanhGiaTheoPhieu($request, $response)
    {
        $result = $this->service->xemDanhGiaTheoPhieu($request->all());
        return Response::json($response, $result);
    }

}
