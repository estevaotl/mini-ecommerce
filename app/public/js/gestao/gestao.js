(() => {
    'use strict';
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
    const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    const icon = name => `<svg class="g-icon" aria-hidden="true" viewBox="0 0 24 24"><use href="#g-${name}"/></svg>`;
    const money = value => new Intl.NumberFormat('pt-BR', {style:'currency',currency:'BRL'}).format(Number(value));
    const read = id => { try { return JSON.parse(document.getElementById(id)?.textContent || '[]'); } catch { return []; } };
    const products = read('admin-products'), coupons = read('admin-coupons'), orders = read('admin-orders');
    const normalize = value => String(value).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR');
    function feedback(element, message) { if (element) element.textContent = message; }
    function open(dialog) { if (dialog && !dialog.open) dialog.showModal(); }
    $$('[data-close-dialog]').forEach(button => button.addEventListener('click', () => button.closest('dialog').close()));
    $$('dialog').forEach(dialog => {
        dialog.addEventListener('cancel', event => { if ($('[aria-busy=true]', dialog)) event.preventDefault(); });
        dialog.addEventListener('click', event => {
            if (event.target !== dialog || $('[aria-busy=true]', dialog)) return;
            const box = dialog.getBoundingClientRect();
            if (event.clientX < box.left || event.clientX > box.right || event.clientY < box.top || event.clientY > box.bottom) dialog.close();
        });
    });
    $('[data-dismiss-banner]')?.addEventListener('click', event => event.currentTarget.closest('.success-banner').remove());
    $$('[data-expand]').forEach(button => button.addEventListener('click', () => {
        const row = document.getElementById(button.dataset.expand), expanded = button.getAttribute('aria-expanded') !== 'true';
        button.setAttribute('aria-expanded', String(expanded)); row.hidden = !expanded;
    }));
    $$('[data-managed-list]').forEach(list => {
        const entries = $$('[data-entry]', list), search = $('[data-list-search]', list), filters = $$('[data-filter-key]', list);
        const tabs = $$('[data-list-tab]', list); let group = tabs.find(tab => tab.getAttribute('aria-pressed') === 'true')?.dataset.listTab || 'todos';
        function render() {
            let count = 0;
            entries.forEach(entry => {
                const match = (group === 'todos' || entry.dataset.group === group) && normalize(entry.dataset.search).includes(normalize(search.value.trim())) && filters.every(select => !select.value || entry.dataset[select.dataset.filterKey] === select.value);
                entry.hidden = !match; if (match) count++;
            });
            tabs.forEach(tab => tab.setAttribute('aria-pressed', String(tab.dataset.listTab === group)));
            $('[data-result-count]', list).textContent = `${count} ${list.dataset.managedList === 'produtos' ? (count === 1 ? 'produto' : 'produtos') : list.dataset.managedList === 'cupons' ? (count === 1 ? 'cupom' : 'cupons') : (count === 1 ? 'pedido' : 'pedidos')}`;
            $('[data-list-empty]', list).hidden = count !== 0; $('[data-list-table]', list).hidden = count === 0;
        }
        tabs.forEach(tab => tab.addEventListener('click', () => { group = tab.dataset.listTab; render(); }));
        search.addEventListener('input', render); filters.forEach(select => select.addEventListener('change', render));
        $('[data-clear-list]', list)?.addEventListener('click', () => {
            if (!entries.length && list.dataset.managedList !== 'pedidos') { $(list.dataset.managedList === 'produtos' ? '[data-new-product]' : '[data-new-coupon]')?.click(); return; }
            search.value = ''; filters.forEach(select => { select.value = ''; }); group = tabs[0]?.dataset.listTab || 'todos'; render(); search.focus();
        });
        if (!entries.length && list.dataset.managedList !== 'pedidos') {
            const empty = $('[data-list-empty]', list);
            $('h3', empty).textContent = list.dataset.managedList === 'produtos' ? 'Seu catálogo começa aqui.' : 'Uma boa ideia para a próxima compra.';
            $('p', empty).textContent = list.dataset.managedList === 'produtos' ? 'Cadastre a primeira peça, com suas cores, tamanhos e estoque.' : 'Crie um cupom com código, desconto e valor mínimo.';
            $('[data-clear-list]', empty).textContent = list.dataset.managedList === 'produtos' ? 'Cadastrar produto' : 'Criar cupom';
        }
        $('[data-list-sort]', list)?.addEventListener('change', event => {
            const order = event.target.value;
            const sorted = [...entries].sort((a,b) => order === 'nome' ? a.dataset.nome.localeCompare(b.dataset.nome,'pt-BR') : order === 'estoque' ? Number(a.dataset.estoque) - Number(b.dataset.estoque) : Number(b.dataset.id) - Number(a.dataset.id));
            sorted.forEach(entry => $('[data-list-table]', list).append(entry));
        });
        render();
    });
    function setError(form, input, message) {
        const key = input.dataset.errorKey, error = $(`[data-field-error="${key}"]`, form);
        if (!error) return;
        error.textContent = message; error.hidden = !message;
        input.setAttribute('aria-describedby', error.id);
        if (message) input.setAttribute('aria-invalid','true'); else input.removeAttribute('aria-invalid');
    }
    function checkField(form, input) {
        if (!input.dataset.errorKey) return true;
        let message = '';
        if (input.required && !input.value.trim()) message = 'Preencha este campo.';
        else if (!input.validity.valid) {
            if (input.validity.stepMismatch) message = input.step === '1' ? 'Use uma quantidade inteira.' : 'Use até duas casas decimais.';
            else if (input.validity.rangeUnderflow) message = Number(input.min) > 0 ? `Use um valor de ${money(input.min)} ou maior.` : 'Use um valor a partir de zero.';
            else if (input.validity.rangeOverflow) message = 'Este valor está acima do limite do campo.';
            else message = 'Confira o valor informado.';
        }
        setError(form, input, message); return !message;
    }
    function bindValidation(form) {
        if (!form) return;
        form.noValidate = true;
        form.addEventListener('focusout', event => { if (event.target.matches('input,select,textarea')) checkField(form, event.target); });
        form.addEventListener('input', event => { if (event.target.getAttribute('aria-invalid') === 'true') checkField(form, event.target); });
        $$('[data-error-key]',form).forEach(input => { const error = $(`[data-field-error="${input.dataset.errorKey}"]`,form); if (error) input.setAttribute('aria-describedby',error.id); });
    }
    function validate(form) {
        const valid = $$('[data-error-key]',form).map(input => checkField(form,input)).every(Boolean);
        if (!valid) { feedback($('[data-editor-feedback]',form),'Confira os campos destacados.'); $('[aria-invalid=true]',form)?.focus(); }
        return valid;
    }
    function clearErrors(form) {
        $$('[data-field-error]',form).forEach(element => { element.hidden = true; element.textContent = ''; });
        $$('[aria-invalid]',form).forEach(input => input.removeAttribute('aria-invalid'));
        feedback($('[data-editor-feedback]',form),'');
    }
    async function api(url, method, data) {
        let response;
        try { response = await fetch(url,{method,headers:{'Content-Type':'application/json','Accept':'application/json'},...(data !== undefined ? {body:JSON.stringify(data)} : {})}); }
        catch { throw new Error('Não foi possível conectar. Confira a conexão e tente novamente.'); }
        let result; try { result = await response.json(); } catch { throw new Error('Não foi possível ler a resposta. Tente novamente.'); }
        if (!response.ok || result.sucesso === false) { const error = new Error(result.mensagem || 'Não foi possível salvar.'); error.fields = result.erros; throw error; }
        return result;
    }
    async function save(form, url, data, button) {
        if (form.getAttribute('aria-busy') === 'true') return;
        clearErrors(form); if (!validate(form)) return;
        const controls = $$('input,select,textarea,button',form), states = controls.map(control => control.disabled), label = button.innerHTML;
        form.setAttribute('aria-busy','true'); controls.forEach(control => { control.disabled = true; }); button.textContent = 'Salvando…';
        try { await api(url,'POST',data); form.closest('dialog').close(); location.reload(); }
        catch (error) {
            if (error.fields) for (const [key,message] of Object.entries(error.fields)) {
                const input = $(`[data-error-key="${key}"]`,form);
                if (input) setError(form,input,message);
                else { const target = $(`[data-field-error="${key}"]`,form); if (target) { target.hidden = false; target.textContent = message; } }
            }
            feedback($('[data-editor-feedback]',form),error.message);
        } finally {
            form.removeAttribute('aria-busy'); controls.forEach((control,index) => { control.disabled = states[index]; }); button.innerHTML = label;
            if ($('[aria-invalid=true]',form)) $('[aria-invalid=true]',form).focus();
            else if ($('[data-editor-feedback]',form).textContent) $('[data-editor-feedback]',form).focus();
        }
    }
    const productForm = $('[data-product-editor]'); let editingProduct = null, rowSequence = 0;
    function imagePreview() {
        if (!productForm) return;
        const value = productForm.elements.imagem.value, preview = $('[data-product-preview]',productForm);
        preview.className = 'image-preview'; preview.style.backgroundImage = ''; preview.innerHTML = value ? '' : icon('box');
        if (value.startsWith('demo:')) { preview.classList.add('atlas',`atlas-${value.split(':')[1]}`); preview.style.backgroundImage = "url('/assets/images/catalogo.webp')"; }
        else if (value) preview.style.backgroundImage = `url('${value}')`;
    }
    function reindexVariants() {
        $$('[data-variant-row]',productForm).forEach((row,index) => {
            $$('input',row).forEach(input => {
                const key = `itens.${index}.${input.name}`; input.dataset.errorKey = key;
                const error = $(`[data-variant-error="${input.name}"]`,row); error.dataset.fieldError = key; input.setAttribute('aria-describedby',error.id);
            });
        });
    }
    function addVariant(item = {}, focus = true) {
        const row = document.createElement('fieldset'); row.className = 'variant-row'; row.dataset.variantRow = ''; row.dataset.itemId = item.id || '';
        const sequence = ++rowSequence;
        const fields = [
            ['referencia','Referência','text',item.referencia || '', 'maxlength="200" placeholder="NUV-SOL-AM-M"'],
            ['cor','Cor','text',item.cor || '', 'maxlength="200" list="color-options" placeholder="Amarelo"'],
            ['tamanho','Tamanho','text',item.tamanho || '', 'maxlength="200" list="size-options" placeholder="M"'],
            ['preco','Preço (R$)','number',item.preco ?? '', 'min="0" max="99999999.99" step="0.01" inputmode="decimal" placeholder="0,00"'],
            ['estoque','Estoque','number',item.estoque ?? 0, 'min="0" max="2147483647" step="1" inputmode="numeric"']
        ];
        row.innerHTML = `<legend class="sr-only">Variação do produto</legend>${fields.map(([name,label,type,value,attributes]) => `<div><label for="variant-${sequence}-${name}">${label}</label><input id="variant-${sequence}-${name}" type="${type}" name="${name}" value="${escape(value)}" ${attributes} required><p class="field-error" id="variant-${sequence}-error-${name}" data-variant-error="${name}" hidden></p></div>`).join('')}<button class="icon-button" type="button" data-remove-variant aria-label="Remover variação ${escape(item.referencia || 'nova')}">${icon('trash')}</button>`;
        $('[data-variant-rows]',productForm).append(row); reindexVariants(); if (focus) $('input',row).focus();
    }
    function editProduct(product = null) {
        editingProduct = product; productForm.reset(); clearErrors(productForm); $('[data-variant-rows]',productForm).replaceChildren();
        $('#product-editor-title').textContent = product ? `Editar ${product.nome}` : 'Novo produto';
        for (const name of ['nome','categoria','descricao']) productForm.elements[name].value = product?.[name] || '';
        const image = product?.imagem === '/assets/images/catalogo.webp' ? `demo:${product.imagem_posicao}` : product?.imagem || '';
        $$('option[data-custom-image]',productForm).forEach(option => option.remove());
        if (image && ![...productForm.elements.imagem.options].some(option => option.value === image)) { const option = new Option('Imagem atual da peça',image); option.dataset.customImage = ''; productForm.elements.imagem.add(option); }
        productForm.elements.imagem.value = image; imagePreview();
        const items = product?.itens || []; if (items.length) items.forEach(item => addVariant(item,false)); else addVariant({},false);
        $('[data-save-product]',productForm).innerHTML = `${icon('check')} ${product ? 'Salvar alterações' : 'Salvar produto'}`;
        open($('#product-editor')); productForm.elements.nome.focus();
    }
    if (productForm) {
        bindValidation(productForm);
        $('[data-new-product]').addEventListener('click',() => editProduct());
        $$('[data-edit-product]').forEach(button => button.addEventListener('click',() => { const product = products.find(product => Number(product.id) === Number(button.dataset.editProduct)); if (product) editProduct(product); }));
        $('[data-add-variant]',productForm).addEventListener('click',() => addVariant());
        productForm.elements.imagem.addEventListener('change',imagePreview);
        productForm.addEventListener('click',event => {
            const button = event.target.closest('[data-remove-variant]'); if (!button) return;
            if ($$('[data-variant-row]',productForm).length === 1) { feedback($('[data-editor-feedback]',productForm),'Mantenha pelo menos uma variação para esta peça.'); $('[data-editor-feedback]',productForm).focus(); return; }
            const row = button.closest('[data-variant-row]'), next = row.nextElementSibling || row.previousElementSibling; row.remove(); reindexVariants(); $('input',next)?.focus();
        });
        productForm.addEventListener('submit',event => {
            event.preventDefault(); reindexVariants();
            const image = productForm.elements.imagem.value, data = {nome:productForm.elements.nome.value.trim(),categoria:productForm.elements.categoria.value.trim(),descricao:productForm.elements.descricao.value.trim(),imagem:image.startsWith('demo:') ? '/assets/images/catalogo.webp' : image,imagem_posicao:image.startsWith('demo:') ? Number(image.split(':')[1]) : 0,itens:$$('[data-variant-row]',productForm).map(row => ({idItem:row.dataset.itemId ? Number(row.dataset.itemId) : null,referencia:row.querySelector('[name=referencia]').value.trim(),cor:row.querySelector('[name=cor]').value.trim(),tamanho:row.querySelector('[name=tamanho]').value.trim(),preco:row.querySelector('[name=preco]').value,estoque:row.querySelector('[name=estoque]').value}))};
            save(productForm,editingProduct ? `/produtos/editar/${editingProduct.id}` : '/produtos/salvar',data,$('[data-save-product]',productForm));
        });
    }
    const couponForm = $('[data-coupon-editor]'); let editingCoupon = null;
    function editCoupon(coupon = null) {
        editingCoupon = coupon; couponForm.reset(); clearErrors(couponForm); $('#coupon-editor-title').textContent = coupon ? `Editar ${coupon.referencia}` : 'Novo cupom';
        for (const name of ['referencia','valorMinimo','valorDesconto']) couponForm.elements[name].value = coupon?.[name] ?? (name === 'valorMinimo' ? 0 : '');
        couponForm.elements.validade.value = coupon?.validade ? coupon.validade.slice(0,19).replace(' ','T') : '';
        $('[data-save-coupon]',couponForm).innerHTML = `${icon('check')} ${coupon ? 'Salvar alterações' : 'Salvar cupom'}`; open($('#coupon-editor')); couponForm.elements.referencia.focus();
    }
    if (couponForm) {
        bindValidation(couponForm); $('[data-new-coupon]').addEventListener('click',() => editCoupon());
        $$('[data-edit-coupon]').forEach(button => button.addEventListener('click',() => { const coupon = coupons.find(coupon => Number(coupon.id) === Number(button.dataset.editCoupon)); if (coupon) editCoupon(coupon); }));
        couponForm.addEventListener('submit',event => { event.preventDefault(); const data = Object.fromEntries(new FormData(couponForm)); save(couponForm,editingCoupon ? `/cupons/editar/${editingCoupon.id}` : '/cupons/salvar',data,$('[data-save-coupon]',couponForm)); });
    }
    let confirmationAction = null;
    $$('[data-admin-action]').forEach(button => button.addEventListener('click',() => {
        const id = Number(button.dataset.id), action = button.dataset.adminAction;
        const product = products.find(product => Number(product.id) === id), coupon = coupons.find(coupon => Number(coupon.id) === id);
        const actions = {
            'produto-arquivar': {title:'Arquivar esta peça?',copy:`${product?.nome} sairá da loja. As variações e os pedidos registrados serão mantidos. Você poderá recuperar a peça em Arquivados.`,label:'Arquivar produto',url:`/produtos/excluir/${id}`,method:'DELETE'},
            'produto-restaurar': {title:'Recuperar esta peça?',copy:`${product?.nome} voltará ao catálogo da loja com suas variações. Confira o estoque antes de receber novas compras.`,label:'Recuperar produto',url:`/produtos/restaurar/${id}`,method:'POST'},
            'cupom-desativar': {title:'Desativar este cupom?',copy:`O código ${coupon?.referencia} não poderá ser aplicado em novas compras. Os pedidos anteriores serão mantidos.`,label:'Desativar cupom',url:`/cupons/excluir/${id}`,method:'DELETE'},
            'cupom-reativar': {title:'Reativar este cupom?',copy:`O código ${coupon?.referencia} ficará habilitado novamente. A data de validade e o valor mínimo continuam valendo.`,label:'Reativar cupom',url:`/cupons/restaurar/${id}`,method:'POST'}
        };
        confirmationAction = actions[action]; if (!confirmationAction) return;
        $('#confirm-title').textContent = confirmationAction.title; $('[data-confirm-copy]').textContent = confirmationAction.copy; $('[data-confirm-submit]').textContent = confirmationAction.label;
        feedback($('[data-confirm-feedback]'),''); open($('#admin-confirm'));
    }));
    $('[data-confirm-submit]')?.addEventListener('click',async event => {
        if (!confirmationAction || event.currentTarget.disabled) return;
        const button = event.currentTarget, dialog = $('#admin-confirm'), controls = $$('button',dialog); controls.forEach(control => { control.disabled = true; }); button.setAttribute('aria-busy','true'); button.textContent = 'Salvando…';
        try { await api(confirmationAction.url,confirmationAction.method); dialog.close(); location.reload(); }
        catch (error) { feedback($('[data-confirm-feedback]'),error.message); $('[data-confirm-feedback]').focus(); }
        finally { controls.forEach(control => { control.disabled = false; }); button.removeAttribute('aria-busy'); button.textContent = confirmationAction.label; }
    });
    $$('[data-view-order]').forEach(button => button.addEventListener('click',() => {
        const order = orders.find(order => Number(order.id) === Number(button.dataset.viewOrder)); if (!order) return;
        $('#order-editor-title').textContent = `Pedido #${order.id}`;
        const date = String(order.dataPedido || '').split(' '); const dateParts = date[0].split('-');
        const label = dateParts.length === 3 ? `${dateParts[2]}/${dateParts[1]}/${dateParts[0]} ${date[1]?.slice(0,5) || ''}` : 'Data não informada';
        $('[data-order-content]').innerHTML = `<dl class="order-meta"><div><dt>Registrado em</dt><dd>${escape(label)}</dd></div><div><dt>Situação</dt><dd>${escape(order.status)}</dd></div><div><dt>Total registrado</dt><dd>${money(order.valorPedido)}</dd></div><div><dt>Frete incluso no total</dt><dd>${money(order.valorFrete)}</dd></div></dl><section class="order-section"><h3>Peças do pedido</h3><ul class="order-items">${order.itens.map(item => `<li><div><strong>${escape(item.nomeProduto || 'Peça indisponível')}</strong><span>${escape(item.cor)} / ${escape(item.tamanho)}</span><span>${escape(item.referencia)}</span>${!Number(item.itemAtivo) || !Number(item.produtoAtivo) ? '<span>Retirado do catálogo atual</span>' : ''}</div><div><strong>${Number(item.quantidade)} un.</strong></div></li>`).join('')}</ul></section><section class="order-section"><h3>Contato e entrega</h3><div class="delivery-info">${order.cliente ? `<strong>${escape(order.cliente)}</strong><p>${escape(order.email)}</p><p>${escape(order.endereco)}</p>` : '<p>Este pedido não tem dados de entrega registrados.</p>'}</div></section><section class="order-section"><h3>Cupom</h3><p>${escape(order.codigoCupom || 'Nenhum cupom aplicado')}</p></section><p class="order-note">O total corresponde ao valor registrado no pedido. A situação apresentada vem do registro da loja.</p>`;
        open($('#order-editor'));
    }));
})();
