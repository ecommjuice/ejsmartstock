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

  const LINK = '{/literal}{$admin_link}{literal}' || window.location.href;
  console.debug('SmartStock AJAX base link:', LINK);

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
    const val = eanInput.value.trim();
    if (!val) return;

    // If it looks numeric → EAN search, else text search
    if (/^\d{4,}$/.test(val)) {
      searchEan(val);
    } else {
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
          loadProduct(d.product);
        } else {
          flashFeedback('error', d.error);
        }
      }).catch(err => {
        setScanning(false);
        console.error('searchEan error:', err);
        flashFeedback('error', err.message || 'Error de red');
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
    }).catch(err => {
      flashFeedback('error', err.message || 'Error de red');
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
    if (!LINK) {
      return Promise.reject(new Error('URL AJAX inválida')); 
    }
    const body = new URLSearchParams({ ...data, ajax: 1 });
    const url  = LINK.includes('?') ? `${LINK}&action=${action}` : `${LINK}?action=${action}`;
    return fetch(url, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString(),
    }).then(async r => {
      const text = await r.text();
      if (!r.ok) {
        console.error(`AJAX HTTP error ${r.status}:`, text);
        const summary = text.trim().slice(0, 200).replace(/\s+/g, ' ');
        throw new Error(`Error del servidor (${r.status}): ${summary}`);
      }
      try {
        return JSON.parse(text);
      } catch (err) {
        console.error('Invalid AJAX JSON response:', text);
        const summary = text.trim().slice(0, 200).replace(/\s+/g, ' ');
        throw new Error(`Respuesta inválida del servidor: ${summary}`);
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
    if (!e.target.closest('input, button, a, .ss-result-item')) eanInput.focus();
  });
})();
{/literal}
</script>
