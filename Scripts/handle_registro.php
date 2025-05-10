<?php
// Scripts/handle_registro.php (Actualizado para AJAX)

// --- Inicialización y Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error sesión"); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php'; // UsuarioPDO, PersonaPDO, mailer.php (indirectamente)
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- Determinar si es una solicitud AJAX ---
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// --- URLs ---
$loginPageUrl = LOGIN_URL ?? '../pages/intranet.php'; // Usado para errores que impiden redirección a verificación
$verificationPageUrl = BASE_URL . '/pages/verificarCorreo.php'; // Ajusta si es diferente

// --- Procesar Solicitud POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['newUsuario'] ?? '');
    $email = trim($_POST['newEmail'] ?? '');
    $password = $_POST['newContraseña'] ?? '';
    $passwordConfirm = $_POST['newValidarContraseña'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // --- Validar CSRF Token ---
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errorMessage = 'Error de validación de seguridad.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json'); http_response_code(403);
            echo json_encode(['success' => false, 'message' => $errorMessage]); exit;
        } else {
            redirectWithError($loginPageUrl . '?form=register', 'register_error', $errorMessage); // Añadir query param para saber qué form mostrar
        }
    }
    // unset($_SESSION['csrf_token']); // Opcional

    // --- Validaciones del Lado del Servidor ---
    $errors = [];
    if (empty($username)) { $errors['newUsuario'] = 'El nombre de usuario es obligatorio.'; }
    elseif (strlen($username) < 4) { $errors['newUsuario'] = 'El usuario debe tener al menos 4 caracteres.';}
    if (empty($email)) { $errors['newEmail'] = 'El correo electrónico es obligatorio.'; }
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors['newEmail'] = 'El formato del correo no es válido.'; }
    if (empty($password)) { $errors['newContraseña'] = 'La contraseña es obligatoria.'; }
    elseif (strlen($password) < 6) { $errors['newContraseña'] = 'La contraseña debe tener al menos 6 caracteres.';}
    if ($password !== $passwordConfirm) { $errors['newValidarContraseña'] = 'Las contraseñas no coinciden.'; }

    // Si hay errores de validación
    if (!empty($errors)) {
        if ($isAjaxRequest) {
            header('Content-Type: application/json'); http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => 'Por favor corrige los errores.', 'errors' => $errors]); exit;
        } else {
            $_SESSION['register_error'] = implode('<br>', array_values($errors)); // Para mostrar error general si no es AJAX
            $_SESSION['register_form_data'] = $_POST; // Para repoblar
            redirectWithError($loginPageUrl . '?form=register', 'register_error_redirect', ''); // El error ya está en register_error
        }
    }

    // --- Intentar Registro ---
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) {
        $errorMessage = 'Error de conexión con la base de datos.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json'); http_response_code(500);
            echo json_encode(['success' => false, 'message' => $errorMessage]); exit;
        } else {
            redirectWithError($loginPageUrl . '?form=register', 'register_error', $errorMessage);
        }
    }

    try {
        $usuarioPDO = new UsuarioPDO();
        if ($usuarioPDO->findByUsername($username)) {
            $errors['newUsuario'] = 'El nombre de usuario ya está en uso.';
        }
        if ($usuarioPDO->findByEmail($email)) {
            $errors['newEmail'] = 'El correo electrónico ya está registrado.';
        }

        if (!empty($errors)) { // Errores de unicidad
            if ($isAjaxRequest) {
                header('Content-Type: application/json'); http_response_code(409); // Conflict
                echo json_encode(['success' => false, 'message' => 'Conflicto de datos.', 'errors' => $errors]); exit;
            } else {
                $_SESSION['register_error'] = implode('<br>', array_values($errors));
                $_SESSION['register_form_data'] = $_POST;
                redirectWithError($loginPageUrl . '?form=register', 'register_error_redirect', '');
            }
        }

        // --- Si no hay errores, proceder a crear usuario ---
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        $codigoManual = bin2hex(random_bytes(4));
        $tokenEnlace = bin2hex(random_bytes(32));
        $expiresAt = time() + TOKEN_VALIDITY_SECONDS; // De config.php

        // Usar el método create modificado que acepta google_id como último argumento (opcional)
        $userId = $usuarioPDO->create($username, $email, $hashedPassword, $codigoManual, $tokenEnlace, $expiresAt, null); // null para google_id

        if ($userId) {
            // Cargar mailer.php para enviar correo
            require_once __DIR__ . '/../Includes/mailer.php'; // ¡Verifica Case!
            if (sendVerificationEmail($email, $username, $codigoManual, $tokenEnlace)) {
                $successMessage = '¡Registro exitoso! Revisa tu correo para verificar tu cuenta.';
                $_SESSION['verification_notice'] = $successMessage; // Para mostrar en verificarCorreo.php
                $_SESSION['verifying_email'] = $email; // Para repoblar en reenviar

                if ($isAjaxRequest) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true, 'redirectUrl' => $verificationPageUrl, 'message' => $successMessage]); exit;
                } else {
                    redirectWithSuccess($verificationPageUrl, 'verification_redirect', ''); // El mensaje está en verification_notice
                }
            } else {
                $errorMessage = 'Usuario registrado, pero falló el envío del correo de verificación. Contacta a soporte.';
                error_log("Fallo sendVerificationEmail para: $email");
                if ($isAjaxRequest) {
                    header('Content-Type: application/json'); http_response_code(500);
                    echo json_encode(['success' => false, 'message' => $errorMessage]); exit;
                } else {
                     redirectWithError($loginPageUrl . '?form=register', 'register_error', $errorMessage);
                }
            }
        } else {
             $errorMessage = 'Error al registrar el usuario en la base de datos.';
             if ($isAjaxRequest) {
                header('Content-Type: application/json'); http_response_code(500);
                echo json_encode(['success' => false, 'message' => $errorMessage]); exit;
            } else {
                 redirectWithError($loginPageUrl . '?form=register', 'register_error', $errorMessage);
            }
        }

    } catch (Exception $e) {
        error_log("Error en handle_registro.php: " . $e->getMessage());
        $errorMessage = 'Ocurrió un error inesperado. Intenta de nuevo.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json'); http_response_code(500);
            echo json_encode(['success' => false, 'message' => $errorMessage]); exit;
        } else {
            redirectWithError($loginPageUrl . '?form=register', 'register_error', $errorMessage);
        }
    }

} else { // Si no es POST
    // ... (Manejo similar a login si no es POST) ...
    if ($isAjaxRequest) { /* ... JSON error ... */ } else { header('Location: ' . $loginPageUrl); exit; }
}
?>