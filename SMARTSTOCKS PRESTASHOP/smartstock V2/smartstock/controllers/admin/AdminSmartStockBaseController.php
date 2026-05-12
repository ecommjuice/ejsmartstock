<?php
/**
 * SmartStock – Controlador base
 * Compatible PS 8.x
 */
if (!defined('_PS_VERSION_')) { exit; }

abstract class AdminSmartStockBaseController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    /* ─── PS8 display fix ────────────────────────────────────────────────── */

    /**
     * PS8: ModuleAdminController ignores $this->content unless we explicitly
     * assign it to the 'content' Smarty variable before display().
     */
    public function display()
    {
        $this->context->smarty->assign('content', $this->content);
        parent::display();
    }

    /* ─── Template rendering ─────────────────────────────────────────────── */

    protected function renderModuleTemplate(string $tplName, array $vars = []): void
    {
        $tplDir  = _PS_MODULE_DIR_ . 'smartstock/views/templates/admin/smartstock/';
        $tplPath = $tplDir . $tplName;

        $smarty = $this->context->smarty;
        $smarty->assign('tpl_dir', $tplDir);
        $smarty->assign('token',   $this->token);
        $smarty->assign($vars);

        $this->content .= $smarty->fetch($tplPath);
    }

    /* ─── Búsqueda ───────────────────────────────────────────────────────── */

    protected function searchByEan(string $ean): ?array
    {
        $idLang = (int) $this->context->language->id;
        $ean    = pSQL($ean);

        $row = Db::getInstance()->getRow("
            SELECT p.id_product, 0 AS id_product_attribute,
                   pl.name, p.ean13, p.reference,
                   sa.quantity AS qty_stock
            FROM `{$this->t('product')}` p
            LEFT JOIN `{$this->t('product_lang')}` pl
                   ON pl.id_product = p.id_product AND pl.id_lang = {$idLang}
            LEFT JOIN `{$this->t('stock_available')}` sa
                   ON sa.id_product = p.id_product AND sa.id_product_attribute = 0
            WHERE p.ean13 = '{$ean}' AND p.active = 1
            LIMIT 1
        ");
        if ($row) return $row;

        return $this->searchCombinationByEan($ean, $idLang);
    }

    protected function searchByQuery(string $q): array
    {
        $idLang = (int) $this->context->language->id;
        $q      = pSQL($q);

        $rows = Db::getInstance()->executeS("
            SELECT p.id_product, 0 AS id_product_attribute,
                   pl.name, p.ean13, p.reference,
                   sa.quantity AS qty_stock
            FROM `{$this->t('product')}` p
            LEFT JOIN `{$this->t('product_lang')}` pl
                   ON pl.id_product = p.id_product AND pl.id_lang = {$idLang}
            LEFT JOIN `{$this->t('stock_available')}` sa
                   ON sa.id_product = p.id_product AND sa.id_product_attribute = 0
            WHERE p.active = 1
              AND (pl.name LIKE '%{$q}%' OR p.reference LIKE '%{$q}%' OR p.ean13 LIKE '%{$q}%')
            LIMIT 15
        ") ?: [];

        $combos = Db::getInstance()->executeS("
            SELECT p.id_product, pa.id_product_attribute,
                   CONCAT(pl.name, ' (', GROUP_CONCAT(al.name ORDER BY agl.id_attribute_group SEPARATOR ' / '), ')') AS name,
                   pa.ean13, pa.reference,
                   sa.quantity AS qty_stock
            FROM `{$this->t('product_attribute')}` pa
            LEFT JOIN `{$this->t('product')}` p ON p.id_product = pa.id_product
            LEFT JOIN `{$this->t('product_lang')}` pl ON pl.id_product = p.id_product AND pl.id_lang = {$idLang}
            LEFT JOIN `{$this->t('stock_available')}` sa ON sa.id_product = pa.id_product AND sa.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `{$this->t('product_attribute_combination')}` pac ON pac.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `{$this->t('attribute')}` a ON a.id_attribute = pac.id_attribute
            LEFT JOIN `{$this->t('attribute_lang')}` al ON al.id_attribute = a.id_attribute AND al.id_lang = {$idLang}
            LEFT JOIN `{$this->t('attribute_group_lang')}` agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = {$idLang}
            WHERE p.active = 1
              AND (pa.ean13 LIKE '%{$q}%' OR pa.reference LIKE '%{$q}%')
            GROUP BY pa.id_product_attribute
            LIMIT 10
        ") ?: [];

        return array_merge($rows, $combos);
    }

    private function searchCombinationByEan(string $ean, int $idLang): ?array
    {
        $row = Db::getInstance()->getRow("
            SELECT p.id_product, pa.id_product_attribute,
                   CONCAT(pl.name, ' (', GROUP_CONCAT(al.name ORDER BY agl.id_attribute_group SEPARATOR ' / '), ')') AS name,
                   pa.ean13, pa.reference,
                   sa.quantity AS qty_stock
            FROM `{$this->t('product_attribute')}` pa
            LEFT JOIN `{$this->t('product')}` p ON p.id_product = pa.id_product
            LEFT JOIN `{$this->t('product_lang')}` pl ON pl.id_product = p.id_product AND pl.id_lang = {$idLang}
            LEFT JOIN `{$this->t('stock_available')}` sa ON sa.id_product = pa.id_product AND sa.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `{$this->t('product_attribute_combination')}` pac ON pac.id_product_attribute = pa.id_product_attribute
            LEFT JOIN `{$this->t('attribute')}` a ON a.id_attribute = pac.id_attribute
            LEFT JOIN `{$this->t('attribute_lang')}` al ON al.id_attribute = a.id_attribute AND al.id_lang = {$idLang}
            LEFT JOIN `{$this->t('attribute_group_lang')}` agl ON agl.id_attribute_group = a.id_attribute_group AND agl.id_lang = {$idLang}
            WHERE pa.ean13 = '{$ean}'
            GROUP BY pa.id_product_attribute
            LIMIT 1
        ");
        return $row ?: null;
    }

    /* ─── Stock ──────────────────────────────────────────────────────────── */

    protected function getCurrentStock(int $idProduct, int $idAttr): int
    {
        $row = Db::getInstance()->getRow("
            SELECT quantity FROM `{$this->t('stock_available')}`
            WHERE id_product = {$idProduct} AND id_product_attribute = {$idAttr}
        ");
        return $row ? (int) $row['quantity'] : 0;
    }

    protected function applyStockDelta(int $idProduct, int $idAttr, int $delta): int
    {
        $before = $this->getCurrentStock($idProduct, $idAttr);
        $after  = max(0, $before + $delta);
        Db::getInstance()->update('stock_available', ['quantity' => $after],
            "id_product = {$idProduct} AND id_product_attribute = {$idAttr}");
        StockAvailable::synchronize($idProduct);
        return $after;
    }

    protected function setStock(int $idProduct, int $idAttr, int $qty): int
    {
        Db::getInstance()->update('stock_available', ['quantity' => $qty],
            "id_product = {$idProduct} AND id_product_attribute = {$idAttr}");
        StockAvailable::synchronize($idProduct);
        return $qty;
    }

    protected function logMovement(array $data): void
    {
        Db::getInstance()->insert('smartstock_movement', [
            'id_product'           => (int)  $data['id_product'],
            'id_product_attribute' => (int)  ($data['id_product_attribute'] ?? 0),
            'id_employee'          => (int)  $this->context->employee->id,
            'product_name'         => pSQL($data['product_name']),
            'reference'            => pSQL($data['reference']    ?? ''),
            'ean13'                => pSQL($data['ean13']         ?? ''),
            'label'                => pSQL($data['label']         ?? ''),
            'qty_before'           => (int)  $data['qty_before'],
            'qty_delta'            => (int)  $data['qty_delta'],
            'qty_after'            => (int)  $data['qty_after'],
            'purchase_price'       => 0,
            'movement_type'        => pSQL($data['movement_type'] ?? 'in'),
            'date_add'             => date('Y-m-d H:i:s'),
        ]);
    }

    /* ─── Helpers ────────────────────────────────────────────────────────── */

    protected function t(string $table): string
    {
        return _DB_PREFIX_ . $table;
    }

    protected function adminLink(string $ctrl): string
    {
        return $this->context->link->getAdminLink($ctrl);
    }

    protected function jsonOk(array $data = []): void
    {
        $this->ajaxDie(json_encode(array_merge(['success' => true], $data)));
    }

    protected function jsonErr(string $msg): void
    {
        $this->ajaxDie(json_encode(['success' => false, 'error' => $msg]));
    }

    protected function getNavVars(): array
    {
        return [
            'link_scan'    => $this->adminLink('AdminSmartStockScan'),
            'link_history' => $this->adminLink('AdminSmartStockHistory'),
            'link_status'  => $this->adminLink('AdminSmartStockStatus'),
            'link_import'  => $this->adminLink('AdminSmartStockImport'),
        ];
    }
}
