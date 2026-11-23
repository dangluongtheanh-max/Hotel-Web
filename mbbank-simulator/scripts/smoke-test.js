const app = require('../server');

process.env.HOTEL_WEBHOOK_URL = 'off';

const srv = app.listen(0, async () => {
  const base = `http://127.0.0.1:${srv.address().port}`;
  const j = async (u, o) => (await fetch(base + u, o)).json();
  const post = (u, b) => j(u, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(b) });
  const ok = (c, m) => { console.log((c ? 'PASS ' : 'FAIL ') + m); if (!c) process.exitCode = 1; };

  let r = await j('/api/payments/mbbank-check/HD123456?expectedAmount=45000');
  ok(r.data.paid === false, 'chưa chuyển -> paid=false');

  r = await post('/api/payments/mbbank-sync', { amount: 45000, content: 'SAI NOI DUNG' });
  ok(r.matched === false, 'sai nội dung -> không khớp đơn');

  r = await post('/api/payments/mbbank-sync', { amount: 10000, content: 'THANHTOAN HD123456' });
  r = await j('/api/payments/mbbank-check/HD123456?expectedAmount=45000');
  ok(r.data.paid === false && r.data.reason === 'UNDERPAID', 'chuyển thiếu -> UNDERPAID');

  r = await post('/api/payments/mbbank-sync', { amount: 45000, content: 'thanhtoan  hd123456', trans_id: 'FT1' });
  ok(r.matched === true, 'đúng nội dung THANHTOAN HD123456 -> matched=true');
  r = await j('/api/payments/mbbank-check/HD123456?expectedAmount=45000');
  ok(r.data.paid === true && r.data.payment.transId === 'FT1', 'đủ tiền -> paid=true');

  r = await post('/api/payments/mbbank-sync', { amount: 50000, content: 'THANHTOAN HD_TEST01', trans_id: 'FT2' });
  ok(r.matched === true && r.payment.orderCode === 'HD_TEST01', 'hỗ trợ mã hóa đơn có dấu gạch dưới HD_TEST01');

  r = await post('/api/payments/mbbank-invalidate/HD123456', {});
  r = await j('/api/payments/mbbank-check/HD123456');
  ok(r.data.paid === false, 'invalidate -> paid=false');

  r = await j('/api_payment.php?action=set_balance&balance=0');
  ok(r.balance === 0, 'admin set số dư = 0');
  r = await j('/api_payment.php?action=get_balance');
  ok(r.balance === 0, 'get_balance đọc lại đúng');

  const html = await (await fetch(base + '/mbbank/index.html')).text();
  ok(html.includes('MBBank') || html.includes('mbbank'), 'phục vụ được /mbbank/index.html');
  const png = await fetch(base + '/mbbank/assets/receipt_bg.png');
  ok(png.status === 200, 'phục vụ được assets');
  const qr = await fetch(base + '/api/qr.png?data=000201010212');
  ok(qr.status === 200 && qr.headers.get('content-type').includes('image/png'), 'sinh được /api/qr.png');

  srv.close();
});
