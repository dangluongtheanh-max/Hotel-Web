<?php

namespace App\HoaDon\Repositories;

use App\HoaDon\Models\HoaDon;
use App\HoaDon\Models\CTHD;
use Database\Database;
use PDO;

/**
 * Repository cho module HoaDon.
 * Chi lop nay duoc phep thao tac truc tiep voi database (qua Database::getConnection()).
 */
class HoaDonRepository
{
    private PDO $db;

    public function __construct(?Database $db = null)
    {
        $this->db = Database::getConnection();
    }

    public function findHoaDonById(string $id): ?HoaDon
    {
        $stmt = $this->db->prepare("SELECT * FROM HoaDon WHERE MaHD = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new HoaDon($row['MaHD'], $row['MaPhieu'], (float) $row['TongTienSauVAT']);
    }

    public function findByMaPhieu(string $maPhieu): ?HoaDon
    {
        $stmt = $this->db->prepare("SELECT * FROM HoaDon WHERE MaPhieu = ?");
        $stmt->execute([$maPhieu]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return new HoaDon($row['MaHD'], $row['MaPhieu'], (float) $row['TongTienSauVAT']);
    }

    public function getAllHoaDon(array $filters = []): array
    {
        $sql = "SELECT * FROM HoaDon WHERE 1=1";
        $params = [];

        if (!empty($filters['MaHD'])) {
            $sql .= " AND MaHD LIKE ?";
            $params[] = '%' . $filters['MaHD'] . '%';
        }
        if (!empty($filters['MaPhieu'])) {
            $sql .= " AND MaPhieu LIKE ?";
            $params[] = '%' . $filters['MaPhieu'] . '%';
        }
        if (isset($filters['GiaTu'])) {
            $sql .= " AND TongTienSauVAT >= ?";
            $params[] = (float) $filters['GiaTu'];
        }
        if (isset($filters['GiaDen'])) {
            $sql .= " AND TongTienSauVAT <= ?";
            $params[] = (float) $filters['GiaDen'];
        }

        $sql .= " ORDER BY MaHD DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(function ($row) {
            return new HoaDon($row['MaHD'], $row['MaPhieu'], (float) $row['TongTienSauVAT']);
        }, $rows);
    }

    public function saveHoaDon(HoaDon $hoaDon): bool
    {
        $existing = $this->findHoaDonById($hoaDon->getMaHD());

        if ($existing) {
            $stmt = $this->db->prepare("UPDATE HoaDon SET MaPhieu = ?, TongTienSauVAT = ? WHERE MaHD = ?");
            return $stmt->execute([
                $hoaDon->getMaPhieu(),
                $hoaDon->getTongTienSauVAT(),
                $hoaDon->getMaHD()
            ]);
        }

        $stmt = $this->db->prepare("INSERT INTO HoaDon (MaHD, MaPhieu, TongTienSauVAT) VALUES (?, ?, ?)");
        return $stmt->execute([
            $hoaDon->getMaHD(),
            $hoaDon->getMaPhieu(),
            $hoaDon->getTongTienSauVAT()
        ]);
    }

    public function deleteHoaDon(string $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM HoaDon WHERE MaHD = ?");
        return $stmt->execute([$id]);
    }

    public function findCTHDById(string|int $id): ?CTHD
    {
        $stmt = $this->db->prepare("SELECT * FROM CTHD WHERE MaCTHD = ?");
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return $this->mapCTHDToModel($row);
    }

