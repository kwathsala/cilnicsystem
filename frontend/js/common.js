/**
 * ============================================================
 * COMMON.JS
 * Shared across all internal pages: auth guard, logout, user label.
 * Include this BEFORE the page-specific script.
 * ============================================================
 */

const API_BASE = '../backend/api';

/**
 * Redirects to the login page. If this script is running inside the
 * dashboard's content iframe, redirects the TOP window instead, so
 * the login form takes over the whole tab rather than appearing
 * squeezed inside the small content pane.
 */
function goToLogin() {
    const target = window.top !== window.self ? window.top : window;
    target.location.href = 'login.html';
}

/**
 * Checks the user is logged in and has an allowed role.
 * Redirects to login.html if not authenticated or not authorized.
 * Returns the user object on success.
 */
async function guardPage(allowedRoles = []) {
    try {
        const res = await fetch(`${API_BASE}/whoami.php`);
        if (!res.ok) {
            goToLogin();
            return null;
        }
        const data = await res.json();
        const user = data.user;

        if (allowedRoles.length && !allowedRoles.includes(user.role)) {
            alert('You do not have permission to view this page.');
            goToLogin();
            return null;
        }

        const label = document.getElementById('userLabel');
        if (label) label.textContent = `${user.username} (${user.role})`;

        return user;
    } catch (err) {
        console.error(err);
        goToLogin();
        return null;
    }
}

function setupLogout() {
    const btn = document.getElementById('logoutBtn');
    if (!btn) return;
    btn.addEventListener('click', async () => {
        await fetch(`${API_BASE}/logout.php`, { method: 'POST' });
        goToLogin();
    });
}

document.addEventListener('DOMContentLoaded', setupLogout);
