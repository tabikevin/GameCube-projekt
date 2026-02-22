const pathParts = window.location.pathname.split('/');
const projectFolder = pathParts[1];
const API_BASE = '/' + projectFolder + '/api';

function getToken() {
    return localStorage.getItem('token');
}

function setToken(token) {
    localStorage.setItem('token', token);
}

function removeToken() {
    localStorage.removeItem('token');
    localStorage.removeItem('user');
}

function getUser() {
    const user = localStorage.getItem('user');
    return user ? JSON.parse(user) : null;
}

function setUser(user) {
    localStorage.setItem('user', JSON.stringify(user));
}

function isLoggedIn() {
    return !!getToken();
}

function isAdmin() {
    const user = getUser();
    return user && user.role === 'admin';
}

function updateAuthUI() {
    const user = getUser();
    
    const userGreeting = document.getElementById('userGreeting');
    const authButtons = document.getElementById('authButtons');
    const logoutBtn = document.getElementById('logoutBtn');
    const profileNavItem = document.getElementById('profileNavItem');
    const adminNavItem = document.getElementById('adminNavItem');

    if (isLoggedIn() && user) {
        if (userGreeting) {
            userGreeting.textContent = 'Szia, ' + user.username + '!';
            userGreeting.classList.remove('d-none');
        }
        if (authButtons) authButtons.style.display = 'none';
        if (logoutBtn) logoutBtn.style.display = 'inline-block';
        if (profileNavItem) profileNavItem.classList.remove('d-none');
        if (adminNavItem && isAdmin()) {
            adminNavItem.classList.remove('d-none');
        } else if (adminNavItem) {
            adminNavItem.classList.add('d-none');
        }
    } else {
        if (userGreeting) userGreeting.classList.add('d-none');
        if (authButtons) authButtons.style.display = 'inline';
        if (logoutBtn) logoutBtn.style.display = 'none';
        if (profileNavItem) profileNavItem.classList.add('d-none');
        if (adminNavItem) adminNavItem.classList.add('d-none');
    }
}

async function logout() {
    try {
        await fetch(API_BASE + '/login.php?logout=1');
    } catch (error) {}
    removeToken();
    window.location.href = 'index.html';
}

async function apiRequest(endpoint, options = {}) {
    const token = getToken();
    const headers = {
        'Content-Type': 'application/json',
        ...options.headers
    };

    if (token) {
        headers['Authorization'] = 'Bearer ' + token;
    }

    const response = await fetch(API_BASE + '/' + endpoint, {
        ...options,
        headers
    });

    const data = await response.json();

    if (!response.ok) {
        throw new Error(data.error || 'Request failed');
    }

    return data;
}

document.addEventListener('DOMContentLoaded', () => {
    updateAuthUI();
    
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', logout);
    }

    updateCartBadge();
});

async function updateCartBadge() {
    try {
        const token = getToken();
        if (!token) {
            const badge = document.getElementById('cartBadge');
            if (badge) badge.style.display = 'none';
            return;
        }
        const response = await fetch(API_BASE + '/cart.php', {
            headers: { 'Authorization': 'Bearer ' + token }
        });
        const data = await response.json();
        const badge = document.getElementById('cartBadge');
        if (badge && data.success && data.item_count > 0) {
            badge.textContent = data.item_count;
            badge.style.display = 'inline-block';
        } else if (badge) {
            badge.style.display = 'none';
        }
    } catch (error) {}
}
