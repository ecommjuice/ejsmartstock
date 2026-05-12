<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockInventoryController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'EJ Smart stock - Inventario fisico';
        parent::__construct();
        $this->ensureInventoryTables();
    }

    public function initContent()
    {
        parent::initContent();

        $inventory = $this->getCurrentInventory();
        $idInventory = $inventory ? (int) $inventory['id_inventory'] : 0;

        $this->renderModuleTemplate('inventory.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'inventory',
            'admin_link' => $this->adminLink('AdminSmartStockInventory'),
            'current_inventory_id' => $idInventory,
            'current_inventory_status' => $inventory ? (string) $inventory['status'] : '',
        ]));
    }

    public function ajaxProcessStartInventory()
    {
        $current = $this->getCurrentInventory();
        if ($current) {
            $message = $current['status'] === 'open'
                ? 'Ya hay una sesion abierta.'
                : 'Hay una sesion cerrada pendiente de volcar.';
            $this->jsonOk($this->payload((int) $current['id_inventory'], $message));
        }

        $now = date('Y-m-d H:i:s');
        $ok = Db::getInstance()->insert('smartstock_inventory', [
            'id_employee' => (int) $this->context->employee->id,
            'id_shop' => $this->getContextShopId(),
            'status' => 'open',
            'date_add' => $now,
            'date_upd' => $now,
        ]);

        if (!$ok) {
            $this->jsonErr('No se pudo crear la sesion de inventario.');
        }

        $this->jsonOk($this->payload((int) Db::getInstance()->Insert_ID(), 'Sesion creada.'));
    }

    public function ajaxProcessScanInventoryItem()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        $raw = trim((string) Tools::getValue('ean', ''));
        $qty = max(1, (int) Tools::getValue('qty', 1));

        $inventory = $this->getInventory($idInventory);
        if (!$inventory || $inventory['status'] !== 'open') {
            $this->jsonErr('Abre una sesion de inventario antes de escanear.');
        }

        if ($raw === '') {
            $this->jsonErr('Codigo vacio.');
        }

        $product = $this->findScannedProduct($raw);
        if (!$product) {
            $this->jsonErr('Producto no encontrado: ' . Tools::safeOutput($raw));
        }

        $idProduct = (int) $product['id_product'];
        $idAttr = (int) $product['id_product_attribute'];
        $line = $this->getLineByProduct($idInventory, $idProduct, $idAttr);
        $now = date('Y-m-d H:i:s');
        $systemQty = $this->getCurrentStock($idProduct, $idAttr);

        if ($line) {
            $counted = (int) $line['qty_counted'] + $qty;
            Db::getInstance()->update('smartstock_inventory_line', [
                'qty_system' => $systemQty,
                'qty_counted' => $counted,
                'qty_difference' => $counted - $systemQty,
                'date_upd' => $now,
            ], 'id_inventory_line = ' . (int) $line['id_inventory_line']);
        } else {
            $counted = $qty;
            Db::getInstance()->insert('smartstock_inventory_line', [
                'id_inventory' => $idInventory,
                'id_product' => $idProduct,
                'id_product_attribute' => $idAttr,
                'product_name' => pSQL((string) $product['name']),
                'reference' => pSQL((string) ($product['reference'] ?? '')),
                'ean13' => pSQL((string) ($product['ean13'] ?? '')),
                'qty_system' => $systemQty,
                'qty_counted' => $counted,
                'qty_difference' => $counted - $systemQty,
                'date_add' => $now,
                'date_upd' => $now,
            ]);
        }

        $this->touchInventory($idInventory);
        $this->jsonOk($this->payload($idInventory, 'Articulo acumulado.'));
    }

    public function ajaxProcessUpdateInventoryQty()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        $idLine = (int) Tools::getValue('id_inventory_line');
        $qty = max(0, (int) Tools::getValue('qty', 0));

        $inventory = $this->getInventory($idInventory);
        if (!$inventory || !in_array($inventory['status'], ['open', 'closed'], true)) {
            $this->jsonErr('Esta sesion ya no se puede editar.');
        }

        $line = $this->getLine($idInventory, $idLine);
        if (!$line) {
            $this->jsonErr('Linea no encontrada.');
        }

        $systemQty = $this->getCurrentStock((int) $line['id_product'], (int) $line['id_product_attribute']);
        Db::getInstance()->update('smartstock_inventory_line', [
            'qty_system' => $systemQty,
            'qty_counted' => $qty,
            'qty_difference' => $qty - $systemQty,
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_inventory_line = ' . $idLine);

        $this->touchInventory($idInventory);
        $this->jsonOk($this->payload($idInventory, 'Cantidad actualizada.'));
    }

    public function ajaxProcessRemoveInventoryLine()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        $idLine = (int) Tools::getValue('id_inventory_line');

        $inventory = $this->getInventory($idInventory);
        if (!$inventory || !in_array($inventory['status'], ['open', 'closed'], true)) {
            $this->jsonErr('Esta sesion ya no se puede editar.');
        }

        Db::getInstance()->delete('smartstock_inventory_line', 'id_inventory = ' . $idInventory . ' AND id_inventory_line = ' . $idLine);
        $this->touchInventory($idInventory);
        $this->jsonOk($this->payload($idInventory, 'Linea eliminada.'));
    }

    public function ajaxProcessGetInventoryLines()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        if (!$this->getInventory($idInventory)) {
            $this->jsonErr('Sesion no encontrada.');
        }

        $this->refreshInventoryDiffs($idInventory);
        $this->jsonOk($this->payload($idInventory));
    }

    public function ajaxProcessCloseInventory()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        $inventory = $this->getInventory($idInventory);
        if (!$inventory || $inventory['status'] !== 'open') {
            $this->jsonErr('Solo se puede finalizar una sesion abierta.');
        }

        $this->refreshInventoryDiffs($idInventory);
        Db::getInstance()->update('smartstock_inventory', [
            'status' => 'closed',
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_inventory = ' . $idInventory);

        $this->jsonOk($this->payload($idInventory, 'Conteo finalizado. Revisa diferencias antes de volcar.'));
    }

    public function ajaxProcessApplyInventory()
    {
        $idInventory = (int) Tools::getValue('id_inventory');
        $inventory = $this->getInventory($idInventory);
        if (!$inventory || $inventory['status'] !== 'closed') {
            $this->jsonErr('Finaliza el conteo antes de volcar ajustes.');
        }

        $lines = $this->getLines($idInventory);
        if (empty($lines)) {
            $this->jsonErr('La sesion no tiene lineas para aplicar.');
        }

        Db::getInstance()->execute('START TRANSACTION');
        try {
            foreach ($lines as $line) {
                $idProduct = (int) $line['id_product'];
                $idAttr = (int) $line['id_product_attribute'];
                $counted = max(0, (int) $line['qty_counted']);
                $before = $this->getCurrentStock($idProduct, $idAttr);
                $after = $this->setStock($idProduct, $idAttr, $counted);
                $delta = $after - $before;

                Db::getInstance()->update('smartstock_inventory_line', [
                    'qty_system' => $before,
                    'qty_counted' => $after,
                    'qty_difference' => $delta,
                    'date_upd' => date('Y-m-d H:i:s'),
                ], 'id_inventory_line = ' . (int) $line['id_inventory_line']);

                $this->logMovement([
                    'id_product' => $idProduct,
                    'id_product_attribute' => $idAttr,
                    'product_name' => (string) $line['product_name'],
                    'reference' => (string) $line['reference'],
                    'ean13' => (string) $line['ean13'],
                    'label' => 'Inventario fisico #' . $idInventory,
                    'qty_before' => $before,
                    'qty_delta' => $delta,
                    'qty_after' => $after,
                    'purchase_price' => 0,
                    'movement_type' => 'inventory',
                ]);
            }

            Db::getInstance()->update('smartstock_inventory', [
                'status' => 'applied',
                'date_upd' => date('Y-m-d H:i:s'),
                'date_applied' => date('Y-m-d H:i:s'),
            ], 'id_inventory = ' . $idInventory);
            Db::getInstance()->execute('COMMIT');
        } catch (Throwable $e) {
            Db::getInstance()->execute('ROLLBACK');
            $this->jsonErr('No se pudieron aplicar los ajustes: ' . $e->getMessage());
        }

        $this->jsonOk($this->payload($idInventory, 'Inventario aplicado. Stock ajustado al conteo fisico.'));
    }

    private function payload(int $idInventory, string $message = ''): array
    {
        $inventory = $this->getInventory($idInventory);
        $lines = $this->getLines($idInventory);
        $summary = [
            'lines' => count($lines),
            'counted' => 0,
            'system' => 0,
            'difference' => 0,
        ];

        foreach ($lines as &$line) {
            $line['id_inventory_line'] = (int) $line['id_inventory_line'];
            $line['id_product'] = (int) $line['id_product'];
            $line['id_product_attribute'] = (int) $line['id_product_attribute'];
            $line['qty_system'] = (int) $line['qty_system'];
            $line['qty_counted'] = (int) $line['qty_counted'];
            $line['qty_difference'] = (int) $line['qty_difference'];
            $summary['counted'] += $line['qty_counted'];
            $summary['system'] += $line['qty_system'];
            $summary['difference'] += $line['qty_difference'];
        }
        unset($line);

        return [
            'message' => $message,
            'inventory' => $inventory,
            'lines' => $lines,
            'summary' => $summary,
        ];
    }

    private function getCurrentInventory(): ?array
    {
        $rows = Db::getInstance()->executeS("
            SELECT *
            FROM `{$this->t('smartstock_inventory')}`
            WHERE id_employee = " . (int) $this->context->employee->id . "
                AND id_shop = " . $this->getContextShopId() . "
                AND status IN ('open','closed')
            ORDER BY id_inventory DESC
            LIMIT 1
        ");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    private function getInventory(int $idInventory): ?array
    {
        if ($idInventory <= 0) {
            return null;
        }

        $rows = Db::getInstance()->executeS("
            SELECT *
            FROM `{$this->t('smartstock_inventory')}`
            WHERE id_inventory = {$idInventory}
                AND id_shop = " . $this->getContextShopId() . "
            LIMIT 1
        ");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    private function getLine(int $idInventory, int $idLine): ?array
    {
        $rows = Db::getInstance()->executeS("
            SELECT *
            FROM `{$this->t('smartstock_inventory_line')}`
            WHERE id_inventory = {$idInventory}
                AND id_inventory_line = {$idLine}
            LIMIT 1
        ");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    private function getLineByProduct(int $idInventory, int $idProduct, int $idAttr): ?array
    {
        $rows = Db::getInstance()->executeS("
            SELECT *
            FROM `{$this->t('smartstock_inventory_line')}`
            WHERE id_inventory = {$idInventory}
                AND id_product = {$idProduct}
                AND id_product_attribute = {$idAttr}
            LIMIT 1
        ");

        return (is_array($rows) && isset($rows[0])) ? $rows[0] : null;
    }

    private function getLines(int $idInventory): array
    {
        return Db::getInstance()->executeS("
            SELECT *
            FROM `{$this->t('smartstock_inventory_line')}`
            WHERE id_inventory = {$idInventory}
            ORDER BY date_upd DESC, id_inventory_line DESC
        ") ?: [];
    }

    private function refreshInventoryDiffs(int $idInventory): void
    {
        foreach ($this->getLines($idInventory) as $line) {
            $systemQty = $this->getCurrentStock((int) $line['id_product'], (int) $line['id_product_attribute']);
            $counted = (int) $line['qty_counted'];
            Db::getInstance()->update('smartstock_inventory_line', [
                'qty_system' => $systemQty,
                'qty_difference' => $counted - $systemQty,
                'date_upd' => (string) $line['date_upd'],
            ], 'id_inventory_line = ' . (int) $line['id_inventory_line']);
        }
    }

    private function touchInventory(int $idInventory): void
    {
        Db::getInstance()->update('smartstock_inventory', [
            'date_upd' => date('Y-m-d H:i:s'),
        ], 'id_inventory = ' . $idInventory);
    }

    private function findScannedProduct(string $raw): ?array
    {
        foreach ($this->buildBarcodeCandidates($raw) as $candidate) {
            if (Tools::strlen($candidate) < 4) {
                continue;
            }

            $product = $this->searchByEan($candidate);
            if ($product) {
                return $product;
            }
        }

        foreach ($this->buildBarcodeCandidates($raw) as $candidate) {
            if (Tools::strlen($candidate) < 2) {
                continue;
            }

            $results = $this->searchByQuery($candidate);
            if (!empty($results)) {
                return $results[0];
            }
        }

        return null;
    }

    private function buildBarcodeCandidates(string $raw): array
    {
        $cleanText = preg_replace('/[\x00-\x1F\x7F]/u', '', trim($raw));
        $cleanText = trim((string) $cleanText);
        $digits = (string) preg_replace('/\D+/', '', $cleanText);
        $candidates = [];

        if ($digits !== '') {
            $candidates[] = $digits;
            foreach ([14, 13, 12, 8] as $length) {
                if (Tools::strlen($digits) > $length) {
                    $candidates[] = Tools::substr($digits, -$length);
                }
            }
            if (Tools::strlen($digits) === 13 && Tools::substr($digits, 0, 1) === '0') {
                $candidates[] = Tools::substr($digits, 1);
            }
            if (Tools::strlen($digits) === 12) {
                $candidates[] = '0' . $digits;
            }
        }

        $alnum = trim((string) preg_replace('/[^A-Za-z0-9_\-\.]/', '', $cleanText));
        if ($alnum !== '') {
            $candidates[] = $alnum;
        }

        return array_values(array_unique(array_filter($candidates, static function ($value) {
            return Tools::strlen((string) $value) >= 2;
        })));
    }

    private function ensureInventoryTables(): void
    {
        $e = _MYSQL_ENGINE_;
        $p = _DB_PREFIX_;

        Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `{$p}smartstock_inventory` (
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
        ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;");

        Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `{$p}smartstock_inventory_line` (
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
        ) ENGINE={$e} DEFAULT CHARSET=utf8mb4;");
    }
}
