<?php
// Scripts/handle_google_callback.php (CON LOGGING DETALLADO)

// --- Función de Log Helper ---
function write_google_log($message) {
    // Asume que LOG_PATH se definió en config.php
    if (defined('LOG_PATH')) {
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents(LOG_PATH, "[$timestamp] [GoogleCallback] " . $message . "\n", FILE_APPEND);
    }
}
// --- Fin Helper ---


// --- Inicialización y Carga ---
write_google_log("--- Callback Iniciado ---");
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); write_google_log("Sesión iniciada."); }
    else { write_google_log("ERROR FATAL: Headers sent antes de session_start."); die(); }
} else { write_google_log("Sesión ya iniciada."); }

// Cargar dependencias (Verifica Case!)
require_once __DIR__ . '/../Config/config.php';       write_google_log("Config cargado.");
require_once __DIR__ . '/../Includes/autoload.php';     write_google_log("Autoload cargado.");
require_once __DIR__ . '/../Includes/functions.php';    write_google_log("Functions cargado.");
require_once __DIR__ . '/../PDO/Conexion.php';          write_google_log("Conexion cargado.");
require_once __DIR__ . '/../Includes/google-api-client/vendor/autoload.php'; write_google_log("Google API Autoload cargado.");



// URLs
$loginUrl = defined('LOGIN_URL') ? LOGIN_URL : '../pages/intranet.php';
$dashboardUrl = defined('DASHBOARD_URL') ? DASHBOARD_URL : '../intranet/intranet.php';
$profileSetupUrl = defined('PROFILE_SETUP_URL') ? PROFILE_SETUP_URL : '../pages/llenarDatos.php';

// --- Inicializar Cliente Google API ---
$client = new Google_Client();
$client->setClientId(GOOGLE_CLIENT_ID);
$client->setClientSecret(GOOGLE_CLIENT_SECRET);
$client->setRedirectUri(GOOGLE_REDIRECT_URI);
$client->addScope("email"); $client->addScope("profile"); $client->addScope("openid");
write_google_log("Parámetros GET recibidos: " . json_encode($_GET));
write_google_log("Cliente Google inicializado.");

