<?php

declare(strict_types=1);

namespace App\KhachHang\Repositories;

use Database\Database;
use PDO;

/**
 * Repository xử lý toàn bộ truy vấn CSDL cho module KhachHang.
 * Bao gồm 2 phần nghiệp vụ: Tra cứu dữ liệu (Query) và Thao tác lưu trữ hồ sơ (Command).
 */
class KhachHangXuLyRepository
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

    // =========================================================================
    // PHẦN 1: TRA CỨU & KIỂM TRA TỒN TẠI (READ / QUERY)
    // =========================================================================

    /**
     * Tìm thông tin khách hàng dựa trên MaTK (liên kết với bảng TaiKhoan).
     */
    public function findByMaTK(string $maTK, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare(
            "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.MaTK, kh.NgaySinh, kh.QuocTich, kh.LoaiGiayTo, kh.SoGiayTo, tk.Email 
             FROM KhachHang kh 
             LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK 
             WHERE kh.MaTK = ?"
        );
        $stmt->execute([$maTK]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tìm khách hàng theo Số Điện Thoại.
     * Trả về thông tin khách hàng kèm thông tin tài khoản và định danh.
     */
    public function findBySDT(string $sdt, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $sql = "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.MaTK, kh.NgaySinh, kh.QuocTich, kh.LoaiGiayTo, kh.SoGiayTo, tk.TenDangNhap, tk.Email, tk.VaiTro
                FROM KhachHang kh
                LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK
                WHERE kh.SDT = ?
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([trim($sdt)]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Tìm khách hàng theo mã khách hàng (MaKH).
     */
    public function findById(string $maKH, ?PDO $db = null): ?array
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->prepare(
            "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.MaTK, kh.NgaySinh, kh.QuocTich, kh.LoaiGiayTo, kh.SoGiayTo, tk.TenDangNhap, tk.Email 
             FROM KhachHang kh 
             LEFT JOIN TaiKhoan tk ON kh.MaTK = tk.MaTK 
             WHERE kh.MaKH = ?"
        );
        $stmt->execute([$maKH]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Lấy đầy đủ thông tin cá nhân khách hàng kèm thống kê đặt phòng và tài khoản.
     */
    public function getThongTinCaNhan(string $identifier): ?array
    {
        $db = $this->getConnection();
        $sql = "SELECT 
                    kh.MaKH, 
                    kh.HoTen, 
                    kh.SDT, 
                    kh.MaTK, 
                    kh.NgaySinh,
                    kh.QuocTich,
                    kh.LoaiGiayTo,
                    kh.SoGiayTo,
                    tk.TenDangNhap, 
                    tk.Email, 
                    tk.VaiTro, 
                    tk.TrangThaiTaiKhoan,
                    COALESCE((SELECT COUNT(*) FROM phieunhanphong pnp WHERE pnp.MaKH = kh.MaKH), 0) AS TongSoDonDat,
                    COALESCE((SELECT COUNT(*) FROM phieunhanphong pnp WHERE pnp.MaKH = kh.MaKH AND pnp.TrangThai IN ('DaNhanPhong', 'DaTraPhong', 'DaThanhToan')), 0) AS SoDonHoanTat,
                    COALESCE((SELECT COUNT(*) FROM danhgia dg WHERE dg.MaKH = kh.MaKH), 0) AS SoDanhGiaDaGui
                FROM khachhang kh
                LEFT JOIN taikhoan tk ON kh.MaTK = tk.MaTK
                WHERE kh.MaTK = :id1 OR kh.MaKH = :id2
                LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':id1' => $identifier, ':id2' => $identifier]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /**
     * Kiểm tra email đã được tài khoản khác sử dụng chưa.
     */
    public function checkEmailExistsForOther(string $email, string $maTK): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM TaiKhoan WHERE Email = ? AND MaTK != ? LIMIT 1");
        $stmt->execute([$email, $maTK]);

        return (bool) $stmt->fetchColumn();
    }

    /**
     * Kiểm tra số điện thoại đã được khách hàng khác sử dụng chưa.
     */
    public function checkSDTExistsForOther(string $sdt, string $maTK): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT 1 FROM KhachHang WHERE SDT = ? AND (MaTK != ? OR MaTK IS NULL) LIMIT 1");
        $stmt->execute([$sdt, $maTK]);

        return (bool) $stmt->fetchColumn();
    }

    // =========================================================================
    // PHẦN 2: THAO TÁC, SINH MÃ & CẬP NHẬT HỒ SƠ (WRITE / COMMAND)
    // =========================================================================

    /**
     * Sinh mã khách hàng tự động theo định dạng KH001, KH002,... dựa trên dữ liệu trong CSDL.
     */
    public function generateMaKH(?PDO $db = null): string
    {
        $db = $db ?? $this->getConnection();
        $stmt = $db->query("SELECT MaKH FROM KhachHang WHERE MaKH LIKE 'KH%' ORDER BY CAST(SUBSTRING(MaKH, 3) AS UNSIGNED) DESC LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row && !empty($row['MaKH'])) {
            $lastNumber = (int) substr($row['MaKH'], 2);
            $nextNumber = $lastNumber + 1;
        } else {
            $nextNumber = 1;
        }

        return sprintf('KH%03d', $nextNumber);
    }

    /**
     * Thêm thông tin khách hàng mới vào cơ sở dữ liệu.
     */
    public function themKhachHang(
        string $maKH,
        string $hoTen,
        ?string $sdt = null,
        ?string $maTK = null,
        ?string $ngaySinh = null,
        string $quocTich = 'Việt Nam',
        string $loaiGiayTo = 'CCCD',
        ?string $soGiayTo = null,
        ?PDO $db = null
    ): bool {
        $db = $db ?? $this->getConnection();
        $sql = "INSERT INTO KhachHang (MaKH, HoTen, SDT, MaTK, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);

        return $stmt->execute([
            $maKH,
            $hoTen,
            $sdt,
            $maTK,
            $ngaySinh,
            $quocTich,
            $loaiGiayTo,
            $soGiayTo
        ]);
    }

    /**
     * Cập nhật thông tin cá nhân (Họ tên, SĐT, Ngày sinh, Quốc tịch, Loại giấy tờ, Số giấy tờ trong KhachHang và Email trong TaiKhoan).
     */
    public function capNhatThongTinCaNhan(
        string $maTK,
        string $hoTen,
        ?string $sdt,
        string $email,
        ?string $ngaySinh = null,
        string $quocTich = 'Việt Nam',
        string $loaiGiayTo = 'CCCD',
        ?string $soGiayTo = null
    ): bool {
        $db = $this->getConnection();
        $db->beginTransaction();
        try {
            $stmtKH = $db->prepare("UPDATE KhachHang SET HoTen = ?, SDT = ?, NgaySinh = ?, QuocTich = ?, LoaiGiayTo = ?, SoGiayTo = ? WHERE MaTK = ?");
            $stmtKH->execute([$hoTen, $sdt, $ngaySinh, $quocTich, $loaiGiayTo, $soGiayTo, $maTK]);

            $stmtTK = $db->prepare("UPDATE TaiKhoan SET Email = ? WHERE MaTK = ?");
            $stmtTK->execute([$email, $maTK]);

            $db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Cập nhật thông tin định danh người lưu trú vào hồ sơ KhachHang (nếu còn thiếu).
     */
    public function capNhatThongTinDinhDanh(
        string $maKH,
        ?string $ngaySinh,
        ?string $quocTich,
        ?string $loaiGiayTo,
        ?string $soGiayTo,
        ?PDO $db = null
    ): bool {
        $db = $db ?? $this->getConnection();
        $sql = "UPDATE KhachHang 
                SET NgaySinh = COALESCE(?, NgaySinh),
                    QuocTich = COALESCE(?, QuocTich),
                    LoaiGiayTo = COALESCE(?, LoaiGiayTo),
                    SoGiayTo = COALESCE(?, SoGiayTo)
                WHERE MaKH = ?";
        $stmt = $db->prepare($sql);
        return $stmt->execute([$ngaySinh, $quocTich, $loaiGiayTo, $soGiayTo, $maKH]);
    }

    /**
     * Đổi mật khẩu tài khoản trong CSDL.
     */
    public function doiMatKhau(string $maTK, string $matKhauCu, string $matKhauMoi): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT MatKhau FROM TaiKhoan WHERE MaTK = ? LIMIT 1");
        $stmt->execute([$maTK]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            throw new \Exception("Không tìm thấy thông tin tài khoản.");
        }

        if (!password_verify($matKhauCu, $row['MatKhau'])) {
            throw new \Exception("Mật khẩu hiện tại không chính xác.");
        }

        $hashed = password_hash($matKhauMoi, PASSWORD_DEFAULT);
        $stmtUp = $db->prepare("UPDATE TaiKhoan SET MatKhau = ? WHERE MaTK = ?");
        return $stmtUp->execute([$hashed, $maTK]);
    }
}
