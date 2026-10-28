<?php

declare(strict_types=1);

namespace App\DatPhong\Services;

use App\DichVu\Repositories\DichVuRepository;
use App\KhuyenMai\Repositories\KhuyenMaiRepository;
use App\Phong\Services\PhongService;
use DateTimeImmutable;
use Exception;

/**
 * Service chuyên trách tính toán chi phí đặt phòng, tiền dịch vụ, kiểm tra khuyến mãi và VAT.
 */
class ChiPhiDatPhongService
{
    private PhongService $phongService;
    private DichVuRepository $dichVuRepo;
    private KhuyenMaiRepository $khuyenMaiRepo;
    private TimKiemPhongService $timKiemService;

    public function __construct(
        PhongService $phongService,
        ?DichVuRepository $dichVuRepo = null,
        ?KhuyenMaiRepository $khuyenMaiRepo = null,
        ?TimKiemPhongService $timKiemService = null
    ) {
        $this->phongService = $phongService;
        $this->dichVuRepo = $dichVuRepo ?? new DichVuRepository();
        $this->khuyenMaiRepo = $khuyenMaiRepo ?? new KhuyenMaiRepository();
        $this->timKiemService = $timKiemService ?? new TimKiemPhongService($phongService);
    }

    /**
     * Lấy danh sách dịch vụ đi kèm
     */
    public function layDanhSachDichVu(): array
    {
        return $this->dichVuRepo->getAllDichVu();
    }

    /**
     * Kiểm tra mã khuyến mãi
     */
    public function kiemTraKhuyenMai(string $maKM, int $soDem, float $tienPhong, ?string $maKH = null, ?string $maTK = null, ?string $sdt = null): array
    {
        if (trim($maKM) === '') {
            return ['hopLe' => false, 'giamGia' => 0, 'thongBao' => ''];
        }

        $km = $this->khuyenMaiRepo->findKhuyenMaiById($maKM);
        if (!$km) {
            return ['hopLe' => false, 'giamGia' => 0, 'thongBao' => 'Mã khuyến mãi không tồn tại.'];
        }

        if ($km->getTrangThai() !== 'HoatDong') {
            return ['hopLe' => false, 'giamGia' => 0, 'thongBao' => 'Mã khuyến mãi hiện đang ngưng áp dụng.'];
        }

        $todayStr = date('Y-m-d');
        $ngayBD = substr($km->getNgayBatDau(), 0, 10);
        $ngayKT = substr($km->getNgayKetThuc(), 0, 10);
        if ($todayStr < $ngayBD || $todayStr > $ngayKT) {
            return ['hopLe' => false, 'giamGia' => 0, 'thongBao' => 'Mã khuyến mãi đã hết hạn hoặc chưa đến ngày áp dụng.'];
        }

        // Kiểm tra số lượng còn lại
        if ($km->getSoLuongDaDung() >= $km->getSoLuong()) {
            return ['hopLe' => false, 'giamGia' => 0, 'thongBao' => 'Mã khuyến mãi đã hết lượt sử dụng.'];
        }

        // Phân giải MaKH từ MaTK hoặc SDT nếu MaKH trống
        $db = $this->khuyenMaiRepo->getConnection();
        if (empty($maKH) && !empty($maTK)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maTK]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (empty($maKH) && !empty($sdt)) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE SDT = ?");
            $stmtKH->execute([$sdt]);
            $maKH = $stmtKH->fetchColumn() ?: '';
        }
        if (!empty($maKH) && str_starts_with(strtoupper($maKH), 'TK')) {
            $stmtKH = $db->prepare("SELECT MaKH FROM KhachHang WHERE MaTK = ?");
            $stmtKH->execute([$maKH]);
            $realMaKH = $stmtKH->fetchColumn();
            if ($realMaKH) $maKH = $realMaKH;
        }

        // Kiểm tra khuyến mãi khách mới
        $isNewCustomerPromo = (strtoupper($km->getMaKM()) === 'KM001' 
            || mb_stripos($km->getTenKM(), 'khách mới') !== false 
            || mb_stripos($km->getTenKM(), 'khach hang moi') !== false 
            || mb_stripos($km->getTenKM(), 'khach moi') !== false
            || mb_stripos($km->getTenKM(), 'lần đầu') !== false);

