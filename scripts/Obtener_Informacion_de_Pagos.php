<?php

/*
 * Welcome to ProcessMaker 4 Script Editor
 * Optimized for maximum performance.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// Paso 1: Obtener informacion del usuario
$apiInstance = $api->users();
$userId = $data['_request']['user_id'] ?? $_user['id'] ?? null;
$user = $apiInstance->getUserById($userId);
$jdeCode = $user['username'] ?? null;

$respuestaControlada = [
    'titleHeader' => 'Consulta de Pagos',
    'titleHeaderOther' => 'HISTORICO DE FACTURAS',
    'versionPM' => 'v0.0.0',
    'consulta_pagos_grilla' => [],
    'CPP_proveedor_nombre'  => $user['fullname'] ?? 'No especificado',
    'CPP_codigo_jde'        => $jdeCode ?? 'No especificado',
    'fecha_consulta'        => date('m/d/Y'),
];

if (!$jdeCode) {
    return $respuestaControlada;
}

// Paso 2: Obtener token de los APIs MSC
$client = new Client([
    'timeout' => 15,
    'verify' => true,
    'http_errors' => false,
]);

try {
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
        ]
    );
} catch (GuzzleException $e) {
    return $respuestaControlada;
}

$loginStatus = $loginResponse->getStatusCode();
$loginData = json_decode((string) $loginResponse->getBody(), true);
$token = is_array($loginData) ? ($loginData['token'] ?? null) : null;

if (
    $loginStatus < 200 ||
    $loginStatus >= 300 ||
    json_last_error() !== JSON_ERROR_NONE ||
    empty($token)
) {
    return $respuestaControlada;
}

// Paso 3: Llamada al Api - MSC, mediante el codigo JDE, para obtener los pagos de los proveedores.
try {
    $url = getenv('MSC_PAGO_PROVEEDORES_API') . urlencode($jdeCode);
    $paymentResponse = $client->get($url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ],
    ]);
} catch (GuzzleException $e) {
    return $respuestaControlada;
}

$paymentStatus = $paymentResponse->getStatusCode();
$resp = json_decode($paymentResponse->getBody()->getContents(), true);
$consulta_pagos_grilla = is_array($resp) ? ($resp['objeto']['pagos'] ?? null) : null;

if (
    $paymentStatus < 200 ||
    $paymentStatus >= 300 ||
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($consulta_pagos_grilla)
) {
    return $respuestaControlada;
}

// Paso 4: Obtener informacion del 'Collection' Notificaciones
$collection_notificacion_id = getenv('ID_COLLECTION_NOTIFICACION');
$data_notificacion = null;

try {
    $collectionRecords = $api->collections()->getRecords($collection_notificacion_id);
    $data_notificacion = $collectionRecords['data'][0]['data']['cp_notificacion'] ?? null;
} catch (Throwable $e) {
    $data_notificacion = null;
}

// Paso 5: Devolver la informacion (el token no se expone al cliente)
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
    'data_notificacion'     => $data_notificacion,
];
