/**
 * ============================================================
 * CASHIER.JS
 * Handles: pending queue, billing panel, item removal, adding
 * proprietary products for Regular/Monthly patients, and
 * finalizing/printing the invoice.
 * ============================================================
 */

let currentVisit = null;

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Cashier', 'Admin']);
    if (!user) return;

    await loadQueue();

    document.getElementById('addProprietaryBtn').addEventListener('click', onAddProprietary);
    document.getElementById('finalizeBtn').addEventListener('click', onFinalize);
});

async function loadQueue() {
    const res = await fetch(`${API_BASE}/cashier_queue.php`);
    const data = await res.json();
    renderQueue(data.queue || []);
}

function renderQueue(queue) {
    const el = document.getElementById('queueList');
    if (queue.length === 0) {
        el.innerHTML = '<p style="color:#6b7280; font-size:13.5px;">No pending prescriptions right now.</p>';
        return;
    }

    let html = '<table><thead><tr><th>Time</th><th>Patient</th><th>Doctor</th><th>Type</th><th></th></tr></thead><tbody>';
    queue.forEach(v => {
        html += `<tr>
            <td>${v.visit_date}</td>
            <td>${escapeHtml(v.patient_name)}</td>
            <td>${escapeHtml(v.doctor_name)}</td>
            <td>${badgeForType(v.patient_type)}</td>
            <td><button class="btn" style="padding:5px 12px; font-size:12.5px;" onclick="openVisit(${v.visit_id})">Open</button></td>
        </tr>`;
    });
    html += '</tbody></table>';
    el.innerHTML = html;
}

function badgeForType(type) {
    if (type === 'Regular') return '<span class="badge badge-info">Regular</span>';
    if (type === 'Monthly') return '<span class="badge badge-warning">Monthly</span>';
    return '<span class="badge">Normal</span>';
}

async function openVisit(visitId) {
    const res = await fetch(`${API_BASE}/cashier_visit_details.php?visit_id=${visitId}`);
    if (!res.ok) {
        alert('Could not load visit.');
        return;
    }
    const data = await res.json();
    currentVisit = data.visit;
    renderBilling(data.visit, data.proprietary_products);
}

function renderBilling(visit, proprietaryProducts) {
    document.getElementById('billingCard').style.display = 'block';
    document.getElementById('billingMsg').textContent = '';

    const allergyEl = document.getElementById('allergyBanner');
    allergyEl.innerHTML = (visit.allergies && visit.allergies.trim() !== '')
        ? `<div class="allergy-alert">⚠️ ALLERGY ALERT: ${escapeHtml(visit.allergies)}</div>`
        : '';

    document.getElementById('patientMeta').innerHTML = `
        <strong>${escapeHtml(visit.patient_name)}</strong> (${escapeHtml(visit.contact_number)}) — ${badgeForType(visit.patient_type)}<br>
        <span style="font-size:13px; color:#4b5563;">Doctor: ${escapeHtml(visit.doctor_name)} | Diagnosis: ${escapeHtml(visit.diagnosis || '-')}</span>
    `;

    renderItemsTable(visit.prescriptions);

    // Show proprietary product quick-add section for Regular/Monthly patients
    const propSection = document.getElementById('proprietarySection');
    if (['Regular', 'Monthly'].includes(visit.patient_type) && proprietaryProducts.length > 0) {
        propSection.style.display = 'block';
        const select = document.getElementById('proprietarySelect');
        select.innerHTML = proprietaryProducts.map(p =>
            `<option value="${p.medicine_id}" data-price="${p.unit_price}">${escapeHtml(p.name)} (Rs. ${Number(p.unit_price).toFixed(2)}, stock: ${p.stock_qty})</option>`
        ).join('');
    } else {
        propSection.style.display = 'none';
    }

    updateTotals(visit);
}

function renderItemsTable(prescriptions) {
    const tbody = document.getElementById('itemsList');
    if (!prescriptions || prescriptions.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="color:#6b7280;">No medicines prescribed.</td></tr>';
        return;
    }

    tbody.innerHTML = prescriptions.map(item => {
        const removed = item.removed_by_cashier == 1;
        const rowStyle = removed ? 'opacity:0.5; text-decoration:line-through;' : '';
        return `
        <tr style="${rowStyle}">
            <td>${escapeHtml(item.medicine_name)} ${item.is_proprietary == 1 ? '⭐' : ''}</td>
            <td>${escapeHtml(item.dosage || '-')}</td>
            <td>${escapeHtml(item.duration || '-')}</td>
            <td>${item.quantity}</td>
            <td>Rs. ${(item.unit_price * item.quantity).toFixed(2)}</td>
            <td>
                <button class="btn ${removed ? 'btn-secondary' : 'btn-danger'}" style="padding:4px 10px; font-size:12px;"
                    onclick="toggleItem(${item.prescription_id}, ${removed ? 0 : 1})">
                    ${removed ? 'Restore' : 'Remove'}
                </button>
            </td>
        </tr>`;
    }).join('');
}

async function toggleItem(prescriptionId, removedFlag) {
    await fetch(`${API_BASE}/cashier_toggle_item.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ prescription_id: prescriptionId, removed: !!removedFlag }),
    });
    // Reload visit details to refresh totals
    await openVisit(currentVisit.visit_id);
}

async function onAddProprietary() {
    const select = document.getElementById('proprietarySelect');
    if (!select.value) return;
    const medicineId = parseInt(select.value, 10);
    const qty = Math.max(1, parseInt(document.getElementById('proprietaryQty').value, 10) || 1);

    await fetch(`${API_BASE}/cashier_add_item.php`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ visit_id: currentVisit.visit_id, medicine_id: medicineId, quantity: qty }),
    });

    document.getElementById('proprietaryQty').value = 1;
    await openVisit(currentVisit.visit_id);
}

function updateTotals(visit) {
    const fee = parseFloat(visit.consultation_fee);
    const medTotal = (visit.prescriptions || [])
        .filter(i => i.removed_by_cashier == 0)
        .reduce((sum, i) => sum + (i.unit_price * i.quantity), 0);

    document.getElementById('feeDisplay').textContent = `Rs. ${fee.toFixed(2)}`;
    document.getElementById('medTotalDisplay').textContent = `Rs. ${medTotal.toFixed(2)}`;
    document.getElementById('grandTotalDisplay').textContent = `Rs. ${(fee + medTotal).toFixed(2)}`;
}

async function onFinalize() {
    if (!currentVisit) return;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const msgEl = document.getElementById('billingMsg');
    msgEl.textContent = '';

    try {
        const res = await fetch(`${API_BASE}/cashier_finalize.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ visit_id: currentVisit.visit_id, payment_method: paymentMethod }),
        });
        const data = await res.json();

        if (!res.ok) {
            msgEl.style.color = '#dc2626';
            msgEl.textContent = data.error || 'Could not finalize invoice.';
            return;
        }

        // Open printable invoice in a new tab
        window.open(`${API_BASE}/invoice_print.php?invoice_id=${data.invoice_id}`, '_blank');

        msgEl.style.color = '#065f46';
        msgEl.textContent = `✅ Invoice #${data.invoice_id} finalized (Rs. ${Number(data.grand_total).toFixed(2)}).`;

        document.getElementById('billingCard').style.display = 'none';
        currentVisit = null;
        await loadQueue();

    } catch (err) {
        msgEl.style.color = '#dc2626';
        msgEl.textContent = 'Something went wrong.';
        console.error(err);
    }
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}
