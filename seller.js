document.addEventListener('DOMContentLoaded', async () => {
    var raw = localStorage.getItem('user');
    if (!raw) return;
    try {
        var parsed = JSON.parse(raw);
        var sidebarName = document.getElementById('sidebarName');
        var sidebarInitial = document.getElementById('sidebarInitial');
        if (sidebarName) sidebarName.textContent = parsed.username || 'Eladó';
        if (sidebarInitial) sidebarInitial.textContent = (parsed.username || 'E')[0].toUpperCase();
        var greetEl = document.getElementById('userGreeting');
        if (greetEl && parsed.username) {
            greetEl.textContent = 'Szia, ' + parsed.username + '!';
        }
        var lb = document.getElementById('logoutBtn');
        if (lb) lb.style.display = 'inline-flex';
    } catch(e) {}
    await initSeller();
});

// Sidebar navigáció
document.querySelectorAll('.sidebar-nav .nav-link').forEach(link => {
    link.addEventListener('click', (e) => {
        e.preventDefault();
        const section = link.dataset.section;
        if (section) switchSection(section);
    });
});

function switchSection(sectionId) {
    document.querySelectorAll('.section-view').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.sidebar-nav .nav-link').forEach(l => l.classList.remove('active'));

    const section = document.getElementById('section-' + sectionId);
    const link = document.querySelector(`.nav-link[data-section="${sectionId}"]`);
    if (section) section.classList.add('active');
    if (link) link.classList.add('active');

    const topbarSection = document.getElementById('topbarSection');
    const labels = { upload: 'Kulcs feltöltés', mykeys: 'Feltöltött kulcsaim' };
    if (topbarSection) topbarSection.textContent = labels[sectionId] || sectionId;
}

async function initSeller() {
    await loadProducts();
    await loadMyKeys();
    initKeyInput();
}

let allProducts = [];
let currentKeyPlatform = null; // az épp kiválasztott platform a kulcs input formázásához

async function loadProducts() {
    try {
        const data = await apiRequest('seller/products.php');
        if (data.success && data.products && data.products.length > 0) {
            allProducts = data.products;
            populateProductSelect();
            return;
        }
    } catch (error) {}

    try {
        const response = await fetch(API_BASE + '/products.php');
        const data = await response.json();
        if (data.success && data.products) {
            allProducts = data.products;
            populateProductSelect();
        }
    } catch (error) {
        console.error('Termékek betöltése sikertelen:', error);
    }
}

