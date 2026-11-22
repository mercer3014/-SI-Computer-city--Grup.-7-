<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vigencia de las cotizaciones
    |--------------------------------------------------------------------------
    |
    | Días de validez de una cotización web (CU19) contados desde su emisión.
    | Los precios cotizados se respetan hasta esa fecha; no reserva stock.
    |
    */

    'dias_validez_cotizacion' => (int) env('TIENDA_DIAS_VALIDEZ_COTIZACION', 7),

    /*
    |--------------------------------------------------------------------------
    | Código de país para WhatsApp
    |--------------------------------------------------------------------------
    |
    | Se antepone al número de la tienda cuando no lo trae (Bolivia = 591).
    |
    */

    'codigo_pais' => env('TIENDA_CODIGO_PAIS', '591'),

];
