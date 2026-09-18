<?php

/*
 * Welcome to ProcessMaker 4 Script Editor
 * Optimized for maximum performance.
 */

use GuzzleHttp\Client;

// Paso 1: Obtener informacion del usuario
$apiInstance = $api->users();
$userId = $data['_request']['user_id'] ?? $_user['id'] ?? null;
$user = $apiInstance->getUserById($userId);
$jdeCode = $user['username'] ?? null;

// Paso 2: Obtener token de los APIs MSC
$client = new Client([
    'timeout' => 15,
    'verify' => false,
    'http_errors' => false,
]);

$loginResponse = $client->request(
    'POST',
    getenv('URL_CONNECTOR_LOGIN'),
    [
        'headers' => [
            'Content-Type' => 'application/json',
            'Accept'       => '*/*',
        ],
        'json' => [
            'usuario'  => getenv('MSC_USER_API'),
            'password' => getenv('MSC_PASS_API'),
        ],
        'verify' => true
    ]
);

$loginData = json_decode((string) $loginResponse->getBody(), true);
$token = $loginData['token'] ?? null;

// Paso 3: Validar si el 'jde code' y el 'token' estan vacios.
if (!$jdeCode || !$token) {
    return [
        'titleHeader' => 'Consulta de Pagos',
        'titleHeaderOther' => 'HISTORICO DE FACTURAS',
        'versionPM' => 'v0.0.0',
        'consulta_pagos_grilla' => [],
        'CPP_proveedor_nombre'  => $user['fullname'] ?? 'No especificado',
        'proveedor_codigo'      => 'No especificado',
        'fecha_consulta'        => date('m/d/Y')
    ];
}

// Paso 4: Llamada al Api - MSC, mediante el codigo JDE, para obtener los pagos de los proveedores. 
$url = getenv('MSC_PAGO_PROVEEDORES_API') . urlencode($jdeCode);
$paymentResponse = $client->get($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
]);

$resp = json_decode($paymentResponse->getBody()->getContents(), true);
$consulta_pagos_grilla = $resp['objeto']['pagos'] ?? [];

// Paso 5: Obtener informacion del 'Collection' Notificaciones
$collection_notificacion_id = 68;
$config_notificacion_rows = $api->collections()
    ->getRecords($collection_notificacion_id)['data'][0]['data'];

// Paso 6: Devolver la informacion
return [
    'titleHeader'           => 'Consulta Pagos',
    'titleHeaderOther'      => 'HISTORICO DE FACTURAS',
    'versionPM'             => 'v0.0.0',
    'server_url'            => getenv('APP_URL'),
    'consulta_pagos_grilla' => $consulta_pagos_grilla,
    'CPP_proveedor_nombre'  => $user['fullname'] ?? 'No especificado',
    'CPP_codigo_jde'        => $jdeCode,
    'fecha_consulta'        => date('m/d/Y'),
    'reporte_nombre'        => 'reporte_' . date('d-m-Y'),
    'data_notificacion'     => $config_notificacion_rows['cp_notificacion'],
    'token'                 => $token
];
