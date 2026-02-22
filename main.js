let allProducts = [];
let filteredProducts = [];
let currentPage = 1;
const itemsPerPage = 8;

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

async function loadProducts() {
    try {
        const response = await fetch(`${API_BASE}/products.php`);
        const data = await response.json();
        

        if (data.success) {
            allProducts = data.products;
            filteredProducts = [...allProducts];
            currentPage = 1;
            displayProducts();
        } else {
            showError('Nem sikerült betölteni a termékeket');
        }
    } catch (error) {
        showError('Hiba történt a termékek betöltése során');
    }
}

function displayProducts() {
    const grid = document.getElementById('productGrid');
    const countSpan = document.getElementById('productCount');

    if (!grid) return;

    if (filteredProducts.length === 0) {
        grid.innerHTML = '<div class="col-12"><p class="text-center text-light-50">Nincs megjeleníthető termék</p></div>';
        if (countSpan) countSpan.textContent = '0';
        return;
    }

    if (countSpan) countSpan.textContent = filteredProducts.length;

    const startIndex = (currentPage - 1) * itemsPerPage;
    const endIndex = startIndex + itemsPerPage;
    const currentProducts = filteredProducts.slice(startIndex, endIndex);
    const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);

    grid.innerHTML = `
        <div class="col-12">
            <div class="gc-carousel-container position-relative">
                ${totalPages > 1 ? `
                    <button class="gc-carousel-btn gc-carousel-prev ${currentPage === 1 ? 'disabled' : ''}" 
                            onclick="changePage(${currentPage - 1})" 
                            ${currentPage === 1 ? 'disabled' : ''}>
                        <i class="bi bi-chevron-left"></i>
                    </button>
                ` : ''}
                
                <div class="gc-carousel-content">
                    <div class="row g-4">
                        ${currentProducts.map(product => `
                            <div class="col-sm-6 col-md-4 col-lg-3">
                                <div class="card gc-product-card h-100">
                                    <div class="gc-product-img">
                                        <img src="assets/images/${product.image_url}" alt="${escapeHtml(product.name)}" class="gc-product-image">
                                        <span class="badge ${platformBadgeClass[product.platform] || 'bg-secondary'} gc-badge">
                                            ${platformLabel[product.platform] || 'PC'}
                                        </span>
                                    </div>
                                    <div class="card-body d-flex flex-column">
                                        <h3 class="h6 card-title mb-1">${escapeHtml(product.name)}</h3>
                                        <p class="card-text text-light-50 mb-2 small">${escapeHtml(product.short_description || '')}</p>
                                        <p class="card-text gc-price mb-3">${formatPrice(product.price)} Ft</p>
                                        <button onclick="addToCart(${product.id})" class="btn btn-primary w-100 gc-add-cart-btn mt-auto">
                                            <i class="bi bi-cart-plus me-1"></i> Kosárba
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                
                ${totalPages > 1 ? `
                    <button class="gc-carousel-btn gc-carousel-next ${currentPage === totalPages ? 'disabled' : ''}" 
                            onclick="changePage(${currentPage + 1})" 
                            ${currentPage === totalPages ? 'disabled' : ''}>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                ` : ''}
            </div>
            
            ${totalPages > 1 ? `
                <div class="gc-carousel-indicators mt-4">
                    <span class="text-light-50">Oldal ${currentPage} / ${totalPages}</span>
                    <div class="gc-dots mt-2">
                        ${Array.from({length: totalPages}, (_, i) => i + 1).map(page => `
                            <button class="gc-dot ${currentPage === page ? 'active' : ''}" 
                                    onclick="changePage(${page})"
                                    aria-label="Ugrás a ${page}. oldalra"></button>
                        `).join('')}
                    </div>
                </div>
            ` : ''}
        </div>
    `;
}

function changePage(page) {
    const totalPages = Math.ceil(filteredProducts.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;
    
    currentPage = page;
    displayProducts();
    
    const content = document.querySelector('.gc-carousel-content');
    if (content) {
        content.style.animation = 'none';
        setTimeout(() => {
            content.style.animation = 'fadeInSlide 0.4s ease-out';
        }, 10);
    }
}

async function addToCart(productId) {
    
    if (!isLoggedIn()) {
        showError('Kérlek jelentkezz be a kosárba helyezéshez!');
        setTimeout(() => {
            window.location.href = 'login.html';
        }, 2000);
        return;
    }
    
    try {
        const token = getToken();
        const cartHeaders = { 'Content-Type': 'application/json' };
        if (token) cartHeaders['Authorization'] = 'Bearer ' + token;
        const response = await fetch(`${API_BASE}/cart.php`, {
            method: 'POST',
            headers: cartHeaders,
            body: JSON.stringify({ product_id: productId, quantity: 1 })
        });

        const data = await response.json();

        if (data.success) {
            showSuccess('Termék hozzáadva a kosárhoz!');
            updateCartBadge();
        } else {
            showError(data.error || 'Hiba történt a kosárba helyezés során');
        }
    } catch (error) {
        showError('Hiba történt a kosárba helyezés során');
    }
}

function filterAndSortProducts() {
    
    if (typeof applyAllFilters === 'function') {
        applyAllFilters();
        return;
    }

    const platformFilter = document.getElementById('platformFilter');
    const tagFilter = document.getElementById('tagFilter');
    const sortSelect = document.getElementById('sortSelect');
    const searchInput = document.getElementById('searchInput');

    if (!platformFilter || !tagFilter || !sortSelect) return;

    filteredProducts = [...allProducts];

    const platform = platformFilter.value;
    if (platform !== 'all') {
        filteredProducts = filteredProducts.filter(p => p.platform === platform);
    }

    const tag = tagFilter.value;
    if (tag !== 'all') {
        filteredProducts = filteredProducts.filter(p => p.tag === tag);
    }

    if (searchInput && searchInput.value.trim()) {
        const query = searchInput.value.toLowerCase().trim();
        filteredProducts = filteredProducts.filter(p =>
            p.name.toLowerCase().includes(query) ||
            (p.short_description && p.short_description.toLowerCase().includes(query))
        );
    }

    const sort = sortSelect.value;
    if (sort === 'price-asc') {
        filteredProducts.sort((a, b) => a.price - b.price);
    } else if (sort === 'price-desc') {
        filteredProducts.sort((a, b) => b.price - a.price);
    }

    currentPage = 1; 
    displayProducts();
}

function renderProducts(products) {
    filteredProducts = products;
    currentPage = 1;
    displayProducts();
}

function setupSearch() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    searchInput.addEventListener('input', (e) => {
        filterAndSortProducts();
    });
}

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
    existingToasts.forEach(toast => {
        const bsToast = bootstrap.Toast.getInstance(toast);
        if (bsToast) bsToast.dispose();
        toast.remove();
    });
    
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastHtml = `
        <div class="toast align-items-center text-bg-success border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-check-circle-fill me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const temp = document.createElement('div');
    temp.innerHTML = toastHtml;
    const toastElement = temp.firstElementChild;
    toastContainer.appendChild(toastElement);
    
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 2500
    });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

