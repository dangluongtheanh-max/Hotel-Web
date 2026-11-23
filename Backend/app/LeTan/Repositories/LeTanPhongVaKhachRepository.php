<?php

declare(strict_types=1);

namespace App\LeTan\Repositories;

use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý Sơ đồ trạng thái phòng, Bảo trì,
 * Người lưu trú và Dịch vụ nhanh cho phòng đang lưu trú.
 */
class LeTanPhongVaKhachRepository
{
    private ?Database $db;
    private ?LeTanPhieuRepository $phieuRepo;

    public function __construct(?Database $db = null, ?LeTanPhieuRepository $phieuRepo = null)
    {
        $this->db = $db ?? new Database();
        $this->phieuRepo = $phieuRepo ?? new LeTanPhieuRepository($this->db);
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    /**
     * Kiểm tra phòng vật lý có đang ở trạng thái Trống hay không.
     * Tiêu chí thống nhất:
     * 1. Cột Phong.TrangThaiPhong = 'Trong'
     * 2. Không có phiếu nào đang lưu trú thực tế trong phòng đó (TrangThai = 'DangLuuTru' trong ChiTietPhieuNhanPhong)
     */
    public function kiemTraPhongCoTrong(string $maPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            SELECT p.TrangThaiPhong,
                   (SELECT COUNT(*) FROM ChiTietPhieuNhanPhong ctp 
                    WHERE ctp.MaPhong = p.MaPhong AND ctp.TrangThai = 'DangLuuTru') AS SoKhachDangO
            FROM Phong p
            WHERE p.MaPhong = ?
            LIMIT 1
        ");
        $stmt->execute([$maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        // Phòng được coi là trống / sẵn sàng nhận phòng:
        // - Không ở trạng thái DangSuDung (đang có khách ở) và không ở trạng thái BaoTri
        // - Không có phiếu nào khác đang lưu trú thực tế trong phòng đó (SoKhachDangO = 0)
        // Các trạng thái 'Trong' hoặc 'DaDat' (đã giữ chỗ cho khách đến nhận) đều hợp lệ.
        $isVacant = in_array($row['TrangThaiPhong'], ['Trong', 'DaDat'], true);
        return ($isVacant && (int)$row['SoKhachDangO'] === 0);
    }

    /**
     * Cập nhật chuyển phòng cho người lưu trú
     */
    public function capNhatPhongNguoiLuuTru(string $maPhieu, string $maNLT, string $maPhongMoi): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE NguoiLuuTru 
            SET MaPhong = ? 
            WHERE MaNguoiLuuTru = ? AND MaPhieu = ?
        ");
        return $stmt->execute([$maPhongMoi, $maNLT, $maPhieu]);
    }

    /**
     * Cập nhật thông tin giấy tờ, họ tên của người lưu trú
     */
    public function capNhatThongTinNLT(string $maPhieu, string $maNLT, array $data): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE NguoiLuuTru 
            SET HoTen = :hoTen,
                NgaySinh = :ngaySinh,
                QuocTich = :quocTich,
                LoaiGiayTo = :loaiGiayTo,
                SoGiayTo = :soGiayTo,
                GhiChu = :ghiChu
            WHERE MaNguoiLuuTru = :maNLT AND MaPhieu = :maPhieu
        ");
        return $stmt->execute([
            ':hoTen' => $data['hoTen'],
            ':ngaySinh' => $data['ngaySinh'],
            ':quocTich' => $data['quocTich'],
            ':loaiGiayTo' => $data['loaiGiayTo'],
            ':soGiayTo' => $data['soGiayTo'],
            ':ghiChu' => $data['ghiChu'] ?? null,
            ':maNLT' => $maNLT,
            ':maPhieu' => $maPhieu
        ]);
    }

    /**
     * Lấy thống kê KPI toàn khách sạn, danh sách các loại phòng kèm chỉ số phòng,
     * và danh sách chi tiết các phòng kèm thông tin người đứng phiếu và người lưu trú thực tế.
     */
    public function getThongKeLoaiPhongVaPhong(?string $maLoaiPhong = null, ?string $tuNgay = null, ?string $denNgay = null): array
    {
        // 1. Tự động quét No-show quá thời gian trả phòng dự kiến & giải phóng phòng ChoThanhToan quá hạn 20 phút
        $this->phieuRepo->tuDongHuyPhieuQuaHanTraPhong();
        try {
            (new \App\ThanhToan\Services\ThanhToanService())->quetVaXuLyTatCaPhieuHetHan();
        } catch (\Throwable $e) {}

        $db = $this->getConnection();

        $todayStr = date('Y-m-d');
        $tuNgayNorm = (!empty($tuNgay) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $tuNgay)) ? $tuNgay : $todayStr;
        if (!empty($denNgay) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $denNgay) && $denNgay > $tuNgayNorm) {
            $denNgayNorm = $denNgay;
        } else {
            $denNgayNorm = date('Y-m-d', strtotime($tuNgayNorm . ' +1 day'));
        }
        $includesToday = ($tuNgayNorm <= $todayStr && $denNgayNorm > $todayStr);

