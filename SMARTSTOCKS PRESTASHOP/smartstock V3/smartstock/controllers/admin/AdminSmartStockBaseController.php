<?php
if (!defined('_PS_VERSION_')) { exit; }

abstract class AdminSmartStockBaseController extends ModuleAdminController
{
    public function __construct()
    {
        $this->bootstrap = true;
        parent::__construct();
    }

    public function display()
    {
        $this->context->smarty->assign('content', $this->content);
        parent::display();
    }

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

    protected function searchByEan(string $ean): ?array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $ean    = pSQL($ean);
        $p      = _DB_PREFIX_;
        $row = Db::getInstance()->getRow(
            "SELECT p.id_product, 0 AS id_product_attribute, pl.name, p.ean13, p.reference, COALESCE(sa.quantity, 0) AS qty_stock"
            . " FROM `" . $p . "product` p"
            . " LEFT JOIN `" . $p . "product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = " . $idLang
            . " LEFT JOIN `" . $p . "stock_available` sa ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = " . $idShop
            . " WHERE p.ean13 = '" . $ean . "' AND p.active = 1 LIMIT 1"
        );
        if ($row) return $row;
        return $this->searchCombinationByEan($ean, $idLang, $idShop);
    }

    protected function searchByQuery(string $q): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->context->shop->id;
        $q      = pSQL($q);
        $p      = _DB_PREFIX_;
        $rows = Db::getInstance()->executeS(
            "SELECT p.id_product, 0 AS id_product_attribute, pl.name, p.ean13, p.reference, COALESCE(sa.quantity, 0) AS qty_stock"
            . " FROM `" . $p . "product` p"
            . " LEFT JOIN `" . $p . "product_lang` pl ON pl.id_product = p.id_product AND pl.id_lang = " . $idLang
            . " LEFT JOIN `" . $p . "stock_available` sa ON sa.id_product = p.id_product AND sa.id_product_attribute = 0 AND sa.id_shop = " . $idShop
            . " WHERE p.active = 1 AND (pl.name LIKE '%" . $q . "%' OR p.reference LIKE '%" . $q . "%' OR p.ean13 LIKE '%" . $q . "%') LIMIT 15"
        ) ?: [];
        $paRows = Db::getInstance()->executeS(
            "SELECT pa.id_product_attribute, pa.id_product, pa.ean13, pa.reference, COALESCE(sa.quantity, 0) AS qty_stock"
            . " FROM `" . $p . "product_attribute` pa"
            . " INNER JOIN `" . $p . "product` p ON p.id_product = pa.id_product"
            . " LEFT JOIN `" . $p . "stock_available` sa ON sa.id_product = pa.id_product AND sa.id_product_attribute = pa.id_product_attribute AND sa.id_shop = " . $idShop
            . " WHERE p.active = 1 AND (pa.ean13 LIKE '%" . $q . "%' OR pa.reference LIKE '%" . $q . "%') LIMIT 10"
        ) ?: [];
        $combos = [];
        foreach ($paRows as $pa) {
            $idProduct = (int) $pa['id_product'];
            $idAttr    = (int) $pa['id_product_attribute'];
            $nameRow = Db::getInstance()->getRow("SELECT name FROM `" . $p . "product_lang` WHERE id_product = " . $idProduct . " AND id_lang = " . $idLang . " LIMIT 1");
            $baseName = $nameRow ? $nameRow['name'] : '';
            $attrs = Db::getInstance()->executeS("SELECT al.name FROM `" . $p . "product_attribute_combination` pac INNER JOIN `" . $p . "attribute` a ON a.id_attribute = pac.id_attribute INNER JOIN `" . $p . "attribute_lang` al ON al.id_attribute = a.id_attribute AND al.id_lang = " . $idLang . " WHERE pac.id_product_attribute = " . $idAttr . " ORDER BY a.id_attribute_group");
            $attrNames = array_column($attrs ?: [], 'name');
            $name = $attrNames ? $baseName . ' (' . implode(' / ', $attrNames) . ')' : $baseName;
            $combos[] = ['id_product' => $idProduct, 'id_product_attribute' => $idAttr, 'name' => $name, 'ean13' => $pa['ean13'], 'reference' => $pa['reference'], 'qty_stock' => (int) $pa['qty_stock']];
        }
        return array_merge($rows, $combos);
    }

    private function searchCombinationByEan(string $ean, int $idLang, int $idShop): ?array
    {
        $p  = _DB_PREFIX_;
        $pa = Db::getInstance()->getRow("SELECT pa.id_product_attribute, pa.id_product, pa.ean13, pa.reference FROM `" . $p . "product_attribute` pa INNER JOIN `" . $p . "product` p ON p.id_product = pa.id_product WHERE pa.ean13 = '" . $ean . "' AND p.active = 1 LIMIT 1");
        if (!$pa) return null;
        $idProduct = (int) $pa['id_product'];
        $idAttr    = (int) $pa['id_product_attribute'];
        $nameRow   = Db::getInstance()->getRow("SELECT name FROM `" . $p . "product_lang` WHERE id_product = " . $idProduct . " AND id_lang = " . $idLang . " LIMIT 1");
        $baseName  = $nameRow ? $nameRow['name'] : '';
        $attrs     = Db::getInstance()->executeS("SELECT al.name FROM `" . $p . "product_attribute_combination` pac INNER JOIN `" . $p . "attribute` a ON a.id_attribute = pac.id_attribute INNER JOIN `" . $p . "attribute_lang` al ON al.id_attribute = a.id_attribute AND al.id_lang = " . $idLang . " WHERE pac.id_product_attribute = " . $idAttr . " ORDER BY a.id_attribute_group");
        $attrNames = array_column($attrs ?: [], 'name');
        $name      = $attrNames ? $baseName . ' (' . implode(' / ', $attrNames) . ')' : $baseName;
        $stockRow  = Db::getInstance()->getRow("SELECT COALESCE(quantity, 0) AS qty_stock FROM `" . $p . "stock_available` WHERE id_product = " . $idProduct . " AND id_product_attribute = " . $idAttr . " AND id_shop = " . $idShop . " LIMIT 1");
        return ['id_product' => $idProduct, 'id_product_attribute' => $idAttr, 'name' => $name, 'ean13' => $pa['ean13'], 'reference' => $pa['reference'], 'qty_stock' => $stockRow ? (int) $stockRow['qty_stock'] : 0];
    }

    protected function getCurrentStock(int $idProduct, int $idAttr): int
    {
        $idShop = (int) $this->context->shop->id;
        $row = Db::getInstance()->getRow("SELECT quantity FROM `" . _DB_PREFIX_ . "stock_available` WHERE id_product = " . $idProduct . " AND id_product_attribute = " . $idAttr . " AND id_shop = " . $idShop);
        return $row ? (int) $row['quantity'] : 0;
    }

    protected function applyStockDelta(int $idProduct, int $idAttr, int $delta): int
    {
        $idShop = (int) $this->context->shop->id;
        $before = $this->getCurrentStock($idProduct, $idAttr);
        $after  = max(0, $before + $delta);
        Db::getInstance()->update('stock_available', ['quantity' => $after], "id_product = " . $idProduct . " AND id_product_attribute = " . $idAttr . " AND id_shop = " . $idShop);
        StockAvailable::synchronize($idProduct);
        return $after;
    }

    protected function setStock(int $idProduct, int $idAttr, int $qty): int
    {
        $idShop = (int) $this->context->shop->id;
        Db::getInstance()->update('stock_available', ['quantity' => $qty], "id_product = " . $idProduct . " AND id_product_attribute = " . $idAttr . " AND id_shop = " . $idShop);
        StockAvailable::synchronize($idProduct);
        return $qty;
    }

    protected function logMovement(array $data): void
    {
        Db::getInstance()->insert('smartstock_movement', ['id_product' => (int) $data['id_product'], 'id_product_attribute' => (int) ($data['id_product_attribute'] ?? 0), 'id_employee' => (int) $this->context->employee->id, 'product_name' => pSQL($data['product_name']), 'reference' => pSQL($data['reference'] ?? ''), 'ean13' => pSQL($data['ean13'] ?? ''), 'label' => pSQL($data['label'] ?? ''), 'qty_before' => (int) $data['qty_before'], 'qty_delta' => (int) $data['qty_delta'], 'qty_after' => (int) $data['qty_after'], 'purchase_price' => 0, 'movement_type' => pSQL($data['movement_type'] ?? 'in'), 'date_add' => date('Y-m-d H:i:s')]);
    }

    protected function t(string $table): string { return _DB_PREFIX_ . $table; }
    protected function adminLink(string $ctrl): string { return $this->context->link->getAdminLink($ctrl); }
    protected function jsonOk(array $data = []): void { $this->ajaxDie(json_encode(array_merge(['success' => true], $data))); }
    protected function jsonErr(string $msg): void { $this->ajaxDie(json_encode(['success' => false, 'error' => $msg])); }
    protected function getNavVars(): array { return ['link_scan' => $this->adminLink('AdminSmartStockScan'), 'link_history' => $this->adminLink('AdminSmartStockHistory'), 'link_status' => $this->adminLink('AdminSmartStockStatus'), 'link_import' => $this->adminLink('AdminSmartStockImport')]; }
}