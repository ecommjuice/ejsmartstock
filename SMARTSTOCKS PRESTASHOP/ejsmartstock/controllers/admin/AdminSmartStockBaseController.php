<?php
/**
 * Base controller para el módulo SmartStock.
 * Compatible con PrestaShop 8.1.6.
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

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

    /**
     * Renderiza una plantilla Smarty del módulo.
     *
     * Ruta base:
     * /modules/smartstock/views/templates/admin/smartstock/
     */
    protected function renderModuleTemplate(string $tplName, array $vars = []): void
    {
        $tplName = ltrim($tplName, '/');

        // Evita path traversal.
        if (strpos($tplName, '..') !== false || strpos($tplName, '\\') !== false) {
            $this->errors[] = 'Nombre de plantilla no válido.';
            return;
        }

        $tplDir = _PS_MODULE_DIR_ . 'smartstock/views/templates/admin/smartstock/';
        $tplPath = $tplDir . $tplName;

        if (!is_file($tplPath)) {
            $this->errors[] = 'No se encontró la plantilla: ' . $tplName;
            return;
        }

        $smarty = $this->context->smarty;

        $smarty->assign([
            'tpl_dir' => $tplDir,
            'token' => $this->token,
        ]);

        if (!empty($vars)) {
            $smarty->assign($vars);
        }

        $this->content .= $smarty->fetch($tplPath);
    }

    /**
     * Devuelve el ID de tienda actual.
     * Si el BO está en contexto "Todas las tiendas", usa la tienda por defecto.
     */
    protected function getContextShopId(): int
    {
        $idShop = 0;

        if (isset($this->context->shop) && (int) $this->context->shop->id > 0) {
            $idShop = (int) $this->context->shop->id;
        }

        if ($idShop <= 0) {
            $idShop = (int) Configuration::get('PS_SHOP_DEFAULT');
        }

        return $idShop;
    }

    /**
     * Busca un producto o combinación por EAN13.
     */
    protected function searchByEan(string $ean): ?array
    {
        $ean = trim($ean);

        if ($ean === '') {
            return null;
        }

        $idLang = (int) $this->context->language->id;
        $idShop = $this->getContextShopId();
        $eanSql = pSQL($ean);
        $p = _DB_PREFIX_;

        /*
         * Primero busca producto simple.
         * Se usa product_shop y product_lang.id_shop para compatibilidad multitienda.
         */
        $rows = Db::getInstance()->executeS(
            "SELECT 
                p.id_product,
                0 AS id_product_attribute,
                pl.name,
                p.ean13,
                p.reference
            FROM `{$p}product` p
            INNER JOIN `{$p}product_shop` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            LEFT JOIN `{$p}product_lang` pl
                ON pl.id_product = p.id_product
                AND pl.id_lang = {$idLang}
                AND pl.id_shop = {$idShop}
            WHERE p.ean13 = '{$eanSql}'
                AND ps.active = 1
            LIMIT 1"
        );
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : false;

        if ($row) {
            $row['id_product'] = (int) $row['id_product'];
            $row['id_product_attribute'] = 0;
            $row['qty_stock'] = $this->getCurrentStock((int) $row['id_product'], 0);

            return $row;
        }

        /*
         * Si no encuentra producto simple, busca combinación.
         */
        return $this->searchCombinationByEan($ean, $idLang, $idShop);
    }

    /**
     * Busca productos y combinaciones por nombre, referencia o EAN13.
     */
    protected function searchByQuery(string $q): array
    {
        $q = trim($q);

        if ($q === '') {
            return [];
        }

        $idLang = (int) $this->context->language->id;
        $idShop = $this->getContextShopId();

        /*
         * Escapa caracteres especiales de LIKE.
         * pSQL protege la cadena frente a SQL injection.
         */
        $qLike = pSQL(addcslashes($q, '%_'));
        $p = _DB_PREFIX_;

        /*
         * Productos simples.
         */
        $rows = Db::getInstance()->executeS(
            "SELECT 
                p.id_product,
                0 AS id_product_attribute,
                pl.name,
                p.ean13,
                p.reference
            FROM `{$p}product` p
            INNER JOIN `{$p}product_shop` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            LEFT JOIN `{$p}product_lang` pl
                ON pl.id_product = p.id_product
                AND pl.id_lang = {$idLang}
                AND pl.id_shop = {$idShop}
            WHERE ps.active = 1
                AND (
                    pl.name LIKE '%{$qLike}%'
                    OR p.reference LIKE '%{$qLike}%'
                    OR p.ean13 LIKE '%{$qLike}%'
                )
            ORDER BY pl.name ASC
            LIMIT 15"
        );

        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as &$row) {
            $row['id_product'] = (int) $row['id_product'];
            $row['id_product_attribute'] = 0;
            $row['qty_stock'] = $this->getCurrentStock((int) $row['id_product'], 0);
        }

        unset($row);

        /*
         * Combinaciones.
         */
        $paRows = Db::getInstance()->executeS(
            "SELECT 
                pa.id_product_attribute,
                pa.id_product,
                pa.ean13,
                pa.reference
            FROM `{$p}product_attribute` pa
            INNER JOIN `{$p}product` p
                ON p.id_product = pa.id_product
            INNER JOIN `{$p}product_shop` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            INNER JOIN `{$p}product_attribute_shop` pas
                ON pas.id_product_attribute = pa.id_product_attribute
                AND pas.id_shop = {$idShop}
            WHERE ps.active = 1
                AND (
                    pa.ean13 LIKE '%{$qLike}%'
                    OR pa.reference LIKE '%{$qLike}%'
                )
            ORDER BY pa.id_product DESC
            LIMIT 10"
        );

        if (!is_array($paRows)) {
            $paRows = [];
        }

        $combos = [];

        foreach ($paRows as $pa) {
            $combo = $this->buildCombinationResultRow($pa, $idLang, $idShop);

            if ($combo !== null) {
                $combos[] = $combo;
            }
        }

        return array_merge($rows, $combos);
    }

    /**
     * Busca una combinación concreta por EAN13.
     */
    private function searchCombinationByEan(string $ean, int $idLang, int $idShop): ?array
    {
        $eanSql = pSQL(trim($ean));
        $p = _DB_PREFIX_;

        $paRows = Db::getInstance()->executeS(
            "SELECT 
                pa.id_product_attribute,
                pa.id_product,
                pa.ean13,
                pa.reference
            FROM `{$p}product_attribute` pa
            INNER JOIN `{$p}product` p
                ON p.id_product = pa.id_product
            INNER JOIN `{$p}product_shop` ps
                ON ps.id_product = p.id_product
                AND ps.id_shop = {$idShop}
            INNER JOIN `{$p}product_attribute_shop` pas
                ON pas.id_product_attribute = pa.id_product_attribute
                AND pas.id_shop = {$idShop}
            WHERE pa.ean13 = '{$eanSql}'
                AND ps.active = 1
            LIMIT 1"
        );
        $pa = (is_array($paRows) && isset($paRows[0])) ? $paRows[0] : false;

        if (!$pa) {
            return null;
        }

        return $this->buildCombinationResultRow($pa, $idLang, $idShop);
    }

    /**
     * Construye el array de respuesta para una combinación.
     */
    private function buildCombinationResultRow(array $pa, int $idLang, int $idShop): ?array
    {
        $idProduct = (int) ($pa['id_product'] ?? 0);
        $idAttr = (int) ($pa['id_product_attribute'] ?? 0);

        if ($idProduct <= 0 || $idAttr <= 0) {
            return null;
        }

        $baseName = $this->getProductName($idProduct, $idLang, $idShop);
        $attrNames = $this->getCombinationAttributeNames($idAttr, $idLang);

        $name = $baseName;

        if (!empty($attrNames)) {
            $name .= ' (' . implode(' / ', $attrNames) . ')';
        }

        return [
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttr,
            'name' => $name,
            'ean13' => (string) ($pa['ean13'] ?? ''),
            'reference' => (string) ($pa['reference'] ?? ''),
            'qty_stock' => $this->getCurrentStock($idProduct, $idAttr),
        ];
    }

    /**
     * Devuelve el nombre del producto en la tienda e idioma actuales.
     */
    private function getProductName(int $idProduct, int $idLang, int $idShop): string
    {
        $p = _DB_PREFIX_;

        $rows = Db::getInstance()->executeS(
            "SELECT name
            FROM `{$p}product_lang`
            WHERE id_product = {$idProduct}
                AND id_lang = {$idLang}
                AND id_shop = {$idShop}
            LIMIT 1"
        );
        $row = (is_array($rows) && isset($rows[0])) ? $rows[0] : false;

        if ($row && isset($row['name'])) {
            return (string) $row['name'];
        }

        return '';
    }

    /**
     * Devuelve los nombres de atributos de una combinación.
     */
    private function getCombinationAttributeNames(int $idProductAttribute, int $idLang): array
    {
        $p = _DB_PREFIX_;

        $attrs = Db::getInstance()->executeS(
            "SELECT al.name
            FROM `{$p}product_attribute_combination` pac
            INNER JOIN `{$p}attribute` a
                ON a.id_attribute = pac.id_attribute
            INNER JOIN `{$p}attribute_lang` al
                ON al.id_attribute = a.id_attribute
                AND al.id_lang = {$idLang}
            WHERE pac.id_product_attribute = {$idProductAttribute}
            ORDER BY a.id_attribute_group ASC, a.position ASC"
        );

        if (!is_array($attrs)) {
            return [];
        }

        return array_column($attrs, 'name');
    }

    /**
     * Devuelve el stock actual usando la API nativa de PrestaShop.
     */
    protected function getCurrentStock(int $idProduct, int $idAttr): int
    {
        if ($idProduct <= 0 || $idAttr < 0) {
            return 0;
        }

        return (int) StockAvailable::getQuantityAvailableByProduct(
            $idProduct,
            $idAttr,
            $this->getContextShopId()
        );
    }

    /**
     * Aplica un delta de stock.
     *
     * Ejemplo:
     * +5 aumenta 5 unidades.
     * -3 descuenta 3 unidades.
     */
    protected function applyStockDelta(int $idProduct, int $idAttr, int $delta): int
    {
        if ($idProduct <= 0 || $idAttr < 0) {
            return 0;
        }

        $idShop = $this->getContextShopId();

        $before = $this->getCurrentStock($idProduct, $idAttr);
        $after = max(0, $before + $delta);

        /*
         * Importante:
         * No actualizar ps_stock_available directamente.
         * StockAvailable::setQuantity crea la fila si no existe,
         * dispara hooks internos y limpia caché.
         */
        StockAvailable::setQuantity(
            $idProduct,
            $idAttr,
            $after,
            $idShop,
            true
        );

        return $after;
    }

    /**
     * Establece el stock exacto de un producto o combinación.
     */
    protected function setStock(int $idProduct, int $idAttr, int $qty): int
    {
        if ($idProduct <= 0 || $idAttr < 0) {
            return 0;
        }

        $idShop = $this->getContextShopId();
        $qty = max(0, $qty);

        StockAvailable::setQuantity(
            $idProduct,
            $idAttr,
            $qty,
            $idShop,
            true
        );

        return $qty;
    }

    /**
     * Registra un movimiento en la tabla smartstock_movement.
     *
     * La tabla debe existir en el install() del módulo.
     */
    protected function logMovement(array $data): void
    {
        Db::getInstance()->insert('smartstock_movement', [
            'id_product' => (int) ($data['id_product'] ?? 0),
            'id_product_attribute' => (int) ($data['id_product_attribute'] ?? 0),
            'id_employee' => (int) $this->context->employee->id,
            'product_name' => pSQL((string) ($data['product_name'] ?? '')),
            'reference' => pSQL((string) ($data['reference'] ?? '')),
            'ean13' => pSQL((string) ($data['ean13'] ?? '')),
            'label' => pSQL((string) ($data['label'] ?? '')),
            'qty_before' => (int) ($data['qty_before'] ?? 0),
            'qty_delta' => (int) ($data['qty_delta'] ?? 0),
            'qty_after' => (int) ($data['qty_after'] ?? 0),
            'purchase_price' => isset($data['purchase_price']) ? (float) $data['purchase_price'] : 0,
            'movement_type' => pSQL((string) ($data['movement_type'] ?? 'in')),
            'date_add' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Devuelve una tabla con prefijo.
     */
    protected function t(string $table): string
    {
        return _DB_PREFIX_ . $table;
    }

    /**
     * Genera enlace a controlador admin.
     */
    protected function adminLink(string $ctrl): string
    {
        return $this->context->link->getAdminLink($ctrl);
    }

    /**
     * Respuesta JSON correcta para AJAX.
     */
    protected function jsonOk(array $data = []): void
    {
        $this->cleanAjaxOutput();
        header('Content-Type: application/json; charset=utf-8');

        $this->ajaxDie(json_encode(
            array_merge(['success' => true], $data),
            JSON_UNESCAPED_UNICODE
        ));
    }

    /**
     * Respuesta JSON de error para AJAX.
     */
    protected function jsonErr(string $msg): void
    {
        $this->cleanAjaxOutput();
        header('Content-Type: application/json; charset=utf-8');

        $this->ajaxDie(json_encode([
            'success' => false,
            'error' => $msg,
        ], JSON_UNESCAPED_UNICODE));
    }

    /**
     * Evita que avisos HTML del back office rompan las respuestas AJAX JSON.
     */
    private function cleanAjaxOutput(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
    }

    /**
     * Variables comunes para navegación del módulo.
     */
    protected function getNavVars(): array
    {
        return [
            'link_scan' => $this->adminLink('AdminSmartStockScan'),
            'link_history' => $this->adminLink('AdminSmartStockHistory'),
            'link_status' => $this->adminLink('AdminSmartStockStatus'),
            'link_import' => $this->adminLink('AdminSmartStockImport'),
        ];
    }
}
