/**
 * ============================================================
 * RECEPTION.JS
 * Handles: patient search, viewing history (with allergy alerts),
 * and new patient registration.
 * ============================================================
 */

let searchTimer = null;

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Reception', 'Admin']);
    if (!user) return;

    document.getElementById('searchInput').addEventListener('input', onSearchInput);
    document.getElementById('registerForm').addEventListener('submit', onRegisterSubmit);
});

function onSearchInput(e) {
    clearTimeout(searchTimer);
    const q = e.target.value.trim();
    const resultsEl = document.getElementById('searchResults');

    if (q.length < 2) {
        resultsEl.innerHTML = '';
        return;
    }

    searchTimer = setTimeout(async () => {
        const res = await fetch(`${API_BASE}/patients_search.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        renderSearchResults(data.patients || []);
    }, 300);
}

function renderSearchResults(patients) {
    const resultsEl = document.getElementById('searchResults');

    if (patients.length === 0) {
        resultsEl.innerHTML = '<p style="color:#6b7280; font-size:13.5px;">No patients found.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Name</th><th>Contact</th><th>Type</th><th>Age</th><th></th><th></th></tr></thead><tbody>';
    patients.forEach(p => {
        html += `<tr>
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.contact_number)}</td>
            <td>${badgeForType(p.patient_type)}</td>
            <td>${p.age ?? '-'}</td>
            <td><button class="btn" style="padding:5px 12px; font-size:12.5px;" onclick="viewHistory(${p.patient_id})">View History</button></td>
            <td><button class="btn btn-secondary" style="padding:5px 12px; font-size:12.5px;" onclick="sendToQueue(${p.patient_id}, this)">🎫 Send to Doctor</button></td>
        </tr>`;
    });
    html += '</tbody></table>';
    resultsEl.innerHTML = html;
}

async function sendToQueue(patientId, btn) {
    btn.disabled = true;
    btn.textContent = 'Adding...';
    try {
        const res = await fetch(`${API_BASE}/queue_add.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ patient_id: patientId }),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Failed to add to queue.');
            btn.disabled = false;
            btn.textContent = '🎫 Send to Doctor';
            return;
        }
        btn.textContent = `✅ Token #${data.token_number} issued`;
    } catch (err) {
        console.error(err);
        alert('Failed to add to queue.');
        btn.disabled = false;
        btn.textContent = '🎫 Send to Doctor';
    }
}

function badgeForType(type) {
    if (type === 'Regular') return '<span class="badge badge-info">Regular</span>';
    if (type === 'Monthly') return '<span class="badge badge-warning">Monthly</span>';
    return '<span class="badge">Normal</span>';
}

async function viewHistory(patientId) {
    const res = await fetch(`${API_BASE}/patient_history.php?patient_id=${patientId}`);
    if (!res.ok) {
        alert('Could not load patient history.');
        return;
    }
    const data = await res.json();
    renderHistory(data.patient, data.visits);
}

function renderHistory(patient, visits) {
    const card = document.getElementById('historyCard');
    card.style.display = 'block';

    // Allergy alert - prominent for Regular patients (and shown for any patient with allergies)
    const allergyEl = document.getElementById('allergyBanner');
    if (patient.allergies && patient.allergies.trim() !== '') {
        allergyEl.innerHTML = `<div class="allergy-alert">⚠️ ALLERGY ALERT: ${escapeHtml(patient.allergies)}</div>`;
    } else {
        allergyEl.innerHTML = '';
    }

    document.getElementById('patientProfile').innerHTML = `
        <div class="form-grid">
            <div><strong>Name:</strong> ${escapeHtml(patient.name)}</div>
            <div><strong>Contact:</strong> ${escapeHtml(patient.contact_number)}</div>
            <div><strong>Age:</strong> ${patient.age ?? '-'}</div>
            <div><strong>Type:</strong> ${badgeForType(patient.patient_type)}</div>
            <div><strong>Address:</strong> ${escapeHtml(patient.address || '-')}</div>
            <div><strong>Next Report Due:</strong> ${patient.report_due_date || '-'}</div>
        </div>
    `;

    const historyEl = document.getElementById('visitHistory');
    if (visits.length === 0) {
        historyEl.innerHTML = '<p style="color:#6b7280; font-size:13.5px;">No previous visits.</p>';
        return;
    }

    let html = '<h3 style="margin-top:10px;">Previous Visits</h3>';
    visits.forEach(v => {
        html += `<div style="border:1px solid #eef1f4; border-radius:8px; padding:12px; margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between;">
                <strong>${v.visit_date}</strong>
                <span class="badge badge-info">${escapeHtml(v.status)}</span>
            </div>
            <div style="margin-top:6px; font-size:13.5px;"><strong>Doctor:</strong> ${escapeHtml(v.doctor_name)}</div>
            <div style="margin-top:4px; font-size:13.5px;"><strong>Diagnosis:</strong> ${escapeHtml(v.diagnosis || '-')}</div>
            <div style="margin-top:4px; font-size:13.5px;"><strong>Consultation Fee:</strong> Rs. ${Number(v.consultation_fee).toFixed(2)}</div>
            ${renderPrescriptions(v.prescriptions)}
        </div>`;
    });
    historyEl.innerHTML = html;
}

function renderPrescriptions(prescriptions) {
    if (!prescriptions || prescriptions.length === 0) return '';
    let html = '<div style="margin-top:8px;"><strong style="font-size:13px;">Medicines:</strong><ul style="margin:6px 0 0 18px; font-size:13px;">';
    prescriptions.forEach(p => {
        html += `<li>${escapeHtml(p.medicine_name)} — ${escapeHtml(p.dosage || '-')}, ${escapeHtml(p.duration || '-')} (Qty: ${p.quantity})${p.removed_by_cashier == 1 ? ' <span class="badge badge-danger">Removed</span>' : ''}</li>`;
    });
    html += '</ul></div>';
    return html;
}

async function onRegisterSubmit(e) {
    e.preventDefault();
    const form = e.target;
    const msgEl = document.getElementById('registerMsg');
    msgEl.textContent = '';
    msgEl.style.color = '';

    const payload = {
        name: form.name.value.trim(),
        address: form.address.value.trim(),
        age: form.age.value,
        contact_number: form.contact_number.value.trim(),
        patient_type: form.patient_type.value,
        allergies: form.allergies.value.trim(),
        monthly_fee: form.monthly_fee.value,
        report_due_date: form.report_due_date.value,
    };

    try {
        const res = await fetch(`${API_BASE}/patients_create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok) {
            msgEl.style.color = '#dc2626';
            msgEl.textContent = data.error || 'Registration failed.';
            return;
        }

        msgEl.style.color = '#059669';
        msgEl.textContent = `Patient registered successfully (ID: ${data.patient_id}). Adding to doctor's queue...`;
        form.reset();

        // A newly-registered patient is here to see the doctor -
        // send them straight into today's queue too.
        try {
            const qRes = await fetch(`${API_BASE}/queue_add.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ patient_id: data.patient_id }),
            });
            const qData = await qRes.json();
            if (qRes.ok) {
                msgEl.textContent = `Patient registered (ID: ${data.patient_id}). 🎫 Token #${qData.token_number} issued for the doctor's queue.`;
            } else {
                msgEl.textContent = `Patient registered (ID: ${data.patient_id}), but couldn't issue a queue token: ${qData.error || 'unknown error'}.`;
            }
        } catch (qErr) {
            console.error(qErr);
        }
    } catch (err) {
        msgEl.style.color = '#dc2626';
        msgEl.textContent = 'Something went wrong. Please try again.';
        console.error(err);
    }
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