function populateProductSelect() {
    const select = document.getElementById('productSelect');
    if (!select) return;

    select.innerHTML = '<option value="" disabled selected>Válassz egy játékot...</option>';

    if (allProducts.length === 0) {
        select.innerHTML = '<option value="" disabled selected>Nincsenek elérhető termékek</option>';
        return;
    }

    const platformSuffixes = [' PC', ' PS', ' Xbox', ' Switch'];
    const groups = {};
    allProducts.forEach(p => {
        let base = p.name;
        for (const s of platformSuffixes) {
            if (base.endsWith(s)) { base = base.slice(0, -s.length); break; }
        }
        if (!groups[base]) groups[base] = [];
        groups[base].push(p);
    });

    const gameSelect = document.getElementById('gameSelect');
    const platformSelectWrap = document.getElementById('platformSelectWrap');
    const platformSelect = document.getElementById('platformSelect');

    if (gameSelect) {
        gameSelect.innerHTML = '<option value="" disabled selected>Válassz egy játékot...</option>';
        Object.keys(groups).sort().forEach(name => {
            const opt = document.createElement('option');
            opt.value = name;
            opt.textContent = name;
            gameSelect.appendChild(opt);
        });

        gameSelect.addEventListener('change', function() {
            const chosen = this.value;
            const products = groups[chosen] || [];
            if (platformSelect) {
                platformSelect.innerHTML = '<option value="" disabled selected>Válassz platformot...</option>';

                const seenPlatforms = new Set();
                products.forEach(p => {
                    if (seenPlatforms.has(p.platform)) return;
                    seenPlatforms.add(p.platform);
                    const opt = document.createElement('option');
                    opt.value = p.id;
                    opt.textContent = getPlatformLabel(p.platform.toUpperCase());
                    opt.dataset.platform = p.platform;
                    opt.dataset.price = p.price;
                    opt.dataset.name = p.name;
                    platformSelect.appendChild(opt);
                });

                if (platformSelect.options.length === 2) {
                    platformSelect.selectedIndex = 1;
                    platformSelect.dispatchEvent(new Event('change'));
                }
            }
            if (platformSelectWrap) platformSelectWrap.style.display = 'block';
        });

        if (platformSelect) {
            platformSelect.addEventListener('change', function() {
                const opt = this.options[this.selectedIndex];
                if (!opt || !opt.value) return;
                select.innerHTML = '';
                const syncOpt = document.createElement('option');
                syncOpt.value = opt.value;
                syncOpt.dataset.platform = opt.dataset.platform;
                syncOpt.dataset.price = opt.dataset.price;
                syncOpt.dataset.name = opt.dataset.name;
                syncOpt.selected = true;
                select.appendChild(syncOpt);
                updateSelectedProduct();

                currentKeyPlatform = opt.dataset.platform || null;
                const fmt = KEY_FORMATS[currentKeyPlatform] || { hint: 'XXXX-XXXX-XXXX-XXXX', maxlen: 19 };
                const keyInput = document.getElementById('keyInput');
                if (keyInput) {
                    keyInput.placeholder = fmt.hint;
                    keyInput.setAttribute('maxlength', fmt.maxlen);
                    keyInput.value = ''; // előző érték törlése ha platformot váltott
                }
                const keyHint = document.getElementById('keyFormatHint');
                if (keyHint) keyHint.textContent = 'Elvárt formátum: ' + fmt.hint;
                const counter = document.getElementById('keyCharCounter');
                if (counter) {
                    const maxRaw = fmt.hint.replace(/-/g, '').length;
                    counter.textContent = '0/' + maxRaw;
                    counter.style.color = '#64748b';
                }
            });
        }

            select.style.display = 'none';
        return;
    }

    const platformOrder = ['PC', 'PS', 'XBOX', 'SWITCH'];
    const byPlatform = {};
    allProducts.forEach(p => {
        const pl = p.platform.toUpperCase();
        if (!byPlatform[pl]) byPlatform[pl] = [];
        byPlatform[pl].push(p);
    });
    platformOrder.forEach(platform => {
        const products = byPlatform[platform];
        if (!products) return;
        const group = document.createElement('optgroup');
        group.label = getPlatformLabel(platform);
        products.sort((a, b) => a.name.localeCompare(b.name));
        products.forEach(p => {
            const opt = document.createElement('option');
            opt.value = p.id;
            opt.textContent = p.name + ' — ' + formatPrice(p.price) + ' Ft';
            opt.dataset.platform = p.platform;
            opt.dataset.price = p.price;
            opt.dataset.name = p.name;
            group.appendChild(opt);
        });
        select.appendChild(group);
    });
}

function getPlatformLabel(p) {
    const labels = { PC: 'PC', PS: 'PlayStation', XBOX: 'Xbox', SWITCH: 'Nintendo Switch' };
    return labels[p] || p;
}

// Kulcs input - formátum platform szerint változik
const KEY_FORMATS = {
    pc:     { hint: 'XXXXX-XXXXX-XXXXX', maxlen: 17 },
    xbox:   { hint: 'XXXXX-XXXXX-XXXXX-XXXXX-XXXXX', maxlen: 29 },
    ps:     { hint: 'XXXX-XXXX-XXXX-XXXX', maxlen: 19 },
    switch: { hint: 'XXXX-XXXX-XXXX', maxlen: 14 }
};

const KEY_SEGMENT_SIZES = {
    pc:     [5, 5, 5],
    xbox:   [5, 5, 5, 5, 5],
    ps:     [4, 4, 4, 4],
    switch: [4, 4, 4]
};

