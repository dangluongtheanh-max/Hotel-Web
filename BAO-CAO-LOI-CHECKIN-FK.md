# Báo Cáo Lỗi: Vi Phạm Ràng Buộc Khóa Ngoại `MaLeTanCheckin` (SQLSTATE[23000] / 1452)

**Ngày lập:** 2026-11-14  
**Dự án:** SKY HOTEL Booking Platform (`d:\HotelWeb`)  
**Mức độ nghiêm trọng:** Cao (Blocker — làm gián đoạn nghiệp vụ Check-in và có nguy cơ tương tự ở Check-out / Hủy phòng)

---

## 1. Thông báo lỗi ghi nhận

```text
SQLSTATE[23000]: Integrity constraint violation: 1452 Cannot add or update a child row:
a foreign key constraint fails (`hotel_booking`.`chitietphieunhanphong`,
CONSTRAINT `chitietphieunhanphong_ibfk_3` FOREIGN KEY (`MaLeTanCheckin`)
REFERENCES `letan` (`MaLeTan`) ON DELETE SET NULL ON UPDATE CASCADE)
```

---

## 2. Mô tả ngắn gọn

Khi người dùng đăng nhập bằng tài khoản **Admin** hoặc **Lễ tân** trên giao diện Lễ tân (`dashboard-le-tan.html`) và thực hiện thao tác **Check-in** phòng cho khách, hệ thống trả về lỗi `SQLSTATE[23000] / 1452` từ MySQL và không thể chuyển trạng thái phòng sang `DangLuuTru`.

---

## 3. Các bước tái hiện

1. Đăng nhập hệ thống bằng tài khoản **Admin** (ví dụ: `theanh`, có `MaTK = TK001`) hoặc **Lễ tân** (ví dụ: `phan`, có `MaTK = TK002`).
2. Truy cập màn hình **Nghiệp vụ Lễ tân & Quản lý Lưu trú** (`Frontend/pages/dashboard-le-tan.html`).
3. Chọn một phiếu/phòng đang ở trạng thái **Chờ Check-in** (`DaDatPhong`), nhấn nút **Check-in** (`🔑 CHECK-IN` hoặc `🔑 CHECK-IN PHÒNG`) và xác nhận.
4. Quan sát phản hồi từ API `/letan/checkin`: hệ thống báo lỗi vi phạm khóa ngoại `chitietphieunhanphong_ibfk_3`.

---

## 4. Phân tích nguyên nhân gốc rễ

Lỗi phát sinh do **sự không khớp giữa khóa tài khoản (`MaTK`) mà Frontend gửi lên và khóa chính nhân viên lễ tân (`MaLeTan`) trong cơ sở dữ liệu**:

### 4.1. Thiết kế khóa ngoại trong Database (`Database/hotel_booking.sql`, dòng 117–142)
Bảng `ChiTietPhieuNhanPhong` định nghĩa các cột nhân viên thao tác tham chiếu trực tiếp tới bảng `LeTan(MaLeTan)` (giá trị dạng `LT001`, `LT002`...):

```sql
MaLeTanCheckin VARCHAR(50) NULL DEFAULT NULL,
MaLeTanCheckout VARCHAR(50) NULL DEFAULT NULL,
MaLeTanHuy VARCHAR(50) NULL DEFAULT NULL,
...
FOREIGN KEY (MaLeTanCheckin) REFERENCES LeTan(MaLeTan)
    ON UPDATE CASCADE ON DELETE SET NULL,
FOREIGN KEY (MaLeTanCheckout) REFERENCES LeTan(MaLeTan)
    ON UPDATE CASCADE ON DELETE SET NULL,
FOREIGN KEY (MaLeTanHuy) REFERENCES LeTan(MaLeTan)
    ON UPDATE CASCADE ON DELETE SET NULL,
```

### 4.2. Frontend gửi `MaTK` thay cho `MaLeTan` (`Frontend/js/dashboard-le-tan.js`)
- Hàm `getLoggedReceptionistMaLT()` (dòng 363–385) đang lấy `u.MaTK` từ session để làm mã lễ tân:

```javascript
if (u && (u.VaiTro === 'LeTan' || u.VaiTro === 'Admin') && u.MaTK) {
  return u.MaTK;
}
```

