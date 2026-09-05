/**
 * ============================================================
 * DASHBOARD.JS
 * Single entry point after login. Shows a sidebar of sections
 * filtered by the logged-in user's role, and loads the selected
 * section's existing page inside an iframe - no business logic
 * duplicated, every section is still the same page/API it always was.
 * ============================================================
 */

const ALL_SECTIONS = [
    { id: 'reception', label: 'Reception', icon: '🧾', src: 'reception.html', roles: ['Reception', 'Admin'] },
    { id: 'doctor', label: "Doctor's Consultation", icon: '🩺', src: 'doctor.html', roles: ['Doctor', 'Admin'] },
    { id: 'cashier', label: 'Cashier & Pharmacy', icon: '💊', src: 'cashier.html', roles: ['Cashier', 'Admin'] },
    { id: 'inventory', label: 'Inventory', icon: '📦', src: 'inventory.html', roles: ['Cashier', 'Admin'] },
    { id: 'analytics', label: 'Financial Analytics', icon: '📊', src: 'analytics.html', roles: ['Doctor', 'Admin'] },
    { id: 'history', label: 'Visit History', icon: '📋', src: 'history.html', roles: ['Doctor', 'Cashier', 'Admin'] },
];

document.addEventListener('DOMContentLoaded', async () => {
    const user = await guardPage(['Reception', 'Doctor', 'Cashier', 'Admin']);
    if (!user) return;

    const sections = ALL_SECTIONS.filter(s => s.roles.includes(user.role));
    const navList = document.getElementById('navList');
    const frame = document.getElementById('contentFrame');

    if (!sections.length) {
        navList.innerHTML = '';
        frame.remove();
        document.querySelector('.content-area').innerHTML =
            '<div class="empty-state">No sections available for your role. Contact an admin.</div>';
        return;
    }

    navList.innerHTML = sections.map(s => `
        <li>
            <button data-id="${s.id}" data-src="${s.src}">
                <span>${s.icon}</span><span>${s.label}</span>
            </button>
        </li>
    `).join('');

    navList.querySelectorAll('button').forEach(btn => {
        btn.addEventListener('click', () => activateSection(btn));
    });

    // Load the first available section by default.
    activateSection(navList.querySelector('button'));
});

function activateSection(btn) {
    document.querySelectorAll('.nav-list button').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('contentFrame').src = btn.dataset.src;
}
