<?php

namespace App\Admin\Repositories;

use App\Admin\Models\Admin;
use Database\Database;
use Shared\Helper;
use PDO;

/**
 * Repository cho module Admin.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class AdminRepository
{
    private PDO $db;

    public function __construct(?Database $db = null)
    {
        $this->db = Database::getConnection();
    }

    public function findAdminById(string $id): ?Admin
    {
        $stmt = $this->db->prepare("SELECT * FROM Admin WHERE MaAdmin = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new Admin(
            $row['MaAdmin'],
            $row['HoTen'],
            $row['SDT'] ?? '',
            $row['Email'] ?? '',
            $row['MaTK']
        );
    }

    public function getAllAdmin(array $filters = []): array
    {
        $sql = "SELECT * FROM Admin WHERE 1=1";
        $params = [];

        if (!empty($filters['HoTen'])) {
            $sql .= " AND HoTen LIKE ?";
            $params[] = '%' . $filters['HoTen'] . '%';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return new Admin(
                $row['MaAdmin'],
                $row['HoTen'],
                $row['SDT'] ?? '',
                $row['Email'] ?? '',
                $row['MaTK']
            );
        }, $rows);
    }

    public function saveAdmin(Admin $admin): bool
    {
        $existing = $this->findAdminById($admin->getMaAdmin());

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE Admin SET HoTen = ?, SDT = ?, Email = ?, MaTK = ? WHERE MaAdmin = ?");
            return $stmt->execute([
                $admin->getHoTen(),
                $admin->getSDT(),
                $admin->getEmail(),
                $admin->getMaTK(),
                $admin->getMaAdmin()
            ]);
        }

        $stmt = $this->db->prepare("INSERT INTO Admin (MaAdmin, HoTen, SDT, Email, MaTK) VALUES (?, ?, ?, ?, ?)");
        return $stmt->execute([
            $admin->getMaAdmin(),
            $admin->getHoTen(),
            $admin->getSDT(),
            $admin->getEmail(),
            $admin->getMaTK()
        ]);
    }

    public function deleteAdmin(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM Admin WHERE MaAdmin = ?");
        return $stmt->execute([$id]);
    }

    // ==========================================
    // Quản lý Nhân Viên / Lễ Tân
    // ==========================================

    public function getAllLeTan(array $filters = []): array
    {
        $sql = "
            SELECT lt.MaLeTan, lt.HoTen, lt.SDT, lt.Email, lt.MaTK,
                   tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.VaiTro
            FROM LeTan lt
            JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['TrangThaiTaiKhoan'])) {
            $sql .= " AND tk.TrangThaiTaiKhoan = ?";
            $params[] = $filters['TrangThaiTaiKhoan'];
        }

        $sql .= " ORDER BY lt.MaLeTan ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLeTanById(string $id): ?array
    {
        $sql = "
            SELECT lt.MaLeTan, lt.HoTen, lt.SDT, lt.Email, lt.MaTK,
                   tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.VaiTro
            FROM LeTan lt
            JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
            WHERE lt.MaLeTan = ? OR lt.MaTK = ?
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function timKiemLeTan(string $keyword): array
    {
        $kw = '%' . $keyword . '%';
        $sql = "
            SELECT lt.MaLeTan, lt.HoTen, lt.SDT, lt.Email, lt.MaTK,
                   tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.VaiTro
            FROM LeTan lt
            JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
            WHERE lt.HoTen LIKE ? OR lt.SDT LIKE ? OR lt.Email LIKE ?
               OR lt.MaLeTan LIKE ? OR tk.TenDangNhap LIKE ?
            ORDER BY lt.MaLeTan ASC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$kw, $kw, $kw, $kw, $kw]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function themLeTan(array $data): array
    {
        $maTK = !empty($data['MaTK']) ? $data['MaTK'] : Helper::generateId('TK');
        $maLeTan = !empty($data['MaLeTan']) ? $data['MaLeTan'] : Helper::generateId('LT');
        $tenDangNhap = $data['TenDangNhap'] ?? ('letan_' . substr($maLeTan, -4));
        $matKhau = password_hash($data['MatKhau'] ?? '123456', PASSWORD_BCRYPT);
        $email = $data['Email'] ?? ($tenDangNhap . '@hotel.com');
        $hoTen = $data['HoTen'] ?? 'Nhân viên lễ tân';
        $sdt = $data['SDT'] ?? '';

        $this->db->beginTransaction();
        try {
            // 1. Thêm TaiKhoan
            $stmtTK = $this->db->prepare("
                INSERT INTO TaiKhoan (MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro)
                VALUES (?, ?, ?, ?, 'HoatDong', 'LeTan')
            ");
            $stmtTK->execute([$maTK, $tenDangNhap, $matKhau, $email]);

            // 2. Thêm LeTan
            $stmtLT = $this->db->prepare("
                INSERT INTO LeTan (MaLeTan, HoTen, SDT, Email, MaTK)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmtLT->execute([$maLeTan, $hoTen, $sdt, $email, $maTK]);

            $this->db->commit();

            return [
                'MaLeTan' => $maLeTan,
                'HoTen' => $hoTen,
                'SDT' => $sdt,
                'Email' => $email,
                'MaTK' => $maTK,
                'TenDangNhap' => $tenDangNhap,
                'TrangThaiTaiKhoan' => 'HoatDong',
                'VaiTro' => 'LeTan',
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function capNhatLeTan(string $maLeTan, array $data): bool
    {
        $sql = "UPDATE LeTan SET HoTen = COALESCE(?, HoTen), SDT = COALESCE(?, SDT), Email = COALESCE(?, Email) WHERE MaLeTan = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            $data['HoTen'] ?? null,
            $data['SDT'] ?? null,
            $data['Email'] ?? null,
            $maLeTan
        ]);
    }

    public function capNhatTrangThaiTaiKhoan(string $maLeTan, string $trangThai): bool
    {
        $sql = "
            UPDATE TaiKhoan tk
            JOIN LeTan lt ON tk.MaTK = lt.MaTK
            SET tk.TrangThaiTaiKhoan = ?
            WHERE lt.MaLeTan = ? OR tk.MaTK = ?
        ";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$trangThai, $maLeTan, $maLeTan]);
    }

    // ==========================================
    // Thống kê & Báo cáo
    // ==========================================

    public function thongKeSoLuongNhanVien(): array
    {
        $sql = "
            SELECT
                COUNT(*) as TongSo,
                SUM(CASE WHEN tk.TrangThaiTaiKhoan = 'HoatDong' THEN 1 ELSE 0 END) as DangHoatDong,
                SUM(CASE WHEN tk.TrangThaiTaiKhoan = 'Khoa' THEN 1 ELSE 0 END) as BiKhoa
            FROM LeTan lt
            JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
        ";
        $stmt = $this->db->query($sql);
        $res = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'TongSo' => (int) ($res['TongSo'] ?? 0),
            'DangHoatDong' => (int) ($res['DangHoatDong'] ?? 0),
            'BiKhoa' => (int) ($res['BiKhoa'] ?? 0),
        ];
    }

    public function thongKeDoanhThu(array $params = []): array
    {
        $sqlTong = "
            SELECT
                COUNT(DISTINCT hd.MaHD) as TongHoaDon,
                COALESCE(SUM(hd.TongTienSauVAT), 0) as TongTienHoaDon,
                COALESCE(SUM(tt.SoTien), 0) as TongDaThanhToan
            FROM HoaDon hd
            LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD AND tt.TrangThaiThanhToan = 'ThanhCong'
        ";
        $stmtTong = $this->db->query($sqlTong);
        $tongKet = $stmtTong->fetch(PDO::FETCH_ASSOC);

        // Doanh thu theo phương thức thanh toán
        $sqlHinhThuc = "
            SELECT HinhThuc, COUNT(*) as SoGiaoDich, COALESCE(SUM(SoTien), 0) as TongTien
            FROM ThanhToan
            WHERE TrangThaiThanhToan = 'ThanhCong'
            GROUP BY HinhThuc
        ";
        $stmtHT = $this->db->query($sqlHinhThuc);
        $theoHinhThuc = $stmtHT->fetchAll(PDO::FETCH_ASSOC);

        return [
            'TongHoaDon' => (int) ($tongKet['TongHoaDon'] ?? 0),
            'TongTienHoaDon' => (float) ($tongKet['TongTienHoaDon'] ?? 0),
            'TongDaThanhToan' => (float) ($tongKet['TongDaThanhToan'] ?? 0),
            'TheoHinhThuc' => $theoHinhThuc,
        ];
    }

    public function thongKeSoLuongPhong(): array
    {
        $sqlTrangThai = "
            SELECT
                COUNT(*) as TongSoPhong,
                SUM(CASE WHEN TrangThaiPhong = 'Trong' THEN 1 ELSE 0 END) as PhongTrong,
                SUM(CASE WHEN TrangThaiPhong = 'DaDat' THEN 1 ELSE 0 END) as PhongDaDat,
                SUM(CASE WHEN TrangThaiPhong = 'DangSuDung' THEN 1 ELSE 0 END) as PhongDangSuDung,
                SUM(CASE WHEN TrangThaiPhong = 'BaoTri' THEN 1 ELSE 0 END) as PhongBaoTri
            FROM Phong
        ";
        $stmtTT = $this->db->query($sqlTrangThai);
        $tt = $stmtTT->fetch(PDO::FETCH_ASSOC);

        $sqlLoaiPhong = "
            SELECT lp.MaLoaiPhong, lp.TenLoaiPhong, COUNT(p.MaPhong) as SoLuongPhong
            FROM LoaiPhong lp
            LEFT JOIN Phong p ON lp.MaLoaiPhong = p.MaLoaiPhong
            GROUP BY lp.MaLoaiPhong, lp.TenLoaiPhong
        ";
        $stmtLP = $this->db->query($sqlLoaiPhong);
        $theoLoai = $stmtLP->fetchAll(PDO::FETCH_ASSOC);

        return [
            'TongSoPhong' => (int) ($tt['TongSoPhong'] ?? 0),
            'PhongTrong' => (int) ($tt['PhongTrong'] ?? 0),
            'PhongDaDat' => (int) ($tt['PhongDaDat'] ?? 0),
            'PhongDangSuDung' => (int) ($tt['PhongDangSuDung'] ?? 0),
            'PhongBaoTri' => (int) ($tt['PhongBaoTri'] ?? 0),
            'TheoLoaiPhong' => $theoLoai,
        ];
    }
}