        // 2. Lấy danh sách Loại phòng
        $lpSql = "
            SELECT 
                lp.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia,
                lp.SoNguoiToiDa,
                lp.MoTa,
                lp.TienNghi,
                lp.TinhTrang
            FROM LoaiPhong lp
        ";
        $lpParams = [];
        if (!empty($maLoaiPhong)) {
            $lpSql .= " WHERE lp.MaLoaiPhong = ?";
            $lpParams[] = $maLoaiPhong;
        }
        $lpSql .= " ORDER BY lp.MaLoaiPhong ASC";

        $lpStmt = $db->prepare($lpSql);
        $lpStmt->execute($lpParams);
        $loaiPhongs = $lpStmt->fetchAll(PDO::FETCH_ASSOC);

        $lpStatsMap = [];
        foreach ($loaiPhongs as &$lp) {
            $lp['tongPhong'] = 0;
            $lp['soPhongTrong'] = 0;
            $lp['soPhongChoThanhToan'] = 0;
            $lp['soPhongDangO'] = 0;
            $lp['soPhongDaDat'] = 0;
            $lp['soPhongBaoTri'] = 0;
            $lp['tyLeSuDung'] = 0.0;
            $lpStatsMap[$lp['MaLoaiPhong']] = &$lp;
        }
        unset($lp);

        // 3. Lấy toàn bộ phòng vật lý
        $phongSql = "
            SELECT 
                p.MaPhong,
                p.MaLoaiPhong,
                lp.TenLoaiPhong,
                lp.Gia,
                lp.SoNguoiToiDa,
                p.TrangThaiPhong
            FROM Phong p
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
        ";
        $phongParams = [];
        if (!empty($maLoaiPhong)) {
            $phongSql .= " WHERE p.MaLoaiPhong = ?";
            $phongParams[] = $maLoaiPhong;
        }
        $phongSql .= " ORDER BY p.MaLoaiPhong ASC, p.MaPhong ASC";

        $phongStmt = $db->prepare($phongSql);
        $phongStmt->execute($phongParams);
        $phongs = $phongStmt->fetchAll(PDO::FETCH_ASSOC);

        // 4. Lấy tất cả chi tiết phiếu còn hiệu lực (bỏ qua đã hủy / đã trả / thanh toán thất bại)
        $slipsSql = "
            SELECT 
                ctp.MaPhieu,
                ctp.MaPhong,
                ctp.TrangThai AS TrangThaiLuuTru,
                ctp.ThoiGianNhanPhong,
                ctp.ThoiGianTraPhong,
                kh.HoTen AS TenNguoiDat,
                kh.SDT AS SDTNguoiDat
            FROM ChiTietPhieuNhanPhong ctp
            JOIN PhieuNhanPhong pnp ON ctp.MaPhieu = pnp.MaPhieu
            JOIN Phong p ON ctp.MaPhong = p.MaPhong
            LEFT JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            WHERE ctp.TrangThai NOT IN ('DaHuy', 'DaTraPhong', 'ThanhToanThatBai')
              AND pnp.TrangThai NOT IN ('DaHuy', 'DaTraPhong', 'ThanhToanThatBai')
        ";
        $slipsParams = [];
        if (!empty($maLoaiPhong)) {
            $slipsSql .= " AND p.MaLoaiPhong = ?";
            $slipsParams[] = $maLoaiPhong;
        }
        $slipsSql .= " ORDER BY ctp.ThoiGianNhanPhong ASC, ctp.MaPhieu ASC";

