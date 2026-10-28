<?php

declare(strict_types=1);

namespace App\DichVu\Services;

use App\DichVu\Repositories\ThueXeMayRepository;
use App\KhachHang\Repositories\KhachHangRepository;

/**
 * Service xử lý toàn bộ nghiệp vụ thuê xe máy (DVThueXe)
 * Tuân thủ Phương án B: Giữ nguyên thiết kế, lưu thời gian vào CTSuDungDichVu,
 * tính tổng tiền đồng bộ với quy tắc DonGia * SoLuong của CTHD.
 */
class ThueXeMayService
{
    private ThueXeMayRepository $repository;
    private KhachHangRepository $khachHangRepo;

    public function __construct(
        ThueXeMayRepository $repository,
        KhachHangRepository $khachHangRepo
    ) {
        $this->repository = $repository;
        $this->khachHangRepo = $khachHangRepo;
    }

    /**
     * Lấy danh sách các dòng xe máy phục vụ thuê
     */
    public function layDanhSachXeMay(): array
    {
        $danhSach = $this->repository->layDanhSachXeMay();
        return [
            'status' => 'success',
            'code' => 200,
            'message' => 'Lấy danh sách xe máy cho thuê thành công.',
            'data' => $danhSach
        ];
    }

    /**
     * Đặt dịch vụ thuê xe máy cho khách hàng đang lưu trú
     */
    public function datThueXeMay(array $payload): array
    {
        // 1. Xác thực tài khoản khách hàng
        $maTK = $payload['maTK'] ?? null;
        $maKH = $payload['maKH'] ?? null;

        if (empty($maKH) && !empty($maTK)) {
            $kh = $this->khachHangRepo->findByMaTK($maTK);
            $maKH = $kh['MaKH'] ?? null;
        }

        if (empty($maKH)) {
            return [
                'status' => 'error',
                'code' => 401,
                'message' => 'Vui lòng đăng nhập tài khoản khách hàng để đặt dịch vụ thuê xe máy.'
            ];
        }

        // 2. Kiểm tra thông tin lưu trú (khách phải có phòng DangLuuTru hoặc DaDat)
        $danhSachLuuTru = $this->repository->timThongTinLuuTruHienTai($maKH);
        if (empty($danhSachLuuTru)) {
            return [
                'status' => 'error',
                'code' => 403,
                'message' => 'Quý khách hiện không có phòng nào đang lưu trú hoặc đặt trước tại khách sạn để sử dụng dịch vụ thuê xe máy.'
            ];
        }

        // 3. Xác định phòng nhận & kiểm tra thời gian trả phòng
        $rawRoom = trim($payload['maPhong'] ?? '');
        $rawPhieu = trim($payload['maPhieu'] ?? '');
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

        $phongLuuTru = null;
        if (!empty($filterPhieu) && !empty($filterPhong)) {
            foreach ($danhSachLuuTru as $p) {
                if ($p['MaPhieu'] === $filterPhieu && $p['MaPhong'] === $filterPhong) {
                    $phongLuuTru = $p;
                    break;
                }
            }
        } elseif (!empty($filterPhong)) {
            foreach ($danhSachLuuTru as $p) {
                if ($p['MaPhong'] === $filterPhong && ($p['TrangThaiPhieu'] ?? '') === 'DangLuuTru') {
                    $phongLuuTru = $p;
                    break;
                }
            }
            if (!$phongLuuTru) {
                foreach ($danhSachLuuTru as $p) {
                    if ($p['MaPhong'] === $filterPhong) {
                        $phongLuuTru = $p;
                        break;
                    }
                }
            }
        } else {
            if (count($danhSachLuuTru) === 1) {
                $phongLuuTru = $danhSachLuuTru[0];
            } else {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => 'Quý khách có nhiều phòng/phiếu. Vui lòng chọn cụ thể phòng và phiếu muốn thuê xe máy.'
                ];
            }
        }

        if (!$phongLuuTru) {
            $disp = $filterPhieu ? "[Phiếu {$filterPhieu}, Phòng {$filterPhong}]" : "Phòng [{$filterPhong}]";
            return [
                'status' => 'error',
                'code' => 400,
                'message' => "{$disp} không thuộc danh sách phòng lưu trú hoặc đặt trước của quý khách."
            ];
        }

        $maPhieu = $phongLuuTru['MaPhieu'];
        $maPhong = $phongLuuTru['MaPhong'];
        $thoiGianNhanPhong = $phongLuuTru['ThoiGianNhanPhong'];
        $thoiGianTraPhong = $phongLuuTru['ThoiGianTraPhong'];
        $trangThaiPhieu = $phongLuuTru['TrangThaiPhieu'] ?? $phongLuuTru['TrangThai'] ?? 'DangLuuTru';