function formatKeyValue(raw, platform) {
    const segs = KEY_SEGMENT_SIZES[platform] || [4, 4, 4, 4];
    let result = '';
    let pos = 0;
    for (let i = 0; i < segs.length; i++) {
        if (pos >= raw.length) break;
        if (i > 0) result += '-';
        result += raw.slice(pos, pos + segs[i]);
        pos += segs[i];
    }
    return result;
}

function getMaxRaw() {
    const segs = KEY_SEGMENT_SIZES[currentKeyPlatform] || [4, 4, 4, 4];
    return segs.reduce((a, b) => a + b, 0);
}

function initKeyInput() {
    const keyInput = document.getElementById('keyInput');
    if (!keyInput) return;

    keyInput.addEventListener('input', function () {
        const raw = this.value.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, getMaxRaw());
        const formatted = formatKeyValue(raw, currentKeyPlatform);
        this.value = formatted;
        validateKeyInput(formatted);
    });

    keyInput.addEventListener('paste', function(e) {
        e.preventDefault();
        const pasted = (e.clipboardData || window.clipboardData).getData('text');
        const raw = pasted.toUpperCase().replace(/[^A-Z0-9]/g, '').slice(0, getMaxRaw());
        this.value = formatKeyValue(raw, currentKeyPlatform);
        validateKeyInput(this.value);
    });

    const form = document.getElementById('uploadKeyForm');
    if (form) {
        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            await uploadKey();
        });
    }
}

function validateKeyInput(val) {
    const raw = val.replace(/-/g, '');
    const maxRaw = getMaxRaw();
    const rules = {
        rule_length: raw.length === maxRaw,
        rule_dash:   val.includes('-'),
        rule_chars:  /^[A-Z0-9\-]+$/.test(val) && val.length > 0
    };

    Object.entries(rules).forEach(([id, ok]) => {
        const el = document.getElementById(id);
        if (el) el.classList.toggle('ok', ok);
    });

    const counter = document.getElementById('keyCharCounter');
    if (counter) {
        counter.textContent = raw.length + '/' + maxRaw;
        counter.style.color = raw.length === maxRaw ? '#10b981' : '#64748b';
    }

    return Object.values(rules).every(v => v);
}

async function uploadKey() {
    const productSelect = document.getElementById('productSelect');
    const keyInput = document.getElementById('keyInput');
    const priceInput = document.getElementById('priceInput');
    const errorDiv = document.getElementById('uploadError');
    const successDiv = document.getElementById('uploadSuccess');
    const btn = document.getElementById('uploadBtn');

    if (errorDiv) errorDiv.style.display = 'none';
    if (successDiv) successDiv.style.display = 'none';

    const productId = productSelect ? productSelect.value : '';
    const keyCode = keyInput ? keyInput.value.trim() : '';
    const price = priceInput ? parseInt(priceInput.value) : 0;

    let errors = [];
    if (!productId) errors.push('Válassz ki egy terméket és platformot');
    if (!keyCode) errors.push('Add meg a játék kulcsot');
    if (keyCode && !validateKeyInput(keyCode)) errors.push('A kulcs formátuma nem megfelelő a kiválasztott platformhoz');
    if (!price || price < 1) errors.push('Add meg az eladási árat');
    if (price > 999999) errors.push('Az ár maximum 999 999 Ft lehet');

    if (errors.length > 0) {
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + errors.join('<br>');
            errorDiv.style.display = 'block';
        }
        return;
    }

    if (btn) { btn.disabled = true; btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Feltöltés...'; }

    try {
        const data = await apiRequest('seller/keys.php', {
            method: 'POST',
            body: JSON.stringify({
                product_id: parseInt(productId),
                key_code: keyCode,
                seller_price: price
            })
        });

        if (data.success) {
            if (successDiv) {
                const opt = productSelect.options[productSelect.selectedIndex];
                successDiv.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i>Kulcs sikeresen feltöltve: <strong>' + escapeHtml(opt.dataset.name) + '</strong>';
                successDiv.style.display = 'block';
            }
            keyInput.value = '';
            if (priceInput) priceInput.value = '';
            document.querySelectorAll('.gc-key-rule').forEach(function(r) { r.classList.remove('ok'); });
            var counter = document.getElementById('keyCharCounter');
            if (counter) { counter.textContent = '0/' + getMaxRaw(); counter.style.color = '#64748b'; }
            await loadMyKeys();
        } else {
            var msg = (data.errors && Array.isArray(data.errors))
                ? data.errors.join('<br>')
                : (data.error || 'Hiba történt a feltöltés során.');
            if (errorDiv) {
                errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + msg;
                errorDiv.style.display = 'block';
            }
        }
    } catch (error) {
        if (errorDiv) {
            errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-2"></i>' + (error.message || 'Hálózati hiba');
            errorDiv.style.display = 'block';
        }
    }

    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="bi bi-cloud-upload me-2"></i>Kulcs feltöltése'; }
}