        $slipsStmt = $db->prepare($slipsSql);
        $slipsStmt->execute($slipsParams);
        $allActiveSlips = $slipsStmt->fetchAll(PDO::FETCH_ASSOC);

        $slipsByRoom = [];
        foreach ($allActiveSlips as $sl) {
            $slipsByRoom[$sl['MaPhong']][] = $sl;
        }

        // 5. Tính trạng thái từng phòng trong kỳ lọc [tuNgayNorm, denNgayNorm) và lịch đặt sắp tới
        $priorityMap = [
            'DangLuuTru' => 3,
            'DaDatPhong' => 2,
            'ChoThanhToan' => 1
        ];
        $statusMapFromSlip = [
            'DangLuuTru' => 'DangSuDung',
            'DaDatPhong' => 'DaDat',
            'ChoThanhToan' => 'ChoThanhToan'
        ];

        $tongSoPhong = 0;
        $soPhongTrong = 0;
        $soPhongChoThanhToan = 0;
        $soPhongDangO = 0;
        $soPhongDaDat = 0;
        $soPhongBaoTri = 0;

        foreach ($phongs as &$phong) {
            $maPhong = $phong['MaPhong'];
            $physicalStatus = $phong['TrangThaiPhong'];
            $phong['TrangThaiPhongVatLy'] = $physicalStatus;

            $roomSlips = $slipsByRoom[$maPhong] ?? [];
            $matchedSlip = null;
            $matchedScore = -1;
            $lichSapToi = [];

            foreach ($roomSlips as $sl) {
                $ngayNhan = substr((string)$sl['ThoiGianNhanPhong'], 0, 10);
                $ngayTra = substr((string)$sl['ThoiGianTraPhong'], 0, 10);

                // Thu thập lịch đặt còn hiệu lực sắp tới hoặc đang lưu trú
                if ($ngayTra >= $todayStr || $sl['TrangThaiLuuTru'] === 'DangLuuTru') {
                    $lichSapToi[] = [
                        'MaPhieu' => $sl['MaPhieu'],
                        'TrangThaiLuuTru' => $sl['TrangThaiLuuTru'],
                        'ThoiGianNhanPhong' => $sl['ThoiGianNhanPhong'],
                        'ThoiGianTraPhong' => $sl['ThoiGianTraPhong'],
                        'TenNguoiDat' => $sl['TenNguoiDat'],
                        'SDTNguoiDat' => $sl['SDTNguoiDat'],
                    ];
                }

                // Kiểm tra trùng khoảng ngày lọc: ngay_nhan_phieu < ngay_tra_loc AND ngay_tra_phieu > ngay_nhan_loc
                $isOverlap = ($ngayNhan < $denNgayNorm && $ngayTra > $tuNgayNorm);
                $isCurrentInHouse = ($includesToday && $sl['TrangThaiLuuTru'] === 'DangLuuTru');

                if ($isOverlap || $isCurrentInHouse) {
                    $score = $priorityMap[$sl['TrangThaiLuuTru']] ?? 0;
                    if ($score > $matchedScore) {
                        $matchedScore = $score;
                        $matchedSlip = $sl;
                    }
                }
            }

            if ($matchedSlip) {
                $effectiveStatus = $statusMapFromSlip[$matchedSlip['TrangThaiLuuTru']] ?? 'DaDat';
                $phong['TrangThaiPhong'] = $effectiveStatus;
                $phong['MaPhieu'] = $matchedSlip['MaPhieu'];
                $phong['TrangThaiLuuTru'] = $matchedSlip['TrangThaiLuuTru'];
                $phong['ThoiGianNhanPhong'] = $matchedSlip['ThoiGianNhanPhong'];
                $phong['ThoiGianTraPhong'] = $matchedSlip['ThoiGianTraPhong'];
                $phong['TenNguoiDat'] = $matchedSlip['TenNguoiDat'];
                $phong['SDTNguoiDat'] = $matchedSlip['SDTNguoiDat'];
            } else {
                $effectiveStatus = ($physicalStatus === 'BaoTri') ? 'BaoTri' : 'Trong';
                $phong['TrangThaiPhong'] = $effectiveStatus;
                $phong['MaPhieu'] = null;
                $phong['TrangThaiLuuTru'] = null;
                $phong['ThoiGianNhanPhong'] = null;
                $phong['ThoiGianTraPhong'] = null;
                $phong['TenNguoiDat'] = null;
                $phong['SDTNguoiDat'] = null;
            }

            $phong['lichDatSapToi'] = $lichSapToi;

            // Cộng dồn KPI tổng thể và theo hạng phòng
            $tongSoPhong++;
            $maLP = $phong['MaLoaiPhong'];
            if (isset($lpStatsMap[$maLP])) {
                $lpStatsMap[$maLP]['tongPhong']++;
            }

            if ($effectiveStatus === 'DangSuDung') {
                $soPhongDangO++;
                if (isset($lpStatsMap[$maLP])) $lpStatsMap[$maLP]['soPhongDangO']++;
            } elseif ($effectiveStatus === 'DaDat') {
                $soPhongDaDat++;
                if (isset($lpStatsMap[$maLP])) $lpStatsMap[$maLP]['soPhongDaDat']++;
            } elseif ($effectiveStatus === 'ChoThanhToan') {
                $soPhongChoThanhToan++;
                if (isset($lpStatsMap[$maLP])) $lpStatsMap[$maLP]['soPhongChoThanhToan']++;
            } elseif ($effectiveStatus === 'BaoTri') {
                $soPhongBaoTri++;
                if (isset($lpStatsMap[$maLP])) $lpStatsMap[$maLP]['soPhongBaoTri']++;
            } else {
                $soPhongTrong++;
                if (isset($lpStatsMap[$maLP])) $lpStatsMap[$maLP]['soPhongTrong']++;
            }
        }
        unset($phong);

