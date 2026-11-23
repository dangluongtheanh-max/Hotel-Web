<?php

declare(strict_types=1);

namespace Shared;

use Database\Database;
use PDO;

/**
 * Xác thực quyền Admin qua Bearer token (MaTK) gửi trong header Authorization.
 */
class AdminAuth
{
    public static function getBearerToken(): string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $k => $v) {
                if (strtolower((string)$k) === 'authorization') {
                    $header = (string)$v;
                    break;
                }
            }
        }

        if (preg_match('/Bearer\s+(.+)$/i', trim($header), $m)) {
            return trim($m[1]);
        }

        return '';
    }

    public static function requireAdmin(): array
    {
        $token = self::getBearerToken();
        if ($token === '') {
            self::deny(401, 'Yêu cầu đăng nhập tài khoản quản trị (thiếu token xác thực).');
        }

        try {
            $pdo = Database::getConnection();
            $stmt = $pdo->prepare(
                "SELECT MaTK, TenDangNhap, VaiTro, TrangThaiTaiKhoan FROM TaiKhoan WHERE MaTK = ? LIMIT 1"
            );
            $stmt->execute([$token]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            self::deny(500, 'Không thể xác thực tài khoản quản trị.');
        }

        if (!$user) {
            self::deny(401, 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.');
        }

        if (($user['TrangThaiTaiKhoan'] ?? '') !== 'HoatDong') {
            self::deny(403, 'Tài khoản quản trị hiện đang bị khóa.');
        }

        if (($user['VaiTro'] ?? '') !== 'Admin') {
            self::deny(403, 'Bạn không có quyền quản trị (Admin) để thực hiện thao tác này.');
        }

        return $user;
    }

    private static function deny(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'status' => 'error',
            'message' => $message,
            'error' => $message,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

