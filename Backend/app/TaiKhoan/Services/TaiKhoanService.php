<?php

namespace App\TaiKhoan\Services;

use App\TaiKhoan\Repositories\TaiKhoanRepository;

/**
 * Service (business logic) cho module TaiKhoan.
 * Cac module khac muon dung du lieu cua module nay PHAI goi qua Service nay,
 * khong duoc goi thang Repository/Model de giu tinh dong goi giua cac module.
 */
class TaiKhoanService
{
    private const ROLES = ['Admin', 'LeTan', 'KhachHang'];
    private const TOKEN_TTL = 3600;

    private TaiKhoanRepository $repository;

    public function __construct(TaiKhoanRepository $repository)
    {
        $this->repository = $repository;
    }

    public function dangNhap(array $params = []): array
    {
        $username = trim((string)($params['TenDangNhap'] ?? ''));
        $password = (string)($params['MatKhau'] ?? '');
        if ($username === '' || $password === '') {
            return $this->failure('Vui lòng cung cấp TenDangNhap và MatKhau.', 400);
        }

        $account = $this->repository->findAccountForLogin($username);
        if ($account === null || !$this->passwordMatches($account['MatKhau'], $password)) {
            return $this->failure('Tên đăng nhập hoặc mật khẩu không chính xác.', 401);
        }
        if ($account['TrangThaiTaiKhoan'] !== 'HoatDong') {
            return $this->failure('Tài khoản hiện đang bị khóa.', 403);
        }

        $secret = $this->tokenSecret();
        if ($secret === null) {
            return $this->failure('Máy chủ chưa cấu hình AUTH_TOKEN_SECRET.', 503);
        }
        if ($this->isLegacyPassword($account['MatKhau'])) {
            if (!$this->repository->updatePasswordHash(
                $account['MaTK'],
                password_hash($password, PASSWORD_DEFAULT)
            )) {
                return $this->failure('Không thể nâng cấp mật khẩu tài khoản.', 500);
            }
        }

        $issuedAt = time();
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => $account['MaTK'],
            'iat' => $issuedAt,
            'exp' => $issuedAt + self::TOKEN_TTL,
            'aud' => 'hotel-web',
        ], JSON_THROW_ON_ERROR));
        $token = $payload . '.' . $this->base64UrlEncode(
            hash_hmac('sha256', $payload, $secret, true)
        );

        return [
            'success' => true,
            'message' => 'Đăng nhập thành công.',
            'token_type' => 'Bearer',
            'access_token' => $token,
            'expires_in' => self::TOKEN_TTL,
            'data' => [
                'MaTK' => $account['MaTK'],
                'TenDangNhap' => $account['TenDangNhap'],
                'Email' => $account['Email'],
                'VaiTro' => $account['VaiTro'],
            ],
        ];
    }

    public function quenMatKhau(array $params = []): array
    {
        return $this->failure('Chức năng đặt lại mật khẩu chưa được cấu hình.', 501);
    }

    public function authenticateToken(string $authorization): array
    {
        if (!preg_match('/^Bearer\s+([A-Za-z0-9_.-]+)$/i', trim($authorization), $matches)) {
            return $this->failure('Cần cung cấp access token dạng Bearer.', 401);
        }

        $secret = $this->tokenSecret();
        if ($secret === null) {
            return $this->failure('Máy chủ chưa cấu hình AUTH_TOKEN_SECRET.', 503);
        }

        $parts = explode('.', $matches[1]);
        if (count($parts) !== 2) {
            return $this->failure('Access token không hợp lệ.', 401);
        }

        [$encodedPayload, $encodedSignature] = $parts;
        $signature = $this->base64UrlDecode($encodedSignature);
        $payloadJson = $this->base64UrlDecode($encodedPayload);
        if ($signature === null || $payloadJson === null) {
            return $this->failure('Access token không hợp lệ.', 401);
        }

        $expectedSignature = hash_hmac('sha256', $encodedPayload, $secret, true);
        if (!hash_equals($expectedSignature, $signature)) {
            return $this->failure('Access token không hợp lệ.', 401);
        }

        $claims = json_decode($payloadJson, true);
        if (
            !is_array($claims)
            || !isset($claims['sub'], $claims['iat'], $claims['exp'], $claims['aud'])
            || $claims['aud'] !== 'hotel-web'
            || !is_string($claims['sub'])
            || $claims['sub'] === ''
            || !is_int($claims['iat'])
            || $claims['iat'] > time() + 60
            || !is_int($claims['exp'])
            || $claims['exp'] <= time()
        ) {
            return $this->failure('Access token đã hết hạn hoặc không hợp lệ.', 401);
        }

        $account = $this->repository->findAccountById($claims['sub']);
        if ($account === null || $account['TrangThaiTaiKhoan'] !== 'HoatDong') {
            return $this->failure('Tài khoản không tồn tại hoặc đã bị khóa.', 401);
        }

        return [
            'success' => true,
            'user' => [
                'MaTK' => $account['MaTK'],
                'TenDangNhap' => $account['TenDangNhap'],
                'Email' => $account['Email'],
                'VaiTro' => $account['VaiTro'],
            ],
        ];
    }

    public function xemDSTaiKhoan(array $params = []): array
    {
        $filters = [];
        $keyword = trim((string)($params['keyword'] ?? $params['TuKhoa'] ?? ''));
        if ($keyword !== '') {
            $filters['keyword'] = $keyword;
        }
        foreach (['VaiTro', 'TrangThaiTaiKhoan'] as $field) {
            if (isset($params[$field]) && $params[$field] !== '') {
                if ($field === 'VaiTro' && !in_array($params[$field], self::ROLES, true)) {
                    return $this->failure('Vai trò không hợp lệ.', 400);
                }
                if ($field === 'TrangThaiTaiKhoan' && !in_array($params[$field], ['HoatDong', 'BiKhoa'], true)) {
                    return $this->failure('Trạng thái tài khoản không hợp lệ.', 400);
                }
                $filters[$field] = $params[$field];
            }
        }

        $accounts = $this->repository->getAllTaiKhoan($filters);
        return ['success' => true, 'total' => count($accounts), 'data' => $accounts];
    }

    public function xemCTTaiKhoan(array $params = []): array
    {
        $id = trim((string)($params['MaTK'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã tài khoản (MaTK).', 400);
        }

        $account = $this->repository->findAccountById($id);
        if ($account === null) {
            return $this->failure("Không tìm thấy tài khoản '{$id}'.", 404);
        }

        return ['success' => true, 'data' => $account];
    }

    public function khoaTaiKhoan(array $params = []): array
    {
        return $this->setAccountStatus($params, 'BiKhoa', 'Khóa tài khoản thành công.');
    }

    public function moTaiKhoan(array $params = []): array
    {
        return $this->setAccountStatus($params, 'HoatDong', 'Mở khóa tài khoản thành công.');
    }

    public function capNhatVaiTro(array $params = []): array
    {
        $id = trim((string)($params['MaTK'] ?? ''));
        $role = trim((string)($params['VaiTro'] ?? ''));
        if ($id === '' || $role === '') {
            return $this->failure('Vui lòng cung cấp MaTK và VaiTro.', 400);
        }
        if (!in_array($role, self::ROLES, true)) {
            return $this->failure('Vai trò không hợp lệ.', 400);
        }
        if ($this->repository->findAccountById($id) === null) {
            return $this->failure("Không tìm thấy tài khoản '{$id}'.", 404);
        }

        try {
            if (!$this->repository->updateAccountRole($id, $role)) {
                return $this->failure('Không thể cập nhật vai trò tài khoản.', 500);
            }
        } catch (\DomainException $exception) {
            return $this->failure($exception->getMessage(), 409);
        }

        return [
            'success' => true,
            'message' => 'Cập nhật vai trò thành công.',
            'data' => $this->repository->findAccountById($id),
        ];
    }

    public function xemVaiTroVaQuyen(): array
    {
        return [
            'success' => true,
            'data' => [
                [
                    'VaiTro' => 'Admin',
                    'MoTa' => 'Toàn quyền quản trị tài khoản, phòng, khách hàng, nhân viên và báo cáo.',
                    'Quyen' => ['*'],
                ],
                [
                    'VaiTro' => 'LeTan',
                    'MoTa' => 'Quản lý nghiệp vụ lễ tân, đặt phòng, hóa đơn và các thao tác vận hành được cấp.',
                    'Quyen' => ['/letan/*', '/datphong/*', '/hoadon/*', '/thanhtoan/*', '/phong/kiemtra*'],
                ],
                [
                    'VaiTro' => 'KhachHang',
                    'MoTa' => 'Sử dụng các API khách hàng cần đăng nhập; xem phòng và đặt phòng vẫn công khai.',
                    'Quyen' => ['/khachhang/*'],
                ],
            ],
        ];
    }

    private function setAccountStatus(array $params, string $status, string $message): array
    {
        $id = trim((string)($params['MaTK'] ?? $params['id'] ?? ''));
        if ($id === '') {
            return $this->failure('Vui lòng cung cấp mã tài khoản (MaTK).', 400);
        }
        if ($this->repository->findAccountById($id) === null) {
            return $this->failure("Không tìm thấy tài khoản '{$id}'.", 404);
        }

        try {
            if (!$this->repository->updateAccountStatus($id, $status)) {
                return $this->failure('Không thể cập nhật trạng thái tài khoản.', 500);
            }
        } catch (\DomainException $exception) {
            return $this->failure($exception->getMessage(), 409);
        }

        return [
            'success' => true,
            'message' => $message,
            'data' => $this->repository->findAccountById($id),
        ];
    }

    private function passwordMatches(string $storedPassword, string $password): bool
    {
        return $this->isLegacyPassword($storedPassword)
            ? hash_equals($storedPassword, $password)
            : password_verify($password, $storedPassword);
    }

    private function isLegacyPassword(string $storedPassword): bool
    {
        return password_get_info($storedPassword)['algo'] === null;
    }

    private function tokenSecret(): ?string
    {
        $secret = $_ENV['AUTH_TOKEN_SECRET'] ?? getenv('AUTH_TOKEN_SECRET');
        if (!is_string($secret) || strlen($secret) < 32) {
            return null;
        }

        return $secret;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);

        return $decoded === false ? null : $decoded;
    }

    private function failure(string $message, int $statusCode): array
    {
        return ['success' => false, 'error' => $message, 'statusCode' => $statusCode];
    }

}