        foreach ($loaiPhongs as &$lp) {
            $lp['tyLeSuDung'] = $lp['tongPhong'] > 0
                ? round((($lp['soPhongDangO'] + $lp['soPhongDaDat'] + $lp['soPhongChoThanhToan']) / $lp['tongPhong']) * 100, 1)
                : 0.0;
        }
        unset($lp);

        $tyLeLapDay = $tongSoPhong > 0
            ? round((($soPhongDangO + $soPhongDaDat + $soPhongChoThanhToan) / $tongSoPhong) * 100, 1)
            : 0.0;

        $kpi = [
            'tongSoPhong' => $tongSoPhong,
            'soPhongTrong' => $soPhongTrong,
            'soPhongChoThanhToan' => $soPhongChoThanhToan,
            'soPhongDangO' => $soPhongDangO,
            'soPhongDaDat' => $soPhongDaDat,
            'soPhongBaoTri' => $soPhongBaoTri,
            'tyLeLapDay' => $tyLeLapDay
        ];

        // 6. Lấy danh sách người lưu trú thực tế cho các phòng đang có phiếu trong kỳ
        $activePhieuList = array_values(array_filter(array_unique(array_column($phongs, 'MaPhieu'))));
        $nltMap = [];
        if (!empty($activePhieuList)) {
            $placeholders = implode(',', array_fill(0, count($activePhieuList), '?'));
            $nltSql = "
                SELECT MaNguoiLuuTru, HoTen, NgaySinh, QuocTich, LoaiGiayTo, SoGiayTo, GhiChu, MaPhieu, MaPhong
                FROM NguoiLuuTru
                WHERE MaPhieu IN ($placeholders)
            ";
            $nltStmt = $db->prepare($nltSql);
            $nltStmt->execute($activePhieuList);
            $nlts = $nltStmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($nlts as $nlt) {
                $key = $nlt['MaPhieu'] . '_' . $nlt['MaPhong'];
                $nltMap[$key][] = $nlt;
            }
        }

        foreach ($phongs as &$phong) {
            if (!empty($phong['MaPhieu'])) {
                $key = $phong['MaPhieu'] . '_' . $phong['MaPhong'];
                $phong['danhSachNguoiLuuTru'] = $nltMap[$key] ?? [];
            } else {
                $phong['danhSachNguoiLuuTru'] = [];
            }
        }
        unset($phong);

