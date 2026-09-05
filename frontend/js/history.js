/**
 * ============================================================
 * HISTORY.JS
 * Powers frontend/history.html - "who did we see on date X,
 * who saw them, what did they get".
 * ============================================================
 */

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Doctor', 'Cashier', 'Admin']);
    if (!user) return;

    const dateInput = document.getElementById('dateInput');
    dateInput.value = new Date().toISOString().slice(0, 10); // default: today

    document.getElementById('loadBtn').addEventListener('click', loadHistory);
    loadHistory();
});

async function loadHistory() {
    const date = document.getElementById('dateInput').value;
    const listEl = document.getElementById('visitsList');
    const countEl = document.getElementById('countLine');

    if (!date) {
        alert('Please choose a date.');
        return;
    }

    listEl.innerHTML = '<p style="color:#6b7280;font-size:13.5px;">Loading...</p>';
    countEl.textContent = '';

    try {
        const res = await fetch(`${API_BASE}/visits_by_date.php?date=${date}`);
        const data = await res.json();

        if (!res.ok) {
            listEl.innerHTML = '';
            countEl.textContent = data.error || 'Failed to load.';
            return;
        }

        countEl.textContent = `${data.count} visit(s) on ${data.date}`;

        if (!data.visits.length) {
            listEl.innerHTML = '<p style="color:#6b7280;font-size:13.5px;">No visits recorded on this date.</p>';
            return;
        }

        listEl.innerHTML = data.visits.map(renderVisit).join('');

    } catch (err) {
        console.error(err);
        listEl.innerHTML = '';
        countEl.textContent = 'Failed to load.';
    }
}

function renderVisit(v) {
    const rx = (v.prescriptions || []).map(p => `
        <li>${escapeHtml(p.medicine_name)} — ${escapeHtml(p.dosage || '-')}, ${escapeHtml(p.duration || '-')} (Qty: ${p.quantity})${p.removed_by_cashier == 1 ? ' <span class="badge badge-danger">Removed</span>' : ''}</li>
    `).join('');

    return `
        <div class="visit-card">
            <div class="visit-top">
                <strong>${escapeHtml(v.patient_name)}</strong>
                <span class="visit-time">${v.visit_date}</span>
            </div>
            <div style="font-size:13px;margin-top:4px;"><strong>Doctor:</strong> ${escapeHtml(v.doctor_name)} &nbsp;|&nbsp; <span class="badge badge-info">${escapeHtml(v.status)}</span></div>
            <div style="font-size:13px;margin-top:4px;"><strong>Diagnosis:</strong> ${escapeHtml(v.diagnosis || '-')}</div>
            <div style="font-size:13px;margin-top:4px;"><strong>Consultation Fee:</strong> Rs. ${Number(v.consultation_fee).toFixed(2)}</div>
            ${rx ? `<div style="margin-top:8px;"><strong style="font-size:13px;">Medicines:</strong><ul class="rx-list">${rx}</ul></div>` : ''}
        </div>
    `;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
