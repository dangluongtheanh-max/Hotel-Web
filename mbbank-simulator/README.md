# MBBank Simulator (Tích hợp SKY HOTEL)

App MB Bank **giả lập** chạy riêng bằng Node.js, hỗ trợ:
- Quét QR bằng camera điện thoại (qua **ngrok HTTPS** hoặc mạng **LAN**).
- Tự động đẩy webhook sang Backend PHP của SKY HOTEL (`/thanhtoan/webhook`) ngay khi chuyển khoản thành công để giao diện Frontend tự nhảy sang **Thanh toán thành công**.

---

## 1. Khởi chạy

Mở terminal tại thư mục `mbbank-simulator/`:

```powershell
npm install   # chỉ cần chạy lần đầu
npm start     # chạy ở http://localhost:4000
```

- App giả lập: `http://localhost:4000/mbbank`
- Admin chỉnh số dư: `http://localhost:4000/mbbank/admin`
- Kiểm tra nhanh API: `npm test`

---

## 2. Cách test với điện thoại (ngrok / LAN)

### Quét bằng camera điện thoại (Khuyên dùng `ngrok`)
Trình duyệt di động yêu cầu **HTTPS** để bật camera quét QR:
1. Chạy Backend + Frontend SKY HOTEL (`d:\xampp\php\php.exe -S localhost:8000` hoặc Apache XAMPP).
2. Chạy simulator: `cd mbbank-simulator && npm start` (cổng `4000`).
3. Mở terminal khác chạy ngrok cho cổng `4000`:
   ```powershell
   ngrok http 4000
   ```
4. Mở link `https://<id>.ngrok-free.app/mbbank` trên điện thoại → bấm nút **Quét QR** ở giữa thanh điều hướng → quét mã QR trên màn hình đặt phòng SKY HOTEL → xác nhận chuyển tiền.
5. `server.js` sẽ nhận `/api/payments/mbbank-sync` qua ngrok và tự forward webhook về PHP Backend (`http://127.0.0.1:8000/Backend/public/index.php/thanhtoan/webhook` hoặc XAMPP). Trang web trên máy tính sẽ tự động chuyển sang **Thanh toán thành công** sau tối đa ~2 giây.

### Nếu chỉ dùng cùng Wi-Fi (không qua ngrok)
- Trên điện thoại mở `http://<IP_LAN_MAY_TINH>:4000/mbbank` (xem IP in sẵn khi chạy `npm start`) và dùng chức năng **Chọn ảnh QR** / **Chuyển khoản** nếu trình duyệt chặn camera HTTP.

---

## 3. Biến môi trường tuỳ chọn

- `PORT=4000`: Cổng chạy Node simulator.
- `MEMO_PREFIX=THANHTOAN`: Tiền tố nội dung chuyển khoản mặc định.
- `HOTEL_WEBHOOK_URL=...`: URL webhook PHP cụ thể nếu bạn dùng cổng/đường dẫn khác mặc định (đặt `off` để tắt forward).
