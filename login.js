document.addEventListener('DOMContentLoaded', () => {
    if (isLoggedIn()) {
        window.location.href = 'index.html';
        return;
    }

    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('registered') === 'true') {
        showSuccessMessage('Sikeres regisztráció! Most már bejelentkezhetsz.');
    }

    const form = document.getElementById('loginForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessages();

        const formData = new FormData(form);
        const data = {
            email_or_username: formData.get('email_or_username'),
            password: formData.get('password')
        };

        try {
            const response = await fetch(`${API_BASE}/login.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                setToken(result.token);
                setUser(result.user);
                const redirectTo = new URLSearchParams(window.location.search).get('redirect');
                window.location.href = redirectTo || 'index.html';
            } else {
                showErrorMessage(result.error || 'Hibás bejelentkezési adatok');
            }
        } catch (error) {
            showErrorMessage('Hiba történt a bejelentkezés során');
        }
    });
});

function showSuccessMessage(message) {
    const successDiv = document.getElementById('successMessage');
    if (successDiv) {
        successDiv.textContent = message;
        successDiv.style.display = 'block';
    }
}

function showErrorMessage(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
    }
}

function hideMessages() {
    const successDiv = document.getElementById('successMessage');
    const errorDiv = document.getElementById('errorMessage');
    if (successDiv) successDiv.style.display = 'none';
    if (errorDiv) errorDiv.style.display = 'none';
}
