{assign var=active_tab value='scan'}
{include file=$tpl_dir|cat:'_nav.tpl' assign='nav_html'}
{$nav_html nofilter}

<div class="ss-wrap">

  {* ── Título ── *}
  <div class="ss-page-header">
    <div>
      <h1 class="ss-page-title">Escáner de stock</h1>
      <p class="ss-page-sub">Escanea EAN13 o busca por nombre · referencia · EAN</p>
    </div>
    {if $can_create}<button type="button" class="ss-search-btn" id="btn-new-product">+ Producto nuevo</button>{/if}
  </div>

  <div class="ss-scan-layout">

    {* ── Panel izquierdo: input ── *}
    <div class="ss-scan-left">

      {* Zona de escáner *}
      <div class="ss-scanner-zone" id="scanner-zone">
        <div class="ss-scanner-ring" id="scanner-ring">
          <div class="ss-scanner-line"></div>
          <span class="ss-scanner-label">EAN · REF · NOMBRE</span>
        </div>
        <input type="text" id="ean-input" class="ss-ean-input"
               placeholder="Escanea o escribe…" autocomplete="off" autofocus />
        <div class="ss-scan-hint">La pistola pulsa Enter automáticamente</div>
      </div>

      {* Resultado del escaneo *}
      <div class="ss-found-card" id="found-card" style="display:none">
        <div class="ss-found-header">
          <div class="ss-found-badge" id="found-badge">✓</div>
          <div class="ss-found-info">
            <strong id="found-name">–</strong>
            <small id="found-meta">–</small>
          </div>
          <button class="ss-clear-btn" id="btn-clear" title="Limpiar">✕</button>
        </div>

        <div class="ss-found-stock">
          <div class="ss-stock-pill">
            <span>Stock actual</span>
            <strong id="found-stock">–</strong>
          </div>
          <div class="ss-stock-arrow">→</div>
          <div class="ss-stock-pill new" id="preview-pill">
            <span>Resultante</span>
            <strong id="preview-qty">–</strong>
          </div>
        </div>

        {* Controles de movimiento *}
        <div class="ss-movement-controls">

          <div class="ss-ctrl-row">
            <label class="ss-label">Tipo de movimiento</label>
            <div class="ss-type-tabs">
              <button class="ss-type-btn active" data-type="in">⬆ Entrada</button>
              <button class="ss-type-btn" data-type="out">⬇ Salida</button>
              <button class="ss-type-btn" data-type="inventory">⇌ Inventario</button>
            </div>
          </div>

          <div class="ss-ctrl-row ss-qty-row">
            <label class="ss-label">Cantidad</label>
            <div class="ss-qty-wrap">
              <button class="ss-qty-btn" id="qty-minus">−</button>
              <input type="number" id="qty-input" class="ss-qty-input" value="1" min="1" />
              <button class="ss-qty-btn" id="qty-plus">+</button>
            </div>
          </div>

          <div class="ss-ctrl-row">
            <label class="ss-label">Etiqueta del movimiento</label>
            <input type="text" id="label-input" class="ss-text-input"
                   placeholder="Ej: Recepción proveedor, Merma, Devolución…" />
          </div>

          <div class="ss-ctrl-row">
            <label class="ss-label">Precio de compra (€)</label>
            <input type="number" id="price-input" class="ss-text-input ss-price-input"
                   step="0.01" min="0" placeholder="0.00" />
          </div>

          <button class="ss-apply-btn" id="btn-apply">
            <span id="apply-btn-text">Aplicar movimiento</span>
          </button>
        </div>

      </div>

      {* Alta rápida de producto (cuando el código no existe) *}
      {if $can_create}
      <div class="ss-create-card" id="create-card" style="display:none">
        <div class="ss-create-header">
          <div>
            <strong>Producto nuevo</strong>
            <small id="create-sub">Este código no está en la tienda. Crea su ficha:</small>
          </div>
          <button type="button" class="ss-clear-btn" id="create-cancel" title="Cancelar">✕</button>
        </div>
        <form id="create-form" autocomplete="off">
          <div class="ss-ctrl-row">
            <label class="ss-label" for="c-name">Nombre *</label>
            <input type="text" id="c-name" name="name" class="ss-text-input" maxlength="128" required placeholder="Ej: Puzzle 1000 piezas Universidad" />
          </div>
          <div class="ss-create-grid">
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-ean">EAN</label>
              <input type="text" id="c-ean" name="ean13" class="ss-text-input" inputmode="numeric" maxlength="13" />
            </div>
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-ref">Referencia</label>
              <input type="text" id="c-ref" name="reference" class="ss-text-input" maxlength="64" />
            </div>
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-price">PVP con IVA (€) *</label>
              <input type="number" id="c-price" name="price_ttc" class="ss-text-input" step="0.01" min="0" required placeholder="0,00" />
            </div>
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-tax">IVA</label>
              <select id="c-tax" name="id_tax_rules_group" class="ss-text-input"></select>
            </div>
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-qty">Stock inicial</label>
              <input type="number" id="c-qty" name="qty" class="ss-text-input" min="0" value="1" />
            </div>
            <div class="ss-ctrl-row">
              <label class="ss-label" for="c-cost">Precio de compra (€)</label>
              <input type="number" id="c-cost" name="purchase_price" class="ss-text-input" step="0.01" min="0" placeholder="0,00" />
            </div>
          </div>
          <div class="ss-ctrl-row">
            <label class="ss-label" for="c-cat-filter">Categoría *</label>
            <input type="search" id="c-cat-filter" class="ss-text-input ss-create-filter" placeholder="Filtrar categorías…" />
            <select id="c-cat" name="id_category" class="ss-text-input" size="6" required></select>
          </div>
          <div class="ss-ctrl-row">
            <label class="ss-label" for="c-brand">Marca</label>
            <select id="c-brand" name="id_manufacturer" class="ss-text-input"></select>
          </div>
          <div class="ss-ctrl-row">
            <label class="ss-label" for="c-photo">Foto</label>
            <input type="file" id="c-photo" name="photo" accept="image/*" capture="environment" class="ss-text-input" />
          </div>
          <label class="ss-create-check"><input type="checkbox" id="c-online" checked /> Mostrar en la tienda online (si lo desmarcas, solo se vende en la tienda física)</label>
          <button type="submit" class="ss-apply-btn" id="create-submit"><span id="create-btn-text">Crear producto</span></button>
        </form>
      </div>
      {/if}

      <div class="ss-feedback" id="feedback"></div>

      {* Búsqueda manual *}
      <div class="ss-manual-search">
        <div class="ss-manual-header">
          <span class="ss-section-label">◎ Búsqueda manual</span>
        </div>
        <div class="ss-manual-input-wrap">
          <input type="text" id="manual-input" class="ss-text-input"
                 placeholder="Nombre, referencia o EAN…" />
          <button class="ss-search-btn" id="btn-manual-search">Buscar</button>
        </div>
        <div class="ss-search-results" id="search-results" style="display:none"></div>
      </div>

    </div>

    {* ── Panel derecho: últimos movimientos ── *}
    <div class="ss-scan-right">
      <div class="ss-recent-header">
        <span class="ss-section-label">◷ Últimos movimientos</span>
        <a href="{$link_history}" class="ss-link-sm">Ver todos →</a>
      </div>
      <div class="ss-recent-list" id="recent-list">
        <div class="ss-empty-recent">Aún no hay movimientos</div>
      </div>
    </div>

  </div>
