// Enhanced Search Service - Add to public/assets/js/app.js

class EnhancedSearchService {
    constructor() {
        this.searchCache = new Map();
        this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
        this.currentRequest = null;
        this.searchHistory = JSON.parse(localStorage.getItem('searchHistory') || '[]').slice(0, 10);
    }

    async search(term, locationId, options = {}) {
        const cacheKey = `${term}|${locationId}`;
        
        // Check cache first
        if (this.searchCache.has(cacheKey) && !options.skipCache) {
            const cached = this.searchCache.get(cacheKey);
            if (Date.now() - cached.timestamp < this.cacheTimeout) {
                return cached.results;
            }
        }

        // Abort previous request if still pending
        if (this.currentRequest) {
            this.currentRequest.abort();
        }

        try {
            const controller = new AbortController();
            this.currentRequest = controller;

            const response = await fetch(
                `api.php?action=search_products&q=${encodeURIComponent(term)}&locationId=${encodeURIComponent(locationId)}`,
                { signal: controller.signal }
            );

            if (!response.ok) throw new Error('Search failed');
            const json = await response.json();

            // Cache results
            this.searchCache.set(cacheKey, {
                results: json.results || [],
                timestamp: Date.now()
            });

            // Add to history
            this.addSearchHistory(term);

            return json.results || [];
        } catch (error) {
            if (error.name !== 'AbortError') {
                throw error;
            }
        }
    }

    async searchSuggestions(term, locationId) {
        const results = await this.search(term, locationId);
        return this.generateSuggestions(results, term);
    }

    generateSuggestions(results, term) {
        const suggestions = [];

        // Brand suggestions
        const brands = [...new Set(results.map(r => r.brand).filter(Boolean))];
        brands.forEach(brand => {
            suggestions.push({
                type: 'brand',
                text: brand,
                icon: '🏷️',
                action: () => `${term} ${brand}`,
            });
        });

        // Category suggestions
        const categories = [...new Set(results.flatMap(r => (r.categories || '').split(',')))].map(c => c.trim()).filter(Boolean);
        categories.slice(0, 3).forEach(category => {
            suggestions.push({
                type: 'category',
                text: category,
                icon: '📁',
                action: () => category,
            });
        });

        // Deal suggestions
        const deals = results.filter(r => r.sale_price && r.regular_price).slice(0, 2);
        deals.forEach(product => {
            const discount = Math.round(((product.regular_price - product.sale_price) / product.regular_price) * 100);
            suggestions.push({
                type: 'deal',
                text: `${product.description} (${discount}% off)`,
                icon: '🎯',
                action: () => product.description,
            });
        });

        return suggestions;
    }

    addSearchHistory(term) {
        if (!this.searchHistory.includes(term)) {
            this.searchHistory.unshift(term);
            this.searchHistory = this.searchHistory.slice(0, 10);
            localStorage.setItem('searchHistory', JSON.stringify(this.searchHistory));
        }
    }

    getSearchHistory() {
        return this.searchHistory;
    }

    clearCache() {
        this.searchCache.clear();
    }
}

// Initialize globally
const searchService = new EnhancedSearchService();

// Enhanced UI Components
class SearchUIEnhancer {
    constructor(searchInputEl, suggestionsEl, resultsEl) {
        this.searchInput = searchInputEl;
        this.suggestionsContainer = suggestionsEl;
        this.resultsContainer = resultsEl;
        this.isOpen = false;

        this.init();
    }

    init() {
        if (!this.searchInput) return;

        this.searchInput.addEventListener('focus', () => this.showSuggestions());
        this.searchInput.addEventListener('blur', () => {
            setTimeout(() => this.hideSuggestions(), 200);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('.search-container')) {
                this.hideSuggestions();
            }
        });
    }

    showSuggestions() {
        this.isOpen = true;
        const term = this.searchInput.value.trim();

        // Show search history if empty
        if (!term) {
            this.renderHistory();
            this.suggestionsContainer.classList.add('active');
            return;
        }

        // Show recent searches
        this.suggestionsContainer.classList.add('active');
    }

    hideSuggestions() {
        this.isOpen = false;
        this.suggestionsContainer.classList.remove('active');
    }

    renderSuggestions(suggestions) {
        if (!this.suggestionsContainer) return;

        this.suggestionsContainer.innerHTML = suggestions.map(sugg => `
            <div class="search-suggestion" data-type="${sugg.type}">
                <span class="suggestion-icon">${sugg.icon}</span>
                <span class="suggestion-text">${escapeHtml(sugg.text)}</span>
            </div>
        `).join('');

        this.suggestionsContainer.classList.add('active');

        // Add click handlers
        this.suggestionsContainer.querySelectorAll('.search-suggestion').forEach((el, index) => {
            el.addEventListener('click', () => {
                this.searchInput.value = suggestions[index].action();
                document.getElementById('search-button')?.click();
            });
        });
    }

    renderHistory() {
        if (!this.suggestionsContainer) return;

        const history = searchService.getSearchHistory();
        if (history.length === 0) {
            this.suggestionsContainer.innerHTML = '<div class="empty-history">No recent searches</div>';
            return;
        }

        this.suggestionsContainer.innerHTML = `
            <div class="history-section">
                <div class="history-label">Recent Searches</div>
                ${history.map(term => `
                    <div class="search-suggestion">
                        <span class="suggestion-icon">⏱️</span>
                        <span class="suggestion-text">${escapeHtml(term)}</span>
                    </div>
                `).join('')}
                <button class="clear-history-btn">Clear history</button>
            </div>
        `;

        this.suggestionsContainer.querySelectorAll('.search-suggestion').forEach((el, index) => {
            el.addEventListener('click', () => {
                this.searchInput.value = history[index];
                document.getElementById('search-button')?.click();
            });
        });

        this.suggestionsContainer.querySelector('.clear-history-btn')?.addEventListener('click', () => {
            localStorage.removeItem('searchHistory');
            searchService.searchHistory = [];
            this.renderHistory();
        });
    }
}

