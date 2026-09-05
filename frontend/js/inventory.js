/**
 * ============================================================
 * INVENTORY.JS
 * Powers frontend/inventory.html
 * ============================================================
 */

let currentUser = null;

document.addEventListener('DOMContentLoaded', async () => {
    currentUser = await guardPage(['Cashier', 'Admin']);
    if (!currentUser) return;

    document.getElementById('refreshBtn').addEventListener('click', loadInventory);
    document.getElementById('searchBox').addEventListener('input', debounce(loadInventory, 350));
    document.getElementById('addMedicineForm').addEventListener('submit', handleAddMedicine);

    loadInventory();
});

function debounce(fn, delay) {
    let timer;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), delay);
    };
}

async function loadInventory() {
    const q = document.getElementById('searchBox').value.trim();
    const tbody = document.getElementById('inventoryTableBody');

    try {
        const res = await fetch(`${API_BASE}/inventory_list.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();

        if (!res.ok) {
            tbody.innerHTML = `<tr><td colspan="9">Error: ${data.error || 'Failed to load'}</td></tr>`;
            return;
        }

        document.getElementById('lowStockThreshold').textContent = data.low_stock_threshold;

        if (!data.medicines.length) {
            tbody.innerHTML = `<tr><td colspan="9">No medicines found.</td></tr>`;
            return;
        }

        tbody.innerHTML = data.medicines.map(renderRow).join('');
        attachRowHandlers();

    } catch (err) {
        console.error(err);
        tbody.innerHTML = `<tr><td colspan="9">Failed to load inventory.</td></tr>`;
    }
}

function renderRow(med) {
    const rowClasses = [];
    if (med.low_stock) rowClasses.push('low-stock-row');
    if (med.expiring_soon) rowClasses.push('expiring-row');

    const stockDiffHtml = formatDiff(med.stock_diff, '');
    const priceDiffHtml = formatDiff(med.price_diff, 'Rs.');

    const expiryText = med.expiry_date
        ? `${med.expiry_date}${med.expiring_soon ? ' ⚠️' : ''}`
        : '-';

    const proprietaryBadge = med.is_proprietary ? ' ⭐' : '';

    const costText = med.cost_price !== null
        ? `Rs. ${med.cost_price.toFixed(2)}`
        : `<span class="diff-down">not set</span>`;

    return `
        <tr class="${rowClasses.join(' ')}" data-id="${med.medicine_id}">
            <td>${escapeHtml(med.name)}${proprietaryBadge}</td>
            <td>${escapeHtml(med.category || '-')}</td>
            <td>
                <span class="stock-display">${med.stock_qty}</span>
                ${med.low_stock ? '<span class="badge badge-danger">Low</span>' : ''}
            </td>
            <td>${stockDiffHtml}</td>
            <td><span class="price-display">${med.unit_price.toFixed(2)}</span></td>
            <td>${priceDiffHtml}</td>
            <td>${costText}</td>
            <td>${expiryText}</td>
            <td>
                <div class="row-actions">
                    <button class="btn btn-secondary restock-btn" data-id="${med.medicine_id}">Restock</button>
                    <button class="btn btn-secondary price-btn" data-id="${med.medicine_id}" data-price="${med.unit_price}">Update Price</button>
                    <button class="btn btn-secondary stocktake-btn" data-id="${med.medicine_id}" data-qty="${med.stock_qty}">Correct Qty</button>
                    <button class="btn btn-secondary cost-btn" data-id="${med.medicine_id}" data-cost="${med.cost_price ?? ''}">Set Cost</button>
                </div>
            </td>
        </tr>
    `;
}

function formatDiff(diff, unit) {
    if (diff === null || diff === undefined) {
        return `<span class="diff-flat">no data</span>`;
    }
    if (diff > 0) {
        return `<span class="diff-up">▲ ${unit}${diff}</span>`;
    }
    if (diff < 0) {
        return `<span class="diff-down">▼ ${unit}${Math.abs(diff)}</span>`;
    }
    return `<span class="diff-flat">— no change</span>`;
}

function attachRowHandlers() {
    document.querySelectorAll('.restock-btn').forEach(btn => {
        btn.addEventListener('click', () => handleRestock(btn.dataset.id));
    });
    document.querySelectorAll('.price-btn').forEach(btn => {
        btn.addEventListener('click', () => handlePriceUpdate(btn.dataset.id, btn.dataset.price));
    });
    document.querySelectorAll('.stocktake-btn').forEach(btn => {
        btn.addEventListener('click', () => handleStockCorrection(btn.dataset.id, btn.dataset.qty));
    });
    document.querySelectorAll('.cost-btn').forEach(btn => {
        btn.addEventListener('click', () => handleCostUpdate(btn.dataset.id, btn.dataset.cost));
    });
}

async function handleRestock(medicineId) {
    const qty = prompt('New delivery quantity to ADD to current stock:');
    if (qty === null || qty.trim() === '') return;
    const adjustment = parseInt(qty, 10);
    if (isNaN(adjustment) || adjustment <= 0) {
        alert('Please enter a positive whole number.');
        return;
    }

    await postJson(`${API_BASE}/inventory_update_stock.php`, {
        medicine_id: parseInt(medicineId, 10),
        adjustment,
        mode: 'add',
    });
    loadInventory();
}

async function handleStockCorrection(medicineId, currentQty) {
    const qty = prompt(`Correct stock-take quantity (currently ${currentQty}):`, currentQty);
    if (qty === null || qty.trim() === '') return;
    const newQty = parseInt(qty, 10);
    if (isNaN(newQty) || newQty < 0) {
        alert('Please enter a valid whole number.');
        return;
    }

    await postJson(`${API_BASE}/inventory_update_stock.php`, {
        medicine_id: parseInt(medicineId, 10),
        adjustment: newQty,
        mode: 'set',
    });
    loadInventory();
}

async function handleCostUpdate(medicineId, currentCost) {
    const label = currentCost ? `current: Rs. ${currentCost}` : 'not set yet';
    const cost = prompt(`Wholesale cost price for this medicine (${label}).\nUsed to calculate profit margin on the Analytics dashboard.`, currentCost || '');
    if (cost === null || cost.trim() === '') return;
    const newCost = parseFloat(cost);
    if (isNaN(newCost) || newCost < 0) {
        alert('Please enter a valid cost price.');
        return;
    }

    await postJson(`${API_BASE}/medicine_update_cost.php`, {
        medicine_id: parseInt(medicineId, 10),
        cost_price: newCost,
    });
    loadInventory();
}

async function handlePriceUpdate(medicineId, currentPrice) {
    const price = prompt(`New price for this medicine (current: Rs. ${currentPrice}).\nThis applies immediately clinic-wide.`, currentPrice);
    if (price === null || price.trim() === '') return;
    const newPrice = parseFloat(price);
    if (isNaN(newPrice) || newPrice < 0) {
        alert('Please enter a valid price.');
        return;
    }

    await postJson(`${API_BASE}/inventory_update_price.php`, {
        medicine_id: parseInt(medicineId, 10),
        new_price: newPrice,
    });
    loadInventory();
}

async function handleAddMedicine(e) {
    e.preventDefault();
    const msgEl = document.getElementById('addMsg');
    msgEl.textContent = '';
    msgEl.style.color = '';

    const payload = {
        name: document.getElementById('newName').value.trim(),
        category: document.getElementById('newCategory').value.trim(),
        is_proprietary: document.getElementById('newProprietary').checked,
        unit_price: parseFloat(document.getElementById('newPrice').value),
        stock_qty: parseInt(document.getElementById('newStock').value, 10) || 0,
        expiry_date: document.getElementById('newExpiry').value || null,
    };

    if (!payload.name || !payload.unit_price || payload.unit_price <= 0) {
        msgEl.textContent = 'Medicine name and a valid price are required.';
        msgEl.style.color = '#dc2626';
        return;
    }

    try {
        const res = await fetch(`${API_BASE}/inventory_add_medicine.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload),
        });
        const data = await res.json();
        if (!res.ok) {
            msgEl.textContent = data.error || 'Failed to add medicine.';
            msgEl.style.color = '#dc2626';
            return;
        }
        msgEl.textContent = `✅ "${payload.name}" added to inventory.`;
        msgEl.style.color = '#065f46';
        document.getElementById('addMedicineForm').reset();
        loadInventory();
    } catch (err) {
        console.error(err);
        msgEl.textContent = 'Failed to add medicine.';
        msgEl.style.color = '#dc2626';
    }
}

async function postJson(url, body) {
    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Action failed.');
        }
        return data;
    } catch (err) {
        console.error(err);
        alert('Request failed.');
    }
}

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}
