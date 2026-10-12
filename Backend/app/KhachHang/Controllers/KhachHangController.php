<?php

namespace App\KhachHang\Controllers;

use App\KhachHang\Services\KhachHangService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module KhachHang (Khách Hàng Đặt Phòng & Dịch Vụ).
 */
class KhachHangController
{
    private KhachHangService $service;

    public function __construct(?KhachHangService $service = null)
    {
        $this->service = $service ?? new KhachHangService();
    }

    private function getParams($request): array
    {
        if ($request instanceof Request) {
            return $request->all();
        }
        if (is_array($request)) {
            return $request;
        }
        if (is_object($request) && method_exists($request, 'all')) {
            return $request->all();
        }
        return Request::capture()->all();
    }

    /**
     * Giao diện Cổng Khách Hàng Thao Tác Trực Tuyến (Customer Portal).
     * Endpoint: GET /client hoặc GET /khachhang
     */
    public function viewPortal($request = null, $response = null)
    {
        $viewFile = __DIR__ . '/../Views/index.html';
        if (file_exists($viewFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewFile);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "Giao diện Khách Hàng đang được khởi tạo...";
        exit;
    }

    public function xemDSKhachHang($request = null, $response = null)
    {
        return $this->respondManagement($response, $this->service->xemDSKhachHang($this->getParams($request)));
    }

    public function timKiemKhachHang($request = null, $response = null)
    {
        return $this->respondManagement($response, $this->service->timKiemKhachHang($this->getParams($request)));
    }

    public function xemCTKhachHang($request = null, $response = null)
    {
        return $this->respondManagement($response, $this->service->xemCTKhachHang($this->getParams($request)));
    }

    public function capNhatThongTinKhachHang($request = null, $response = null)
    {
        return $this->respondManagement(
            $response,
            $this->service->capNhatThongTinKhachHang($this->getParams($request))
        );
    }

    public function khoaTaiKhoanKhachHang($request = null, $response = null)
    {
        return $this->respondManagement(
            $response,
            $this->service->khoaTaiKhoanKhachHang($this->getParams($request))
        );
    }

    public function moTaiKhoanKhachHang($request = null, $response = null)
    {
        return $this->respondManagement(
            $response,
            $this->service->moTaiKhoanKhachHang($this->getParams($request))
        );
    }

    private function respondManagement($response, array $result)
    {
        if (!($result['success'] ?? false)) {
            return Response::error(
                $response,
                $result['error'] ?? 'Không thể xử lý yêu cầu.',
                $result['statusCode'] ?? 400
            );
        }

        return Response::json($response, $result);
    }

    /**
     * API: Lấy danh sách các phòng và loại phòng.
     * Endpoint: GET /khachhang/danhsachphong hoặc POST /khachhang/xemdsphong
     */
    public function xemDSPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSPhong($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Khách hàng đặt phòng trực tuyến (Lưu CSDL thật).
     * Endpoint: POST /khachhang/datphong
     */
    public function datPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->datPhong($params);
        $status = $result['statusCode'] ?? (($result['success'] ?? false) ? 200 : 400);
        unset($result['statusCode']);
        return Response::json($response, $result, $status);
    }

    /**
     * API: Tra cứu đơn đặt phòng theo SĐT hoặc mã phiếu.
     * Endpoint: GET /khachhang/tracuu hoặc POST /khachhang/tracuu
     */
    public function traCuu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $keyword = $params['sdt'] ?? ($params['keyword'] ?? ($params['MaPhieu'] ?? ''));
        $result = $this->service->traCuu($keyword);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API: Xem lịch sử đặt phòng của khách hàng.
     * Endpoint: POST /khachhang/xemlichsudatphong hoặc GET /khachhang/lichsu
     */
    public function xemLichSuDatPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $keyword = $params['sdt'] ?? ($params['SDT'] ?? ($params['MaKH'] ?? ''));
        $result = $this->service->traCuu($keyword);
        return Response::json($response, $result, 200);
    }
}