// --- Procesar Respuesta de Google ---
try {
    write_google_log("Procesando respuesta de Google...");
    if (isset($_GET['error'])) {
        throw new Exception('Error desde Google: ' . htmlspecialchars($_GET['error']));
    }
    if (!isset($_GET['code'])) {
        throw new Exception('No se recibió código de autorización de Google.');
    }
    $code = $_GET['code'];
    write_google_log("Código de autorización recibido: " . substr($code, 0, 10) . "..."); // Loggear solo parte del código

    // Intercambiar código por token
    write_google_log("Intercambiando código por token...");
    $token = $client->fetchAccessTokenWithAuthCode($code);
    if (isset($token['error'])) {
        throw new Exception('Error al obtener token de Google: ' . ($token['error_description'] ?? json_encode($token)));
    }
    if (!isset($token['id_token'])) {
        throw new Exception('No se recibió id_token de Google.');
    }
    write_google_log("Token ID recibido.");

    // Verificar Token ID
    write_google_log("Verificando Token ID...");
    $payload = $client->verifyIdToken($token['id_token']);
    if (!$payload) {
         throw new Exception('Token ID de Google inválido o expirado.');
    }
    write_google_log("Token ID verificado OK. Payload: " . json_encode($payload));

    // Extraer datos del payload
    $google_id = $payload['sub'];
    $email = $payload['email'] ?? null;
    $email_verified = $payload['email_verified'] ?? false;
    $name = $payload['name'] ?? '';

    if (empty($email) || !$email_verified) {
         throw new Exception('Email de Google no presente o no verificado.');
    }
    write_google_log("Datos extraídos: GoogleID=$google_id, Email=$email");

    // --- Lógica de Usuario Local ---
    $pdo = Conexion::obtenerConexion();
    if(!$pdo) { throw new Exception("Error de conexión a BD interna.", 500); }
    $userPDO = new UsuarioPDO();
    write_google_log("Conexión BD y UsuarioPDO listos.");

    // 1. Buscar por Google ID
    write_google_log("Buscando usuario por Google ID: $google_id");
    $user = $userPDO->findByGoogleId($google_id);

    if ($user) {
        write_google_log("Usuario encontrado por Google ID: {$user['id']}. Procediendo a login.");
        // Usuario ya existe y está vinculado, proceder a login (se hace más abajo)
    } else {
        write_google_log("Usuario NO encontrado por Google ID. Buscando por Email: $email");
        // 2. Buscar por Email
        $user = $userPDO->findByEmail($email); // Asegúrate que findByEmail devuelve todos los datos necesarios

        if ($user) {
            // 2a. Encontrado por Email -> Vincular
            write_google_log("Usuario encontrado por Email: {$user['id']}. Vinculando Google ID.");
            if (!$userPDO->updateGoogleId($user['id'], $google_id)) {
                error_log("ADVERTENCIA: No se pudo vincular Google ID $google_id a User ID {$user['id']}.");
                // No es un error fatal para el login, pero sí para el futuro. Continuamos.
            } else {
                 write_google_log("Google ID vinculado a User ID: {$user['id']}.");
            }
             // Proceder a login (se hace más abajo)
        } else {
            // 2b. No encontrado por Email -> Crear NUEVO usuario
            write_google_log("Usuario no encontrado por Email. Creando nueva cuenta...");
            $username = explode('@', $email)[0] . '_' . substr($google_id, 0, 4);
            if ($userPDO->findByUsername($username)) { $username .= rand(100, 999); }
            $placeholderPassword = password_hash(bin2hex(random_bytes(20)), PASSWORD_DEFAULT);
            write_google_log("Intentando crear usuario con username: $username");

            // Llamar a create con el argumento googleId
            $newUserId = $userPDO->create(
                 $username, $email, $placeholderPassword,
                 null, null, null, // codigo, token, expires (null para social)
                 $google_id        // ID de Google
            );

            if ($newUserId) {
                write_google_log("Usuario creado con ID: $newUserId. Buscando datos completos...");
                $user = $userPDO->findById($newUserId); // Obtener datos del nuevo usuario
                if (!$user) { throw new Exception("Error al recuperar datos del usuario recién creado.", 500); }
                 write_google_log("Datos del nuevo usuario obtenidos.");
            } else {
                write_google_log("ERROR CRITICO: Falló userPDO->create() para email $email");
                throw new Exception("No se pudo crear tu cuenta en nuestro sistema.", 500);
            }
        }
    }

    // --- Tenemos $user (existente o nuevo), proceder a login ---
    write_google_log("Usuario listo para login (ID: {$user['id']}). Verificando estado activo...");
    if ($user['active'] != 1) { // Doble chequeo por si acaso
         throw new Exception('Tu cuenta en nuestro sistema está actualmente inactiva.');
    }

    // --- Establecer Sesión Local ---
    write_google_log("Estableciendo sesión...");
    session_regenerate_id(true);
    unset($_SESSION['login_error']);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['google_id'] = $google_id; // Guardar google_id
    write_google_log("Sesión establecida para User ID: {$user['id']}.");

    // --- Redirigir al Usuario ---
    $destination = ($user['perfil_completo'] == 0) ? $profileSetupUrl : $dashboardUrl;
    write_google_log("Redirigiendo a: $destination");
    header('Location: ' . $destination);
    exit; // Terminar script después de la redirección

} catch (Exception $e) {
    // Captura cualquier excepción y redirige a login con error
    write_google_log("!!! EXCEPCIÓN CAPTURADA: " . $e->getMessage());
    // Limpiar buffer por si acaso antes de la redirección de error
    if (ob_get_level() > 0) { ob_end_clean(); }
    redirectWithError($loginUrl, 'login_error', 'Error Google Sign-In: ' . $e->getMessage());
    // redirectWithError incluye exit()
}

// Limpiar buffer si el script llegara aquí inesperadamente
if (ob_get_level() > 0) { ob_end_clean(); }
?>