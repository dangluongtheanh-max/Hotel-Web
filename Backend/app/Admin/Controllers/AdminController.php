<?php

namespace App\Admin\Controllers;

use App\Admin\Services\AdminService;
use Shared\Response;
use Throwable;

/**
 * Controller cho module Admin.
 * Chi nhan request, goi Service xu ly, tra ve response - khong chua business logic.
 */
class AdminController
{
    private AdminService $service;

    public function __construct(AdminService $service)
    {
        $this->service = $service;
    }

    private function getParams($request): array
    {
        if (is_array($request)) {
            return $request;
        }
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        $body = file_get_contents('php://input');
        $json = json_decode($body, true);
        if (is_array($json)) {
            return array_merge($_GET, $_POST, $json);
        }
        return array_merge($_GET, $_POST);
    }

    public function xemDSNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->xemDSNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemDSLeTan($request = null, $response = null)
    {
        return $this->xemDSNhanVien($request, $response);
    }

    public function timKiemNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->timKiemNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function timKiemLeTan($request = null, $response = null)
    {
        return $this->timKiemNhanVien($request, $response);
    }

    public function xemCTNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->xemCTNhanVien($this->getParams($request));
            if ($result === null) {
                return Response::error($response, 'Không tìm thấy thông tin nhân viên.', 404);
            }
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function xemCTLeTan($request = null, $response = null)
    {
        return $this->xemCTNhanVien($request, $response);
    }

    public function capNhatThongTinNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->capNhatThongTinNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function capNhatThongTinLeTan($request = null, $response = null)
    {
        return $this->capNhatThongTinNhanVien($request, $response);
    }

    public function themNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->themNhanVien($this->getParams($request));
            return Response::json($response, $result, 201);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function themLeTan($request = null, $response = null)
    {
        return $this->themNhanVien($request, $response);
    }

    public function moTaiKhoanNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->moTaiKhoanNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function moTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->moTaiKhoanNhanVien($request, $response);
    }

    public function khoaTaiKhoanNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->khoaTaiKhoanNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function khoaTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->khoaTaiKhoanNhanVien($request, $response);
    }

    public function thongKeSoLuongNhanVien($request = null, $response = null)
    {
        try {
            $result = $this->service->thongKeSoLuongNhanVien($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function thongKeDoanhThu($request = null, $response = null)
    {
        try {
            $result = $this->service->thongKeDoanhThuChiNhanh($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }

    public function thongKeSoLuongPhong($request = null, $response = null)
    {
        try {
            $result = $this->service->thongKeSoLuongPhong($this->getParams($request));
            return Response::json($response, $result);
        } catch (Throwable $e) {
            return Response::error($response, $e->getMessage(), 400);
        }
    }
}
