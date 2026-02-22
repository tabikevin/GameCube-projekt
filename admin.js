document.addEventListener('DOMContentLoaded', async () => {
    const token = localStorage.getItem('token');
    const user = localStorage.getItem('user');
    
    if (token && user) {
        try {
            const parsed = JSON.parse(user);
            if (parsed.role === 'admin') {
                loadOrders();
                return;
            }
        } catch {}
    }
    // Not admin - show access denied overlay
    document.body.innerHTML = `
        <style>
            body { margin:0; background:#0f0e17; display:flex; align-items:center; justify-content:center; min-height:100vh; font-family:'Sora',sans-serif; }
        </style>
        <link href="https://fonts.googleapis.com/css2?family=Orbitron:wght@700;900&family=Sora:wght@400;600&display=swap" rel="stylesheet">
        <div style="text-align:center; padding:2rem; max-width:480px;">
            <div style="font-size:4rem; margin-bottom:1rem;">🔒</div>
            <h1 style="font-family:'Orbitron',sans-serif; color:#ef4444; font-size:1.6rem; margin-bottom:0.5rem;">Hozzáférés megtagadva</h1>
            <p style="color:#94a3b8; margin-bottom:2rem; line-height:1.6;">Ez az oldal kizárólag adminisztrátorok számára elérhető. Nincs jogosultságod megtekinteni ezt az oldalt.</p>
            <a href="index.html" style="background:linear-gradient(135deg,#a855f7,#7c3aed); color:white; text-decoration:none; border-radius:10px; padding:0.75rem 2rem; font-weight:600; display:inline-block;">Vissza a főoldalra</a>
        </div>
    `;
});

async function loadOrders() {
    try {
        const data = await apiRequest('admin/orders.php');

        if (data.success) {
            displayOrders(data.orders);
        }
    } catch (error) {
        alert('Hiba történt a rendelések betöltése során');
    }
}

function displayOrders(orders) {
    const tbody = document.getElementById('ordersBody');
    if (!tbody) return;

    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">Nincs rendelés</td></tr>';
        return;
    }

    tbody.innerHTML = orders.map(order => `
        <tr>
            <td>#${order.id}</td>
            <td>${formatDate(order.created_at)}</td>
            <td>${escapeHtml(order.username)}</td>
            <td>${formatPrice(order.total_price)} Ft</td>
            <td>${getPaymentMethodLabel(order.payment_method)}</td>
            <td><span class="badge ${getStatusBadgeClass(order.status)}">${getStatusLabel(order.status)}</span></td>
            <td>
                ${order.status === 'pending' ? `
                    <button class="btn btn-sm btn-success" onclick="approveOrder(${order.id})">
                        <i class="bi bi-check-circle"></i> Jóváhagyás
                    </button>
                ` : '<span class="text-light-50">-</span>'}
            </td>
        </tr>
    `).join('');
}

async function approveOrder(orderId) {
    if (!confirm('Biztosan jóváhagyod ezt a rendelést? A kulcsok automatikusan kiosztásra kerülnek.')) {
        return;
    }

    try {
        const data = await apiRequest('admin/orders.php', {
            method: 'POST',
            body: JSON.stringify({ order_id: orderId })
        });

        if (data.success) {
            alert(data.message);
            loadOrders();
        }
    } catch (error) {
        alert(error.message || 'Hiba történt a rendelés jóváhagyása során');
    }
}

function filterOrders() {
    const statusFilter = document.getElementById('statusFilter');
    const paymentFilter = document.getElementById('paymentFilter');
    
    if (!statusFilter || !paymentFilter) return;

    const rows = document.querySelectorAll('#ordersBody tr');
    const status = statusFilter.value;
    const payment = paymentFilter.value;

    rows.forEach(row => {
        const statusCell = row.querySelector('td:nth-child(6)');
        const paymentCell = row.querySelector('td:nth-child(5)');
        
        if (!statusCell || !paymentCell) return;

        const statusMatch = status === 'all' || statusCell.textContent.toLowerCase().includes(getStatusLabel(status).toLowerCase());
        const paymentMatch = payment === 'all' || paymentCell.textContent.includes(getPaymentMethodLabel(payment));

        row.style.display = (statusMatch && paymentMatch) ? '' : 'none';
    });
}

document.getElementById('statusFilter')?.addEventListener('change', filterOrders);
document.getElementById('paymentFilter')?.addEventListener('change', filterOrders);

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
    const date = new Date(dateString);
    return date.toLocaleDateString('hu-HU', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
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
