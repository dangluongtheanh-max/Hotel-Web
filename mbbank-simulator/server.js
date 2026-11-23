/**
 * MBBank Simulator server (độc lập, hỗ trợ quét QR từ điện thoại qua LAN/ngrok).
 * - Phục vụ app giả lập tại  /mbbank  và  /mbbank/admin
 * - Webhook nhận "biến động số dư" khi app giả lập chuyển khoản thành công
 * - Tự động forward webhook sang Backend PHP của SKY HOTEL (/thanhtoan/webhook)
 * - Endpoint /api/qr.png sinh ảnh QR VietQR offline
 *
 * ENV:
 *   PORT=4000
 *   MEMO_PREFIX=THANHTOAN
 *   HOTEL_WEBHOOK_URL=          (tuỳ chọn) URL webhook PHP; bỏ trống sẽ tự dò cổng 8000 / XAMPP
 *   MBBANK_SYNC_SECRET=         (tuỳ chọn) nếu đặt, webhook bắt buộc header x-mbbank-secret
 *   PAYMENT_TTL_MS=600000       Giao dịch hết hiệu lực sau 10 phút
 *   CORS_ORIGIN=*               Origin được phép gọi API
 */
const os = require('os');
const path = require('path');
const express = require('express');
const QRCode = require('qrcode');

const PORT = Number(process.env.PORT || 4000);
const MEMO_PREFIX = (process.env.MEMO_PREFIX || 'THANHTOAN').toUpperCase();
const SECRET = process.env.MBBANK_SYNC_SECRET || '';
const TTL = Number(process.env.PAYMENT_TTL_MS || 10 * 60 * 1000);
const CORS_ORIGIN = process.env.CORS_ORIGIN || '*';

const DEFAULT_HOTEL_WEBHOOK_URLS = [
  'http://127.0.0.1:8000/Backend/public/index.php/thanhtoan/webhook',
  'http://127.0.0.1/HotelWeb-final/Backend/public/index.php/thanhtoan/webhook',
  'http://127.0.0.1/Backend/public/index.php/thanhtoan/webhook',
];

function getHotelWebhookUrls() {
  const raw = (process.env.HOTEL_WEBHOOK_URL || '').trim();
  if (!raw) return DEFAULT_HOTEL_WEBHOOK_URLS;
  if (raw.toLowerCase() === 'off' || raw.toLowerCase() === 'none') return [];
  return raw.split(',').map((u) => u.trim()).filter(Boolean);
}

const app = express();
app.use(express.json());

app.use((req, res, next) => {
  res.setHeader('Access-Control-Allow-Origin', CORS_ORIGIN);
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type, x-mbbank-secret');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  if (req.method === 'OPTIONS') return res.sendStatus(204);
  next();
});

// ---- Kho giao dịch (RAM) ----
/** @type {Map<string, any>} key = mã đơn/hóa đơn (HOA) */
const payments = new Map();
let balance = 50_000_000;

const normalize = (s) => String(s || '').toUpperCase().trim().replace(/\s+/g, ' ');
const CODE_RE = new RegExp(`^(?:${MEMO_PREFIX}|THANHTOAN|BREWLITE)\\s+([A-Z0-9_-]{3,30})$`);
const HOTEL_HD_RE = /\b(HD_[A-Z0-9]+|HD\d+)\b/;

function extractOrderCode(content, invoiceCode) {
  const hd = content.match(HOTEL_HD_RE);
  if (hd) return hd[1];
  const m = content.match(CODE_RE);
  if (m) return m[1];
  const raw = normalize(invoiceCode);
  if (raw && (content === `${MEMO_PREFIX} ${raw}` || content === `THANHTOAN ${raw}` || content === raw)) {
    return raw;
  }
  return '';
}

