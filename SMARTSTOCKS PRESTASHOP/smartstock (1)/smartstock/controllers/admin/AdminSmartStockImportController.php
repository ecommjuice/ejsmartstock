<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockImportController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'Smart Stock – Importación CSV';
        parent::__construct();
    }

    public function ajaxProcessPreviewCsv()
    {
        $result = $this->parseCsvUpload();
        isset($result['error']) ? $this->jsonErr($result['error']) : $this->jsonOk(['rows' => $result['rows'], 'total' => count($result['rows'])]);
    }

    public function ajaxProcessApplyCsv()
    {
        $result = $this->parseCsvUpload();
        if (isset($result['error'])) { $this->jsonErr($result['error']); }

        $type    = pSQL(Tools::getValue('movement_type', 'in'));
        $label   = pSQL(Tools::getValue('label', 'Importación masiva CSV'));
        $applied = 0; $errors = [];
        $idLang  = (int) $this->context->language->id;

        foreach ($result['rows'] as $i => $row) {
            $idProduct = (int) $row['id_product'];
            $idAttr    = (int) $row['id_product_attribute'];
            $qty       = (int) $row['qty'];
            if (!$idProduct) { $errors[] = "Fila ".($i+2).": id_product inválido"; continue; }

            $pInfo  = Db::getInstance()->getRow("SELECT pl.name, p.ean13, p.reference FROM `{$this->t('product')}` p LEFT JOIN `{$this->t('product_lang')}` pl ON pl.id_product = p.id_product AND pl.id_lang = {$idLang} WHERE p.id_product = {$idProduct}");
            $before = $this->getCurrentStock($idProduct, $idAttr);

            if ($type === 'inventory') { $after = $this->setStock($idProduct, $idAttr, $qty); $delta = $after - $before; }
            elseif ($type === 'out')   { $delta = -abs($qty); $after = $this->applyStockDelta($idProduct, $idAttr, $delta); }
            else                       { $delta = abs($qty);  $after = $this->applyStockDelta($idProduct, $idAttr, $delta); }

            $this->logMovement(['id_product'=>$idProduct,'id_product_attribute'=>$idAttr,'product_name'=>$pInfo['name']??'Producto #'.$idProduct,'reference'=>$pInfo['reference']??'','ean13'=>$pInfo['ean13']??'','label'=>$label,'qty_before'=>$before,'qty_delta'=>$delta,'qty_after'=>$after,'movement_type'=>$type]);
            $applied++;
        }
        $this->jsonOk(['applied' => $applied, 'errors' => $errors]);
    }

    public function initContent()
    {
        parent::initContent();
        $this->renderModuleTemplate('import.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'import',
            'admin_link' => $this->adminLink('AdminSmartStockImport'),
        ]));
    }

    private function parseCsvUpload(): array
    {
        if (empty($_FILES['csv_file']['tmp_name'])) return ['error' => 'No se ha subido ningún archivo CSV'];
        $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
        if (!$handle) return ['error' => 'No se pudo abrir el archivo'];

        $firstLine = fgets($handle); rewind($handle);
        $delimiter = strpos($firstLine, ';') !== false ? ';' : ',';
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") rewind($handle);

        $headers = fgetcsv($handle, 0, $delimiter);
        $headers = array_map('strtolower', array_map('trim', $headers));

        if (!in_array('id_product', $headers) || !in_array('qty', $headers)) {
            fclose($handle);
            return ['error' => 'Columnas obligatorias: id_product, qty. Detectadas: '.implode(', ', $headers)];
        }

        $rows = [];
        while (($data = fgetcsv($handle, 0, $delimiter)) !== false) {
            if (count($data) < 2) continue;
            $row = array_combine($headers, array_pad($data, count($headers), ''));
            if (empty($row['id_product'])) continue;
            $rows[] = ['id_product'=>(int)$row['id_product'],'id_product_attribute'=>(int)($row['id_product_attribute']??0),'qty'=>(int)$row['qty'],'purchase_price'=>0];
        }
        fclose($handle);
        return ['rows' => $rows];
    }
}
