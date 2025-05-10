<?php
// scripts/handle_resend_verification.php

// --- Inicialización y Carga ---
// Verificar si la sesión ya ha comenzado; si no, iniciar la sesión
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { 
        session_start(); 
    }
    else { 
        die("Error fatal: Sesión no pudo iniciarse."); 
    }
}

// Cargar dependencias necesarias para la ejecución
require_once __DIR__ . '/../Config/config.php';       // Verifica si la configuración está cargada correctamente
require_once __DIR__ . '/../Includes/autoload.php';    // Cargar clases y dependencias de la aplicación
require_once __DIR__ . '/../Includes/functions.php';   // Cargar funciones auxiliares
require_once __DIR__ . '/../PDO/Conexion.php';         // Cargar conexión con la base de datos
require_once __DIR__ . '/../Includes/mailer.php';      // Cargar funciones para el envío de correos electrónicos

// --- Definir la URL de la página de reenvío de verificación ---
define('RESEND_FORM_URL', '../pages/solicitar_reenvio.php'); // Página que solicita el email para reenviar verificación

// --- Verificar Método HTTP ---
// Se verifica que la solicitud sea un POST, si no se redirige con un mensaje de error
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectWithError(RESEND_FORM_URL, 'resend_error', 'Método no permitido.');
}

// --- Obtener y Validar Email ---
// Se obtiene el email del formulario y se valida que no esté vacío ni tenga un formato incorrecto
$email = trim($_POST['email'] ?? '');

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError(RESEND_FORM_URL, 'resend_error', 'Por favor, introduce una dirección de correo electrónico válida.');
}

// --- Lógica de Reenvío ---
// Intentar conectar con la base de datos
$pdo = Conexion::obtenerConexion();
if ($pdo === null) {
    // Error crítico si no se puede obtener conexión a la base de datos
    error_log("Error CRÍTICO en handle_resend_verification: No se pudo obtener conexión a la BD.");
    redirectWithError(RESEND_FORM_URL, 'resend_error', 'Error interno del servidor [DBC].');
}

try {
    // Instanciamos la clase UsuarioPDO para interactuar con la base de datos
    $userPDO = new UsuarioPDO();

    // 1. Buscar usuario por email usando el método de la clase
    $user = $userPDO->findByEmail($email);

    if (!$user) {
        // Si no se encuentra el usuario en la base de datos
        error_log("Intento de reenvío para email no registrado: $email");
        redirectWithError(RESEND_FORM_URL, 'resend_error', 'No se encontró ninguna cuenta asociada a esa dirección de correo.');
    }

    // 2. Verificar si la cuenta YA está verificada
    if ($user['active'] == 1 && $user['id_eCon'] == 1) {
        // La cuenta ya está activa, no es necesario reenviar el correo de verificación
        error_log("Intento de reenvío para cuenta ya verificada: $email (ID: {$user['id']})");
        // Usamos 'resend_message' para un mensaje informativo, no de error
        redirectWithSuccess(RESEND_FORM_URL, 'resend_message', 'Esta cuenta ya ha sido verificada. Puedes intentar iniciar sesión.');
    }

    // 3. Si la cuenta existe y NO está verificada, generar nuevos datos y actualizar
    $userId = $user['id'];
    $username = $user['username']; // Necesitamos el nombre de usuario para el correo

    error_log("Procediendo a reenviar verificación para $email (ID: $userId)");

    // Generamos nuevos códigos de verificación y un nuevo token de enlace
    $newCodigo = bin2hex(random_bytes(4));  // Nuevo código de verificación
    $newToken = bin2hex(random_bytes(16));  // Nuevo token para el enlace de verificación
    $newExpiry = time() + TOKEN_VALIDITY_SECONDS; // Tiempo de expiración del token, definido en configuración

    // Actualizamos la base de datos con los nuevos datos
    $updateSuccess = $userPDO->updateVerificationData($userId, $newCodigo, $newToken, $newExpiry);

    if ($updateSuccess) {
        // Si la base de datos se actualiza correctamente, intentamos enviar el nuevo correo de verificación
        $mailSent = sendVerificationEmail($email, $username, $newCodigo, $newToken);

        if ($mailSent) {
            // Si el correo se envía correctamente, redirigimos con un mensaje de éxito
            redirectWithSuccess(RESEND_FORM_URL, 'resend_message', 'Se ha enviado un nuevo correo de verificación a tu dirección. Revisa tu bandeja de entrada (y spam).');
        } else {
            // Si la base de datos se actualizó pero el correo no se pudo enviar, se maneja el error
            error_log("Error al reenviar correo para $email (ID: $userId) después de actualizar BD.");
            redirectWithError(RESEND_FORM_URL, 'resend_error', 'Se actualizaron tus datos pero hubo un problema al reenviar el correo. Intenta de nuevo más tarde o contacta a soporte.');
        }
    } else {
        // Si la actualización en la base de datos falla, mostramos un error
        error_log("Error al ejecutar updateVerificationData para user ID $userId durante reenvío.");
        redirectWithError(RESEND_FORM_URL, 'resend_error', 'Error al actualizar los datos de verificación. Por favor, contacta a soporte.');
    }

} catch (RuntimeException $e) { // Error al crear instancia PDO
    error_log("Error CRÍTICO (RuntimeException) en handle_resend_verification: " . $e->getMessage());
    redirectWithError(RESEND_FORM_URL, 'resend_error', 'Error interno del servidor [PDO Init].');
} catch (Exception $e) { // Otros errores inesperados
    error_log("Excepción inesperada en handle_resend_verification para email '$email': " . $e->getMessage());
    redirectWithError(RESEND_FORM_URL, 'resend_error', 'Ocurrió un error inesperado.');
}

?>
