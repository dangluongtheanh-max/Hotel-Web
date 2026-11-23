/**
 * mbbank-client.js — helper phía shop (không phụ thuộc framework).
 * Dùng được bằng <script> (window.MBBank) hoặc import (ESM/CJS bundler).
 *
 *   const qr = MBBank.generateVietQrEmv({ amount: 45000, orderCode: 'BL123456' });
 *   // -> vẽ qr bằng thư viện bất kỳ (qrcode, qrcodejs...)
 *   const stop = MBBank.waitForPayment({
 *     baseUrl: 'http://localhost:4000',
 *     orderCode: 'BL123456', expectedAmount: 45000,
 *     onPaid: (payment) => { ... },
 *   });
 */
(function (root, factory) {
  if (typeof module === 'object' && module.exports) module.exports = factory();
  else root.MBBank = factory();
})(typeof self !== 'undefined' ? self : this, function () {
  const tlv = (tag, value) => `${tag}${String(value.length).padStart(2, '0')}${value}`;

  function crc16(str) {
    let crc = 0xffff;
    for (let i = 0; i < str.length; i++) {
      crc ^= str.charCodeAt(i) << 8;
      for (let j = 0; j < 8; j++) crc = crc & 0x8000 ? ((crc << 1) ^ 0x1021) & 0xffff : (crc << 1) & 0xffff;
    }
    return crc.toString(16).toUpperCase().padStart(4, '0');
  }

  /** Sinh chuỗi VietQR (EMVCo) — app giả lập quét được và đọc ra STK, số tiền, nội dung. */
  function generateVietQrEmv({
    bankBin = '970422',            // 970422 = MBBank
    accountNumber = '0589822346',
    amount,
    orderCode,
    memoPrefix = 'THANHTOAN',      // phải trùng MEMO_PREFIX của server
    beneficiaryName = 'NGUYEN HOANG ANH',
    city = 'HO CHI MINH',
  }) {
    const amt = Math.max(0, Math.round(Number(amount) || 0));
    const tag38 = tlv('38', tlv('00', 'A000000727') + tlv('01', tlv('00', bankBin) + tlv('01', accountNumber)) + tlv('02', 'QRIBFTTA'));
    const tag62 = tlv('62', tlv('08', `${memoPrefix} ${orderCode}`));
    const raw = [
      tlv('00', '01'),
      tlv('01', amt > 0 ? '12' : '11'),
      tag38,
      tlv('53', '704'),
      amt > 0 ? tlv('54', String(amt)) : '',
      tlv('58', 'VN'),
      tlv('59', beneficiaryName),
      tlv('60', city),
      tag62,
      '6304',
    ].join('');
    return raw + crc16(raw);
  }

  /** Link mở thẳng app giả lập với form chuyển khoản điền sẵn (dùng khi test trên cùng máy). */
  function buildSimulatorUrl({ baseUrl = '', amount, orderCode, memoPrefix = 'THANHTOAN', name = 'NGUYEN HOANG ANH', account = '0589822346', bank = 'MB' }) {
    const q = new URLSearchParams({ amount: String(amount), orderCode, memo: `${memoPrefix} ${orderCode}`, name, account, bank });
    return `${baseUrl.replace(/\/+$/, '')}/mbbank/index.html?${q}`;
  }

  /**
   * Chờ khách chuyển khoản. Kết hợp 3 kênh: BroadcastChannel + localStorage (cùng trình duyệt)
   * và polling server (điện thoại quét QR → máy tính). Trả về hàm stop().
   */
  function waitForPayment({
    baseUrl = '',
    orderCode,
    expectedAmount,
    memoPrefix = 'THANHTOAN',
    exactAmount = true,   // true: phải đúng từng đồng; false: chấp nhận chuyển dư
    intervalMs = 1000,
    maxAgeMs = 10 * 60 * 1000,
    onPaid,
    onUnderpaid,
    onError,
  }) {
    const code = String(orderCode).toUpperCase().trim();
    const expectedMemo = `${memoPrefix.toUpperCase()} ${code}`;
    let done = false, busy = false, lastTime = 0, bc = null, timer = null;
    const api = baseUrl.replace(/\/+$/, '');

    const accept = (p) => {
      if (done || !p) return;
      const memo = String(p.content || '').toUpperCase().trim().replace(/\s+/g, ' ');
      const amount = Math.round(Number(p.amount ?? p.paidAmount));
      if (memo !== expectedMemo) return;                       // sai nội dung -> im lặng
      if (Date.now() - Number(p.time || Date.now()) > maxAgeMs) return;
      if (expectedAmount != null) {
        if (exactAmount ? amount !== Math.round(expectedAmount) : amount < Math.round(expectedAmount)) {
          if (onUnderpaid) onUnderpaid(p);
          return;
        }
      }
      done = true; stop();
      onPaid && onPaid(p);
    };

    const checkLocal = () => {
      try {
        const raw = localStorage.getItem('brewlite_mb_payment') || localStorage.getItem('last_mb_payment');
        if (!raw) return;
        const p = JSON.parse(raw);
        if (p && p.time > lastTime) { lastTime = p.time; accept(p); }
      } catch (_) {}
    };

    const poll = async () => {
      if (done || busy) return;
      busy = true;
      try {
        const qs = expectedAmount != null ? `?expectedAmount=${Math.round(expectedAmount)}` : '';
        const res = await fetch(`${api}/api/payments/mbbank-check/${encodeURIComponent(code)}${qs}`, { cache: 'no-store' });
        const json = await res.json();
        const d = json.data || json;
        if (d && d.paid && d.payment) accept(d.payment);
      } catch (e) { onError && onError(e); }
      busy = false;
    };

    try {
      if (typeof BroadcastChannel !== 'undefined') {
        bc = new BroadcastChannel('brewlite_payment_channel');
        bc.onmessage = (ev) => accept(ev.data);
      }
    } catch (_) {}
    timer = setInterval(() => { checkLocal(); poll(); }, intervalMs);

    function stop() { clearInterval(timer); if (bc) { try { bc.close(); } catch (_) {} } }
    return stop;
  }

  async function invalidate({ baseUrl = '', orderCode }) {
    try { await fetch(`${baseUrl.replace(/\/+$/, '')}/api/payments/mbbank-invalidate/${encodeURIComponent(orderCode)}`, { method: 'POST' }); } catch (_) {}
  }

  return { generateVietQrEmv, buildSimulatorUrl, waitForPayment, invalidate, crc16 };
});