async function forwardToHotelWebhook(record) {
  const urls = getHotelWebhookUrls();
  if (!urls.length) {
    return { forwarded: false, ok: false, reason: 'DISABLED' };
  }

  const payload = {
    content: record.content,
    amount: record.amount,
    transferAmount: record.amount,
    trans_id: record.transId,
  };

  for (const url of urls) {
    try {
      const resp = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload),
        signal: AbortSignal.timeout(5000),
      });
      const text = await resp.text();
      let json = null;
      try {
        json = JSON.parse(text);
      } catch (_) {
        json = null;
      }
      // Endpoint không tồn tại ở server này -> thử URL tiếp theo
      if (resp.status === 404) continue;
      if (!json) continue;

      const ok = Boolean(json.success);
      const msg = json.message || json.error || '';
      console.log(`[hotel-webhook] ${ok ? 'OK' : 'REJECT'} -> ${url}: ${msg}`);
      return { forwarded: true, ok, url, status: resp.status, message: msg, data: json.data || null };
    } catch (_) {
      // Bỏ qua nếu server ở URL này chưa chạy, thử URL kế tiếp
    }
  }

  console.log('[hotel-webhook] SKIP (không kết nối được PHP Backend trên cổng 8000 hoặc XAMPP)');
  return { forwarded: false, ok: false, reason: 'UNREACHABLE' };
}

// ---- Webhook: app MB giả lập gọi khi chuyển khoản xong ----
app.post('/api/payments/mbbank-sync', async (req, res) => {
  if (SECRET && req.headers['x-mbbank-secret'] !== SECRET) {
    return res.status(403).json({ success: false, error: 'Sai x-mbbank-secret' });
  }
  const d = req.body || {};
  const amount = Number(d.amount);
  if (!Number.isFinite(amount) || amount <= 0) {
    return res.status(400).json({ success: false, error: 'Số tiền phải > 0' });
  }
  const content = normalize(d.content);
  const orderCode = extractOrderCode(content, d.invoice_code);

  const record = {
    orderCode,
    amount,
    content: String(d.content || ''),
    sender: String(d.sender || '0589822346'),
    senderName: String(d.sender_name || d.senderName || 'NGUYEN HOANG ANH'),
    transId: String(d.trans_id || d.transId || `FT${Date.now()}`),
    time: Date.now(),
  };

  if (orderCode) payments.set(orderCode, record);
  console.log(`[mbbank-sync] đơn=${orderCode || '(không khớp)'} tiền=${amount} nội dung="${record.content}"`);

  const phpWebhook = orderCode
    ? await forwardToHotelWebhook(record)
    : { forwarded: false, ok: false, reason: 'NO_ORDER_CODE' };

  res.json({ success: true, matched: Boolean(orderCode), payment: record, phpWebhook });
});

// ---- Polling: shop hỏi trạng thái ----
app.get('/api/payments/mbbank-check/:orderCode', (req, res) => {
  const code = normalize(req.params.orderCode);
  const expected = Number(req.query.expectedAmount);
  const p = payments.get(code);

  if (p && Date.now() - p.time < TTL) {
    if (Number.isFinite(expected) && expected > 0 && p.amount < expected) {
      return res.json({ success: true, data: { paid: false, reason: 'UNDERPAID', receivedAmount: p.amount, expectedAmount: expected } });
    }
    return res.json({ success: true, data: { paid: true, payment: p } });
  }
  res.json({ success: true, data: { paid: false } });
});

// ---- Huỷ mã sau khi đơn xong/bị huỷ ----
app.post('/api/payments/mbbank-invalidate/:orderCode', (req, res) => {
  const code = normalize(req.params.orderCode);
  payments.delete(code);
  res.json({ success: true, message: `Đã huỷ mã thanh toán ${code}` });
});

