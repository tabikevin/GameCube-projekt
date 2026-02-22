let currentCategory = 'all';

document.addEventListener('DOMContentLoaded', function() {
    loadCategories();
    setupCatDropdown();
});

async function loadCategories() {
    try {
        const response = await fetch(API_BASE + '/categories.php');
        const data = await response.json();

        if (data.success && data.categories.length > 0) {
            const container = document.getElementById('catMenuItems');
            container.innerHTML = data.categories.map(cat => `
                <a href="#products" class="gc-cat-item" data-category="${cat.key}">
                    <i class="bi ${cat.icon}"></i> ${cat.name}
                    <span class="gc-cat-count">${cat.count}</span>
                </a>
            `).join('');

            document.querySelectorAll('.gc-cat-item[data-category]').forEach(item => {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const category = this.dataset.category;
                    selectCategory(category);
                    closeCatDropdown();
                });
            });
        }
    } catch (err) {
        console.error('Kategória betöltés hiba:', err);
    }
}

function selectCategory(category) {
    currentCategory = category;

    document.querySelectorAll('.gc-cat-item').forEach(item => {
        item.classList.toggle('active', item.dataset.category === category);
    });

    const btn = document.getElementById('catDropdownBtn');
    if (category === 'all') {
        btn.innerHTML = '<i class="bi bi-controller"></i> Kategóriák <i class="bi bi-chevron-down gc-cat-chevron"></i>';
    } else {
        const activeItem = document.querySelector(`.gc-cat-item[data-category="${category}"]`);
        if (activeItem) {
            const icon = activeItem.querySelector('.bi').className;
            const name = activeItem.childNodes[1].textContent.trim();
            btn.innerHTML = `<i class="${icon}"></i> ${name} <i class="bi bi-chevron-down gc-cat-chevron"></i>`;
        }
    }

    applyAllFilters();

    setTimeout(() => {
        const productsSection = document.getElementById('products');
        if (productsSection) {
            productsSection.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }, 100);
}

function applyAllFilters() {
    if (typeof allProducts === 'undefined' || !allProducts || allProducts.length === 0) return;

    let result = [...allProducts];

    
    if (currentCategory !== 'all') {
        result = result.filter(p => p.category === currentCategory);
    }

    
    const platformFilter = document.getElementById('platformFilter');
    if (platformFilter && platformFilter.value !== 'all') {
        result = result.filter(p => p.platform === platformFilter.value);
    }

    
    const tagFilter = document.getElementById('tagFilter');
    if (tagFilter && tagFilter.value !== 'all') {
        result = result.filter(p => p.tag === tagFilter.value);
    }

    
    const searchInput = document.getElementById('searchInput');
    if (searchInput && searchInput.value.trim()) {
        const query = searchInput.value.toLowerCase().trim();
        result = result.filter(p =>
            p.name.toLowerCase().includes(query) ||
            (p.short_description && p.short_description.toLowerCase().includes(query))
        );
    }

    
    const sortSelect = document.getElementById('sortSelect');
    if (sortSelect) {
        if (sortSelect.value === 'price-asc') {
            result.sort((a, b) => a.price - b.price);
        } else if (sortSelect.value === 'price-desc') {
            result.sort((a, b) => b.price - a.price);
        }
    }

    filteredProducts = result;
    currentPage = 1;
    displayProducts();
}

function setupCatDropdown() {
    const btn = document.getElementById('catDropdownBtn');
    const menu = document.getElementById('catDropdownMenu');
    if (!btn || !menu) return;

    btn.addEventListener('click', function(e) {
        e.stopPropagation();
        menu.classList.toggle('open');
        btn.classList.toggle('open');
    });

    document.addEventListener('click', function(e) {
        if (!e.target.closest('.gc-cat-dropdown')) {
            closeCatDropdown();
        }
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCatDropdown();
    });
}

function closeCatDropdown() {
    const menu = document.getElementById('catDropdownMenu');
    const btn = document.getElementById('catDropdownBtn');
    if (menu) menu.classList.remove('open');
    if (btn) btn.classList.remove('open');
}