// Enhanced result rendering
function renderEnhancedSearchResults(results, locationId) {
    const container = document.getElementById('search-results');
    if (!container) return;

    if (!results || results.length === 0) {
        container.innerHTML = '<div class="empty-state">No products found. Try different keywords.</div>';
        return;
    }

    // Group by category if multiple categories
    const grouped = {};
    results.forEach(product => {
        const category = product.categories?.split(',')[0]?.trim() || 'Other';
        if (!grouped[category]) grouped[category] = [];
        grouped[category].push(product);
    });

    let html = '';
    Object.entries(grouped).forEach(([category, products]) => {
        if (products.length > 0) {
            html += `<div class="result-category"><h4>${escapeHtml(category)}</h4>`;
            html += products.map(product => renderProductCard(product)).join('');
            html += '</div>';
        }
    });

    container.innerHTML = html;

    // Attach click handlers
    container.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-product-action]');
        if (btn) {
            handleProductAction(btn.dataset.productAction, btn.dataset.productId);
        }
    });
}

function renderProductCard(product) {
    const price = product.sale_price ?? product.regular_price;
    const discount = product.sale_price ? Math.round(((product.regular_price - product.sale_price) / product.regular_price) * 100) : 0;
    const inStock = product.inventory_level !== 'TEMPORARILY_OUT_OF_STOCK';

    return `
        <article class="result-item ${!inStock ? 'out-of-stock' : ''}">
            <div class="result-main">
                <img class="result-thumb" src="${escapeHtml(product.image_url || 'https://via.placeholder.com/88x88')}" alt="${escapeHtml(product.description)}">
                <div class="result-copy">
                    <div class="result-title">${escapeHtml(product.description)}</div>
                    <div class="result-meta">${escapeHtml([product.brand, product.size].filter(Boolean).join(' • '))}</div>
                    <div class="result-meta" style="font-size: 0.85em; color: #666;">
                        ${product.aisle_locations || ''} 
                        ${inStock ? '✓ In stock' : '⚠️ Out of stock'}
                    </div>
                </div>
            </div>
            <div class="result-actions">
                <div class="result-price-section">
                    <div class="result-price">${price ? currency(price) : 'N/A'}</div>
                    ${product.sale_price && product.regular_price ? `
                        <div style="font-size: 0.85em; color: #d9534f;">
                            Was ${currency(product.regular_price)}
                            <span style="color: #fff; background: #d9534f; padding: 2px 6px; border-radius: 3px;">-${discount}%</span>
                        </div>
                    ` : ''}
                </div>
                <div style="display: flex; gap: 8px; flex-direction: column;">
                    <button class="btn-primary" data-product-action="add-to-cart" data-product-id="${product.db_id}" ${!inStock ? 'disabled' : ''}>
                        ${inStock ? 'Add to cart' : 'Out of stock'}
                    </button>
                    <button class="btn-secondary" data-product-action="add-as-usual" data-product-id="${product.db_id}">
                        Save as usual
                    </button>
                </div>
            </div>
        </article>
    `;
}

async function handleProductAction(action, productId) {
    try {
        if (action === 'add-to-cart') {
            await requestJson('api.php?action=add_list_item', {
                method: 'POST',
                body: JSON.stringify({ product_id: Number(productId), quantity: 1 }),
            });
            showNotification('Added to cart! ✓', 'success');
        } else if (action === 'add-as-usual') {
            await requestJson('api.php?action=add_usual_item', {
                method: 'POST',
                body: JSON.stringify({ product_id: Number(productId), quantity: 1 }),
            });
            showNotification('Saved as usual item! ✓', 'success');
        }
        await loadCart();
    } catch (error) {
        showNotification(error.message, 'error');
    }
}

function showNotification(message, type = 'info') {
    const notif = document.createElement('div');
    notif.className = `notification notification-${type}`;
    notif.textContent = message;
    document.body.appendChild(notif);

    setTimeout(() => notif.remove(), 3000);
}
