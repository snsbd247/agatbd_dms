import './bootstrap';
import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);
window.Chart = Chart;

// Chart text and grid lines follow the light/dark theme.
function applyChartTheme() {
    const dark = document.documentElement.classList.contains('dark');
    Chart.defaults.color = dark ? '#cbd5e1' : '#64748b';
    Chart.defaults.borderColor = dark ? 'rgba(148, 163, 184, 0.18)' : 'rgba(0, 0, 0, 0.1)';
}

applyChartTheme();

window.dmsSetTheme = function (theme) {
    try { localStorage.setItem('dms_theme', theme); } catch (e) {}
    const dark = theme === 'dark' || (theme !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);
    document.documentElement.classList.toggle('dark', dark);
    applyChartTheme();
    Object.values(Chart.instances).forEach((chart) => chart.update());
};

// "Download as Excel": the main table on the page as a CSV file (Excel
// opens it). Row buttons and the action column are left out; on phones the
// card layout's labels come from the header row, so the data is the same.
window.dmsExportTable = function (filename) {
    const table = document.querySelector('main table');
    if (!table) { alert(document.documentElement.lang === 'bn' ? 'এই পেজে কোনো টেবিল নেই।' : 'There is no table on this page.'); return; }
    const skip = new Set();
    const head = table.querySelector('thead tr:last-child');
    const rows = [];
    if (head) {
        const cells = [...head.children];
        cells.forEach((c, i) => { if (!c.innerText.trim() || c.hasAttribute('data-actions')) skip.add(i); });
        rows.push(cells.filter((c, i) => !skip.has(i)).map(c => c.innerText.trim()));
    }
    table.querySelectorAll('tbody tr, tfoot tr').forEach(tr => {
        const cells = [...tr.children].filter(c => !c.classList.contains('rtable-title'));
        if (cells.length === 1 && cells[0].colSpan > 1) return;   // "nothing found" row
        rows.push(cells.filter((c, i) => !skip.has(i) && !c.hasAttribute('data-actions'))
            .map(c => c.innerText.replace(/\s+/g, ' ').trim()));
    });
    const csv = rows.map(r => r.map(v => /[",\n]/.test(v) ? '"' + v.replace(/"/g, '""') + '"' : v).join(',')).join('\r\n');
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (filename || 'export').replace(/[^\w\u0980-\u09FF-]+/g, '-') + '-' + new Date().toISOString().slice(0, 10) + '.csv';
    document.body.appendChild(a);
    a.click();
    setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
};

// Camera barcode / QR scanner. window.dmsScan() opens the camera and
// resolves with the first code read (or null if closed). Uses the browser's
// own BarcodeDetector where there is one (Chrome, Android) and loads the
// ZXing reader only when needed (iPhone Safari, Firefox).
window.dmsScan = function () {
    return new Promise((resolve) => {
        const bn = document.documentElement.lang === 'bn';
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 z-[60] bg-black/90 flex flex-col items-center justify-center p-4';
        overlay.innerHTML = '<video playsinline muted class="w-full max-w-md rounded-xl bg-black aspect-[3/4] object-cover"></video>'
            + '<div class="pointer-events-none absolute w-64 h-40 border-2 border-white/80 rounded-lg"></div>'
            + '<p class="text-white text-sm mt-3 text-center" data-msg>' + (bn ? 'বারকোড বা QR কোড ফ্রেমের ভেতরে ধরুন' : 'Hold the barcode or QR code inside the frame') + '</p>'
            + '<button type="button" class="mt-4 px-6 py-2 rounded-lg bg-white text-slate-800 font-medium">' + (bn ? 'বন্ধ করুন' : 'Close') + '</button>';
        document.body.appendChild(overlay);
        const video = overlay.querySelector('video');
        const msg = overlay.querySelector('[data-msg]');
        let stream = null, stopZxing = null, done = false, timer = null;

        const finish = (code) => {
            if (done) return;
            done = true;
            clearInterval(timer);
            if (stopZxing) { try { stopZxing(); } catch (e) {} }
            if (stream) stream.getTracks().forEach(t => t.stop());
            overlay.remove();
            if (code && navigator.vibrate) navigator.vibrate(80);
            resolve(code || null);
        };
        overlay.querySelector('button').addEventListener('click', () => finish(null));

        (async () => {
            try {
                if ('BarcodeDetector' in window) {
                    const detector = new window.BarcodeDetector({ formats: ['ean_13', 'ean_8', 'code_128', 'code_39', 'upc_a', 'upc_e', 'qr_code', 'itf'] });
                    stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
                    video.srcObject = stream;
                    await video.play();
                    timer = setInterval(async () => {
                        try {
                            const found = await detector.detect(video);
                            if (found.length) finish(found[0].rawValue);
                        } catch (e) {}
                    }, 250);
                } else {
                    const { BrowserMultiFormatReader } = await import('@zxing/browser');
                    const reader = new BrowserMultiFormatReader();
                    const controls = await reader.decodeFromConstraints({ video: { facingMode: 'environment' } }, video, (result) => {
                        if (result) finish(result.getText());
                    });
                    stopZxing = () => controls.stop();
                }
            } catch (e) {
                msg.textContent = bn ? 'ক্যামেরা চালু করা যায়নি। অনুমতি দিন, অথবা HTTPS-এ খুলুন।' : 'Could not start the camera. Allow camera access, or open the site over HTTPS.';
            }
        })();
    });
};

// Offline order taking (Sales → Offline Orders): customers, products and
// unsent orders are kept in this phone's storage per user; see OfflineOrderController.
window.dmsOfflineOrders = (key, dataUrl, syncUrl, t) => ({
        online: navigator.onLine, busy: false, message: '',
        customers: [], products: [], dataAt: null, token: document.querySelector('meta[name=csrf-token]')?.content || '',
        queue: [], sent: [], customerId: '', search: '', lines: [],
        init() {
            try {
                const s = JSON.parse(localStorage.getItem(key) || '{}');
                Object.assign(this, { customers: s.customers || [], products: s.products || [], dataAt: s.dataAt || null, queue: s.queue || [], sent: s.sent || [] });
                if (s.token) this.token = s.token;
            } catch (e) {}
            window.addEventListener('online', () => { this.online = true; this.sync(); });
            window.addEventListener('offline', () => { this.online = false; });
            if (this.online && !this.customers.length) this.refresh();
        },
        store() {
            try { localStorage.setItem(key, JSON.stringify({ customers: this.customers, products: this.products, dataAt: this.dataAt, queue: this.queue, sent: this.sent.slice(0, 20), token: this.token })); } catch (e) {}
        },
        async refresh() {
            this.busy = true;
            try {
                const r = await fetch(dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                if (!r.ok) throw new Error(r.status);
                const d = await r.json();
                Object.assign(this, { customers: d.customers, products: d.products, dataAt: d.at, token: d.token });
                this.store();
                this.message = t.refreshed;
            } catch (e) { this.message = t.failed; }
            this.busy = false;
        },
        customer() { return this.customers.find(c => c.id === this.customerId); },
        customerName(id) { return (this.customers.find(c => c.id === id) || {}).name || '#' + id; },
        productById(id) { return this.products.find(p => p.id === id) || { price: 0 }; },
        priceOf(p) { const c = this.customer(); return c && c.dealer && p.dealer_price > 0 ? p.dealer_price : p.price; },
        matches() {
            const q = this.search.toLowerCase();
            return this.products.filter(p => (p.name + ' ' + p.sku + ' ' + (p.barcode || '')).toLowerCase().includes(q)).slice(0, 30);
        },
        add(p) {
            const line = this.lines.find(l => l.id === p.id);
            line ? line.qty++ : this.lines.push({ id: p.id, name: p.name, qty: 1 });
        },
        addByCode(code) {
            const p = this.products.find(p => p.barcode === code || p.sku === code);
            p ? this.add(p) : (this.message = code + ' ?');
        },
        total() { return this.lines.reduce((s, l) => s + l.qty * this.priceOf(this.productById(l.id)), 0); },
        save() {
            const uuid = (crypto.randomUUID ? crypto.randomUUID() : Date.now() + '-' + Math.random().toString(16).slice(2));
            const d = new Date();
            const date = d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
            this.queue.push({ uuid, retailer_id: this.customerId, order_date: date, items: this.lines.map(l => ({ product_id: l.id, quantity: l.qty })) });
            this.lines = []; this.customerId = '';
            this.store();
            this.message = t.saved;
            if (this.online) this.sync();
        },
        drop(uuid) { this.queue = this.queue.filter(o => o.uuid !== uuid); this.store(); },
        async sync() {
            if (!this.queue.length || this.busy) return;
            this.busy = true;
            try {
                // A fresh token first, in case the page was opened long ago.
                try { const r = await fetch(dataUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' }); if (r.ok) { const d = await r.json(); Object.assign(this, { customers: d.customers, products: d.products, dataAt: d.at, token: d.token }); } } catch (e) {}
                const r = await fetch(syncUrl, { method: 'POST', credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': this.token },
                    body: JSON.stringify({ orders: this.queue.map(({ error, ...o }) => o) }) });
                if (r.status === 419 || r.status === 401) { this.message = t.expired; this.busy = false; return; }
                if (!r.ok) throw new Error(r.status);
                const d = await r.json();
                this.token = d.token || this.token;
                let n = 0;
                for (const res of d.results) {
                    const o = this.queue.find(q => q.uuid === res.uuid);
                    if (!o) continue;
                    if (res.ok) { this.sent.unshift({ uuid: o.uuid, retailer_id: o.retailer_id, number: res.number }); this.queue = this.queue.filter(q => q.uuid !== res.uuid); n++; }
                    else { o.error = res.error; }
                }
                this.message = t.synced.replace(':n', n);
            } catch (e) { this.message = t.failed; }
            this.store();
            this.busy = false;
        },
    });
