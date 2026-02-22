let cart = [];

document.addEventListener('DOMContentLoaded', function() {
    const yearSpan = document.getElementById('yearSpan');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

    // Irányítószám: csak szám
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
    
    cartBody.innerHTML = cart.map(item => 
        '<tr>' +
            '<td>' + escapeHtml(item.name) + '</td>' +
            '<td><span class="badge ' + (platformBadgeClass[item.platform] || 'bg-secondary') + '">' + (platformLabel[item.platform] || 'PC') + '</span></td>' +
            '<td>' + formatPrice(item.price) + ' Ft</td>' +
            '<td><div class="d-flex align-items-center gap-2">' +
                '<button onclick="updateQuantity(' + item.id + ', ' + (item.quantity - 1) + ')" class="btn btn-sm btn-outline-light"' + (item.quantity <= 1 ? ' disabled' : '') + '><i class="bi bi-dash"></i></button>' +
                '<span class="mx-2">' + item.quantity + '</span>' +
                '<button onclick="updateQuantity(' + item.id + ', ' + (item.quantity + 1) + ')" class="btn btn-sm btn-outline-light"><i class="bi bi-plus"></i></button>' +
            '</div></td>' +
            '<td><strong>' + formatPrice(item.subtotal) + ' Ft</strong></td>' +
            '<td><button onclick="removeItem(' + item.id + ')" class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button></td>' +
        '</tr>'
    ).join('');
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
            
            const formData = new FormData(checkoutForm);
            
            const checkoutData = {
                billing_name: formData.get('billing_name'),
                billing_address: formData.get('billing_address'),
                billing_city: formData.get('billing_city'),
                billing_zip: formData.get('billing_zip'),
                billing_country: formData.get('billing_country'),
                billing_tax_number: formData.get('billing_tax_number') || '',
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
