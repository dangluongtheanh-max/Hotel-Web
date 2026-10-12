<?php

namespace App\KhachHang\Repositories;

use Database\Database;
use PDO;

/**
 * Repository cho module KhachHang & Luồng Khách Đặt Phòng Trực Tuyến.
 * Thao tác trực tiếp với cơ sở dữ liệu MariaDB/MySQL.
 */
class KhachHangRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    /**
     * Lấy toàn bộ danh sách phòng kèm thông tin loại phòng và trạng thái.
     */
    public function getAllRoomsWithTypes(): array
    {
        $sql = "SELECT p.MaPhong, p.TrangThaiPhong, lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa, lp.MoTa, lp.TienNghi
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                ORDER BY p.MaPhong ASC";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Tìm phòng cụ thể theo mã phòng.
     */
    public function findRoomById(string $maPhong): ?array
    {
        $sql = "SELECT p.MaPhong, p.TrangThaiPhong, lp.MaLoaiPhong, lp.TenLoaiPhong, lp.Gia, lp.SoNguoiToiDa, lp.MoTa, lp.TienNghi
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE p.MaPhong = :maPhong
                LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maPhong' => $maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tìm khách hàng theo số điện thoại.
     */
    public function findCustomerByPhone(string $sdt): ?array
    {
        $sql = "SELECT * FROM KhachHang WHERE SDT = :sdt LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':sdt' => $sdt]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Tìm khách hàng theo mã.
     */
    public function findCustomerById(string $maKH): ?array
    {
        $sql = "SELECT * FROM KhachHang WHERE MaKH = :maKH LIMIT 1";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maKH' => $maKH]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getAllCustomers(string $keyword = ''): array
    {
        $sql = "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.Email, kh.MaTK,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM KhachHang kh
                LEFT JOIN TaiKhoan tk ON tk.MaTK = kh.MaTK";
        $parameters = [];

        if ($keyword !== '') {
            $sql .= " WHERE kh.MaKH LIKE :idKeyword
                      OR kh.HoTen LIKE :nameKeyword
                      OR kh.SDT LIKE :phoneKeyword
                      OR kh.Email LIKE :emailKeyword
                      OR tk.TenDangNhap LIKE :usernameKeyword";
            $likeKeyword = '%' . $keyword . '%';
            $parameters = [
                ':idKeyword' => $likeKeyword,
                ':nameKeyword' => $likeKeyword,
                ':phoneKeyword' => $likeKeyword,
                ':emailKeyword' => $likeKeyword,
                ':usernameKeyword' => $likeKeyword,
            ];
        }

        $sql .= ' ORDER BY kh.MaKH ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findCustomerForManagement(string $maKH): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT kh.MaKH, kh.HoTen, kh.SDT, kh.Email, kh.MaTK,
                    tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.VaiTro
             FROM KhachHang kh
             LEFT JOIN TaiKhoan tk ON tk.MaTK = kh.MaTK
             WHERE kh.MaKH = :maKH"
        );
        $stmt->execute([':maKH' => $maKH]);
        $customer = $stmt->fetch(PDO::FETCH_ASSOC);

        return $customer ?: null;
    }

    public function customerEmailExists(string $email, string $excludeMaKH): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1
             FROM KhachHang kh
             LEFT JOIN TaiKhoan tk ON tk.MaTK = kh.MaTK
             WHERE kh.MaKH <> :maKH
               AND (kh.Email = :customerEmail OR tk.Email = :accountEmail)
             LIMIT 1'
        );
        $stmt->execute([
            ':maKH' => $excludeMaKH,
            ':customerEmail' => $email,
            ':accountEmail' => $email,
        ]);

        if ($stmt->fetchColumn() !== false) {
            return true;
        }

        $stmt = $this->db->prepare(
            "SELECT 1 FROM TaiKhoan
             WHERE Email = :email AND VaiTro <> 'KhachHang'
             LIMIT 1"
        );
        $stmt->execute([':email' => $email]);

        return $stmt->fetchColumn() !== false;
    }

    public function updateCustomer(string $maKH, array $data): bool
    {
        $current = $this->findCustomerForManagement($maKH);
        if ($current === null) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $customer = $this->db->prepare(
                'UPDATE KhachHang
                 SET HoTen = :hoTen, SDT = :sdt, Email = :email
                 WHERE MaKH = :maKH'
            );
            $customer->execute([
                ':hoTen' => $data['HoTen'] ?? $current['HoTen'],
                ':sdt' => $data['SDT'] ?? $current['SDT'],
                ':email' => array_key_exists('Email', $data) ? $data['Email'] : $current['Email'],
                ':maKH' => $maKH,
            ]);

            if (array_key_exists('Email', $data) && $current['MaTK'] !== null) {
                $account = $this->db->prepare(
                    "UPDATE TaiKhoan
                     SET Email = :email
                     WHERE MaTK = :maTK AND VaiTro = 'KhachHang'"
                );
                $account->execute([
                    ':email' => $data['Email'],
                    ':maTK' => $current['MaTK'],
                ]);
                if ($account->rowCount() !== 1 && $current['Email'] !== $data['Email']) {
                    throw new \RuntimeException('Linked customer account could not be updated.');
                }
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return true;
    }

    public function updateCustomerAccountStatus(string $maKH, string $status): bool
    {
        $customer = $this->findCustomerForManagement($maKH);
        if ($customer === null || $customer['MaTK'] === null || $customer['VaiTro'] !== 'KhachHang') {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE TaiKhoan
             SET TrangThaiTaiKhoan = :status
             WHERE MaTK = :maTK AND VaiTro = 'KhachHang'"
        );
        $stmt->execute([':status' => $status, ':maTK' => $customer['MaTK']]);

        return true;
    }

    /**
     * Sinh mã khách hàng tiếp theo (KH001, KH002...).
     */
    public function generateNextMaKH(): string
    {
        $sql = "SELECT MaKH FROM KhachHang ORDER BY MaKH DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();
        if ($last && preg_match('/KH(\d+)/i', $last, $m)) {
            $num = (int)$m[1] + 1;
            return 'KH' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }
        return 'KH001';
    }

    /**
     * Tạo khách hàng mới.
     */
    public function createCustomer(array $data): string
    {
        $maKH = $data['MaKH'] ?? $this->generateNextMaKH();
        $sql = "INSERT INTO KhachHang (MaKH, HoTen, SDT, Email)
                VALUES (:maKH, :hoTen, :sdt, :email)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maKH' => $maKH,
            ':hoTen' => $data['HoTen'],
            ':sdt' => $data['SDT'],
            ':email' => $data['Email'] ?? null,
        ]);
        return $maKH;
    }

    /**
     * Sinh mã phiếu nhận phòng tiếp theo (PP001, PP002...).
     */
    public function generateNextMaPhieu(): string
    {
        $sql = "SELECT MaPhieu FROM PhieuNhanPhong ORDER BY MaPhieu DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();
        if ($last && preg_match('/PP(\d+)/i', $last, $m)) {
            $num = (int)$m[1] + 1;
            return 'PP' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }
        return 'PP001';
    }

    /**
     * Sinh mã hóa đơn tiếp theo (HD001, HD002...).
     */
    public function generateNextMaHD(): string
    {
        $sql = "SELECT MaHD FROM HoaDon ORDER BY MaHD DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();
        if ($last && preg_match('/HD(\d+)/i', $last, $m)) {
            $num = (int)$m[1] + 1;
            return 'HD' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }
        return 'HD001';
    }

    /**
     * Sinh mã thanh toán tiếp theo (TT001, TT002...).
     */
    public function generateNextMaTT(): string
    {
        $sql = "SELECT MaThanhToan FROM ThanhToan ORDER BY MaThanhToan DESC LIMIT 1";
        $stmt = $this->db->query($sql);
        $last = $stmt->fetchColumn();
        if ($last && preg_match('/TT(\d+)/i', $last, $m)) {
            $num = (int)$m[1] + 1;
            return 'TT' . str_pad((string)$num, 3, '0', STR_PAD_LEFT);
        }
        return 'TT001';
    }

    /**
     * Thêm mới phiếu nhận phòng.
     */
    public function createPhieuNhanPhong(array $data): void
    {
        $sql = "INSERT INTO PhieuNhanPhong (MaPhieu, NgayLap, KenhDat, TrangThai, MaKH, MaLeTan, MaKM)
                VALUES (:maPhieu, :ngayLap, 'TrucTuyen', :trangThai, :maKH, NULL, :maKM)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maPhieu' => $data['MaPhieu'],
            ':ngayLap' => $data['NgayLap'] ?? date('Y-m-d'),
            ':trangThai' => $data['TrangThai'] ?? 'DaDat',
            ':maKH' => $data['MaKH'],
            ':maKM' => !empty($data['MaKM']) ? $data['MaKM'] : null,
        ]);
    }

    /**
     * Thêm chi tiết phòng vào phiếu nhận phòng.
     */
    public function createChiTietPhieu(array $data): void
    {
        $sql = "INSERT INTO ChiTietPhieuNhanPhong (MaPhieu, MaPhong, ThoiGianNhanPhong, ThoiGianTraPhong, SoNguoi, TrangThai)
                VALUES (:maPhieu, :maPhong, :nhan, :tra, :soNguoi, :trangThai)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maPhieu' => $data['MaPhieu'],
            ':maPhong' => $data['MaPhong'],
            ':nhan' => $data['ThoiGianNhanPhong'],
            ':tra' => $data['ThoiGianTraPhong'],
            ':soNguoi' => $data['SoNguoi'] ?? 2,
            ':trangThai' => $data['TrangThai'] ?? 'DaDat',
        ]);
    }

    /**
     * Cập nhật trạng thái phòng.
     */
    public function updateRoomStatus(string $maPhong, string $status): void
    {
        $sql = "UPDATE Phong SET TrangThaiPhong = :status WHERE MaPhong = :maPhong";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':status' => $status, ':maPhong' => $maPhong]);
    }

    /**
     * Tạo hóa đơn.
     */
    public function createHoaDon(array $data): void
    {
        $sql = "INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT)
                VALUES (:maHD, :maPhieu, :tongTien)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maHD' => $data['MaHD'],
            ':maPhieu' => $data['MaPhieu'],
            ':tongTien' => $data['TongTienSauVAT'],
        ]);
    }

    /**
     * Tạo chi tiết hóa đơn (CTHD).
     */
    public function createCTHD(array $data): void
    {
        $sql = "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia)
                VALUES (:maHD, :maPhieu, :maPhong, :maCTDV, :soDem, :soLuong, :donGia)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maHD' => $data['MaHD'],
            ':maPhieu' => $data['MaPhieu'],
            ':maPhong' => $data['MaPhong'] ?? null,
            ':maCTDV' => $data['MaCTDV'] ?? null,
            ':soDem' => $data['SoDem'] ?? 0,
            ':soLuong' => $data['SoLuong'] ?? 1,
            ':donGia' => $data['DonGia'],
        ]);
    }

    /**
     * Tạo giao dịch thanh toán.
     */
    public function createThanhToan(array $data): void
    {
        $sql = "INSERT INTO ThanhToan (MaThanhToan, SoTien, ThoiGianThanhToan, HinhThuc, TrangThaiThanhToan, MaHD)
                VALUES (:maTT, :soTien, :thoiGian, :hinhThuc, :trangThai, :maHD)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':maTT' => $data['MaThanhToan'],
            ':soTien' => $data['SoTien'],
            ':thoiGian' => $data['ThoiGianThanhToan'] ?? date('Y-m-d H:i:s'),
            ':hinhThuc' => $data['HinhThuc'] ?? 'ChuyenKhoan',
            ':trangThai' => $data['TrangThaiThanhToan'] ?? 'ChoThanhToan',
            ':maHD' => $data['MaHD'],
        ]);
    }

    /**
     * Tra cứu phiếu đặt phòng và hóa đơn theo số điện thoại hoặc từ khóa.
     */
    public function findBookingsByPhoneOrKeyword(string $kw): array
    {
        $kw = trim($kw);
        $sql = "SELECT pnp.MaPhieu, pnp.NgayLap, pnp.KenhDat, pnp.TrangThai AS TrangThaiPhieu, pnp.MaKM,
                       kh.MaKH, kh.HoTen AS TenKhachHang, kh.SDT, kh.Email,
                       ct.MaPhong, ct.ThoiGianNhanPhong, ct.ThoiGianTraPhong, ct.SoNguoi,
                       lp.TenLoaiPhong, lp.Gia AS GiaPhong,
                       hd.MaHD, hd.TongTienSauVAT,
                       tt.MaThanhToan, tt.HinhThuc, tt.TrangThaiThanhToan
                FROM PhieuNhanPhong pnp
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN ChiTietPhieuNhanPhong ct ON pnp.MaPhieu = ct.MaPhieu
                LEFT JOIN Phong p ON ct.MaPhong = p.MaPhong
                LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
                LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                WHERE kh.SDT = :kw1 OR pnp.MaPhieu = :kw2 OR hd.MaHD = :kw3
                ORDER BY pnp.NgayLap DESC, pnp.MaPhieu DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':kw1' => $kw,
            ':kw2' => $kw,
            ':kw3' => $kw
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
