const platformBadgeClasses = {
    'pc': 'bg-warning text-dark',
    'ps': 'bg-primary',
    'xbox': 'bg-success',
    'switch': 'bg-danger'
};
const platformLabels = {
    'pc': 'PC', 'ps': 'PS', 'xbox': 'Xbox', 'switch': 'Switch'
};

let topSliderIndex = 0;
let topSliderInterval = null;
let topSliderProducts = [];

async function loadTopSlider() {
    try {
        const response = await fetch(API_BASE + '/top_products.php');
        const data = await response.json();

        if (data.success && data.products.length > 0) {
            topSliderProducts = data.products;
            renderTopSlider();
            startTopSlider();
        }
    } catch (err) {
        console.error('Top slider hiba:', err);
    }
}

function renderTopSlider() {
    const container = document.getElementById('topSliderContent');
    const dotsContainer = document.getElementById('topSliderDots');

    let slidesHtml = '';
    let dotsHtml = '';

    topSliderProducts.forEach((product, i) => {
        const isActive = i === 0 ? 'active' : '';
        const price = new Intl.NumberFormat('hu-HU').format(product.price);
        const badgeClass = platformBadgeClasses[product.platform] || 'bg-secondary';
        const platformText = platformLabels[product.platform] || 'PC';

        let priceHtml = `<span class="gc-top-slide-price">${price} Ft</span>`;
        if (product.original_price) {
            const origPrice = new Intl.NumberFormat('hu-HU').format(product.original_price);
            priceHtml = `<span class="gc-top-slide-original-price">${origPrice} Ft</span>` + priceHtml;
        }

        let discountHtml = '';
        if (product.discount_percent > 0) {
            discountHtml = `<span class="gc-top-slide-discount">-${product.discount_percent}%</span>`;
        }

        slidesHtml += `
            <div class="gc-top-slide ${isActive}" data-index="${i}">
                <div class="gc-top-slide-img-wrap">
                    <img src="assets/images/${product.image_url}" alt="${product.name}" class="gc-top-slide-img">
                    <span class="badge ${badgeClass} gc-top-slide-platform">${platformText}</span>
                    ${discountHtml}
                </div>
                <div class="gc-top-slide-info">
                    <h4 class="gc-top-slide-name">${product.name}</h4>
                    <div class="gc-top-slide-prices">
                        ${priceHtml}
                    </div>
                </div>
            </div>
        `;

        dotsHtml += `<button class="gc-top-slider-dot ${isActive}" data-index="${i}" onclick="goToTopSlide(${i})"></button>`;
    });

    container.innerHTML = slidesHtml;
    dotsContainer.innerHTML = dotsHtml;
}

function goToTopSlide(index) {
    const slides = document.querySelectorAll('.gc-top-slide');
    const dots = document.querySelectorAll('.gc-top-slider-dot');

    slides.forEach(s => s.classList.remove('active'));
    dots.forEach(d => d.classList.remove('active'));

    topSliderIndex = index;
    slides[topSliderIndex].classList.add('active');
    dots[topSliderIndex].classList.add('active');

    clearInterval(topSliderInterval);
    startTopSlider();
}

function nextTopSlide() {
    const nextIndex = (topSliderIndex + 1) % topSliderProducts.length;
    goToTopSlide(nextIndex);
}

function startTopSlider() {
    topSliderInterval = setInterval(nextTopSlide, 10000);
}

document.addEventListener('DOMContentLoaded', function() {
    loadTopSlider();
});
