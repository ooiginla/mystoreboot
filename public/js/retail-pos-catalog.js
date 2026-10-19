/* Storeboot Retail POS catalogue — authenticated fetch with an IndexedDB fast path. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var root = document.querySelector('[data-rpos][data-catalog-url]');
        if (!root) return;

        var grid = root.querySelector('[data-grid]');
        var categories = root.querySelector('[data-cats]');
        var loading = root.querySelector('[data-grid-loading]');
        var empty = root.querySelector('[data-grid-empty]');
        var currency = root.dataset.currency || '';
        var expected = {
            tenant_id: String(root.dataset.tenant || ''),
            user_id: String(root.dataset.user || ''),
            branch_id: String(root.dataset.branch || ''),
            inventory_location_id: String(root.dataset.location || '')
        };
        var cacheKey = [expected.tenant_id, expected.user_id, expected.branch_id, expected.inventory_location_id].join(':');
        var cachedRecord = null;

        function validPayload(payload) {
            return payload && Array.isArray(payload.items) && Array.isArray(payload.categories) &&
                String(payload.tenant_id) === expected.tenant_id &&
                String(payload.user_id) === expected.user_id &&
                String(payload.branch_id) === expected.branch_id &&
                String(payload.inventory_location_id) === expected.inventory_location_id;
        }

        function emitLoaded(source) {
            document.dispatchEvent(new CustomEvent('pos:catalog-loaded', { detail: { source: source } }));
        }

        function render(payload, source) {
            if (!validPayload(payload)) return false;

            grid.querySelectorAll('[data-tile]').forEach(function (tile) { tile.remove(); });
            categories.querySelectorAll('[data-dynamic-category]').forEach(function (button) { button.remove(); });

            payload.categories.forEach(function (category) {
                var button = document.createElement('button');
                button.className = 'rpos-cat';
                button.type = 'button';
                button.dataset.cat = String(category.id);
                button.dataset.dynamicCategory = '1';
                button.textContent = category.name;
                categories.appendChild(button);
            });

            payload.items.forEach(function (item) {
                var tile = document.createElement('div');
                tile.className = 'rpos-tile';
                tile.tabIndex = 0;
                tile.setAttribute('role', 'button');
                tile.dataset.tile = '';
                tile.dataset.variantId = String(item.id);
                tile.dataset.categoryId = String(item.category_id || 0);
                tile.dataset.name = item.name || '';
                tile.dataset.price = String(item.price || 0);
                tile.dataset.taxRate = String(item.tax_rate || 0);
                tile.dataset.sku = item.sku || '';
                tile.dataset.search = [item.name, item.sku, item.barcode, item.brand, item.category_name]
                    .filter(Boolean).join(' ').toLocaleLowerCase();

                var pin = document.createElement('button');
                pin.className = 'rpos-pin';
                pin.type = 'button';
                pin.dataset.pin = '';
                pin.setAttribute('aria-label', 'Pin product');
                pin.innerHTML = '<svg fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M14 3l7 7-4 1-4 4-1 5-2-2-4 4-1-1 4-4-2-2 5-1 4-4 1-4Z"/></svg>';

                var image = document.createElement('span');
                image.className = 'rpos-tile-img';
                image.style.background = 'hsl(' + Number(item.hue || 0) + ' 55% 42%)';
                image.textContent = String(item.name || 'P').charAt(0).toLocaleUpperCase();
                if (item.image_url) {
                    var img = document.createElement('img');
                    img.src = item.image_url;
                    img.alt = '';
                    img.loading = 'lazy';
                    img.addEventListener('error', function () { img.remove(); });
                    image.appendChild(img);
                }

                var name = document.createElement('span');
                name.className = 'rpos-tile-name';
                name.textContent = item.name || '';

                var foot = document.createElement('span');
                foot.className = 'rpos-tile-foot';
                var price = document.createElement('span');
                price.className = 'rpos-tile-price';
                price.textContent = currency + ' ' + Number(item.price || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                foot.appendChild(price);

                tile.appendChild(pin);
                tile.appendChild(image);
                tile.appendChild(name);
                tile.appendChild(foot);
                grid.insertBefore(tile, loading || empty);
            });

            if (loading) loading.hidden = true;
            if (empty) {
                empty.textContent = payload.items.length ? 'No products match this view.' : 'No active products are available for this till.';
                empty.hidden = payload.items.length > 0;
            }
            emitLoaded(source);
            return true;
        }

        function showError() {
            if (cachedRecord) return;
            if (loading) {
                loading.hidden = false;
                loading.textContent = 'The catalogue could not be loaded. Check the connection and retry.';
            }
        }

        function openDatabase() {
            return new Promise(function (resolve, reject) {
                if (!window.indexedDB) { reject(new Error('IndexedDB unavailable')); return; }
                var request = indexedDB.open('storeboot-pos', 1);
                request.onupgradeneeded = function () {
                    if (!request.result.objectStoreNames.contains('catalogs')) request.result.createObjectStore('catalogs', { keyPath: 'key' });
                };
                request.onsuccess = function () { resolve(request.result); };
                request.onerror = function () { reject(request.error); };
            });
        }

        function readCache(db) {
            return new Promise(function (resolve) {
                var request = db.transaction('catalogs', 'readonly').objectStore('catalogs').get(cacheKey);
                request.onsuccess = function () { resolve(request.result || null); };
                request.onerror = function () { resolve(null); };
            });
        }

        function writeCache(db, record) {
            try { db.transaction('catalogs', 'readwrite').objectStore('catalogs').put(record); } catch (error) {}
        }

        function refresh(db) {
            var headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            if (cachedRecord && cachedRecord.etag) headers['If-None-Match'] = cachedRecord.etag;

            fetch(root.dataset.catalogUrl, { credentials: 'same-origin', headers: headers })
                .then(function (response) {
                    if (response.status === 304 && cachedRecord) return null;
                    if (!response.ok) throw new Error('Catalogue request failed');
                    return response.json().then(function (payload) {
                        if (!validPayload(payload)) throw new Error('Catalogue context mismatch');
                        render(payload, 'network');
                        if (db) writeCache(db, {
                            key: cacheKey,
                            etag: response.headers.get('ETag') || '',
                            payload: payload,
                            saved_at: Date.now()
                        });
                        return payload;
                    });
                })
                .catch(showError);
        }

        openDatabase()
            .then(function (db) {
                return readCache(db).then(function (record) {
                    cachedRecord = record && validPayload(record.payload) ? record : null;
                    if (cachedRecord) render(cachedRecord.payload, 'cache');
                    refresh(db);
                });
            })
            .catch(function () { refresh(null); });
    });
})();
