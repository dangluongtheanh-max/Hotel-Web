<?php

require_once __DIR__ . '/../Backend/vendor/autoload.php';
(Dotenv\Dotenv::createImmutable(__DIR__ . '/../Backend'))->load();

use App\DatPhong\Services\NguoiLuuTruValidator;
use App\KhachHang\Services\KhachHangService;
use App\KhachHang\Repositories\KhachHangRepository;
use Database\Database;

echo "=== KIỂM THỬ LOGIC KIỂM TRA GIẤY TỜ & SĐT (VALIDATION) ===\n\n";

$validator = new NguoiLuuTruValidator();
$khRepo = new KhachHangRepository(new Database());
$khService = new KhachHangService($khRepo);

$passed = 0;
$failed = 0;

function assertError(callable $fn, string $expectedSubstr, string $testName) {
    global $passed, $failed;
    try {
        $fn();
        echo "[FAILED] {$testName}: Không ném ra ngoại lệ như mong đợi!\n";
        $failed++;
    } catch (\Throwable $e) {
        if (str_contains($e->getMessage(), $expectedSubstr)) {
            echo "[PASSED] {$testName}: Bắt lỗi chính xác -> \"{$e->getMessage()}\"\n";
            $passed++;
        } else {
            echo "[FAILED] {$testName}: Thông báo lỗi không khớp!\n  Nhận được: \"{$e->getMessage()}\"\n  Kỳ vọng chứa: \"{$expectedSubstr}\"\n";
            $failed++;
        }
    }
}

function assertSuccess(callable $fn, string $testName) {
    global $passed, $failed;
    try {
        $fn();
        echo "[PASSED] {$testName}: Dữ liệu hợp lệ được chấp nhận thành công.\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "[FAILED] {$testName}: Ngoại lệ không mong muốn -> {$e->getMessage()}\n";
        $failed++;
    }
}

// 1. Kiểm tra SĐT qua NguoiLuuTruValidator
echo "--- 1. Kiểm tra Số Điện Thoại ---\n";
assertError(fn() => $validator->kiemTraSoDienThoai('091234567'), 'thiếu 1 số', 'SĐT 9 chữ số (thiếu số)');
assertError(fn() => $validator->kiemTraSoDienThoai('09123456789'), 'thừa 1 số', 'SĐT 11 chữ số (thừa số)');
assertError(fn() => $validator->kiemTraSoDienThoai('091234567a'), 'chỉ được chứa các chữ số', 'SĐT chứa chữ cái');
assertError(fn() => $validator->kiemTraSoDienThoai('0212345678'), 'đầu số di động Việt Nam', 'SĐT đầu số 02');
assertSuccess(fn() => $validator->kiemTraSoDienThoai('0912345678'), 'SĐT 10 chữ số chuẩn (0912345678)');
assertSuccess(fn() => $validator->kiemTraSoDienThoai('0388999888'), 'SĐT 10 chữ số chuẩn (0388999888)');

// 2. Kiểm tra CCCD
echo "\n--- 2. Kiểm tra CCCD ---\n";
assertError(fn() => $validator->kiemTraGiayToTuyThan('CCCD', '00120200000'), 'thiếu 1 số', 'CCCD 11 chữ số (thiếu số)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('CCCD', '0012020000001'), 'thừa 1 số', 'CCCD 13 chữ số (thừa số)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('CCCD', '00120200000A'), 'chỉ được chứa các chữ số', 'CCCD chứa chữ cái');
assertSuccess(fn() => $validator->kiemTraGiayToTuyThan('CCCD', '001202000001'), 'CCCD 12 chữ số chuẩn');

// 3. Kiểm tra CMND
echo "\n--- 3. Kiểm tra CMND ---\n";
assertError(fn() => $validator->kiemTraGiayToTuyThan('CMND', '12345678'), 'thiếu 1 số', 'CMND 8 chữ số (thiếu số)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('CMND', '1234567890'), 'thừa 1 số', 'CMND 10 chữ số (thừa số)');
assertSuccess(fn() => $validator->kiemTraGiayToTuyThan('CMND', '123456789'), 'CMND 9 chữ số chuẩn');

// 4. Kiểm tra Hộ Chiếu
echo "\n--- 4. Kiểm tra Hộ Chiếu ---\n";
assertError(fn() => $validator->kiemTraGiayToTuyThan('HoChieu', 'B12345'), 'thiếu tối thiểu 1 ký tự', 'Hộ chiếu 6 ký tự (quá ngắn)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('HoChieu', 'B123456789'), 'thừa 1 ký tự', 'Hộ chiếu 10 ký tự (quá dài)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('HoChieu', 'B12 3456'), 'khoảng trắng hoặc ký tự đặc biệt', 'Hộ chiếu chứa khoảng trắng');
assertSuccess(fn() => $validator->kiemTraGiayToTuyThan('HoChieu', 'B1234567'), 'Hộ chiếu 8 ký tự chuẩn');
assertSuccess(fn() => $validator->kiemTraGiayToTuyThan('Passport', 'C12345678'), 'Passport 9 ký tự chuẩn');

// 5. Kiểm tra Giấy Khai Sinh
echo "\n--- 5. Kiểm tra Giấy Khai Sinh ---\n";
assertError(fn() => $validator->kiemTraGiayToTuyThan('GiayKhaiSinh', 'KS-12'), 'thiếu 1 ký tự', 'Giấy khai sinh 5 ký tự (quá ngắn)');
assertError(fn() => $validator->kiemTraGiayToTuyThan('GiayKhaiSinh', 'GIAYKHAISINH-12345'), 'thừa 3 ký tự', 'Giấy khai sinh 18 ký tự (quá dài)');
assertSuccess(fn() => $validator->kiemTraGiayToTuyThan('GiayKhaiSinh', 'KS-2022-001'), 'Giấy khai sinh 11 ký tự chuẩn');

// 6. Kiểm tra KhachHangService::validateSDT và validateSoGiayTo
echo "\n--- 6. Kiểm tra KhachHangService ---\n";
assertError(fn() => $khService->validateSDT('098765432'), 'thiếu 1 số', 'KhachHangService::validateSDT thiếu số');
assertError(fn() => $khService->validateSDT('098765432100'), 'thừa 2 số', 'KhachHangService::validateSDT thừa số');
assertError(fn() => $khService->validateSoGiayTo('CCCD', '07920200000'), 'thiếu 1 số', 'KhachHangService::validateSoGiayTo CCCD thiếu số');
assertSuccess(fn() => $khService->validateSDT('0987654321'), 'KhachHangService::validateSDT hợp lệ');
assertSuccess(fn() => $khService->validateSoGiayTo('CCCD', '079202000001'), 'KhachHangService::validateSoGiayTo CCCD hợp lệ');

echo "\n============================================\n";
echo "KẾT QUẢ KIỂM THỬ: {$passed} PASSED, {$failed} FAILED\n";
echo "============================================\n";

if ($failed > 0) {
    exit(1);
}
