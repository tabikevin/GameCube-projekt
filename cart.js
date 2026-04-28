let cart = [];

document.addEventListener('DOMContentLoaded', function() {
    const yearSpan = document.getElementById('yearSpan');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

    const zipInput = document.getElementById('billing_zip');
    if (zipInput) {
        zipInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '').substring(0, 10);
        });
        zipInput.addEventListener('keypress', function(e) {
            if (!/[0-9]/.test(e.key)) e.preventDefault();
        });
    }

    if (!isLoggedIn()) {
        const warn = document.getElementById('notLoggedInWarning');
        const cartContent = document.getElementById('cartContent');
        const emptyCart = document.getElementById('emptyCart');
        if (warn) warn.style.display = 'block';
        if (cartContent) cartContent.style.display = 'none';
        if (emptyCart) emptyCart.style.display = 'none';
        return;
    }
    loadCart();
});

async function loadCart() {
    try {
        const token = getToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;
        const response = await fetch(API_BASE + '/cart.php', { headers });
        const data = await response.json();
        
        if (data.success) {
            cart = data.cart || [];
            displayCart();
            updateCartSummary(data.total);
        } else {
            showError('Hiba a kosár betöltése során');
        }
    } catch (error) {
        showError('Hiba történt a kosár betöltése során');
    }
}

function displayCart() {
    const cartBody = document.getElementById('cartBody');
    const emptyCart = document.getElementById('emptyCart');
    const cartContent = document.getElementById('cartContent');
    
    if (!cartBody) return;
    
    if (cart.length === 0) {
        if (emptyCart) emptyCart.style.display = 'block';
        if (cartContent) cartContent.style.display = 'none';
        return;
    }
    
    if (emptyCart) emptyCart.style.display = 'none';
    if (cartContent) cartContent.style.display = 'block';
    
    const platformBadgeClass = {
        'pc': 'bg-warning text-dark',
        'ps': 'bg-primary',
        'xbox': 'bg-success',
        'switch': 'bg-danger'
    };
    
    const platformLabel = {
        'pc': 'PC',
        'ps': 'PS',
        'xbox': 'Xbox',
        'switch': 'Switch'
    };
    
    cartBody.innerHTML = cart.map(item => {
        const elerheto = item.available_keys ?? Infinity;
        const maxElert = item.quantity >= elerheto;

        const arCella = item.price_mixed
            ? '<span class="text-warning" title="A kulcsok eltérő áron kerülnek kiosztásra">vegyes ár</span>'
            : formatPrice(item.price) + ' Ft';

        let figyelmeztetesek = '';
        if (item.price_mixed) {
            figyelmeztetesek += '<br><small class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Vegyes árak – az összeg a tényleges kulcsárak összege</small>';
        }
        if (maxElert && elerheto !== Infinity) {
            figyelmeztetesek += '<br><small class="text-warning"><i class="bi bi-exclamation-triangle-fill me-1"></i>Maximum elérhető mennyiség (' + elerheto + ' db)</small>';
        }

        return '<tr>' +
            '<td>' + escapeHtml(item.name) + figyelmeztetesek + '</td>' +
            '<td><span class="badge ' + (platformBadgeClass[item.platform] || 'bg-secondary') + '">' + (platformLabel[item.platform] || 'PC') + '</span></td>' +
            '<td>' + arCella + '</td>' +
            '<td><div class="d-flex align-items-center gap-2">' +
                '<button onclick="updateQuantity(' + item.id + ', ' + (item.quantity - 1) + ')" class="btn btn-sm btn-outline-light"' + (item.quantity <= 1 ? ' disabled' : '') + '><i class="bi bi-dash"></i></button>' +
                '<span class="mx-2">' + item.quantity + '</span>' +
                '<button onclick="updateQuantity(' + item.id + ', ' + (item.quantity + 1) + ')" class="btn btn-sm btn-outline-light"' + (maxElert ? ' disabled title="Nincs több elérhető kulcs"' : '') + '><i class="bi bi-plus"></i></button>' +
            '</div></td>' +
            '<td><strong>' + formatPrice(item.subtotal) + ' Ft</strong></td>' +
            '<td><button onclick="removeItem(' + item.id + ')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>' +
        '</tr>';
    }).join('');
}

