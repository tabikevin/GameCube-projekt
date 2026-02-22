document.addEventListener('DOMContentLoaded', () => {
    
    if (!isLoggedIn()) {
        alert('Be kell jelentkezned a profil megtekintéséhez!');
        window.location.href = 'login.html?redirect=profile.html';
        return;
    }

    loadProfile();
});

async function loadProfile() {
    try {
        const data = await apiRequest('profile.php');

        if (data.success) {
            displayUserInfo(data.user);
            displayOrders(data.orders || []);
            displayPurchasedKeys(data.purchased_keys || []);
        } else {
            showError('Hiba történt a profil betöltése során: ' + data.error);
        }
    } catch (error) {
        
        if (error.message && error.message.includes('401')) {
            alert('A munkamenet lejárt. Kérlek jelentkezz be újra!');
            localStorage.removeItem('token');
            localStorage.removeItem('user');
            window.location.href = 'login.html?redirect=profile.html';
        } else {
            showError('Hiba történt a profil betöltése során: ' + error.message);
        }
    }
}

function displayUserInfo(user) {
    
    const userName = document.getElementById('userName');
    const userEmail = document.getElementById('userEmail');
    const userUsername = document.getElementById('userUsername');
    const userPhone = document.getElementById('userPhone');
    const userRole = document.getElementById('userRole');
    const userSince = document.getElementById('userSince');
    const adminBadge = document.getElementById('adminBadge');
    const adminSection = document.getElementById('adminSection');
    
    if (userName) userName.textContent = user.full_name || 'N/A';
    if (userEmail) userEmail.textContent = user.email || 'N/A';
    if (userUsername) userUsername.textContent = user.username || 'N/A';
    if (userPhone) userPhone.textContent = user.phone || 'Nincs megadva';
    if (userRole) userRole.textContent = user.role === 'admin' ? 'Adminisztrátor' : 'Felhasználó';
    if (userSince) userSince.textContent = formatDate(user.created_at);

    if (user.role === 'admin') {
        if (adminBadge) adminBadge.style.display = 'inline-block';
        if (adminSection) adminSection.style.display = 'block';
    }
}

function displayOrders(orders) {
    
    const tbody = document.getElementById('ordersBody');
    if (!tbody) return;

    if (!orders || orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center">Még nincs rendelésed</td></tr>';
        return;
    }

    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>#${order.id}</td>
            <td>${formatDate(order.created_at)}</td>
            <td>${formatPrice(order.total_price)} Ft</td>
            <td>${getPaymentMethodLabel(order.payment_method)}</td>
            <td><span class="badge ${getStatusBadgeClass(order.status)}">${getStatusLabel(order.status)}</span></td>
        </tr>
    `).join('');
}

function displayPurchasedKeys(keys) {
    
    const container = document.getElementById('keysContainer');
    if (!container) return;

    if (!keys || keys.length === 0) {
        container.innerHTML = '<p class="text-center text-light-50">Még nincs vásárolt játékkulcsod</p>';
        return;
    }

    container.innerHTML = keys.map(key => `
        <div class="card gc-product-card mb-3">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <div>
                        <h5 class="card-title mb-1">${escapeHtml(key.product_name)}</h5>
                        <p class="card-text text-light-50 mb-2">
                            <span class="badge bg-secondary">${key.platform ? key.platform.toUpperCase() : 'PC'}</span>
                            <small class="ms-2">Vásárlás: ${formatDate(key.sold_at)}</small>
                        </p>
                    </div>
                    <button class="btn btn-sm btn-outline-primary" onclick="copyKey('${key.key_code}', this)">
                        <i class="bi bi-clipboard"></i> Másolás
                    </button>
                </div>
                <div class="alert alert-info mb-0">
                    <code class="text-dark fs-6">${key.key_code}</code>
                </div>
            </div>
        </div>
    `).join('');
}

function copyKey(keyCode, button) {
    navigator.clipboard.writeText(keyCode).then(() => {
        const originalText = button.innerHTML;
        button.innerHTML = '<i class="bi bi-check"></i> Másolva!';
        button.classList.add('btn-success');
        button.classList.remove('btn-outline-primary');
        
        setTimeout(() => {
            button.innerHTML = originalText;
            button.classList.remove('btn-success');
            button.classList.add('btn-outline-primary');
        }, 2000);
    }).catch(err => {
        alert('Hiba történt a másolás során');
    });
}

function getStatusLabel(status) {
    const labels = {
        'pending': 'Függőben',
        'paid': 'Fizetve',
        'cancelled': 'Törölve'
    };
    return labels[status] || status;
}

function getStatusBadgeClass(status) {
    const classes = {
        'pending': 'bg-warning text-dark',
        'paid': 'bg-success',
        'cancelled': 'bg-danger'
    };
    return classes[status] || 'bg-secondary';
}

function getPaymentMethodLabel(method) {
    const labels = {
        'online_card': 'Online kártya',
        'bank_transfer': 'Banki átutalás',
        'paypal': 'PayPal'
    };
    return labels[method] || method;
}

function formatDate(dateString) {
    if (!dateString) return 'N/A';
    const date = new Date(dateString);
    return date.toLocaleDateString('hu-HU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric'
    });
}

function formatPrice(price) {
    return new Intl.NumberFormat('hu-HU').format(price);
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showError(message) {
    alert(message);
}
