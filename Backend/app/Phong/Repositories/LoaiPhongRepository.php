<?php

declare(strict_types=1);

namespace App\Phong\Repositories;

use App\Phong\Models\LoaiPhong;
use Database\Database;
use PDO;

/**
 * Repository chuyên trách quản lý dữ liệu Loại phòng (LoaiPhong).
 */
class LoaiPhongRepository
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? new Database();
    }

    public function getConnection(): PDO
    {
        return Database::getConnection();
    }

    public function findLoaiPhongDetailById(string $id): ?array
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM LoaiPhong WHERE MaLoaiPhong = ? LIMIT 1");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $row['Gia'] = (float)$row['Gia'];
        $row['SoNguoiToiDa'] = (int)$row['SoNguoiToiDa'];
        $row['MoTa'] = $row['MoTa'] ?? '';
        $row['TienNghi'] = $row['TienNghi'] ?? '';

        $diemNhan = [];
        if (!empty($row['diem_nhan'])) {
            $decoded = json_decode((string)$row['diem_nhan'], true);
            if (is_array($decoded)) {
                $diemNhan = $decoded;
            }
        }
        $dacQuyen = [];
        if (!empty($row['dac_quyen'])) {
            $decoded = json_decode((string)$row['dac_quyen'], true);
            if (is_array($decoded)) {
                $dacQuyen = $decoded;
            }
        }
        $row['diem_nhan_list'] = $diemNhan;
        $row['dac_quyen_list'] = $dacQuyen;

        // Danh sách ảnh
        $anh = [];
        try {
            $stmtAnh = $db->prepare(
                "SELECT id, ma_loai_phong, duong_dan, la_anh_dai_dien, thu_tu
                 FROM loai_phong_anh
                 WHERE ma_loai_phong = ?
                 ORDER BY la_anh_dai_dien DESC, thu_tu ASC, id ASC"
            );
            $stmtAnh->execute([$id]);
            $anh = $stmtAnh->fetchAll(PDO::FETCH_ASSOC);
            foreach ($anh as &$a) {
                $a['id'] = (int)$a['id'];
                $a['la_anh_dai_dien'] = (int)$a['la_anh_dai_dien'];
                $a['thu_tu'] = (int)$a['thu_tu'];
            }
            unset($a);
        } catch (\Throwable $e) {
            $anh = [];
        }
        $row['anh'] = $anh;

        // Tiện nghi đã nhóm sẵn theo 5 nhóm
        $groupsOrder = [
            'Phòng ngủ & Thư giãn',
            'Phòng tắm & Chăm sóc cá nhân',
            'Công nghệ & Giải trí',
            'Ẩm thực & Thức uống',
            'Tiện ích & Dịch vụ an toàn',
        ];
        $grouped = [];
        foreach ($groupsOrder as $g) {
            $grouped[$g] = [];
        }
        $tienNghiIds = [];
        try {
            $stmtTn = $db->prepare(
                "SELECT tn.id, tn.ten, tn.nhom, tn.icon
                 FROM loai_phong_tien_nghi lptn
                 INNER JOIN tien_nghi tn ON tn.id = lptn.tien_nghi_id
                 WHERE lptn.ma_loai_phong = ?
                 ORDER BY tn.id ASC"
            );
            $stmtTn->execute([$id]);
            foreach ($stmtTn->fetchAll(PDO::FETCH_ASSOC) as $tn) {
                $tn['id'] = (int)$tn['id'];
                $tienNghiIds[] = $tn['id'];
                $g = $tn['nhom'] ?: 'Tiện ích & Dịch vụ an toàn';
                $grouped[$g][] = $tn;
            }
        } catch (\Throwable $e) {
            // Bỏ qua nếu chưa chạy migration
        }

        // Loại bỏ nhóm rỗng để frontend dễ render
        $row['tien_nghi_nhom'] = array_filter($grouped, static fn(array $items) => count($items) > 0);
        $row['tien_nghi_ids'] = $tienNghiIds;

        return $row;
    }

    public function findLoaiPhongById(string $id): ?LoaiPhong
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT * FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        return new LoaiPhong(
            $row['MaLoaiPhong'],
            $row['TenLoaiPhong'],
            (float) $row['Gia'],
            (int) $row['SoNguoiToiDa'],
            $row['MoTa'] ?? '',
            $row['TienNghi'] ?? ''
        );
    }

    public function getAllLoaiPhong(array $filters = []): array
    {
        $db = $this->getConnection();
        $sql = "SELECT * FROM LoaiPhong WHERE 1=1";
        $params = [];

        if (!empty($filters['MaLoaiPhong'])) {
            $sql .= " AND MaLoaiPhong = ?";
            $params[] = $filters['MaLoaiPhong'];
        }

        if (!empty($filters['keyword'])) {
            $kw = '%' . trim((string)$filters['keyword']) . '%';
            $sql .= " AND (TenLoaiPhong LIKE ? OR MoTa LIKE ? OR TienNghi LIKE ?)";
            $params[] = $kw;
            $params[] = $kw;
            $params[] = $kw;
        }

        $sql .= " ORDER BY MaLoaiPhong ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($rows as $row) {
            $result[] = new LoaiPhong(
                $row['MaLoaiPhong'],
                $row['TenLoaiPhong'],
                (float) $row['Gia'],
                (int) $row['SoNguoiToiDa'],
                $row['MoTa'] ?? '',
                $row['TienNghi'] ?? ''
            );
        }
        return $result;
    }

    public function existsLoaiPhong(string $id): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $stmt->execute([$id]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function createLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            INSERT INTO LoaiPhong (MaLoaiPhong, TenLoaiPhong, Gia, SoNguoiToiDa, MoTa, TienNghi, TinhTrang)
            VALUES (?, ?, ?, ?, ?, ?, 'ConPhong')
        ");
        return $stmt->execute([
            $loaiPhong->getMaLoaiPhong(),
            $loaiPhong->getTenLoaiPhong(),
            $loaiPhong->getGia(),
            $loaiPhong->getSoNguoiToiDa(),
            $loaiPhong->getMoTa(),
            $loaiPhong->getTienNghi()
        ]);
    }

    public function updateLoaiPhong(LoaiPhong $loaiPhong): bool
    {
        $db = $this->getConnection();
        $stmt = $db->prepare("
            UPDATE LoaiPhong 
            SET TenLoaiPhong = ?, Gia = ?, SoNguoiToiDa = ?, MoTa = ?, TienNghi = ?
            WHERE MaLoaiPhong = ?
        ");
        return $stmt->execute([
            $loaiPhong->getTenLoaiPhong(),
            $loaiPhong->getGia(),
            $loaiPhong->getSoNguoiToiDa(),
            $loaiPhong->getMoTa(),
            $loaiPhong->getTienNghi(),
            $loaiPhong->getMaLoaiPhong()
        ]);
    }
}