function updateCartSummary(total) {
    const subtotalAmount = document.getElementById('subtotalAmount');
    const totalAmount = document.getElementById('totalAmount');
    
    if (subtotalAmount) subtotalAmount.textContent = formatPrice(total);
    if (totalAmount) totalAmount.textContent = formatPrice(total);
}

async function updateQuantity(productId, newQuantity) {
    if (newQuantity < 1) {
        await removeItem(productId);
        return;
    }
    
    try {
        const updates = {};
        updates[productId] = newQuantity;
        
        const response = await fetch(API_BASE + '/cart.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', ...(getToken() ? {'Authorization': 'Bearer ' + getToken()} : {}) },
            body: JSON.stringify({ items: updates })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            updateCartBadge();
        } else {
            showError('Hiba a mennyiség frissítése során');
        }
    } catch (error) {
        showError('Hiba történt a mennyiség frissítése során');
    }
}

async function removeItem(productId) {
    try {
        const updates = {};
        updates[productId] = 0;
        
        const response = await fetch(API_BASE + '/cart.php', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json', ...(getToken() ? {'Authorization': 'Bearer ' + getToken()} : {}) },
            body: JSON.stringify({ items: updates })
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            updateCartBadge();
            showSuccess('Termék eltávolítva a kosárból');
        } else {
            showError('Hiba a termék eltávolítása során');
        }
    } catch (error) {
        showError('Hiba történt a termék eltávolítása során');
    }
}

async function clearCart() {
    if (!confirm('Biztosan ki szeretnéd üríteni a kosarad?')) {
        return;
    }
    
    try {
        const response = await fetch(API_BASE + '/cart.php', {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            await loadCart();
            updateCartBadge();
            showSuccess('Kosár kiürítve');
        } else {
            showError('Hiba a kosár ürítése során');
        }
    } catch (error) {
        showError('Hiba történt a kosár ürítése során');
    }
}

function proceedToCheckout() {
    if (!isLoggedIn()) {
        alert('A fizetéshez be kell jelentkezned!');
        window.location.href = 'login.html?redirect=cart.html';
        return;
    }
    
    if (cart.length === 0) {
        alert('A kosarad üres!');
        return;
    }
    
    const checkoutSection = document.getElementById('checkoutSection');
    if (checkoutSection) {
        checkoutSection.style.display = 'block';
        checkoutSection.scrollIntoView({ behavior: 'smooth' });
    }
}

document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    if (checkoutForm) {
        
        ['billing_name','billing_address','billing_city','billing_zip','billing_country'].forEach(id => {
            document.getElementById(id)?.addEventListener('blur', () => validateBillingField(id));
            document.getElementById(id)?.addEventListener('input', () => {
                const el = document.getElementById(id);
                if (el && el.classList.contains('is-invalid')) validateBillingField(id);
            });
        });

        checkoutForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const token = localStorage.getItem('token');
            
            if (!token) {
                alert('Be kell jelentkezned a rendelés leadásához!');
                window.location.href = 'login.html?redirect=cart.html';
                return;
            }
            
            if (cart.length === 0) {
                showError('A kosarad üres!');
                return;
            }

            const billingFields = ['billing_name','billing_address','billing_city','billing_zip','billing_country'];
            let billingValid = true;
            billingFields.forEach(id => { if (!validateBillingField(id)) billingValid = false; });

            if (!billingValid) {
                const firstInvalid = checkoutForm.querySelector('.is-invalid');
                if (firstInvalid) firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return;
            }
            
            const formData = new FormData(checkoutForm);
            
            const checkoutData = {
                billing_name: formData.get('billing_name').trim(),
                billing_address: formData.get('billing_address').trim(),
                billing_city: formData.get('billing_city').trim(),
                billing_zip: formData.get('billing_zip').trim(),
                billing_country: formData.get('billing_country').trim(),
                billing_tax_number: formData.get('billing_tax_number')?.trim() || '',
                payment_method: formData.get('payment_method'),
                _token: token
            };
            
            try {
                const response = await fetch(API_BASE + '/checkout.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer ' + token,
                        'X-Authorization': 'Bearer ' + token
                    },
                    body: JSON.stringify(checkoutData)
                });
                
                const data = await response.json();
                
                if (data.success) {
                    const msg = data.status === 'pending'
                        ? 'Rendelés leadva! (#' + data.order_id + ')\nAz admin jóváhagyása után aktiváljuk a kulcsokat.'
                        : 'Sikeres rendelés! Rendelés száma: #' + data.order_id;
                    alert(msg);
                    const token2 = getToken();
                    const h2 = { 'Content-Type': 'application/json' };
                    if (token2) h2['Authorization'] = 'Bearer ' + token2;
                    await fetch(API_BASE + '/cart.php', { method: 'DELETE', headers: h2 });
                    window.location.href = 'profile.html';
                } else {
                    const errorMsg = data.error || (data.errors ? data.errors.join(', ') : 'Hiba történt a rendelés leadása során');
                    showError(errorMsg);
                }
            } catch (error) {
                console.error('Checkout error:', error);
                showError('Hiba történt a rendelés leadása során');
            }
        });
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function formatPrice(price) {
    return new Intl.NumberFormat('hu-HU').format(price);
}