    public function getCTHDByMaHD(string $maHD): array
    {
        $stmt = $this->db->prepare("SELECT * FROM CTHD WHERE MaHD = ?");
        $stmt->execute([$maHD]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapCTHDToModel'], $rows);
    }

    public function getAllCTHD(array $filters = []): array
    {
        $sql = "SELECT * FROM CTHD WHERE 1=1";
        $params = [];

        if (!empty($filters['MaHD'])) {
            $sql .= " AND MaHD = ?";
            $params[] = $filters['MaHD'];
        }
        if (!empty($filters['MaPhieu'])) {
            $sql .= " AND MaPhieu = ?";
            $params[] = $filters['MaPhieu'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map([$this, 'mapCTHDToModel'], $rows);
    }

    public function saveCTHD(CTHD $cthd): bool
    {
        if ($cthd->getMaCTHD() !== null) {
            $stmt = $this->db->prepare(
                "UPDATE CTHD SET MaHD = ?, MaPhieu = ?, MaPhong = ?, MaCTDV = ?, SoDem = ?, SoLuong = ?, DonGia = ? WHERE MaCTHD = ?"
            );
            return $stmt->execute([
                $cthd->getMaHD(),
                $cthd->getMaPhieu(),
                $cthd->getMaPhong(),
                $cthd->getMaCTDV(),
                $cthd->getSoDem(),
                $cthd->getSoLuong(),
                $cthd->getDonGia(),
                $cthd->getMaCTHD()
            ]);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO CTHD (MaHD, MaPhieu, MaPhong, MaCTDV, SoDem, SoLuong, DonGia) VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $res = $stmt->execute([
            $cthd->getMaHD(),
            $cthd->getMaPhieu(),
            $cthd->getMaPhong(),
            $cthd->getMaCTDV(),
            $cthd->getSoDem(),
            $cthd->getSoLuong(),
            $cthd->getDonGia()
        ]);

        if ($res) {
            $cthd->setMaCTHD((int) $this->db->lastInsertId());
            return true;
        }

        return false;
    }

    public function deleteCTHD(string|int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM CTHD WHERE MaCTHD = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Lay chi tiet phong va dich vu cua phieu dat phong de tinh tien tu dong
     */
    public function layChiPhiTuPhieu(string $maPhieu): array
    {
        // 1. Tien phong
        $sqlPhong = "
            SELECT ct.MaPhieu, ct.MaPhong, lp.Gia as DonGia,
                   GREATEST(1, DATEDIFF(ct.ThoiGianTraPhong, ct.ThoiGianNhanPhong)) as SoDem
            FROM ChiTietPhieuNhanPhong ct
            JOIN Phong p ON ct.MaPhong = p.MaPhong
            JOIN LoaiPhong lp ON p.MaLoaiPhong = lp.MaLoaiPhong
            WHERE ct.MaPhieu = ?
        ";
        $stmtPhong = $this->db->prepare($sqlPhong);
        $stmtPhong->execute([$maPhieu]);
        $dsPhong = $stmtPhong->fetchAll(PDO::FETCH_ASSOC);

        // 2. Tien dich vu
        $sqlDV = "
            SELECT cs.MaPhieu, cs.MaPhong, cs.MaCTDV, cs.DonGia, cs.SoLuong
            FROM CTSuDungDichVu cs
            WHERE cs.MaPhieu = ?
        ";
        $stmtDV = $this->db->prepare($sqlDV);
        $stmtDV->execute([$maPhieu]);
        $dsDV = $stmtDV->fetchAll(PDO::FETCH_ASSOC);

        // 3. Khuyen mai (neu co)
        $sqlKM = "
            SELECT km.LoaiKM, km.GiaTriGiam
            FROM PhieuNhanPhong pnp
            JOIN KhuyenMai km ON pnp.MaKM = km.MaKM
            WHERE pnp.MaPhieu = ? AND km.TrangThai = 'HoatDong'
        ";
        $stmtKM = $this->db->prepare($sqlKM);
        $stmtKM->execute([$maPhieu]);
        $km = $stmtKM->fetch(PDO::FETCH_ASSOC);

        return [
            'phong' => $dsPhong,
            'dichVu' => $dsDV,
            'khuyenMai' => $km ?: null,
        ];
    }

    private function mapCTHDToModel(array $row): CTHD
    {
        return new CTHD(
            isset($row['MaCTHD']) ? (int) $row['MaCTHD'] : null,
            $row['MaHD'],
            $row['MaPhieu'],
            $row['MaPhong'] ?? null,
            $row['MaCTDV'] ?? null,
            (int) ($row['SoDem'] ?? 0),
            (int) ($row['SoLuong'] ?? 1),
            (float) ($row['DonGia'] ?? 0)
        );
    }
}
