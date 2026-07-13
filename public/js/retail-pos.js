/* Storeboot Retail POS — touch cart, keypad, hold/recall, pin, quick customer. */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        // Auto-open receipt after a completed sale (works even on the open-till screen).
        var root = document.querySelector('[data-rpos]');
        if (!root) return;

        var CUR = root.dataset.currency || '';
        var TENANT = root.dataset.tenant;
        var WALKIN = { id: root.dataset.walkinId, name: root.dataset.walkinName };
        var PIN_KEY = 'sb-pos-pins-' + TENANT;
        var HELD_KEY = 'sb-pos-held-' + TENANT;

        var $ = function (sel, ctx) { return (ctx || document).querySelector(sel); };
        var $$ = function (sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); };
        var clean = function (v) { return Number(String(v == null ? '' : v).replace(/,/g, '')) || 0; };
        var fmt = function (v) { return CUR + ' ' + Number(v || 0).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }); };
        var commafy = function (v) {
            var s = String(v == null ? '' : v).replace(/,/g, '');
            if (s === '' || s === '.') return s;
            var neg = s.charAt(0) === '-';
            s = s.replace(/[^\d.]/g, '');
            var p = s.split('.');
            p[0] = Number(p[0] || 0).toLocaleString('en-US');
            return (neg ? '-' : '') + (p.length > 1 ? p[0] + '.' + p[1].slice(0, 2) : p[0]);
        };
        var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]; }); };

        // ---- State ----
        var cart = [];               // {id,name,qty,price,taxRate,sku}
        var customer = { id: WALKIN.id, name: WALKIN.name, sub: 'Walk-in customer' };
        var coupon = '';             // code
        var discount = { type: 'amount', value: 0 };
        var delivery = { method: '', shipping: 0, status: 'delivered', address: '' };
        var note = '';
        var pay = { received: 0, method: '', accountId: '', credit: false };
        var pins = loadPins();

        function loadPins() { try { return new Set(JSON.parse(localStorage.getItem(PIN_KEY) || '[]')); } catch (e) { return new Set(); } }
        function savePins() { try { localStorage.setItem(PIN_KEY, JSON.stringify(Array.from(pins))); } catch (e) {} }
        function loadHeld() { try { return JSON.parse(localStorage.getItem(HELD_KEY) || '[]'); } catch (e) { return []; } }
        function saveHeld(list) { try { localStorage.setItem(HELD_KEY, JSON.stringify(list)); } catch (e) {} }

        function toast(msg) {
            var t = $('[data-toast]'); if (!t) return;
            t.textContent = msg; t.classList.add('show');
            clearTimeout(t._t); t._t = setTimeout(function () { t.classList.remove('show'); }, 2200);
        }

        on('[data-rpos-menu-toggle]', function () {
            document.body.classList.toggle('rpos-menu-open');
        });
        document.addEventListener('click', function (e) {
            if (!document.body.classList.contains('rpos-menu-open')) return;
            if (e.target.closest('.sidebar') || e.target.closest('[data-rpos-menu-toggle]')) return;
            document.body.classList.remove('rpos-menu-open');
        });
        $$('.sidebar a').forEach(function (link) {
            link.addEventListener('click', function () { document.body.classList.remove('rpos-menu-open'); });
        });

        // ---- Mobile cart drawer ----
        function openCartDrawer() { document.body.classList.add('rpos-cart-open'); }
        function closeCartDrawer() { document.body.classList.remove('rpos-cart-open'); }
        on('[data-cart-open]', openCartDrawer);
        on('[data-cart-close]', closeCartDrawer);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape') closeCartDrawer(); });
        document.addEventListener('click', function (e) {
            if (!document.body.classList.contains('rpos-cart-open')) return;
            if (e.target.closest('.rpos-cart') || e.target.closest('[data-cart-open]')) return;
            closeCartDrawer();
        });

        // ================= CART =================
        function findCoupon(code) {
            return $$('[data-coupon]').find(function (c) { return c.dataset.code.toUpperCase() === String(code).toUpperCase() && c.dataset.active === '1'; });
        }

        function totals() {
            var subtotal = 0, tax = 0;
            cart.forEach(function (i) { subtotal += i.qty * i.price; tax += i.qty * i.price * (i.taxRate / 100); });
            var c = findCoupon(coupon);
            var couponDisc = c ? Math.min(subtotal, c.dataset.type === 'percentage' ? subtotal * (clean(c.dataset.percent) / 100) : clean(c.dataset.amount)) : 0;
            var adminDisc = Math.min(subtotal, discount.type === 'percentage' ? subtotal * (discount.value / 100) : discount.value);
            var shipping = clean(delivery.shipping);
            var total = Math.max(0, subtotal + tax + shipping - couponDisc - adminDisc);
            return { subtotal: subtotal, tax: tax, couponDisc: couponDisc, adminDisc: adminDisc, shipping: shipping, total: total, couponValid: !!c };
        }

        function renderCart() {
            var wrap = $('[data-cart-lines]');
            var empty = $('[data-empty-cart]');
            $$('.rpos-line', wrap).forEach(function (n) { n.remove(); });
            if (!cart.length) { if (empty) empty.hidden = false; }
            else {
                if (empty) empty.hidden = true;
                cart.forEach(function (item, idx) {
                    var div = document.createElement('div');
                    div.className = 'rpos-line';
                    div.innerHTML =
                        '<div class="nm">' + esc(item.name) + '<small>' + fmt(item.price) + ' each</small></div>' +
                        '<div class="rpos-qty"><button type="button" data-dec="' + idx + '">−</button>' +
                        '<input type="text" inputmode="numeric" value="' + item.qty + '" data-qty="' + idx + '">' +
                        '<button type="button" data-inc="' + idx + '">+</button></div>' +
                        '<div class="lt">' + fmt(item.qty * item.price) + '</div>' +
                        '<button class="rm" type="button" data-rm="' + idx + '" aria-label="Remove">✕</button>';
                    wrap.appendChild(div);
                });
            }
            var t = totals();
            $('[data-sum-subtotal]').textContent = fmt(t.subtotal);
            $('[data-sum-tax]').textContent = fmt(t.tax);
            $('[data-sum-total]').textContent = fmt(t.total);
            toggleLine('[data-coupon-line]', t.couponDisc > 0, '[data-sum-coupon]', '-' + fmt(t.couponDisc));
            toggleLine('[data-discount-line]', t.adminDisc > 0, '[data-sum-discount]', '-' + fmt(t.adminDisc));
            toggleLine('[data-shipping-line]', t.shipping > 0, '[data-sum-shipping]', fmt(t.shipping));
            var tag = $('[data-coupon-tag]'); if (tag) tag.textContent = t.couponValid ? '(' + coupon.toUpperCase() + ')' : '';
            var payBtn = $('[data-open-pay]'); if (payBtn) payBtn.disabled = cart.length === 0;

            // Mobile cart bar sync
            var qtyCount = cart.reduce(function (n, i) { return n + i.qty; }, 0);
            var cbCount = $('[data-cartbar-count]'); if (cbCount) cbCount.textContent = qtyCount;
            var cbItems = $('[data-cartbar-items]'); if (cbItems) cbItems.textContent = qtyCount + (qtyCount === 1 ? ' item' : ' items');
            var cbTotal = $('[data-cartbar-total]'); if (cbTotal) cbTotal.textContent = fmt(t.total);
            var bar = $('[data-cart-open]'); if (bar) bar.classList.toggle('is-empty', cart.length === 0);
            if (!cart.length) document.body.classList.remove('rpos-cart-open');
        }
        function toggleLine(sel, show, valSel, val) {
            var line = $(sel); if (!line) return; line.hidden = !show;
            if (show && valSel) $(valSel).textContent = val;
        }

        function addProduct(tile) {
            var id = tile.dataset.variantId;
            var existing = cart.find(function (i) { return i.id === id; });
            if (existing) existing.qty += 1;
            else cart.push({ id: id, name: tile.dataset.name, qty: 1, price: clean(tile.dataset.price), taxRate: clean(tile.dataset.taxRate), sku: tile.dataset.sku });
            renderCart();
        }

        // ================= CUSTOMER =================
        function setCustomer(c) {
            customer = c;
            $('[data-customer-name]').textContent = c.name;
            $('[data-customer-sub]').textContent = c.sub || '';
            $('[data-customer-initial]').textContent = (c.name || 'W').charAt(0).toUpperCase();
            var pc = $('[data-pay-customer]'); if (pc) pc.textContent = c.name;
        }

        // ================= EVENTS =================
        document.addEventListener('click', function (e) {
            var tile = e.target.closest('[data-tile]');
            var pin = e.target.closest('[data-pin]');
            if (pin) { e.preventDefault(); e.stopPropagation(); togglePin(pin.closest('[data-tile]')); return; }
            if (tile) { addProduct(tile); return; }

            var inc = e.target.closest('[data-inc]'); if (inc) { cart[+inc.dataset.inc].qty += 1; renderCart(); return; }
            var dec = e.target.closest('[data-dec]'); if (dec) { var i = +dec.dataset.dec; cart[i].qty = Math.max(1, cart[i].qty - 1); renderCart(); return; }
            var rm = e.target.closest('[data-rm]'); if (rm) { cart.splice(+rm.dataset.rm, 1); renderCart(); return; }

            var sel = e.target.closest('[data-select-customer]');
            if (sel) { setCustomer({ id: sel.dataset.id, name: sel.dataset.name, sub: sel.dataset.sub }); close('rpos-customer-dialog'); toast('Customer set: ' + sel.dataset.name); return; }
        });

        document.addEventListener('input', function (e) {
            var q = e.target.closest('[data-qty]');
            if (q) { var i = +q.dataset.qty; var v = Math.max(1, parseInt(q.value.replace(/[^\d]/g, ''), 10) || 1); cart[i].qty = v; var t = totals(); $('[data-sum-subtotal]').textContent = fmt(t.subtotal); $('[data-sum-total]').textContent = fmt(t.total); $('[data-pay-amount]').textContent = fmt(t.total); q.closest('.rpos-line').querySelector('.lt').textContent = fmt(cart[i].qty * cart[i].price); return; }
            var cs = e.target.closest('[data-customer-search]');
            if (cs) { var term = cs.value.toLowerCase(); $$('[data-select-customer][data-search]').forEach(function (b) { b.style.display = b.dataset.search.indexOf(term) !== -1 ? '' : 'none'; }); return; }
            var ps = e.target.closest('[data-product-search]');
            if (ps) { filterGrid(); return; }
        });

        // Void / hold / fullscreen
        on('[data-void]', function () { if (!cart.length) return; if (confirm('Void this sale and clear the cart?')) resetSale(); });
        on('[data-hold]', function () {
            if (!cart.length) { toast('Cart is empty'); return; }
            var held = loadHeld();
            held.push({ ref: 'H' + Date.now().toString().slice(-6), at: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }), cart: cart.slice(), customer: customer, coupon: coupon, discount: discount, delivery: delivery, note: note, total: totals().total });
            saveHeld(held); refreshHeld(); resetSale(); toast('Sale held');
        });
        on('[data-fullscreen]', function () {
            document.body.classList.toggle('rpos-full');
            try { if (document.body.classList.contains('rpos-full')) document.documentElement.requestFullscreen(); else if (document.fullscreenElement) document.exitFullscreen(); } catch (e) {}
        });

        function resetSale() {
            cart = []; coupon = ''; discount = { type: 'amount', value: 0 }; delivery = { method: '', shipping: 0, status: 'delivered', address: '' }; note = '';
            setCustomer({ id: WALKIN.id, name: WALKIN.name, sub: 'Walk-in customer' });
            renderCart();
        }

        // ================= DIALOG ACTIONS =================
        on('[data-apply-coupon]', function () {
            var code = ($('[data-coupon-input]').value || '').trim();
            var msg = $('[data-coupon-msg]');
            if (!code) return;
            coupon = code; renderCart();
            var ok = totals().couponValid;
            msg.hidden = false; msg.textContent = ok ? 'Coupon applied.' : 'Coupon not found or inactive.'; msg.style.color = ok ? '#067647' : '#b42318';
            if (ok) { toast('Coupon applied'); close('rpos-coupon-dialog'); } else { coupon = ''; renderCart(); }
        });
        on('[data-clear-coupon]', function () { coupon = ''; $('[data-coupon-input]').value = ''; renderCart(); close('rpos-coupon-dialog'); });

        $$('[data-disc-type]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-disc-type]').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); }); });
        on('[data-apply-discount]', function () {
            var active = $('[data-disc-type].active');
            discount = { type: active ? active.dataset.discType : 'amount', value: clean($('[data-disc-value]').value) };
            renderCart(); close('rpos-discount-dialog'); toast('Discount applied');
        });
        on('[data-clear-discount]', function () { discount = { type: 'amount', value: 0 }; $('[data-disc-value]').value = '0'; renderCart(); close('rpos-discount-dialog'); });

        var dmSel = $('[data-delivery-method]');
        if (dmSel) dmSel.addEventListener('change', function () { var o = dmSel.selectedOptions[0]; $('[data-delivery-shipping]').value = Number(o && o.dataset.price || 0).toFixed(2); });
        on('[data-apply-delivery]', function () {
            delivery = { method: dmSel ? dmSel.value : '', shipping: clean($('[data-delivery-shipping]').value), status: $('[data-delivery-status]').value, address: $('[data-delivery-address]').value };
            renderCart(); close('rpos-delivery-dialog'); toast('Delivery saved');
        });

        on('[data-apply-note]', function () { note = $('[data-note-input]').value; close('rpos-note-dialog'); toast('Note saved'); });

        // Quick create customer
        on('[data-create-customer]', function (btn) {
            var first = $('[data-newc-first]').value.trim();
            var err = $('[data-newc-error]');
            if (!first) { err.hidden = false; err.textContent = 'First name is required.'; return; }
            btn.disabled = true; btn.textContent = 'Creating...';
            var body = new FormData();
            body.append('tenant_id', TENANT);
            body.append('first_name', first);
            body.append('last_name', $('[data-newc-last]').value.trim());
            body.append('phone', $('[data-newc-phone]').value.trim());
            body.append('email', $('[data-newc-email]').value.trim());
            body.append('_token', $('meta[name=csrf-token]').content);
            fetch(root.dataset.quickCustomerUrl, { method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }, body: body })
                .then(function (r) { if (!r.ok) throw new Error('failed'); return r.json(); })
                .then(function (c) {
                    setCustomer({ id: String(c.id), name: c.name, sub: c.phone || 'New customer' });
                    ['[data-newc-first]', '[data-newc-last]', '[data-newc-phone]', '[data-newc-email]'].forEach(function (s) { $(s).value = ''; });
                    err.hidden = true; close('rpos-customer-dialog'); toast('Customer created: ' + c.name);
                })
                .catch(function () { err.hidden = false; err.textContent = 'Could not create customer. Check the details and try again.'; })
                .finally(function () { btn.disabled = false; btn.textContent = 'Create & select'; });
        });

        // ================= PAY =================
        on('[data-open-pay]', function () { if (!cart.length) return; openPay(); });
        function openPay() {
            var t = totals();
            pay.received = 0; pay.credit = false; pay.method = ''; pay.accountId = '';
            $$('[data-pay-method]').forEach(function (x) { x.classList.remove('active'); });
            $$('[data-payment-account]').forEach(function (x) { x.classList.remove('active'); });
            $('[data-pay-credit]').checked = false;
            $('[data-pay-total]').textContent = fmt(t.total);
            $('[data-pay-received]').value = '0.00';
            var err = $('[data-pay-error]'); if (err) err.hidden = true;
            buildQuickCash(t.total);
            syncPaymentAccounts();
            syncChange();
            var d = document.getElementById('rpos-pay-dialog'); if (d) d.showModal();
        }
        function buildQuickCash(total) {
            var wrap = $('[data-pay-quick]'); if (!wrap) return;
            var opts = [total];
            [total, Math.ceil(total / 1000) * 1000, Math.ceil(total / 5000) * 5000, Math.ceil(total / 500) * 500].forEach(function (v) { if (v > 0 && opts.indexOf(v) === -1) opts.push(v); });
            opts = opts.filter(function (v, i) { return opts.indexOf(v) === i; }).slice(0, 4);
            wrap.innerHTML = '';
            opts.forEach(function (v, i) {
                var b = document.createElement('button'); b.type = 'button';
                b.textContent = i === 0 ? 'Exact' : fmt(v).replace(CUR + ' ', '');
                b.addEventListener('click', function () { $('[data-pay-received]').value = commafy(v.toFixed(2)); pay.received = v; syncChange(); });
                wrap.appendChild(b);
            });
        }
        function syncChange() {
            pay.received = clean($('[data-pay-received]').value);
            var total = totals().total;
            var diff = pay.received - total;
            var status = $('[data-pay-status]'), label = $('[data-pay-status-label]'), amt = $('[data-pay-change]');
            if (status) status.classList.remove('short', 'over', 'exact');
            if (diff < -0.001) {
                if (status) status.classList.add('short'); if (label) label.textContent = 'Short by'; if (amt) amt.textContent = fmt(Math.abs(diff));
            } else if (diff > 0.001) {
                if (status) status.classList.add('over'); if (label) label.textContent = 'Change'; if (amt) amt.textContent = fmt(diff);
            } else {
                if (status) status.classList.add('exact'); if (label) label.textContent = 'Change'; if (amt) amt.textContent = fmt(0);
            }
            var err = $('[data-pay-error]');
            if (err && (diff >= -0.001 || pay.credit)) err.hidden = true;
        }
        if ($('[data-pay-received]')) {
            $('[data-pay-received]').addEventListener('input', syncChange);
            $('[data-pay-received]').addEventListener('blur', function () { this.value = commafy(this.value); });
        }
        $('[data-pay-credit]') && $('[data-pay-credit]').addEventListener('change', function () { pay.credit = this.checked; syncChange(); });
        $$('[data-pay-method]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-pay-method]').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); pay.method = b.dataset.payMethod; pay.accountId = ''; syncPaymentAccounts(); }); });
        $$('[data-payment-account]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-payment-account]').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); pay.accountId = b.dataset.accountId || ''; }); });
        function canonicalMethod(method) {
            method = String(method || '').toLowerCase();
            if (method.indexOf('card') !== -1 || method.indexOf('pos') !== -1) return 'card';
            if (method.indexOf('cheque') !== -1 || method.indexOf('check') !== -1) return 'cheque';
            if (method.indexOf('transfer') !== -1 || method.indexOf('bank') !== -1) return 'transfer';
            return 'cash';
        }
        function syncPaymentAccounts() {
            var wrap = $('[data-payment-account-wrap]');
            if (!wrap) return;
            var method = canonicalMethod(pay.method);
            var needsAccount = method !== 'cash';
            wrap.hidden = !needsAccount;
            var shown = [];
            $$('[data-payment-account]').forEach(function (b) {
                var match = canonicalMethod(b.dataset.accountMethod) === method;
                b.hidden = !match;
                b.classList.remove('active');
                if (match) shown.push(b);
            });
            var empty = $('[data-payment-account-empty]');
            if (empty) empty.hidden = !needsAccount || shown.length > 0;
            if (needsAccount && shown.length === 1) {
                shown[0].classList.add('active');
                pay.accountId = shown[0].dataset.accountId || '';
            } else if (!needsAccount) {
                pay.accountId = '';
            }
        }
        $$('[data-pay-keypad] [data-key]').forEach(function (b) {
            b.addEventListener('click', function () {
                var input = $('[data-pay-received]'); var k = b.dataset.key; var v = input.value.replace(/,/g, '');
                var isZeroValue = clean(v) === 0 && v.indexOf('.') !== -1;
                if (k === 'clear') v = '';
                else if (k === 'back') v = v.slice(0, -1);
                else if (k === '.') { if (v.indexOf('.') === -1) v = (v || '0') + '.'; }
                else v = (v === '0' || isZeroValue ? '' : v) + k;
                input.value = commafy(v); syncChange();
            });
        });

        function payError(msg) {
            var err = $('[data-pay-error]');
            if (err) { err.hidden = false; err.textContent = msg; err.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
        }
        on('[data-confirm-pay]', function (btn) {
            if (!cart.length) return;
            var t = totals();
            var received = clean($('[data-pay-received]').value);
            if (received > 0.001 && !pay.method) { payError('Select a payment method for the amount received.'); return; }
            if (!pay.credit && received < t.total - 0.001) {
                var status = $('[data-pay-status]'); if (status) { status.classList.remove('over', 'exact'); status.classList.add('short'); }
                payError('Short by ' + fmt(t.total - received) + '. Collect the full amount, or switch on "Credit sale (pay later)".');
                return;
            }
            if (pay.credit && (customer.id === WALKIN.id)) { payError('Select or create a customer before booking a credit sale.'); return; }
            if (canonicalMethod(pay.method) !== 'cash' && $('[data-payment-account]:not([hidden])') && !pay.accountId) {
                payError('Select the receiving account for this ' + String(pay.method || 'payment').toUpperCase() + ' payment.');
                return;
            }

            var f = $('[data-pos-form]');
            $('[data-f-customer]', f).value = customer.id;
            $('[data-f-method]', f).value = pay.method || 'Cash';
            $('[data-f-payment-account]', f).value = pay.accountId || '';
            $('[data-f-paid]', f).value = received;
            $('[data-f-coupon]', f).value = t.couponValid ? coupon : '';
            $('[data-f-disc-type]', f).value = discount.type;
            $('[data-f-disc-value]', f).value = discount.value;
            $('[data-f-delivery]', f).value = delivery.method || '';
            $('[data-f-shipping]', f).value = delivery.shipping || 0;
            $('[data-f-delivery-status]', f).value = delivery.status || 'delivered';
            $('[data-f-delivery-address]', f).value = delivery.address || '';
            $('[data-f-notes]', f).value = note || '';
            $('[data-f-credit]', f).value = pay.credit ? '1' : '0';
            var items = $('[data-f-items]', f); items.innerHTML = '';
            cart.forEach(function (it, i) {
                items.insertAdjacentHTML('beforeend',
                    '<input type="hidden" name="items[' + i + '][product_variant_id]" value="' + it.id + '">' +
                    '<input type="hidden" name="items[' + i + '][quantity]" value="' + it.qty + '">' +
                    '<input type="hidden" name="items[' + i + '][unit_price]" value="' + it.price.toFixed(2) + '">');
            });
            btn.disabled = true; btn.textContent = 'Processing...';
            f.submit();
        });

        // ================= PRODUCTS: pin, category, search =================
        var activeCat = 'pinned';
        $$('[data-cat]').forEach(function (b) { b.addEventListener('click', function () { $$('[data-cat]').forEach(function (x) { x.classList.remove('active'); }); b.classList.add('active'); activeCat = b.dataset.cat; filterGrid(); }); });

        function applyPinState() { $$('[data-tile]').forEach(function (tile) { var p = $('[data-pin]', tile); if (p) p.classList.toggle('pinned', pins.has(tile.dataset.variantId)); }); }
        function togglePin(tile) {
            var id = tile.dataset.variantId;
            if (pins.has(id)) pins.delete(id); else pins.add(id);
            savePins(); applyPinState();
            if (activeCat === 'pinned') filterGrid();
            toast(pins.has(id) ? 'Pinned' : 'Unpinned');
        }
        function filterGrid() {
            var q = ($('[data-product-search]').value || '').trim().toLowerCase();
            var tiles = $$('[data-tile]');
            var shown = 0;
            var pinnedEmptyFallback = (activeCat === 'pinned' && pins.size === 0 && !q);
            tiles.forEach(function (tile, idx) {
                var match;
                if (q) match = (tile.dataset.name + ' ' + tile.dataset.sku).toLowerCase().indexOf(q) !== -1;
                else if (pinnedEmptyFallback) match = idx < 12;
                else if (activeCat === 'pinned') match = pins.has(tile.dataset.variantId);
                else if (activeCat === 'all') match = true;
                else match = tile.dataset.categoryId === activeCat;
                tile.style.display = match ? '' : 'none';
                if (match) shown++;
            });
            var empty = $('[data-grid-empty]'); if (empty) empty.hidden = shown > 0;
        }

        // ================= HELD =================
        function refreshHeld() {
            var held = loadHeld();
            $('[data-held-count]').textContent = held.length;
            var list = $('[data-held-list]'); var emptyEl = $('[data-held-empty]');
            list.innerHTML = '';
            emptyEl.hidden = held.length > 0;
            held.forEach(function (h, idx) {
                var div = document.createElement('div'); div.className = 'held-item';
                div.innerHTML = '<span style="text-align:left;"><strong style="display:block;">' + esc(h.customer.name) + ' · ' + h.cart.length + ' item(s)</strong><span class="subtle">' + esc(h.ref) + ' · ' + esc(h.at) + ' · ' + fmt(h.total) + '</span></span>' +
                    '<span style="display:flex; gap:6px;"><button class="btn primary" type="button" data-recall="' + idx + '">Recall</button><button class="btn danger" type="button" data-delhold="' + idx + '">✕</button></span>';
                list.appendChild(div);
            });
        }
        document.addEventListener('click', function (e) {
            var rc = e.target.closest('[data-recall]');
            if (rc) {
                if (cart.length && !confirm('Replace the current cart with the held sale?')) return;
                var held = loadHeld(); var h = held[+rc.dataset.recall];
                if (h) { cart = h.cart; coupon = h.coupon || ''; discount = h.discount || { type: 'amount', value: 0 }; delivery = h.delivery || { method: '', shipping: 0, status: 'delivered', address: '' }; note = h.note || ''; setCustomer(h.customer); renderCart(); held.splice(+rc.dataset.recall, 1); saveHeld(held); refreshHeld(); close('rpos-held-dialog'); toast('Sale recalled'); }
                return;
            }
            var dh = e.target.closest('[data-delhold]');
            if (dh) { var l = loadHeld(); l.splice(+dh.dataset.delhold, 1); saveHeld(l); refreshHeld(); return; }
        });

        // ================= TILL CLOSE RECONCILIATION =================
        function updateTillVariance(form) {
            if (!form) return;
            var hasVariance = false, totalVar = 0;
            $$('[data-till-actual]', form).forEach(function (input) {
                var variance = clean(input.value) - clean(input.dataset.expected);
                totalVar += variance;
                var off = Math.round(variance * 100) !== 0;
                var row = input.closest('tr');
                var output = row && row.querySelector('[data-till-variance]');
                if (output) { output.value = (variance < 0 ? '-' : '') + fmt(Math.abs(variance)); output.classList.toggle('bad', off); output.classList.toggle('ok', !off); }
                if (off) hasVariance = true;
            });
            var btn = form.querySelector('[data-till-close-button]');
            if (btn) btn.dataset.hasVariance = hasVariance ? '1' : '0';
            var box = form.querySelector('[data-book-variance]');
            if (box) {
                box.hidden = !hasVariance;
                var amt = box.querySelector('[data-variance-amount]');
                if (amt) amt.textContent = (totalVar < 0 ? '-' : '+') + fmt(Math.abs(totalVar)) + (totalVar < 0 ? ' (short)' : ' (over)');
                if (!hasVariance) { var chk = box.querySelector('[data-book-variance-check]'); if (chk) chk.checked = false; }
            }
            var warn = form.querySelector('[data-till-close-warning]');
            if (warn) warn.hidden = true;
        }
        document.addEventListener('input', function (e) {
            var a = e.target.closest('[data-till-actual]');
            if (a) updateTillVariance(a.closest('[data-till-close-form]'));
        });
        document.addEventListener('submit', function (e) {
            var form = e.target.closest('[data-till-close-form]');
            if (!form) return;
            updateTillVariance(form);
            var btn = form.querySelector('[data-till-close-button]');
            var booked = form.querySelector('[data-book-variance-check]');
            if (btn && btn.dataset.hasVariance === '1' && !(booked && booked.checked)) {
                e.preventDefault();
                var box = form.querySelector('[data-book-variance]');
                if (box) { box.hidden = false; box.scrollIntoView({ behavior: 'smooth', block: 'center' }); }
                toast('Recount to balance, or tick "book the variance" to close.');
            }
        });

        // helpers
        function on(sel, fn) { var el = $(sel); if (el) el.addEventListener('click', function () { fn(el); }); }
        function close(id) { var d = document.getElementById(id); if (d && d.open) d.close(); }

        // ================= POST-SALE SUCCESS =================
        var successDlg = document.getElementById('rpos-success-dialog');
        if (successDlg && typeof successDlg.showModal === 'function') {
            setTimeout(function () { try { successDlg.showModal(); } catch (e) {} }, 160);
            var orderId = successDlg.dataset.successOrder;
            var printBtn = successDlg.querySelector('[data-success-print]');
            var emailBtn = successDlg.querySelector('[data-success-email]');
            var newBtn = successDlg.querySelector('[data-success-newsale]');
            if (printBtn) printBtn.addEventListener('click', function () {
                var r = document.getElementById('sales-receipt-' + orderId);
                if (r) { successDlg.close(); try { r.showModal(); } catch (e) {} }
            });
            if (newBtn) newBtn.addEventListener('click', function () { successDlg.close(); });
            if (emailBtn) emailBtn.addEventListener('click', function () {
                var em = emailBtn.dataset.customerEmail;
                toast(em ? 'Receipt will be emailed to ' + em : 'No email on file for this customer.');
            });
        }

        // ---- Init ----
        applyPinState();
        filterGrid();
        renderCart();
        refreshHeld();
        var tillForm = $('[data-till-close-form]');
        if (tillForm) updateTillVariance(tillForm);
    });
})();
