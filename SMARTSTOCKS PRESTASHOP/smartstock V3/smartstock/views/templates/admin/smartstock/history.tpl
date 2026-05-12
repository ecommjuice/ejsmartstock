{include file=$tpl_dir|cat:'_nav.tpl' assign='nav_html'}
{$nav_html nofilter}

<div class="ss-wrap">
  <div class="ss-page-header">
    <div>
      <h1 class="ss-page-title">Historial de movimientos</h1>
      <p class="ss-page-sub">Filtra y exporta todos los movimientos de stock</p>
    </div>
    <a href="{$admin_link}&action=exportCsv&ajax=1" class="ss-btn ss-btn-outline" id="btn-export">⬇ Exportar CSV</a>
  </div>

  <form method="GET" class="ss-filter-bar">
    <input type="hidden" name="controller" value="AdminSmartStockHistory" />
    <input type="hidden" name="token" value="{$token}" />
    <input type="text" name="f_name"  value="{$f_name|escape:'html'}"  class="ss-filter-input" placeholder="Producto…" />
    <input type="text" name="f_ean"   value="{$f_ean|escape:'html'}"   class="ss-filter-input" placeholder="EAN13…" />
    <input type="text" name="f_ref"   value="{$f_ref|escape:'html'}"   class="ss-filter-input" placeholder="Referencia…" />
    <input type="text" name="f_label" value="{$f_label|escape:'html'}" class="ss-filter-input" placeholder="Etiqueta…" />
    <select name="f_type" class="ss-filter-select">
      <option value="">Todos los tipos</option>
      <option value="in"        {if $f_type == 'in'}selected{/if}>⬆ Entrada</option>
      <option value="out"       {if $f_type == 'out'}selected{/if}>⬇ Salida</option>
      <option value="inventory" {if $f_type == 'inventory'}selected{/if}>⇌ Inventario</option>
    </select>
    <input type="date" name="f_from" value="{$f_from|escape:'html'}" class="ss-filter-input" />
    <input type="date" name="f_to"   value="{$f_to|escape:'html'}"   class="ss-filter-input" />
    <button type="submit" class="ss-btn ss-btn-primary">Filtrar</button>
    <a href="{$admin_link}" class="ss-btn ss-btn-ghost">Limpiar</a>
  </form>

  <div class="ss-table-card">
    {if $movements}
    <div class="ss-table-wrap">
      <table class="ss-table">
        <thead>
          <tr>
            <th>Fecha</th><th>Tipo</th><th>Producto</th><th>Referencia</th>
            <th>EAN13</th><th>Etiqueta</th>
            <th class="num">Antes</th><th class="num">Δ</th><th class="num">Después</th>
            <th>Empleado</th>
          </tr>
        </thead>
        <tbody>
          {foreach $movements as $m}
          <tr>
            <td class="ss-date">{$m.date_add|date_format:'%d/%m/%Y %H:%M'}</td>
            <td>
              <span class="ss-type-badge {$m.movement_type}">
                {if $m.movement_type == 'in'}⬆ Entrada
                {elseif $m.movement_type == 'out'}⬇ Salida
                {else}⇌ Inventario{/if}
              </span>
            </td>
            <td class="ss-product-cell">{$m.product_name|escape:'html'}</td>
            <td><code>{$m.reference|escape:'html'}</code></td>
            <td><code>{$m.ean13|escape:'html'}</code></td>
            <td class="ss-label-cell">{$m.label|escape:'html'}</td>
            <td class="num">{$m.qty_before}</td>
            <td class="num {if $m.qty_delta > 0}diff-up{elseif $m.qty_delta < 0}diff-down{else}diff-eq{/if}">
              {if $m.qty_delta > 0}+{/if}{$m.qty_delta}
            </td>
            <td class="num"><strong>{$m.qty_after}</strong></td>
            <td class="ss-emp-cell">{$m.firstname|escape:'html'} {$m.lastname|escape:'html'}</td>
          </tr>
          {/foreach}
        </tbody>
      </table>
    </div>
    {else}
    <div class="ss-empty-state">
      <div class="ss-empty-icon">◷</div>
      <h3>Sin movimientos</h3>
      <p>No hay movimientos que coincidan con los filtros aplicados.</p>
    </div>
    {/if}
  </div>
</div>
