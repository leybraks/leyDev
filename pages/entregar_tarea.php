<?php
// pages/entregar_tarea.php

// 1. Inicialización, Sesión, Carga
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error fatal: Sesión no pudo iniciarse."); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// Definir constante si no está en config.php (MEJOR EN CONFIG.PHP)
if (!defined('MAX_FILE_SIZE_SUBMISSION_MB')) {
    define('MAX_FILE_SIZE_SUBMISSION_MB', 10); // Ejemplo: 10MB. Mover a Config/config.php
}

// 2. Seguridad: Login y Rol Alumno (o el que corresponda)
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] !== 'alumno' && $_SESSION['role'] !== 'tutor') ) {
    redirectWithError(LOGIN_URL ?? 'intranet.php', 'login_error', 'Debes iniciar sesión como alumno para entregar tareas.');
}
$studentId = $_SESSION['user_id'];
$role = $_SESSION['role'];

// 3. Obtener y Validar ID de Tarea desde URL
$assignmentId = filter_input(INPUT_GET, 'assignment_id', FILTER_VALIDATE_INT);
$pageError = null;
$assignmentDetails = null;
$existingSubmission = null;
$classDetails = null;

// 4. CSRF Token
if (empty($_SESSION['csrf_token_submission'])) {
    $_SESSION['csrf_token_submission'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_submission'];

if (!$assignmentId || $assignmentId <= 0) {
    $pageError = "Identificador de tarea no válido.";
} else {
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) {
        $pageError = "Error de conexión a la base de datos.";
    } else {
        try {
            $tareaPDO = new TareaPDO();
            $cursoPDO = new CursoPDO();
            $entregaPDO = new EntregaPDO();

            $assignmentDetails = $tareaPDO->findAssignmentById($assignmentId);

            if (!$assignmentDetails) {
                $pageError = "Tarea no encontrada.";
            } else {
                $lessonDetails = $cursoPDO->findLessonById($assignmentDetails['lesson_id']);
                if ($lessonDetails) {
                    $classDetails = $cursoPDO->findClassById($lessonDetails['class_id']);
                }

                if ($role === 'alumno' && $classDetails) {
                    $isEnrolled = $cursoPDO->isStudentEnrolled($studentId, $classDetails['class_id']);
                    if (!$isEnrolled) {
                        $pageError = "No estás inscrito en la clase a la que pertenece esta tarea.";
                        $assignmentDetails = null;
                    }
                }

                if ($assignmentDetails && $role === 'alumno') {
                    $existingSubmission = $entregaPDO->findSubmissionByAssignmentAndStudent($assignmentId, $studentId);
                }
            }
        } catch (Exception $e) {
            $pageError = "Error al cargar datos de la tarea: " . $e->getMessage();
            error_log("Error en entregar_tarea.php para assignment $assignmentId: " . $e->getMessage());
        }
    }
}

$pageTitle = $assignmentDetails ? 'Entregar Tarea: ' . htmlspecialchars($assignmentDetails['title']) : 'Entregar Tarea';

$submissionSuccess = $_SESSION['submission_success'] ?? null; unset($_SESSION['submission_success']);
$submissionError = $_SESSION['submission_error'] ?? null; unset($_SESSION['submission_error']);

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
        /* Estilos específicos para esta página, si los necesitas además de intranet.css */
        /* La mayoría de los estilos deberían venir de intranet.css usando las variables CSS */
        .submission-container { max-width: 700px; margin: 2rem auto; padding: 2rem; background-color: var(--intranet-content-bg); border: 1px solid var(--intranet-border-color); border-radius: 8px; box-shadow: 0 2px 10px var(--intranet-shadow-color); }
        .back-link { margin-bottom: 1.5rem; display: inline-block; font-size: 0.9em; color: var(--intranet-accent); }
        .submission-container h1 { font-size: 1.7rem; margin-bottom: 0.5rem; color: var(--intranet-heading-color); }
        .assignment-info { margin-bottom: 1.5rem; padding-bottom:1rem; border-bottom: 1px solid var(--intranet-border-color); }
        .assignment-info p { margin-bottom: 0.5rem; font-size: 0.95rem; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.5rem; color: var(--intranet-text-color); }
        input[type="file"], textarea[name="submission_comments"] {
            width: 100%;
            padding: 0.6rem 0.8rem;
            font-size: 0.95rem;
            border: 1px solid var(--intranet-input-border);
            border-radius: 4px;
            background-color: var(--intranet-input-bg);
            color: var(--intranet-input-text);
            box-sizing: border-box;
            transition: background-color 0.3s ease, border-color 0.3s ease, color 0.3s ease;
        }
        textarea[name="submission_comments"] { min-height: 80px; resize: vertical; }
        .btn-submit {
            background-color: var(--intranet-btn-primary-bg); color: var(--intranet-btn-primary-text);
            padding: 0.7rem 1.5rem; border:none; border-radius: 5px; cursor: pointer; font-weight: 500;
            transition: background-color 0.2s ease, transform 0.2s ease;
        }
        .btn-submit:hover { background-color: var(--intranet-btn-primary-hover-bg); transform: translateY(-1px); }
        .btn-edit-submission { background-color: #ffc107; color: #212529; /* Amarillo */ }
        .btn-edit-submission:hover { background-color: #e0a800; }
        .message { padding: 10px; margin: 15px 0; border-radius: 4px; border: 1px solid transparent; font-size: 0.9em; }
        .current-submission-info { padding: 1rem; margin-bottom: 1rem; border: 1px solid var(--intranet-accent); border-left-width: 4px; background-color: color-mix(in srgb, var(--intranet-accent) 5%, var(--intranet-content-bg)); border-radius: 4px; }
        .current-submission-info p { margin-bottom: 0.5rem; }
        .current-submission-info strong { font-weight: 600; }
        .current-submission-info h4 { margin-top:0; margin-bottom: 0.75rem; color: var(--intranet-heading-color); font-size: 1.1rem;}
        .current-submission-info h4 i { margin-right: 0.5em; }

        /* Modo Oscuro específico para elementos de esta página si es necesario */
        body.modo-oscuro .btn-edit-submission { background-color: var(--intranet-accent); color: var(--intranet-btn-primary-text); } /* Usar variables de modo oscuro */
        body.modo-oscuro .btn-edit-submission:hover { background-color: var(--intranet-btn-primary-hover-bg); }
    </style>
</head>
<body class="modo-oscuro"> <?php // Aplicar siempre la clase modo-oscuro para esta página ?>

    <div class="submission-container">
        <p class="back-link">
            <?php if ($classDetails): ?>
                <a href="../intranet/ver_curso.php?clase_id=<?php echo htmlspecialchars($classDetails['class_id']); ?>"><i class="fas fa-arrow-left"></i> Volver a la Clase '<?php echo htmlspecialchars($classDetails['course_title'] ?? 'Curso'); ?>'</a>
            <?php else: ?>
                <a href="../intranet/intranet.php#cursos"><i class="fas fa-arrow-left"></i> Volver a Mis Cursos</a>
            <?php endif; ?>
        </p>

        <h1><?php echo $pageTitle; ?></h1>
        <hr style="margin-bottom: 1.5rem;">

        <?php if ($submissionSuccess): ?><p class="message success-message"><?php echo htmlspecialchars($submissionSuccess); ?></p><?php endif; ?>
        <?php if ($submissionError): ?><p class="message error-message"><?php echo htmlspecialchars($submissionError); ?></p><?php endif; ?>

        <?php if ($pageError): ?>
            <p class="message error-message"><?php echo htmlspecialchars($pageError); ?></p>
        <?php elseif ($assignmentDetails): ?>
            <div class="assignment-info">
                <p><strong>Tarea:</strong> <?php echo htmlspecialchars($assignmentDetails['title']); ?></p>
                <?php if(!empty($assignmentDetails['description'])): ?>
                    <p><strong>Descripción:</strong> <?php echo nl2br(htmlspecialchars($assignmentDetails['description'])); ?></p>
                <?php endif; ?>
                <p><strong>Fecha Límite:</strong> <?php echo htmlspecialchars($assignmentDetails['due_date'] ? date('d/m/Y H:i', strtotime($assignmentDetails['due_date'])) : 'N/A'); ?></p>
                <p><strong>Puntos Posibles:</strong> <?php echo htmlspecialchars($assignmentDetails['total_points'] ?: 'N/A'); ?></p>
            </div>

            <div id="submission-ajax-message" class="message" style="display:none; margin-bottom: 15px;"></div>

            <?php if ($role === 'alumno'): ?>
                <?php if ($existingSubmission): ?>
                    <div class="current-submission-info">
                        <h4><i class="fas fa-check-circle" style="color: var(--intranet-success-text);"></i> Ya has enviado esta tarea</h4>
                        <p><strong>Archivo enviado:</strong> 
                            <a href="../<?php echo htmlspecialchars($existingSubmission['stored_filepath']); ?>" target="_blank" download="<?php echo htmlspecialchars($existingSubmission['original_filename']); ?>">
                                <?php echo htmlspecialchars($existingSubmission['original_filename']); ?> <i class="fas fa-download fa-xs"></i>
                            </a>
                        </p>
                        <p><strong>Fecha de entrega:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($existingSubmission['submission_time']))); ?></p>
                        <?php if (isset($existingSubmission['student_comments']) && !empty(trim($existingSubmission['student_comments']))): ?>
                            <p><strong>Tus Comentarios:</strong><br><?php echo nl2br(htmlspecialchars($existingSubmission['student_comments'])); ?></p>
                        <?php endif; ?>
                        <hr style="margin: 0.75rem 0;">
                        <?php if (isset($existingSubmission['grade'])): ?>
                            <p><strong>Nota:</strong> <?php echo htmlspecialchars($existingSubmission['grade']); ?> / <?php echo htmlspecialchars($assignmentDetails['total_points'] ?: 'N/A'); ?></p>
                        <?php else: ?>
                            <p><strong>Nota:</strong> Pendiente de calificación.</p>
                        <?php endif; ?>
                        <?php if (!empty($existingSubmission['tutor_feedback'])): ?>
                            <p><strong>Feedback del Tutor:</strong><br><?php echo nl2br(htmlspecialchars($existingSubmission['tutor_feedback'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <form action="../Scripts/handle_submission_upload.php" method="POST" enctype="multipart/form-data" id="submission-form" style="margin-top:1.5rem;">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignmentId); ?>">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="existing_submission_id" value="<?php echo htmlspecialchars($existingSubmission['submission_id']); ?>">
                        
                        <h4><i class="fas fa-edit"></i> Editar Entrega</h4>
                        <div class="form-group">
                            <label for="submission_file_edit">Seleccionar nuevo archivo (reemplazará el anterior):</label>
                            <input type="file" name="submission_file" id="submission_file_edit" required>
                            <small>Tipos permitidos: PDF, DOC, DOCX, TXT, ZIP, etc. Max: <?php echo htmlspecialchars(MAX_FILE_SIZE_SUBMISSION_MB); ?>MB</small>
                        </div>
                        <div class="form-group">
                            <label for="submission_comments_edit">Comentarios adicionales (opcional):</label>
                            <textarea name="submission_comments" id="submission_comments_edit" rows="3"><?php echo htmlspecialchars($existingSubmission['student_comments'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn-submit btn-edit-submission">Actualizar Entrega</button>
                    </form>
                <?php else: ?>
                    <form action="../Scripts/handle_submission_upload.php" method="POST" enctype="multipart/form-data" id="submission-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignmentId); ?>">
                        <input type="hidden" name="action" value="new">
                        <h4><i class="fas fa-upload"></i> Realizar Entrega</h4>
                        <div class="form-group">
                            <label for="submission_file_new">Seleccionar archivo:</label>
                            <input type="file" name="submission_file" id="submission_file_new" required>
                            <small>Tipos permitidos: PDF, DOC, DOCX, TXT, ZIP, etc. Max: <?php echo htmlspecialchars(MAX_FILE_SIZE_SUBMISSION_MB); ?>MB</small>
                        </div>
                        <div class="form-group">
                            <label for="submission_comments_new">Comentarios (opcional):</label>
                            <textarea name="submission_comments" id="submission_comments_new" rows="3" placeholder="Añade comentarios para tu tutor aquí..."></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Enviar Tarea</button>
                    </form>
                <?php endif; ?>
            <?php elseif ($role === 'tutor'): ?>
                 <p class="message info-message">Como tutor, puedes ver las entregas de los alumnos desde la sección "Ver Entregas" de esta tarea, accesible desde la vista del curso.</p>
            <?php endif; ?>
        <?php else: ?>
            <p class="message error-message">No se pudo cargar la información de la tarea.</p>
        <?php endif; ?>
    </div>

    <script src="../resources/js/submission_form_ajax.js" defer></script>
</body>
</html>
