/**
 * ============================================================
 * DOCTOR.JS
 * Handles: patient search/select, building a prescription list,
 * and submitting the completed consultation (auto fee + instant
 * handoff to Cashier/Pharmacy queue).
 * ============================================================
 */

let searchTimer = null;
let selectedPatient = null;
let consultationFee = 0;
let prescriptionItems = []; // { medicine_id, name, dosage, duration, quantity, unit_price }
let medicineOptions = [];
let currentQueue = []; // today's waiting queue - the ONLY patients the search bar looks through

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Doctor', 'Admin']);
    if (!user) return;

    await loadDoctorInfo();
    await loadMedicineOptions();

    document.getElementById('searchInput').addEventListener('input', onSearchInput);
    document.getElementById('addMedicineBtn').addEventListener('click', onAddMedicine);
    document.getElementById('completeBtn').addEventListener('click', onComplete);
    document.getElementById('historySearchBtn').addEventListener('click', onHistorySearch);

    loadQueue();
    setInterval(loadQueue, 15000); // keep the "who's next" list fresh
});

async function onHistorySearch() {
    const date = document.getElementById('historyDateInput').value;
    const resultsEl = document.getElementById('historyResults');

    if (!date) {
        alert('Please pick a date first.');
        return;
    }

    resultsEl.innerHTML = '<p style="color:#6b7280; font-size:13.5px;">Loading...</p>';

    try {
        const res = await fetch(`${API_BASE}/prescriptions_by_date.php?date=${date}`);
        const data = await res.json();

        if (!res.ok) {
            resultsEl.innerHTML = `<p style="color:#dc2626;">${data.error || 'Failed to load.'}</p>`;
            return;
        }

        renderHistoryResults(data);
    } catch (err) {
        console.error(err);
        resultsEl.innerHTML = '<p style="color:#dc2626;">Failed to load.</p>';
    }
}

function renderHistoryResults(data) {
    const resultsEl = document.getElementById('historyResults');

    if (!data.visits.length) {
        resultsEl.innerHTML = `<p style="color:#6b7280; font-size:13.5px;">No visits recorded on ${data.date}.</p>`;
        return;
    }

    let html = `<p style="font-size:13.5px; color:#374151; margin-bottom:10px;"><strong>${data.visit_count}</strong> patient(s) seen on ${data.date}.</p>`;

    data.visits.forEach(v => {
        html += `<div style="border:1px solid #eef1f4; border-radius:8px; padding:12px; margin-bottom:10px;">
            <div style="display:flex; justify-content:space-between;">
                <strong>${escapeHtml(v.patient_name)} (${escapeHtml(v.contact_number)})</strong>
                <span class="badge badge-info">${escapeHtml(v.status)}</span>
            </div>
            <div style="margin-top:4px; font-size:13px; color:#6b7280;">${v.visit_date} — Dr. ${escapeHtml(v.doctor_name)}</div>
            <div style="margin-top:6px; font-size:13.5px;"><strong>Diagnosis:</strong> ${escapeHtml(v.diagnosis || '-')}</div>
            <div style="margin-top:4px; font-size:13.5px;"><strong>Consultation Fee:</strong> Rs. ${v.consultation_fee.toFixed(2)}</div>
            ${renderHistoryMedicines(v.prescriptions)}
        </div>`;
    });

    resultsEl.innerHTML = html;
}

function renderHistoryMedicines(prescriptions) {
    if (!prescriptions.length) return '<div style="margin-top:6px; font-size:13px; color:#9ca3af;">No medicines prescribed.</div>';
    let html = '<div style="margin-top:8px;"><strong style="font-size:13px;">Medicines given:</strong><ul style="margin:6px 0 0 18px; font-size:13px;">';
    prescriptions.forEach(p => {
        html += `<li>${escapeHtml(p.medicine_name)} — ${escapeHtml(p.dosage || '-')}, ${escapeHtml(p.duration || '-')} (Qty: ${p.quantity})${p.removed_by_cashier ? ' <span class="badge badge-danger">Removed at cashier</span>' : ''}</li>`;
    });
    html += '</ul></div>';
    return html;
}

