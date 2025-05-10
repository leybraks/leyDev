<?php
// scripts/handle_verificacion_codigo.php

if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        http_response_code(500);
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode([
            'success' => false,
            'message' => 'Error crítico del servidor: Headers ya enviados.',
            'status' => 'server_error_headers'
        ]);
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

$response = [
    'success' => false,
    'message' => '',
    'status' => '',
    'redirectUrl' => null // Añadido para la URL de redirección
];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    $response['status'] = 'invalid_method';
    echo json_encode($response);
    exit;
}

$codigoIntroducido = trim($_POST['codigo'] ?? '');

if (empty($codigoIntroducido)) {
    http_response_code(400);
    $response['message'] = 'Por favor, introduce el código de verificación.';
    $response['status'] = 'empty_code';
    echo json_encode($response);
    exit;
}

if (!preg_match('/^[a-f0-9]{8}$/i', $codigoIntroducido)) {
    http_response_code(400);
    $response['message'] = 'El formato del código introducido no es válido (debe ser 8 caracteres hexadecimales).';
    $response['status'] = 'invalid_format_code';
    echo json_encode($response);
    exit;
}

try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        error_log("Error CRÍTICO en handle_verificacion_codigo (AJAX): No se pudo obtener conexión a la BD.");
        http_response_code(500);
        $response['message'] = 'Error interno del servidor. Intente más tarde.';
        $response['status'] = 'db_connection_failed';
        echo json_encode($response);
        exit;
    }

    $userPDO = new UsuarioPDO();
    $user = $userPDO->findByVerificationCode($codigoIntroducido);

    if ($user) {
        $userIdToActivate = $user['id'];
        $tiempoExpiracionGuardado = $user['token_expires_at'];
        $yaActivo = ($user['active'] == 1 && $user['id_eCon'] == 1);

        if ($yaActivo) {
            unset($_SESSION['verifying_email']);
            unset($_SESSION['verification_notice']);
            $response['success'] = true;
            $response['message'] = 'Tu cuenta ya ha sido verificada anteriormente. Serás redirigido.';
            $response['status'] = 'already_active';
            // Redirigir a login si ya estaba activo
            $response['redirectUrl'] = BASE_URL . '/pages/intranet.php'; // O tu página de login
        } elseif ($tiempoExpiracionGuardado !== null && time() > $tiempoExpiracionGuardado) {
            error_log("Intento de verificación con código MANUAL EXPIRADO (AJAX): $codigoIntroducido para user ID: $userIdToActivate");
            $response['message'] = 'El código de verificación ha expirado. Por favor, solicita uno nuevo.';
            $response['status'] = 'expired_code';
        } else {
            $activationSuccess = $userPDO->activateUser($userIdToActivate);
            if ($activationSuccess) {
                error_log("Usuario ID: $userIdToActivate activado mediante código manual '$codigoIntroducido' (AJAX).");
                unset($_SESSION['verifying_email']);
                
                $response['success'] = true;
                // MENSAJE Y REDIRECCIÓN CORREGIDOS AQUÍ
                $response['message'] = '¡Cuenta verificada exitosamente! Serás redirigido a la página de confirmación.';
                $response['status'] = 'activation_success';
                $response['redirectUrl'] = BASE_URL . '/pages/registro_exitoso.php';
            } else {
                error_log("Error al ejecutar activateUser para user ID: $userIdToActivate con código: $codigoIntroducido (AJAX)");
                http_response_code(500);
                $response['message'] = 'Error al actualizar el estado de la cuenta. Inténtalo de nuevo.';
                $response['status'] = 'activation_db_error';
            }
        }
    } else {
        error_log("Intento de verificación con código INVÁLIDO/INEXISTENTE (AJAX): $codigoIntroducido");
        $response['message'] = 'El código de verificación no es válido o ya fue utilizado.';
        $response['status'] = 'invalid_or_used_code';
    }

} catch (RuntimeException $e) {
    error_log("Error CRÍTICO al instanciar UsuarioPDO (AJAX): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Error interno del servidor (Init). Intente más tarde.';
    $response['status'] = 'pdo_init_exception';
} catch (Exception $e) {
    error_log("Excepción inesperada en handle_verificacion_codigo (AJAX): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Ocurrió un error inesperado durante la verificación.';
    $response['status'] = 'unexpected_exception';
}

echo json_encode($response);
exit;
?>
