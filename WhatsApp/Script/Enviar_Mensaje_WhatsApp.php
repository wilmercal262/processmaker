<?php
/*
 *  Welcome to ProcessMaker 4 Script Editor
 *  Envia un mensaje de WhatsApp Business (Cloud API de Meta).
 *
 *  Variables de entorno:
 *    WHATSAPP_TOKEN            Token permanente de la app de Meta
 *    WHATSAPP_PHONE_NUMBER_ID  ID del numero de telefono de WhatsApp Business
 *    WHATSAPP_API_VERSION      Opcional. Por defecto v22.0
 *
 *  Datos del caso ($data):
 *    whatsapp_to               Numero destino en formato internacional, sin + (ej. 5917XXXXXXX)
 *    whatsapp_message          Texto libre. Solo funciona dentro de la ventana de 24 horas.
 *    whatsapp_template         Nombre de la plantilla aprobada (notificaciones iniciadas por el proceso)
 *    whatsapp_template_lang    Codigo de idioma de la plantilla. Por defecto es
 *    whatsapp_template_params  Arreglo de textos para las variables {{1}}, {{2}}, ... del cuerpo
 *
 *  Si whatsapp_template tiene valor, se envia plantilla. Si no, se envia texto.
 *  El arreglo devuelto se fusiona con los datos del caso.
 */

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

$respuesta = [
    'whatsapp_enviado'    => false,
    'whatsapp_message_id' => '',
    'whatsapp_error'      => '',
];

$to = preg_replace('/\D+/', '', (string) ($data['whatsapp_to'] ?? ''));
$mensaje = trim((string) ($data['whatsapp_message'] ?? ''));
$plantilla = trim((string) ($data['whatsapp_template'] ?? ''));
$idioma = trim((string) ($data['whatsapp_template_lang'] ?? 'es'));
$parametros = $data['whatsapp_template_params'] ?? [];

$token = getenv('WHATSAPP_TOKEN');
$phoneNumberId = getenv('WHATSAPP_PHONE_NUMBER_ID');
$version = getenv('WHATSAPP_API_VERSION') ?: 'v22.0';

if ($to === '') {
    $respuesta['whatsapp_error'] = 'Falta el numero destino (whatsapp_to).';
    return $respuesta;
}

if ($token === false || $token === '' || $phoneNumberId === false || $phoneNumberId === '') {
    $respuesta['whatsapp_error'] = 'Faltan WHATSAPP_TOKEN o WHATSAPP_PHONE_NUMBER_ID.';
    return $respuesta;
}

if ($plantilla !== '') {
    $bodyParams = [];
    if (!is_array($parametros)) {
        $parametros = [$parametros];
    }
    foreach ($parametros as $valor) {
        $bodyParams[] = [
            'type' => 'text',
            'text' => (string) $valor,
        ];
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'template',
        'template'          => [
            'name'     => $plantilla,
            'language' => ['code' => $idioma !== '' ? $idioma : 'es'],
        ],
    ];

    if ($bodyParams !== []) {
        $payload['template']['components'] = [[
            'type'       => 'body',
            'parameters' => $bodyParams,
        ]];
    }
} else {
    if ($mensaje === '') {
        $respuesta['whatsapp_error'] = 'Indica whatsapp_message o whatsapp_template.';
        return $respuesta;
    }

    $payload = [
        'messaging_product' => 'whatsapp',
        'recipient_type'    => 'individual',
        'to'                => $to,
        'type'              => 'text',
        'text'              => [
            'preview_url' => false,
            'body'        => $mensaje,
        ],
    ];
}

$client = new Client([
    'timeout'     => 15,
    'verify'      => true,
    'http_errors' => false,
]);

$url = 'https://graph.facebook.com/' . rawurlencode($version) . '/' . rawurlencode($phoneNumberId) . '/messages';

try {
    $response = $client->request('POST', $url, [
        'headers' => [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ],
        'json' => $payload,
    ]);
} catch (GuzzleException $e) {
    $respuesta['whatsapp_error'] = 'No se pudo contactar la API de WhatsApp.';
    return $respuesta;
}

$status = $response->getStatusCode();
$body = json_decode((string) $response->getBody(), true);

if ($status < 200 || $status >= 300 || !is_array($body)) {
    $detalle = is_array($body) ? ($body['error']['message'] ?? '') : '';
    $respuesta['whatsapp_error'] = $detalle !== ''
        ? $detalle
        : 'WhatsApp respondio con estado ' . $status . '.';
    return $respuesta;
}

$respuesta['whatsapp_enviado'] = true;
$respuesta['whatsapp_message_id'] = (string) ($body['messages'][0]['id'] ?? '');

return $respuesta;
