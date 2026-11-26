<?php
/**
 * 1.2.0: alta rápida de productos desde el escáner cuando el código no existe.
 * Sin cambios de base de datos.
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_2_0($module)
{
    return true;
}
