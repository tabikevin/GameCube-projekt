let allProducts = [];
let filteredProducts = [];
let currentPage = 1;
const itemsPerPage = 8;

const groupSelectedPlatform = {};
let groupData = {};

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

function getBaseName(name) {
    const suffixes = [' PC', ' PS', ' PS4', ' PS5', ' Xbox', ' Switch'];
    let n = name.trim();
    for (let i = 0; i < suffixes.length; i++) {
        if (n.endsWith(suffixes[i])) {
            n = n.slice(0, n.length - suffixes[i].length);
            break;
        }
    }
    if (n === 'Minecraft Java & Bedrock') return 'Minecraft';
    if (n === 'FIFA 25') return 'EA Sports FC 25';
    return n;
}

function toKey(baseName) {
    return baseName.replace(/[^a-zA-Z0-9]/g, '_');
}

function groupProducts(products) {
    const map = new Map();

    products.forEach(p => {
        const base = getBaseName(p.name);
        if (!map.has(base)) {
            map.set(base, { baseName: base, products: [] });
        }
        map.get(base).products.push(p);
    });

    const tagPriority = { top: 4, new: 3, sale: 2, normal: 1 };

    map.forEach(group => {
        group.products.sort((a, b) => {
            const ta = tagPriority[a.tag] || 0;
            const tb = tagPriority[b.tag] || 0;
            if (tb !== ta) return tb - ta;
            if (b.long_description && !a.long_description) return 1;
            if (a.long_description && !b.long_description) return -1;
            return a.id - b.id;
        });

        const seen = new Set();
        group.products = group.products.filter(p => {
            if (seen.has(p.platform)) return false;
            seen.add(p.platform);
            return true;
        });

        group.representative = group.products[0];
    });

    return Array.from(map.values());
}

async function loadProducts() {
    try {
        const response = await fetch(`${API_BASE}/products.php`);
        const data = await response.json();
        if (data.success) {
            allProducts = data.products;
            filteredProducts = [...allProducts];
            currentPage = 1;
            displayProducts();
            betoltKosarAllapot();
        } else {
            showError('Nem sikerült betölteni a termékeket');
        }
    } catch (err) {
        showError('Hiba történt a termékek betöltése során');
    }
}

function displayProducts() {
    const grid = document.getElementById('productGrid');
    const countSpan = document.getElementById('productCount');
    if (!grid) return;

    const groups = groupProducts(filteredProducts);

    groupData = {};
    groups.forEach(g => {
        groupData[toKey(g.baseName)] = g;
    });

    const platformFilterEl = document.getElementById('platformFilter');
    const activePlatformFilter = platformFilterEl ? platformFilterEl.value : 'all';

    if (countSpan) countSpan.textContent = groups.length;

    if (groups.length === 0) {
        grid.innerHTML = '<div class="col-12"><p class="text-center text-light-50">Nincs megjeleníthető termék</p></div>';
        return;
    }

    const totalPages = Math.ceil(groups.length / itemsPerPage);
    const startIndex = (currentPage - 1) * itemsPerPage;
    const currentGroups = groups.slice(startIndex, startIndex + itemsPerPage);

    currentGroups.forEach(group => {
        const key = toKey(group.baseName);
        const availablePlatforms = group.products.map(p => p.platform);

        if (activePlatformFilter !== 'all' && availablePlatforms.includes(activePlatformFilter)) {
            groupSelectedPlatform[key] = activePlatformFilter;
        }

        if (!groupSelectedPlatform[key] || !availablePlatforms.includes(groupSelectedPlatform[key])) {
            groupSelectedPlatform[key] = group.representative.platform;
        }
    });

    let prevBtn = '';
    let nextBtn = '';
    let indicators = '';

    if (totalPages > 1) {
        prevBtn = `<button class="gc-carousel-btn gc-carousel-prev ${currentPage === 1 ? 'disabled' : ''}"
                onclick="changePage(${currentPage - 1})"
                ${currentPage === 1 ? 'disabled' : ''}>
            <i class="bi bi-chevron-left"></i>
        </button>`;
        nextBtn = `<button class="gc-carousel-btn gc-carousel-next ${currentPage === totalPages ? 'disabled' : ''}"
                onclick="changePage(${currentPage + 1})"
                ${currentPage === totalPages ? 'disabled' : ''}>
            <i class="bi bi-chevron-right"></i>
        </button>`;

        let dots = '';
        for (let p = 1; p <= totalPages; p++) {
            dots += `<button class="gc-dot ${currentPage === p ? 'active' : ''}"
                onclick="changePage(${p})" aria-label="Ugrás a ${p}. oldalra"></button>`;
        }
        indicators = `<div class="gc-carousel-indicators mt-4">
            <span class="text-light-50">Oldal ${currentPage} / ${totalPages}</span>
            <div class="gc-dots mt-2">${dots}</div>
        </div>`;
    }

    const cards = currentGroups.map(g => renderGroupCard(g)).join('');

    grid.innerHTML = `
        <div class="col-12">
            <div class="gc-carousel-container position-relative">
                ${prevBtn}
                <div class="gc-carousel-content">
                    <div class="row g-4">${cards}</div>
                </div>
                ${nextBtn}
            </div>
            ${indicators}
        </div>
    `;
}

