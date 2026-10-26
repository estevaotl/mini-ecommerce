(() => {
    'use strict';
    const $ = (s, root = document) => root.querySelector(s);
    const $$ = (s, root = document) => [...root.querySelectorAll(s)];
    const money = value => new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(Number(value));
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const icon = name => `<svg class="icon" aria-hidden="true" viewBox="0 0 24 24"><use href="#i-${name}"/></svg>`;
    const readData = id => { try { return JSON.parse(document.getElementById(id)?.textContent || 'null'); } catch { return null; } };
    const products = readData('catalog-data') || [];
    let cart = readData('initial-cart');
    let toastTimer;
    let favorites = [];
    try { favorites = JSON.parse(localStorage.getItem('nuvix-favorites') || '[]'); if (!Array.isArray(favorites)) favorites = []; } catch { favorites = []; }
    const params = new URLSearchParams(location.search);
    let category = params.get('categoria') || 'Todas';
    let search = params.get('busca') || '';
    let favoriteOnly = params.get('favoritos') === '1';

    function toast(message) {
        const element = $('[data-toast]');
        element.textContent = message; element.hidden = false;
        clearTimeout(toastTimer); toastTimer = setTimeout(() => { element.hidden = true; }, 4000);
    }
    async function api(url, data) {
        let response;
        try { response = await fetch(url, { method: data === undefined ? 'GET' : 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }, ...(data !== undefined ? { body: JSON.stringify(data) } : {}) }); }
        catch { throw new Error('Não foi possível conectar à loja. Confira a conexão e tente novamente.'); }
        let result;
        try { result = await response.json(); } catch { throw new Error('A loja não conseguiu responder. Tente novamente.'); }
        if (!response.ok || result.sucesso === false) {
            const error = new Error(result.mensagem || 'Não foi possível concluir. Tente novamente.');
            error.fields = result.erros || null;
            throw error;
        }
        return result;
    }
    function feedback(element, message, success = false) {
        if (!element) return;
        element.textContent = message; element.classList.toggle('is-success', success);
    }
    const checkoutForm = $('[data-checkout-form]');
    const fieldMessages = {
        nome: 'Informe o nome de quem vai receber.', email: 'Informe um e-mail válido, como voce@exemplo.com.',
        cep: 'Informe um CEP com 8 números.', endereco: 'Informe a rua ou avenida.',
        numero: 'Informe o número ou s/n.', cidade: 'Informe a cidade.', uf: 'Informe a sigla de um estado brasileiro, como SP.'
    };
    const states = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    let deliverySubmitted = false;
    function fieldError(input, message = '') {
        const element = $(`[data-field-error="${input.name}"]`, checkoutForm);
        if (!element) return;
        element.textContent = message; element.hidden = !message;
        if (message) input.setAttribute('aria-invalid', 'true'); else input.removeAttribute('aria-invalid');
    }
    function validateField(input) {
        if (!fieldMessages[input.name]) return true;
        let valid = input.value.trim() !== '' && input.validity.valid;
        if (input.name === 'uf') valid = states.includes(input.value.trim().toUpperCase());
        fieldError(input, valid ? '' : fieldMessages[input.name]);
        return valid;
    }
    function validationSummary(focus = false) {
        if (!checkoutForm) return;
        const summary = $('[data-validation-summary]', checkoutForm);
        const links = $('[data-validation-links]', summary); links.replaceChildren();
        $$('input[aria-invalid="true"]', checkoutForm).forEach(input => {
            const item = document.createElement('li'); const link = document.createElement('a');
            link.href = `#${input.id}`; link.textContent = $(`[data-field-error="${input.name}"]`, checkoutForm).textContent;
            link.addEventListener('click', event => { event.preventDefault(); input.focus(); });
            item.append(link); links.append(item);
        });
        summary.hidden = !links.children.length;
        if (focus && !summary.hidden) { summary.focus(); summary.scrollIntoView({ block: 'center', behavior: 'instant' }); checkoutStep(2); }
    }
    if (checkoutForm) {
        // Sem JavaScript, os atributos HTML mantêm a validação nativa.
        checkoutForm.noValidate = true;
        $$('input', checkoutForm).forEach(input => {
            input.addEventListener('blur', () => {
                input.value = input.value.trim(); if (input.name === 'uf') input.value = input.value.toUpperCase();
                validateField(input); if (deliverySubmitted) validationSummary();
            });
            input.addEventListener('input', () => {
                if (input.getAttribute('aria-invalid') === 'true') validateField(input);
                if (deliverySubmitted) validationSummary();
            });
        });
    }
    function openDialog(dialog) { if (dialog && !dialog.open) dialog.showModal(); }
    $$('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    $$('dialog').forEach(dialog => dialog.addEventListener('click', event => {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        if (event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    }));
    $('[data-open-search]')?.addEventListener('click', () => openDialog($('#search-dialog')));
    $('[data-open-cart]')?.addEventListener('click', () => { openDialog($('#cart-dialog')); if (!cart) loadCart(); });
    $$('[data-open-product]').forEach(link => link.addEventListener('click', event => {
        const dialog = document.getElementById(`product-${link.dataset.openProduct}`);
        if (dialog) {
            event.preventDefault();
            if (link.dataset.productColor) {
                $('[data-product-form]', dialog)?.dispatchEvent(new CustomEvent('nuvix:select-color', { detail: { color: link.dataset.productColor } }));
            }
            openDialog(dialog);
        }
    }));
    $$('[data-size-guide]').forEach(link => link.addEventListener('click', event => { event.preventDefault(); openDialog($('#size-dialog')); }));
    $$('[data-open-gallery]').forEach(button => button.addEventListener('click', () => openDialog(document.getElementById(button.dataset.openGallery))));
    $$('[data-toggle-photo-zoom]').forEach(button => button.addEventListener('click', () => {
        const dialog = button.closest('dialog');
        const zoomed = dialog.dataset.zoomed !== 'true';
        dialog.dataset.zoomed = String(zoomed);
        button.setAttribute('aria-pressed', String(zoomed));
        button.innerHTML = `${icon('zoom')} ${zoomed ? 'Mostrar foto inteira' : 'Ampliar detalhes'}`;
    }));

    function updateFavorites() {
        const count = favorites.length;
        $$('[data-favorites-count]').forEach(element => { element.textContent = count; element.hidden = count === 0; });
        $('[data-show-favorites]')?.setAttribute('aria-label', `Ver favoritos, ${count} ${count === 1 ? 'peça salva' : 'peças salvas'}`);
        $$('[data-favorite]').forEach(button => {
            const selected = favorites.includes(Number(button.dataset.favorite));
            button.setAttribute('aria-pressed', String(selected));
            const product = products.find(p => Number(p.id) === Number(button.dataset.favorite));
            button.setAttribute('aria-label', `${selected ? 'Remover dos favoritos' : 'Favoritar'} ${product?.nome || 'peça'}`);
        });
    }
    $$('[data-favorite]').forEach(button => button.addEventListener('click', () => {
        const id = Number(button.dataset.favorite);
        const wasFavorite = favorites.includes(id);
        favorites = wasFavorite ? favorites.filter(f => f !== id) : [...favorites, id];
        try { localStorage.setItem('nuvix-favorites', JSON.stringify(favorites)); } catch { /* A sessão continua mesmo sem armazenamento local. */ }
        updateFavorites(); filterCards(); toast(wasFavorite ? 'Peça removida dos favoritos.' : 'Peça salva nos favoritos.');
    }));
    $('[data-show-favorites]')?.addEventListener('click', () => {
        if (!$('[data-product-grid]')) { location.href = '/colecao?favoritos=1'; return; }
        favoriteOnly = !favoriteOnly; category = 'Todas'; search = ''; filterCards(); $('#colecao')?.scrollIntoView({ behavior: 'smooth' });
    });
    function filterCards() {
        const cards = $$('[data-card-id]');
        if (!cards.length) return;
        const normalize = s => s.toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        let visible = 0;
        cards.forEach(card => {
            const match = (category === 'Todas' || card.dataset.category === category) && normalize(card.dataset.name).includes(normalize(search)) && (!favoriteOnly || favorites.includes(Number(card.dataset.cardId)));
            card.hidden = !match; if (match) visible++;
        });
        $$('.filter-button[data-category]').forEach(button => { const selected = button.dataset.category === category; button.classList.toggle('is-active', selected); button.setAttribute('aria-pressed', String(selected)); });
        const summary = $('[data-search-summary]');
        if (summary) {
            summary.hidden = !search && !favoriteOnly;
            summary.replaceChildren();
            summary.append(document.createTextNode(favoriteOnly ? 'Suas peças favoritas' : `Resultados para “${search}”`));
            const clear = document.createElement('button'); clear.type = 'button'; clear.textContent = 'Limpar filtros'; clear.addEventListener('click', clearFilters); summary.append(clear);
        }
        $('[data-show-favorites]')?.setAttribute('aria-pressed', String(favoriteOnly));
        const empty = $('[data-filter-empty]'); if (empty) empty.hidden = visible !== 0;
        const count = $('[data-result-count]'); if (count) count.textContent = `${visible} ${visible === 1 ? 'peça para chamar de sua' : 'peças para chamar de suas'}.`;
    }
    function clearFilters() { category = 'Todas'; search = ''; favoriteOnly = false; filterCards(); history.replaceState(null, '', location.pathname); }
    $$('.filter-button[data-category]').forEach(button => button.addEventListener('click', () => { category = button.dataset.category; filterCards(); }));
    $('[data-clear-filters]')?.addEventListener('click', clearFilters);
    $('[data-sort]')?.addEventListener('change', event => {
        const grid = $('[data-product-grid]');
        const cards = $$('[data-card-id]', grid);
        const order = event.target.value;
        cards.sort((a, b) => order === 'price-asc' ? Number(a.dataset.price) - Number(b.dataset.price) : order === 'price-desc' ? Number(b.dataset.price) - Number(a.dataset.price) : order === 'name' ? a.dataset.name.localeCompare(b.dataset.name, 'pt-BR') : products.findIndex(p => Number(p.id) === Number(a.dataset.cardId)) - products.findIndex(p => Number(p.id) === Number(b.dataset.cardId)));
        cards.forEach(card => grid.append(card));
    });
    updateFavorites(); filterCards();

    function photoStyle(photo) {
        const cols = Number(photo.colunas) || 1, rows = Number(photo.linhas) || 1, position = Number(photo.posicao) || 0;
        const x = position % cols, y = Math.floor(position / cols);
        return `background-image:url('${escape(photo.imagem)}');--photo-width:${cols * 100}%;--photo-height:${rows * 100}%;--photo-x:${cols > 1 ? x / (cols - 1) * 100 : 50}%;--photo-y:${rows > 1 ? y / (rows - 1) * 100 : 50}%;--zoom-width:${cols * 150}%;--zoom-height:${rows * 150}%;--zoom-x:${((x + .5) * 1.5 - .5) / (cols * 1.5 - 1) * 100}%;--zoom-y:${((y + .5) * 1.5 - .5) / (rows * 1.5 - 1) * 100}%;`;
    }
    function setPhoto(element, photo, product) {
        element.classList.add('tile-photo'); element.style.cssText = photoStyle(photo);
        element.setAttribute('aria-label', `${product.nome}, ${photo.nome.toLowerCase()} na cor ${photo.cor}, imagem ilustrativa`);
    }

    $$('[data-product-form]').forEach(form => {
        const product = products.find(p => Number(p.id) === Number(form.dataset.productForm));
        if (!product) return;
        const items = product.itens;
        const requestedColor = params.get('cor');
        let color = items.some(i => i.cor === requestedColor && i.estoque > 0) ? requestedColor : items.find(i => i.estoque > 0)?.cor || items[0]?.cor;
        let size = items.length && items.every(i => i.tamanho === 'Único') ? 'Único' : null;
        let busy = false;
        let photoIndex = 0, photoColor = null;
        const colors = [...new Set(items.map(i => i.cor))];
        function renderGallery() {
            const photos = (product.galeria || []).filter(photo => photo.cor === color);
            if (!photos.length) return;
            if (photoColor !== color) { photoIndex = 0; photoColor = color; }
            const photo = photos[photoIndex] || photos[0];
            $$(`[data-product-image="${product.id}"]`).forEach(element => setPhoto(element, photo, product));
            $$(`[data-gallery-caption="${product.id}"]`).forEach(element => { element.textContent = `${photo.nome} na cor ${color}`; });
            const thumbs = $(`[data-gallery-thumbs="${product.id}"]`);
            if (!thumbs) return;
            thumbs.replaceChildren();
            photos.forEach((photo, index) => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'gallery-thumb';
                button.setAttribute('aria-label', `Ver ${photo.nome.toLowerCase()} na cor ${color}`); button.setAttribute('aria-pressed', String(index === photoIndex));
                const image = document.createElement('span'); image.className = 'product-photo'; image.setAttribute('aria-hidden', 'true'); setPhoto(image, photo, product);
                button.append(image); button.addEventListener('click', () => { photoIndex = index; renderGallery(); thumbs.children[index]?.focus(); }); thumbs.append(button);
            });
        }
        form.addEventListener('nuvix:select-color', event => {
            const next = event.detail?.color;
            if (busy || !items.some(i => i.cor === next && i.estoque > 0)) return;
            color = next;
            if (!items.some(i => i.cor === color && i.tamanho === size && i.estoque > 0)) size = null;
            feedback($('[data-product-feedback]', form), '');
            render();
        });
        function selected() { return items.find(i => i.cor === color && i.tamanho === size && i.estoque > 0); }
        function render() {
            $('[data-color-name]', form).textContent = color || '';
            $('[data-colors]', form).replaceChildren();
            colors.forEach(c => {
                const variant = items.find(i => i.cor === c);
                const button = document.createElement('button'); button.type = 'button'; button.className = 'color-choice'; button.classList.toggle('is-selected', c === color); button.setAttribute('aria-label', c); button.setAttribute('aria-pressed', String(c === color)); button.disabled = busy || !items.some(i => i.cor === c && i.estoque > 0);
                const swatch = document.createElement('span'); swatch.style.setProperty('--swatch', variant.hex); button.append(swatch);
                button.addEventListener('click', () => { color = c; if (!items.some(i => i.cor === c && i.tamanho === size && i.estoque > 0)) size = null; feedback($('[data-product-feedback]', form), ''); render(); $('[data-colors]', form).querySelector(`[aria-label="${CSS.escape(c)}"]`)?.focus(); });
                $('[data-colors]', form).append(button);
            });
            $('[data-sizes]', form).replaceChildren();
            [...new Set(items.filter(i => i.cor === color).map(i => i.tamanho))].forEach(s => {
                const button = document.createElement('button'); button.type = 'button'; button.className = 'size-choice'; button.classList.toggle('is-selected', s === size); button.textContent = s; button.setAttribute('aria-label', `Tamanho ${s}`); button.setAttribute('aria-pressed', String(s === size)); button.disabled = busy || !items.some(i => i.cor === color && i.tamanho === s && i.estoque > 0);
                button.addEventListener('click', () => { size = s; feedback($('[data-product-feedback]', form), ''); render(); $('[data-sizes]', form).querySelector(`[aria-label="Tamanho ${CSS.escape(s)}"]`)?.focus(); });
                $('[data-sizes]', form).append(button);
            });
            const variant = selected();
            const available = $('[data-availability]', form);
            available.textContent = variant ? 'Sua versão está disponível.' : product.disponivel ? 'Escolha o tamanho para continuar.' : 'Esta peça está esgotada no momento.';
            available.style.color = product.disponivel ? '' : 'var(--error)';
            const quantity = $('input[name="quantidade"]', form); quantity.max = variant?.estoque || 1; quantity.disabled = busy || !variant;
            const button = $('[data-add-button]', form); button.disabled = busy || !variant; button.innerHTML = busy ? 'Adicionando…' : `Adicionar à sacola ${icon('bag')}`;
            form.setAttribute('aria-busy', String(busy));
            renderGallery();
            const detailLink = form.closest('.product-detail-copy')?.querySelector('.subtle-link');
            if (detailLink && color) detailLink.href = `/peca/${product.id}?cor=${encodeURIComponent(color)}`;
            if (variant) {
                const price = form.closest('.product-detail-copy')?.querySelector('.detail-price'); if (price) price.textContent = money(variant.preco);
                const guide = $('[data-size-guide]', form); if (guide) guide.hidden = size === 'Único';
            }
        }
        form.addEventListener('submit', async event => {
            event.preventDefault(); const variant = selected(); if (!variant || busy) return;
            const quantity = Number($('input[name="quantidade"]', form).value);
            busy = true; render(); feedback($('[data-product-feedback]', form), '');
            try {
                const result = await api('/carrinho/adicionar', { idItem: variant.id, quantidade: quantity }); cart = result.sacola; renderCart();
                form.closest('dialog')?.close(); openDialog($('#cart-dialog')); toast('Sua peça foi adicionada à sacola.');
            } catch (error) { feedback($('[data-product-feedback]', form), error.message); }
            finally { busy = false; render(); }
        });
        render();
    });

    function photo(item) {
        if (!item.imagem) return `<div class="product-photo" role="img" aria-label="${escape(item.nome)}">${icon('shirt')}</div>`;
        if (item.foto) return `<div class="product-photo tile-photo" role="img" aria-label="${escape(item.nome)}, ${escape(item.cor)}, imagem ilustrativa" style="${photoStyle(item.foto)}"></div>`;
        return `<div class="product-photo ${item.imagem.endsWith('catalogo.webp') ? `atlas atlas-${item.imagem_posicao}` : ''}" role="img" aria-label="${escape(item.nome)}, imagem ilustrativa" style="background-image:url('${escape(item.imagem)}')"></div>`;
    }
    function itemHTML(item) {
        return `<article class="cart-item" data-cart-id="${item.idItem}"><a class="cart-item-photo" href="/peca/${item.idProduto}" aria-label="Ver ${escape(item.nome)}">${photo(item)}</a><div class="cart-item-copy"><h3>${escape(item.nome)}</h3><p>${escape(item.cor)} / ${escape(item.tamanho)}</p><strong class="cart-item-price">${money(item.total)}</strong>${!item.disponivel ? '<p class="form-feedback">Estoque insuficiente. Ajuste ou remova esta peça.</p>' : ''}<div class="cart-item-actions"><div class="qty-control"><button type="button" data-quantity-change="${item.idItem}" data-next="${item.quantidade - 1}" aria-label="Diminuir quantidade de ${escape(item.nome)}">${icon('minus')}</button><span aria-label="Quantidade">${item.quantidade}</span><button type="button" data-quantity-change="${item.idItem}" data-next="${item.quantidade + 1}" ${item.quantidade >= item.estoque ? 'disabled' : ''} aria-label="Aumentar quantidade de ${escape(item.nome)}">${icon('plus')}</button></div><button type="button" class="remove-item" data-quantity-change="${item.idItem}" data-next="0" aria-label="Remover ${escape(item.nome)} da sacola">Remover</button></div></div></article>`;
    }
    function summaryHTML() {
        return `<div class="summary-line"><span>Subtotal (${cart.quantidade} ${cart.quantidade === 1 ? 'peça' : 'peças'})</span><strong>${money(cart.subtotal)}</strong></div><div class="summary-line"><span>Frete</span><strong>${cart.frete === 0 ? 'Grátis' : money(cart.frete)}</strong></div>${cart.desconto ? `<div class="summary-line"><span>Cupom ${escape(cart.cupom.codigo)}</span><strong>− ${money(cart.desconto)}</strong></div>` : ''}<div class="summary-line total"><span>Total</span><strong>${money(cart.total)}</strong></div>`;
    }
    function renderCart() {
        if (!cart) return;
        $$('[data-cart-count]').forEach(element => { element.textContent = cart.quantidade; });
        $('[data-open-cart]')?.setAttribute('aria-label', `Abrir sacola, ${cart.quantidade} ${cart.quantidade === 1 ? 'peça' : 'peças'}`);
        const empty = `<div class="empty-state">${icon('bag')}<h3>Espaço para novas ideias.</h3><p>Sua sacola ainda está vazia. Que tal encontrar a sua próxima peça?</p><a class="button button-blue" href="/colecao">Explorar coleção</a></div>`;
        $('[data-cart-items]').innerHTML = cart.itens.length ? cart.itens.map(itemHTML).join('') : empty;
        $('[data-cart-summary]').hidden = !cart.itens.length;
        if (cart.itens.length) $('[data-cart-summary]').innerHTML = `<div class="free-shipping">${cart.faltaFreteGratis > 0 ? `Faltam ${money(cart.faltaFreteGratis)} para ganhar frete grátis.` : 'Sua sacola ganhou frete grátis.'}<div class="shipping-progress"><span style="width:${Math.min(100, cart.subtotal / 200.01 * 100)}%"></span></div></div>${summaryHTML()}<a class="button button-blue" href="/carrinho">Continuar para entrega ${icon('arrow')}</a>`;
        const checkoutItems = $('[data-checkout-items]');
        if (checkoutItems) {
            checkoutItems.innerHTML = cart.itens.length ? cart.itens.map(itemHTML).join('') : empty;
            $('[data-checkout-form]').hidden = !cart.itens.length;
            $('.order-summary').hidden = !cart.itens.length;
            $('[data-checkout-summary]').innerHTML = summaryHTML();
            $('[data-confirm-order]').disabled = !cart.itens.every(i => i.disponivel);
            const mobileBar = $('[data-checkout-mobile-bar]');
            if (mobileBar) mobileBar.hidden = !cart.itens.length;
            if ($('[data-checkout-total]')) $('[data-checkout-total]').textContent = money(cart.total);
            $('.checkout-progress')?.toggleAttribute('hidden', !cart.itens.length);
        }
    }
    function checkoutStep(step) {
        $$('[data-checkout-step]').forEach(link => {
            if (link.dataset.checkoutStep === String(step)) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        });
    }
    $$('[data-checkout-step]').forEach(link => link.addEventListener('click', () => checkoutStep(link.dataset.checkoutStep)));
    $('[data-checkout-form]')?.addEventListener('focusin', () => checkoutStep(2));
    $('[data-checkout-items]')?.addEventListener('focusin', () => checkoutStep(1));
    $('.order-summary')?.addEventListener('focusin', () => checkoutStep(3));
    $('[data-confirm-order]')?.addEventListener('focus', () => checkoutStep(3));
    $('[data-review-order]')?.addEventListener('click', () => checkoutStep(3));
    async function loadCart() {
        try { cart = await api('/carrinho/estado'); renderCart(); }
        catch (error) { feedback($('[data-cart-feedback]'), error.message); }
    }
    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-quantity-change]');
        if (!button || button.disabled) return;
        const parent = button.closest('[data-cart-id]');
        const id = Number(button.dataset.quantityChange), next = Number(button.dataset.next);
        $$('button', parent).forEach(b => { b.disabled = true; });
        feedback($('[data-cart-feedback]'), ''); feedback($('[data-checkout-feedback]'), '');
        try {
            const result = await api('/carrinho/atualizar', { idItem: id, quantidade: next }); cart = result.sacola; renderCart();
            const root = $('#cart-dialog').open ? $('#cart-dialog') : document;
            root.querySelector(`[data-quantity-change="${id}"][data-next="${next + 1}"]`)?.focus();
            if (next === 0) toast('Peça removida da sacola.');
        } catch (error) { toast(error.message); renderCart(); }
    });
    $('[data-coupon-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form = event.currentTarget, button = $('button', form); button.disabled = true; button.textContent = 'Aplicando…';
        try { const result = await api('/carrinho/cupom', { codigo: $('input[name="codigo"]', form).value }); cart = result.sacola; renderCart(); $('input', form).removeAttribute('aria-invalid'); feedback($('[data-coupon-feedback]'), cart.cupom ? 'Cupom aplicado à sua sacola.' : 'Cupom removido.', true); }
        catch (error) { $('input', form).setAttribute('aria-invalid', 'true'); feedback($('[data-coupon-feedback]'), error.message); }
        finally { button.disabled = false; button.textContent = 'Aplicar'; }
    });
    $('#coupon-code')?.addEventListener('input', event => { event.target.removeAttribute('aria-invalid'); feedback($('[data-coupon-feedback]'), ''); });
    $('[data-lookup-cep]')?.addEventListener('click', async event => {
        const form = $('[data-checkout-form]'); const cep = $('input[name="cep"]', form).value.replace(/\D/g, '');
        if (!validateField(form.elements.cep)) { feedback($('[data-cep-feedback]'), ''); form.elements.cep.focus(); if (deliverySubmitted) validationSummary(); return; }
        const button = event.currentTarget; button.disabled = true; button.textContent = 'Consultando…';
        try {
            const result = await api(`/carrinho/endereco/${cep}`);
            for (const name of ['endereco', 'cidade', 'uf']) form.elements[name].value = result[name === 'endereco' ? 'logradouro' : name];
            for (const name of ['cep', 'endereco', 'cidade', 'uf']) if (form.elements[name].getAttribute('aria-invalid') === 'true') validateField(form.elements[name]);
            if (deliverySubmitted) validationSummary();
            feedback($('[data-cep-feedback]'), result.logradouro ? 'Endereço encontrado. Confira os dados e informe o número.' : 'Cidade encontrada. Complete o endereço e o número.', true);
            form.elements[result.logradouro ? 'numero' : 'endereco'].focus();
        } catch (error) { feedback($('[data-cep-feedback]'), error.message); }
        finally { button.disabled = false; button.textContent = 'Consultar CEP'; }
    });
    $('[data-checkout-form]')?.addEventListener('submit', async event => {
        event.preventDefault(); const form = event.currentTarget, button = $('[data-confirm-order]');
        if (button.disabled) return;
        deliverySubmitted = true;
        const valid = $$('input', form).map(validateField).every(Boolean);
        validationSummary(!valid); if (!valid) return;
        feedback($('[data-checkout-feedback]'), ''); button.disabled = true; button.textContent = 'Registrando pedido…'; form.setAttribute('aria-busy', 'true');
        try { const result = await api('/carrinho/finalizar', Object.fromEntries(new FormData(form))); location.href = result.redirect; }
        catch (error) {
            if (error.fields) {
                for (const [name, message] of Object.entries(error.fields)) if (fieldMessages[name]) fieldError(form.elements[name], message);
                validationSummary(true);
            } else { const element = $('[data-checkout-feedback]'); feedback(element, error.message); element.focus(); }
            button.disabled = false; button.innerHTML = `Confirmar pedido ${icon('arrow')}`;
        }
        finally { form.setAttribute('aria-busy', 'false'); }
    });
    if (cart) renderCart(); else loadCart();
})();
