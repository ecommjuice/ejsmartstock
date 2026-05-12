{assign var=active_tab value='inventory'}
{include file=$tpl_dir|cat:'_nav.tpl' assign='nav_html'}
{$nav_html nofilter}

<div class="ss-wrap">
  <div class="ss-page-header">
    <div>
      <h1 class="ss-page-title">Inventario / Conteo fisico</h1>
      <p class="ss-page-sub">Acumula escaneos, revisa diferencias y ajusta el stock al final.</p>
    </div>
    <div class="ss-page-actions">
      <button class="ss-btn ss-btn-primary" id="inv-start">Nueva sesion de inventario</button>
      <button class="ss-btn ss-btn-outline" id="inv-close" disabled>Finalizar conteo</button>
      <button class="ss-btn ss-btn-primary" id="inv-apply" disabled>Volcar ajustes de stock</button>
    </div>
  </div>

  <div class="ss-inventory-status" id="inv-status">
    <span class="ss-section-label">Sesion</span>
    <strong id="inv-status-text">Sin sesion activa</strong>
  </div>

  <div class="ss-inventory-layout">
    <div class="ss-inventory-scan">
      <div class="ss-scanner-zone" id="inventory-scanner-zone">
        <div class="ss-scanner-ring" id="inventory-scanner-ring">
          <div class="ss-scanner-line"></div>
          <span class="ss-scanner-label">INVENTARIO</span>
        </div>
        <input type="text" id="inv-ean" class="ss-ean-input" placeholder="Escanea EAN..." autocomplete="off" />
        <div class="ss-scan-hint">Cada lectura suma a la lista temporal, no toca el stock todavia</div>
      </div>

      <div class="ss-inventory-controls">
        <div class="ss-ctrl-row ss-qty-row">
          <label class="ss-label">Cantidad por escaneo</label>
          <div class="ss-qty-wrap">
            <button class="ss-qty-btn" id="inv-qty-minus">-</button>
            <input type="number" id="inv-qty" class="ss-qty-input" value="1" min="1" />
            <button class="ss-qty-btn" id="inv-qty-plus">+</button>
          </div>
        </div>
        <div class="ss-feedback" id="inv-feedback"></div>
      </div>
    </div>

    <div class="ss-kpi-row ss-inventory-kpis">
      <div class="ss-kpi-card">
        <span class="ss-kpi-label">Lineas</span>
        <span class="ss-kpi-value" id="inv-kpi-lines">0</span>
      </div>
      <div class="ss-kpi-card">
        <span class="ss-kpi-label">Stock PS</span>
        <span class="ss-kpi-value" id="inv-kpi-system">0</span>
      </div>
      <div class="ss-kpi-card accent">
        <span class="ss-kpi-label">Contado</span>
        <span class="ss-kpi-value" id="inv-kpi-counted">0</span>
      </div>
      <div class="ss-kpi-card">
        <span class="ss-kpi-label">Diferencia</span>
        <span class="ss-kpi-value" id="inv-kpi-diff">0</span>
      </div>
    </div>
  </div>

  <div class="ss-table-card">
    <div class="ss-table-card-header">
      <span class="ss-section-label">Productos escaneados</span>
    </div>
    <div class="ss-table-wrap">
      <table class="ss-table ss-inventory-table">
        <thead>
          <tr>
            <th>Producto</th>
            <th>Referencia</th>
            <th>EAN13</th>
            <th class="num">Stock PS</th>
            <th class="num">Contado</th>
            <th class="num">Diferencia</th>
            <th class="num">Acciones</th>
          </tr>
        </thead>
        <tbody id="inv-lines">
          <tr>
            <td colspan="7">
              <div class="ss-empty-state">
                <span class="ss-empty-icon">INV</span>
                <h3>No hay productos contados</h3>
                <p>Abre una sesion y empieza a escanear.</p>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
{literal}
(function () {
  'use strict';

  const LINK = '{/literal}{$admin_link}{literal}';
  let inventoryId = {/literal}{$current_inventory_id|intval}{literal};
  let inventoryStatus = '{/literal}{$current_inventory_status|escape:'javascript'}{literal}';
  let lines = [];

  const el = id => document.getElementById(id);
  const eanInput = el('inv-ean');
  const qtyInput = el('inv-qty');
  const feedback = el('inv-feedback');
  const tbody = el('inv-lines');
  const ring = el('inventory-scanner-ring');

  el('inv-start').addEventListener('click', startInventory);
  el('inv-close').addEventListener('click', closeInventory);
  el('inv-apply').addEventListener('click', applyInventory);
  el('inv-qty-minus').addEventListener('click', () => {
    qtyInput.value = Math.max(1, (parseInt(qtyInput.value, 10) || 1) - 1);
    eanInput.focus();
  });
  el('inv-qty-plus').addEventListener('click', () => {
    qtyInput.value = (parseInt(qtyInput.value, 10) || 1) + 1;
    eanInput.focus();
  });

  eanInput.addEventListener('keydown', event => {
    const isEnter = event.key === 'Enter' || event.keyCode === 13;
    if (!isEnter) return;
    event.preventDefault();
    scanItem();
  });

  tbody.addEventListener('click', event => {
    const btn = event.target.closest('[data-action]');
    if (!btn) return;
    const idLine = parseInt(btn.dataset.line, 10);
    const line = lines.find(item => item.id_inventory_line === idLine);
    if (!line) return;

    if (btn.dataset.action === 'plus') updateLine(idLine, line.qty_counted + 1);
    if (btn.dataset.action === 'minus') updateLine(idLine, Math.max(0, line.qty_counted - 1));
    if (btn.dataset.action === 'remove' && confirm('Eliminar esta linea del conteo?')) removeLine(idLine);
  });

  tbody.addEventListener('change', event => {
    const input = event.target.closest('.inv-line-qty');
    if (!input) return;
    updateLine(parseInt(input.dataset.line, 10), Math.max(0, parseInt(input.value, 10) || 0));
  });

  if (inventoryId > 0) {
    getLines();
  } else {
    renderState();
  }

  function startInventory() {
    ajax('startInventory', {}).then(handlePayload).then(() => eanInput.focus());
  }

  function scanItem() {
    if (!inventoryId || inventoryStatus !== 'open') {
      flash('error', 'Abre una sesion de inventario antes de escanear.');
      return;
    }

    const ean = eanInput.value.trim();
    const qty = Math.max(1, parseInt(qtyInput.value, 10) || 1);
    if (!ean) return;

    setScanning(true);
    ajax('scanInventoryItem', { id_inventory: inventoryId, ean, qty })
      .then(handlePayload)
      .then(() => {
        eanInput.value = '';
        eanInput.focus();
      })
      .finally(() => setScanning(false));
  }

  function updateLine(idLine, qty) {
    ajax('updateInventoryQty', {
      id_inventory: inventoryId,
      id_inventory_line: idLine,
      qty
    }).then(handlePayload);
  }

  function removeLine(idLine) {
    ajax('removeInventoryLine', {
      id_inventory: inventoryId,
      id_inventory_line: idLine
    }).then(handlePayload);
  }

  function getLines() {
    ajax('getInventoryLines', { id_inventory: inventoryId }).then(handlePayload);
  }

  function closeInventory() {
    if (!confirm('Finalizar el conteo? Podras revisar diferencias y volcar el ajuste.')) return;
    ajax('closeInventory', { id_inventory: inventoryId }).then(handlePayload);
  }

  function applyInventory() {
    if (!confirm('Volcar ajustes ahora? El stock de PrestaShop quedara igual que el conteo fisico.')) return;
    ajax('applyInventory', { id_inventory: inventoryId }).then(handlePayload);
  }

  function handlePayload(data) {
    if (!data.success) {
      flash('error', data.error || 'Error');
      return data;
    }

    if (data.inventory) {
      inventoryId = parseInt(data.inventory.id_inventory, 10);
      inventoryStatus = data.inventory.status;
    }

    lines = Array.isArray(data.lines) ? data.lines : [];
    renderLines(lines);
    renderSummary(data.summary || {});
    renderState();

    if (data.message) flash('ok', data.message);
    return data;
  }

  function renderState() {
    const statusText = inventoryId
      ? ('#' + inventoryId + ' - ' + labelStatus(inventoryStatus))
      : 'Sin sesion activa';

    el('inv-status-text').textContent = statusText;
    el('inv-close').disabled = !inventoryId || inventoryStatus !== 'open';
    el('inv-apply').disabled = !inventoryId || inventoryStatus !== 'closed' || lines.length === 0;
    eanInput.disabled = !inventoryId || inventoryStatus !== 'open';
  }

  function renderSummary(summary) {
    el('inv-kpi-lines').textContent = summary.lines || 0;
    el('inv-kpi-system').textContent = summary.system || 0;
    el('inv-kpi-counted').textContent = summary.counted || 0;
    el('inv-kpi-diff').textContent = signed(summary.difference || 0);
  }

  function renderLines(items) {
    if (!items.length) {
      tbody.innerHTML = '<tr><td colspan="7"><div class="ss-empty-state"><span class="ss-empty-icon">INV</span><h3>No hay productos contados</h3><p>Escanea el primer EAN para empezar.</p></div></td></tr>';
      return;
    }

    tbody.innerHTML = items.map(line => {
      const diffClass = line.qty_difference > 0 ? 'diff-up' : line.qty_difference < 0 ? 'diff-down' : 'diff-eq';
      const disabled = inventoryStatus === 'applied' ? 'disabled' : '';
      return `
        <tr>
          <td class="ss-product-cell">${escapeHtml(line.product_name)}</td>
          <td><code>${escapeHtml(line.reference || '-')}</code></td>
          <td><code>${escapeHtml(line.ean13 || '-')}</code></td>
          <td class="num">${line.qty_system}</td>
          <td class="num">
            <input type="number" class="ss-filter-input inv-line-qty" data-line="${line.id_inventory_line}" value="${line.qty_counted}" min="0" ${disabled} />
          </td>
          <td class="num"><span class="${diffClass}">${signed(line.qty_difference)}</span></td>
          <td class="num">
            <div class="ss-row-actions">
              <button class="ss-btn ss-btn-sm ss-btn-outline" data-action="minus" data-line="${line.id_inventory_line}" ${disabled}>-</button>
              <button class="ss-btn ss-btn-sm ss-btn-outline" data-action="plus" data-line="${line.id_inventory_line}" ${disabled}>+</button>
              <button class="ss-btn ss-btn-sm ss-btn-ghost" data-action="remove" data-line="${line.id_inventory_line}" ${disabled}>Eliminar</button>
            </div>
          </td>
        </tr>
      `;
    }).join('');
  }

  function ajax(action, data) {
    const body = new URLSearchParams(Object.assign({}, data, { ajax: 1 }));
    return fetch(LINK + '&ajax=1&action=' + action, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    }).then(async response => {
      const text = await response.text();
      try {
        return JSON.parse(text);
      } catch (error) {
        const jsonStart = text.indexOf('{');
        const jsonEnd = text.lastIndexOf('}');
        if (jsonStart !== -1 && jsonEnd > jsonStart) {
          return JSON.parse(text.slice(jsonStart, jsonEnd + 1));
        }
        throw new Error('Respuesta invalida del servidor');
      }
    }).catch(error => {
      flash('error', error.message || 'Error de red');
      return { success: false };
    });
  }

  function flash(type, message) {
    feedback.textContent = message;
    feedback.className = 'ss-feedback ' + type + ' visible';
    clearTimeout(feedback._timer);
    feedback._timer = setTimeout(() => feedback.classList.remove('visible'), 4000);
  }

  function setScanning(on) {
    ring.classList.toggle('scanning', on);
  }

  function labelStatus(status) {
    if (status === 'open') return 'abierta';
    if (status === 'closed') return 'cerrada';
    if (status === 'applied') return 'aplicada';
    return status || 'sin estado';
  }

  function signed(value) {
    value = parseInt(value, 10) || 0;
    return value > 0 ? '+' + value : String(value);
  }

  function escapeHtml(value) {
    return String(value == null ? '' : value)
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
})();
{/literal}
</script>