        // 4. Kiểm tra loại xe
        $maCTDV = trim($payload['maCTDV'] ?? '');
        if (empty($maCTDV)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Vui lòng chọn loại xe máy cần thuê.'
            ];
        }

        $xe = $this->repository->timXeMayTheoMa($maCTDV);
        if (!$xe) {
            return [
                'status' => 'error',
                'code' => 404,
                'message' => 'Không tìm thấy loại xe máy yêu cầu hoặc xe đang tạm ngưng phục vụ.'
            ];
        }

        // 5. Kiểm tra số lượng xe
        $soLuongXe = (int)($payload['soLuongXe'] ?? 1);
        if ($soLuongXe <= 0) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Số lượng xe thuê phải từ 1 chiếc trở lên.'
            ];
        }

        $soLuongTon = (int)$xe['SoLuongTon'];
        if ($soLuongXe > $soLuongTon) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => "Số lượng xe hiện còn lại trong kho không đủ (Hiện còn: {$soLuongTon} xe). Vui lòng giảm số lượng xe."
            ];
        }

        // 6. Kiểm tra & chuẩn hóa thời gian nhận và trả xe
        $thoiGianNhanRaw = trim($payload['thoiGianNhan'] ?? '');
        $thoiGianTraRaw = trim($payload['thoiGianTra'] ?? '');

        if (empty($thoiGianNhanRaw) || empty($thoiGianTraRaw)) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Vui lòng chọn đầy đủ thời gian nhận xe và thời gian trả xe.'
            ];
        }

        $dtNhan = date_create($thoiGianNhanRaw);
        $dtTra = date_create($thoiGianTraRaw);

        if (!$dtNhan || !$dtTra) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Định dạng thời gian nhận hoặc trả xe không hợp lệ.'
            ];
        }

        $thoiGianNhan = $dtNhan->format('Y-m-d H:i:s');
        $thoiGianTra = $dtTra->format('Y-m-d H:i:s');

        $tsNhan = $dtNhan->getTimestamp();
        $tsTra = $dtTra->getTimestamp();

        if ($tsTra <= $tsNhan) {
            return [
                'status' => 'error',
                'code' => 400,
                'message' => 'Thời gian trả xe phải sau thời gian nhận xe.'
            ];
        }

        // Ràng buộc thời gian nhận xe với phiếu đặt trước
        if (in_array($trangThaiPhieu, ['DaDatPhong', 'DaDat'], true) && !empty($thoiGianNhanPhong)) {
            $tsNhanPhong = strtotime($thoiGianNhanPhong);
            if ($tsNhan < $tsNhanPhong) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => "Thời gian nhận xe ({$thoiGianNhan}) không được sớm hơn thời gian nhận phòng ({$thoiGianNhanPhong}) của phiếu đặt trước."
                ];
            }
        }

        // Ràng buộc: Không được thuê vượt quá hạn trả phòng
        if (!empty($thoiGianTraPhong)) {
            $tsTraPhong = strtotime($thoiGianTraPhong);
            if ($tsTra > $tsTraPhong) {
                return [
                    'status' => 'error',
                    'code' => 400,
                    'message' => "Thời gian trả xe ({$thoiGianTra}) không được muộn hơn thời gian trả phòng của quý khách ({$thoiGianTraPhong})."
                ];
            }
        }

        // 7. Tính số ngày thuê và số lượng quy đổi cho CTHD
        // Quy tắc: 1 ngày = 24 giờ. Bất kỳ phần dư giờ nào đều tính tròn lên 1 ngày
        $diffSeconds = $tsTra - $tsNhan;
        $soNgay = (int)ceil($diffSeconds / 86400);
        if ($soNgay < 1) {
            $soNgay = 1;
        }

        $donGiaNgay = (float)$xe['DonGia'];
        // Số lượng lưu vào CTSuDungDichVu = số xe * số ngày thuê
        // Đảm bảo CTHD: ThanhTien = DonGia * SoLuong hoàn toàn chính xác!
        $soLuongLuu = $soLuongXe * $soNgay;
        $tongTien = $donGiaNgay * $soLuongLuu;

        // Định dạng ghi chú
        $ghiChuText = "Thuê {$soLuongXe} xe trong {$soNgay} ngày (Từ {$thoiGianNhan} đến {$thoiGianTra})";
        $userNote = trim($payload['ghiChu'] ?? '');
        if ($userNote !== '') {
            $ghiChuText .= " - Yêu cầu: " . $userNote;
        }

        // 8. Lưu vào cơ sở dữ liệu
        try {
            $saved = $this->repository->luuThueXeMay(
                $maCTDV,
                $maPhieu,
                $maPhong,
                $donGiaNgay,
                $soLuongLuu,
                $soLuongXe,
                $thoiGianNhan,
                $thoiGianTra,
                $ghiChuText
            );

            return [
                'status' => 'success',
                'code' => 200,
                'message' => 'Đặt dịch vụ thuê xe máy thành công! Vui lòng liên hệ quầy lễ tân để nhận chìa khóa xe.',
                'data' => [
                    'maCTSDDV' => $saved['maCTSDDV'],
                    'maCTDV' => $maCTDV,
                    'tenXe' => $xe['TenCTDV'],
                    'soLuongXe' => $soLuongXe,
                    'soNgay' => $soNgay,
                    'donGiaNgay' => $donGiaNgay,
                    'soLuongLuu' => $soLuongLuu,
                    'tongTien' => $tongTien,
                    'thoiGianNhan' => $thoiGianNhan,
                    'thoiGianTra' => $thoiGianTra,
                    'maPhieu' => $maPhieu,
                    'maPhong' => $maPhong,
                    'ghiChu' => $ghiChuText,
                    'soLuongTonConLai' => $soLuongTon - $soLuongXe
                ]
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'code' => 500,
                'message' => 'Lỗi hệ thống khi lưu yêu cầu thuê xe: ' . $e->getMessage()
            ];
        }
    }
}
