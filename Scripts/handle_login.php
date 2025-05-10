<?php
// Scripts/handle_login.php (Actualizado para AJAX)

// --- Inicialización y Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error sesión"); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php'; // Carga UsuarioPDO, Usuario
require_once __DIR__ . '/../Includes/functions.php'; // Para redirect* si no es AJAX
require_once __DIR__ . '/../PDO/Conexion.php';

// --- Determinar si es una solicitud AJAX ---
$isAjaxRequest = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// --- URLs (Definidas en config.php, con fallbacks) ---
$loginPageUrl = LOGIN_URL ?? '../pages/intranet.php';
$dashboardUrl = DASHBOARD_URL ?? '../intranet/intranet.php';
$profileSetupUrl = PROFILE_SETUP_URL ?? '../pages/llenarDatos.php';

// --- Procesar Solicitud POST ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['usuario'] ?? '');
    $password = $_POST['clave'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    // --- Validar CSRF Token ---
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $errorMessage = 'Error de validación de seguridad.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            http_response_code(403); // Forbidden
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
            redirectWithError($loginPageUrl, 'login_error', $errorMessage);
        }
    }
    // unset($_SESSION['csrf_token']); // Opcional: invalidar token después de usar

    // --- Validaciones Básicas de Input ---
    if (empty($username) || empty($password)) {
        $errorMessage = 'Usuario y contraseña son obligatorios.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            http_response_code(400); // Bad Request
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
            redirectWithError($loginPageUrl, 'login_error', $errorMessage);
        }
    }

    // --- Intentar Login ---
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) {
        $errorMessage = 'Error de conexión con la base de datos.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            http_response_code(500); // Internal Server Error
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
             redirectWithError($loginPageUrl, 'login_error', $errorMessage);
        }
    }

    try {
        $usuarioPDO = new UsuarioPDO();
        $user = $usuarioPDO->findByUsername($username);

        $loginValid = false;
        $errorMessage = 'Usuario o contraseña incorrectos.'; // Mensaje genérico por defecto

        if ($user) { // Usuario encontrado
            if (password_verify($password, $user['password_hash'])) { // Contraseña coincide
                if ($user['active'] == 1 && $user['id_eCon'] == 1) { // Activo y verificado
                    // --- LOGIN EXITOSO ---
                    session_regenerate_id(true); // Seguridad
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    // Limpiar cualquier error de login previo
                    unset($_SESSION['login_error']);
                    session_write_close(); // Guardar sesión antes de responder/redirigir

                    $destinationUrl = ($user['perfil_completo'] == 0) ? $profileSetupUrl : $dashboardUrl;

                    if ($isAjaxRequest) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => true, 'redirectUrl' => $destinationUrl]);
                        exit;
                    } else {
                        header('Location: ' . $destinationUrl);
                        exit;
                    }


                } elseif ($user['active'] == 0 || $user['id_eCon'] == 0) {
                    $errorMessage = 'Tu cuenta no está activa o no ha sido verificada. <a href="../pages/solicitar_reenvio.php">Reenviar verificación</a>';
                }
            } // Si password_verify falla, se usa el $errorMessage genérico
        } // Si $user es false, se usa el $errorMessage genérico


        // --- Si Login NO fue exitoso (para AJAX o fallback) ---
        if ($isAjaxRequest) { // Siempre entra aquí si no hubo 'exit' antes
            header('Content-Type: application/json');
            http_response_code(401); // Unauthorized
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
            redirectWithError($loginPageUrl, 'login_error', $errorMessage);
        }

    } catch (Exception $e) {
        error_log("Error en handle_login.php: " . $e->getMessage());
        $errorMessage = 'Ocurrió un error inesperado. Intenta de nuevo.';
        if ($isAjaxRequest) {
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $errorMessage]);
            exit;
        } else {
            redirectWithError($loginPageUrl, 'login_error', $errorMessage);
        }
    }

} else { // Si no es POST
    if ($isAjaxRequest) {
        header('Content-Type: application/json');
        http_response_code(405); // Method Not Allowed
        echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
        exit;
    } else {
        header('Location: ' . $loginPageUrl);
        exit;
    }
}
?>