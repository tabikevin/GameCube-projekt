document.addEventListener("DOMContentLoaded", () => {
    const searchInput     = document.querySelector("#searchInput");
    const platformFilter  = document.querySelector("#platformFilter");
    const tagFilter       = document.querySelector("#tagFilter");
    const sortSelect      = document.querySelector("#sortSelect");
    const productWrappers = Array.from(document.querySelectorAll(".gc-product-wrapper"));
    const productCount    = document.querySelector("#productCount");
    const yearSpan        = document.querySelector("#yearSpan");

    if (yearSpan) {
        yearSpan.textContent = new Date().getFullYear();
    }

    function parsePrice(element) {
        const raw   = element.getAttribute("data-price");
        const value = Number(raw);
        return Number.isFinite(value) ? value : 0;
    }

    function applyFiltersAndSort() {
        const query    = searchInput ? searchInput.value.trim().toLowerCase() : "";
        const platform = platformFilter ? platformFilter.value : "all";
        const tag      = tagFilter ? tagFilter.value : "all";
        const sortVal  = sortSelect ? sortSelect.value : "default";

        productWrappers.forEach(wrapper => {
            const titleElement   = wrapper.querySelector(".card-title");
            const title          = titleElement ? titleElement.textContent.toLowerCase() : "";
            const wrapperPlatform = wrapper.getAttribute("data-platform") || "pc";
            const wrapperTag      = wrapper.getAttribute("data-tag") || "top";

            const matchesSearch   = !query || title.includes(query);
            const matchesPlatform = platform === "all" || platform === wrapperPlatform;
            const matchesTag      = tag === "all" || tag === wrapperTag;

            if (matchesSearch && matchesPlatform && matchesTag) {
                wrapper.classList.remove("gc-hidden");
            } else {
                wrapper.classList.add("gc-hidden");
            }
        });

        let visibleItems = productWrappers.filter(w => !w.classList.contains("gc-hidden"));

        if (sortVal === "price-asc") {
            visibleItems.sort((a, b) => parsePrice(a) - parsePrice(b));
        } else if (sortVal === "price-desc") {
            visibleItems.sort((a, b) => parsePrice(b) - parsePrice(a));
        }

        const grid = document.querySelector("#productGrid");
        if (grid) {
            visibleItems.forEach(item => grid.appendChild(item));
        }

        if (productCount) {
            productCount.textContent = String(visibleItems.length);
        }
    }

    if (searchInput) {
        const parentForm = searchInput.closest("form");
        if (parentForm) {
            parentForm.addEventListener("submit", event => {
                event.preventDefault();
                applyFiltersAndSort();
            });
        }

        let debounceTimer;
        searchInput.addEventListener("input", () => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                applyFiltersAndSort();
            }, 200);
        });
    }

    if (platformFilter) {
        platformFilter.addEventListener("change", applyFiltersAndSort);
    }
    if (tagFilter) {
        tagFilter.addEventListener("change", applyFiltersAndSort);
    }
    if (sortSelect) {
        sortSelect.addEventListener("change", applyFiltersAndSort);
    }

    applyFiltersAndSort();
});
