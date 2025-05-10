<?php
// intranet/calificar_entrega.php

// --- 1. Inicialización, Sesión, Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error sesión"); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- 2. Seguridad: Login y Rol Tutor ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    redirectWithError(LOGIN_URL ?? '../pages/intranet.php', 'login_error', 'Acceso no autorizado.');
}
$tutorId = $_SESSION['user_id'];

// --- 3. Obtener y Validar ID de Entrega desde URL ---
$submissionId = filter_input(INPUT_GET, 'submission_id', FILTER_VALIDATE_INT);
$pageError = null;
$submissionDetails = null;
$assignmentDetails = null;
$classDetails = null;

// --- CSRF Token (para el formulario de calificación) ---
if (empty($_SESSION['csrf_token_grading'])) { // Usar token específico
    $_SESSION['csrf_token_grading'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_grading'];

if (!$submissionId || $submissionId <= 0) {
    $pageError = "Identificador de entrega no válido.";
} else {
    // --- 4. Cargar Datos y Verificar Autorización ---
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) {
        $pageError = "Error de conexión a la base de datos.";
    } else {
        try {
            $entregaPDO = new EntregaPDO();
            $tareaPDO = new TareaPDO();
            $cursoPDO = new CursoPDO();

            $submissionDetails = $entregaPDO->findSubmissionById($submissionId);

            if (!$submissionDetails) {
                $pageError = "Entrega no encontrada.";
            } else {
                $assignmentDetails = $tareaPDO->findAssignmentById($submissionDetails['assignment_id']);
                if (!$assignmentDetails) {
                    $pageError = "Tarea asociada no encontrada."; $submissionDetails = null;
                } else {
                    $lessonDetails = $cursoPDO->findLessonById($assignmentDetails['lesson_id']);
                    if (!$lessonDetails) {
                        $pageError = "Lección asociada no encontrada."; $submissionDetails = null;
                    } else {
                        $classDetails = $cursoPDO->findClassById($lessonDetails['class_id']);
                        if (!$classDetails || $classDetails['tutor_id'] != $tutorId) {
                            $pageError = "No tienes permiso para calificar esta entrega.";
                            $submissionDetails = null;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $pageError = "Error al cargar datos de la entrega: " . $e->getMessage();
            error_log("Error cargando datos para calificar entrega $submissionId: " . $e->getMessage());
        }
    }
}

$pageTitle = ($submissionDetails && $assignmentDetails) ? 'Calificar Entrega: ' . htmlspecialchars($assignmentDetails['title'] ?? '') : 'Calificar Entrega';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Intranet LEYdev</title>
    <link rel="stylesheet" href="../resources/css/intranet.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* Estilos que me proporcionaste anteriormente para esta página */
        body { background-color: var(--intranet-page-bg); color: var(--intranet-text-color); font-family: "Poppins", sans-serif; }
        .grading-container { max-width: 1200px; margin: 1rem auto; padding: 1.5rem; background-color: var(--intranet-content-bg); border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .back-link { margin-bottom: 1rem; display: inline-block; color: var(--intranet-accent); text-decoration: none; }
        .back-link:hover { text-decoration: underline; }
        .grading-layout { display: flex; flex-wrap: wrap; gap: 1.5rem; }
        .document-viewer { flex: 2; min-width: 300px; background-color: var(--intranet-content-bg); border: 1px solid var(--intranet-border-color); border-radius: 6px; padding: 1rem; min-height: 500px; }
        .document-viewer embed, .document-viewer iframe { width: 100%; height: 500px; border: none; }
        .grading-form-panel { flex: 1; min-width: 280px; background-color: var(--intranet-content-bg); border: 1px solid var(--intranet-border-color); border-radius: 6px; padding: 1.5rem; }
        .grading-form-panel h1, .grading-form-panel h2, .grading-form-panel h3 {color: var(--intranet-heading-color); margin-top:0;}
        .grading-form-panel .student-info { font-size: 0.9rem; margin-bottom: 0.75rem; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; font-weight: 500; margin-bottom: 0.3rem; color: var(--intranet-text-color); }
        input[type="number"], textarea { width: 100%; padding: 0.6rem; border: 1px solid var(--intranet-input-border); border-radius: 4px; background-color: var(--intranet-input-bg); color: var(--intranet-input-text); box-sizing: border-box; }
        textarea { min-height: 100px; resize: vertical; }
        .btn-submit {
            background-color: var(--intranet-btn-primary-bg); color: var(--intranet-btn-primary-text);
            padding: 0.7rem 1.2rem; border:none; border-radius: 5px; cursor: pointer; font-weight: 500;
        }
        .btn-submit:hover { background-color: var(--intranet-btn-primary-hover-bg); }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid transparent; }
        .success-message { color: var(--intranet-success-text); background-color: var(--intranet-success-bg); border-color: var(--intranet-success-border); }
        .error-message { color: var(--intranet-error-text); background-color: var(--intranet-error-bg); border-color: var(--intranet-error-border); }
    </style>
</head>
<body class="modo-oscuro"> <?php // Aplicar siempre la clase modo-oscuro para esta página ?>

    <div class="grading-container">
        <?php if ($assignmentDetails && $classDetails): ?>
            <p class="back-link">
                <a href="ver_entregas.php?assignment_id=<?php echo htmlspecialchars($assignmentDetails['assignment_id']); ?>"><i class="fas fa-arrow-left"></i> Volver a Entregas de '<?php echo htmlspecialchars($assignmentDetails['title']); ?>'</a>
            </p>
        <?php else: ?>
            <p class="back-link"><a href="intranet.php"><i class="fas fa-arrow-left"></i> Volver al Panel</a></p>
        <?php endif; ?>

        <h1><?php echo $pageTitle; ?></h1>
        <hr style="margin-bottom: 1.5rem;">

        <?php
            if (isset($_SESSION['grading_success'])): ?>
            <p class="message success-message"><?php echo htmlspecialchars($_SESSION['grading_success']); ?></p>
            <?php unset($_SESSION['grading_success']); ?>
        <?php endif; ?>
        <?php if (isset($_SESSION['grading_error'])): ?>
            <p class="message error-message"><?php echo htmlspecialchars($_SESSION['grading_error']); ?></p>
            <?php unset($_SESSION['grading_error']); ?>
        <?php endif; ?>

        <?php if ($pageError): ?>
            <p class="message error-message"><?php echo htmlspecialchars($pageError); ?></p>
        <?php elseif ($submissionDetails && $assignmentDetails):
            $filePath = '../' . htmlspecialchars($submissionDetails['stored_filepath']);
            $fileExtension = strtolower(pathinfo($submissionDetails['original_filename'], PATHINFO_EXTENSION));
            $isPdf = ($fileExtension === 'pdf');
        ?>
            <div class="grading-layout">
                <div class="document-viewer">
                    <h3>Documento Entregado: <?php echo htmlspecialchars($submissionDetails['original_filename']); ?></h3>
                    <?php if ($isPdf && file_exists($filePath)): ?>
                        <embed src="<?php echo $filePath; ?>" type="application/pdf" width="100%" height="600px" />
                        <p><small>Si no ves el PDF, puedes <a href="<?php echo $filePath; ?>" target="_blank" download>descargarlo aquí</a>.</small></p>
                    <?php elseif (file_exists($filePath)): ?>
                        <p>Este tipo de archivo (<?php echo strtoupper($fileExtension); ?>) no se puede mostrar directamente.</p>
                        <a href="<?php echo $filePath; ?>" class="btn-submit" download style="display:inline-block; text-decoration:none;">Descargar Archivo</a>
                    <?php else: ?>
                        <p class="message error-message">El archivo de la entrega no fue encontrado en el servidor.</p>
                    <?php endif; ?>
                </div>

                <div class="grading-form-panel">
                    <h2>Calificar Entrega</h2>
                    <div class="student-info">
                        <strong>Alumno:</strong> <?php echo htmlspecialchars($submissionDetails['student_username'] ?? 'N/A'); ?><br>
                        <strong>Tarea:</strong> <?php echo htmlspecialchars($assignmentDetails['title']); ?><br>
                        <strong>Entregado:</strong> <?php echo htmlspecialchars(date('d/m/Y H:i', strtotime($submissionDetails['submission_time']))); ?>
                    </div>
                    <hr>
                    
                    <form action="../Scripts/handle_grading.php" method="POST" id="grading-form">
                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                        <input type="hidden" name="submission_id" value="<?php echo htmlspecialchars($submissionDetails['submission_id']); ?>">
                        <input type="hidden" name="assignment_id" value="<?php echo htmlspecialchars($assignmentDetails['assignment_id']); ?>">

                        <div id="grading-ajax-message" class="message" style="display:none; margin-bottom: 15px;"></div>

                        <div class="form-group">
                            <label for="grade">Nota (Puntos máx: <?php echo htmlspecialchars($assignmentDetails['total_points'] ?: 'N/A'); ?>):</label>
                            <input type="number" name="grade" id="grade" class="form-control"
                                   value="<?php echo htmlspecialchars($submissionDetails['grade'] ?? ''); ?>"
                                   <?php if(isset($assignmentDetails['total_points']) && is_numeric($assignmentDetails['total_points'])) echo 'max="' . htmlspecialchars($assignmentDetails['total_points']) . '"'; ?> min="0" step="0.1" placeholder="Ej: 15.5">
                        </div>
                        <div class="form-group">
                            <label for="tutor_feedback">Comentarios / Feedback:</label>
                            <textarea name="tutor_feedback" id="tutor_feedback" class="form-control" rows="6" placeholder="Escribe tus comentarios aquí..."><?php echo htmlspecialchars($submissionDetails['tutor_feedback'] ?? ''); ?></textarea>
                        </div>
                        <button type="submit" class="btn-submit">Guardar Calificación</button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <p class="message error-message">No se pudieron cargar los detalles necesarios para la calificación.</p>
        <?php endif; ?>
    </div>

    <script src="../resources/js/calificar_entrega_ajax.js" defer></script>
</body>
</html>
