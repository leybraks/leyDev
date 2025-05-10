<?php
// --- Inicialización y Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); }
    else { die("Error fatal: Sesión no pudo iniciarse."); }
}

// --- ¡NUEVO: VALIDACIÓN TOKEN CSRF!! ---
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    error_log("Error CSRF detectado en profile setup para user ID: " . ($_SESSION['user_id'] ?? '???'));
    redirectWithError(PROFILE_SETUP_URL, 'profile_error', 'Error de validación de seguridad. Por favor, intenta enviar el formulario de nuevo.');
}

// Cargar dependencias
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- Definir URLs ---
define('USER_DASHBOARD_URL', '../intranet/intranet.php');
define('PROFILE_SETUP_URL', '../pages/llenarDatos.php');
define('LOGIN_URL', '../pages/intranet.php');

// --- Seguridad: Verificar Login ---
if (!isset($_SESSION['user_id'])) {
    redirectWithError(LOGIN_URL, 'login_error', 'Debes iniciar sesión para completar tu perfil.');
}
$user_id = $_SESSION['user_id']; // Obtener ID del usuario logueado

// --- Verificar Método HTTP ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    redirectWithError(PROFILE_SETUP_URL, 'profile_error', 'Método no permitido.');
}

// --- Obtener Datos del POST ---
$profileDataInput = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'paternal_last_name' => trim($_POST['paternal_last_name'] ?? ''),
    'maternal_last_name' => trim($_POST['maternal_last_name'] ?? ''),
    'birth_date' => trim($_POST['birth_date'] ?? ''),
    'city' => trim($_POST['city'] ?? ''),
    'gender' => $_POST['gender'] ?? '',
];

// --- Validación de Datos ---
$errors = [];
if (empty($profileDataInput['first_name'])) {
    $errors[] = "El nombre es obligatorio.";
}
if (empty($profileDataInput['paternal_last_name'])) {
    $errors[] = "El apellido paterno es obligatorio.";
}
if (empty($profileDataInput['birth_date'])) {
    $errors[] = "La fecha de nacimiento es obligatoria.";
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $profileDataInput['birth_date'])) {
        $errors[] = "El formato de la fecha de nacimiento debe ser AAAA-MM-DD.";
    }
}

// --- Si hay errores de validación ---
if (!empty($errors)) {
    $_SESSION['profile_error'] = implode('<br>', $errors);
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . PROFILE_SETUP_URL);
    exit;
}

// --- Si la validación pasa, proceder a guardar ---
$pdo = Conexion::obtenerConexion();
if ($pdo === null) {
    error_log("Error CRÍTICO en handle_profile_setup: No se pudo obtener conexión a la BD.");
    $_SESSION['profile_error'] = 'Error interno del servidor [DBC].';
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . PROFILE_SETUP_URL);
    exit;
}

try {
    $personaPDO = new PersonaPDO();
    $usuarioPDO = new UsuarioPDO();

    $saveSuccess = $personaPDO->saveOrUpdate($user_id, $profileDataInput);

    if ($saveSuccess) {
        $flagSuccess = $usuarioPDO->markProfileAsComplete($user_id);

        if ($flagSuccess) {
            unset($_SESSION['form_data']);
            unset($_SESSION['profile_error']);
            redirectWithSuccess(USER_DASHBOARD_URL, 'profile_message', '¡Perfil completado y guardado exitosamente!');
        } else {
            error_log("Perfil guardado (User ID: $user_id) pero falló markProfileAsComplete.");
            $_SESSION['profile_error'] = 'Perfil guardado, pero ocurrió un error al finalizar. Contacta a soporte.';
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . PROFILE_SETUP_URL);
            exit;
        }
    } else {
        error_log("Falló PersonaPDO::saveOrUpdate para user ID: $user_id");
        $_SESSION['profile_error'] = 'Error al guardar los datos del perfil. Por favor, intenta de nuevo.';
        $_SESSION['form_data'] = $_POST;
        header('Location: ' . PROFILE_SETUP_URL);
        exit;
    }

} catch (RuntimeException $e) {
    error_log("Error CRÍTICO (RuntimeException) en handle_profile_setup: " . $e->getMessage());
    $_SESSION['profile_error'] = 'Error interno del servidor [PDO Init].';
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . PROFILE_SETUP_URL);
    exit;
} catch (Exception $e) {
    error_log("Excepción inesperada en handle_profile_setup: " . $e->getMessage());
    $_SESSION['profile_error'] = 'Ocurrió un error inesperado al guardar el perfil.';
    $_SESSION['form_data'] = $_POST;
    header('Location: ' . PROFILE_SETUP_URL);
    exit;
}

?>
