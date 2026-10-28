<?php

declare(strict_types=1);

namespace App\DichVu\Services;

use App\DichVu\Repositories\DatDoAnRepository;
use App\KhachHang\Repositories\KhachHangRepository;

/**
 * Service xử lý toàn bộ nghiệp vụ Đặt đồ ăn (cho khách đang lưu trú).
 */
class DatDoAnService
{
    private DatDoAnRepository $repository;
    private KhachHangRepository $khachHangRepo;

    public function __construct(
        DatDoAnRepository $repository,
        KhachHangRepository $khachHangRepo
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo;
    }

    /**
     * Lấy danh sách tất cả các dịch vụ đang có trong hệ thống
     */
    public function layTatCaDichVu(): array
    {
        $danhSach = $this->repository->getAllDichVu();
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Lấy danh sách tất cả dịch vụ thành công.',
            'data' => $danhSach
        ];
    }

    /**
     * Lấy danh sách thực đơn món ăn đang phục vụ (DVDoAn)
     */
    public function layDanhSachMonAn(): array
    {
        $danhSach = $this->repository->layDanhSachMonAn();
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Lấy danh sách món ăn thành công.',
            'data' => $danhSach
        ];
    }

    /**
     * Lấy thông tin phòng đang lưu trú của khách hàng hiện tại
     */
    public function layPhongDangLuuTru(?string $maTK = null, ?string $maKH = null): array
    {
        $resolvedMaKH = $this->resolveMaKH($maTK, $maKH);
        if (!$resolvedMaKH) {
            return [
                'status' => 'error',
                'code' => 401,
                'message' => 'Vui lòng đăng nhập tài khoản khách hàng để kiểm tra phòng lưu trú.',
                'data' => []
            ];
        }

        $phongLuuTru = $this->repository->timThongTinLuuTruHienTai($resolvedMaKH);
        return [
            'status' => 'success',
            'code' => 200,
            'message' => empty($phongLuuTru)
                ? 'Khách hàng hiện không có phòng nào đang lưu trú hoặc đặt trước.'
                : 'Lấy thông tin phòng lưu trú và đặt trước thành công.',
            'data' => $phongLuuTru
        ];
    }

    /**
     * Xử lý quy trình đặt món ăn
     */
    public function datMonAn(array $params): array
    {
        $maTK = trim($params['maTK'] ?? '');
        $maKH = trim($params['maKH'] ?? '');
        $maCTDV = trim($params['maCTDV'] ?? '');
        $soLuong = isset($params['soLuong']) ? (int)$params['soLuong'] : 1;
        $ghiChu = isset($params['ghiChu']) ? trim($params['ghiChu']) : null;
        $maPhong = trim($params['maPhong'] ?? '');

        // 1. Kiểm tra tài khoản khách hàng
        $resolvedMaKH = $this->resolveMaKH($maTK, $maKH);
        if (!$resolvedMaKH) {
            return [
                'status' => 'error',
                'code' => 401,
                'message' => 'Vui lòng đăng nhập tài khoản khách hàng để thực hiện đặt món ăn.'
            ];
        }

        // 2. Kiểm tra số lượng hợp lệ
        if ($soLuong <= 0) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Số lượng món ăn phải lớn hơn hoặc bằng 1.'
            ];
        }

        // 3. Kiểm tra thông tin lưu trú của khách
        $dsPhongLuuTru = $this->repository->timThongTinLuuTruHienTai($resolvedMaKH);
        if (empty($dsPhongLuuTru)) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Quý khách hiện không có phòng nào tại khách sạn để sử dụng dịch vụ đặt món ăn.'
            ];
        }

        // Lọc các phòng thực tế đang lưu trú (DangLuuTru)
        $dsDangO = array_values(array_filter($dsPhongLuuTru, function ($p) {
            return ($p['TrangThaiPhieu'] ?? '') === 'DangLuuTru' && ($p['TrangThai'] ?? '') === 'DangLuuTru';
        }));

        if (empty($dsDangO)) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Dịch vụ đặt món ăn tận phòng chỉ áp dụng khi quý khách đã làm thủ tục nhận phòng (Check-in) và đang lưu trú tại khách sạn. Quý khách hiện chỉ có phòng đặt trước, vui lòng đặt món sau khi làm thủ tục nhận phòng.'
            ];
        }

        // 4. Xác định phòng và phiếu nhận phòng nhận món
        $rawRoom = trim($params['maPhong'] ?? '');
        $rawPhieu = trim($params['maPhieu'] ?? '');
        $filterPhieu = null;
        $filterPhong = null;

        if (str_contains($rawRoom, '|')) {
            [$filterPhieu, $filterPhong] = explode('|', $rawRoom, 2);
            $filterPhieu = trim($filterPhieu);
            $filterPhong = trim($filterPhong);
        } else {
            $filterPhong = $rawRoom !== '' ? $rawRoom : null;
            $filterPhieu = $rawPhieu !== '' ? $rawPhieu : null;
        }

        $phongChon = null;
        if (!empty($filterPhieu) && !empty($filterPhong)) {
            foreach ($dsPhongLuuTru as $p) {
                if ($p['MaPhieu'] === $filterPhieu && $p['MaPhong'] === $filterPhong) {
                    $phongChon = $p;
                    break;
                }
            }
        } elseif (!empty($filterPhong)) {
            foreach ($dsDangO as $p) {
                if ($p['MaPhong'] === $filterPhong) {
                    $phongChon = $p;
                    break;
                }
            }
        } else {
            if (count($dsDangO) === 1) {
                $phongChon = $dsDangO[0];
            } else {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Quý khách có nhiều phòng đang lưu trú. Vui lòng chọn cụ thể phòng muốn nhận món ăn.'
                ];
            }
        }

        if (!$phongChon) {
            $disp = $filterPhieu ? "[Phiếu {$filterPhieu}, Phòng {$filterPhong}]" : "Phòng [{$filterPhong}]";
            return [
                'status' => 'error',
                'code' => 400,
                'message' => "{$disp} không thuộc danh sách phòng của quý khách."
            ];
        }

        // Kiểm tra chặt chẽ: Phòng được chọn bắt buộc phải đang lưu trú thực tế (DangLuuTru)
        if (($phongChon['TrangThaiPhieu'] ?? '') !== 'DangLuuTru' || ($phongChon['TrangThai'] ?? '') !== 'DangLuuTru') {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => "Dịch vụ đặt món ăn tận phòng chỉ áp dụng cho phòng quý khách đang lưu trú thực tế. Phòng {$phongChon['MaPhong']} (Phiếu {$phongChon['MaPhieu']}) hiện đang ở trạng thái 'Đã đặt trước'. Quý khách vui lòng đặt món sau khi làm thủ tục nhận phòng tại khách sạn."
            ];
        }

        $maPhieu = $phongChon['MaPhieu'];
        $maPhongGiao = $phongChon['MaPhong'];

        // Xác thực lại với CSDL qua kiemTraPhongCoDangLuuTru
        $coLuuTru = $this->repository->kiemTraPhongCoDangLuuTru($maPhieu, $maPhongGiao, $resolvedMaKH);
        if (!$coLuuTru) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => "Phòng {$maPhongGiao} thuộc phiếu {$maPhieu} hiện không ở trạng thái đang lưu trú thực tế để giao món ăn."
            ];
        }

        // 5. Kiểm tra món ăn và lấy đơn giá gốc từ Database
        $monAn = $this->repository->timMonAnTheoMa($maCTDV);
        if (!$monAn) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Món ăn không tồn tại hoặc hiện không phục vụ.'
            ];
        }

        $donGia = (float)$monAn['DonGia'];
        $thanhTien = $donGia * $soLuong;

        // 6. Lưu thông tin sử dụng dịch vụ vào CTSuDungDichVu
        $maCTSDDV = $this->repository->luuDatMonAn(
            $maCTDV,
            $maPhieu,
            $maPhongGiao,
            $donGia,
            $ghiChu,
            $soLuong
        );

        // 7. Thông báo thành công theo đúng quy định
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Đặt món thành công! Món ăn sẽ được chuẩn bị và giao đến phòng bạn trong khoảng 15–20 phút.',
            'data' => [
                'maCTSDDV' => $maCTSDDV,
                'maCTDV' => $maCTDV,
                'tenMonAn' => $monAn['TenCTDV'],
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhongGiao,
                'soLuong' => $soLuong,
                'donGia' => $donGia,
                'thanhTien' => $thanhTien,
                'ghiChu' => $ghiChu,
                'thoiGianDuKien' => '15–20 phút'
            ]
        ];
    }

    /**
     * Xác định mã khách hàng từ mã tài khoản hoặc mã khách hàng truyền vào
     */
    private function resolveMaKH(?string $maTK, ?string $maKH): ?string
    {
        if (!empty($maKH)) {
            return $maKH;
        }
        if (!empty($maTK)) {
            $kh = $this->khachHangRepo->findByMaTK($maTK);
            return $kh['MaKH'] ?? null;
        }
        return null;
    }
}
