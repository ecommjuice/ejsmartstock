{include file=$tpl_dir|cat:'_nav.tpl' assign='nav_html'}
{$nav_html nofilter}

<div class="ss-wrap">
  <div class="ss-page-header">
    <div>
      <h1 class="ss-page-title">Estado del stock</h1>
      <p class="ss-page-sub">Stock actual de todo el catálogo</p>
    </div>
    <a href="{$admin_link}&action=exportCsv&ajax=1" class="ss-btn ss-btn-outline">⬇ Exportar CSV</a>
  </div>

  <div class="ss-kpi-row">
    <div class="ss-kpi-card">
      <span class="ss-kpi-label">Total unidades</span>
      <span class="ss-kpi-value">{$total_qty}</span>
    </div>
    <div class="ss-kpi-card">
      <span class="ss-kpi-label">Referencias activas</span>
      <span class="ss-kpi-value">{$total_refs}</span>
    </div>
  </div>

  <form method="GET" class="ss-filter-bar">
    <input type="hidden" name="controller" value="AdminSmartStockStatus" />
    <input type="hidden" name="token" value="{$token}" />
    <input type="text" name="f_name" value="{$f_name|escape:'html'}" class="ss-filter-input" placeholder="Producto…" />
    <input type="text" name="f_ean"  value="{$f_ean|escape:'html'}"  class="ss-filter-input" placeholder="EAN13…" />
    <input type="text" name="f_ref"  value="{$f_ref|escape:'html'}"  class="ss-filter-input" placeholder="Referencia…" />
    <button type="submit" class="ss-btn ss-btn-primary">Filtrar</button>
    <a href="{$admin_link}" class="ss-btn ss-btn-ghost">Limpiar</a>
  </form>

  <div class="ss-table-card">
    {if $stock}
    <div class="ss-table-wrap">
      <table class="ss-table">
        <thead>
          <tr>
            <th>Producto</th><th>Referencia</th><th>EAN13</th><th class="num">Stock</th>
          </tr>
        </thead>
        <tbody>
          {foreach $stock as $s}
          <tr class="{if $s.qty == 0}row-zero{elseif $s.qty < 5}row-low{/if}">
            <td class="ss-product-cell">{$s.name|escape:'html'}</td>
            <td><code>{$s.reference|escape:'html'}</code></td>
            <td><code>{$s.ean13|escape:'html'}</code></td>
            <td class="num {if $s.qty == 0}diff-down{elseif $s.qty < 5}diff-warn{/if}">
              <strong>{$s.qty}</strong>
            </td>
          </tr>
          {/foreach}
        </tbody>
        <tfoot>
          <tr class="ss-tfoot">
            <td colspan="3"><strong>TOTAL</strong></td>
            <td class="num"><strong>{$total_qty}</strong></td>
          </tr>
        </tfoot>
      </table>
    </div>
    {else}
    <div class="ss-empty-state">
      <div class="ss-empty-icon">◈</div>
      <h3>Sin datos</h3>
      <p>No se encontraron productos con los filtros actuales.</p>
    </div>
    {/if}
  </div>
</div>
