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
