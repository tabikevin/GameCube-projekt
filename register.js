document.addEventListener('DOMContentLoaded', () => {
    if (isLoggedIn()) {
        window.location.href = 'index.html';
        return;
    }

    const form = document.getElementById('registerForm');
    if (!form) return;

    // Szerepkör kártya kiválasztás
    const roleCards = document.querySelectorAll('.gc-role-card');
    roleCards.forEach(card => {
        card.addEventListener('click', () => {
            roleCards.forEach(c => c.classList.remove('active'));
            card.classList.add('active');
            card.querySelector('input[type="radio"]').checked = true;
        });
    });

    
    const toggleBtn = document.getElementById('togglePw');
    const pwInput = document.getElementById('password');
    if (toggleBtn && pwInput) {
        toggleBtn.addEventListener('click', () => {
            const isHidden = pwInput.type === 'password';
            pwInput.type = isHidden ? 'text' : 'password';
            document.getElementById('togglePwIcon').className = isHidden ? 'bi bi-eye-slash' : 'bi bi-eye';
        });
    }

    if (pwInput) pwInput.addEventListener('input', updatePasswordStrength);

    
    ['full_name','username','email','password','password_confirm'].forEach(id => {
        document.getElementById(id)?.addEventListener('blur', () => validateField(id));
    });

    
    document.getElementById('password_confirm')?.addEventListener('input', () => {
        const pw = document.getElementById('password')?.value || '';
        const conf = document.getElementById('password_confirm')?.value || '';
        if (conf.length > 0) validateField('password_confirm');
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideMessages();

        let valid = true;
        ['full_name','username','email','password','password_confirm'].forEach(f => {
            if (!validateField(f)) valid = false;
        });

        const terms = document.getElementById('acceptTerms');
        if (terms && !terms.checked) {
            showFieldError('err_terms', true);
            terms.closest('.form-check').style.borderColor = 'rgba(239,68,68,0.6)';
            valid = false;
        } else if (terms) {
            showFieldError('err_terms', false);
        }

        if (!valid) {
            const firstInvalid = form.querySelector('.is-invalid');
            if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
        }

        const formData = new FormData(form);
        const selectedRole = form.querySelector('input[name="role"]:checked');
        const data = {
            full_name: formData.get('full_name').trim(),
            username: formData.get('username').trim(),
            email: formData.get('email').trim(),
            phone: formData.get('phone').trim(),
            password: formData.get('password'),
            password_confirm: formData.get('password_confirm'),
            role: selectedRole ? selectedRole.value : 'user'
        };

        const btn = document.getElementById('registerBtn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Regisztrálás...'; }

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
                const msg = (result.errors && Array.isArray(result.errors))
                    ? result.errors.map(e => { const d = document.createElement('div'); d.textContent = e; return d.innerHTML; }).join('<br>')
                    : (result.error || 'Hiba történt a regisztráció során.');
                showErrorMessage(msg);
                if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-plus me-2"></i>Fiók létrehozása'; }
            }
        } catch (error) {
            showErrorMessage('Hálózati hiba. Kérjük, próbáld újra.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-person-plus me-2"></i>Fiók létrehozása'; }
        }
    });

    document.getElementById('acceptTerms')?.addEventListener('change', function() {
        showFieldError('err_terms', false);
        this.closest('.form-check').style.borderColor = this.checked ? 'rgba(34,197,94,0.4)' : 'rgba(168,85,247,0.2)';
    });
});

function calcPasswordStrength(pw) {
    let score = 0;
    const rules = {
        len:     pw.length >= 8,
        upper:   /[A-Z]/.test(pw),
        lower:   /[a-z]/.test(pw),
        num:     /[0-9]/.test(pw),
        special: /[!@#$%^&*()\-_=+\[\]{};:'",.<>?\/`~\\|]/.test(pw)
    };
    Object.values(rules).forEach(r => { if (r) score++; });
    return { score, rules };
}

function updatePasswordStrength() {
    const pw = document.getElementById('password')?.value || '';
    const { score, rules } = calcPasswordStrength(pw);
    const strengthEl = document.getElementById('pwStrength');
    const textEl = document.getElementById('pwStrengthText');
    if (!strengthEl || !textEl) return;

    Object.entries(rules).forEach(([key, ok]) => {
        document.getElementById('rule_' + key)?.classList.toggle('ok', ok);
    });

    const labels = ['', 'Nagyon gyenge', 'Gyenge', 'Közepes', 'Erős', 'Nagyon erős'];
    const colors = ['#64748b', '#ef4444', '#f97316', '#eab308', '#84cc16', '#22c55e'];
    strengthEl.className = 'gc-password-strength strength-' + score;
    textEl.textContent = pw.length > 0 ? labels[score] : 'Erősség jelző';
    textEl.style.color = pw.length > 0 ? colors[score] : '#64748b';
}

function validateField(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return true;
    const val = el.value.trim();
    let valid = true;

    switch (fieldId) {
        case 'full_name':
            valid = val.length >= 3 && val.split(/\s+/).filter(w => w.length > 0).length >= 2;
            break;
        case 'username':
            valid = val.length >= 3 && /^[a-zA-Z0-9_áéíóöőüűÁÉÍÓÖŐÜŰ]+$/.test(val);
            break;
        case 'email':
            valid = /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(val);
            break;
        case 'password':
            valid = calcPasswordStrength(el.value).score >= 4;
            break;
        case 'password_confirm':
            const pw = document.getElementById('password')?.value || '';
            valid = el.value === pw && el.value.length > 0;
            break;
    }

    el.classList.toggle('is-invalid', !valid);
    el.classList.toggle('is-valid', valid && el.value.length > 0);
    showFieldError('err_' + fieldId, !valid);
    return valid;
}

function showFieldError(errId, show) {
    document.getElementById(errId)?.classList.toggle('show', show);
}

function showErrorMessage(message) {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) {
        errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + message;
        errorDiv.style.display = 'block';
        errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

function hideMessages() {
    const errorDiv = document.getElementById('errorMessage');
    if (errorDiv) errorDiv.style.display = 'none';
}
