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
