<?php
if (!defined('_PS_VERSION_')) { exit; }
require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockHistoryController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'EJ Smart stock – Historial';
        parent::__construct();
    }

    public function ajaxProcessExportCsv()
    {
        $rows = $this->fetchMovements(true);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="smartstock_historial_' . date('Ymd_His') . '.csv"');
        $out = fopen('php://output', 'w');
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($out, ['Fecha','Tipo','Producto','Referencia','EAN13','Etiqueta','Antes','Delta','Después','Empleado'], ';');
        foreach ($rows as $r) {
            fputcsv($out, [$r['date_add'],$r['movement_type'],$r['product_name'],$r['reference'],$r['ean13'],$r['label'],$r['qty_before'],$r['qty_delta'],$r['qty_after'],$r['firstname'].' '.$r['lastname']], ';');
        }
        fclose($out); exit;
    }

    public function initContent()
    {
        parent::initContent();
        $this->renderModuleTemplate('history.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'history',
            'admin_link' => $this->adminLink('AdminSmartStockHistory'),
            'movements'  => $this->fetchMovements(false, 200),
            'f_name'     => Tools::getValue('f_name', ''),
            'f_ean'      => Tools::getValue('f_ean',  ''),
            'f_ref'      => Tools::getValue('f_ref',  ''),
            'f_label'    => Tools::getValue('f_label',''),
            'f_type'     => Tools::getValue('f_type', ''),
            'f_from'     => Tools::getValue('f_from', ''),
            'f_to'       => Tools::getValue('f_to',   ''),
        ]));
    }

    private function fetchMovements(bool $all = false, int $limit = 200): array
    {
        $fName  = pSQL(trim(Tools::getValue('f_name',  '')));
        $fEan   = pSQL(trim(Tools::getValue('f_ean',   '')));
        $fRef   = pSQL(trim(Tools::getValue('f_ref',   '')));
        $fLabel = pSQL(trim(Tools::getValue('f_label', '')));
        $fType  = pSQL(trim(Tools::getValue('f_type',  '')));
        $fFrom  = pSQL(trim(Tools::getValue('f_from',  '')));
        $fTo    = pSQL(trim(Tools::getValue('f_to',    '')));

        $where = ['1=1'];
        if ($fName)  $where[] = "m.product_name LIKE '%{$fName}%'";
        if ($fEan)   $where[] = "m.ean13 LIKE '%{$fEan}%'";
        if ($fRef)   $where[] = "m.reference LIKE '%{$fRef}%'";
        if ($fLabel) $where[] = "m.label LIKE '%{$fLabel}%'";
        if ($fType)  $where[] = "m.movement_type = '{$fType}'";
        if ($fFrom)  $where[] = "m.date_add >= '{$fFrom} 00:00:00'";
        if ($fTo)    $where[] = "m.date_add <= '{$fTo} 23:59:59'";

        $limitStr = $all ? '' : "LIMIT {$limit}";
        return Db::getInstance()->executeS("
            SELECT m.*, e.firstname, e.lastname
            FROM `{$this->t('smartstock_movement')}` m
            LEFT JOIN `{$this->t('employee')}` e ON e.id_employee = m.id_employee
            WHERE " . implode(' AND ', $where) . "
            ORDER BY m.date_add DESC {$limitStr}
        ") ?: [];
    }
}