        return [
            'boLocNgay' => [
                'tuNgay' => $tuNgayNorm,
                'denNgay' => $denNgayNorm,
                'homNay' => $todayStr
            ],
            'kpi' => $kpi,
            'loaiPhong' => $loaiPhongs,
            'danhSachPhong' => $phongs
        ];
    }

    /**
     * Cập nhật chuyển đổi trạng thái phòng giữa 'Trong' và 'BaoTri'
     */
    public function capNhatTrangThaiBaoTri(string $maPhong, string $trangThaiMoi): array
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT MaPhong, TrangThaiPhong, MaLoaiPhong FROM Phong WHERE MaPhong = ?");
        $stmt->execute([$maPhong]);
        $phong = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$phong) {
            throw new \Exception("Không tìm thấy thông tin phòng {$maPhong}.", 404);
        }

        $currentStatus = $phong['TrangThaiPhong'];
        if ($trangThaiMoi === 'Trong') {
            // Chuyển từ BaoTri sang Trong
            if ($currentStatus !== 'BaoTri') {
                throw new \Exception("Phòng {$maPhong} hiện không ở trạng thái Bảo trì (hiện tại: {$currentStatus}).", 400);
            }
        } elseif ($trangThaiMoi === 'BaoTri') {
            // Chuyển từ Trong sang BaoTri
            if ($currentStatus !== 'Trong') {
                throw new \Exception("Chỉ có thể chuyển phòng đang Trống sang Bảo trì (phòng {$maPhong} hiện tại: {$currentStatus}).", 400);
            }
        } else {
            throw new \Exception("Trạng thái mới không hợp lệ. Chỉ chấp nhận 'Trong' hoặc 'BaoTri'.", 400);
        }

        $stmtUp = $db->prepare("UPDATE Phong SET TrangThaiPhong = ? WHERE MaPhong = ?");
        $stmtUp->execute([$trangThaiMoi, $maPhong]);

        return [
            'maPhong' => $maPhong,
            'trangThaiCu' => $currentStatus,
            'trangThaiMoi' => $trangThaiMoi,
            'message' => ($trangThaiMoi === 'Trong') 
                ? "Hoàn tất bảo trì/dọn dẹp cho phòng {$maPhong}. Phòng đã sẵn sàng đón khách!" 
                : "Đã chuyển phòng {$maPhong} sang trạng thái Bảo trì / Chờ dọn dẹp."
        ];
    }

    /**
     * Lấy danh mục dịch vụ khả dụng trong khách sạn (Đồ ăn, giặt ủi, thuê xe...)
     */
    public function layDanhSachDichVuKhaDung(): array
    {
        $db = $this->getConnection();
        $sql = "
            SELECT 
                dv.MaDV,
                dv.TenDV,
                dv.MoTa as MoTaDV,
                ct.MaCTDV,
                ct.TenCTDV,
                ct.MoTa as MoTaCTDV,
                ct.DonGia,
                ct.SoLuongTon
            FROM DichVu dv
            JOIN ChiTietDichVu ct ON dv.MaDV = ct.MaDV
            WHERE dv.TrangThai = 'DangCungCap' AND dv.MaDV != 'DVPhuThu'
            ORDER BY dv.MaDV ASC, ct.TenCTDV ASC
        ";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $groups = [];
        foreach ($rows as $r) {
            $maDV = $r['MaDV'];
            if (!isset($groups[$maDV])) {
                $groups[$maDV] = [
                    'maDV' => $maDV,
                    'tenDV' => $r['TenDV'],
                    'moTa' => $r['MoTaDV'],
                    'danhSach' => []
                ];
            }
            $groups[$maDV]['danhSach'][] = [
                'maCTDV' => $r['MaCTDV'],
                'tenCTDV' => $r['TenCTDV'],
                'moTa' => $r['MoTaCTDV'],
                'donGia' => (float)$r['DonGia'],
                'soLuongTon' => (int)$r['SoLuongTon']
            ];
        }

        return array_values($groups);
    }

    /**
     * Đặt dịch vụ nhanh cho phòng đang lưu trú
     */
    public function datDichVuNhanhChoPhong(array $data): array
    {
        $db = $this->getConnection();
        $maPhong = trim($data['maPhong'] ?? '');
        $maCTDV = trim($data['maCTDV'] ?? '');
        $soLuong = max(1, (int)($data['soLuong'] ?? 1));
        $ghiChu = trim($data['ghiChu'] ?? '');
        $thoiGianNhan = !empty($data['thoiGianNhan']) ? $data['thoiGianNhan'] : null;
        $thoiGianTra = !empty($data['thoiGianTra']) ? $data['thoiGianTra'] : null;

        if (empty($maPhong)) {
            throw new \Exception("Vui lòng cung cấp mã phòng cần đặt dịch vụ.", 400);
        }
        if (empty($maCTDV)) {
            throw new \Exception("Vui lòng chọn dịch vụ chi tiết cần đặt.", 400);
        }

        // 1. Kiểm tra phòng và tìm mã phiếu đang lưu trú
        $stmtPhieu = $db->prepare("
            SELECT ctp.MaPhieu, ctp.MaPhong, pnp.MaKH, kh.HoTen as TenKH
            FROM ChiTietPhieuNhanPhong ctp
            JOIN PhieuNhanPhong pnp ON ctp.MaPhieu = pnp.MaPhieu
            LEFT JOIN KhachHang kh ON pnp.MaKH = kh.MaKH
            WHERE ctp.MaPhong = ? AND ctp.TrangThai = 'DangLuuTru'
            ORDER BY ctp.MaPhieu DESC LIMIT 1
        ");
        $stmtPhieu->execute([$maPhong]);
        $activeRoom = $stmtPhieu->fetch(PDO::FETCH_ASSOC);

        if (!$activeRoom) {
            throw new \Exception("Phòng {$maPhong} hiện không có khách đang lưu trú (hoặc chưa check-in). Không thể đặt dịch vụ.", 400);
        }

        $maPhieu = $activeRoom['MaPhieu'];

        // 2. Lấy thông tin dịch vụ chi tiết
        $stmtDV = $db->prepare("
            SELECT ct.MaCTDV, ct.TenCTDV, ct.DonGia, ct.SoLuongTon, dv.TenDV
            FROM ChiTietDichVu ct
            JOIN DichVu dv ON ct.MaDV = dv.MaDV
            WHERE ct.MaCTDV = ? AND dv.TrangThai = 'DangCungCap'
        ");
        $stmtDV->execute([$maCTDV]);
        $dvInfo = $stmtDV->fetch(PDO::FETCH_ASSOC);

        if (!$dvInfo) {
            throw new \Exception("Dịch vụ được chọn không tồn tại hoặc tạm ngưng cung cấp.", 404);
        }

        $donGia = (float)$dvInfo['DonGia'];
        $thanhTien = $donGia * $soLuong;

        // 3. Insert vào ctsudungdichvu
        $stmtInsert = $db->prepare("
            INSERT INTO ctsudungdichvu (MaCTDV, MaPhieu, MaPhong, DonGia, GhiChu, SoLuong, ThoiGianNhan, ThoiGianTra)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmtInsert->execute([
            $maCTDV,
            $maPhieu,
            $maPhong,
            $donGia,
            $ghiChu ?: "Đặt nhanh tại phòng {$maPhong}",
            $soLuong,
            $thoiGianNhan,
            $thoiGianTra
        ]);

        $insertId = $db->lastInsertId();

        return [
            'maCTSDDV' => $insertId,
            'maPhieu' => $maPhieu,
            'maPhong' => $maPhong,
            'tenKhach' => $activeRoom['TenKH'] ?? 'Khách lưu trú',
            'tenDichVu' => $dvInfo['TenDV'],
            'tenCTDV' => $dvInfo['TenCTDV'],
            'soLuong' => $soLuong,
            'donGia' => $donGia,
            'thanhTien' => $thanhTien,
            'ghiChu' => $ghiChu,
            'thoiGianNhan' => $thoiGianNhan,
            'thoiGianTra' => $thoiGianTra,
            'message' => "Đặt thành công dịch vụ '{$dvInfo['TenCTDV']}' (x{$soLuong}) cho phòng {$maPhong}!"
        ];
    }
}
