<?php

declare(strict_types=1);

namespace App\LeTan\Services;

use App\LeTan\Repositories\LeTanCheckoutRepository;
use App\LeTan\Repositories\LeTanPhieuRepository;
use App\HoaDon\Repositories\HoaDonRepository;
use App\ThanhToan\Repositories\ThanhToanRepository;
use Database\Database;
use Exception;
use PDO;

/**
 * Service chuyên trách quản lý Check-out (Trả phòng), Phụ phí trễ giờ và Quyết toán hóa đơn.
 */
class LeTanCheckoutService
{
    private LeTanCheckoutRepository $checkoutRepo;
    private LeTanPhieuRepository $phieuRepo;
    private HoaDonRepository $hoaDonRepo;
    private ThanhToanRepository $thanhToanRepo;

    public function __construct(
        ?LeTanCheckoutRepository $checkoutRepo = null,
        ?LeTanPhieuRepository $phieuRepo = null,
        ?HoaDonRepository $hoaDonRepo = null,
        ?ThanhToanRepository $thanhToanRepo = null
    ) {
        $this->checkoutRepo = $checkoutRepo ?? new LeTanCheckoutRepository();
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository();
        $this->hoaDonRepo = $hoaDonRepo ?? new HoaDonRepository();
        $this->thanhToanRepo = $thanhToanRepo ?? new ThanhToanRepository();
    }

    /**
     * Phân giải mã định danh (MaLeTan hoặc MaTK) sang MaLeTan hợp lệ
     */
    public function resolveMaLeTan(string $identifier = 'LT001'): string
    {
        if (empty($identifier)) return 'LT001';
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT MaLeTan FROM LeTan WHERE MaLeTan = ? OR MaTK = ? LIMIT 1");
        $stmt->execute([$identifier, $identifier]);
        $val = $stmt->fetchColumn();
        return $val ?: ($identifier ?: 'LT001');
    }

    /**
     * Tính phụ phí trả phòng trễ (Late Check-out) theo chính sách khách sạn:
     * - Giờ trả phòng tiêu chuẩn: 12:00
     * - Ân hạn 30 phút: Trả phòng trước hoặc đúng 12:30 được MIỄN PHÍ
     * - Quá 30 phút (từ phút 31 trở đi): Tính 1 giờ = 10% đơn giá loại phòng đó
     * - Mỗi 60 phút tiếp theo cộng thêm 10% (Tối đa 100% đơn giá phòng)
     */
    public function tinhPhuPhiTreGio(string $thoiGianTraDuKien, float $donGiaPhong, ?int $thoiGianThucTeTs = null): array
    {
        $nowTs = $thoiGianThucTeTs ?? time();
        $traDuKienTs = strtotime($thoiGianTraDuKien);
        $soGiayTre = $nowTs - $traDuKienTs;

        if ($soGiayTre <= 30 * 60) {
            return [
                'thoiGianTraDuKien' => $thoiGianTraDuKien,
                'soPhutTre' => max(0, (int)round($soGiayTre / 60)),
                'soGioTinhPhi' => 0,
                'soGioTre' => 0,
                'tyLePhuThu' => 0.0,
                'tiLePhanTram' => 0.0,
                'tienPhuThu' => 0.0,
                'ghiChu' => ($soGiayTre > 0) ? 'Trong thời gian ân hạn 30 phút (Miễn phí phụ thu)' : 'Đúng hoặc trước giờ trả phòng'
            ];
        }

        // Từ phút 31 trở đi
        $soGiaySauAnHan = $soGiayTre - (30 * 60);
        $soGioTinhPhi = (int)ceil($soGiaySauAnHan / 3600);
        $tyLe = min(1.0, $soGioTinhPhi * 0.10); // tối đa 100%
        $tienPhuThu = round($donGiaPhong * $tyLe);

        return [
            'thoiGianTraDuKien' => $thoiGianTraDuKien,
            'soPhutTre' => (int)round($soGiayTre / 60),
            'soGioTinhPhi' => $soGioTinhPhi,
            'soGioTre' => $soGioTinhPhi,
            'tyLePhuThu' => round($tyLe * 100, 1),
            'tiLePhanTram' => round($tyLe * 100, 1),
            'tienPhuThu' => (float)$tienPhuThu,
            'ghiChu' => "Trễ {$soGioTinhPhi} giờ (Quá 30 phút ân hạn) - Phụ thu " . round($tyLe * 100) . "% đơn giá phòng"
        ];
    }

