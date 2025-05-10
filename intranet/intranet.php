<?php
// intranet/intranet.php

// --- 1. Inicialización, Sesión, Carga de Dependencias ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error fatal: Sesión no pudo iniciarse."); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- 2. Seguridad: Verificar Login ---
if (!isset($_SESSION['user_id'])) {
    redirectWithError(LOGIN_URL ?? '../pages/intranet.php', 'login_error', 'Debes iniciar sesión para acceder a la intranet.');
}
$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? 'Usuario';
$role = $_SESSION['role'] ?? 'desconocido';

// --- 3. Generar/Obtener Token CSRF (General para la página o específico para el modal) ---
if (empty($_SESSION['csrf_token_intranet'])) {
    $_SESSION['csrf_token_intranet'] = bin2hex(random_bytes(32));
}
$csrf_token_page = $_SESSION['csrf_token_intranet'];

// --- 4. Lógica para obtener datos del perfil (para el saludo inicial) ---
$persona = null;
$nombreCompletoParaSaludo = $username;
$primerNombre = $username;
$genero = null;

$pdo = Conexion::obtenerConexion();
if ($pdo) {
    try {
        $personaPDO = new PersonaPDO();
        $profileData = $personaPDO->findByUserId($userId);
        if ($profileData) {
            $persona = new Persona();
            $persona->fillFromArray($profileData);
            $nombreCompletoParaSaludo = $persona->getFullName() ?: $username;
            $partesNombre = explode(' ', trim($nombreCompletoParaSaludo), 2);
            $primerNombre = htmlspecialchars($partesNombre[0]);
            $genero = $persona->getGender();
        } else {
            $partesNombre = explode(' ', trim($username), 2);
            $primerNombre = htmlspecialchars($partesNombre[0]);
        }
    } catch (Exception $e) {
        error_log("Error cargando perfil en intranet.php para user $userId: " . $e->getMessage());
        $partesNombre = explode(' ', trim($username), 2);
        $primerNombre = htmlspecialchars($partesNombre[0]);
    }
}

