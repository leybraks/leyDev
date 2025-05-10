<?php
// intranet/crear_tarea.php

// --- Inicialización, Sesión, Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error sesión"); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- Seguridad: Login y Rol Tutor ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    redirectWithError(LOGIN_URL ?? '../pages/intranet.php', 'login_error', 'Acceso no autorizado.');
}
$tutorId = $_SESSION['user_id'];

// --- Obtener IDs de URL y Validar ---
$lessonId = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);
$classId = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);
$pageError = null;
$lessonDetails = null;
$classDetails = null;

// --- Generar Token CSRF ---
// Usar un token específico para este formulario si es necesario, o el general.
if (empty($_SESSION['csrf_token_assignment_create'])) { // Token específico
    $_SESSION['csrf_token_assignment_create'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_assignment_create'];


if (!$lessonId || !$classId) {
    $pageError = "Faltan identificadores de lección o clase.";
} else {
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) {
        $pageError = "Error de conexión a la base de datos.";
    } else {
        try {
            $cursoPDO = new CursoPDO();
            $lessonDetails = $cursoPDO->findLessonById($lessonId);
            if (!$lessonDetails || $lessonDetails['class_id'] != $classId) {
                $pageError = "Lección o clase no válida.";
                $lessonDetails = null; // Asegurar que no se usen datos incorrectos
            } else {
                $classDetails = $cursoPDO->findClassById($classId);
                if (!$classDetails || $classDetails['tutor_id'] != $tutorId) {
                    $pageError = "No tienes permiso para añadir tareas a esta clase.";
                    $lessonDetails = null; $classDetails = null;
                }
            }
        } catch (Exception $e) {
            $pageError = "Error al verificar datos de la lección/clase.";
            error_log("Error verificando datos en crear_tarea.php: " . $e->getMessage());
        }
    }
}

// Los mensajes de sesión $formError y $formData ya no son la forma principal con AJAX
// $formData = $_SESSION['assignment_form_data'] ?? []; unset($_SESSION['assignment_form_data']);
// $formError = $_SESSION['assignment_error'] ?? null; unset($_SESSION['assignment_error']);
$formData = []; // Para AJAX, el repopulado se haría con JS si es necesario tras un error
$formError = null;


$lessonTitle = $lessonDetails ? htmlspecialchars($lessonDetails['title'] ?? 'N/A') : ($pageError ? 'Inválida' : 'Cargando...');
$pageTitle = ($lessonDetails && $classDetails) ? "Crear Tarea para Lección: " . $lessonTitle : ($pageError ?: "Error al Cargar");

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Intranet LEYdev</title>
    <link rel="stylesheet" href="../resources/css/intranet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Puedes mover estos estilos a intranet.css si son reutilizables */
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid transparent; }
        .success-message { color: var(--intranet-success-text); background-color: var(--intranet-success-bg); border-color: var(--intranet-success-border); }
        .error-message { color: var(--intranet-error-text); background-color: var(--intranet-error-bg); border-color: var(--intranet-error-border); }
        .error-message ul { margin-top: 5px; padding-left: 20px; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled' ? 'modo-oscuro' : ''; ?>">

    <div class="form-container">
        <p class="back-link">
            <a href="ver_curso.php?clase_id=<?php echo htmlspecialchars($classId ?: ''); ?>"><i class="fas fa-arrow-left"></i> Volver a la Clase</a>
        </p>

        <h1><?php echo $pageTitle; ?></h1>

        <?php if ($pageError): ?>
            <p class="message error-message"><?php echo htmlspecialchars($pageError); ?></p>
        <?php elseif ($lessonDetails && $classDetails): ?>
            <p class="context-info">Estás creando una tarea para la lección "<?php echo $lessonTitle; ?>" de la clase "<?php echo htmlspecialchars($classDetails['course_title'] ?? ''); ?>" (<?php echo htmlspecialchars($classDetails['schedule'] ?? ''); ?>).</p>

            <div id="assignment-creation-ajax-message" class="message" style="display:none; margin-bottom: 15px;"></div>

            <?php /* Los mensajes de error de sesión ya no son el método principal
            if ($formError): ?>
                <p class="message error-message"><?php echo nl2br(htmlspecialchars($formError)); ?></p>
            <?php endif; */ ?>

            <form action="../Scripts/handle_assignment_create.php" method="POST" id="create-assignment-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="lesson_id" value="<?php echo htmlspecialchars($lessonId); ?>">
                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($classId); ?>">

                <div class="form-group">
                    <label for="assignment_title">Título de la Tarea:</label>
                    <input type="text" id="assignment_title" name="title" required value="<?php echo htmlspecialchars($formData['title'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label for="assignment_description">Descripción / Instrucciones:</label>
                    <textarea id="assignment_description" name="description" rows="5"><?php echo htmlspecialchars($formData['description'] ?? ''); ?></textarea>
                </div>
                <div class="form-row-flex">
                    <div class="form-group form-group-half">
                        <label for="assignment_due_date">Fecha Límite (Opcional):</label>
                        <input type="datetime-local" id="assignment_due_date" name="due_date" value="<?php echo htmlspecialchars($formData['due_date'] ?? ''); ?>">
                    </div>
                    <div class="form-group form-group-half">
                        <label for="assignment_points">Puntaje Máximo (Opcional):</label>
                        <input type="number" id="assignment_points" name="total_points" min="0" step="1" value="<?php echo htmlspecialchars($formData['total_points'] ?? ''); ?>">
                    </div>
                </div>
                <button type="submit" class="btn-submit">Guardar Tarea</button>
            </form>
        <?php else: ?>
             <p class="message error-message">No se pudieron cargar los detalles necesarios para crear la tarea. Asegúrate de que los IDs de lección y clase sean correctos.</p>
        <?php endif; ?>
    </div>

    <script src="../resources/js/create_assignment_ajax.js"></script>
    <script src="../resources/js/script.js"></script> <?php // Para modo oscuro, etc. ?>
</body>
</html>