    /**
     * Tính toán quyết toán chi phí khi Check-out:
     * - Tiền phòng ban đầu
     * - Phụ phí trả phòng trễ (Late check-out surcharge)
     * - Tổng tiền các dịch vụ phát sinh (Đồ ăn, Giặt ủi, Thuê xe máy)
     * - Số tiền đã thanh toán trước đó
     * - Số tiền còn lại cần thanh toán
     */
    public function tinhTienCheckout(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        $hoaDon = $detail['hoaDon'];
        $danhSachDichVu = $detail['danhSachDichVu'];
        $danhSachThanhToan = $detail['danhSachThanhToan'];

        // 1. Phân tích chi tiết tiền phòng từng phòng theo chuẩn hóa đơn
        $danhSachChiTietPhong = [];
        $tamTinhTienPhong = 0;

        foreach ($detail['danhSachPhong'] as $p) {
            if (!empty($maPhong) && $p['MaPhong'] !== $maPhong) {
                continue;
            }
            $thoiGianNhan = $p['ThoiGianNhanPhong'];
            $thoiGianTra = $p['ThoiGianTraPhong'];
            $nhanTs = strtotime($thoiGianNhan);
            $traTs = strtotime($thoiGianTra);
            $soDem = max(1, (int)round(($traTs - $nhanTs) / 86400));
            $donGia = (float)$p['Gia'];
            $thanhTien = $soDem * $donGia;
            $tamTinhTienPhong += $thanhTien;

            $danhSachChiTietPhong[] = [
                'maPhong' => $p['MaPhong'],
                'tenLoaiPhong' => $p['TenLoaiPhong'] ?? ($p['MaLoaiPhong'] ?? 'Phòng tiêu chuẩn'),
                'maLoaiPhong' => $p['MaLoaiPhong'],
                'thoiGianNhan' => $thoiGianNhan,
                'thoiGianTra' => $thoiGianTra,
                'soDem' => $soDem,
                'donGia' => $donGia,
                'thanhTien' => $thanhTien,
                'trangThaiPhong' => $p['TrangThai']
            ];
        }

        // Tính tiền giảm khuyến mãi (nếu có)
        $tienGiamKM = 0;
        $kmInfo = null;
        if (!empty($phieu['MaKM'])) {
            $stmtKM = $this->checkoutRepo->getConnection()->prepare("SELECT * FROM KhuyenMai WHERE MaKM = ?");
            $stmtKM->execute([$phieu['MaKM']]);
            $kmRow = $stmtKM->fetch(PDO::FETCH_ASSOC);
            if ($kmRow) {
                $kmInfo = $kmRow;
                if (($kmRow['LoaiKM'] ?? '') === 'PhanTram') {
                    $tienGiamKM = $tamTinhTienPhong * ((float)($kmRow['GiaTriGiam'] ?? 0) / 100);
                } else {
                    $tienGiamKM = min($tamTinhTienPhong, (float)($kmRow['GiaTriGiam'] ?? 0));
                }
            }
        }

        $tienPhongSauKM = max(0, $tamTinhTienPhong - $tienGiamKM);
        $vatPhongRate = 0.08;
        $vatPhong = round($tienPhongSauKM * $vatPhongRate, 2);
        $tongTienPhongSauVAT = $tienPhongSauKM + $vatPhong;

        // Nếu trả toàn bộ phiếu và hóa đơn có sẵn tổng tiền sau VAT thì dùng giá trị hóa đơn gốc
        if (empty($maPhong) && $hoaDon && (float)$hoaDon['TongTienSauVAT'] > 0) {
            $tienPhongGoc = (float)$hoaDon['TongTienSauVAT'];
        } else {
            $tienPhongGoc = $tongTienPhongSauVAT;
        }

        // 2. Tính tiền dịch vụ phát sinh trong quá trình ở (lọc theo phòng nếu có)
        $filteredDichVu = [];
        $tongTienDichVu = 0;
        foreach ($danhSachDichVu as $dv) {
            if (!empty($maPhong) && ($dv['MaPhong'] ?? '') !== $maPhong) {
                continue;
            }
            $tongTienDichVu += (float)$dv['ThanhTien'];
            $filteredDichVu[] = $dv;
        }
        $vatDichVuRate = 0.08;
        $vatDichVu = round($tongTienDichVu * $vatDichVuRate, 2);
        $tongTienDichVuSauVAT = $tongTienDichVu + $vatDichVu;

        // 3. Tính phụ phí trả phòng trễ (Late check-out)
        $tongPhuPhiTreGio = 0;
        $chiTietTreGio = [];

        foreach ($detail['danhSachPhong'] as $p) {
            if (!empty($maPhong) && $p['MaPhong'] !== $maPhong) {
                continue;
            }
            if ($p['TrangThai'] === 'DangLuuTru') {
                $treInfo = $this->tinhPhuPhiTreGio($p['ThoiGianTraPhong'], (float)$p['Gia']);
                $treInfo['maPhong'] = $p['MaPhong'];
                $treInfo['tenLoaiPhong'] = $p['TenLoaiPhong'] ?? $p['MaLoaiPhong'];
                $treInfo['donGiaPhong'] = (float)$p['Gia'];
                $chiTietTreGio[] = $treInfo;
                $tongPhuPhiTreGio += $treInfo['tienPhuThu'];
            }
        }

        // 4. Tổng chi phí toàn bộ kỳ lưu trú (Phòng gốc + Dịch vụ + Phụ phí trễ)
        $tongChiPhiSauVAT = $tienPhongGoc + $tongTienDichVu + $tongPhuPhiTreGio;

        // 5. Số tiền đã thanh toán hoàn tất trước đó
        $daThanhToan = 0;
        foreach ($danhSachThanhToan as $tt) {
            if ($tt['TrangThaiThanhToan'] === 'HoanTat') {
                $daThanhToan += (float)$tt['SoTien'];
            }
        }

        // 6. Số tiền phụ thu / chênh lệch còn lại cần thanh toán
        $conLai = max(0, $tongChiPhiSauVAT - $daThanhToan);

        return [
            'success' => true,
            'data' => [
                'maPhieu' => $maPhieu,
                'maPhong' => $maPhong ?: null,
                'tenKH' => $phieu['TenKH'],
                'sdt' => $phieu['SDT'] ?? '',
                'email' => $phieu['Email'] ?? '',
                'ngayLap' => $phieu['NgayLap'] ?? date('Y-m-d'),
                'kenhDat' => $phieu['KenhDat'] ?? 'TrucTuyen',
                'tenLeTan' => $phieu['TenLeTan'] ?? 'Lễ tân',
                'trangThaiPhieu' => $phieu['TrangThai'],
                'maHD' => $hoaDon ? $hoaDon['MaHD'] : null,
                'tienPhongGoc' => $tienPhongGoc,
                
                // Đầy đủ chi tiết phòng theo chuẩn hóa đơn
                'danhSachChiTietPhong' => $danhSachChiTietPhong,
                'tamTinhTienPhong' => $tamTinhTienPhong,
                'maKM' => $phieu['MaKM'] ?? null,
                'tenKM' => $kmInfo['TenKM'] ?? ($phieu['MaKM'] ?? null),
                'tienGiamKM' => $tienGiamKM,
                'tienPhongSauKM' => $tienPhongSauKM,
                'vatPhong' => $vatPhong,
                'vatPhongRate' => $vatPhongRate,
                'tongTienPhongSauVAT' => $tongTienPhongSauVAT,
                
                // Dịch vụ phát sinh
                'danhSachDichVu' => $filteredDichVu,
                'tongTienDichVu' => $tongTienDichVu,
                'vatDichVu' => $vatDichVu,
                'vatDichVuRate' => $vatDichVuRate,
                'tongTienDichVuSauVAT' => $tongTienDichVuSauVAT,
                
                // Phụ phí trễ giờ
                'tongPhuPhiTreGio' => $tongPhuPhiTreGio,
                'chiTietTreGio' => $chiTietTreGio,
                
                // Tổng kết
                'tongChiPhiToanBo' => $tongChiPhiSauVAT,
                'daThanhToan' => $daThanhToan,
                'conLai' => $conLai,
                'danhSachThanhToan' => $danhSachThanhToan
            ]
        ];
    }

