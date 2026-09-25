# Backend - Hotel Booking

Cay thu muc va code duoc sinh tu so do lop (class diagram), theo kien truc module
hoa: moi module nghiep vu co du 4 lop `Routes -> Controllers -> Services -> Repositories -> Models`.

## Danh sach module

- `TaiKhoan` (BE1) — model: TaiKhoan
- `KhachHang` (BE1) — model: KhachHang
- `DatPhong` (BE1) — model: PhieuNhanPhong, KhuyenMai
- `DanhGia` (BE1) — model: DanhGia
- `LeTan` (BE2) — model: LeTan
- `Phong` (BE3) — model: Phong, LoaiPhong
- `ChiNhanh` (BE3) — model: ChiNhanh
- `QuanLy` (BE3) — model: QuanLy
- `ThanhToan` (BE4) — model: ThanhToan
- `HoaDon` (BE4) — model: HoaDon, CTHD
- `Admin` (BE4) — model: Admin

## Nguyen tac quan trong

1. **Repository** la lop DUY NHAT duoc phep dung `Database::getConnection()` de truy van DB.
2. **Service** chua toan bo business logic; neu can du lieu cua module khac, phai
   goi qua **Service** cua module do (khong duoc goi thang Repository/Model cheo module)
   de giu tinh dong goi giua cac module.
3. **Controller** chi nhan request/goi Service/tra response, khong chua logic nghiep vu.
4. `database/migrations` duoc danh so thu tu theo phu thuoc khoa ngoai
   (TaiKhoan -> ChiNhanh/Admin -> KhachHang/LeTan/QuanLy -> Phong -> PhieuNhanPhong -> ...).

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
