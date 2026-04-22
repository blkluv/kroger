async function getJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    const json = await response.json();
    if (!json.ok) {
        throw new Error(json.error || 'Request failed');
    }
    return json;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function row(template) {
    return `<div class="row">${template}</div>`;
}

function renderList(el, items, columns) {
    if (!el) return;
    if (!items.length) {
        el.innerHTML = '<div class="row"><div>No data found.</div></div>';
        return;
    }

    el.innerHTML = items.map((item) => row(columns(item))).join('');
}

function formatDate(value) {
    if (!value) return 'n/a';
    return new Date(value).toLocaleString();
}

document.addEventListener('DOMContentLoaded', async () => {
    const summary = {};
    const views = document.querySelectorAll('[data-view]');
    const navItems = document.querySelectorAll('[data-panel]');
    const search = document.getElementById('global-search');

    function setActive(panel) {
        navItems.forEach((button) => {
            button.classList.toggle('is-active', button.dataset.panel === panel);
        });
        views.forEach((view) => {
            view.style.display = view.dataset.view === panel ? '' : 'none';
        });
    }

    navItems.forEach((button) => {
        button.addEventListener('click', () => setActive(button.dataset.panel));
    });

    setActive('dashboard');

    try {
        const json = await getJson('api.php?action=dashboard');
        Object.assign(summary, json.summary || {});

        document.querySelectorAll('[data-summary]').forEach((el) => {
            const key = el.dataset.summary;
            el.textContent = summary[key] ?? 0;
        });

        const recentProducts = document.getElementById('dashboard-products');
        const storesList = document.getElementById('stores-list');
        const productsList = document.getElementById('products-list');
        const usersList = document.getElementById('users-list');
        const chart = document.getElementById('price-chart');

        const series = json.priceSeries || [];
        chart.innerHTML = series.length
            ? series.map((point) => `<div class="row"><div><strong>${escapeHtml(point.day)}</strong><small>Price snapshots</small></div><div></div><div></div><div><strong>${escapeHtml(point.total)}</strong></div></div>`).join('')
            : '<div class="row"><div>No price history yet.</div></div>';

        renderList(recentProducts, json.recentProducts || [], (item) => `
            <div>
                <strong>${escapeHtml(item.description || 'Unnamed product')}</strong>
                <small>${escapeHtml([item.brand, item.temperature_indicator].filter(Boolean).join(' • '))}</small>
            </div>
            <div>${escapeHtml(item.product_id || '')}</div>
            <div>${escapeHtml(item.upc || '')}</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        renderList(storesList, json.recentStores || [], (item) => `
            <div>
                <strong>${escapeHtml(item.name || 'Unnamed store')}</strong>
                <small>${escapeHtml([item.city, item.state_code].filter(Boolean).join(', '))}</small>
            </div>
            <div>${escapeHtml(item.zip_code || '')}</div>
            <div>${escapeHtml(item.chain || '')}</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        renderList(productsList, json.recentProducts || [], (item) => `
            <div>
                <strong>${escapeHtml(item.description || 'Unnamed product')}</strong>
                <small>${escapeHtml(item.brand || '')}</small>
            </div>
            <div>${escapeHtml(item.upc || '')}</div>
            <div>${item.snap_eligible ? 'SNAP' : 'Retail'}</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        renderList(usersList, json.recentUsers || [], (item) => `
            <div>
                <strong>${escapeHtml(item.display_name || 'User')}</strong>
                <small>${escapeHtml(item.email || '')}</small>
            </div>
            <div>${escapeHtml(item.id)}</div>
            <div>Identity</div>
            <div>${formatDate(item.updated_at)}</div>
        `);
    } catch (error) {
        document.body.insertAdjacentHTML('afterbegin', `<div style="padding:16px;color:#a80f27;font-weight:700;">${escapeHtml(error.message)}</div>`);
    }

    search?.addEventListener('input', async () => {
        const term = search.value.trim();
        if (!term) {
            setActive('dashboard');
            return;
        }

        const [products, stores, users] = await Promise.all([
            getJson(`api.php?action=products&q=${encodeURIComponent(term)}&limit=8`),
            getJson(`api.php?action=stores&q=${encodeURIComponent(term)}&limit=8`),
            getJson(`api.php?action=users&q=${encodeURIComponent(term)}&limit=8`),
        ]);

        document.getElementById('dashboard-products').innerHTML = '';
        document.getElementById('products-list').innerHTML = '';
        document.getElementById('stores-list').innerHTML = '';
        document.getElementById('users-list').innerHTML = '';

        renderList(document.getElementById('products-list'), products.items || [], (item) => `
            <div>
                <strong>${escapeHtml(item.description || 'Unnamed product')}</strong>
                <small>${escapeHtml(item.brand || '')}</small>
            </div>
            <div>${escapeHtml(item.upc || '')}</div>
            <div>${item.snap_eligible ? 'SNAP' : 'Retail'}</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        renderList(document.getElementById('stores-list'), stores.items || [], (item) => `
            <div>
                <strong>${escapeHtml(item.name || 'Unnamed store')}</strong>
                <small>${escapeHtml([item.city, item.state_code].filter(Boolean).join(', '))}</small>
            </div>
            <div>${escapeHtml(item.zip_code || '')}</div>
            <div>${escapeHtml(item.chain || '')}</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        renderList(document.getElementById('users-list'), users.items || [], (item) => `
            <div>
                <strong>${escapeHtml(item.display_name || 'User')}</strong>
                <small>${escapeHtml(item.email || '')}</small>
            </div>
            <div>${escapeHtml(item.id)}</div>
            <div>Identity</div>
            <div>${formatDate(item.updated_at)}</div>
        `);

        setActive('products');
    });
});