    /**
     * Check-out trả phòng và quyết toán:
     * - Thu nốt số tiền dịch vụ phát sinh + phụ phí trễ giờ (nếu có)
     * - Lưu CTHD cho các dịch vụ phát sinh
     * - Cập nhật hóa đơn tổng
     * - Cập nhật trạng thái phiếu và các phòng sang DaTraPhong
     */
    public function checkout(array $params = []): array
    {
        $maPhieu = trim($params['maPhieu'] ?? ($params['MaPhieu'] ?? ''));
        $hinhThuc = trim($params['hinhThuc'] ?? ($params['HinhThuc'] ?? 'TienMat'));
        $rawLeTan = trim($params['maLeTan'] ?? ($params['MaLeTan'] ?? 'LT001'));
        $maLeTan = $this->resolveMaLeTan($rawLeTan);
        $maPhong = trim($params['maPhong'] ?? ($params['MaPhong'] ?? ''));

        if (empty($maPhieu)) {
            throw new Exception("Vui lòng cung cấp mã phiếu nhận phòng (MaPhieu).", 400);
        }

        $detail = $this->phieuRepo->getChiTietPhieu($maPhieu);
        if (!$detail) {
            throw new Exception("Không tìm thấy phiếu nhận phòng '{$maPhieu}'.", 404);
        }

        $phieu = $detail['phieu'];
        if ($phieu['TrangThai'] === 'DaTraPhong') {
            throw new Exception("Phiếu này đã hoàn tất trả phòng trước đó.", 400);
        }
        if ($phieu['TrangThai'] !== 'DangLuuTru') {
            throw new Exception("Chỉ có phiếu đang ở trạng thái 'Đang lưu trú' mới có thể thực hiện trả phòng (Check-out).", 400);
        }

        // Nếu check-out theo từng phòng, kiểm tra trạng thái phòng đó
        if (!empty($maPhong)) {
            $targetPhong = null;
            foreach ($detail['danhSachPhong'] as $p) {
                if ($p['MaPhong'] === $maPhong) {
                    $targetPhong = $p;
                    break;
                }
            }
            if (!$targetPhong) {
                throw new Exception("Phòng '{$maPhong}' không thuộc phiếu #{$maPhieu}.", 404);
            }
            if ($targetPhong['TrangThai'] === 'DaTraPhong') {
                throw new Exception("Phòng {$maPhong} đã được làm thủ tục trả phòng trước đó.", 400);
            }
        }

        $db = Database::getConnection();
        $db->beginTransaction();

        try {
            $tinhToan = $this->tinhTienCheckout(['maPhieu' => $maPhieu, 'maPhong' => $maPhong]);
            $info = $tinhToan['data'];
            $soTienPhuThu = $info['conLai'];
            $maHD = $info['maHD'];

            // Nếu chưa có hóa đơn thì tạo hóa đơn
            if (empty($maHD)) {
                $maHD = $this->hoaDonRepo->generateMaHoaDon($db);
                $this->hoaDonRepo->luuHoaDon($maHD, $maPhieu, $info['tongChiPhiToanBo'], $db);
            } else {
                // Cập nhật tổng tiền hóa đơn lên mức tổng chi phí
                $this->hoaDonRepo->luuHoaDon($maHD, $maPhieu, $info['tongChiPhiToanBo'], $db);
            }

            // Ghi nhận các mục dịch vụ phát sinh vào CTHD
            foreach ($info['danhSachDichVu'] as $dv) {
                $this->hoaDonRepo->luuCTHD(
                    $maHD,
                    $maPhieu,
                    null,
                    $dv['MaCTDV'],
                    0,
                    (int)$dv['SoLuong'],
                    (float)$dv['DonGia'],
                    $db
                );
            }

            // Ghi nhận phụ phí trả phòng trễ (Late check-out) vào CTHD nếu có
            if ($info['tongPhuPhiTreGio'] > 0) {
                // Đảm bảo tồn tại dịch vụ phụ thu trong bảng ChiTietDichVu
                $checkDV = $db->query("SELECT COUNT(*) FROM ChiTietDichVu WHERE MaCTDV = 'CTDV_LATE_CHECKOUT'")->fetchColumn();
                if (!$checkDV) {
                    $db->exec("INSERT INTO DichVu (MaDV, TenDV, MoTa, MoTaChiTiet, DonGia, TrangThai) 
                              VALUES ('DVPhuThu', 'Phụ thu & Bồi thường', 'Dịch vụ phụ thu', 'Phụ thu trả phòng trễ giờ hoặc dịch vụ bổ sung', 0, 'DangCungCap')
                              ON DUPLICATE KEY UPDATE TenDV = VALUES(TenDV)");
                    $db->exec("INSERT INTO ChiTietDichVu (MaCTDV, MaDV, TenCTDV, MoTa, MoTaChiTiet, DonGia, SoLuongTon)
                              VALUES ('CTDV_LATE_CHECKOUT', 'DVPhuThu', 'Phụ phí trả phòng trễ (Late Check-out)', 'Phụ phí trả phòng sau 12:30 (10% đơn giá phòng / giờ)', 'Phụ phí trả phòng sau 12:30', 0, 9999)
                              ON DUPLICATE KEY UPDATE TenCTDV = VALUES(TenCTDV)");
                }

                $this->hoaDonRepo->luuCTHD(
                    $maHD,
                    $maPhieu,
                    null,
                    'CTDV_LATE_CHECKOUT',
                    0,
                    1,
                    (float)$info['tongPhuPhiTreGio'],
                    $db
                );
            }

            // Nếu có số tiền phụ thu dịch vụ cần trả lúc check-out
            $maTTPhuThu = null;
            if ($soTienPhuThu > 0) {
                $maTTPhuThu = $this->thanhToanRepo->generateMaThanhToan($db);
                $thoiGian = date('Y-m-d H:i:s');
                $this->thanhToanRepo->luuThanhToan(
                    $maTTPhuThu,
                    $soTienPhuThu,
                    $thoiGian,
                    $hinhThuc,
                    'HoanTat',
                    $maHD,
                    $db
                );
            }

            // Cập nhật trạng thái phiếu và phòng sang DaTraPhong
            $this->checkoutRepo->capNhatCheckout($maPhieu, $maLeTan, !empty($maPhong) ? $maPhong : null);

            $db->commit();

            $msg = !empty($maPhong)
                ? "Check-out hoàn tất cho phòng {$maPhong} (Phiếu #{$maPhieu})! Phòng đã chuyển sang trạng thái Bảo trì / Chờ dọn dẹp."
                : "Check-out hoàn tất cho toàn bộ phiếu #{$maPhieu}! Tất cả các phòng đã chuyển sang trạng thái Bảo trì / Chờ dọn dẹp.";

            return [
                'success' => true,
                'message' => $msg,
                'data' => [
                    'maPhieu' => $maPhieu,
                    'maPhong' => $maPhong ?: null,
                    'trangThai' => 'DaTraPhong',
                    'tongChiPhi' => $info['tongChiPhiToanBo'],
                    'soTienPhuThu' => $soTienPhuThu,
                    'hinhThucPhuThu' => $hinhThuc,
                    'maThanhToanPhuThu' => $maTTPhuThu,
                    'maLeTan' => $maLeTan
                ]
            ];
        } catch (\Exception $e) {
            $db->rollBack();
            throw $e;
        }
    }
}
