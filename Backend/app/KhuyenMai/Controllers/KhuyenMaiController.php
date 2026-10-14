<?php

namespace App\KhuyenMai\Controllers;

use App\KhuyenMai\Services\KhuyenMaiService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho module KhuyenMai.
 * Tiếp nhận request HTTP, điều phối Service và trả về JSON hoặc HTML Dashboard.
 */
class KhuyenMaiController
{
    private KhuyenMaiService $service;

    public function __construct(?KhuyenMaiService $service = null)
    {
        $this->service = $service ?? new KhuyenMaiService();
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
     * API Client: Lấy danh sách mã đang hoạt động và sự kiện ưu đãi sắp tới (Banner).
     * Endpoint: GET /khuyenmai/danhsach
     */
    public function xemDSClient($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->layDanhSachClient($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API Client: Kiểm tra và áp dụng mã khuyến mãi khi đặt phòng / thanh toán.
     * Endpoint: POST /khuyenmai/apdung
     */
    public function apDungKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->apDungKhuyenMai($params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API Admin: Lấy toàn bộ danh sách khuyến mãi để quản lý.
     * Endpoint: GET /khuyenmai/admin/danhsach
     */
    public function xemDSAdmin($request = null, $response = null)
    {
        $result = $this->service->layDanhSachAdmin();
        return Response::json($response, $result, 200);
    }

    /**
     * API Admin: Thêm mới mã khuyến mãi (ví dụ Admin set ngày 12/12/2026).
     * Endpoint: POST /khuyenmai/admin/them
     */
    public function themKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themKhuyenMai($params);
        $status = ($result['success'] ?? false) ? 201 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API Admin: Cập nhật thông tin mã khuyến mãi.
     * Endpoint: POST /khuyenmai/admin/capnhat
     */
    public function capNhatKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maKM = $params['MaKM'] ?? '';
        $result = $this->service->capNhatKhuyenMai($maKM, $params);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API Admin: Bật / Tắt trạng thái khuyến mãi.
     * Endpoint: POST /khuyenmai/admin/doitrangthai
     */
    public function doiTrangThai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maKM = $params['MaKM'] ?? '';
        $trangThai = $params['TrangThai'] ?? '';
        $result = $this->service->doiTrangThai($maKM, $trangThai);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * API Admin: Xóa mã khuyến mãi.
     * Endpoint: POST /khuyenmai/admin/xoa
     */
    public function xoaKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maKM = $params['MaKM'] ?? '';
        $result = $this->service->xoaKhuyenMai($maKM);
        $status = ($result['success'] ?? false) ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    /**
     * Giao diện trực quan Dashboard kiểm thử Khuyến mãi (Client & Admin).
     * Endpoint: GET /khuyenmai
     */
    public function viewDashboard($request = null, $response = null)
    {
        $viewFile = __DIR__ . '/../Views/index.html';
        if (file_exists($viewFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewFile);
            exit;
        }

        header('Content-Type: text/plain; charset=utf-8');
        echo "Giao diện kiểm thử Khuyến mãi đang được khởi tạo...";
        exit;
    }
}