// --- 5. Lógica para construir el saludo ---
$saludoBase = 'Bienvenide';
switch(strtolower($genero ?? '')) {
    case 'masculino': $saludoBase = 'Bienvenido'; break;
    case 'femenino': $saludoBase = 'Bienvenida'; break;
}
$rolDisplay = '';
switch (strtolower($role)) {
    case 'alumno':
        switch (strtolower($genero ?? '')) {
            case 'masculino': $rolDisplay = 'Alumno'; break;
            case 'femenino': $rolDisplay = 'Alumna'; break;
            default: $rolDisplay = 'Alumne';
        }
        break;
    case 'tutor':
        switch (strtolower($genero ?? '')) {
            case 'masculino': $rolDisplay = 'Tutor'; break;
            case 'femenino': $rolDisplay = 'Tutora'; break;
            default: $rolDisplay = 'Tutore';
        }
        break;
    default: $rolDisplay = ucfirst(htmlspecialchars($role));
}
$saludoCompleto = $saludoBase . ($rolDisplay ? ' ' . $rolDisplay : '') . ', ' . $primerNombre . '!';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Intranet | LeyCode</title>
    <link rel="stylesheet" href="../resources/css/intranet.css">
    <link rel="shortcut icon" href="../resources/img/logo.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        .message { padding: 10px; margin: 15px 0; border-radius: 4px; border: 1px solid transparent; font-size: 0.9em; }
        .success-message { color: var(--intranet-success-text, #155724); background-color: var(--intranet-success-bg, #d4edda); border-color: var(--intranet-success-border, #c3e6cb); }
        .error-message { color: var(--intranet-error-text, #721c24); background-color: var(--intranet-error-bg, #f8d7da); border-color: var(--intranet-error-border, #f5c6cb); }
        .loading-message {text-align: center; padding: 20px; font-style: italic; color: var(--intranet-text-color);}
        .modal { position: fixed; z-index: 1050; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0, 0, 0, 0.6); display: none; align-items: center; justify-content: center; opacity: 0; visibility: hidden; transition: opacity 0.3s ease, visibility 0.3s ease; }
        .modal.is-visible { opacity: 1; visibility: visible; display: flex !important; }
        .modal-content { background-color: var(--intranet-content-bg); margin: auto; padding: 25px; border: 1px solid var(--intranet-border-color); width: 90%; max-width: 480px; border-radius: 8px; position: relative; box-shadow: 0 5px 15px rgba(0,0,0,0.3); color: var(--intranet-text-color); transform: scale(0.95); transition: transform 0.3s ease; }
        .modal.is-visible .modal-content { transform: scale(1); }
        .modal-content h2 { margin-top: 0; color: var(--intranet-heading-color); text-align: center; margin-bottom: 15px; font-size: 1.3rem; }
        .modal-content p { margin-bottom: 15px; line-height: 1.5; font-size: 0.9rem; }
        .modal-content p strong { color: var(--intranet-error-text, #721c24); font-weight: 600; }
        .close-modal-btn { color: #aaa; position: absolute; top: 8px; right: 12px; font-size: 26px; font-weight: bold; line-height: 1; cursor: pointer; transition: color 0.2s ease; }
        .close-modal-btn:hover, .close-modal-btn:focus { color: var(--intranet-text-color); text-decoration: none; }
        #delete-account-modal .form-group { margin-bottom: 18px; }
        #delete-account-modal label { display: block; margin-bottom: 6px; font-weight: 500; color: var(--intranet-text-color); font-size: 0.85rem; }
        #delete-account-modal input[type="password"] { width: 100%; padding: 8px 10px; font-size: 0.95rem; border-radius: 4px; box-sizing: border-box; border: 1px solid var(--intranet-input-border); background-color: var(--intranet-input-bg); color: var(--intranet-input-text); }
        #delete-account-modal input[type="password"]:focus { border-color: var(--intranet-input-focus-border); outline: none; box-shadow: 0 0 0 2px color-mix(in srgb, var(--intranet-accent) 25%, transparent); }
        .modal-actions { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; padding-top: 15px; border-top: 1px solid var(--intranet-border-color); }
        .btn-secondary { background-color: #6c757d; color: white; padding: 8px 15px; font-size:0.9em; border: none; border-radius: 5px; cursor: pointer; font-weight: 500; transition: background-color 0.2s ease; }
        .btn-secondary:hover { background-color: #5a6268; }
        /* #delete-error-message tiene .message .error-message */
    </style>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled' ? 'modo-oscuro' : ''; ?>">
<header>
    <i class="fas fa-user user-header-icon" aria-hidden="true"></i> <h1>Intranet | LeyCode</h1>
    <button class="logout" onclick="window.location.href='../Scripts/handle_logout.php';">Cerrar Sesión</button>
</header>

<div class="container">
    <aside class="sidebar">
        <nav id="sidebar-nav">
            <ul>
                <li><a href="#inicio" class="sidebar-link active" data-target="inicio"><i class="fas fa-house"></i> Inicio</a></li>
                <?php if ($role === 'alumno'): ?>
                    <li><a href="#catalogo" class="sidebar-link" data-target="catalogo"><i class="fas fa-th-list fs-4"></i> <span class="ms-1 d-none d-sm-inline">Catálogo</span></a></li>
                    <li><a href="#cursos" class="sidebar-link" data-target="cursos"><i class="fas fa-book"></i> Mis Cursos</a></li>
                    <li><a href="#notas" class="sidebar-link" data-target="notas"><i class="fas fa-clipboard"></i> Mis Notas</a></li>
                <?php elseif ($role === 'tutor'): ?>
                    <li><a href="#cursos-asignados" class="sidebar-link" data-target="cursos-asignados"><i class="fas fa-chalkboard-teacher"></i> Cursos Asignados</a></li>
                    <li><a href="#mis-estudiantes" class="sidebar-link" data-target="mis-estudiantes"><i class="fas fa-users"></i> Mis Estudiantes</a></li>
                    <li><a href="#calificaciones" class="sidebar-link" data-target="calificaciones"><i class="fas fa-marker"></i> Calificaciones</a></li>
                <?php endif; ?>
                <li><a href="#mensajes" class="sidebar-link" data-target="mensajes"><i class="fas fa-envelope"></i> Mensajes</a></li>
                <li><a href="#configuracion" class="sidebar-link" data-target="configuracion"><i class="fas fa-gear"></i> Configuración</a></li>
            </ul>
        </nav>
    </aside>

    <main class="content" id="main-content-area">
        <p class="loading-message" style="text-align:center; padding:20px;">Cargando intranet...</p>
    </main>
</div>

<div id="delete-account-modal" data-csrf-token="<?php echo htmlspecialchars($csrf_token_page); ?>" class="modal" style="display: none;">
    <div class="modal-content">
        <span class="close-modal-btn" id="btn-close-delete-modal" title="Cerrar">&times;</span>
        <h2>Confirmar Eliminación de Cuenta</h2>
        <p><strong>¡ADVERTENCIA!</strong> Esta acción es irreversible y eliminará todos tus datos asociados con esta cuenta.</p>
        <p>Para confirmar, por favor ingresa tu contraseña actual:</p>
        <div class="form-group">
            <label for="delete-confirm-password">Contraseña Actual:</label>
            <input type="password" id="delete-confirm-password" name="delete_confirm_password" required autocomplete="current-password">
            <p id="delete-error-message" class="message" style="display: none; margin-top: 10px;"></p>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" id="btn-cancel-delete">Cancelar</button>
            <button type="button" class="btn-danger" id="btn-confirm-delete">Sí, Eliminar mi Cuenta</button>
        </div>
    </div>
</div>

<script src="../resources/js/script.js"></script>
<script src="../resources/js/profile_form_ajax.js"></script>
<script src="../resources/js/enrollment_ajax.js"></script>
<script src="../resources/js/navegation.js" defer></script>
</body>
</html>
