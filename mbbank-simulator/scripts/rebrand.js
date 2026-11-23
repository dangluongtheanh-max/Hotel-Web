/**
 * Đổi thương hiệu: thay BREWLITE / BrewLite trong app giả lập + client + example.
 *   node scripts/rebrand.js --prefix MYSHOP --name "My Shop" --beneficiary "MY SHOP COFFEE"
 * Sau đó đặt MEMO_PREFIX=MYSHOP khi chạy server.
 */
const fs = require('fs'), path = require('path');
const args = Object.fromEntries(process.argv.slice(2).reduce((a, v, i, r) => (v.startsWith('--') ? [...a, [v.slice(2), r[i + 1]]] : a), []));
if (!args.prefix) { console.error('Thiếu --prefix. VD: --prefix MYSHOP --name "My Shop"'); process.exit(1); }
const prefix = args.prefix.toUpperCase().replace(/[^A-Z0-9]/g, '');
const name = args.name || args.prefix;
const files = [
  'public/mbbank/index.html', 'public/mbbank/admin.html',
  'client/mbbank-client.js', 'example/index.html', 'server.js',
].map((f) => path.join(__dirname, '..', f));

let n = 0;
for (const f of files) {
  let s = fs.readFileSync(f, 'utf8');
  const before = s;
  if (args.beneficiary) s = s.replace(/BREWLITE COFFEE/g, args.beneficiary.toUpperCase());
  s = s.replace(/BREWLITE/g, prefix).replace(/BrewLite/g, name).replace(/brewlite_payment_channel/g, `${prefix.toLowerCase()}_payment_channel`)
       .replace(/brewlite_mb_payment/g, `${prefix.toLowerCase()}_mb_payment`);
  if (s !== before) { fs.writeFileSync(f, s); n++; }
}
console.log(`Đã cập nhật ${n} file. Nhớ chạy server với MEMO_PREFIX=${prefix}`);
