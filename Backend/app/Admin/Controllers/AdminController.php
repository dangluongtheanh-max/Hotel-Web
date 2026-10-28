<?php

declare(strict_types=1);

namespace App\Admin\Controllers;

use App\Admin\Services\AdminService;
use Shared\Response;
use Shared\Request;

/**
 * Controller cho phân hệ Admin & Báo Cáo Thống Kê (BE4).
 * Tiếp nhận request HTTP, điều phối AdminService xử lý và trả về JSON hoặc HTML Dashboard.
 */
class AdminController
{
    private AdminService $service;

    public function __construct(?AdminService $service = null)
    {
        $this->service = $service ?? new AdminService();
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

    /* =========================================================================
     * THỐNG KÊ & BÁO CÁO (BE4)
     * ========================================================================= */

    /**
     * API: Thống kê doanh thu theo thời gian, hình thức và cơ cấu nguồn thu.
     * Endpoint: POST /admin/thongkedoanhthu hoặc GET /admin/thongkedoanhthu
     */
    public function thongKeDoanhThu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDoanhThu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê doanh thu loại phòng (Tab 1: Thống Kê Loại Phòng).
     * Endpoint: GET|POST /admin/thongkedoanhthuloaiphong
     */
    public function thongKeDoanhThuLoaiPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDoanhThuLoaiPhong($params);
        $statusCode = ($result['status'] ?? 'success') === 'error' ? 400 : 200;
        return Response::json($response, $result, $statusCode);
    }

