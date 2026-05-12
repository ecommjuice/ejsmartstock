<?php
if (!defined('_PS_VERSION_')) { exit; }

require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'Smart Stock';
        parent::__construct();
    }

    public function initContent()
    {
        Tools::redirectAdmin($this->adminLink('AdminSmartStockScan'));
    }
}