// Kiválasztott termék info
document.addEventListener('change', function(e) {
    if (e.target.id === 'productSelect') updateSelectedProduct();
});

function updateSelectedProduct() {
    var select = document.getElementById('productSelect');
    var infoBox = document.getElementById('selectedProductInfo');
    if (!select || !infoBox) return;

    var opt = select.options[select.selectedIndex];
    if (!opt || !opt.value) {
        infoBox.style.display = 'none';
        return;
    }

    var platform = opt.dataset.platform;
    var price = parseInt(opt.dataset.price);
    var name = opt.dataset.name;

    infoBox.innerHTML = '<div style="display:flex;align-items:center;gap:0.75rem;">' +
        '<div class="plat-badge ' + platform + '">' + platform.toUpperCase() + '</div>' +
        '<div>' +
            '<div style="font-weight:600;color:#f1f5f9;font-size:0.9rem;">' + escapeHtml(name) + '</div>' +
            '<div style="color:#a855f7;font-weight:700;">' + formatPrice(price) + ' Ft</div>' +
        '</div>' +
    '</div>';
    infoBox.style.display = 'block';
}

// Saját kulcsok listája
async function loadMyKeys() {
    var tbody = document.getElementById('myKeysBody');
    var statsTotal = document.getElementById('statTotal');
    var statsSold = document.getElementById('statSold');
    var statsAvail = document.getElementById('statAvailable');

    try {
        var data = await apiRequest('seller/keys.php');
        if (data.success) {
            if (statsTotal) statsTotal.textContent = data.stats.total;
            if (statsSold) statsSold.textContent = data.stats.sold;
            if (statsAvail) statsAvail.textContent = data.stats.available;

            if (!tbody) return;
            if (data.keys.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--gc-text-muted);">Még nem töltöttél fel kulcsot.</td></tr>';
                return;
            }

            tbody.innerHTML = data.keys.map(function(key) {
                return '<tr>' +
                    '<td style="font-weight:600;">' + escapeHtml(key.product_name) + '</td>' +
                    '<td><span class="plat-badge ' + key.platform + '">' + key.platform.toUpperCase() + '</span></td>' +
                    '<td><code style="color:#a855f7;background:rgba(168,85,247,0.08);padding:2px 8px;border-radius:4px;font-size:0.8rem;">' + escapeHtml(key.key_code) + '</code></td>' +
                    '<td>' + (key.is_sold
                        ? '<span class="status-pill sold">Eladva</span>'
                        : (!key.is_approved
                            ? '<span class="status-pill pending">Jóváhagyás alatt</span>'
                            : '<span class="status-pill available">Elérhető</span>'))
                    + '</td>' +
                    '<td class="date-cell">' + formatDate(key.created_at) + '</td>' +
                '</tr>';
            }).join('');
        }
    } catch (error) {
        if (statsTotal) statsTotal.textContent = '0';
        if (statsSold) statsSold.textContent = '0';
        if (statsAvail) statsAvail.textContent = '0';
        if (tbody) tbody.innerHTML = '<tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--gc-text-muted);">Még nem töltöttél fel kulcsot.</td></tr>';
    }
}

function formatPrice(price) {
    return new Intl.NumberFormat('hu-HU').format(price);
}

function formatDate(dateString) {
    var date = new Date(dateString);
    return date.toLocaleDateString('hu-HU', {
        year: 'numeric', month: 'short', day: 'numeric',
        hour: '2-digit', minute: '2-digit'
    });
}

function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