function renderGroupCard(group) {
    const key = toKey(group.baseName);
    const rep = group.representative;
    const selectedPlatform = groupSelectedPlatform[key] || rep.platform;
    const selectedProduct = group.products.find(p => p.platform === selectedPlatform) || rep;

    const platformOrder = ['pc', 'ps', 'xbox', 'switch'];
    const sortedProducts = [...group.products].sort((a, b) =>
        platformOrder.indexOf(a.platform) - platformOrder.indexOf(b.platform)
    );

    const hasDiscount = selectedProduct.original_price && selectedProduct.original_price > selectedProduct.price;
    const multiPlatform = group.products.length > 1;
    const minPrice = multiPlatform ? Math.min(...group.products.map(p => p.price)) : selectedProduct.price;
    const isDefaultSelection = !groupSelectedPlatform[key];

    let priceLabel;
    if (multiPlatform && isDefaultSelection) {
        priceLabel = `<span class="gc-price">${formatPrice(minPrice)} Ft-tól</span>`;
    } else if (hasDiscount) {
        priceLabel = `<span class="text-decoration-line-through text-light-50 small me-1">${formatPrice(selectedProduct.original_price)} Ft</span>
                      <span class="gc-price">${formatPrice(selectedProduct.price)} Ft</span>`;
    } else {
        priceLabel = `<span class="gc-price">${formatPrice(selectedProduct.price)} Ft</span>`;
    }

    let platformSelector = '';
    if (sortedProducts.length > 1) {
        let btns = '';
        sortedProducts.forEach(p => {
            const activeClass = p.platform === selectedPlatform
                ? platformBadgeClass[p.platform] || 'bg-secondary'
                : 'btn-outline-secondary gc-platform-btn-inactive';
            btns += `<button class="btn btn-sm gc-platform-btn ${activeClass}"
                        onclick="selectGroupPlatform('${key}', '${p.platform}')">
                        ${platformLabel[p.platform] || p.platform}
                    </button>`;
        });
        platformSelector = `<div class="gc-platform-selector px-2 pt-2 d-flex gap-1 flex-wrap">${btns}</div>`;
    } else {
        platformSelector = `<div class="px-2 pt-2">
            <span class="badge ${platformBadgeClass[selectedPlatform] || 'bg-secondary'}">
                ${platformLabel[selectedPlatform] || selectedPlatform}
            </span>
        </div>`;
    }

    return `
        <div class="col-sm-6 col-md-4 col-lg-3">
            <div class="card gc-product-card h-100" id="card-${key}">
                <div class="gc-product-img">
                    <img src="assets/images/${escapeHtml(rep.image_url)}"
                         alt="${escapeHtml(group.baseName)}"
                         class="gc-product-image">
                    ${hasDiscount ? `<span class="badge bg-danger gc-badge gc-discount-badge">-${selectedProduct.discount_percent}%</span>` : ''}
                </div>
                ${platformSelector}
                <div class="card-body d-flex flex-column">
                    <h3 class="h6 card-title mb-1">${escapeHtml(group.baseName)}</h3>
                    <p class="card-text text-light-50 mb-2 small">${escapeHtml(rep.short_description || '')}</p>
                    <div class="mb-3" id="price-${key}">${priceLabel}</div>
                    <button onclick="addGroupToCart('${key}')" class="btn btn-primary w-100 gc-add-cart-btn mt-auto" data-product-id="${selectedProduct.id}" data-group-key="${key}">
                        <i class="bi bi-cart-plus me-1"></i> Kosárba
                    </button>
                </div>
            </div>
        </div>
    `;
}

