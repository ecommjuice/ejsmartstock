<?php
if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__) . '/AdminSmartStockBaseController.php';

class AdminSmartStockScanController extends AdminSmartStockBaseController
{
    public function __construct()
    {
        $this->meta_title = 'EJ Smart stock – Escáner';

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

        /*
         * 3) No existe: se devuelve el código limpio para ofrecer el alta del producto.
         */
        $this->jsonOk([
            'success' => false,
            'not_found' => true,
            'error' => 'Producto no encontrado: ' . Tools::safeOutput($raw),
            'ean' => $this->bestEanCandidate($candidates),
        ]);
    }

    /**
     * Alta rápida de un producto que no existe al escanearlo.
     *
     * Crea la ficha con nombre, EAN, precio con IVA, impuesto, categoría, marca,
     * visibilidad, stock inicial y foto opcional, y registra el movimiento de entrada.
     */
    public function ajaxProcessCreateProduct()
    {
        if (!$this->access('add')) {
            $this->jsonErr('No tienes permiso para crear productos.');
        }

        $name = trim((string) Tools::getValue('name', ''));
        $ean = preg_replace('/\D+/', '', (string) Tools::getValue('ean13', ''));
        $reference = trim((string) Tools::getValue('reference', ''));
        $pvp = (float) str_replace(',', '.', (string) Tools::getValue('price_ttc', '0'));
        $wholesale = (float) str_replace(',', '.', (string) Tools::getValue('purchase_price', '0'));
        $idTaxGroup = (int) Tools::getValue('id_tax_rules_group', 0);
        $idCategory = (int) Tools::getValue('id_category', 0);
        $idManufacturer = (int) Tools::getValue('id_manufacturer', 0);
        $qty = max(0, (int) Tools::getValue('qty', 0));
        $online = (int) Tools::getValue('online', 1) === 1;

        if (Tools::strlen($name) < 2) {
            $this->jsonErr('Escribe el nombre del producto.');
        }
        if (Tools::strlen($name) > 128 || !Validate::isCatalogName($name)) {
            $this->jsonErr('El nombre tiene caracteres no permitidos o es demasiado largo (máx. 128).');
        }
        if ($ean !== '' && !Validate::isEan13($ean)) {
            $this->jsonErr('El EAN no es válido (máx. 13 dígitos).');
        }
        if ($reference !== '' && !Validate::isReference($reference)) {
            $this->jsonErr('La referencia tiene caracteres no permitidos.');
        }
        if ($pvp < 0 || $wholesale < 0) {
            $this->jsonErr('Los precios no pueden ser negativos.');
        }

        $category = new Category($idCategory);
        if (!Validate::isLoadedObject($category)) {
            $this->jsonErr('Elige una categoría.');
        }
        if ($idTaxGroup > 0 && !Validate::isLoadedObject(new TaxRulesGroup($idTaxGroup))) {
            $this->jsonErr('El tipo de IVA no existe.');
        }
        if ($idManufacturer > 0 && !Validate::isLoadedObject(new Manufacturer($idManufacturer))) {
            $idManufacturer = 0;
        }

        /*
         * Evita duplicados: también mira productos desactivados, que el escáner no encuentra.
         */
        if ($ean !== '') {
            $dup = Db::getInstance()->getRow(
                'SELECT p.id_product, pl.name FROM `' . _DB_PREFIX_ . 'product` p
                LEFT JOIN `' . _DB_PREFIX_ . 'product_lang` pl ON pl.id_product = p.id_product
                    AND pl.id_lang = ' . (int) $this->context->language->id . '
                    AND pl.id_shop = ' . (int) $this->getContextShopId() . '
                WHERE p.ean13 = \'' . pSQL($ean) . '\''
            );
            if ($dup) {
                $this->jsonErr('Ya existe un producto con ese EAN (#' . (int) $dup['id_product'] . ' ' . $dup['name'] . '). Puede estar desactivado: revísalo en Catálogo.');
            }
        }

        $rate = $idTaxGroup > 0 ? $this->getTaxRate($idTaxGroup) : 0.0;
        $priceNet = $rate > 0 ? $pvp / (1 + $rate / 100) : $pvp;

        $product = new Product();
        $langs = Language::getIDs(false);
        foreach ($langs as $idLang) {
            $product->name[$idLang] = $name;
            $product->link_rewrite[$idLang] = Tools::str2url($name) ?: 'producto';
        }
        $product->ean13 = $ean;
        $product->reference = $reference;
        $product->price = Tools::ps_round($priceNet, 6);
        $product->wholesale_price = Tools::ps_round($wholesale, 6);
        $product->id_tax_rules_group = $idTaxGroup;
        $product->id_category_default = (int) $category->id;
        $product->id_manufacturer = $idManufacturer;
        $product->active = 1;
        $product->visibility = $online ? 'both' : 'none';
        $product->available_for_order = 1;
        $product->show_price = 1;

        if (!$product->add()) {
            $this->jsonErr('No se pudo crear el producto.');
        }
        $product->addToCategories([(int) $category->id]);

        $after = $this->setStock((int) $product->id, 0, $qty);

        $photo = '';
        if (!empty($_FILES['photo']['tmp_name']) && is_uploaded_file($_FILES['photo']['tmp_name'])) {
            $photo = $this->attachPhoto((int) $product->id, $_FILES['photo']) ? 'ok' : 'error';
        }

        $this->logMovement([
            'id_product' => (int) $product->id,
            'id_product_attribute' => 0,
            'product_name' => $name,
            'reference' => $reference,
            'ean13' => $ean,
            'label' => 'Alta de producto',
            'qty_before' => 0,
            'qty_delta' => $after,
            'qty_after' => $after,
            'purchase_price' => $wholesale,
            'movement_type' => 'in',
        ]);

        $this->jsonOk([
            'product' => [
                'id_product' => (int) $product->id,
                'id_product_attribute' => 0,
                'name' => $name,
                'ean13' => $ean,
                'reference' => $reference,
                'qty_stock' => $after,
            ],
            'photo' => $photo,
            'edit_link' => $this->context->link->getAdminLink('AdminProducts', true, ['id_product' => (int) $product->id, 'updateproduct' => 1]),
        ]);
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
            'can_create' => (bool) $this->access('add'),
            'create_form' => $this->getCreateFormData(),
        ]));
    }

    /**
     * Datos para el formulario de alta: categorías en árbol, tipos de IVA y marcas.
     */
    private function getCreateFormData(): array
    {
        $idLang = (int) $this->context->language->id;
        $idShop = (int) $this->getContextShopId();
        $p = _DB_PREFIX_;
        $root = (int) Configuration::get('PS_ROOT_CATEGORY');

        $cats = Db::getInstance()->executeS(
            "SELECT c.id_category, c.level_depth, cl.name
            FROM `{$p}category` c
            INNER JOIN `{$p}category_shop` cs ON cs.id_category = c.id_category AND cs.id_shop = {$idShop}
            INNER JOIN `{$p}category_lang` cl ON cl.id_category = c.id_category AND cl.id_lang = {$idLang} AND cl.id_shop = {$idShop}
            WHERE c.active = 1 AND c.id_category <> {$root}
            ORDER BY c.nleft ASC"
        ) ?: [];
        $minDepth = $cats ? min(array_map(static function ($c) { return (int) $c['level_depth']; }, $cats)) : 0;
        $categories = [];
        foreach ($cats as $c) {
            $categories[] = [
                'id' => (int) $c['id_category'],
                'label' => str_repeat('— ', max(0, (int) $c['level_depth'] - $minDepth)) . $c['name'],
            ];
        }

        $taxes = [];
        $defaultTax = 0;
        foreach (TaxRulesGroup::getTaxRulesGroups(true) as $g) {
            $rate = $this->getTaxRate((int) $g['id_tax_rules_group']);
            $taxes[] = ['id' => (int) $g['id_tax_rules_group'], 'label' => $g['name'], 'rate' => $rate];
            if (!$defaultTax && abs($rate - 21) < 0.01) {
                $defaultTax = (int) $g['id_tax_rules_group'];
            }
        }

        $brands = Db::getInstance()->executeS(
            "SELECT id_manufacturer AS id, name AS label FROM `{$p}manufacturer` WHERE active = 1 ORDER BY name ASC"
        ) ?: [];

        return [
            'categories' => $categories,
            'taxes' => $taxes,
            'brands' => $brands,
            'default_category' => (int) Configuration::get('PS_HOME_CATEGORY'),
            'default_tax' => $defaultTax,
        ];
    }

    /**
     * Tipo de IVA (%) de un grupo de impuestos para el país por defecto de la tienda.
     */
    private function getTaxRate(int $idTaxRulesGroup): float
    {
        $address = new Address();
        $address->id_country = (int) Configuration::get('PS_COUNTRY_DEFAULT');
        $manager = TaxManagerFactory::getManager($address, $idTaxRulesGroup);

        return (float) $manager->getTaxCalculator()->getTotalRate();
    }

    /**
     * Prefiere un candidato de 13, 8 o 12 dígitos para rellenar el EAN del alta.
     */
    private function bestEanCandidate(array $candidates): string
    {
        foreach ([13, 8, 12, 14] as $len) {
            foreach ($candidates as $c) {
                if (ctype_digit((string) $c) && Tools::strlen($c) === $len) {
                    return $len === 14 ? Tools::substr($c, -13) : $c;
                }
            }
        }

        return '';
    }

    /**
     * Guarda la foto subida (cámara del móvil o archivo) como portada del producto.
     */
    private function attachPhoto(int $idProduct, array $file): bool
    {
        if ($file['error'] !== UPLOAD_ERR_OK || $file['size'] > 15 * 1024 * 1024) {
            return false;
        }
        if (!ImageManager::isRealImage($file['tmp_name'], $file['type'] ?? null)) {
            return false;
        }

        $image = new Image();
        $image->id_product = $idProduct;
        $image->position = Image::getHighestPosition($idProduct) + 1;
        $image->cover = true;
        if (!$image->add()) {
            return false;
        }

        $path = $image->getPathForCreation();
        if (!ImageManager::resize($file['tmp_name'], $path . '.jpg')) {
            $image->delete();
            return false;
        }
        foreach (ImageType::getImagesTypes('products') as $type) {
            ImageManager::resize($file['tmp_name'], $path . '-' . stripslashes($type['name']) . '.jpg', (int) $type['width'], (int) $type['height']);
        }
        Hook::exec('actionWatermark', ['id_image' => $image->id, 'id_product' => $idProduct]);

        return true;
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
