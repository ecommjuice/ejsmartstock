<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockScanController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'Smart Stock – Escáner';

        parent::__construct();
    }

    /**
     * Búsqueda desde el input superior del escáner.
     *
     * Importante:
     * - Primero intenta búsqueda exacta por EAN13.
     * - Si no encuentra nada, usa la misma búsqueda flexible que la caja inferior.
     * - Esto corrige lectores que envían prefijos, sufijos, espacios, caracteres invisibles,
     *   códigos GS1 o variantes donde el EAN queda contenido dentro del texto recibido.
     */
    public function ajaxProcessSearchEan()
    {
        $raw = trim((string) Tools::getValue('ean', ''));

        if ($raw === '') {
            $this->jsonErr('Código vacío');
        }

        $candidates = $this->buildBarcodeCandidates($raw);

        if (empty($candidates)) {
            $this->jsonErr('EAN inválido');
        }

        /*
         * 1) Búsqueda exacta por posibles EAN.
         */
        foreach ($candidates as $candidate) {
            if (Tools::strlen($candidate) < 4) {
                continue;
            }

            $product = $this->searchByEan($candidate);

            if ($product) {
                $this->jsonOk([
                    'product' => $product,
                    'matched_by' => 'ean_exact',
                    'searched' => $candidate,
                ]);
            }
        }

        /*
         * 2) Fallback flexible: mismo comportamiento que la caja inferior.
         * Esto es lo que hace que un EAN que funciona abajo también funcione arriba.
         */
        foreach ($candidates as $candidate) {
            if (Tools::strlen($candidate) < 2) {
                continue;
            }

            $results = $this->searchByQuery($candidate);

            if (!empty($results)) {
                /*
                 * Si hay coincidencia exacta dentro de los resultados, priorizarla.
                 */
                foreach ($results as $result) {
                    $resultEan = isset($result['ean13']) ? preg_replace('/\D+/', '', (string) $result['ean13']) : '';
                    $resultRef = isset($result['reference']) ? trim((string) $result['reference']) : '';

                    if ($resultEan !== '' && in_array($resultEan, $candidates, true)) {
                        $this->jsonOk([
                            'product' => $result,
                            'matched_by' => 'ean_query_exact',
                            'searched' => $candidate,
                        ]);
                    }

                    if ($resultRef !== '' && in_array($resultRef, $candidates, true)) {
                        $this->jsonOk([
                            'product' => $result,
                            'matched_by' => 'reference_query_exact',
                            'searched' => $candidate,
                        ]);
                    }
                }

                /*
                 * En escáner normalmente llega un código completo.
                 * Si la búsqueda flexible devuelve resultados, cargamos el primero,
                 * igual que seleccionaría el usuario en la caja inferior.
                 */
                $this->jsonOk([
                    'product' => $results[0],
                    'matched_by' => 'query_fallback',
                    'searched' => $candidate,
                ]);
            }
        }

        $this->jsonErr('Producto no encontrado: ' . Tools::safeOutput($raw));
    }

    /**
     * Búsqueda manual inferior.
     */
    public function ajaxProcessSearchQuery()
    {
        $q = trim((string) Tools::getValue('q', ''));

        if (Tools::strlen($q) < 2) {
            $this->jsonErr('Mínimo 2 caracteres');
        }

        $this->jsonOk([
            'results' => $this->searchByQuery($q),
        ]);
    }

    /**
     * Aplica movimiento de stock.
     */
    public function ajaxProcessApplyMovement()
    {
        $idProduct = (int) Tools::getValue('id_product');
        $idAttr = (int) Tools::getValue('id_product_attribute', 0);
        $type = (string) Tools::getValue('movement_type', 'in');
        $qty = (int) Tools::getValue('qty');

        $label = (string) Tools::getValue('label', '');
        $name = (string) Tools::getValue('product_name', '');
        $ean = (string) Tools::getValue('ean13', '');
        $ref = (string) Tools::getValue('reference', '');
        $purchasePrice = (float) Tools::getValue('purchase_price', 0);

        if ($idProduct <= 0 || $qty <= 0) {
            $this->jsonErr('Parámetros inválidos');
        }

        if (!in_array($type, ['in', 'out', 'inventory'], true)) {
            $this->jsonErr('Tipo de movimiento inválido');
        }

        $before = $this->getCurrentStock($idProduct, $idAttr);

        if ($type === 'inventory') {
            $after = $this->setStock($idProduct, $idAttr, $qty);
            $delta = $after - $before;
        } elseif ($type === 'out') {
            $delta = -abs($qty);
            $after = $this->applyStockDelta($idProduct, $idAttr, $delta);
        } else {
            $delta = abs($qty);
            $after = $this->applyStockDelta($idProduct, $idAttr, $delta);
        }

        $this->logMovement([
            'id_product' => $idProduct,
            'id_product_attribute' => $idAttr,
            'product_name' => $name,
            'reference' => $ref,
            'ean13' => $ean,
            'label' => $label ?: ($type === 'in' ? 'Entrada de stock' : ($type === 'out' ? 'Salida de stock' : 'Ajuste de inventario')),
            'qty_before' => $before,
            'qty_delta' => $delta,
            'qty_after' => $after,
            'purchase_price' => $purchasePrice,
            'movement_type' => $type,
        ]);

        $this->jsonOk([
            'qty_before' => $before,
            'qty_after' => $after,
            'qty_delta' => $delta,
        ]);
    }

    public function initContent()
    {
        parent::initContent();

        $this->renderModuleTemplate('scan.tpl', array_merge($this->getNavVars(), [
            'active_tab' => 'scan',
            'admin_link' => $this->adminLink('AdminSmartStockScan'),
        ]));
    }

    /**
     * Genera candidatos limpios desde lo recibido por el lector.
     *
     * Ejemplos soportados:
     * - 8431234567890
     * - "8431234567890\n"
     * - "]C1018431234567890"
     * - "EAN: 8431234567890"
     * - códigos con espacios o separadores
     */
    private function buildBarcodeCandidates(string $raw): array
    {
        $raw = trim($raw);

        /*
         * Quita caracteres de control invisibles que algunos lectores envían.
         */
        $cleanText = preg_replace('/[\x00-\x1F\x7F]/u', '', $raw);
        $cleanText = trim((string) $cleanText);

        /*
         * Versión solo dígitos para EAN/UPC.
         */
        $digits = preg_replace('/\D+/', '', $cleanText);
        $digits = (string) $digits;

        $candidates = [];

        if ($digits !== '') {
            $candidates[] = $digits;

            /*
             * Muchos lectores GS1 envían prefijos o application identifiers.
             * Probamos las longitudes más habituales desde el final.
             */
            foreach ([14, 13, 12, 8] as $length) {
                if (Tools::strlen($digits) > $length) {
                    $candidates[] = Tools::substr($digits, -$length);
                }
            }

            /*
             * En algunos catálogos se guarda UPC sin cero inicial o EAN con cero inicial.
             */
            if (Tools::strlen($digits) === 13 && Tools::substr($digits, 0, 1) === '0') {
                $candidates[] = Tools::substr($digits, 1);
            }

            if (Tools::strlen($digits) === 12) {
                $candidates[] = '0' . $digits;
            }
        }

        /*
         * También conservar texto alfanumérico por si el lector escanea referencia.
         */
        $alnum = preg_replace('/[^A-Za-z0-9_\-\.]/', '', $cleanText);
        $alnum = trim((string) $alnum);

        if ($alnum !== '') {
            $candidates[] = $alnum;
        }

        $candidates = array_values(array_unique(array_filter($candidates, static function ($value) {
            return Tools::strlen((string) $value) >= 2;
        })));

        return $candidates;
    }
}
