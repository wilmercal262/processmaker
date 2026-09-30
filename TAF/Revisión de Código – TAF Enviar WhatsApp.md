Revisión de Código – Hallazgos Críticos y Altos

Script: TAF/Script/TAF - Enviar WhatsApp.php
Proceso: TAF - Transport Manifest Generator

Hallazgos Críticos

1. Confirmación falsa de envío de mensajes WhatsApp
Severidad: Crítica

El teléfono del pasajero se agrega a la lista de enviados inmediatamente después de la llamada a la API, sin validar que Meta WhatsApp haya aceptado el mensaje:

$telefonosEnviados[] = $telefono;

Además, el script siempre retorna el mismo estado, aunque no se haya enviado ningún mensaje o todas las llamadas hayan fallado:

return [
    "status" => "mensajes enviados",
    "telefonos" => $telefonosEnviados
];

El proceso de negocio puede asumir que los pasajeros fueron notificados cuando en realidad la API devolvió un error, JSON inválido o una respuesta distinta a la esperada. En un manifiesto de transporte esto implica que el flujo continúe como si el aviso se hubiera enviado.

Recomendación:

Solo agregar el teléfono a `$telefonosEnviados` cuando la respuesta de la API cumpla las condiciones de éxito (código HTTP 2xx, JSON válido y presencia de `messages`).

Si falla la configuración o no es posible invocar la API, retornar una respuesta controlada, por ejemplo:

return [
    "status" => "error",
    "telefonos" => []
];

No incluir en esa respuesta credenciales, tokens, URLs internas ni trazas de error.


Hallazgos Altos

2. Manejo incompleto de excepciones en las llamadas a la API de WhatsApp
Severidad: Alta

El cliente HTTP se crea sin timeout ni configuración explícita de TLS:

$client = new Client();

Existe un bloque try/catch, pero en el catch se guarda el mensaje crudo de la excepción:

} catch (\Exception $e) {
    $result = $e->getMessage();
}

Los mensajes de Guzzle suelen incluir la URL invocada (`https://graph.facebook.com/v22.0/{id}/messages`), códigos HTTP y fragmentos del cuerpo de error. Aunque `$result` no se retorna hoy, queda disponible en el script y podría filtrarse si más adelante se asigna al payload del proceso.

Problemas como errores de conexión, timeouts, errores DNS, problemas de certificados SSL o indisponibilidad de Graph API pueden interrumpir el envío o dejar información sensible en variables de proceso.

Recomendación:

Configurar el cliente de forma explícita:

$client = new Client([
    'timeout' => 15,
    'verify' => true,
    'http_errors' => false,
]);

Capturar `GuzzleHttp\Exception\GuzzleException` y continuar o retornar una respuesta controlada, sin persistir `$e->getMessage()`.

Los mensajes mostrados al usuario o al proceso no deberían incluir información sensible, como credenciales, tokens, URLs internas o trazas completas de errores.

3. Falta de validación de las respuestas de la API de WhatsApp
Severidad: Alta

La respuesta se procesa directamente sin validar el código HTTP ni la estructura esperada:

$result = json_decode(
    $response->getBody(),
    true
);

$telefonosEnviados[] = $telefono;

No se verifica:

- Código de respuesta HTTP.
- Resultado de `json_decode()`.
- Existencia del arreglo `messages` en la respuesta de Meta.
- Mensajes de error retornados por Graph API (`error.message`, `error.code`).

Si el servicio devuelve un error, JSON inválido o una estructura diferente a la esperada, el script puede marcar el envío como exitoso y continuar el proceso con información incorrecta.

Recomendación:

Validar como mínimo:

- Código de respuesta HTTP (2xx).
- Resultado de `json_decode()`.
- Existencia y contenido de `$result['messages']`.
- Mensajes de error retornados por la API, sin exponerlos al usuario.

El teléfono solo debe registrarse como enviado cuando la respuesta cumpla esas condiciones. Si no las cumple, el procesamiento de ese pasajero debe detenerse de forma controlada y continuar con el siguiente.

4. URL y versión de Graph API hardcodeadas
Severidad: Alta

La URL de WhatsApp está definida directamente en el código:

$url =
    "https://graph.facebook.com/v22.0/"
    . $idWhatsapp
    . "/messages";

Mantener el host y la versión `v22.0` hardcodeados genera una dependencia directa del script con un endpoint específico. Un cambio de versión de Meta, o una diferencia entre ambientes, obliga a modificar el código fuente.

También dificulta el mantenimiento: DEV, TEST y PROD no pueden apuntar a una URL distinta sin editar el script.

Recomendación:

Mover la URL base a una variable de entorno:

$url = rtrim(getenv('URL_WHATSAPP_API'), '/')
    . '/'
    . $idWhatsapp
    . '/messages';

De esta manera, cada ambiente puede utilizar su propio endpoint y versión de Graph API sin modificar el código fuente del proceso.

5. Falta de validación de variables de entorno y de la estructura de entrada
Severidad: Alta

Las credenciales y plantillas se leen desde el entorno, pero no se valida que existan antes de recorrer los manifiestos:

$idWhatsapp = getenv("ID_IDENTIFICADOR_WHATSAPP");
$token = getenv("TOKEN_IDENTIFICADOR_WHATSAPP");
$templateAvion = getenv("TAF_nombre_template_vuelo");
$templateOtros = getenv("TAF_nombre_template_otros");

Si alguna está vacía, el script igual intenta enviar mensajes con un token o template inválido.

La estructura de entrada se recorre sin comprobar tipos ni claves:

foreach ($data["TAF_manifiestos_generados"] as $manifiesto) {
    foreach ($manifiesto["transportes_asignados"] as $transporte) {
        $transporteRow = $transporte['transporte'];
        foreach ($transporte["pasajeros"] as $pasajero) {
            $telefono = str_replace("+", "", $pasajero["telefono"]);

En PHP 8+, una clave ausente o un valor que no sea arreglo genera un error no controlado e interrumpe todo el envío, incluso para pasajeros que sí tenían datos válidos.

Recomendación:

Validar al inicio que `$idWhatsapp`, `$token` y los nombres de template no estén vacíos. Si falta configuración, retornar la respuesta controlada de error.

Validar que `TAF_manifiestos_generados`, `transportes_asignados` y `pasajeros` sean arreglos antes del `foreach`. Omitir registros incompletos (sin teléfono o sin estructura esperada) y continuar con el resto, en lugar de abortar el script.
