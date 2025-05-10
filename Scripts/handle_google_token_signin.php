<?php
// Scripts/handle_google_token_signin.php

/**
 * Script backend que recibe un ID Token de Google (enviado por JS vía POST),
 * lo verifica, y busca/crea/loguea al usuario localmente.
 * Responde siempre con JSON.
 */
require_once __DIR__ . '/../Config/config.php';
ini_set('display_errors', 1); // INTENTAR mostrar errores
error_reporting(E_ALL);      // Reportar TODO

register_shutdown_function(function () {
    $error = error_get_last();
    // Comprobar si hubo un error fatal que detuvo el script
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
         // Intentar limpiar cualquier buffer que pudiera estar activo
         if (ob_get_level() > 0) { ob_end_clean(); }
         // Intentar enviar una respuesta JSON con el detalle del error
         // (puede fallar si las cabeceras ya se enviaron por el error fatal)
         if (!headers_sent()) {
              header('Content-Type: application/json');
              http_response_code(500); // Indicar error de servidor
         }
         // ¡No mostrar detalles de error en producción! Solo para depurar.
         echo json_encode([
             'success' => false,
             'message' => 'FATAL PHP ERROR OCCURRED',
             'error_details' => [
                 'type'    => $error['type'],
                 'message' => $error['message'],
                 'file'    => $error['file'],
                 'line'    => $error['line']
             ]
         ]);
         // Si el JSON falla, puede que se vea algo con print_r
         print_r($error);
    }
});

// Iniciar buffer DESPUÉS de registrar la función de apagado
if (ob_get_level() == 0) { ob_start(); }

// 2. JSON Response Function (ASEGÚRATE QUE ESTO ESTÉ PRESENTE)
/**
 * Limpia buffers, establece cabeceras JSON, imprime JSON y termina script.
 * @param array $data Datos a codificar en JSON.
 * @param int $statusCode Código de estado HTTP (ej. 200, 400, 403, 500).
 */
function sendJsonResponse(array $data, int $statusCode = 200) { // Sin :void por compatibilidad
    // Limpiar cualquier buffer de salida accidental
    if (ob_get_level() > 0) {
       ob_end_clean();
    }
    // Establecer cabeceras solo si no se han enviado ya
    if (!headers_sent()) {
        header('Content-Type: application/json');
        http_response_code($statusCode);
    }
    // Imprimir JSON y terminar
    echo json_encode($data);
    exit;
}
// --- FIN FUNCIÓN sendJsonResponse ---


// --- Configuración Inicial y Respuesta JSON ---
@ini_set('display_errors', 0); error_reporting(E_ALL);
if (ob_get_level() == 0) { ob_start(); }
header('Content-Type: application/json');

function sendJsonResponseAndExit(array $data, int $statusCode = 200): void {
    if (ob_get_level() > 0) { ob_end_clean(); }
    if (!headers_sent()) { http_response_code($statusCode); }
    echo json_encode($data); exit;
}

$response = ['success' => false, 'message' => 'Error desconocido.'];
$httpStatusCode = 500;

// --- Iniciar Sesión y Cargar Dependencias ---
try {
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) { session_start(); } else { throw new Exception('Error Interno (Session).', 500); }
    }
    // Cargar dependencias (¡Verifica Case!)
    require_once __DIR__ . '/../Config/config.php';
    require_once __DIR__ . '/../Includes/autoload.php';
    require_once __DIR__ . '/../PDO/Conexion.php';
    require_once __DIR__ . '/../Includes/google-api-client/vendor/autoload.php';

    // --- Validar Método y CSRF Token ---
    if ($_SERVER["REQUEST_METHOD"] !== "POST") { throw new Exception('Método no permitido.', 405); }
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        throw new Exception('Error de validación de seguridad.', 403);
    }
    // Opcional: Invalidar CSRF token usado
    // unset($_SESSION['csrf_token']);

    // --- Obtener ID Token ---
    $id_token = $_POST['id_token'] ?? null;
    if (empty($id_token)) { throw new Exception('No se recibió el token de identidad.', 400); }

    // --- Verificar ID Token con la Librería de Google ---
    $client = new Google_Client(['client_id' => GOOGLE_CLIENT_ID]); // Solo necesita el Client ID para verificar
    $payload = $client->verifyIdToken($id_token);

    if ($payload) {
        // --- Token VÁLIDO ---
        $google_id = $payload['sub'];
        $email = $payload['email'] ?? null;
        $email_verified = $payload['email_verified'] ?? false;
        $name = $payload['name'] ?? '';

        if (empty($email) || !$email_verified) { throw new Exception('Email de Google no válido o no verificado.'); }

        // --- Lógica de Usuario Local (Igual que en handle_google_callback.php) ---
        $pdo = Conexion::obtenerConexion();
        if(!$pdo) { throw new Exception("Error BD.", 500); }
        $userPDO = new UsuarioPDO();

        $user = $userPDO->findByGoogleId($google_id); // Buscar por Google ID
        if (!$user) {
            $user = $userPDO->findByEmail($email); // Buscar por Email
            if ($user) { // Encontrado por email -> Vincular
                if (!$userPDO->updateGoogleId($user['id'], $google_id)) {
                     error_log("ADVERTENCIA Callback Google Token: No se pudo vincular Google ID {$google_id} a User ID {$user['id']}.");
                }
            } else { // No encontrado -> Crear nuevo usuario
                $username = explode('@', $email)[0] . '_' . substr($google_id, 0, 4);
                if ($userPDO->findByUsername($username)) { $username .= rand(100, 999); }
                $placeholderPassword = password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT);

                $newUserId = $userPDO->create($username, $email, $placeholderPassword, null, null, null, $google_id); // Incluir google_id
                if ($newUserId) {
                    $user = $userPDO->findById($newUserId); // Obtener datos del nuevo usuario
                    if (!$user) { throw new Exception("Error al crear/recuperar cuenta.", 500); }
                } else { throw new Exception("Error al crear cuenta.", 500); }
            }
        }

        // --- Verificar estado activo y establecer sesión ---
        if ($user['active'] != 1) { throw new Exception('Tu cuenta está inactiva.', 403); }
        if ($user['id_eCon'] != 1) { throw new Exception('Tu cuenta no está verificada (email).', 403); } // Añadido por si acaso

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['google_id'] = $google_id;

        // Determinar URL de redirección
        $redirectUrl = ($user['perfil_completo'] == 0) ? PROFILE_SETUP_URL : DASHBOARD_URL;

        // Respuesta JSON de éxito
        $response = ['success' => true, 'redirectUrl' => $redirectUrl];
        $httpStatusCode = 200;

    } else {
        // Token ID inválido
        throw new Exception('Verificación de Google falló (token inválido).', 401);
    }

} catch (Throwable $e) { // Capturar todo (Errores y Excepciones)
    error_log("Error en handle_google_token_signin: (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = $e->getMessage();
    $code = $e->getCode();
    $httpStatusCode = (is_int($code) && $code >= 400 && $code <= 599) ? $code : 500;
}

// --- Enviar Respuesta JSON ---
sendJsonResponse($response, $httpStatusCode);

?>