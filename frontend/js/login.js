document.getElementById('loginForm').addEventListener('submit', async function (e) {
    e.preventDefault();

    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value;
    const errorEl = document.getElementById('errorMsg');
    errorEl.textContent = '';

    try {
        const res = await fetch('../backend/api/login.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password }),
        });

        const data = await res.json();

        if (!res.ok) {
            errorEl.textContent = data.error || 'Login failed';
            return;
        }

        // Every role now lands on the same unified dashboard shell;
        // the dashboard itself shows only the sections that role can use.
        window.location.href = 'dashboard.html';

    } catch (err) {
        errorEl.textContent = 'Something went wrong. Please try again.';
        console.error(err);
    }
});
