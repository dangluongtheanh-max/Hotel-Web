# SKY HOTEL — Hướng dẫn cài đặt & chạy dự án

Dự án hệ thống đặt phòng & quản lý khách sạn **SKY HOTEL**, kết hợp giao diện **Frontend** (`Frontend/`) với **Backend PHP + MySQL** (`Backend/`).

---

## 1. Yêu cầu môi trường

- **PHP `>= 8.1`** (bật extension `pdo_mysql`, `mbstring`, `openssl` — có sẵn khi cài XAMPP).
- **MySQL / MariaDB** (mặc định cổng `3306`).
- **Composer** (chỉ cần nếu chưa có sẵn thư mục `Backend/vendor/`).

---

## 2. Cấu trúc thư mục chính

```text
HotelWeb/
├── Backend/                 # API PHP (PSR-4), cấu hình .env, vendor/
│   ├── config/database.php  # Đọc DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD
│   ├── public/index.php     # Entry point API (/Backend/public/index.php)
│   ├── .env.example         # Mẫu biến môi trường (DB + Gmail SMTP)
│   └── composer.json        # Dependencies: vlucas/phpdotenv, phpmailer/phpmailer
├── Database/                # Script khởi tạo & dữ liệu mẫu MySQL
│   ├── hotel_booking.sql
│   ├── data.sql
│   ├── add_tai_khoan_otp.sql
│   └── migrate_booking_status.php
├── Frontend/                # Giao diện chính SKY HOTEL (SPA iframe shell)
│   ├── index.html           # Điểm vào chính của giao diện
│   ├── css/, js/, pages/    # Tài nguyên & các trang Khách hàng / Lễ tân / Quản lý
└── Test/                    # Các script PHP kiểm tra DB & API
```

---

## 3. Các bước cài đặt & chạy dự án

### Bước 1: Khởi tạo cơ sở dữ liệu MySQL

1. Mở **XAMPP Control Panel** và bật **MySQL** (hoặc khởi động dịch vụ MySQL/MariaDB trên máy).
2. Mở **phpMyAdmin** (`http://localhost/phpmyadmin`) hoặc MySQL CLI, tạo database `hotel_booking` (UTF-8 `utf8mb4_unicode_ci`) và import lần lượt 3 file sau:
   1. `Database/hotel_booking.sql`
   2. `Database/data.sql`
   3. `Database/add_tai_khoan_otp.sql`
3. *(Tùy chọn)* Nếu nâng cấp từ dữ liệu cũ, chạy thêm script đồng bộ trạng thái đặt phòng:
   ```powershell
   php Database/migrate_booking_status.php
   ```

### Bước 2: Cấu hình biến môi trường (`Backend/.env`)

1. Nếu chưa có `Backend/.env`, sao chép từ file mẫu `Backend/.env.example`:
   ```powershell
   Copy-Item Backend\.env.example Backend\.env
   ```
2. Mở `Backend/.env` và cập nhật thông tin kết nối MySQL (nếu dùng XAMPP mặc định, `DB_USERNAME=root`, `DB_PASSWORD=` để trống):
   ```env
   APP_ENV=local
   APP_DEBUG=true

   DB_HOST=127.0.0.1
   DB_DATABASE=hotel_booking
   DB_USERNAME=root
   DB_PASSWORD=
   ```
3. Nếu cần dùng tính năng **Quên mật khẩu (gửi mã OTP qua Gmail)**, điền thêm thông tin `MAIL_USERNAME` và `MAIL_PASSWORD` (App Password 16 ký tự của Gmail).

### Bước 3: Cài đặt thư viện PHP *(bỏ qua nếu đã có `Backend/vendor/`)*

```powershell
cd Backend
composer install
cd ..
```

### Bước 4: Khởi chạy dự án

#### Cách 1: Chạy bằng PHP Built-in Server *(khuyên dùng khi phát triển)*
Mở terminal tại **thư mục gốc dự án** (`d:\HotelWeb`) và chạy:

```powershell
php -S localhost:8000
```

*(Nếu `php` chưa có trong PATH của Windows, gọi trực tiếp PHP của XAMPP:)*

```powershell
d:\xampp\php\php.exe -S localhost:8000
```

Sau đó mở trình duyệt và truy cập:
- **Trang chủ / Giao diện chính:** `http://localhost:8000/Frontend/`

#### Cách 2: Chạy qua Apache của XAMPP
1. Đặt toàn bộ thư mục dự án vào `C:\xampp\htdocs\HotelWeb` (hoặc `d:\xampp\htdocs\HotelWeb`).
2. Bật **Apache** và **MySQL** trong XAMPP Control Panel.
3. Truy cập trình duyệt tại: `http://localhost/HotelWeb/Frontend/`.

---

## 4. Kiểm tra nhanh & Lưu ý

- **Kiểm tra kết nối CSDL:**
  ```powershell
  php Backend/test_db.php
  ```
- **Đăng nhập:** Hệ thống xác thực bằng **Tên đăng nhập** (không dùng email để đăng nhập).
- **Số điện thoại hợp lệ:** Đúng 10 chữ số, bắt đầu bằng đầu số `03`, `05`, `07`, `08`, `09`.

---

## 5. Lưu ý bảo mật khi triển khai

- **Tắt chế độ debug khi chạy thật:** Đổi `APP_DEBUG=false` (và `APP_ENV=production`) trong `Backend/.env` để tránh lộ thông tin lỗi/stack trace ra ngoài.
- **Bảo vệ `Backend/.env` và khóa bí mật:** Không chia sẻ hoặc commit `Backend/.env` lên Git; nếu lỡ lộ **App Password Gmail** (`MAIL_PASSWORD`), hãy thu hồi ngay trong tài khoản Google và tạo App Password mới.
- **Cẩn trọng khi chạy `php -S localhost:8000` từ thư mục gốc:** Ở chế độ này, mọi tệp dưới thư mục gốc đều có thể bị truy cập trực tiếp qua web, bao gồm cả `Backend/.env` và các tệp kiểm thử (`Test/`, `Backend/test_*.php`). Cách chạy này chỉ phù hợp cho **môi trường demo/nội bộ**; khi triển khai thực tế cần cấu hình document root riêng (chỉ trỏ vào `Frontend/` hoặc `Backend/public/` và chặn truy cập các tệp cấu hình nhạy cảm).
- **Hạn chế đã biết (sẽ củng cố ở bước sau):** Cơ chế token đăng nhập, phân quyền và xác thực webhook thanh toán hiện ở mức phục vụ demo và sẽ được tăng cường bảo mật ở giai đoạn tiếp theo.