    /**
     * API: Thống kê doanh thu dịch vụ (Tab 2: Thống Kê Dịch Vụ).
     * Endpoint: GET|POST /admin/thongkedoanhthudichvu
     */
    public function thongKeDoanhThuDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDoanhThuDichVu($params);
        $statusCode = ($result['status'] ?? 'success') === 'error' ? 400 : 200;
        return Response::json($response, $result, $statusCode);
    }

    /**
     * API: Thống kê công suất phòng theo use case UC21.2.
     * Endpoint: GET|POST /admin/thongkecongsuatphong
     */
    public function thongKeCongSuatPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeCongSuatPhong($params);
        $statusCode = ($result['status'] ?? 'success') === 'error' ? 400 : 200;
        return Response::json($response, $result, $statusCode);
    }

    /**
     * API: Thống kê đánh giá theo use case UC21.3.
     * Endpoint: GET|POST /admin/thongkedanhgia
     */
    public function thongKeDanhGia($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDanhGia($params);
        $statusCode = ($result['status'] ?? 'success') === 'error' ? 400 : 200;
        return Response::json($response, $result, $statusCode);
    }

    /**
     * API: Xem danh sách chi tiết đánh giá theo loại phòng hoặc phòng (UC21.3).
     * Endpoint: GET|POST /admin/chitietdanhgia
     */
    public function xemChiTietDanhGia($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemChiTietDanhGia($params);
        $statusCode = ($result['status'] ?? 'success') === 'error' ? 400 : 200;
        return Response::json($response, $result, $statusCode);
    }

    /**
     * API: Thống kê số lượng phòng và tình trạng phòng hiện tại.
     * Endpoint: POST /admin/thongkesoluongphong hoặc GET /admin/thongkesoluongphong
     */
    public function thongKeSoLuongPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongPhong($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê số lượng phiếu đặt phòng theo trạng thái và kênh đặt.
     * Endpoint: POST /admin/thongkesoluongphieu hoặc GET /admin/thongkesoluongphieu
     */
    public function thongKeSoLuongPhieu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongPhieu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê dịch vụ sử dụng: lượt dùng, doanh thu từ dịch vụ, tồn kho.
     * Endpoint: POST /admin/thongkedichvu hoặc GET /admin/thongkedichvu
     */
    public function thongKeDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeDichVu($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê tổng quan KPI trang chủ Dashboard.
     * Endpoint: GET /admin/thongketongquan hoặc POST /admin/thongketongquan
     */
    public function thongKeTongQuan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeTongQuan($params);
        return Response::json($response, $result, 200);
    }

    /**
     * API: Thống kê số lượng nhân sự.
     * Endpoint: POST /admin/thongkesoluongnhanvien
     */
    public function thongKeSoLuongNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->thongKeSoLuongNhanVien($params);
        return Response::json($response, $result, 200);
    }

    /* =========================================================================
     * QUẢN LÝ NHÂN SỰ / LỄ TÂN (Admin Shared)
     * ========================================================================= */

    public function xemDSNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSLeTan($request = null, $response = null)
    {
        return $this->respondLeTan($response, $this->service->xemDSLeTan($this->getParams($request)));
    }

    public function xemDSKhachHang($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSKhachHang($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSPhieuNhanPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSPhieuNhanPhong($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSHoaDon($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSHoaDon($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSDichVu($params);
        return Response::json($response, $result, 200);
    }

    public function sinhMaChiTietDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maDV = $params['maDV'] ?? $params['MaDV'] ?? 'DVDoAn';
        $result = $this->service->sinhMaChiTietDichVu($maDV);
        return Response::json($response, $result, 200);
    }

    public function themChiTietDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themChiTietDichVu($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function suaChiTietDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maCTDV = $params['maCTDV'] ?? $params['MaCTDV'] ?? '';
        $result = $this->service->suaChiTietDichVu($maCTDV, $params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function doiTrangThaiChiTietDichVu($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maCTDV = $params['maCTDV'] ?? $params['MaCTDV'] ?? '';
        $result = $this->service->doiTrangThaiChiTietDichVu($maCTDV);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function xemDSKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSKhuyenMai($params);
        return Response::json($response, $result, 200);
    }

    public function xemDSPhongTheoLoai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemDSPhongTheoLoai($params);
        return Response::json($response, $result, 200);
    }

    public function timKiemNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->timKiemNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function timKiemLeTan($request = null, $response = null)
    {
        return $this->respondLeTan($response, $this->service->timKiemLeTan($this->getParams($request)));
    }

    public function xemCTNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->xemCTNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function xemCTLeTan($request = null, $response = null)
    {
        return $this->respondLeTan($response, $this->service->xemCTLeTan($this->getParams($request)));
    }

    public function capNhatThongTinNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->capNhatThongTinNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function capNhatThongTinLeTan($request = null, $response = null)
    {
        return $this->respondLeTan($response, $this->service->capNhatThongTinLeTan($this->getParams($request)));
    }

    public function themNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function themLeTan($request = null, $response = null)
    {
        return $this->respondLeTan($response, $this->service->themLeTan($this->getParams($request)));
    }

    public function moTaiKhoanNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->moTaiKhoanNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function moTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->moTaiKhoanNhanVien($request, $response);
    }

    public function khoaTaiKhoanNhanVien($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->khoaTaiKhoanNhanVien($params);
        return Response::json($response, $result, 200);
    }

    public function khoaTaiKhoanLeTan($request = null, $response = null)
    {
        return $this->khoaTaiKhoanNhanVien($request, $response);
    }

    public function sinhMaLeTan($request = null, $response = null)
    {
        $result = $this->service->sinhMaLeTan();
        return Response::json($response, $result, 200);
    }

    public function doiTrangThaiLeTan($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maLeTan = $params['maLeTan'] ?? $params['MaLeTan'] ?? '';
        $result = $this->service->doiTrangThaiLeTan($maLeTan);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function sinhMaLoaiPhong($request = null, $response = null)
    {
        $result = $this->service->sinhMaLoaiPhong();
        return Response::json($response, $result, 200);
    }

    public function themLoaiPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themLoaiPhong($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function suaLoaiPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maLoai = $params['maLoaiPhong'] ?? $params['MaLoaiPhong'] ?? '';
        $result = $this->service->suaLoaiPhong($maLoai, $params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function doiTrangThaiLoaiPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maLoai = $params['maLoaiPhong'] ?? $params['MaLoaiPhong'] ?? '';
        $result = $this->service->doiTrangThaiLoaiPhong($maLoai);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function themPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themPhong($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function suaPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maPhong = $params['maPhong'] ?? $params['MaPhong'] ?? '';
        $result = $this->service->suaPhong($maPhong, $params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function doiTrangThaiPhong($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maPhong = $params['maPhong'] ?? $params['MaPhong'] ?? '';
        $result = $this->service->doiTrangThaiPhong($maPhong);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function sinhMaKhuyenMai($request = null, $response = null)
    {
        $result = $this->service->sinhMaKhuyenMai();
        return Response::json($response, $result, 200);
    }

    public function themKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $result = $this->service->themKhuyenMai($params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function suaKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maKM = $params['maKM'] ?? $params['MaKM'] ?? '';
        $result = $this->service->suaKhuyenMai($maKM, $params);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    public function doiTrangThaiKhuyenMai($request = null, $response = null)
    {
        $params = $this->getParams($request);
        $maKM = $params['maKM'] ?? $params['MaKM'] ?? '';
        $result = $this->service->doiTrangThaiKhuyenMai($maKM);
        $status = ($result['status'] === 'success') ? 200 : 400;
        return Response::json($response, $result, $status);
    }

    private function respondLeTan($response, array $result)
    {
        $statusCode = $result['statusCode'] ?? 200;
        if (($result['status'] ?? '') !== 'success') {
            return Response::error($response, $result['message'] ?? 'Không thể xử lý yêu cầu.', $statusCode);
        }

        return Response::json($response, $result, $statusCode);
    }

    /* =========================================================================
     * GIAO DIỆN TRỰC QUAN DASHBOARD THỐNG KÊ (BE4)
     * ========================================================================= */

    /**
     * Giao diện HTML trực quan Trung tâm Báo Cáo & Thống Kê BE4.
     * Endpoint: GET /admin hoặc GET /admin/dashboard
     */
    public function viewDashboard($request = null, $response = null)
    {
        $viewFile = dirname(__DIR__, 4) . '/Frontend/admin.html';
        if (!file_exists($viewFile)) {
            $viewFile = __DIR__ . '/../View/index.html';
        }
        if (file_exists($viewFile)) {
            header('Content-Type: text/html; charset=utf-8');
            readfile($viewFile);
            exit;
        }

        $overview = $this->service->thongKeTongQuan();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($overview, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }
}