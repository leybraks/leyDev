<?php

// Establecer tipo de contenido como JSON
header('Content-Type: application/json');

// Inicialización de sesión si no está activa
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); }
    else {
         echo json_encode(['success' => false, 'message' => 'Error interno del servidor (Headers Sent).']);
         exit;
    }
}

// Cargar archivos necesarios
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// Respuesta por defecto (Error)
$response = ['success' => false, 'message' => 'Error desconocido.'];

// Verificar si el usuario está autenticado
if (!isset($_SESSION['user_id'])) {
    $response['message'] = 'No autenticado. Por favor, inicia sesión de nuevo.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
$user_id = $_SESSION['user_id'];

// Verificar si la solicitud es POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    $response['message'] = 'Método no permitido.';
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit;
}

// Obtener la contraseña enviada
$submittedPassword = $_POST['password'] ?? null;

if (empty($submittedPassword)) {
    $response['message'] = 'Se requiere la contraseña actual para confirmar.';
    http_response_code(400); // Bad Request
    echo json_encode($response);
    exit;
}

// Conexión a la base de datos
$pdo = Conexion::obtenerConexion();
if ($pdo === null) {
    error_log("Error CRÍTICO: No se pudo obtener conexión a la BD.");
    $response['message'] = 'Error interno del servidor [DBC].';
    http_response_code(500); // Internal Server Error
    echo json_encode($response);
    exit;
}

try {
    $userPDO = new UsuarioPDO();

    // Obtener datos del usuario
    $userData = $userPDO->findById($user_id);

    if (!$userData) {
        error_log("Usuario logueado no encontrado en la BD.");
        session_unset(); session_destroy();
        $response['message'] = 'Error de sesión. Por favor, inicia sesión de nuevo.';
        $response['redirectUrl'] = LOGIN_URL ?? '../pages/intranet.php';
        http_response_code(404); // Not Found
        echo json_encode($response);
        exit;
    }

    // Verificar la contraseña
    if (password_verify($submittedPassword, $userData['password_hash'])) {

        // Eliminar el usuario
        $deleteSuccess = $userPDO->deleteUser($user_id);

        if ($deleteSuccess) {
            error_log("Usuario eliminado correctamente.");

            // Destruir la sesión
            session_unset();
            session_destroy();

            // Respuesta de éxito con redirección
            $response = [
                'success' => true,
                'redirectUrl' => (defined('LOGIN_URL') ? LOGIN_URL : '../pages/intranet.php') . '?account_deleted=1'
            ];
            http_response_code(200); // OK
        } else {
            error_log("Error al intentar eliminar el usuario.");
            $response['message'] = 'Error al intentar eliminar la cuenta. Contacta a soporte.';
            http_response_code(500); // Internal Server Error
        }
    } else {
        error_log("Contraseña incorrecta.");
        $response['message'] = 'La contraseña actual ingresada es incorrecta.';
        http_response_code(403); // Forbidden
    }

} catch (RuntimeException $e) {
    error_log("Error crítico: " . $e->getMessage());
    $response['message'] = 'Error interno del servidor [PDO Init].';
    http_response_code(500);
} catch (Exception $e) {
    error_log("Excepción inesperada: " . $e->getMessage());
    $response['message'] = 'Ocurrió un error inesperado al eliminar la cuenta.';
    http_response_code(500);
}

// Enviar respuesta JSON final
echo json_encode($response);
exit;

?>