</div>

<script>
{literal}
(function () {
  'use strict';

  const LINK = '{/literal}{$admin_link}{literal}';

  // State
  let currentProduct = null;
  let movementType   = 'in';

  // DOM
  const eanInput    = id('ean-input');
  const foundCard   = id('found-card');
  const foundName   = id('found-name');
  const foundMeta   = id('found-meta');
  const foundStock  = id('found-stock');
  const previewQty  = id('preview-qty');
  const qtyInput    = id('qty-input');
  const labelInput  = id('label-input');
  const priceInput  = id('price-input');
  const feedback    = id('feedback');
  const recentList  = id('recent-list');
  const searchRes   = id('search-results');
  const scannerRing = id('scanner-ring');

  function id(x) { return document.getElementById(x); }

  /* ── Scanner Input ─────────────────────────────────────── */
  function onEanSubmit(e) {
    const isEnter = e.key === 'Enter' || e.keyCode === 13;
    if (!isEnter) return;
    e.preventDefault();
    let val = eanInput.value.trim();
    if (!val) return;

    // Limpiar caracteres especiales/invisibles que el scanner pueda enviar
    val = val.replace(/[^\d\w\-\s]/g, '').trim();
    
    // Extraer solo los dígitos para validar si es un código de barras
    const onlyDigits = val.replace(/\D/g, '');
    
    // If it looks numeric (4+ digits) → EAN search, else text search
    if (onlyDigits.length >= 4) {
      searchEan(onlyDigits);
    } else if (val.length >= 2) {
      doManualSearch(val);
    }
  }

  eanInput.addEventListener('keydown', onEanSubmit);
  eanInput.addEventListener('keypress', onEanSubmit);

  function searchEan(ean) {
    setScanning(true);
    ajax('searchEan', { ean })
      .then(d => {
        setScanning(false);
        eanInput.value = '';
        if (d.success) {
          hideCreate();
          loadProduct(d.product);
        } else if (d.not_found && CAN_CREATE) {
          showCreate(d.ean || ean);
        } else {
          flashFeedback('error', d.error);
        }
      }).catch(err => { setScanning(false); flashFeedback('error', err.message || 'Error de red'); });
  }

  /* ── Alta rápida de producto ────────────────────────────── */
  const CAN_CREATE = {/literal}{if $can_create}true{else}false{/if}{literal};
  const FORM_DATA  = {/literal}{$create_form|json_encode nofilter}{literal};
  const createCard = id('create-card');

  function store(k, v) { try { if (v === undefined) return localStorage.getItem('ss_' + k); localStorage.setItem('ss_' + k, v); } catch (e) { return null; } }

  function fillSelect(sel, items, selected, emptyLabel) {
    sel.innerHTML = (emptyLabel ? `<option value="0">${emptyLabel}</option>` : '') +
      items.map(o => `<option value="${o.id}"${String(o.id) === String(selected) ? ' selected' : ''}>${escapeHtml(o.label)}${o.rate !== undefined ? ' (' + o.rate + ' %)' : ''}</option>`).join('');
  }

  function escapeHtml(s) { return String(s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c])); }

  if (CAN_CREATE) {
    fillSelect(id('c-tax'), FORM_DATA.taxes, store('tax') || FORM_DATA.default_tax, 'Sin IVA');
    fillSelect(id('c-cat'), FORM_DATA.categories, store('cat') || FORM_DATA.default_category);
    fillSelect(id('c-brand'), FORM_DATA.brands, store('brand') || 0, 'Sin marca');

    id('c-cat-filter').addEventListener('input', e => {
      const q = e.target.value.trim().toLowerCase();
      const current = id('c-cat').value;
      fillSelect(id('c-cat'), FORM_DATA.categories.filter(c => !q || c.label.toLowerCase().includes(q)), current);
    });
    id('create-cancel').addEventListener('click', () => { hideCreate(); eanInput.focus(); });
    id('btn-new-product').addEventListener('click', () => showCreate(''));
    id('create-form').addEventListener('submit', submitCreate);
  }

  function showCreate(ean) {
    currentProduct = null;
    foundCard.style.display = 'none';
    id('create-form').reset();
    id('c-ean').value = ean || '';
    id('c-qty').value = 1;
    id('c-online').checked = store('online') !== '0';
    id('c-tax').value = store('tax') || FORM_DATA.default_tax;
    id('c-brand').value = store('brand') || 0;
    id('c-cat-filter').value = '';
    fillSelect(id('c-cat'), FORM_DATA.categories, store('cat') || FORM_DATA.default_category);
    id('create-sub').textContent = ean ? `El código ${ean} no está en la tienda. Crea su ficha:` : 'Crea la ficha de un producto nuevo:';
    createCard.style.display = 'block';
    id('c-name').focus();
  }

  function hideCreate() { if (createCard) createCard.style.display = 'none'; }

  function submitCreate(e) {
    e.preventDefault();
    if (!id('c-cat').value) { flashFeedback('error', 'Elige una categoría'); return; }
    const fd = new FormData(id('create-form'));
    fd.set('online', id('c-online').checked ? '1' : '0');
    fd.set('ajax', '1');
    const btn = id('create-submit');
    btn.disabled = true; id('create-btn-text').textContent = 'Creando…';

    fetch(LINK + '&ajax=1&action=createProduct', { method: 'POST', body: fd })
      .then(r => r.text()).then(t => JSON.parse(t.slice(t.indexOf('{'), t.lastIndexOf('}') + 1)))
      .then(d => {
        btn.disabled = false; id('create-btn-text').textContent = 'Crear producto';
        if (!d.success) { flashFeedback('error', d.error || 'No se pudo crear'); return; }
        store('cat', id('c-cat').value); store('tax', id('c-tax').value); store('brand', id('c-brand').value);
        store('online', id('c-online').checked ? '1' : '0');
        hideCreate();
        loadProduct(d.product);
        addRecentItem(d.product, { qty_delta: d.product.qty_stock, qty_after: d.product.qty_stock }, 'in', 'Alta de producto');
        flashFeedback(d.photo === 'error' ? 'error' : 'ok',
          `✓ Producto creado (#${d.product.id_product})` + (d.photo === 'error' ? ' · la foto no se pudo guardar' : ''));
      }).catch(() => {
        btn.disabled = false; id('create-btn-text').textContent = 'Crear producto';
        flashFeedback('error', 'Error de red al crear el producto');
      });
  }

  /* ── Product card ───────────────────────────────────────── */
  function loadProduct(p) {
    currentProduct = p;
    foundName.textContent = p.name;
    foundMeta.textContent = [p.reference && 'Ref: ' + p.reference, p.ean13 && 'EAN: ' + p.ean13].filter(Boolean).join('  ·  ');
    foundStock.textContent = p.qty_stock;
    updatePreview();
    foundCard.style.display = 'block';
    eanInput.focus();
  }

  function updatePreview() {
    if (!currentProduct) return;
    const qty    = parseInt(qtyInput.value) || 0;
    const stock  = parseInt(currentProduct.qty_stock) || 0;

    let result;
    if (movementType === 'inventory') result = qty;
    else if (movementType === 'out')  result = Math.max(0, stock - qty);
    else                              result = stock + qty;

    previewQty.textContent = result;
    previewQty.className   = 'preview-qty-val ' + (result > stock ? 'up' : result < stock ? 'down' : 'eq');
  }

  qtyInput.addEventListener('input', updatePreview);

  /* ── Movement type tabs ─────────────────────────────────── */
  document.querySelectorAll('.ss-type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.ss-type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      movementType = btn.dataset.type;
      updatePreview();
    });
  });

  /* ── Qty +/- ─────────────────────────────────────────────── */
  id('qty-minus').addEventListener('click', () => {
    qtyInput.value = Math.max(1, (parseInt(qtyInput.value)||1) - 1);
    updatePreview();
  });
  id('qty-plus').addEventListener('click', () => {
    qtyInput.value = (parseInt(qtyInput.value)||1) + 1;
    updatePreview();
  });

  /* ── Apply movement ─────────────────────────────────────── */
  id('btn-apply').addEventListener('click', () => {
    if (!currentProduct) { flashFeedback('error', 'Selecciona un producto primero'); return; }
    const qty = parseInt(qtyInput.value) || 0;
    if (!qty) { flashFeedback('error', 'Indica una cantidad'); return; }

    id('btn-apply').disabled = true;
    id('apply-btn-text').textContent = 'Aplicando…';

    ajax('applyMovement', {
      id_product:           currentProduct.id_product,
      id_product_attribute: currentProduct.id_product_attribute,
      movement_type:        movementType,
      qty:                  qty,
      label:                labelInput.value.trim(),
      purchase_price:       priceInput.value || 0,
      product_name:         currentProduct.name,
      ean13:                currentProduct.ean13 || '',
      reference:            currentProduct.reference || '',
    }).then(d => {
      id('btn-apply').disabled = false;
      id('apply-btn-text').textContent = 'Aplicar movimiento';
      if (d.success) {
        flashFeedback('ok', `✓ Stock actualizado: ${d.qty_before} → ${d.qty_after}`);
        currentProduct.qty_stock = d.qty_after;
        foundStock.textContent   = d.qty_after;
        updatePreview();
        addRecentItem(currentProduct, d, movementType, labelInput.value.trim());
        labelInput.value = '';
        priceInput.value = '';
        qtyInput.value   = 1;
      } else {
        flashFeedback('error', d.error);
      }
    }).catch(() => {
      id('btn-apply').disabled = false;
      id('apply-btn-text').textContent = 'Aplicar movimiento';
      flashFeedback('error', 'Error de red');
    });
  });

  id('btn-clear').addEventListener('click', () => {
    currentProduct = null;
    foundCard.style.display = 'none';
    eanInput.value = '';
    eanInput.focus();
  });

  /* ── Manual search ──────────────────────────────────────── */
  id('btn-manual-search').addEventListener('click', () => doManualSearch(id('manual-input').value.trim()));
  id('manual-input').addEventListener('keydown', e => {
    const isEnter = e.key === 'Enter' || e.keyCode === 13;
    if (!isEnter) return;
    e.preventDefault();
    doManualSearch(e.target.value.trim());
  });

  function doManualSearch(q) {
    if (q.length < 2) return;
    ajax('searchQuery', { q }).then(d => {
      if (!d.success || !d.results.length) {
        searchRes.innerHTML = '<div class="ss-no-results">Sin resultados para "' + q + '"</div>';
        searchRes.style.display = 'block';
        return;
      }
      searchRes.innerHTML = d.results.map(p => `
        <div class="ss-result-item" data-json='${JSON.stringify(p).replace(/'/g,"&#39;")}'>
          <div class="ss-result-name">${p.name}</div>
          <div class="ss-result-meta">${p.reference || ''} ${p.ean13 ? '· ' + p.ean13 : ''} · <strong>${p.qty_stock}</strong> uds</div>
        </div>
      `).join('');
      searchRes.style.display = 'block';
    });
  }

  document.addEventListener('click', e => {
    const item = e.target.closest('.ss-result-item');
    if (item) {
      loadProduct(JSON.parse(item.dataset.json));
      searchRes.style.display = 'none';
      id('manual-input').value = '';
    }
    if (!e.target.closest('.ss-manual-search')) {
      searchRes.style.display = 'none';
    }
  });

  /* ── Recent list ─────────────────────────────────────────── */
  function addRecentItem(product, data, type, label) {
    const typeLabel = type === 'in' ? 'Entrada' : type === 'out' ? 'Salida' : 'Inventario';
    const sign      = data.qty_delta > 0 ? '+' : '';
    const cls       = data.qty_delta > 0 ? 'up' : data.qty_delta < 0 ? 'down' : 'eq';

    const now = new Date().toLocaleTimeString('es-ES', { hour: '2-digit', minute: '2-digit' });

    const emptyEl = recentList.querySelector('.ss-empty-recent');
    if (emptyEl) emptyEl.remove();

    const div = document.createElement('div');
    div.className = 'ss-recent-item';
    div.innerHTML = `
      <div class="ss-recent-meta">
        <span class="ss-recent-type ${cls}">${typeLabel}</span>
        <span class="ss-recent-time">${now}</span>
      </div>
      <div class="ss-recent-name">${product.name}</div>
      <div class="ss-recent-delta ${cls}">${sign}${data.qty_delta} uds · Stock: ${data.qty_after}</div>
      ${label ? `<div class="ss-recent-label">${label}</div>` : ''}
    `;
    recentList.prepend(div);
  }

  /* ── Helpers ─────────────────────────────────────────────── */
  function ajax(action, data) {
    const body = new URLSearchParams({ ...data, ajax: 1 });
    return fetch(LINK + '&ajax=1&action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(async r => {
      const text = await r.text();
      try {
        return JSON.parse(text);
      } catch (err) {
        console.error('Invalid AJAX JSON response:', text);
        const jsonStart = text.indexOf('{');
        const jsonEnd = text.lastIndexOf('}');
        if (jsonStart !== -1 && jsonEnd > jsonStart) {
          try {
            return JSON.parse(text.slice(jsonStart, jsonEnd + 1));
          } catch (innerErr) {}
        }

        const detail = text
          .replace(/<script[\s\S]*?<\/script>/gi, ' ')
          .replace(/<style[\s\S]*?<\/style>/gi, ' ')
          .replace(/<[^>]+>/g, ' ')
          .replace(/\s+/g, ' ')
          .trim()
          .slice(0, 260);

        throw new Error(detail ? 'Respuesta inválida: ' + detail : 'Respuesta inválida del servidor');
      }
    });
  }

  function flashFeedback(type, msg) {
    feedback.textContent  = msg;
    feedback.className    = 'ss-feedback ' + type + ' visible';
    clearTimeout(feedback._timer);
    feedback._timer = setTimeout(() => feedback.classList.remove('visible'), 3500);
  }

  function setScanning(on) {
    scannerRing.classList.toggle('scanning', on);
    eanInput.disabled = on;
  }

  // Keep focus on scanner input
  document.addEventListener('click', e => {
    if (!e.target.closest('input, select, textarea, label, button, a, .ss-result-item, .ss-create-card')) eanInput.focus();
  });
})();
{/literal}
</script>
