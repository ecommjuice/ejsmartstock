<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_1_0($module)
{
    $e = _MYSQL_ENGINE_;
    $p = _DB_PREFIX_;

    $sql = [
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

    foreach ($sql as $query) {
        if (!Db::getInstance()->execute($query)) {
            return false;
        }
    }

    if (!Tab::getIdFromClassName('AdminSmartStockInventory')) {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = 'AdminSmartStockInventory';
        $tab->module = $module->name;
        $tab->id_parent = (int) Tab::getIdFromClassName('AdminSmartStock');
        $tab->icon = 'inventory_2';

        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[$lang['id_lang']] = 'Inventario fisico';
        }

        if (!$tab->add()) {
            return false;
        }
    }

    return true;
}
