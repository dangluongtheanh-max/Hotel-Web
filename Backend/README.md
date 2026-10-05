# Backend - Hotel Booking

Cay thu muc va code duoc sinh tu so do lop (class diagram), theo kien truc module
hoa: moi module nghiep vu co du 4 lop `Routes -> Controllers -> Services -> Repositories -> Models`.

## Danh sach module

- `TaiKhoan` (BE1) — model: TaiKhoan
- `KhachHang` (BE1) — model: KhachHang
- `DatPhong` (BE1) — entities: PhieuNhanPhong, ChiTietPhieuNhanPhong, KhuyenMai
- `DanhGia` (BE1) — model: DanhGia
- `LeTan` (BE2) — model: LeTan
- `Phong` (BE3) — model: Phong, LoaiPhong
- `DichVu` — catalog and service-booking controller; other layers remain unimplemented
- `ThanhToan` (BE4) — model: ThanhToan
- `HoaDon` (BE4) — model: HoaDon, CTHD
- `Admin` (BE4) — model: Admin

## Nguyen tac quan trong

1. **Repository** la lop DUY NHAT duoc phep dung `Database::getConnection()` de truy van DB.
2. **Service** chua toan bo business logic; neu can du lieu cua module khac, phai
   goi qua **Service** cua module do (khong duoc goi thang Repository/Model cheo module)
   de giu tinh dong goi giua cac module.
3. **Controller** chi nhan request/goi Service/tra response, khong chua logic nghiep vu.
4. Thứ tự tạo bảng theo phụ thuộc khóa ngoại: tài khoản và vai trò, khách hàng/nhân viên,
   loại phòng/phòng/khuyến mãi, phiếu và chi tiết phiếu, dịch vụ/hóa đơn, rồi đánh giá/thanh toán.
5. Admin quản lý tài khoản nhân viên lễ tân; hệ thống hiện mô hình hóa một khách sạn,
   không tách chi nhánh hoặc vai trò quản lý riêng.

## Cai dat

```bash
composer install   # (chua co vendor/, can chay composer de sinh autoload)
cp .env.example .env  # neu co, hoac chinh sua truc tiep .env
php -S localhost:8000 -t public
```

## Ghi chu

- Cac method trong Service/Repository/Controller hien la **stub (TODO)** — day la
  khung xuong (scaffold) khop 1-1 voi cac lop/thuoc tinh/operation trong so do lop,
  can code phan logic that ben trong.
- Router trong `public/index.php` la ban toi gian minh hoa; nen thay bang mot
  thu vien routing that (Slim, FastRoute, Laravel...) khi trien khai that.