async function loadQueue() {
    try {
        const res = await fetch(`${API_BASE}/queue_list.php`);
        if (!res.ok) return;
        const data = await res.json();
        currentQueue = data.queue || [];
        renderQueue(currentQueue);
    } catch (err) {
        console.error(err);
    }
}

function renderQueue(queue) {
    const listEl = document.getElementById('queueList');
    document.getElementById('queueCount').textContent = queue.length ? `(${queue.length})` : '';

    if (!queue.length) {
        listEl.innerHTML = '<div class="queue-empty">No one waiting right now.</div>';
        return;
    }

    listEl.innerHTML = queue.map(q => `
        <div class="queue-item" data-token-id="${q.token_id}">
            <span class="queue-token-num">${q.token_number}</span>
            <span>${escapeHtml(q.name)}${q.allergies && q.allergies.trim() !== '' ? ' ⚠️' : ''}</span>
        </div>
    `).join('');

    listEl.querySelectorAll('.queue-item').forEach(el => {
        el.addEventListener('click', () => pickFromQueue(el.dataset.tokenId));
    });
}

async function pickFromQueue(tokenId) {
    try {
        const res = await fetch(`${API_BASE}/queue_pick.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token_id: parseInt(tokenId, 10) }),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Could not pick this patient from the queue.');
            loadQueue(); // someone else may have already picked them up
            return;
        }
        selectPatient(data.patient); // same prefill behaviour as manual search-select
        loadQueue();
    } catch (err) {
        console.error(err);
        alert('Could not pick this patient from the queue.');
    }
}

async function loadDoctorInfo() {
    const res = await fetch(`${API_BASE}/doctor_info.php`);
    if (!res.ok) return;
    const data = await res.json();
    consultationFee = parseFloat(data.doctor.default_fee);
    document.getElementById('consultFeeInput').value = consultationFee.toFixed(2);
}

async function loadMedicineOptions(query = '') {
    const res = await fetch(`${API_BASE}/medicines_search.php?q=${encodeURIComponent(query)}`);
    const data = await res.json();
    medicineOptions = data.medicines || [];

    const select = document.getElementById('medicineSelect');
    select.innerHTML = medicineOptions.map(m =>
        `<option value="${m.medicine_id}" data-price="${m.unit_price}" data-stock="${m.stock_qty}">
            ${escapeHtml(m.name)} ${m.is_proprietary == 1 ? '⭐' : ''} (Rs. ${Number(m.unit_price).toFixed(2)}, stock: ${m.stock_qty})
        </option>`
    ).join('');
}

function onSearchInput(e) {
    const q = e.target.value.trim().toLowerCase();
    const resultsEl = document.getElementById('searchResults');

    if (q.length < 1) {
        resultsEl.innerHTML = '';
        return;
    }

    const matches = currentQueue.filter(p =>
        p.name.toLowerCase().includes(q) || (p.contact_number || '').includes(q)
    );
    renderSearchResults(matches);
}

function renderSearchResults(matches) {
    const resultsEl = document.getElementById('searchResults');

    if (matches.length === 0) {
        resultsEl.innerHTML = '<p style="color:#6b7280; font-size:13.5px;">No match in today\'s queue. Only patients Reception has sent to the queue today show up here.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Token</th><th>Name</th><th>Contact</th><th>Type</th><th></th></tr></thead><tbody>';
    matches.forEach(p => {
        html += `<tr>
            <td>#${p.token_number}</td>
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.contact_number)}</td>
            <td>${p.patient_type}</td>
            <td><button class="btn" style="padding:5px 12px; font-size:12.5px;" onclick="pickFromQueue(${p.token_id})">Select</button></td>
        </tr>`;
    });
    html += '</tbody></table>';
    resultsEl.innerHTML = html;
}

function selectPatient(patient) {
    selectedPatient = patient;
    document.getElementById('searchResults').innerHTML = '';
    document.getElementById('searchInput').value = '';
    document.getElementById('completeMsg').textContent = '';

    let banner = `<div class="card" style="background:#f0f7ff; margin-bottom:0;">
        <strong>Selected Patient:</strong> ${escapeHtml(patient.name)} (${escapeHtml(patient.contact_number)})`;
    if (patient.allergies && patient.allergies.trim() !== '') {
        banner += `<div class="allergy-alert" style="margin-top:10px;">⚠️ ALLERGY ALERT: ${escapeHtml(patient.allergies)}</div>`;
    }
    banner += `</div>`;
    document.getElementById('selectedPatientBanner').innerHTML = banner;

    document.getElementById('consultCard').style.display = 'block';
    document.getElementById('consultFeeInput').value = consultationFee.toFixed(2);
    prescriptionItems = [];
    renderPrescriptionTable();
}

function onAddMedicine() {
    const select = document.getElementById('medicineSelect');
    const option = select.selectedOptions[0];
    if (!option) return;

    const medicineId = parseInt(select.value, 10);
    const name = option.textContent.split('(')[0].replace('⭐', '').trim();
    const price = parseFloat(option.dataset.price);
    const dosage = document.getElementById('dosageInput').value.trim();
    const duration = document.getElementById('durationInput').value.trim();
    const quantity = Math.max(1, parseInt(document.getElementById('quantityInput').value, 10) || 1);

    prescriptionItems.push({ medicine_id: medicineId, name, dosage, duration, quantity, unit_price: price });

    document.getElementById('dosageInput').value = '';
    document.getElementById('durationInput').value = '';
    document.getElementById('quantityInput').value = 1;

    renderPrescriptionTable();
}

function renderPrescriptionTable() {
    const tbody = document.getElementById('prescriptionList');
    if (prescriptionItems.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:#6b7280;">No medicines added yet.</td></tr>';
        return;
    }

    tbody.innerHTML = prescriptionItems.map((item, idx) => `
        <tr>
            <td>${escapeHtml(item.name)}</td>
            <td>${escapeHtml(item.dosage || '-')}</td>
            <td>${escapeHtml(item.duration || '-')}</td>
            <td>${item.quantity}</td>
            <td>Rs. ${(item.unit_price * item.quantity).toFixed(2)}</td>
            <td><button class="btn btn-danger" style="padding:4px 10px; font-size:12px;" onclick="removeItem(${idx})">Remove</button></td>
        </tr>
    `).join('');
}

function removeItem(idx) {
    prescriptionItems.splice(idx, 1);
    renderPrescriptionTable();
}

async function onComplete() {
    if (!selectedPatient) {
        alert('Please select a patient first.');
        return;
    }

    const diagnosis = document.getElementById('diagnosisInput').value.trim();
    const msgEl = document.getElementById('completeMsg');
    msgEl.textContent = '';

    const feeInput = parseFloat(document.getElementById('consultFeeInput').value);
    const feeToSend = (!isNaN(feeInput) && feeInput >= 0) ? feeInput : consultationFee;

    const payload = {
        patient_id: selectedPatient.patient_id,
        diagnosis,
        consultation_fee: feeToSend,
        prescriptions: prescriptionItems.map(i => ({
            medicine_id: i.medicine_id,
            dosage: i.dosage,
            duration: i.duration,
            quantity: i.quantity,
        })),
    };

    try {
        const res = await fetch(`${API_BASE}/visits_create.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();

        if (!res.ok) {
            msgEl.style.color = '#dc2626';
            msgEl.textContent = data.error || 'Failed to save.';
            return;
        }

        msgEl.style.color = '#065f46';
        msgEl.style.background = '#d1fae5';
        msgEl.style.padding = '12px 16px';
        msgEl.style.borderRadius = '8px';
        msgEl.textContent = `✅ Consultation saved and sent to Pharmacy/Cashier (Visit ID: ${data.visit_id}).`;

        // Reset for next patient
        selectedPatient = null;
        prescriptionItems = [];
        document.getElementById('diagnosisInput').value = '';
        document.getElementById('consultCard').style.display = 'none';
        document.getElementById('selectedPatientBanner').innerHTML = '';
        renderPrescriptionTable();

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