function showError(message) {
    const existingToasts = document.querySelectorAll('.toast');
    existingToasts.forEach(toast => {
        const bsToast = bootstrap.Toast.getInstance(toast);
        if (bsToast) bsToast.dispose();
        toast.remove();
    });
    
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container position-fixed top-0 end-0 p-3';
        toastContainer.style.zIndex = '9999';
        document.body.appendChild(toastContainer);
    }

    const toastHtml = `
        <div class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    `;
    
    const temp = document.createElement('div');
    temp.innerHTML = toastHtml;
    const toastElement = temp.firstElementChild;
    toastContainer.appendChild(toastElement);
    
    const toast = new bootstrap.Toast(toastElement, {
        autohide: true,
        delay: 2500
    });
    toast.show();
    
    toastElement.addEventListener('hidden.bs.toast', () => {
        toastElement.remove();
    });
}

document.addEventListener('DOMContentLoaded', () => {
    
    const yearSpan = document.getElementById('yearSpan');
    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }

    loadProducts();

    const platformFilter = document.getElementById('platformFilter');
    const tagFilter = document.getElementById('tagFilter');
    const sortSelect = document.getElementById('sortSelect');

    if (platformFilter) platformFilter.addEventListener('change', filterAndSortProducts);
    if (tagFilter) tagFilter.addEventListener('change', filterAndSortProducts);
    if (sortSelect) sortSelect.addEventListener('change', filterAndSortProducts);

    setupSearch();
});
