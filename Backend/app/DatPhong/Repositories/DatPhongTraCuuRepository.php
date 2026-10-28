<?php

declare(strict_types=1);

namespace App\DatPhong\Repositories;

use App\DatPhong\Models\PhieuNhanPhong;
use Database\Database;
use PDO;

/**
 * Repository chuyên trách việc truy vấn, tra cứu danh sách và chi tiết phiếu đặt phòng.
 */
class DatPhongTraCuuRepository
{
    private ?Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function findPhieuNhanPhongById(string $id): ?PhieuNhanPhong
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM PhieuNhanPhong WHERE MaPhieu = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $hetHan = !empty($row['ThoiGianHetHan']) ? new \DateTimeImmutable($row['ThoiGianHetHan']) : null;
        return new PhieuNhanPhong(
            $row['MaPhieu'],
            new \DateTimeImmutable($row['NgayLap']),
            $row['KenhDat'],
            $row['TrangThai'],
            $row['MaKH'],
            $row['MaLeTan'] ?? null,
            $row['MaKM'] ?? null,
            $hetHan
        );
    }

    /**
     * Lấy danh sách phiếu đặt phòng của một khách hàng (kèm thông tin tóm tắt).
     */
    public function getDanhSachPhieuCuaKhach(string $maKH, int $limit = 20, int $offset = 0): array
    {
        $db = $this->getConnection();
        $sql = "SELECT 
                    pnp.MaPhieu, 
                    pnp.NgayLap, 
                    pnp.TrangThai,
                    pnp.TrangThai AS TrangThaiPhieu,
                    pnp.ThoiGianHetHan,
                    (SELECT MIN(ctp.ThoiGianNhanPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayNhan,
                    (SELECT MAX(ctp.ThoiGianTraPhong) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS NgayTra,
                    (SELECT GROUP_CONCAT(ctp.MaPhong SEPARATOR ', ') FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS DanhSachPhong,
                    (SELECT COALESCE(SUM(ctp.SoNguoi), 0) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS SoNguoi,
                    (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu) AS TongSoPhong,
                    (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp WHERE ctp.MaPhieu = pnp.MaPhieu AND ctp.TrangThai = 'DaTraPhong') AS SoPhongDuDieuKien,
                    (SELECT COUNT(DISTINCT dg.MaPhong) FROM DanhGia dg 
                     JOIN ChiTietPhieuNhanPhong ctp ON dg.MaPhieu = ctp.MaPhieu AND dg.MaPhong = ctp.MaPhong 
                     WHERE dg.MaPhieu = pnp.MaPhieu AND ctp.TrangThai = 'DaTraPhong') AS SoPhongDaDanhGia,
                    hd.MaHD,
                    hd.TongTienSauVAT AS TongTien,
                    COALESCE((
                        SELECT tt.TrangThaiThanhToan 
                        FROM ThanhToan tt 
                        WHERE tt.MaHD = hd.MaHD 
                        ORDER BY tt.ThoiGianThanhToan DESC 
                        LIMIT 1
                    ), 'ChuaThanhToan') AS TrangThaiThanhToan
                FROM PhieuNhanPhong pnp
                LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
                WHERE pnp.MaKH = :maKH
                ORDER BY pnp.NgayLap DESC, pnp.MaPhieu DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $db->prepare($sql);
        $stmt->bindValue(':maKH', $maKH);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Lấy toàn bộ chi tiết phiếu nhận phòng để hiển thị (hỗ trợ kiểm tra quyền sở hữu)
     *
     * @param string      $maPhieu Mã phiếu nhận phòng
     * @param string|null $maKH    Mã khách hàng (nếu truyền vào sẽ xác minh quyền sở hữu)
     * @return array|false|null    null: không tìm thấy; false: không có quyền (403); array: dữ liệu
     */
    public function getChiTietPhieu(string $maPhieu, ?string $maKH = null)
    {
        $db = $this->getConnection();

        // 1. Kiểm tra tồn tại và quyền sở hữu
        $stmtCheck = $db->prepare("SELECT MaPhieu, MaKH FROM PhieuNhanPhong WHERE MaPhieu = ?");
        $stmtCheck->execute([$maPhieu]);
        $check = $stmtCheck->fetch(PDO::FETCH_ASSOC);

        if (!$check) {
            return null; // Không tìm thấy phiếu
        }

        if ($maKH !== null && $check['MaKH'] !== $maKH) {
            return false; // Phiếu không thuộc khách hàng này (403)
        }

        $stmt = $db->prepare("
            SELECT pnp.*, kh.HoTen AS TenKH, kh.HoTen AS TenKhachHang, kh.MaTK, kh.SDT, tk.Email,
                   lt.HoTen AS TenLeTan
            FROM PhieuNhanPhong pnp
            JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK
            LEFT JOIN LeTan lt ON pnp.MaLeTan = lt.MaLeTan
            WHERE pnp.MaPhieu = ?
        ");
        $stmt->execute([$maPhieu]);
        $phieu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            return null;
        }

        // Danh sách phòng kèm thông tin Lễ tân Check-in & Check-out
        $stmtPhong = $db->prepare("
            SELECT ctpnp.*, p.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia,
                   lt_in.HoTen AS TenLeTanCheckin,
                   lt_out.HoTen AS TenLeTanCheckout
            FROM ChiTietPhieuNhanPhong ctpnp
            JOIN Phong p ON ctpnp.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            LEFT JOIN LeTan lt_in ON ctpnp.MaLeTanCheckin = lt_in.MaLeTan
            LEFT JOIN LeTan lt_out ON ctpnp.MaLeTanCheckout = lt_out.MaLeTan
            WHERE ctpnp.MaPhieu = ?
        ");
        $stmtPhong->execute([$maPhieu]);
        $phongList = $stmtPhong->fetchAll(PDO::FETCH_ASSOC);

        // Danh sách người lưu trú
        $stmtNLT = $db->prepare("SELECT * FROM NguoiLuuTru WHERE MaPhieu = ?");
        $stmtNLT->execute([$maPhieu]);
        $nltList = $stmtNLT->fetchAll(PDO::FETCH_ASSOC);

        // Hóa đơn & thanh toán
        $stmtHD = $db->prepare("
            SELECT hd.*, tt.MaThanhToan, tt.SoTien, tt.ThoiGianThanhToan, tt.HinhThuc, tt.TrangThaiThanhToan
            FROM HoaDon hd
            LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
            WHERE hd.MaPhieu = ?
        ");
        $stmtHD->execute([$maPhieu]);
        $hoaDon = $stmtHD->fetch(PDO::FETCH_ASSOC);

        $chiTietHD = [];
        if ($hoaDon && !empty($hoaDon['MaHD'])) {
            $stmtCTHD = $db->prepare("
                SELECT ct.*, p.MaLoaiPhong, lp.TenLoaiPhong, ctdv.TenCTDV, dv.TenDV,
                    CASE 
                        WHEN ct.MaPhong IS NOT NULL AND ct.SoDem > 0 THEN (ct.SoDem * ct.DonGia)
                        ELSE (ct.SoLuong * ct.DonGia)
                    END AS ThanhTien
                FROM CTHD ct
                LEFT JOIN Phong p ON ct.MaPhong = p.MaPhong
                LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN ChiTietDichVu ctdv ON ct.MaCTDV = ctdv.MaCTDV
                LEFT JOIN DichVu dv ON ctdv.MaDV = dv.MaDV
                WHERE ct.MaHD = ?
                ORDER BY ct.MaCTHD ASC
            ");
            $stmtCTHD->execute([$hoaDon['MaHD']]);
            $chiTietHD = $stmtCTHD->fetchAll(PDO::FETCH_ASSOC);
        }

        // Tính toán tổng hợp chi phí: Tiền phòng, VAT phòng (8%), Tiền DV, VAT DV (8%)
        $tongTienPhong = 0;
        $tongTienDichVu = 0;
        foreach ($chiTietHD as $it) {
            $tt = (float)($it['ThanhTien'] ?? 0);
            if (!empty($it['MaPhong'])) {
                $tongTienPhong += $tt;
            } else {
                $tongTienDichVu += $tt;
            }
        }

        $tienGiamKM = 0;
        if (!empty($phieu['MaKM'])) {
            $stmtKM = $db->prepare("SELECT * FROM KhuyenMai WHERE MaKM = ?");
            $stmtKM->execute([$phieu['MaKM']]);
            $kmRow = $stmtKM->fetch(PDO::FETCH_ASSOC);
            if ($kmRow) {
                if (($kmRow['LoaiKM'] ?? '') === 'PhanTram') {
                    $tienGiamKM = $tongTienPhong * ((float)($kmRow['GiaTriGiam'] ?? 0) / 100);
                } else {
                    $tienGiamKM = min($tongTienPhong, (float)($kmRow['GiaTriGiam'] ?? 0));
                }
            }
        }

        $tienPhongSauKM = max(0, $tongTienPhong - $tienGiamKM);
        $vatPhongRate = 0.08;
        $vatPhong = round($tienPhongSauKM * $vatPhongRate, 2);
        $tongTienPhongSauVAT = $tienPhongSauKM + $vatPhong;

        $vatDichVuRate = 0.08;
        $vatDichVu = round($tongTienDichVu * $vatDichVuRate, 2);
        $tongTienDichVuSauVAT = $tongTienDichVu + $vatDichVu;

        $tongTienCuoiCung = (float)($hoaDon['TongTienSauVAT'] ?? ($tongTienPhongSauVAT + $tongTienDichVuSauVAT));

        return [
            'Phieu' => $phieu,
            'DanhSachPhong' => $phongList,
            'NguoiLuuTru' => $nltList,
            'HoaDon' => $hoaDon,
            'ChiTietHoaDon' => $chiTietHD,
            'TongHop' => [
                'tongTienPhong' => $tongTienPhong,
                'tienGiamKM' => $tienGiamKM,
                'tienPhongSauKM' => $tienPhongSauKM,
                'vatPhongRate' => $vatPhongRate,
                'vatPhong' => $vatPhong,
                'tongTienPhongSauVAT' => $tongTienPhongSauVAT,
                'tongTienDichVu' => $tongTienDichVu,
                'vatDichVuRate' => $vatDichVuRate,
                'vatDichVu' => $vatDichVu,
                'tongTienDichVuSauVAT' => $tongTienDichVuSauVAT,
                'tongTienSauVAT' => $tongTienCuoiCung
            ]
        ];
    }

    /**
     * Lấy danh sách các mã phòng thuộc phiếu
     */
    public function getDanhSachPhongCuaPhieu(string $maPhieu, ?PDO $db = null): array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare("SELECT MaPhong FROM ChiTietPhieuNhanPhong WHERE MaPhieu = ?");
        $stmt->execute([$maPhieu]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
