<?php

/************************************************************
 * Proyecto   : TAF - Transport Manifest Generator
 * Script     : Enviar Mensajes WhatsApp
 * Descripción:
 * Envía mensajes WhatsApp utilizando templates
 * configurados en Meta WhatsApp API.
 *
 * Variables de Entorno:
 * - ID_IDENTIFICADOR_WHATSAPP
 * - TOKEN_IDENTIFICADOR_WHATSAPP
 * - TAF_nombre_template_vuelo
 * - TAF_nombre_template_otros
 *
 * Entradas:
 * - $data['TAF_fecha_buscar']
 * - $data['TAF_tipo_transporte']
 * - $data['TAF_manifiestos_generados']
 *
 * Salidas:
 * - status
 * - telefonos
 *
 * Autor : Manuel Monroy
 * Fecha : 2026-05
 ************************************************************/

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

$respuestaControlada = [
    "status" => "error",
    "telefonos" => []
];

/**
 * CLIENTE HTTP
 */
$client = new Client([
    'timeout' => 15,
    'verify' => true,
    'http_errors' => false,
]);

/**
 * VARIABLES DE ENTORNO
 */
$idWhatsapp = getenv("ID_IDENTIFICADOR_WHATSAPP");
$token = getenv("TOKEN_IDENTIFICADOR_WHATSAPP");
$templateAvion = getenv("TAF_nombre_template_vuelo");
$templateOtros = getenv("TAF_nombre_template_otros");

if (
    empty($idWhatsapp) ||
    empty($token) ||
    empty($templateAvion) ||
    empty($templateOtros)
) {
    return $respuestaControlada;
}

/**
 * URL WHATSAPP API
 */
$url =
    "https://graph.facebook.com/v22.0/"
    . $idWhatsapp
    . "/messages";

/**
 * DATOS DE ENTRADA
 */
$fecha = $data["TAF_fecha_buscar"] ?? null;
$tipoTransporte = $data["TAF_tipo_transporte"] ?? '';
$manifiestos = $data["TAF_manifiestos_generados"] ?? [];

if (!is_array($manifiestos)) {
    return $respuestaControlada;
}

/**
 * LISTA DE TELÉFONOS ENVIADOS
 */
$telefonosEnviados = [];

/**
 * RECORRER MANIFIESTOS
 */
foreach ($manifiestos as $manifiesto) {

    $transportes = is_array($manifiesto) ? ($manifiesto["transportes_asignados"] ?? []) : [];
    if (!is_array($transportes)) {
        continue;
    }

    /**
     * RECORRER TRANSPORTES
     */
    foreach ($transportes as $transporte) {

        if (!is_array($transporte)) {
            continue;
        }

        $transporteRow = $transporte['transporte'] ?? [];
        $transporteRuta =
            $tipoTransporte
            . ' - '
            . ($transporteRow['nombre'] ?? '')
            . ' '
            . ($transporteRow['hora'] ?? '');

        $pasajeros = $transporte["pasajeros"] ?? [];
        if (!is_array($pasajeros)) {
            continue;
        }

        /**
         * RECORRER PASAJEROS
         */
        foreach ($pasajeros as $pasajero) {

            if (!is_array($pasajero)) {
                continue;
            }

            $telefono = str_replace(
                "+",
                "",
                (string)($pasajero["telefono"] ?? '')
            );

            if ($telefono === '') {
                continue;
            }

            /**
             * MENSAJE WHATSAPP
             */
            $mensaje = is_array($transporte["mensaje_whatsapp"] ?? null)
                ? $transporte["mensaje_whatsapp"]
                : [];

            /**
             * TEMPLATE AVIÓN
             */
            if ($tipoTransporte == "Avión") {

                $templateName = $templateAvion;

                $parameters = [
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var1"])
                            ? $mensaje["var1"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var2"])
                            ? $mensaje["var2"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var3"])
                            ? $mensaje["var3"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var4"])
                            ? $mensaje["var4"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var5"])
                            ? $mensaje["var5"]
                            : " "
                    ]
                ];

            } else {

                /**
                 * TEMPLATE OTROS
                 */
                $templateName = $templateOtros;

                $parameters = [
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var1"])
                            ? $mensaje["var1"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var2"])
                            ? $mensaje["var2"]
                            : " "
                    ],
                    [
                        "type" => "text",
                        "text" =>
                            !empty($mensaje["var7"])
                            ? $mensaje["var7"]
                            : " "
                    ]
                ];
            }

            /**
             * PAYLOAD WHATSAPP
             */
            $payload = [
                "messaging_product" => "whatsapp",
                "to" => $telefono,
                "type" => "template",
                "template" => [
                    "name" => $templateName,
                    "language" => [
                        "code" => "es"
                    ],
                    "components" => [
                        [
                            "type" => "body",
                            "parameters" => $parameters
                        ]
                    ]
                ]
            ];

            try {

                /**
                 * ENVIAR MENSAJE
                 */
                $response = $client->post(
                    $url,
                    [
                        'headers' => [
                            'Authorization' =>
                                'Bearer ' . $token,

                            'Content-Type' =>
                                'application/json'
                        ],
                        'json' => $payload
                    ]
                );
            } catch (GuzzleException $e) {
                continue;
            }

            $status = $response->getStatusCode();
            $result = json_decode((string) $response->getBody(), true);
            $mensajesApi = is_array($result) ? ($result['messages'] ?? null) : null;

            if (
                $status < 200 ||
                $status >= 300 ||
                json_last_error() !== JSON_ERROR_NONE ||
                !is_array($mensajesApi) ||
                empty($mensajesApi)
            ) {
                continue;
            }

            /**
             * GUARDAR TELÉFONO ENVIADO
             */
            $telefonosEnviados[] = $telefono;
        }
    }
}

/**
 * RETORNO FINAL
 * El token de WhatsApp no se expone al cliente.
 */
return [
    "status" => "mensajes enviados",
    "telefonos" => $telefonosEnviados
];
