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
