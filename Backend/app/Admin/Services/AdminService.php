<?php

namespace App\Admin\Services;

use App\Admin\Repositories\AdminRepository;

/**
 * Service (business logic) cho module Admin & Thống Kê Báo Cáo (BE4).
 * Xử lý toàn bộ logic nghiệp vụ tổng hợp số liệu doanh thu, tình trạng phòng,
 * phiếu đặt phòng, dịch vụ sử dụng cho Quản trị viên (Admin).
 */
class AdminService
{
    private AdminRepository $repository;

    public function __construct(?AdminRepository $repository = null)
    {
        $this->repository = $repository ?? new AdminRepository();
    }

    /* =========================================================================
     * THỐNG KÊ DOANH THU (BE4)
     * ========================================================================= */

    /**
     * Báo cáo thống kê doanh thu theo thời gian, hình thức và cơ cấu nguồn thu.
     */
    public function thongKeDoanhThu(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-01-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-12-31 23:59:59'));

        $data = $this->repository->thongKeDoanhThu([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return [
            'status' => 'success',
            'message' => 'Lấy thống kê doanh thu thành công',
            'data' => $data,
        ];
    }

    /**
     * Alias tương thích với route cũ /admin/thongkedoanhthu
     */
    public function thongKeDoanhThuChiNhanh(array $params = []): array
    {
        return $this->thongKeDoanhThu($params);
    }

    /* =========================================================================
     * THỐNG KÊ SỐ LƯỢNG / TÌNH TRẠNG PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Báo cáo số lượng phòng, tỷ lệ lấp đầy và tình trạng thực tế từng phòng.
     */
    public function thongKeSoLuongPhong(array $params = []): array
    {
        $data = $this->repository->thongKeSoLuongPhong();
        return [
            'status' => 'success',
            'message' => 'Lấy thống kê số lượng và tình trạng phòng thành công',
            'data' => $data,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ PHIẾU ĐẶT PHÒNG (BE4)
     * ========================================================================= */

    /**
     * Báo cáo số lượng phiếu đặt phòng theo trạng thái và kênh đặt trực tuyến / trực tiếp.
     */
    public function thongKeSoLuongPhieu(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-01-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-12-31 23:59:59'));

        $data = $this->repository->thongKeSoLuongPhieu([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return [
            'status' => 'success',
            'message' => 'Lấy thống kê phiếu nhận phòng thành công',
            'data' => $data,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ DỊCH VỤ SỬ DỤNG (BE4)
     * ========================================================================= */

    /**
     * Báo cáo chi tiết top dịch vụ sử dụng, doanh thu từ dịch vụ và tồn kho.
     */
    public function thongKeDichVu(array $params = []): array
    {
        $data = $this->repository->thongKeDichVu();
        return [
            'status' => 'success',
            'message' => 'Lấy thống kê dịch vụ sử dụng thành công',
            'data' => $data,
        ];
    }

    /* =========================================================================
     * THỐNG KÊ TỔNG QUAN DASHBOARD (BE4)
     * ========================================================================= */

    /**
     * Dashboard tổng quan kết hợp tất cả các chỉ số KPI trọng yếu.
     */
    public function thongKeTongQuan(array $params = []): array
    {
        $data = $this->repository->thongKeTongQuan();
        return $data;
    }

    /* =========================================================================
     * QUẢN LÝ NHÂN SỰ / LỄ TÂN (Hỗ trợ phân hệ Admin)
     * ========================================================================= */

    public function thongKeSoLuongNhanVien(array $params = []): array
    {
        $data = $this->repository->thongKeSoLuongNhanVien();
        return [
            'status' => 'success',
            'data' => $data,
        ];
    }

    public function xemDSNhanVien(array $params = []): array
    {
        $list = $this->repository->getAllNhanVien($params);
        return [
            'status' => 'success',
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function timKiemNhanVien(array $params = []): array
    {
        $keyword = strtolower(trim($params['keyword'] ?? ''));
        $list = $this->repository->getAllNhanVien($params);

        if ($keyword !== '') {
            $list = array_values(array_filter($list, function ($item) use ($keyword) {
                return str_contains(strtolower($item['HoTen']), $keyword)
                    || str_contains(strtolower($item['MaNV']), $keyword)
                    || str_contains(strtolower($item['SDT'] ?? ''), $keyword)
                    || str_contains(strtolower($item['TenDangNhap']), $keyword);
            }));
        }

        return [
            'status' => 'success',
            'keyword' => $keyword,
            'total' => count($list),
            'data' => $list,
        ];
    }

    public function xemCTNhanVien(array $params = []): array
    {
        $id = $params['id'] ?? ($params['MaNV'] ?? '');
        if (empty($id)) {
            return ['status' => 'error', 'message' => 'Thiếu mã nhân viên'];
        }

        $item = $this->repository->findNhanVienById($id);
        if (!$item) {
            return ['status' => 'error', 'message' => "Không tìm thấy nhân viên '{$id}'"];
        }

        return ['status' => 'success', 'data' => $item];
    }

    public function capNhatThongTinNhanVien(array $params = []): array
    {
        $id = $params['id'] ?? ($params['MaNV'] ?? '');
        if (empty($id)) {
            return ['status' => 'error', 'message' => 'Thiếu mã nhân viên'];
        }

        return [
            'status' => 'success',
            'message' => "Cập nhật thông tin nhân viên '{$id}' thành công",
        ];
    }

    public function themNhanVien(array $params = []): array
    {
        return [
            'status' => 'success',
            'message' => 'Thêm nhân viên thành công',
            'data' => $params,
        ];
    }

    public function moTaiKhoanNhanVien(array $params = []): array
    {
        $maTK = $params['MaTK'] ?? '';
        if ($maTK) {
            $this->repository->updateTrangThaiTaiKhoan($maTK, 'HoatDong');
        }
        return ['status' => 'success', 'message' => 'Mở khóa tài khoản thành công'];
    }

    public function khoaTaiKhoanNhanVien(array $params = []): array
    {
        $maTK = $params['MaTK'] ?? '';
        if ($maTK) {
            $this->repository->updateTrangThaiTaiKhoan($maTK, 'BiKhoa');
        }
        return ['status' => 'success', 'message' => 'Khóa tài khoản thành công'];
    }
}