- Khi gọi thao tác `checkin`, `leTanFetch()` (dòng 223, 258–260) chuyển `MaLT` thành tham số `base.maLeTan` (tức mang giá trị `TK001` hoặc `TK002`, không phải `LT001`).

### 4.3. Backend ghi trực tiếp giá trị `maLeTan` vào câu lệnh `UPDATE` (`Backend/app/LeTan/Repositories/LeTanPhieuRepository.php`, dòng 285–314)
Hàm `capNhatCheckin(string $maPhieu, string $maLeTan = 'LT001', ?string $maPhong = null)` thực thi:

```php
UPDATE ChiTietPhieuNhanPhong 
SET TrangThai = 'DangLuuTru', MaLeTanCheckin = :maLeTan
WHERE MaPhieu = :maPhieu AND MaPhong = :maPhong
```

Do `:maLeTan` nhận giá trị `TK001` / `TK002` (không tồn tại trong `letan.MaLeTan`), MySQL chặn lệnh `UPDATE` và ném lỗi `1452`.

---

## 5. Phạm vi ảnh hưởng mở rộng (các nghiệp vụ tương đồng)

Cùng cơ chế truyền `MaLT` từ `getLoggedReceptionistMaLT()` và ràng buộc khóa ngoại tới `LeTan(MaLeTan)`, các nghiệp vụ sau cũng chịu chung rủi ro:
1. **Check-out (`MaLeTanCheckout`):** `Backend/app/LeTan/Repositories/LeTanCheckoutRepository.php` (dòng 65–93) thực hiện `UPDATE ChiTietPhieuNhanPhong SET ..., MaLeTanCheckout = :maLeTan`.
2. **Hủy phòng / Hủy phiếu (`MaLeTanHuy`):** Cột `MaLeTanHuy` có cùng khóa ngoại `REFERENCES LeTan(MaLeTan)`.
3. **Tài khoản Admin (`TK001`):** Do tài khoản quản trị viên không có bản ghi tương ứng trong bảng `LeTan`, ngay cả khi tra cứu `MaTK -> MaLeTan` thì Admin vẫn không có `MaLeTan` riêng nếu không có cơ chế fallback hợp lệ (`NULL` hoặc mã lễ tân mặc định).

---

## 6. Đề xuất giải pháp khắc phục

### Phương án 1: Xử lý đồng bộ tại Backend + Frontend (Khuyến nghị)
1. **Backend (`auth/login`):** Khi đăng nhập thành công với vai trò `LeTan`, truy vấn kèm `MaLeTan` từ bảng `LeTan` (theo `MaTK`) và trả về trong thông tin phiên đăng nhập.
2. **Backend (`LeTanPhieuRepository::capNhatCheckin`, `LeTanCheckoutRepository`...):**
   - Trước khi `UPDATE`, kiểm tra xem `$maLeTan` truyền vào có tồn tại trong bảng `LeTan` hay không.
   - Nếu không tồn tại trực tiếp ở `LeTan.MaLeTan`, thử ánh xạ từ `TaiKhoan.MaTK` sang `LeTan.MaLeTan`.
   - Nếu người thao tác là `Admin` không có bản ghi trong bảng `LeTan`, gán giá trị `NULL` (vì cột đã khai báo `NULL DEFAULT NULL`) hoặc dùng mã lễ tân mặc định theo quy ước nghiệp vụ.
3. **Frontend (`Frontend/js/dashboard-le-tan.js`):**
   - Sửa `getLoggedReceptionistMaLT()` ưu tiên trả về `u.MaLeTan` thay vì `u.MaTK`.

### Phương án 2: Sửa tạm ở Frontend (nếu chưa sửa Backend)
- Không truyền `u.MaTK` làm `MaLT` khi `u.MaLeTan` không tồn tại; chỉ gửi `MaLeTan` hợp lệ hoặc bỏ qua tham số `maLeTan` để Backend dùng giá trị mặc định.

---

## 7. Danh sách tệp liên quan đã đối chiếu
- `Database/hotel_booking.sql` (dòng 117–142)
- `Frontend/js/dashboard-le-tan.js` (dòng 223, 258–260, 363–385)
- `Backend/app/LeTan/Repositories/LeTanPhieuRepository.php` (dòng 285–323)
- `Backend/app/LeTan/Repositories/LeTanCheckoutRepository.php` (dòng 65–93)

