<?php

declare(strict_types=1);

namespace App\DichVu\Services;

use App\DichVu\Repositories\GiatUiRepository;
use App\KhachHang\Repositories\KhachHangRepository;

/**
 * Service xử lý toàn bộ nghiệp vụ Đặt dịch vụ giặt ủi cho khách đang lưu trú.
 */
class GiatUiService
{
    private GiatUiRepository $repository;
    private KhachHangRepository $khachHangRepo;

    public function __construct(
        GiatUiRepository $repository,
        KhachHangRepository $khachHangRepo
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo;
    }

    /**
     * Lấy danh mục 8 loại đồ giặt ủi và bảng giá cố định
     */
    public function layDanhSachGiatUi(): array
    {
        $danhSach = $this->repository->layDanhSachGiatUi();
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Lấy danh sách dịch vụ giặt ủi thành công.',
            'data' => $danhSach
        ];
    }

    /**
     * Xử lý quy trình đặt dịch vụ giặt ủi nhiều món
     */
    public function datDichVuGiatUi(array $params): array
    {
        $maTK = trim($params['maTK'] ?? '');
        $maKH = trim($params['maKH'] ?? '');
        $maPhong = trim($params['maPhong'] ?? '');
        $ghiChu = isset($params['ghiChu']) ? trim($params['ghiChu']) : null;
        $danhSachDo = $params['danhSachDo'] ?? [];

        // Hỗ trợ truyền 1 món đơn lẻ {maCTDV, soLuong} nếu không dùng mảng danhSachDo
        if (empty($danhSachDo) && !empty($params['maCTDV'])) {
            $danhSachDo = [
                [
                    'maCTDV' => trim($params['maCTDV']),
                    'soLuong' => isset($params['soLuong']) ? (int)$params['soLuong'] : 1
                ]
            ];
        }

        // 1. Kiểm tra tài khoản khách hàng
        $resolvedMaKH = $this->resolveMaKH($maTK, $maKH);
        if (!$resolvedMaKH) {
            return [
                'status' => 'error',
                'code' => 401,
                'message' => 'Vui lòng đăng nhập tài khoản khách hàng để đặt dịch vụ giặt ủi.'
            ];
        }

        // 2. Kiểm tra danh sách đồ giặt gửi lên
        if (!is_array($danhSachDo) || empty($danhSachDo)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Danh sách đồ giặt không được để trống. Quý khách vui lòng chọn ít nhất 1 loại đồ.'
            ];
        }

        // 3. Kiểm tra số lượng hợp lệ cho từng loại đồ
        $maCTDVList = [];
        $sanitizedItems = [];
        foreach ($danhSachDo as $item) {
            $mCode = trim($item['maCTDV'] ?? '');
            $qty = isset($item['soLuong']) ? (int)$item['soLuong'] : 0;
            if (empty($mCode)) {
                continue;
            }
            if ($qty <= 0) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => "Số lượng cho loại đồ [{$mCode}] phải là số nguyên lớn hơn 0."
                ];
            }
            $maCTDVList[] = $mCode;
            $sanitizedItems[] = [
                'maCTDV' => $mCode,
                'soLuong' => $qty,
                'ghiChu' => isset($item['ghiChu']) ? trim($item['ghiChu']) : null
            ];
        }

        if (empty($sanitizedItems)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Không tìm thấy loại đồ giặt hợp lệ trong yêu cầu.'
            ];
        }

        // 4. Kiểm tra thông tin lưu trú của khách
        $dsPhongLuuTru = $this->repository->timThongTinLuuTruHienTai($resolvedMaKH);
        if (empty($dsPhongLuuTru)) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Quý khách hiện không có phòng nào tại khách sạn để sử dụng dịch vụ giặt ủi.'
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
                'message' => 'Dịch vụ giặt ủi chỉ áp dụng khi quý khách đã làm thủ tục nhận phòng (Check-in) và đang lưu trú tại khách sạn để nhân viên đến phòng nhận đồ. Quý khách hiện chỉ có phòng đặt trước, vui lòng đặt dịch vụ sau khi làm thủ tục nhận phòng.'
            ];
        }

        // 5. Xác định phòng nhận và trả đồ giặt
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
                    'message' => 'Quý khách có nhiều phòng đang lưu trú. Vui lòng chọn cụ thể phòng và phiếu giao nhận đồ giặt.'
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
                'message' => "Dịch vụ giặt ủi chỉ áp dụng cho phòng quý khách đang lưu trú thực tế tại khách sạn để nhân viên có thể đến phòng nhận đồ. Phòng {$phongChon['MaPhong']} (Phiếu {$phongChon['MaPhieu']}) hiện đang ở trạng thái 'Đã đặt trước'. Quý khách vui lòng đặt dịch vụ sau khi làm thủ tục nhận phòng."
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
                'message' => "Phòng {$maPhongGiao} thuộc phiếu {$maPhieu} hiện không ở trạng thái đang lưu trú thực tế để nhận và trả đồ giặt."
            ];
        }

        // 6. Lấy đơn giá gốc từ Cơ sở dữ liệu (Không tin tưởng đơn giá gửi từ Client)
        $chiTietMap = $this->repository->timChiTietDichVuTheoDanhSach($maCTDVList);
        $danhSachLuu = [];
        $tongTienGiatUi = 0.0;

        foreach ($sanitizedItems as $item) {
            $mCode = $item['maCTDV'];
            if (!isset($chiTietMap[$mCode])) {
                return [
                    'status' => 'error',
                    'code' => 404,
                    'message' => "Loại đồ giặt [{$mCode}] không tồn tại trong hệ thống hoặc đã ngừng cung cấp."
                ];
            }

            $dbItem = $chiTietMap[$mCode];
            $donGiaGoc = (float)$dbItem['DonGia'];
            $thanhTien = $donGiaGoc * $item['soLuong'];
            $tongTienGiatUi += $thanhTien;

            $danhSachLuu[] = [
                'maCTDV' => $mCode,
                'tenCTDV' => $dbItem['TenCTDV'],
                'donGia' => $donGiaGoc,
                'soLuong' => $item['soLuong'],
                'thanhTien' => $thanhTien,
                'ghiChu' => $item['ghiChu']
            ];
        }

        // 7. Lưu từng loại đồ thành một bản ghi riêng trong CTSuDungDichVu qua Transaction
        try {
            $ketQuaLuu = $this->repository->luuDatDichVuGiatUi(
                $danhSachLuu,
                $maPhieu,
                $maPhongGiao,
                $ghiChu
            );
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Lỗi khi lưu đơn dịch vụ giặt ủi: ' . $e->getMessage()
            ];
        }

        // 8. Trả về kết quả thông báo thành công
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Đặt dịch vụ giặt ủi thành công! Đồ giặt của bạn đã được ghi nhận và chi phí sẽ được cộng vào hóa đơn khi trả phòng.',
            'data' => [
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhongGiao,
                'soLoaiDo' => count($ketQuaLuu),
                'tongTien' => $tongTienGiatUi,
                'chiTietDonGiat' => $ketQuaLuu,
                'ghiChuChung' => $ghiChu,
                'quyTacThanhToan' => 'Cộng vào hóa đơn khi trả phòng (không thanh toán ngay)'
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
