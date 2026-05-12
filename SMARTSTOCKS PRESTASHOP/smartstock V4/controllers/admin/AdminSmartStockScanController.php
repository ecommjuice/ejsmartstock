<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockScanController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'Smart Stock – Escáner';
        parent::__construct();
    }

    public function ajaxProcessSearchEan()
    {
        $ean = trim(Tools::getValue('ean'));
        // Limpiar caracteres especiales/invisibles que el scanner pueda enviar
        $ean = preg_replace('/[^\d]/', '', $ean);
        if (!$ean || strlen($ean) < 4) { $this->jsonErr('EAN inválido'); }
        $product = $this->searchByEan($ean);
        $product ? $this->jsonOk(['product' => $product]) : $this->jsonErr('Producto no encontrado: ' . $ean);
    }

    public function ajaxProcessSearchQuery()
    {
        $q = trim(Tools::getValue('q'));
        if (strlen($q) < 2) { $this->jsonErr('Mínimo 2 caracteres'); }
        $this->jsonOk(['results' => $this->searchByQuery($q)]);
    }

    public function ajaxProcessApplyMovement()
    {
        $idProduct = (int)  Tools::getValue('id_product');
        $idAttr    = (int)  Tools::getValue('id_product_attribute', 0);
        $type      = pSQL(  Tools::getValue('movement_type', 'in'));
        $qty       = (int)  Tools::getValue('qty');
        $label     = pSQL(  Tools::getValue('label', ''));
        $name      = pSQL(  Tools::getValue('product_name', ''));
        $ean       = pSQL(  Tools::getValue('ean13', ''));
        $ref       = pSQL(  Tools::getValue('reference', ''));

        if (!$idProduct || !$qty) { $this->jsonErr('Parámetros inválidos'); }

        $before = $this->getCurrentStock($idProduct, $idAttr);

        if ($type === 'inventory') {
            $after = $this->setStock($idProduct, $idAttr, $qty);
            $delta = $after - $before;
        } elseif ($type === 'out') {
            $delta = -abs($qty);
            $after = $this->applyStockDelta($idProduct, $idAttr, $delta);
        } else {
            $delta = abs($qty);
            $after = $this->applyStockDelta($idProduct, $idAttr, $delta);
        }

        $this->logMovement([
            'id_product'           => $idProduct,
            'id_product_attribute' => $idAttr,
            'product_name'         => $name,
            'reference'            => $ref,
            'ean13'                => $ean,
            'label'                => $label ?: ($type === 'in' ? 'Entrada de stock' : ($type === 'out' ? 'Salida de stock' : 'Ajuste de inventario')),
            'qty_before'           => $before,
            'qty_delta'            => $delta,
            'qty_after'            => $after,
            'movement_type'        => $type,
        ]);

        $this->jsonOk(['qty_before' => $before, 'qty_after' => $after, 'qty_delta' => $delta]);
    }

    public function initContent()
    {
        parent::initContent();
        $this->renderModuleTemplate('scan.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'scan',
            'admin_link' => $this->adminLink('AdminSmartStockScan'),
        ]));
    }
}
