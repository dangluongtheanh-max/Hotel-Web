<?php

namespace App\LeTan\Repositories;

use App\LeTan\Models\LeTan;
use Database\Database;
use PDO;
use PDOException;

/**
 * Repository cho module LeTan (BE2).
 * Lớp DUY NHẤT trong module LeTan được phép kết nối và thực hiện truy vấn trực tiếp với DB.
 */
class LeTanRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getConnection(): PDO
    {
        return $this->db;
    }

    /* =========================================================================
     * 1. TRA CỨU & QUẢN LÝ PHÒNG (BE2)
     * ========================================================================= */

    /**
     * Lấy danh sách phòng kèm thông tin loại phòng, giá và trạng thái hiện tại.
     */
    public function getDanhSachPhong(array $filters = []): array
    {
        $sql = "SELECT 
                    p.MaPhong, 
                    p.TrangThaiPhong, 
                    p.MaLoaiPhong, 
                    lp.TenLoaiPhong, 
                    lp.Gia, 
                    lp.SoNguoiToiDa, 
                    lp.MoTa, 
                    lp.TienNghi,
                    (
                        SELECT kh.HoTen 
                        FROM ChiTietPhieuNhanPhong ct
                        JOIN PhieuNhanPhong pnp ON ct.MaPhieu = pnp.MaPhieu
                        JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                        WHERE ct.MaPhong = p.MaPhong 
                          AND ct.TrangThai IN ('DangLuuTru', 'DaDat')
                        ORDER BY ct.ThoiGianNhanPhong ASC
                        LIMIT 1
                    ) AS KhachDangDat,
                    (
                        SELECT ct.MaPhieu 
                        FROM ChiTietPhieuNhanPhong ct
                        WHERE ct.MaPhong = p.MaPhong 
                          AND ct.TrangThai IN ('DangLuuTru', 'DaDat')
                        ORDER BY ct.ThoiGianNhanPhong ASC
                        LIMIT 1
                    ) AS MaPhieuLienQuan
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE 1=1";

        $params = [];

        if (!empty($filters['TrangThaiPhong'])) {
            $sql .= " AND p.TrangThaiPhong = :trangThaiPhong";
            $params[':trangThaiPhong'] = $filters['TrangThaiPhong'];
        }

        if (!empty($filters['MaLoaiPhong'])) {
            $sql .= " AND p.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $filters['MaLoaiPhong'];
        }

        $sql .= " ORDER BY p.MaPhong ASC";

        if (!empty($filters['limit']) && (int)$filters['limit'] > 0) {
            $limit = (int)$filters['limit'];
            $offset = !empty($filters['offset']) ? (int)$filters['offset'] : 0;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Xem chi tiết một phòng kèm thông tin booking đang lưu trú hoặc sắp tới.
     */
    public function getChiTietPhong(string $maPhong): ?array
    {
        $sql = "SELECT 
                    p.MaPhong, 
                    p.TrangThaiPhong, 
                    p.MaLoaiPhong, 
                    lp.TenLoaiPhong, 
                    lp.Gia, 
                    lp.SoNguoiToiDa, 
                    lp.MoTa, 
                    lp.TienNghi
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE p.MaPhong = :maPhong
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maPhong' => $maPhong]);
        $room = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$room) {
            return null;
        }

        // Lấy danh sách booking liên quan đang lưu trú hoặc đã đặt
        $bookingSql = "SELECT 
                            ct.MaPhieu,
                            ct.ThoiGianNhanPhong,
                            ct.ThoiGianTraPhong,
                            ct.SoNguoi,
                            ct.TrangThai AS TrangThaiChiTiet,
                            pnp.NgayLap,
                            pnp.KenhDat,
                            pnp.TrangThai AS TrangThaiPhieu,
                            kh.MaKH,
                            kh.HoTen AS TenKhachHang,
                            kh.SDT,
                            kh.Email
                       FROM ChiTietPhieuNhanPhong ct
                       JOIN PhieuNhanPhong pnp ON ct.MaPhieu = pnp.MaPhieu
                       JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                       WHERE ct.MaPhong = :maPhong
                         AND ct.TrangThai IN ('DangLuuTru', 'DaDat')
                       ORDER BY ct.ThoiGianNhanPhong ASC";

        $stmtBookings = $this->db->prepare($bookingSql);
        $stmtBookings->execute([':maPhong' => $maPhong]);
        $bookings = $stmtBookings->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Xác định booking hiện tại đang lưu trú (nếu có)
        $currentBooking = null;
        foreach ($bookings as $b) {
            if ($b['TrangThaiChiTiet'] === 'DangLuuTru') {
                $currentBooking = $b;
                break;
            }
        }

        return [
            'Phong' => $room,
            'DangLuuTru' => $currentBooking,
            'DanhSachDatPhong' => $bookings
        ];
    }

    /**
     * Tìm kiếm phòng linh hoạt theo từ khóa, khoảng giá, sức chứa và trạng thái.
     */
    public function timKiemPhong(array $filters = []): array
    {
        $sql = "SELECT 
                    p.MaPhong, 
                    p.TrangThaiPhong, 
                    p.MaLoaiPhong, 
                    lp.TenLoaiPhong, 
                    lp.Gia, 
                    lp.SoNguoiToiDa, 
                    lp.MoTa, 
                    lp.TienNghi
                FROM Phong p
                JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                WHERE 1=1";

        $params = [];

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim($filters['keyword']) . '%';
            $sql .= " AND (p.MaPhong LIKE :kw1 OR lp.TenLoaiPhong LIKE :kw2 OR lp.MoTa LIKE :kw3 OR lp.TienNghi LIKE :kw4)";
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
        }

        if (!empty($filters['TrangThaiPhong'])) {
            $sql .= " AND p.TrangThaiPhong = :trangThaiPhong";
            $params[':trangThaiPhong'] = $filters['TrangThaiPhong'];
        }

        if (!empty($filters['MaLoaiPhong'])) {
            $sql .= " AND p.MaLoaiPhong = :maLoaiPhong";
            $params[':maLoaiPhong'] = $filters['MaLoaiPhong'];
        }

        if (!empty($filters['GiaMin'])) {
            $sql .= " AND lp.Gia >= :giaMin";
            $params[':giaMin'] = (float)$filters['GiaMin'];
        }

        if (!empty($filters['GiaMax'])) {
            $sql .= " AND lp.Gia <= :giaMax";
            $params[':giaMax'] = (float)$filters['GiaMax'];
        }

        if (!empty($filters['SoNguoi'])) {
            $sql .= " AND lp.SoNguoiToiDa >= :soNguoi";
            $params[':soNguoi'] = (int)$filters['SoNguoi'];
        }

        $sql .= " ORDER BY p.MaPhong ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================================
     * 2. TRA CỨU & QUẢN LÝ PHIẾU NHẬN PHÒNG (BE2)
     * ========================================================================= */

    /**
     * Lấy danh sách phiếu nhận phòng theo điều kiện lọc.
     */
    public function getDanhSachPhieu(array $filters = []): array
    {
        $sql = "SELECT 
                    pnp.MaPhieu,
                    pnp.NgayLap,
                    pnp.KenhDat,
                    pnp.TrangThai,
                    pnp.MaKH,
                    pnp.MaLeTan,
                    pnp.MaKM,
                    kh.HoTen AS TenKhachHang,
                    kh.SDT,
                    kh.Email,
                    lt.HoTen AS TenLeTan,
                    km.TenKM,
                    km.GiaTriGiam,
                    km.LoaiKM,
                    (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong WHERE MaPhieu = pnp.MaPhieu) AS SoLuongPhong,
                    (SELECT GROUP_CONCAT(MaPhong SEPARATOR ', ') FROM ChiTietPhieuNhanPhong WHERE MaPhieu = pnp.MaPhieu) AS DanhSachMaPhong,
                    hd.MaHD,
                    hd.TongTienSauVAT
                FROM PhieuNhanPhong pnp
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN LeTan lt ON pnp.MaLeTan = lt.MaLeTan
                LEFT JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
                LEFT JOIN HoaDon hd ON pnp.MaPhieu = hd.MaPhieu
                WHERE 1=1";

        $params = [];

        if (!empty($filters['TrangThai'])) {
            $sql .= " AND pnp.TrangThai = :trangThai";
            $params[':trangThai'] = $filters['TrangThai'];
        }

        if (!empty($filters['KenhDat'])) {
            $sql .= " AND pnp.KenhDat = :kenhDat";
            $params[':kenhDat'] = $filters['KenhDat'];
        }

        if (!empty($filters['MaKH'])) {
            $sql .= " AND pnp.MaKH = :maKH";
            $params[':maKH'] = $filters['MaKH'];
        }

        if (!empty($filters['NgayLap'])) {
            $sql .= " AND pnp.NgayLap = :ngayLap";
            $params[':ngayLap'] = $filters['NgayLap'];
        }

        if (!empty($filters['tu_ngay'])) {
            $sql .= " AND pnp.NgayLap >= :tuNgay";
            $params[':tuNgay'] = $filters['tu_ngay'];
        }

        if (!empty($filters['den_ngay'])) {
            $sql .= " AND pnp.NgayLap <= :denNgay";
            $params[':denNgay'] = $filters['den_ngay'];
        }

        $sql .= " ORDER BY pnp.NgayLap DESC, pnp.MaPhieu DESC";

        if (!empty($filters['limit']) && (int)$filters['limit'] > 0) {
            $limit = (int)$filters['limit'];
            $offset = !empty($filters['offset']) ? (int)$filters['offset'] : 0;
            $sql .= " LIMIT {$limit} OFFSET {$offset}";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Xem thông tin đầy đủ, chi tiết của một phiếu nhận phòng.
     */
    public function getChiTietPhieu(string $maPhieu): ?array
    {
        // 1. Thông tin phiếu nhận phòng cơ sở
        $sql = "SELECT 
                    pnp.MaPhieu,
                    pnp.NgayLap,
                    pnp.KenhDat,
                    pnp.TrangThai,
                    pnp.MaKH,
                    pnp.MaLeTan,
                    pnp.MaKM,
                    kh.HoTen AS TenKhachHang,
                    kh.SDT,
                    kh.Email,
                    lt.HoTen AS TenLeTan,
                    km.TenKM,
                    km.LoaiKM,
                    km.GiaTriGiam,
                    km.SoDemToiThieu,
                    km.SoNguoiToiThieu
                FROM PhieuNhanPhong pnp
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN LeTan lt ON pnp.MaLeTan = lt.MaLeTan
                LEFT JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
                WHERE pnp.MaPhieu = :maPhieu
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maPhieu' => $maPhieu]);
        $phieu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            return null;
        }

        // 2. Danh sách phòng trong phiếu
        $roomSql = "SELECT 
                        ct.MaPhieu,
                        ct.MaPhong,
                        ct.ThoiGianNhanPhong,
                        ct.ThoiGianTraPhong,
                        ct.SoNguoi,
                        ct.TrangThai,
                        p.TrangThaiPhong,
                        lp.MaLoaiPhong,
                        lp.TenLoaiPhong,
                        lp.Gia AS DonGiaPhong,
                        lp.SoNguoiToiDa
                    FROM ChiTietPhieuNhanPhong ct
                    JOIN Phong p ON ct.MaPhong = p.MaPhong
                    JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                    WHERE ct.MaPhieu = :maPhieu
                    ORDER BY ct.MaPhong ASC";

        $roomStmt = $this->db->prepare($roomSql);
        $roomStmt->execute([':maPhieu' => $maPhieu]);
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 3. Danh sách dịch vụ phát sinh đã dùng
        $dvSql = "SELECT 
                    ctsd.MaCTSDDV,
                    ctsd.MaCTDV,
                    ctsd.MaPhieu,
                    ctsd.MaPhong,
                    ctsd.DonGia,
                    ctsd.MoTa,
                    ctsd.SoLuong,
                    ctsd.TrangThai,
                    ctdv.TenCTDV,
                    (ctsd.SoLuong * ctsd.DonGia) AS ThanhTien
                  FROM CTSuDungDichVu ctsd
                  JOIN ChiTietDichVu ctdv ON ctsd.MaCTDV = ctdv.MaCTDV
                  WHERE ctsd.MaPhieu = :maPhieu
                  ORDER BY ctsd.MaCTSDDV ASC";

        $dvStmt = $this->db->prepare($dvSql);
        $dvStmt->execute([':maPhieu' => $maPhieu]);
        $services = $dvStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // 4. Hóa đơn và thanh toán nếu có
        $hdSql = "SELECT hd.MaHD, hd.TongTienSauVAT, tt.MaThanhToan, tt.HinhThuc, tt.TrangThaiThanhToan, tt.ThoiGianThanhToan
                  FROM HoaDon hd
                  LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                  WHERE hd.MaPhieu = :maPhieu
                  LIMIT 1";
        $hdStmt = $this->db->prepare($hdSql);
        $hdStmt->execute([':maPhieu' => $maPhieu]);
        $invoice = $hdStmt->fetch(PDO::FETCH_ASSOC) ?: null;

        return [
            'MaPhieu' => $phieu['MaPhieu'],
            'NgayLap' => $phieu['NgayLap'],
            'KenhDat' => $phieu['KenhDat'],
            'TrangThai' => $phieu['TrangThai'],
            'KhachHang' => [
                'MaKH' => $phieu['MaKH'],
                'HoTen' => $phieu['TenKhachHang'],
                'SDT' => $phieu['SDT'],
                'Email' => $phieu['Email'],
            ],
            'LeTan' => $phieu['MaLeTan'] ? [
                'MaLeTan' => $phieu['MaLeTan'],
                'HoTen' => $phieu['TenLeTan'],
            ] : null,
            'KhuyenMai' => $phieu['MaKM'] ? [
                'MaKM' => $phieu['MaKM'],
                'TenKM' => $phieu['TenKM'],
                'LoaiKM' => $phieu['LoaiKM'],
                'GiaTriGiam' => $phieu['GiaTriGiam'],
            ] : null,
            'DanhSachPhong' => $rooms,
            'DanhSachDichVu' => $services,
            'HoaDon' => $invoice,
        ];
    }

    /**
     * Tìm kiếm phiếu nhận phòng theo từ khóa hoặc các trường cụ thể.
     */
    public function timKiemPhieu(array $filters = []): array
    {
        $sql = "SELECT 
                    pnp.MaPhieu,
                    pnp.NgayLap,
                    pnp.KenhDat,
                    pnp.TrangThai,
                    pnp.MaKH,
                    pnp.MaLeTan,
                    pnp.MaKM,
                    kh.HoTen AS TenKhachHang,
                    kh.SDT,
                    kh.Email,
                    lt.HoTen AS TenLeTan,
                    (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong WHERE MaPhieu = pnp.MaPhieu) AS SoLuongPhong,
                    (SELECT GROUP_CONCAT(MaPhong SEPARATOR ', ') FROM ChiTietPhieuNhanPhong WHERE MaPhieu = pnp.MaPhieu) AS DanhSachMaPhong
                FROM PhieuNhanPhong pnp
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN LeTan lt ON pnp.MaLeTan = lt.MaLeTan
                WHERE 1=1";

        $params = [];

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim($filters['keyword']) . '%';
            $sql .= " AND (pnp.MaPhieu LIKE :kw1 OR kh.HoTen LIKE :kw2 OR kh.SDT LIKE :kw3 OR kh.Email LIKE :kw4)";
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
        }

        if (!empty($filters['MaPhieu'])) {
            $sql .= " AND pnp.MaPhieu LIKE :maPhieu";
            $params[':maPhieu'] = '%' . trim($filters['MaPhieu']) . '%';
        }

        if (!empty($filters['SDT'])) {
            $sql .= " AND kh.SDT LIKE :sdt";
            $params[':sdt'] = '%' . trim($filters['SDT']) . '%';
        }

        if (!empty($filters['TenKhachHang']) || !empty($filters['HoTen'])) {
            $name = trim($filters['TenKhachHang'] ?? $filters['HoTen']);
            $sql .= " AND kh.HoTen LIKE :hoTen";
            $params[':hoTen'] = '%' . $name . '%';
        }

        if (!empty($filters['TrangThai'])) {
            $sql .= " AND pnp.TrangThai = :trangThai";
            $params[':trangThai'] = $filters['TrangThai'];
        }

        if (!empty($filters['KenhDat'])) {
            $sql .= " AND pnp.KenhDat = :kenhDat";
            $params[':kenhDat'] = $filters['KenhDat'];
        }

        $sql .= " ORDER BY pnp.NgayLap DESC, pnp.MaPhieu DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================================
     * 3. CHECK-IN & CHECK-OUT TRANSACTIONS (BE2)
     * ========================================================================= */

    /**
     * Khóa và đọc thông tin phiếu cùng các phòng bằng FOR UPDATE trong transaction.
     */
    public function lockAndGetPhieuForUpdate(string $maPhieu): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM PhieuNhanPhong WHERE MaPhieu = :maPhieu FOR UPDATE");
        $stmt->execute([':maPhieu' => $maPhieu]);
        $phieu = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phieu) {
            return null;
        }

        $roomStmt = $this->db->prepare("
            SELECT ct.*, p.TrangThaiPhong, lp.SoNguoiToiDa, lp.TenLoaiPhong, lp.Gia
            FROM ChiTietPhieuNhanPhong ct
            JOIN Phong p ON ct.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE ct.MaPhieu = :maPhieu
            FOR UPDATE
        ");
        $roomStmt->execute([':maPhieu' => $maPhieu]);
        $rooms = $roomStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $phieu['DanhSachPhong'] = $rooms;
        return $phieu;
    }

    /**
     * Thực hiện Check-in: Cập nhật đồng bộ PhieuNhanPhong, ChiTietPhieuNhanPhong và Phong.
     */
    public function updateCheckInStatus(string $maPhieu, ?string $maLeTan, array $maPhongs): bool
    {
        // 1. Cập nhật trạng thái phiếu nhận phòng
        $sqlPhieu = "UPDATE PhieuNhanPhong 
                     SET TrangThai = 'DangLuuTru', 
                         MaLeTan = COALESCE(:maLeTan, MaLeTan)
                     WHERE MaPhieu = :maPhieu";
        $stmtPhieu = $this->db->prepare($sqlPhieu);
        $stmtPhieu->execute([
            ':maLeTan' => $maLeTan,
            ':maPhieu' => $maPhieu
        ]);

        // 2. Cập nhật trạng thái các dòng chi tiết phòng trong phiếu
        $sqlCT = "UPDATE ChiTietPhieuNhanPhong 
                  SET TrangThai = 'DangLuuTru' 
                  WHERE MaPhieu = :maPhieu";
        $stmtCT = $this->db->prepare($sqlCT);
        $stmtCT->execute([':maPhieu' => $maPhieu]);

        // 3. Cập nhật trạng thái phòng vật lý thành 'DangSuDung'
        $sqlPhong = "UPDATE Phong SET TrangThaiPhong = 'DangSuDung' WHERE MaPhong = :maPhong";
        $stmtPhong = $this->db->prepare($sqlPhong);
        foreach ($maPhongs as $maPhong) {
            $stmtPhong->execute([':maPhong' => $maPhong]);
        }

        return true;
    }

    /**
     * Thực hiện Trả phòng (Check-out): Cập nhật PhieuNhanPhong, ChiTietPhieuNhanPhong và trả phòng về 'Trong'.
     */
    public function updateCheckOutStatus(string $maPhieu, array $maPhongs): bool
    {
        // 1. Cập nhật trạng thái phiếu thành 'DaTraPhong'
        $stmtPhieu = $this->db->prepare("UPDATE PhieuNhanPhong SET TrangThai = 'DaTraPhong' WHERE MaPhieu = :maPhieu");
        $stmtPhieu->execute([':maPhieu' => $maPhieu]);

        // 2. Cập nhật các dòng chi tiết phiếu thành 'DaTraPhong'
        $stmtCT = $this->db->prepare("UPDATE ChiTietPhieuNhanPhong SET TrangThai = 'DaTraPhong' WHERE MaPhieu = :maPhieu");
        $stmtCT->execute([':maPhieu' => $maPhieu]);

        // 3. Cập nhật các phòng về trạng thái 'Trong'
        $stmtPhong = $this->db->prepare("UPDATE Phong SET TrangThaiPhong = 'Trong' WHERE MaPhong = :maPhong");
        foreach ($maPhongs as $maPhong) {
            $stmtPhong->execute([':maPhong' => $maPhong]);
        }

        return true;
    }

    /* =========================================================================
     * 4. TRA CỨU HÓA ĐƠN & CHI TIẾT HÓA ĐƠN (BE2)
     * ========================================================================= */

    /**
     * Tìm kiếm hóa đơn theo mã hóa đơn, mã phiếu hoặc tên khách hàng.
     */
    public function timKiemHoaDon(array $filters = []): array
    {
        $sql = "SELECT 
                    hd.MaHD, 
                    hd.MaPhieu, 
                    hd.TongTienSauVAT, 
                    pnp.NgayLap, 
                    pnp.TrangThai AS TrangThaiPhieu, 
                    kh.MaKH, 
                    kh.HoTen AS TenKhachHang, 
                    kh.SDT,
                    tt.MaThanhToan,
                    tt.HinhThuc,
                    tt.TrangThaiThanhToan,
                    tt.ThoiGianThanhToan
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                WHERE 1=1";

        $params = [];

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim($filters['keyword']) . '%';
            $sql .= " AND (hd.MaHD LIKE :kw1 OR hd.MaPhieu LIKE :kw2 OR kh.HoTen LIKE :kw3 OR kh.SDT LIKE :kw4)";
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
            $params[':kw4'] = $kw;
        }

        if (!empty($filters['MaHD'])) {
            $sql .= " AND hd.MaHD LIKE :maHD";
            $params[':maHD'] = '%' . trim($filters['MaHD']) . '%';
        }

        if (!empty($filters['MaPhieu'])) {
            $sql .= " AND hd.MaPhieu LIKE :maPhieu";
            $params[':maPhieu'] = '%' . trim($filters['MaPhieu']) . '%';
        }

        if (!empty($filters['TenKhachHang'])) {
            $sql .= " AND kh.HoTen LIKE :tenKH";
            $params[':tenKH'] = '%' . trim($filters['TenKhachHang']) . '%';
        }

        $sql .= " ORDER BY hd.MaHD DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Xem thông tin tóm tắt và chi tiết của một hóa đơn theo MaHD hoặc MaPhieu.
     */
    public function xemHoaDon(string $query): ?array
    {
        $sql = "SELECT 
                    hd.MaHD,
                    hd.MaPhieu,
                    hd.TongTienSauVAT,
                    pnp.NgayLap,
                    pnp.TrangThai AS TrangThaiPhieu,
                    pnp.KenhDat,
                    kh.MaKH,
                    kh.HoTen AS TenKhachHang,
                    kh.SDT,
                    kh.Email,
                    tt.MaThanhToan,
                    tt.HinhThuc,
                    tt.TrangThaiThanhToan,
                    tt.ThoiGianThanhToan,
                    tt.SoTien AS SoTienThanhToan
                FROM HoaDon hd
                JOIN PhieuNhanPhong pnp ON hd.MaPhieu = pnp.MaPhieu
                JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
                LEFT JOIN ThanhToan tt ON hd.MaHD = tt.MaHD
                WHERE hd.MaHD = :q1 OR hd.MaPhieu = :q2
                LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':q1' => $query, ':q2' => $query]);
        $hd = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$hd) {
            return null;
        }

        // Lấy danh sách các dòng CTHD
        $detailsSql = "SELECT 
                            cthd.MaCTHD,
                            cthd.MaHD,
                            cthd.MaPhieu,
                            cthd.MaPhong,
                            cthd.MaCTDV,
                            cthd.SoDem,
                            cthd.SoLuong,
                            cthd.DonGia,
                            CASE 
                                WHEN cthd.MaPhong IS NOT NULL THEN (cthd.SoDem * cthd.DonGia)
                                ELSE (cthd.SoLuong * cthd.DonGia)
                            END AS ThanhTien,
                            lp.TenLoaiPhong,
                            ctdv.TenCTDV
                       FROM CTHD cthd
                       LEFT JOIN Phong p ON cthd.MaPhong = p.MaPhong
                       LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                       LEFT JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
                       WHERE cthd.MaHD = :maHD
                       ORDER BY cthd.MaCTHD ASC";

        $dStmt = $this->db->prepare($detailsSql);
        $dStmt->execute([':maHD' => $hd['MaHD']]);
        $hd['ChiTiet'] = $dStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return $hd;
    }

    /**
     * Xem các dòng chi tiết của một hóa đơn (tiền phòng, dịch vụ).
     */
    public function getChiTietHoaDon(string $maHD): array
    {
        $sql = "SELECT 
                    cthd.MaCTHD,
                    cthd.MaHD,
                    cthd.MaPhieu,
                    cthd.MaPhong,
                    cthd.MaCTDV,
                    cthd.SoDem,
                    cthd.SoLuong,
                    cthd.DonGia,
                    CASE 
                        WHEN cthd.MaPhong IS NOT NULL THEN (cthd.SoDem * cthd.DonGia)
                        ELSE (cthd.SoLuong * cthd.DonGia)
                    END AS ThanhTien,
                    CASE 
                        WHEN cthd.MaPhong IS NOT NULL THEN CONCAT('Tiền phòng ', cthd.MaPhong, COALESCE(CONCAT(' (', lp.TenLoaiPhong, ')'), ''))
                        ELSE COALESCE(ctdv.TenCTDV, 'Dịch vụ')
                    END AS TenKhoan
                FROM CTHD cthd
                LEFT JOIN Phong p ON cthd.MaPhong = p.MaPhong
                LEFT JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
                LEFT JOIN ChiTietDichVu ctdv ON cthd.MaCTDV = ctdv.MaCTDV
                WHERE cthd.MaHD = :maHD
                ORDER BY cthd.MaCTHD ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':maHD' => $maHD]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /* =========================================================================
     * 5. CRUD NHÂN VIÊN LỄ TÂN (KHỚP SƠ ĐỒ LỚP)
     * ========================================================================= */

    public function findLeTanById(string $id): ?LeTan
    {
        $stmt = $this->db->prepare("SELECT * FROM LeTan WHERE MaLeTan = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new LeTan(
            $row['MaLeTan'],
            $row['HoTen'],
            $row['SDT'],
            $row['Email'] ?? null,
            $row['MaTK']
        );
    }

    public function getAllLeTan(array $filters = []): array
    {
        $sql = "SELECT * FROM LeTan WHERE 1=1";
        $params = [];

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim($filters['keyword']) . '%';
            $sql .= " AND (MaLeTan LIKE :kw1 OR HoTen LIKE :kw2 OR SDT LIKE :kw3)";
            $params[':kw1'] = $kw;
            $params[':kw2'] = $kw;
            $params[':kw3'] = $kw;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $result = [];
        foreach ($rows as $row) {
            $result[] = new LeTan(
                $row['MaLeTan'],
                $row['HoTen'],
                $row['SDT'],
                $row['Email'] ?? null,
                $row['MaTK']
            );
        }
        return $result;
    }

    public function saveLeTan(LeTan $leTan): bool
    {
        $existing = $this->findLeTanById($leTan->getMaLeTan());
        if ($existing) {
            $stmt = $this->db->prepare("UPDATE LeTan SET HoTen = :hoTen, SDT = :sdt, Email = :email, MaTK = :maTK WHERE MaLeTan = :maLeTan");
            return $stmt->execute([
                ':hoTen' => $leTan->getHoTen(),
                ':sdt' => $leTan->getSDT(),
                ':email' => $leTan->getEmail(),
                ':maTK' => $leTan->getMaTK(),
                ':maLeTan' => $leTan->getMaLeTan(),
            ]);
        }

        $stmt = $this->db->prepare("INSERT INTO LeTan (MaLeTan, HoTen, SDT, Email, MaTK) VALUES (:maLeTan, :hoTen, :sdt, :email, :maTK)");
        return $stmt->execute([
            ':maLeTan' => $leTan->getMaLeTan(),
            ':hoTen' => $leTan->getHoTen(),
            ':sdt' => $leTan->getSDT(),
            ':email' => $leTan->getEmail(),
            ':maTK' => $leTan->getMaTK(),
        ]);
    }

    public function deleteLeTan(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM LeTan WHERE MaLeTan = :id");
        return $stmt->execute([':id' => $id]);
    }
}
