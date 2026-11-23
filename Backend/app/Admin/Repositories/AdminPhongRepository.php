<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use App\Phong\Models\Phong;
use Database\Database;
use PDO;

/**
 * Repository quản lý phòng vật lý và loại phòng.
 */
class AdminPhongRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public const NHOM_TIEN_NGHI = [
        'Phòng ngủ & Thư giãn',
        'Phòng tắm & Chăm sóc cá nhân',
        'Công nghệ & Giải trí',
        'Ẩm thực & Thức uống',
        'Tiện ích & Dịch vụ an toàn',
    ];

    public const EXTRA_LOAI_PHONG_COLS = [
        'tom_tat',
        'dien_tich',
        'tang',
        'bo_tri_giuong',
        'huong_view',
        'tien_nghi_phong_tam',
        'quy_dinh_hut_thuoc',
        'diem_nhan',
        'dac_quyen',
        'gio_nhan_phong',
        'gio_tra_phong',
        'chinh_sach_huy',
        'chinh_sach_tre_em',
        'phuong_thuc_coc_thanh_toan',
    ];

    /* =========================================================================
     * QUẢN LÝ PHÒNG THEO TỪNG LOẠI PHÒNG (Admin)
     * ========================================================================= */

    public function getPhongTheoLoai(): array
    {
        $sqlLoai = "SELECT * FROM LoaiPhong ORDER BY Gia ASC";
        $dsLoai = $this->db->query($sqlLoai)->fetchAll(PDO::FETCH_ASSOC);

        $sqlPhong = "SELECT MaPhong, TrangThaiPhong, MaLoaiPhong FROM Phong ORDER BY MaPhong ASC";
        $dsPhong = $this->db->query($sqlPhong)->fetchAll(PDO::FETCH_ASSOC);

        $anhByLoai = [];
        try {
            $sqlAnh = "SELECT id, ma_loai_phong, duong_dan, la_anh_dai_dien, thu_tu
                       FROM loai_phong_anh
                       ORDER BY la_anh_dai_dien DESC, thu_tu ASC, id ASC";
            foreach ($this->db->query($sqlAnh)->fetchAll(PDO::FETCH_ASSOC) as $a) {
                $a['id'] = (int)$a['id'];
                $a['la_anh_dai_dien'] = (int)$a['la_anh_dai_dien'];
                $a['thu_tu'] = (int)$a['thu_tu'];
                $anhByLoai[$a['ma_loai_phong']][] = $a;
            }
        } catch (\Throwable $e) {
            // Bảng loai_phong_anh chưa tạo (nếu chưa chạy migration)
        }

        $tnByLoai = [];
        try {
            $sqlTn = "SELECT ma_loai_phong, tien_nghi_id FROM loai_phong_tien_nghi";
            foreach ($this->db->query($sqlTn)->fetchAll(PDO::FETCH_ASSOC) as $tn) {
                $tnByLoai[$tn['ma_loai_phong']][] = (int)$tn['tien_nghi_id'];
            }
        } catch (\Throwable $e) {
            // Bảng loai_phong_tien_nghi chưa tạo
        }

        $result = [];
        foreach ($dsLoai as $loai) {
            $maLoai = $loai['MaLoaiPhong'];
            $phongThuocLoai = array_values(array_filter($dsPhong, function($p) use ($maLoai) {
                return $p['MaLoaiPhong'] === $maLoai;
            }));

            $loai['diem_nhan_list'] = $this->decodeJsonArray($loai['diem_nhan'] ?? null);
            $loai['dac_quyen_list'] = $this->decodeJsonArray($loai['dac_quyen'] ?? null);
            $loai['anh'] = $anhByLoai[$maLoai] ?? [];
            $loai['tien_nghi_ids'] = $tnByLoai[$maLoai] ?? [];

            $loai['danh_sach_phong'] = $phongThuocLoai;
            $loai['tong_phong'] = count($phongThuocLoai);
            $loai['phong_trong'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'Trong'));
            $loai['phong_dang_dung'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'DangSuDung'));
            $loai['phong_da_dat'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'DaDat'));
            $loai['phong_bao_tri'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'BaoTri'));
            $loai['phong_cho_thanh_toan'] = count(array_filter($phongThuocLoai, fn($p) => $p['TrangThaiPhong'] === 'ChoThanhToan'));

            $result[] = $loai;
        }

        return $result;
    }

    private function decodeJsonArray(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    /* =========================================================================
     * THƯ VIỆN TIỆN NGHI
     * ========================================================================= */

    public function getAllTienNghi(): array
    {
        $stmt = $this->db->query("SELECT id, ten, nhom, icon, created_at FROM tien_nghi ORDER BY nhom ASC, id ASC");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as &$r) {
            $r['id'] = (int)$r['id'];
        }
        unset($r);

        $grouped = [];
        foreach (self::NHOM_TIEN_NGHI as $nhom) {
            $grouped[$nhom] = [];
        }
        foreach ($rows as $r) {
            $g = $r['nhom'] ?: 'Tiện ích & Dịch vụ an toàn';
            $grouped[$g][] = $r;
        }

        return [
            'items' => $rows,
            'groups' => $grouped,
            'group_names' => array_keys($grouped),
        ];
    }

    public function createOrGetTienNghi(string $ten, string $nhom, ?string $icon = null): array
    {
        $cleanTen = trim($ten);
        $cleanNhom = trim($nhom);
        $cleanIcon = $icon !== null ? trim($icon) : null;
        if ($cleanIcon === '') {
            $cleanIcon = null;
        }

        $check = $this->db->prepare("SELECT id, ten, nhom, icon, created_at FROM tien_nghi WHERE LOWER(TRIM(ten)) = LOWER(TRIM(?)) LIMIT 1");
        $check->execute([$cleanTen]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            $existing['id'] = (int)$existing['id'];
            return ['existed' => true, 'item' => $existing];
        }

        $ins = $this->db->prepare("INSERT INTO tien_nghi (ten, nhom, icon) VALUES (?, ?, ?)");
        $ins->execute([$cleanTen, $cleanNhom, $cleanIcon]);
        $id = (int)$this->db->lastInsertId();

        $fetch = $this->db->prepare("SELECT id, ten, nhom, icon, created_at FROM tien_nghi WHERE id = ? LIMIT 1");
        $fetch->execute([$id]);
        $item = $fetch->fetch(PDO::FETCH_ASSOC);
        $item['id'] = (int)$item['id'];

        return ['existed' => false, 'item' => $item];
    }

    private function syncTienNghiForLoaiPhong(string $maLoaiPhong, array $tienNghiIds): ?string
    {
        $cleanIds = [];
        foreach ($tienNghiIds as $id) {
            $intId = (int)$id;
            if ($intId > 0) {
                $cleanIds[$intId] = $intId;
            }
        }
        $cleanIds = array_values($cleanIds);

        $del = $this->db->prepare("DELETE FROM loai_phong_tien_nghi WHERE ma_loai_phong = ?");
        $del->execute([$maLoaiPhong]);

        if (empty($cleanIds)) {
            return '';
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $stmtValid = $this->db->prepare("SELECT id, ten FROM tien_nghi WHERE id IN ($placeholders) ORDER BY id ASC");
        $stmtValid->execute($cleanIds);
        $validRows = $stmtValid->fetchAll(PDO::FETCH_ASSOC);

        $ins = $this->db->prepare("INSERT INTO loai_phong_tien_nghi (ma_loai_phong, tien_nghi_id) VALUES (?, ?)");
        $names = [];
        foreach ($validRows as $row) {
            $ins->execute([$maLoaiPhong, (int)$row['id']]);
            $names[] = $row['ten'];
        }

        return implode(', ', $names);
    }

    /* =========================================================================
     * QUẢN LÝ LOẠI PHÒNG (Admin CRUD)
     * ========================================================================= */

    public function generateMaLoaiPhong(): string
    {
        $stmt = $this->db->query("SELECT MaLoaiPhong FROM LoaiPhong WHERE MaLoaiPhong LIKE 'LP%' ORDER BY MaLoaiPhong DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, 2);
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        return sprintf('LP%03d', $maxNum + 1);
    }

    public function findLoaiPhongById(string $maLoaiPhong): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM LoaiPhong WHERE MaLoaiPhong = ? LIMIT 1");
        $stmt->execute([$maLoaiPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['diem_nhan_list'] = $this->decodeJsonArray($row['diem_nhan'] ?? null);
        $row['dac_quyen_list'] = $this->decodeJsonArray($row['dac_quyen'] ?? null);
        $row['anh'] = $this->getAnhByLoaiPhong($maLoaiPhong);
        $row['tien_nghi_ids'] = $this->getTienNghiIdsByLoaiPhong($maLoaiPhong);
        return $row;
    }

    public function getTienNghiIdsByLoaiPhong(string $maLoaiPhong): array
    {
        try {
            $stmt = $this->db->prepare("SELECT tien_nghi_id FROM loai_phong_tien_nghi WHERE ma_loai_phong = ?");
            $stmt->execute([$maLoaiPhong]);
            return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function createLoaiPhong(array $data): array
    {
        $maLoai = !empty($data['MaLoaiPhong']) ? trim($data['MaLoaiPhong']) : $this->generateMaLoaiPhong();
        $check = $this->db->prepare("SELECT 1 FROM LoaiPhong WHERE MaLoaiPhong = ?");
        $check->execute([$maLoai]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã loại phòng '{$maLoai}' đã tồn tại!");
        }

        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }

        try {
            $cols = ['MaLoaiPhong', 'TenLoaiPhong', 'Gia', 'SoNguoiToiDa', 'MoTa', 'TienNghi', 'TinhTrang'];
            $vals = [
                $maLoai,
                trim($data['TenLoaiPhong'] ?? ''),
                (float)($data['Gia'] ?? 0),
                (int)($data['SoNguoiToiDa'] ?? 2),
                $data['MoTa'] ?? null,
                $data['TienNghi'] ?? null,
                $data['TinhTrang'] ?? 'ConPhong',
            ];

            foreach (self::EXTRA_LOAI_PHONG_COLS as $col) {
                if (array_key_exists($col, $data)) {
                    $cols[] = $col;
                    $vals[] = $data[$col];
                }
            }

            $placeholders = implode(', ', array_fill(0, count($cols), '?'));
            $sql = "INSERT INTO LoaiPhong (" . implode(', ', $cols) . ") VALUES ($placeholders)";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($vals);

            if (isset($data['tien_nghi_ids']) && is_array($data['tien_nghi_ids'])) {
                $tienNghiText = $this->syncTienNghiForLoaiPhong($maLoai, $data['tien_nghi_ids']);
                $upd = $this->db->prepare("UPDATE LoaiPhong SET TienNghi = ? WHERE MaLoaiPhong = ?");
                $upd->execute([$tienNghiText, $maLoai]);
            }

            if ($ownTx) {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $this->findLoaiPhongById($maLoai);
    }

    public function updateLoaiPhong(string $maLoaiPhong, array $data): bool
    {
        $ownTx = !$this->db->inTransaction();
        if ($ownTx) {
            $this->db->beginTransaction();
        }

        try {
            $fields = [];
            $params = [];
            if (isset($data['TenLoaiPhong'])) {
                $fields[] = 'TenLoaiPhong = :ten';
                $params[':ten'] = trim($data['TenLoaiPhong']);
            }
            if (isset($data['Gia'])) {
                $fields[] = 'Gia = :gia';
                $params[':gia'] = (float)$data['Gia'];
            }
            if (isset($data['SoNguoiToiDa'])) {
                $fields[] = 'SoNguoiToiDa = :soNguoi';
                $params[':soNguoi'] = (int)$data['SoNguoiToiDa'];
            }
            if (array_key_exists('MoTa', $data)) {
                $fields[] = 'MoTa = :moTa';
                $params[':moTa'] = $data['MoTa'];
            }
            if (array_key_exists('TienNghi', $data)) {
                $fields[] = 'TienNghi = :tienNghi';
                $params[':tienNghi'] = $data['TienNghi'];
            }
            if (isset($data['TinhTrang'])) {
                $fields[] = 'TinhTrang = :tinhTrang';
                $params[':tinhTrang'] = $data['TinhTrang'];
            }

            foreach (self::EXTRA_LOAI_PHONG_COLS as $col) {
                if (array_key_exists($col, $data)) {
                    $fields[] = "$col = :$col";
                    $params[":$col"] = $data[$col];
                }
            }

            if (isset($data['tien_nghi_ids']) && is_array($data['tien_nghi_ids'])) {
                $tienNghiText = $this->syncTienNghiForLoaiPhong($maLoaiPhong, $data['tien_nghi_ids']);
                if (!isset($params[':tienNghi'])) {
                    $fields[] = 'TienNghi = :tienNghi';
                }
                $params[':tienNghi'] = $tienNghiText;
            }

            if (empty($fields)) {
                if ($ownTx) {
                    $this->db->commit();
                }
                return false;
            }

            $params[':ma'] = $maLoaiPhong;
            $stmt = $this->db->prepare("UPDATE LoaiPhong SET " . implode(', ', $fields) . " WHERE MaLoaiPhong = :ma");
            $ok = $stmt->execute($params);

            if ($ownTx) {
                $this->db->commit();
            }
            return $ok;
        } catch (\Throwable $e) {
            if ($ownTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function deleteLoaiPhong(string $maLoaiPhong): array
    {
        $item = $this->findLoaiPhongById($maLoaiPhong);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy loại phòng '{$maLoaiPhong}'!");
        }

        // Kiểm tra ràng buộc khóa ngoại với bảng Phong
        $chkPhong = $this->db->prepare("SELECT COUNT(*) FROM Phong WHERE MaLoaiPhong = ?");
        $chkPhong->execute([$maLoaiPhong]);
        if ((int)$chkPhong->fetchColumn() > 0) {
            throw new \RuntimeException("Không thể xóa loại phòng '{$maLoaiPhong}' vì vẫn còn phòng vật lý thuộc loại này. Hãy chuyển sang Ngừng kinh doanh.");
        }

        $images = $this->getAnhByLoaiPhong($maLoaiPhong);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("DELETE FROM loai_phong_tien_nghi WHERE ma_loai_phong = ?")->execute([$maLoaiPhong]);
            $this->db->prepare("DELETE FROM loai_phong_anh WHERE ma_loai_phong = ?")->execute([$maLoaiPhong]);
            $this->db->prepare("DELETE FROM LoaiPhong WHERE MaLoaiPhong = ?")->execute([$maLoaiPhong]);
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $images;
    }

    /* =========================================================================
     * QUẢN LÝ ẢNH LOẠI PHÒNG
     * ========================================================================= */

    public function getAnhByLoaiPhong(string $maLoaiPhong): array
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT id, ma_loai_phong, duong_dan, la_anh_dai_dien, thu_tu, created_at
                 FROM loai_phong_anh
                 WHERE ma_loai_phong = ?
                 ORDER BY la_anh_dai_dien DESC, thu_tu ASC, id ASC"
            );
            $stmt->execute([$maLoaiPhong]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as &$r) {
                $r['id'] = (int)$r['id'];
                $r['la_anh_dai_dien'] = (int)$r['la_anh_dai_dien'];
                $r['thu_tu'] = (int)$r['thu_tu'];
            }
            unset($r);
            return $rows;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function countAnhByLoaiPhong(string $maLoaiPhong): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loai_phong_anh WHERE ma_loai_phong = ?");
        $stmt->execute([$maLoaiPhong]);
        return (int)$stmt->fetchColumn();
    }

    public function getMaxThuTuAnh(string $maLoaiPhong): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(thu_tu), 0) FROM loai_phong_anh WHERE ma_loai_phong = ?");
        $stmt->execute([$maLoaiPhong]);
        return (int)$stmt->fetchColumn();
    }

    public function hasAnhDaiDien(string $maLoaiPhong): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM loai_phong_anh WHERE ma_loai_phong = ? AND la_anh_dai_dien = 1");
        $stmt->execute([$maLoaiPhong]);
        return (int)$stmt->fetchColumn() > 0;
    }

    public function addAnhLoaiPhong(string $maLoaiPhong, string $duongDan, bool $laAnhDaiDien, int $thuTu): array
    {
        $stmt = $this->db->prepare(
            "INSERT INTO loai_phong_anh (ma_loai_phong, duong_dan, la_anh_dai_dien, thu_tu) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$maLoaiPhong, $duongDan, $laAnhDaiDien ? 1 : 0, $thuTu]);
        $id = (int)$this->db->lastInsertId();

        $fetch = $this->db->prepare("SELECT id, ma_loai_phong, duong_dan, la_anh_dai_dien, thu_tu, created_at FROM loai_phong_anh WHERE id = ?");
        $fetch->execute([$id]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);
        $row['id'] = (int)$row['id'];
        $row['la_anh_dai_dien'] = (int)$row['la_anh_dai_dien'];
        $row['thu_tu'] = (int)$row['thu_tu'];
        return $row;
    }

    public function deleteAnhLoaiPhong(int $id, string $maLoaiPhong): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM loai_phong_anh WHERE id = ? AND ma_loai_phong = ? LIMIT 1");
        $stmt->execute([$id, $maLoaiPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }

        $this->db->beginTransaction();
        try {
            $del = $this->db->prepare("DELETE FROM loai_phong_anh WHERE id = ? AND ma_loai_phong = ?");
            $del->execute([$id, $maLoaiPhong]);

            if ((int)$row['la_anh_dai_dien'] === 1) {
                $next = $this->db->prepare(
                    "SELECT id FROM loai_phong_anh WHERE ma_loai_phong = ? ORDER BY thu_tu ASC, id ASC LIMIT 1"
                );
                $next->execute([$maLoaiPhong]);
                $nextId = $next->fetchColumn();
                if ($nextId) {
                    $this->db->prepare("UPDATE loai_phong_anh SET la_anh_dai_dien = 1 WHERE id = ?")->execute([$nextId]);
                }
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }

        return $row;
    }

    public function setAnhDaiDien(int $id, string $maLoaiPhong): bool
    {
        $chk = $this->db->prepare("SELECT id FROM loai_phong_anh WHERE id = ? AND ma_loai_phong = ? LIMIT 1");
        $chk->execute([$id, $maLoaiPhong]);
        if (!$chk->fetchColumn()) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE loai_phong_anh SET la_anh_dai_dien = 0 WHERE ma_loai_phong = ?")->execute([$maLoaiPhong]);
            $this->db->prepare("UPDATE loai_phong_anh SET la_anh_dai_dien = 1 WHERE id = ? AND ma_loai_phong = ?")->execute([$id, $maLoaiPhong]);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function reorderAnhLoaiPhong(string $maLoaiPhong, array $orderedIds): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("UPDATE loai_phong_anh SET thu_tu = ? WHERE id = ? AND ma_loai_phong = ?");
            $order = 1;
            foreach ($orderedIds as $id) {
                $intId = (int)$id;
                if ($intId > 0) {
                    $stmt->execute([$order++, $intId, $maLoaiPhong]);
                }
            }
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    public function toggleTrangThaiLoaiPhong(string $maLoaiPhong): array
    {
        $item = $this->findLoaiPhongById($maLoaiPhong);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy loại phòng '{$maLoaiPhong}'!");
        }

        $newStatus = ($item['TinhTrang'] === 'NgungKinhDoanh') ? 'ConPhong' : 'NgungKinhDoanh';
        $stmt = $this->db->prepare("UPDATE LoaiPhong SET TinhTrang = ? WHERE MaLoaiPhong = ?");
        $stmt->execute([$newStatus, $maLoaiPhong]);

        $item['TinhTrang'] = $newStatus;
        return $item;
    }

    /* =========================================================================
     * QUẢN LÝ PHÒNG CỤ THỂ (Admin CRUD)
     * ========================================================================= */

    public function findPhongById(string $maPhong): ?array
    {
        $stmt = $this->db->prepare("SELECT p.*, lp.TenLoaiPhong, lp.Gia FROM Phong p JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong WHERE p.MaPhong = ? LIMIT 1");
        $stmt->execute([$maPhong]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createPhong(array $data): array
    {
        $maPhong = trim($data['MaPhong'] ?? '');
        if (empty($maPhong)) {
            throw new \InvalidArgumentException("Mã phòng không được để trống!");
        }

        $check = $this->db->prepare("SELECT 1 FROM Phong WHERE MaPhong = ?");
        $check->execute([$maPhong]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã phòng '{$maPhong}' đã tồn tại!");
        }

        $trangThai = trim((string)($data['TrangThaiPhong'] ?? 'Trong'));
        $statusError = Phong::validateInitialStatus($trangThai);
        if ($statusError !== null) {
            throw new \InvalidArgumentException($statusError);
        }

        $sql = "INSERT INTO Phong (MaPhong, MaLoaiPhong, TrangThaiPhong) VALUES (?, ?, ?)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $maPhong,
            trim($data['MaLoaiPhong'] ?? 'LP001'),
            $trangThai
        ]);

        return $this->findPhongById($maPhong);
    }

    public function updatePhong(string $maPhong, array $data): bool
    {
        $current = $this->findPhongById($maPhong);
        if (!$current) {
            throw new \InvalidArgumentException("Không tìm thấy phòng '{$maPhong}'!");
        }

        $fields = [];
        $params = [];
        if (isset($data['MaLoaiPhong'])) {
            $fields[] = 'MaLoaiPhong = :maLoai';
            $params[':maLoai'] = trim($data['MaLoaiPhong']);
        }
        if (isset($data['TrangThaiPhong'])) {
            $newStatus = trim((string)$data['TrangThaiPhong']);
            $statusError = Phong::validateManualStatusTransition((string)$current['TrangThaiPhong'], $newStatus, $maPhong);
            if ($statusError !== null) {
                throw new \RuntimeException($statusError);
            }
            $fields[] = 'TrangThaiPhong = :trangThai';
            $params[':trangThai'] = $newStatus;
        }
        if (empty($fields)) return false;

        $params[':ma'] = $maPhong;
        $stmt = $this->db->prepare("UPDATE Phong SET " . implode(', ', $fields) . " WHERE MaPhong = :ma");
        return $stmt->execute($params);
    }

    public function toggleTrangThaiPhong(string $maPhong): array
    {
        $item = $this->findPhongById($maPhong);
        if (!$item) {
            throw new \InvalidArgumentException("Không tìm thấy phòng '{$maPhong}'!");
        }

        $currentStatus = (string)$item['TrangThaiPhong'];
        $newStatus = ($currentStatus === 'BaoTri') ? 'Trong' : 'BaoTri';
        $statusError = Phong::validateManualStatusTransition($currentStatus, $newStatus, $maPhong);
        if ($statusError !== null) {
            throw new \RuntimeException($statusError);
        }

        $stmt = $this->db->prepare("UPDATE Phong SET TrangThaiPhong = ? WHERE MaPhong = ?");
        $stmt->execute([$newStatus, $maPhong]);

        $item['TrangThaiPhong'] = $newStatus;
        return $item;
    }

}
