<?php
/**
 * SmartStock – Gestión de stock avanzada con escáner
 * PrestaShop 8.x compatible
 *
 * @author    Tu Tienda
 * @version   1.1.0
 * @license   MIT
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

class SmartStock extends Module
{
    public function __construct()
    {
        $this->name          = 'smartstock';
        $this->tab           = 'administration';
        $this->version       = '1.1.0';
        $this->author        = 'Ecommjuice';
        $this->need_instance = 0;
        $this->bootstrap     = true;

        parent::__construct();

        $this->displayName = $this->l('EJ Smart stock');
        $this->description = $this->l('Gestion de stock con escaner EAN13, inventario fisico, historial, informe y carga masiva CSV.');
        $this->ps_versions_compliancy = ['min' => '8.0.0', 'max' => _PS_VERSION_];
    }

    /* ───────────────────────────── Install ──────────────────────────────── */

    public function install()
    {
        return parent::install()
            && $this->createTabs()
            && $this->createTables()
            && $this->registerHook('actionAdminControllerSetMedia');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && $this->removeTabs()
            && $this->dropTables();
    }

    /* ───────────────────── Tabs (menu back-office) ───────────────────────── */

    private function createTabs(): bool
    {
        $tabs = [
            [
                'class'  => 'AdminSmartStock',
                'parent' => 'AdminCatalog',
                'icon'   => 'inventory',
                'name'   => 'EJ Smart stock',
            ],
            [
                'class'  => 'AdminSmartStockScan',
                'parent' => 'AdminSmartStock',
                'icon'   => 'qr_code_scanner',
                'name'   => 'Escáner / Movimientos',
            ],
            [
                'class'  => 'AdminSmartStockHistory',
                'parent' => 'AdminSmartStock',
                'icon'   => 'history',
                'name'   => 'Historial',
            ],
            [
                'class'  => 'AdminSmartStockInventory',
                'parent' => 'AdminSmartStock',
                'icon'   => 'inventory_2',
                'name'   => 'Inventario fisico',
            ],
            [
                'class'  => 'AdminSmartStockStatus',
                'parent' => 'AdminSmartStock',
                'icon'   => 'bar_chart',
                'name'   => 'Estado del stock',
            ],
            [
                'class'  => 'AdminSmartStockImport',
                'parent' => 'AdminSmartStock',
                'icon'   => 'upload_file',
                'name'   => 'Importación CSV',
            ],
        ];

        foreach ($tabs as $def) {
            $tab             = new Tab();
            $tab->active     = 1;
            $tab->class_name = $def['class'];
            $tab->module     = $this->name;
            $tab->id_parent  = (int) Tab::getIdFromClassName($def['parent']);
            $tab->icon       = $def['icon'] ?? '';

            foreach (Language::getLanguages(false) as $lang) {
                $tab->name[$lang['id_lang']] = $def['name'];
            }

            if (!$tab->add()) {
                return false;
            }
        }
        return true;
    }

    private function removeTabs(): bool
    {
        $classes = [
            'AdminSmartStockImport',
            'AdminSmartStockStatus',
            'AdminSmartStockInventory',
            'AdminSmartStockHistory',
            'AdminSmartStockScan',
            'AdminSmartStock',
        ];
        foreach ($classes as $class) {
            $id = Tab::getIdFromClassName($class);
            if ($id) {
                (new Tab($id))->delete();
            }
        }
        return true;
    }

    /* ────────────────────────────── DB ──────────────────────────────────── */

    private function createTables(): bool
    {
        $e = _MYSQL_ENGINE_;
        $p = _DB_PREFIX_;

        $sql = [
            /* Movimientos de stock */
            "CREATE TABLE IF NOT EXISTS `{$p}smartstock_movement` (
                `id_movement`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_product`           INT UNSIGNED NOT NULL,
                `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
                `id_employee`          INT UNSIGNED NOT NULL,
                `product_name`         VARCHAR(255) NOT NULL,
                `reference`            VARCHAR(64)  NOT NULL DEFAULT '',
                `ean13`                VARCHAR(20)  NOT NULL DEFAULT '',
                `label`                VARCHAR(128) NOT NULL DEFAULT '',
                `qty_before`           INT          NOT NULL DEFAULT 0,
                `qty_delta`            INT          NOT NULL DEFAULT 0,
                `qty_after`            INT          NOT NULL DEFAULT 0,
                `purchase_price`       DECIMAL(20,6) NOT NULL DEFAULT 0,
                `movement_type`        ENUM('in','out','inventory') NOT NULL DEFAULT 'in',
                `date_add`             DATETIME     NOT NULL,
                PRIMARY KEY (`id_movement`),
                KEY `id_product` (`id_product`, `id_product_attribute`),
                KEY `date_add`   (`date_add`),
                KEY `ean13`      (`ean13`)
            ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;",

            /* Sesiones de inventario masivo */
            "CREATE TABLE IF NOT EXISTS `{$p}smartstock_session` (
                `id_session`  INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `name`        VARCHAR(128) NOT NULL,
                `status`      ENUM('open','applied') NOT NULL DEFAULT 'open',
                `id_employee` INT UNSIGNED NOT NULL,
                `date_add`    DATETIME     NOT NULL,
                `date_upd`    DATETIME     NOT NULL,
                PRIMARY KEY (`id_session`)
            ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;",

            /* Líneas de sesión */
            "CREATE TABLE IF NOT EXISTS `{$p}smartstock_session_line` (
                `id_line`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_session`           INT UNSIGNED NOT NULL,
                `id_product`           INT UNSIGNED NOT NULL,
                `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
                `ean13`                VARCHAR(20)  NOT NULL DEFAULT '',
                `product_name`         VARCHAR(255) NOT NULL,
                `qty_system`           INT          NOT NULL DEFAULT 0,
                `qty_counted`          INT          NOT NULL DEFAULT 0,
                `date_add`             DATETIME     NOT NULL,
                `date_upd`             DATETIME     NOT NULL,
                PRIMARY KEY (`id_line`),
                KEY `id_session` (`id_session`)
            ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;",

            /* Sesiones de inventario fisico por acumulacion */
            "CREATE TABLE IF NOT EXISTS `{$p}smartstock_inventory` (
                `id_inventory` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_employee`  INT UNSIGNED NOT NULL,
                `id_shop`      INT UNSIGNED NOT NULL,
                `status`       ENUM('open','closed','applied') NOT NULL DEFAULT 'open',
                `date_add`     DATETIME NOT NULL,
                `date_upd`     DATETIME NOT NULL,
                `date_applied` DATETIME NULL,
                PRIMARY KEY (`id_inventory`),
                KEY `id_employee` (`id_employee`),
                KEY `id_shop` (`id_shop`),
                KEY `status` (`status`)
            ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;",

            /* Lineas acumuladas de inventario fisico */
            "CREATE TABLE IF NOT EXISTS `{$p}smartstock_inventory_line` (
                `id_inventory_line`    INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_inventory`         INT UNSIGNED NOT NULL,
                `id_product`           INT UNSIGNED NOT NULL,
                `id_product_attribute` INT UNSIGNED NOT NULL DEFAULT 0,
                `product_name`         VARCHAR(255) NOT NULL,
                `reference`            VARCHAR(64)  NOT NULL DEFAULT '',
                `ean13`                VARCHAR(20)  NOT NULL DEFAULT '',
                `qty_system`           INT NOT NULL DEFAULT 0,
                `qty_counted`          INT NOT NULL DEFAULT 0,
                `qty_difference`       INT NOT NULL DEFAULT 0,
                `date_add`             DATETIME NOT NULL,
                `date_upd`             DATETIME NOT NULL,
                PRIMARY KEY (`id_inventory_line`),
                UNIQUE KEY `inventory_product` (`id_inventory`, `id_product`, `id_product_attribute`),
                KEY `id_inventory` (`id_inventory`),
                KEY `ean13` (`ean13`)
            ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;",
        ];

        foreach ($sql as $q) {
            if (!Db::getInstance()->execute($q)) {
                return false;
            }
        }
        return true;
    }

    private function dropTables(): bool
    {
        $p = _DB_PREFIX_;
        foreach (['smartstock_movement', 'smartstock_session', 'smartstock_session_line', 'smartstock_inventory_line', 'smartstock_inventory'] as $t) {
            Db::getInstance()->execute("DROP TABLE IF EXISTS `{$p}{$t}`");
        }
        return true;
    }

    /* ───────────────────── Hooks ────────────────────────────────────────── */

    public function hookActionAdminControllerSetMedia($params)
    {
        $ctrl = Tools::getValue('controller');
        $smartCtrls = [
            'AdminSmartStock', 'AdminSmartStockScan',
            'AdminSmartStockHistory', 'AdminSmartStockInventory', 'AdminSmartStockStatus', 'AdminSmartStockImport',
        ];
        if (in_array($ctrl, $smartCtrls)) {
            $this->context->controller->addCSS($this->_path . 'views/css/smartstock.css');
            $this->context->controller->addJS($this->_path . 'views/js/smartstock.js');
        }
    }

    /* ───────────────────── Redirect getContent ──────────────────────────── */

    public function getContent()
    {
        Tools::redirectAdmin(
            $this->context->link->getAdminLink('AdminSmartStockScan')
        );
    }
}