function selectGroupPlatform(key, platform) {
    groupSelectedPlatform[key] = platform;
    const group = groupData[key];
    if (!group) return;

    const selectedProduct = group.products.find(p => p.platform === platform) || group.representative;
    const card = document.getElementById(`card-${key}`);
    if (!card) return;

    card.querySelectorAll('.gc-platform-btn').forEach(btn => {
        const match = btn.getAttribute('onclick').match(/',\s*'([a-z]+)'\)/);
        const btnPlatform = match ? match[1] : null;
        if (!btnPlatform) return;
        if (btnPlatform === platform) {
            btn.className = `btn btn-sm gc-platform-btn ${platformBadgeClass[platform] || 'bg-secondary'}`;
        } else {
            btn.className = 'btn btn-sm gc-platform-btn btn-outline-secondary gc-platform-btn-inactive';
        }
    });

    const priceEl = document.getElementById(`price-${key}`);
    if (!priceEl) return;

    const hasDiscount = selectedProduct.original_price && selectedProduct.original_price > selectedProduct.price;
    if (hasDiscount) {
        priceEl.innerHTML = `<span class="text-decoration-line-through text-light-50 small me-1">${formatPrice(selectedProduct.original_price)} Ft</span>
                             <span class="gc-price">${formatPrice(selectedProduct.price)} Ft</span>`;
    } else {
        priceEl.innerHTML = `<span class="gc-price">${formatPrice(selectedProduct.price)} Ft</span>`;
    }
}

const kosarbanLevoTermekek = new Set();

async function betoltKosarAllapot() {
    try {
        const token = getToken();
        if (!token) return;
        const res = await fetch(API_BASE + '/cart.php', { headers: { 'Authorization': 'Bearer ' + token } });
        const data = await res.json();
        if (data.success && data.cart) {
            kosarbanLevoTermekek.clear();
            data.cart.forEach(item => kosarbanLevoTermekek.add(item.id));
            frissitKosarGombokat();
        }
    } catch(e) {}
}

function frissitKosarGombokat() {
    document.querySelectorAll('.gc-add-cart-btn').forEach(btn => {
        const pid = parseInt(btn.dataset.productId);
        if (!pid) return;
        if (kosarbanLevoTermekek.has(pid)) {
            btn.innerHTML = '<i class="bi bi-cart-check me-1"></i> Kosárban';
            btn.classList.add('btn-success');
            btn.classList.remove('btn-primary');
            btn.disabled = false;
            btn.onclick = () => { window.location.href = 'cart.html'; };
        } else {
            btn.innerHTML = '<i class="bi bi-cart-plus me-1"></i> Kosárba';
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-success');
            btn.disabled = false;
            const key = btn.dataset.groupKey;
            if (key) btn.onclick = () => addGroupToCart(key);
        }
    });
}

async function addGroupToCart(key) {
    const group = groupData[key];
    if (!group) return;
    const platform = groupSelectedPlatform[key] || group.representative.platform;
    const product = group.products.find(p => p.platform === platform) || group.representative;
    await addToCart(product.id, key);
}

function changePage(page) {
    const groups = groupProducts(filteredProducts);
    const totalPages = Math.ceil(groups.length / itemsPerPage);
    if (page < 1 || page > totalPages) return;

    currentPage = page;
    displayProducts();

    const content = document.querySelector('.gc-carousel-content');
    if (content) {
        content.style.animation = 'none';
        setTimeout(() => { content.style.animation = 'fadeInSlide 0.4s ease-out'; }, 10);
    }
}

async function addToCart(productId, groupKey) {
    if (!isLoggedIn()) {
        showError('Kérlek jelentkezz be a kosárba helyezéshez!');
        setTimeout(() => { window.location.href = 'login.html'; }, 2000);
        return;
    }
    try {
        const token = getToken();
        const headers = { 'Content-Type': 'application/json' };
        if (token) headers['Authorization'] = 'Bearer ' + token;

        const response = await fetch(`${API_BASE}/cart.php`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify({ product_id: productId, quantity: 1 })
        });
        const data = await response.json();
        if (data.success) {
            if (data.already_at_max) {
                showInfo(data.message || 'Már a kosárban van (elérted a maximális mennyiséget)');
            } else {
                showSuccess('Termék hozzáadva a kosárhoz!');
            }
            kosarbanLevoTermekek.add(productId);
            frissitKosarGombokat();
            updateCartBadge();
        } else {
            showError(data.error || 'Hiba történt a kosárba helyezés során');
        }
    } catch (err) {
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
    if (platform !== 'all') filteredProducts = filteredProducts.filter(p => p.platform === platform);

    const tag = tagFilter.value;
    if (tag !== 'all') filteredProducts = filteredProducts.filter(p => p.tag === tag);

    if (searchInput && searchInput.value.trim()) {
        const q = searchInput.value.toLowerCase().trim();
        filteredProducts = filteredProducts.filter(p =>
            p.name.toLowerCase().includes(q) ||
            (p.short_description && p.short_description.toLowerCase().includes(q))
        );
    }

    const sort = sortSelect.value;
    if (sort === 'price-asc') filteredProducts.sort((a, b) => a.price - b.price);
    else if (sort === 'price-desc') filteredProducts.sort((a, b) => b.price - a.price);

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
    searchInput.addEventListener('input', filterAndSortProducts);
}

function escapeHtml(text) {
    const d = document.createElement('div');
    d.textContent = text;
    return d.innerHTML;
}

function formatPrice(price) {
    return new Intl.NumberFormat('hu-HU').format(price);
}

function showSuccess(msg) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="toast align-items-center text-bg-success border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-check-circle-fill me-2"></i>${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>`;
    const el = wrapper.firstElementChild;
    container.appendChild(el);
    const t = new bootstrap.Toast(el, { autohide: true, delay: 2500 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function showInfo(msg) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }
    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="toast align-items-center text-bg-warning border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body text-dark"><i class="bi bi-info-circle-fill me-2"></i>${msg}</div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>`;
    const el = wrapper.firstElementChild;
    container.appendChild(el);
    const t = new bootstrap.Toast(el, { autohide: true, delay: 3000 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

function showError(msg) {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed top-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    container.querySelectorAll('.toast').forEach(old => {
        const inst = bootstrap.Toast.getInstance(old);
        if (inst) inst.dispose();
        old.remove();
    });

    const wrapper = document.createElement('div');
    wrapper.innerHTML = `<div class="toast align-items-center text-bg-danger border-0" role="alert">
        <div class="d-flex">
            <div class="toast-body"><i class="bi bi-exclamation-triangle-fill me-2"></i>${msg}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    </div>`;
    const el = wrapper.firstElementChild;
    container.appendChild(el);
    const t = new bootstrap.Toast(el, { autohide: true, delay: 3000 });
    t.show();
    el.addEventListener('hidden.bs.toast', () => el.remove());
}

document.addEventListener('DOMContentLoaded', () => {
    const yearSpan = document.getElementById('yearSpan');
    if (yearSpan) yearSpan.textContent = new Date().getFullYear();

    loadProducts();

    const platformFilter = document.getElementById('platformFilter');
    const tagFilter = document.getElementById('tagFilter');
    const sortSelect = document.getElementById('sortSelect');

    if (platformFilter) platformFilter.addEventListener('change', filterAndSortProducts);
    if (tagFilter) tagFilter.addEventListener('change', filterAndSortProducts);
    if (sortSelect) sortSelect.addEventListener('change', filterAndSortProducts);

    setupSearch();
});