function showSuccess(message) {
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastHtml = '<div class="toast align-items-center text-bg-success border-0" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi bi-check-circle-fill me-2"></i>' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
    
    const temp = document.createElement('div');
    temp.innerHTML = toastHtml;
    const toastElement = temp.firstElementChild;
    toastContainer.appendChild(toastElement);
    
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}

function showError(message) {
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => toast.remove());
    
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastHtml = '<div class="toast align-items-center text-bg-danger border-0" role="alert"><div class="d-flex"><div class="toast-body"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + message + '</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div></div>';
    
    const temp = document.createElement('div');
    temp.innerHTML = toastHtml;
    const toastElement = temp.firstElementChild;
    toastContainer.appendChild(toastElement);
    
    const toast = new bootstrap.Toast(toastElement, { autohide: true, delay: 3000 });
    toast.show();
    toastElement.addEventListener('hidden.bs.toast', () => toastElement.remove());
}

function validateBillingField(fieldId) {
    const el = document.getElementById(fieldId);
    if (!el) return true;
    const val = el.value.trim();
    let valid = true;
    let errMsg = '';

    if (val.length === 0) {
        valid = false;
        errMsg = 'Ez a mező kötelező.';
    } else {
        switch (fieldId) {
            case 'billing_name':
                valid = val.length >= 5 && val.split(/\s+/).filter(w => w.length > 0).length >= 2;
                errMsg = 'Add meg a teljes nevet (pl. Kovács János).';
                break;
            case 'billing_address':
                valid = val.length >= 6 && /\d/.test(val);
                errMsg = 'Add meg az utcát és házszámot (pl. Fő utca 12.).';
                break;
            case 'billing_city':
                valid = val.length >= 2 && /^[a-zA-ZáéíóöőüűÁÉÍÓÖŐÜŰ\s\-\.]+$/.test(val);
                errMsg = 'Add meg az érvényes várost.';
                break;
            case 'billing_zip':
                valid = /^[0-9]{4,10}$/.test(val.replace(/\s/g, ''));
                errMsg = 'Az irányítószám 4-10 számjegyből állhat (pl. 7622).';
                break;
            case 'billing_country':
                valid = val.length >= 3;
                errMsg = 'Add meg az érvényes országnevet.';
                break;
        }
    }

    el.classList.toggle('is-invalid', !valid);
    el.classList.toggle('is-valid', valid);

    let errEl = document.getElementById('err_' + fieldId);
    if (!errEl) {
        errEl = document.createElement('div');
        errEl.id = 'err_' + fieldId;
        errEl.className = 'gc-field-error';
        el.parentNode.appendChild(errEl);
    }
    errEl.textContent = valid ? '' : errMsg;
    errEl.classList.toggle('show', !valid);

    if (!valid) {
        el.classList.remove('gc-shake');
        void el.offsetWidth;
        el.classList.add('gc-shake');
    } else {
        el.classList.remove('gc-shake');
    }

    return valid;
}
