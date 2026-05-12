{include file=$tpl_dir|cat:'_nav.tpl' assign='nav_html'}
{$nav_html nofilter}

<div class="ss-wrap">
  <div class="ss-page-header">
    <div>
      <h1 class="ss-page-title">Importación masiva CSV</h1>
      <p class="ss-page-sub">Actualiza el stock de múltiples productos en un solo archivo</p>
    </div>
  </div>

  <div class="ss-import-layout">
    <div class="ss-import-guide">
      <div class="ss-guide-card">
        <h3 class="ss-guide-title">📋 Formato del CSV</h3>
        <p>Columnas separadas por <code>;</code> o <code>,</code>:</p>
        <div class="ss-csv-preview">
          id_product;id_product_attribute;qty<br>
          15;0;50<br>
          15;3;20<br>
          22;0;100
        </div>
        <ul class="ss-guide-list">
          <li><strong>id_product</strong> – obligatorio</li>
          <li><strong>id_product_attribute</strong> – 0 si no hay combinación</li>
          <li><strong>qty</strong> – cantidad a aplicar</li>
        </ul>
        <button class="ss-btn ss-btn-ghost ss-btn-sm" id="btn-download-template">⬇ Descargar plantilla</button>
      </div>
    </div>

    <div class="ss-import-form-wrap">
      <div class="ss-import-card">
        <div class="ss-dropzone" id="dropzone">
          <div class="ss-drop-icon">⇪</div>
          <div class="ss-drop-label">Arrastra tu CSV aquí</div>
          <div class="ss-drop-sub">o haz clic para seleccionar</div>
          <input type="file" id="csv-file" accept=".csv,.txt" />
        </div>
        <div class="ss-file-name" id="file-name" style="display:none"></div>

        <div class="ss-import-options">
          <div class="ss-ctrl-row">
            <label class="ss-label">Tipo de movimiento</label>
            <div class="ss-type-tabs">
              <button class="ss-type-btn active" data-type="in">⬆ Entrada</button>
              <button class="ss-type-btn" data-type="out">⬇ Salida</button>
              <button class="ss-type-btn" data-type="inventory">⇌ Inventario (sobrescribir)</button>
            </div>
          </div>
          <div class="ss-ctrl-row">
            <label class="ss-label">Etiqueta del movimiento</label>
            <input type="text" id="import-label" class="ss-text-input" placeholder="Ej: Recepción proveedor X" />
          </div>
        </div>

        <div class="ss-import-actions">
          <button class="ss-btn ss-btn-outline" id="btn-preview">Vista previa</button>
          <button class="ss-btn ss-btn-primary" id="btn-apply-csv" disabled>Aplicar al stock</button>
        </div>
        <div class="ss-feedback" id="import-feedback"></div>
      </div>

      <div class="ss-table-card" id="preview-section" style="display:none">
        <div class="ss-table-card-header">
          <span class="ss-section-label">Vista previa — <span id="preview-count">0</span> filas</span>
        </div>
        <div class="ss-table-wrap">
          <table class="ss-table" id="preview-table">
            <thead>
              <tr><th>#</th><th>ID Producto</th><th>ID Atributo</th><th class="num">Cantidad</th></tr>
            </thead>
            <tbody id="preview-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
{literal}
(function() {
  const LINK = '{/literal}{$admin_link}{literal}';
  let importType = 'in';
  let selectedFile = null;

  const dropzone  = document.getElementById('dropzone');
  const fileInput = document.getElementById('csv-file');
  const fileName  = document.getElementById('file-name');

  dropzone.addEventListener('click', () => fileInput.click());
  dropzone.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('drag-over'); });
  dropzone.addEventListener('dragleave', () => dropzone.classList.remove('drag-over'));
  dropzone.addEventListener('drop', e => { e.preventDefault(); dropzone.classList.remove('drag-over'); if (e.dataTransfer.files[0]) setFile(e.dataTransfer.files[0]); });
  fileInput.addEventListener('change', () => { if (fileInput.files[0]) setFile(fileInput.files[0]); });

  function setFile(f) {
    selectedFile = f;
    fileName.textContent = '📄 ' + f.name + ' (' + (f.size/1024).toFixed(1) + ' KB)';
    fileName.style.display = 'block';
    dropzone.classList.add('has-file');
  }

  document.querySelectorAll('.ss-type-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.ss-type-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      importType = btn.dataset.type;
    });
  });

  document.getElementById('btn-preview').addEventListener('click', () => {
    if (!selectedFile) { flash('error', 'Selecciona un archivo CSV primero'); return; }
    uploadCsv('previewCsv').then(d => {
      if (!d.success) { flash('error', d.error); return; }
      const tbody = document.getElementById('preview-tbody');
      tbody.innerHTML = d.rows.map((r,i) => `<tr><td>${i+1}</td><td>${r.id_product}</td><td>${r.id_product_attribute}</td><td class="num">${r.qty}</td></tr>`).join('');
      document.getElementById('preview-count').textContent = d.rows.length;
      document.getElementById('preview-section').style.display = 'block';
      document.getElementById('btn-apply-csv').disabled = false;
      flash('ok', d.rows.length + ' filas detectadas. Revisa y aplica.');
    });
  });

  document.getElementById('btn-apply-csv').addEventListener('click', () => {
    if (!confirm('¿Aplicar los cambios de stock? Esta acción es irreversible.')) return;
    document.getElementById('btn-apply-csv').disabled = true;
    uploadCsv('applyCsv', { movement_type: importType, label: document.getElementById('import-label').value.trim() || 'Importación masiva CSV' })
      .then(d => {
        document.getElementById('btn-apply-csv').disabled = false;
        d.success ? flash('ok', '✓ ' + d.applied + ' productos actualizados.' + (d.errors.length ? ' ⚠ ' + d.errors.length + ' errores.' : '')) : flash('error', d.error);
      });
  });

  document.getElementById('btn-download-template').addEventListener('click', e => {
    e.preventDefault();
    const csv = 'id_product;id_product_attribute;qty\n15;0;50\n22;0;100\n';
    const a = document.createElement('a');
    a.href = URL.createObjectURL(new Blob(["\uFEFF"+csv], {type:'text/csv;charset=utf-8;'}));
    a.download = 'smartstock_plantilla.csv';
    a.click();
  });

  function uploadCsv(action, extra = {}) {
    const fd = new FormData();
    fd.append('ajax','1'); fd.append('csv_file', selectedFile);
    Object.entries(extra).forEach(([k,v]) => fd.append(k,v));
    return fetch(LINK + '&action=' + action, {method:'POST', body:fd}).then(r => r.json());
  }

  function flash(type, msg) {
    const el = document.getElementById('import-feedback');
    el.textContent = msg; el.className = 'ss-feedback ' + type + ' visible';
  }
})();
{/literal}
</script>
