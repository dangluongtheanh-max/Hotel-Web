<?php

declare(strict_types=1);

namespace App\Admin\Repositories;

use Database\Database;
use PDO;

/**
 * Repository phụ trách truy vấn & thao tác CSDL cho Nhân Sự & Lễ Tân.
 */
class AdminNhanVienRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getConnection();
    }

    public function getAllNhanVien(array $filters = []): array
    {
        $sql = "SELECT lt.MaLeTan AS MaNV, lt.HoTen, lt.SDT, tk.Email, 'LeTan' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM LeTan lt
                JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
                UNION ALL
                SELECT adm.MaAdmin AS MaNV, adm.HoTen, adm.SDT, tk.Email, 'Admin' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM Admin adm
                JOIN TaiKhoan tk ON adm.MaTK = tk.MaTK";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findNhanVienById(string $id): ?array
    {
        $sql = "SELECT lt.MaLeTan AS MaNV, lt.HoTen, lt.SDT, tk.Email, 'LeTan' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.MaTK
                FROM LeTan lt
                JOIN TaiKhoan tk ON lt.MaTK = tk.MaTK
                WHERE lt.MaLeTan = :id
                UNION ALL
                SELECT adm.MaAdmin AS MaNV, adm.HoTen, adm.SDT, tk.Email, 'Admin' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan, tk.MaTK
                FROM Admin adm
                JOIN TaiKhoan tk ON adm.MaTK = tk.MaTK
                WHERE adm.MaAdmin = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateTrangThaiTaiKhoan(string $maTK, string $trangThai): bool
    {
        $stmt = $this->db->prepare("UPDATE TaiKhoan SET TrangThaiTaiKhoan = :trangThai WHERE MaTK = :maTK");
        return $stmt->execute([':trangThai' => $trangThai, ':maTK' => $maTK]);
    }

    public function getAllLeTan(array $filters = []): array
    {
        $sql = "SELECT lt.MaLeTan, lt.HoTen, lt.SDT, tk.Email, lt.MaTK, 'LeTan' AS VaiTro,
                       tk.TenDangNhap, tk.TrangThaiTaiKhoan
                FROM LeTan lt
                JOIN TaiKhoan tk ON tk.MaTK = lt.MaTK";
        $conditions = [];
        $parameters = [];

        if (!empty($filters['keyword'])) {
            $conditions[] = '(lt.MaLeTan LIKE :idKeyword
                OR lt.HoTen LIKE :nameKeyword
                OR lt.SDT LIKE :phoneKeyword
                OR tk.Email LIKE :emailKeyword
                OR tk.TenDangNhap LIKE :usernameKeyword)';
            $keyword = '%' . $filters['keyword'] . '%';
            $parameters = [
                ':idKeyword' => $keyword,
                ':nameKeyword' => $keyword,
                ':phoneKeyword' => $keyword,
                ':emailKeyword' => $keyword,
                ':usernameKeyword' => $keyword,
            ];
        }

        if ($conditions !== []) {
            $sql .= ' WHERE ' . implode(' AND ', $conditions);
        }
        $sql .= ' ORDER BY lt.MaLeTan';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findLeTanById(string $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT lt.MaLeTan, lt.HoTen, lt.SDT, tk.Email, lt.MaTK, 'LeTan' AS VaiTro,
                    tk.TenDangNhap, tk.TrangThaiTaiKhoan
             FROM LeTan lt
             JOIN TaiKhoan tk ON tk.MaTK = lt.MaTK
             WHERE lt.MaLeTan = :id"
        );
        $stmt->execute([':id' => $id]);
        $leTan = $stmt->fetch(PDO::FETCH_ASSOC);

        return $leTan ?: null;
    }

    public function leTanAccountValueExists(string $column, string $value): bool
    {
        if (!in_array($column, ['TenDangNhap', 'Email'], true)) {
            throw new \InvalidArgumentException('Unsupported account column.');
        }

        $stmt = $this->db->prepare("SELECT 1 FROM TaiKhoan WHERE {$column} = :value LIMIT 1");
        $stmt->execute([':value' => $value]);

        return $stmt->fetchColumn() !== false;
    }

    public function generateMaLeTan(): string
    {
        $stmt = $this->db->query("SELECT MaLeTan FROM LeTan WHERE MaLeTan LIKE 'LT%' ORDER BY MaLeTan DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, 2);
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        return sprintf('LT%03d', $maxNum + 1);
    }

    public function generateMaTaiKhoan(): string
    {
        $stmt = $this->db->query("SELECT MaTK FROM TaiKhoan WHERE MaTK LIKE 'TK%' ORDER BY MaTK DESC");
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $maxNum = 0;
        foreach ($rows as $code) {
            $numPart = substr($code, 2);
            if (is_numeric($numPart)) {
                $num = (int)$numPart;
                if ($num > $maxNum) $maxNum = $num;
            }
        }
        return sprintf('TK%03d', $maxNum + 1);
    }

    public function createLeTan(array $data): array
    {
        $maTK = $data['MaTK'] ?? $this->generateMaTaiKhoan();
        $maLeTan = !empty($data['MaLeTan']) ? trim($data['MaLeTan']) : $this->generateMaLeTan();

        $check = $this->db->prepare("SELECT 1 FROM LeTan WHERE MaLeTan = ?");
        $check->execute([$maLeTan]);
        if ($check->fetchColumn()) {
            throw new \InvalidArgumentException("Mã lễ tân '{$maLeTan}' đã tồn tại!");
        }

        $rawPassword = $data['MatKhau'] ?? '123456';
        $hashedPassword = password_hash($rawPassword, PASSWORD_BCRYPT);

        $this->db->beginTransaction();
        try {
            $account = $this->db->prepare(
                "INSERT INTO TaiKhoan
                    (MaTK, TenDangNhap, MatKhau, Email, TrangThaiTaiKhoan, VaiTro)
                 VALUES
                    (:maTK, :tenDangNhap, :matKhau, :email, 'HoatDong', 'LeTan')"
            );
            $account->execute([
                ':maTK' => $maTK,
                ':tenDangNhap' => $data['TenDangNhap'],
                ':matKhau' => $hashedPassword,
                ':email' => $data['Email'],
            ]);

            $leTan = $this->db->prepare(
                'INSERT INTO LeTan (MaLeTan, HoTen, SDT, MaTK)
                 VALUES (:maLeTan, :hoTen, :sdt, :maTK)'
            );
            $leTan->execute([
                ':maLeTan' => $maLeTan,
                ':hoTen' => $data['HoTen'],
                ':sdt' => $data['SDT'],
                ':maTK' => $maTK,
            ]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }

        return $this->findLeTanById($maLeTan)
            ?? throw new \RuntimeException('Created receptionist could not be loaded.');
    }

    public function updateLeTan(string $id, array $data): bool
    {
        $current = $this->findLeTanById($id);
        if ($current === null) {
            return false;
        }

        $email = $data['Email'] ?? $current['Email'];
        $this->db->beginTransaction();
        try {
            $leTan = $this->db->prepare(
                'UPDATE LeTan
                 SET HoTen = :hoTen, SDT = :sdt
                 WHERE MaLeTan = :maLeTan'
            );
            $leTan->execute([
                ':hoTen' => $data['HoTen'] ?? $current['HoTen'],
                ':sdt' => $data['SDT'] ?? $current['SDT'],
                ':maLeTan' => $id,
            ]);

            $accountFields = [];
            $accountParams = [':maTK' => $current['MaTK']];

            if (array_key_exists('Email', $data)) {
                $accountFields[] = 'Email = :email';
                $accountParams[':email'] = $email;
            }
            if (!empty($data['TenDangNhap'])) {
                $accountFields[] = 'TenDangNhap = :tenDangNhap';
                $accountParams[':tenDangNhap'] = trim($data['TenDangNhap']);
            }
            if (!empty($data['MatKhau'])) {
                $accountFields[] = 'MatKhau = :matKhau';
                $accountParams[':matKhau'] = password_hash($data['MatKhau'], PASSWORD_BCRYPT);
            }
            if (!empty($data['TrangThaiTaiKhoan'])) {
                $accountFields[] = 'TrangThaiTaiKhoan = :trangThai';
                $accountParams[':trangThai'] = trim($data['TrangThaiTaiKhoan']);
            }

            if (!empty($accountFields)) {
                $sqlAccount = 'UPDATE TaiKhoan SET ' . implode(', ', $accountFields) . ' WHERE MaTK = :maTK';
                $accountStmt = $this->db->prepare($sqlAccount);
                $accountStmt->execute($accountParams);
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

    public function toggleTrangThaiLeTan(string $maLeTan): array
    {
        $current = $this->findLeTanById($maLeTan);
        if (!$current) {
            throw new \InvalidArgumentException("Không tìm thấy lễ tân '{$maLeTan}'!");
        }

        $currentStatus = $current['TrangThaiTaiKhoan'] ?? 'HoatDong';
        $newStatus = ($currentStatus === 'HoatDong') ? 'BiKhoa' : 'HoatDong';

        $stmt = $this->db->prepare("UPDATE TaiKhoan SET TrangThaiTaiKhoan = :status WHERE MaTK = :maTK");
        $stmt->execute([':status' => $newStatus, ':maTK' => $current['MaTK']]);

        $current['TrangThaiTaiKhoan'] = $newStatus;
        return $current;
    }

}