// ---- Sinh ảnh QR VietQR offline ----
app.get('/api/qr.png', async (req, res) => {
  const data = String(req.query.data || '').trim();
  if (!data || data.length > 1024) {
    return res.status(400).send('Invalid QR data');
  }
  try {
    const png = await QRCode.toBuffer(data, {
      type: 'png',
      width: 280,
      margin: 2,
      errorCorrectionLevel: 'M',
    });
    res.setHeader('Content-Type', 'image/png');
    res.setHeader('Cache-Control', 'public, max-age=300');
    res.send(png);
  } catch (err) {
    res.status(500).send('QR error');
  }
});

// ---- Số dư giả lập dùng chung (app + admin gọi /api_payment.php) ----
app.get('/api_payment.php', (req, res) => {
  const { action } = req.query;
  if (action === 'set_balance') {
    const v = Number(req.query.balance);
    if (Number.isFinite(v) && v >= 0) balance = Math.round(v);
  } else if (action === 'deduct') {
    const v = Number(req.query.amount);
    if (Number.isFinite(v) && v > 0) balance = Math.max(0, balance - Math.round(v));
  }
  res.json({ success: true, balance });
});

// ---- Giao diện ----
app.get('/mbbank', (_req, res) => res.redirect('/mbbank/index.html' + (_req.url.includes('?') ? _req.url.slice(_req.url.indexOf('?')) : '')));
app.get('/mbbank/admin', (_req, res) => res.redirect('/mbbank/admin.html'));
app.use(express.static(path.join(__dirname, 'public')));
app.use('/client', express.static(path.join(__dirname, 'client')));
app.use('/example', express.static(path.join(__dirname, 'example')));

const VIRTUAL_IFACE_RE = /vethernet|wsl|virtualbox|vbox|vmware|hyper-v|docker|hamachi|tailscale|zerotier/i;

function scoreLanInterface(name, address) {
  let score = 0;
  if (VIRTUAL_IFACE_RE.test(name) || address.startsWith('192.168.56.')) score -= 100;
  if (/wi-?fi|wlan|wireless/i.test(name)) score += 20;
  else if (/ethernet/i.test(name)) score += 5;
  if (/^192\.168\.(0|1)\./.test(address)) score += 10;
  else if (address.startsWith('172.')) score -= 20;
  return score;
}

function getLanUrls(port) {
  const customIp = (process.env.LAN_IP || '').trim();
  const nets = os.networkInterfaces();
  const candidates = [];
  for (const name of Object.keys(nets)) {
    for (const net of nets[name] || []) {
      if (net.family === 'IPv4' && !net.internal) {
        candidates.push({
          url: `http://${net.address}:${port}/mbbank`,
          score: scoreLanInterface(name, net.address),
        });
      }
    }
  }
  candidates.sort((a, b) => b.score - a.score);
  const urls = candidates.map((c) => c.url);
  if (customIp) {
    const customUrl = `http://${customIp}:${port}/mbbank`;
    return [customUrl, ...urls.filter((u) => u !== customUrl)];
  }
  return urls;
}

if (require.main === module) {
  app.listen(PORT, '0.0.0.0', async () => {
    console.log(`MBBank Simulator: http://localhost:${PORT}/mbbank   (admin: /mbbank/admin)`);
    const lanUrls = getLanUrls(PORT);
    if (lanUrls.length) {
      console.log(`Mạng LAN (cùng Wi-Fi): ${lanUrls.join(' | ')}`);
    }
    console.log(`Nội dung CK chuẩn: "${MEMO_PREFIX} <MaHD>" (VD: "THANHTOAN HD0001")`);
    console.log(`Forward webhook PHP: ${getHotelWebhookUrls().join(' -> ')}`);

    const qrTarget = (process.env.PUBLIC_URL || '').trim() || lanUrls[0] || `http://localhost:${PORT}/mbbank`;
    try {
      const asciiQr = await QRCode.toString(qrTarget, { type: 'terminal', small: true });
      console.log(`\nQuét mã QR này bằng điện thoại để mở MBBank Simulator (${qrTarget}):\n`);
      console.log(asciiQr);
    } catch (_) {}
  });
}
module.exports = app;
