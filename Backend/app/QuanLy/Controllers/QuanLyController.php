<?php

namespace App\QuanLy\Controllers;

use App\QuanLy\Services\QuanLyService;
use Shared\Response;

/**
 * Controller cho module QuanLy.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class QuanLyController
{
    private QuanLyService $service;

    public function __construct(QuanLyService $service)
    {
        $this->service = $service;
    }

    public function xemDSLeTan($request, $response)
    {
        $result = $this->service->xemDSLeTan($request->all());
        return Response::json($response, $result);
    }

    public function xemChiTietLeTan($request, $response)
    {
        $result = $this->service->xemChiTietLeTan($request->all());
        return Response::json($response, $result);
    }

    public function capNhatThongTinLeTan($request, $response)
    {
        $result = $this->service->capNhatThongTinLeTan($request->all());
        return Response::json($response, $result);
    }

    public function timKiemLeTan($request, $response)
    {
        $result = $this->service->timKiemLeTan($request->all());
        return Response::json($response, $result);
    }

    public function khoaTaiKhoanLeTan($request, $response)
    {
        $result = $this->service->khoaTaiKhoanLeTan($request->all());
        return Response::json($response, $result);
    }

    public function moTaiKhoanLeTan($request, $response)
    {
        $result = $this->service->moTaiKhoanLeTan($request->all());
        return Response::json($response, $result);
    }

    public function thongKeDoanhThu($request, $response)
    {
        $result = $this->service->thongKeDoanhThu($request->all());
        return Response::json($response, $result);
    }

    public function thongKeSLDatPhong($request, $response)
    {
        $result = $this->service->thongKeSLDatPhong($request->all());
        return Response::json($response, $result);
    }

    public function thongKeDanhGiaKhachHang($request, $response)
    {
        $result = $this->service->thongKeDanhGiaKhachHang($request->all());
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
