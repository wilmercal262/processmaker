<?php 
/*  
 *  Welcome to ProcessMaker 4 Script Editor 
 *  Optimized for maximum performance and PHP 8+ stability.
 */
use GuzzleHttp\Client;

// 1. SOLICITUD Y DECODIFICACIÓN DE LA API
$jdeCode = (string)($data['jde_code'] ?? '');
$token = $data['token'] ?? '';
$url = getenv('MSC_PAGO_PROVEEDORES_API') . urlencode($jdeCode);

$client = new Client([
    'timeout' => 15,
    'http_errors' => false,
    'verify' => false,
]);
$paymentResponse = $client->get($url, [
    'headers' => [
        'Authorization' => 'Bearer ' . $token,
        'Accept' => 'application/json',
    ],
]);

if ($paymentResponse->getStatusCode() !== 200) {
    return [];
}

$resp = json_decode($paymentResponse->getBody()->getContents(), true);
$datos = $resp['objeto']['pagos'] ?? [];

// 2. VALIDACIÓN Y BÚSQUEDA RÁPIDAS
$columnaFiltro = $data['columnaFiltro'] ?? '';
$textoBusqueda = trim((string)($data['textoBusqueda'] ?? ''));

if ($textoBusqueda === '') {
    return $datos;
}

$resultado = [];
$isGlobalSearch = ($columnaFiltro === '*');

foreach ($datos as $fila) {
    if ($isGlobalSearch) {
        foreach ($fila as $valor) {
            if (is_scalar($valor) && stripos((string)$valor, $textoBusqueda) !== false) {
                $resultado[] = $fila;
                break;
            }
        }
    } else {
        if (isset($fila[$columnaFiltro]) && is_scalar($fila[$columnaFiltro]) && stripos((string)$fila[$columnaFiltro], $textoBusqueda) !== false) {
            $resultado[] = $fila;
        }
    }
}

return $resultado;