        if ($isNewCustomerPromo) {
            if (empty($maKH)) {
                return [
                    'hopLe' => false,
                    'giamGia' => 0,
                    'thongBao' => 'Mã khuyến mãi chỉ dành cho khách hàng mới trong lần đặt phòng đầu tiên. Vui lòng đăng nhập để áp dụng.'
                ];
            }

            $stmtCount = $db->prepare("SELECT COUNT(*) FROM PhieuNhanPhong WHERE MaKH = ? AND TrangThai != 'DaHuy'");
            $stmtCount->execute([$maKH]);
            $soPhieu = (int)$stmtCount->fetchColumn();

            $stmtUsed = $db->prepare(
                "SELECT COUNT(*) FROM PhieuNhanPhong p 
                 JOIN KhuyenMai k ON p.MaKM = k.MaKM 
                 WHERE p.MaKH = ? AND (
                     k.MaKM = 'KM001' 
                     OR k.TenKM LIKE '%khách mới%' 
                     OR k.TenKM LIKE '%khach hang moi%' 
                     OR k.TenKM LIKE '%khach moi%' 
                     OR k.TenKM LIKE '%lần đầu%'
                 )"
            );
            $stmtUsed->execute([$maKH]);
            $daTungDung = ((int)$stmtUsed->fetchColumn()) > 0;

            if ($soPhieu > 0 || $daTungDung) {
                return [
                    'hopLe' => false,
                    'giamGia' => 0,
                    'thongBao' => 'Mã khuyến mãi chỉ dành riêng cho khách hàng mới trong lần đặt phòng đầu tiên (Mỗi tài khoản chỉ được sử dụng 1 lần duy nhất).'
                ];
            }
        }

        if ($soDem < $km->getSoDemToiThieu()) {
            return [
                'hopLe' => false,
                'giamGia' => 0,
                'thongBao' => "Mã khuyến mãi yêu cầu lưu trú tối thiểu {$km->getSoDemToiThieu()} đêm (Bạn đang đặt {$soDem} đêm)."
            ];
        }

        // Tính tiền giảm
        $giamGia = 0;
        if ($km->getLoaiKM() === 'PhanTram') {
            $giamGia = $tienPhong * ($km->getGiaTriGiam() / 100);
        } else {
            $giamGia = min($tienPhong, $km->getGiaTriGiam());
        }

