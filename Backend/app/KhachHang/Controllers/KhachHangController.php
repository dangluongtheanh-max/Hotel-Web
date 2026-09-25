<?php

namespace App\KhachHang\Controllers;

use App\KhachHang\Services\KhachHangService;
use Shared\Response;

/**
 * Controller cho module KhachHang.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class KhachHangController
{
    private KhachHangService $service;

    public function __construct(KhachHangService $service)
    {
        $this->service = $service;
    }

    public function dangKy($request, $response)
    {
        $result = $this->service->dangKy($request->all());
        return Response::json($response, $result);
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

    public function datPhong($request, $response)
    {
        $result = $this->service->datPhong($request->all());
        return Response::json($response, $result);
    }

    public function thanhToan($request, $response)
    {
        $result = $this->service->thanhToan($request->all());
        return Response::json($response, $result);
    }

    public function huyDatPhong($request, $response)
    {
        $result = $this->service->huyDatPhong($request->all());
        return Response::json($response, $result);
    }

    public function xemLichSuDatPhong($request, $response)
    {
        $result = $this->service->xemLichSuDatPhong($request->all());
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

    public function xemThongTinCaNhan($request, $response)
    {
        $result = $this->service->xemThongTinCaNhan($request->all());
        return Response::json($response, $result);
    }

    public function capNhatThongTinCaNhan($request, $response)
    {
        $result = $this->service->capNhatThongTinCaNhan($request->all());
        return Response::json($response, $result);
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

}
