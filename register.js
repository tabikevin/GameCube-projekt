document.addEventListener('DOMContentLoaded', () => {
    if (isLoggedIn()) {
        window.location.href = 'index.html';
        return;
    }

    const form = document.getElementById('registerForm');
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessages();

        const formData = new FormData(form);
        const data = {
            full_name: formData.get('full_name'),
            username: formData.get('username'),
            email: formData.get('email'),
            phone: formData.get('phone'),
            password: formData.get('password'),
            password_confirm: formData.get('password_confirm')
        };

        if (data.password !== data.password_confirm) {
            showErrorMessage('A két jelszó nem egyezik');
            return;
        }

        if (data.password.length < 6) {
            showErrorMessage('A jelszó legalább 6 karakter legyen');
            return;
        }

        if (data.username.length < 3) {
            showErrorMessage('A felhasználónév legalább 3 karakter legyen');
            return;
        }

        try {
            const response = await fetch(`${API_BASE}/register.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = 'login.html?registered=true';
            } else {
                if (result.errors && Array.isArray(result.errors)) {
                    showErrorMessage(result.errors.join('<br>'));
                } else {
                    showErrorMessage(result.error || 'Hiba történt a regisztráció során');
                }
            }
        } catch (error) {
            showErrorMessage('Hiba történt a regisztráció során');
        }
    });
});

function showErrorMessage(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.innerHTML = message;
        errorDiv.style.display = 'block';
    }
}

function hideMessages() {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) errorDiv.style.display = 'none';
}