        return [
            'hopLe' => true,
            'giamGia' => round($giamGia, 2),
            'tenKM' => $km->getTenKM(),
            'thongBao' => "Áp dụng thành công mã {$km->getTenKM()} (-" . number_format($giamGia, 0, ',', '.') . "đ).",
        ];
    }

    /**
     * Tính toán tổng chi phí đặt phòng tạm tính
     */
    public function tinhChiPhiTamTinh(array $params = []): array
    {
        $ngayNhan = trim($params['ngayNhan'] ?? '');
        $ngayTra = trim($params['ngayTra'] ?? '');
        $soNguoiLon = (int) ($params['soNguoiLon'] ?? 1);
        $soTreEm = (int) ($params['soTreEm'] ?? 0);
        $soPhong = (int) ($params['soPhong'] ?? 1);
        $phongDaChon = $params['phongDaChon'] ?? [];
        $maKM = trim($params['maKM'] ?? '');
        $maKH = trim($params['maKH'] ?? ($params['makh'] ?? ''));
        $maTK = trim($params['maTK'] ?? ($params['matk'] ?? ''));
        $sdt = trim($params['sdt'] ?? ($params['SDT'] ?? ''));
        $dichVuDaChon = $params['dichVuDaChon'] ?? [];

        $soDem = $this->timKiemService->tinhSoDem($ngayNhan, $ngayTra);
        $tongKhach = $this->timKiemService->quyDoiSoKhach($soNguoiLon, $soTreEm);

        // Kiểm tra tổng số phòng chọn
        $tongPhongChon = 0;
        $tongSucChua = 0;
        $tienPhongMotDem = 0;
        $chiTietPhong = [];

        foreach ($phongDaChon as $item) {
            $maLP = $item['maLoaiPhong'] ?? '';
            $sl = (int) ($item['soLuong'] ?? 0);
            if ($sl <= 0) continue;

            $lp = $this->phongService->layThongTinLoaiPhong($maLP);
            if (!$lp) {
                throw new Exception("Loại phòng {$maLP} không tồn tại.");
            }

            $tongPhongChon += $sl;
            $tongSucChua += $sl * $lp->getSoNguoiToiDa();
            $tienPhongMotDem += $sl * $lp->getGia();

            $chiTietPhong[] = [
                'maLoaiPhong' => $maLP,
                'tenLoaiPhong' => $lp->getTenLoaiPhong(),
                'soLuong' => $sl,
                'donGia' => $lp->getGia(),
                'soNguoiToiDa' => $lp->getSoNguoiToiDa(),
                'thanhTien' => $sl * $lp->getGia() * $soDem,
            ];
        }

        if ($tongPhongChon !== $soPhong) {
            throw new Exception("Tổng số phòng bạn chọn ({$tongPhongChon} phòng) không khớp với số phòng đã yêu cầu ({$soPhong} phòng).");
        }

        if ($tongSucChua < $tongKhach) {
            throw new Exception("Tổng sức chứa của các phòng đã chọn ({$tongSucChua} người) không đủ cho {$tongKhach} khách.");
        }

        $tongTienPhong = $tienPhongMotDem * $soDem;

        // Tính tiền dịch vụ
        $tongTienDichVu = 0;
        $chiTietDichVu = [];
        $danhSachDichVuAll = $this->layDanhSachDichVu();
        $ctdvMap = [];
        foreach ($danhSachDichVuAll as $dv) {
            foreach ($dv['ChiTiet'] as $ct) {
                $ctdvMap[$ct['MaCTDV']] = [
                    'ten' => $ct['TenCTDV'],
                    'gia' => $ct['DonGia'],
                ];
            }
        }

        foreach ($dichVuDaChon as $dv) {
            $maCTDV = $dv['maCTDV'] ?? '';
            $slDV = (int) ($dv['soLuong'] ?? 0);
            if ($slDV <= 0 || !isset($ctdvMap[$maCTDV])) continue;

            $donGia = $ctdvMap[$maCTDV]['gia'];
            $thanhTienDV = $donGia * $slDV;
            $tongTienDichVu += $thanhTienDV;

            $chiTietDichVu[] = [
                'maCTDV' => $maCTDV,
                'tenCTDV' => $ctdvMap[$maCTDV]['ten'],
                'soLuong' => $slDV,
                'donGia' => $donGia,
                'thanhTien' => $thanhTienDV,
            ];
        }

        // Khuyến mãi
        $kmResult = $this->kiemTraKhuyenMai($maKM, $soDem, $tongTienPhong, $maKH, $maTK, $sdt);
        $tienGiam = $kmResult['hopLe'] ? $kmResult['giamGia'] : 0;

        // 1. VAT Phòng (8% tính trên giá phòng sau khi đã trừ khuyến mãi)
        $tienPhongSauKM = max(0, $tongTienPhong - $tienGiam);
        $vatPhongRate = 0.08;
        $vatPhong = round($tienPhongSauKM * $vatPhongRate, 2);
        $tongTienPhongSauVAT = $tienPhongSauKM + $vatPhong;

        // 2. VAT Dịch Vụ (8% tính trên tổng giá dịch vụ phát sinh)
        $vatDichVuRate = 0.08;
        $vatDichVu = round($tongTienDichVu * $vatDichVuRate, 2);
        $tongTienDichVuSauVAT = $tongTienDichVu + $vatDichVu;

        // 3. Tổng tiền sau VAT cuối cùng của hóa đơn = Tổng phòng sau VAT + Tổng dịch vụ sau VAT
        $tongTienSauVAT = $tongTienPhongSauVAT + $tongTienDichVuSauVAT;
        $vatTong = $vatPhong + $vatDichVu;
        $tienTruocVAT = $tienPhongSauKM + $tongTienDichVu;

        return [
            'soDem' => $soDem,
            'tongKhach' => $tongKhach,
            'tongPhong' => $tongPhongChon,
            'tongSucChua' => $tongSucChua,
            'tienPhong' => $tongTienPhong,
            'chiTietPhong' => $chiTietPhong,
            'tienDichVu' => $tongTienDichVu,
            'chiTietDichVu' => $chiTietDichVu,
            'tienGiamKM' => $tienGiam,
            'thongTinKM' => $kmResult,
            'tienPhongSauKM' => $tienPhongSauKM,
            'vatPhongRate' => $vatPhongRate,
            'vatPhong' => $vatPhong,
            'tongTienPhongSauVAT' => $tongTienPhongSauVAT,
            'vatDichVuRate' => $vatDichVuRate,
            'vatDichVu' => $vatDichVu,
            'tongTienDichVuSauVAT' => $tongTienDichVuSauVAT,
            'tienTruocVAT' => $tienTruocVAT,
            'vat' => $vatTong,
            'tongTienSauVAT' => $tongTienSauVAT,
        ];
    }
}
