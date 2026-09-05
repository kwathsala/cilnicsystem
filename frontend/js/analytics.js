/**
 * ============================================================
 * ANALYTICS.JS
 * Powers frontend/analytics.html
 * ============================================================
 */

let activeRange = 'today';

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Doctor', 'Admin']);
    if (!user) return;

    document.querySelectorAll('.range-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            activeRange = btn.dataset.range;
            setActiveButton();
            loadAnalytics();
        });
    });

    document.getElementById('customApplyBtn').addEventListener('click', () => {
        const from = document.getElementById('customFrom').value;
        const to = document.getElementById('customTo').value;
        if (!from || !to) {
            alert('Please choose both a "from" and a "to" date.');
            return;
        }
        activeRange = 'custom';
        setActiveButton();
        loadAnalytics(from, to);
    });

    setActiveButton();
    loadAnalytics();
});

function setActiveButton() {
    document.querySelectorAll('.range-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.range === activeRange);
    });
}

async function loadAnalytics(from, to) {
    let url = `${API_BASE}/analytics_summary.php?range=${activeRange}`;
    if (activeRange === 'custom' && from && to) {
        url += `&from=${from}&to=${to}`;
    }

    try {
        const res = await fetch(url);
        const data = await res.json();

        if (!res.ok) {
            alert(data.error || 'Failed to load analytics.');
            return;
        }

        renderStats(data);
        renderChart(data.daily_trend);
        renderTopMedicines(data.top_medicines);
        renderByDoctor(data.by_doctor);

    } catch (err) {
        console.error(err);
        alert('Failed to load analytics.');
    }
}

function money(n) {
    return `Rs. ${Number(n).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

function renderStats(data) {
    document.getElementById('statRevenue').textContent = money(data.total_revenue);
    document.getElementById('statCogs').textContent = money(data.cogs);
    document.getElementById('statProfit').textContent = money(data.net_profit);
    document.getElementById('statInvoices').textContent = data.invoice_count;

    document.getElementById('breakdownConsultation').textContent = money(data.consultation_revenue);
    document.getElementById('breakdownMedicine').textContent = money(data.medicine_revenue);

    const warnEl = document.getElementById('missingCostWarning');
    if (data.missing_cost_price_items > 0) {
        warnEl.style.display = 'block';
        warnEl.textContent = `⚠️ ${data.missing_cost_price_items} billed medicine line(s) in this range don't have a cost price set yet, so Cost of Goods Sold and Net Profit shown here are understated. Set cost prices from the Inventory page for accurate margins.`;
    } else {
        warnEl.style.display = 'none';
    }
}

function renderChart(dailyTrend) {
    const container = document.getElementById('chartBars');
    if (!dailyTrend || !dailyTrend.length) {
        container.innerHTML = `<div style="font-size:13px;color:#6b7280;">No revenue recorded in this range yet.</div>`;
        return;
    }

    const max = Math.max(...dailyTrend.map(d => d.revenue), 1);

    container.innerHTML = dailyTrend.map(d => {
        const heightPct = Math.max((d.revenue / max) * 100, 2);
        const shortDate = d.date.slice(5); // MM-DD
        return `
            <div class="chart-bar-wrap">
                <div class="chart-value">${d.revenue > 0 ? Math.round(d.revenue) : ''}</div>
                <div class="chart-bar" style="height:${heightPct}%;"></div>
                <div class="chart-label">${shortDate}</div>
            </div>
        `;
    }).join('');
}

function renderTopMedicines(list) {
    const tbody = document.getElementById('topMedicinesBody');
    if (!list || !list.length) {
        tbody.innerHTML = `<tr><td colspan="3">No medicine sales in this range.</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(m => `
        <tr>
            <td>${escapeHtml(m.name)}</td>
            <td>${m.qty_sold}</td>
            <td>${money(m.revenue)}</td>
        </tr>
    `).join('');
}

function renderByDoctor(list) {
    const tbody = document.getElementById('byDoctorBody');
    if (!list || !list.length) {
        tbody.innerHTML = `<tr><td colspan="3">No billed visits in this range.</td></tr>`;
        return;
    }
    tbody.innerHTML = list.map(d => `
        <tr>
            <td>${escapeHtml(d.doctor_name)}</td>
            <td>${d.visit_count}</td>
            <td>${money(d.revenue)}</td>
        </tr>
    `).join('');
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}
