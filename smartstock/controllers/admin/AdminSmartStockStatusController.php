<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockStatusController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'EJ Smart stock – Estado del stock';
        parent::__construct();
    }

    public function ajaxProcessExportCsv()
    {
        $rows = $this->fetchStock(true);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="smartstock_estado_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Producto','Referencia','EAN13','Stock'], ';');
        foreach ($rows as $r) { fputcsv($out, [$r['name'],$r['reference'],$r['ean13'],$r['qty']], ';'); }
        fclose($out); exit;
    }

    public function initContent()
    {
        parent::initContent();
        $stock   = $this->fetchStock(false, 500);
        $summary = $this->fetchStockSummary();

        $this->renderModuleTemplate('status.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'status',
            'admin_link' => $this->adminLink('AdminSmartStockStatus'),
            'stock'      => $stock,
            'total_qty'  => $summary['total_qty'],
            'total_refs' => $summary['total_refs'],
            'f_name'     => Tools::getValue('f_name', ''),
            'f_ean'      => Tools::getValue('f_ean',  ''),
            'f_ref'      => Tools::getValue('f_ref',  ''),
        ]));
    }

    private function fetchStock(bool $all = false, int $limit = 500): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = $this->getContextShopId();
        $fName  = pSQL(trim(Tools::getValue('f_name', '')));
        $fEan   = pSQL(trim(Tools::getValue('f_ean',  '')));
        $fRef   = pSQL(trim(Tools::getValue('f_ref',  '')));

        $where = ['ps.active = 1', 'sa.id_product_attribute = 0'];
        if ($fName) $where[] = "pl.name LIKE '%{$fName}%'";
        if ($fEan)  $where[] = "p.ean13 LIKE '%{$fEan}%'";
        if ($fRef)  $where[] = "p.reference LIKE '%{$fRef}%'";

        $limitStr = $all ? '' : "LIMIT {$limit}";
        return Db::getInstance()->executeS("
            SELECT pl.name, p.reference, p.ean13, sa.quantity AS qty
            FROM `{$this->t('product')}` p
            INNER JOIN `{$this->t('product_shop')}` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            LEFT JOIN `{$this->t('product_lang')}` pl
                ON pl.id_product = p.id_product
                AND pl.id_lang = {$idLang}
                AND pl.id_shop = {$idShop}
            LEFT JOIN `{$this->t('stock_available')}` sa
                ON sa.id_product = p.id_product
                AND sa.id_shop = {$idShop}
            WHERE " . implode(' AND ', $where) . "
            ORDER BY pl.name ASC {$limitStr}
        ") ?: [];
    }

    private function fetchStockSummary(): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = $this->getContextShopId();
        $fName  = pSQL(trim(Tools::getValue('f_name', '')));
        $fEan   = pSQL(trim(Tools::getValue('f_ean',  '')));
        $fRef   = pSQL(trim(Tools::getValue('f_ref',  '')));

        $where = ['ps.active = 1', 'sa.id_product_attribute = 0'];
        if ($fName) $where[] = "pl.name LIKE '%{$fName}%'";
        if ($fEan)  $where[] = "p.ean13 LIKE '%{$fEan}%'";
        if ($fRef)  $where[] = "p.reference LIKE '%{$fRef}%'";

        $rows = Db::getInstance()->executeS("
            SELECT
                COUNT(DISTINCT p.id_product) AS total_refs,
                COALESCE(SUM(sa.quantity), 0) AS total_qty
            FROM `{$this->t('product')}` p
            INNER JOIN `{$this->t('product_shop')}` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            LEFT JOIN `{$this->t('product_lang')}` pl
                ON pl.id_product = p.id_product
                AND pl.id_lang = {$idLang}
                AND pl.id_shop = {$idShop}
            LEFT JOIN `{$this->t('stock_available')}` sa
                ON sa.id_product = p.id_product
                AND sa.id_shop = {$idShop}
            WHERE " . implode(' AND ', $where)
        ) ?: [];

        $row = $rows[0] ?? [];

        return [
            'total_refs' => (int) ($row['total_refs'] ?? 0),
            'total_qty' => (int) ($row['total_qty'] ?? 0),
        ];
    }
}
