<?php

declare(strict_types=1);

namespace App\Admin\Services;

use App\Admin\Repositories\AdminThongKeRepository;
use App\Admin\Repositories\AdminRepository;

/**
 * Service xử lý logic Báo Cáo & Thống Kê (BE4).
 */
class AdminThongKeService
{
    private $repository;

    public function __construct($repository = null)
    {
        if ($repository instanceof AdminThongKeRepository) {
            $this->repository = $repository;
        } elseif ($repository instanceof AdminRepository) {
            $this->repository = $repository->getThongKeRepo();
        } else {
            $this->repository = new AdminThongKeRepository();
        }
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
     * Thống kê doanh thu theo từng Loại Phòng (Tab 1: Thống Kê Loại Phòng).
     * Bao gồm: Tổng doanh thu phòng, Tổng số đêm sử dụng, Chi tiết theo loại phòng, Tỷ trọng %.
     */
    public function thongKeDoanhThuLoaiPhong(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-01-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-12-31'));

        // Kiểm tra hợp lệ ngày bắt đầu và kết thúc
        if (strtotime($fromDate) > strtotime($toDate)) {
            return [
                'status' => 'error',
                'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc.',
                'data' => null
            ];
        }

        $data = $this->repository->thongKeDoanhThuLoaiPhong([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return [
            'status' => 'success',
            'message' => 'Lấy thống kê doanh thu theo loại phòng thành công',
            'data' => $data,
        ];
    }

    /**
     * Thống kê doanh thu theo từng Dịch Vụ (Tab 2: Thống Kê Dịch Vụ).
     * Bao gồm: Tổng doanh thu dịch vụ, Tổng lượt sử dụng, Tổng số lượng, Chi tiết theo loại dịch vụ, Tỷ trọng %.
     */
    public function thongKeDoanhThuDichVu(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-01-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-12-31'));

        // Kiểm tra hợp lệ ngày bắt đầu và kết thúc
        if (strtotime($fromDate) > strtotime($toDate)) {
            return [
                'status' => 'error',
                'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc.',
                'data' => null
            ];
        }

        $data = $this->repository->thongKeDoanhThuDichVu([
            'from_date' => $fromDate,
            'to_date' => $toDate,
        ]);

        return [
            'status' => 'success',
            'message' => 'Lấy thống kê doanh thu theo dịch vụ thành công',
            'data' => $data,
        ];
    }

    /**
     * Thống kê công suất phòng theo đặc tả usecase UC21.2.
     * Công thức: Công suất phòng = (Số đêm phòng thực tế sử dụng / Số đêm phòng có thể bán) * 100%
     */
    public function thongKeCongSuatPhong(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-m-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-m-d'));
        $maLoaiPhong = $params['ma_loai_phong'] ?? ($params['MaLoaiPhong'] ?? null);

        // Kiểm tra hợp lệ ngày bắt đầu và kết thúc (Dòng sự kiện 4.1)
        if (substr($fromDate, 0, 10) > substr($toDate, 0, 10)) {
            return [
                'status' => 'error',
                'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc. Vui lòng chọn lại.',
                'data' => null
            ];
        }

        try {
            $data = $this->repository->thongKeCongSuatPhong([
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'ma_loai_phong' => $maLoaiPhong
            ]);

            return [
                'status' => 'success',
                'message' => 'Lấy thống kê công suất phòng thành công',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Lỗi truy xuất dữ liệu công suất phòng: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Thống kê đánh giá theo loại phòng hoặc theo từng phòng (UC21.3).
     */
    public function thongKeDanhGia(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? date('Y-01-01'));
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-m-d'));
        $mode = $params['mode'] ?? ($params['che_do'] ?? 'room_type'); // 'room_type' hoặc 'room'
        $maLoaiPhong = $params['ma_loai_phong'] ?? ($params['MaLoaiPhong'] ?? null);
        $keyword = $params['keyword'] ?? ($params['tu_khoa'] ?? '');

        // Kiểm tra hợp lệ ngày bắt đầu và ngày kết thúc (Dòng sự kiện 3.1)
        if (substr($fromDate, 0, 10) > substr($toDate, 0, 10)) {
            return [
                'status' => 'error',
                'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc. Vui lòng chọn lại.',
                'data' => null
            ];
        }

        try {
            if ($mode === 'room') {
                $data = $this->repository->thongKeDanhGiaTheoPhong([
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'ma_loai_phong' => $maLoaiPhong,
                    'keyword' => $keyword
                ]);
            } else {
                $data = $this->repository->thongKeDanhGiaTheoLoaiPhong([
                    'from_date' => $fromDate,
                    'to_date' => $toDate,
                    'ma_loai_phong' => $maLoaiPhong
                ]);
            }

            return [
                'status' => 'success',
                'message' => 'Lấy thống kê đánh giá thành công',
                'data' => $data,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Lỗi truy xuất dữ liệu đánh giá: ' . $e->getMessage(),
                'data' => null
            ];
        }
    }

    /**
     * Lấy danh sách đánh giá chi tiết theo loại phòng hoặc phòng (UC21.3).
     */
    public function xemChiTietDanhGia(array $params = []): array
    {
        $fromDate = $params['from_date'] ?? ($params['tu_ngay'] ?? '2020-01-01');
        $toDate = $params['to_date'] ?? ($params['den_ngay'] ?? date('Y-m-d'));

        $doiTuong = $params['doi_tuong'] ?? null;
        $maDoiTuong = $params['ma_doi_tuong'] ?? null;

        $maLoaiPhong = $params['ma_loai_phong'] ?? ($params['MaLoaiPhong'] ?? null);
        $maPhong = $params['ma_phong'] ?? ($params['MaPhong'] ?? null);

        if ($doiTuong === 'room_type' && !empty($maDoiTuong) && $maDoiTuong !== 'all') {
            $maLoaiPhong = $maDoiTuong;
        } elseif ($doiTuong === 'room' && !empty($maDoiTuong) && $maDoiTuong !== 'all') {
            $maPhong = $maDoiTuong;
        }

        if (substr($fromDate, 0, 10) > substr($toDate, 0, 10)) {
            return [
                'status' => 'error',
                'message' => 'Ngày bắt đầu không được lớn hơn ngày kết thúc.',
                'data' => []
            ];
        }

        try {
            $list = $this->repository->danhSachChiTietDanhGia([
                'from_date' => $fromDate,
                'to_date' => $toDate,
                'ma_loai_phong' => $maLoaiPhong,
                'ma_phong' => $maPhong
            ]);

            $totalCount = count($list);
            $sumScore = 0;
            foreach ($list as $r) {
                $sumScore += (int)($r['SoSao'] ?? 0);
            }
            $avgScore = $totalCount > 0 ? round($sumScore / $totalCount, 2) : null;

            return [
                'status' => 'success',
                'message' => 'Lấy danh sách chi tiết đánh giá thành công',
                'tong_quan' => [
                    'tong_so_luong' => $totalCount,
                    'diem_trung_binh' => $avgScore
                ],
                'danh_sach' => $list,
                'data' => $list
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => 'Lỗi truy xuất chi tiết đánh giá: ' . $e->getMessage(),
                'data' => []
            ];
        }
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
}
