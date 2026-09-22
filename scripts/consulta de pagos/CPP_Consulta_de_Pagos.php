<?php 
/*  
 *  Welcome to ProcessMaker 4 Script Editor 
 *  Optimized for maximum performance and PHP 8+ stability.
 */
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

// 1. SOLICITUD Y DECODIFICACIÓN DE LA API
$jdeCode = (string)($data['jde_code'] ?? '');
$respuestaControlada = [];

if ($jdeCode === '') {
    return $respuestaControlada;
}

// 2. Obtener token de los APIs MSC
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
$datos = is_array($resp) ? ($resp['objeto']['pagos'] ?? null) : null;

if (
    $paymentStatus < 200 ||
    $paymentStatus >= 300 ||
    json_last_error() !== JSON_ERROR_NONE ||
    !is_array($datos)
) {
    return $respuestaControlada;
}

// 3. VALIDACIÓN Y BÚSQUEDA RÁPIDAS
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
