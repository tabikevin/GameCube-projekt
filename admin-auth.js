const _pathParts = window.location.pathname.split('/');
const _projectFolder = _pathParts[1];
const ADMIN_API = '/' + _projectFolder + '/api/admin';

async function checkAdminSession() {
    try {
        const res = await fetch(ADMIN_API + '/admin-check.php');
        const data = await res.json();
        return data.admin === true;
    } catch {
        return false;
    }
}

function initAdminLogin() {
    const form = document.getElementById('adminLoginForm');
    const errorDiv = document.getElementById('adminError');
    if (!form) return;

    checkAdminSession().then(ok => {
        if (ok) window.location.href = 'admin.html';
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        errorDiv.style.display = 'none';

        const password = document.getElementById('adminPassword').value;

        try {
            const res = await fetch(ADMIN_API + '/admin-login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const data = await res.json();

            if (data.success) {
                window.location.href = 'admin.html';
            } else {
                errorDiv.textContent = data.error || 'Hibás jelszó!';
                errorDiv.style.display = 'block';
            }
        } catch {
            errorDiv.textContent = 'Hiba történt, próbáld újra!';
            errorDiv.style.display = 'block';
        }
    });
}

async function adminLogout() {
    try { await fetch(ADMIN_API + '/admin-logout.php'); } catch {}
    window.location.href = 'admin-login.html';
}
