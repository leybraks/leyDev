<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php
    // intranet/crear_examen.php (Versión Dinámica para Múltiples Preguntas)

    /**
     * Página con formulario para crear un examen con múltiples preguntas de opción múltiple.
     * Utiliza JavaScript para añadir/quitar preguntas y opciones dinámicamente.
     */

    // --- 0. Initialize all template variables to safe defaults ---
    $pageTitle = "Crear Examen"; // Default title
    $lessonTitle = "Lección Desconocida"; // Default
    $pageError = null;
    $lessonDetails = null;
    $classDetails = null;
    $csrf_token = ''; // Will be set from session
    $formData = [];   // For repopulating form
    $formError = null;
    $lessonId = null; // Will be from GET
    $classId = null;  // Will be from GET

    // --- 1. Inicialización, Sesión, Carga ---
    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    require_once __DIR__ . '/../Config/config.php';
    require_once __DIR__ . '/../Includes/autoload.php';
    require_once __DIR__ . '/../Includes/functions.php';
    require_once __DIR__ . '/../PDO/Conexion.php';

    // --- 2. Seguridad: Login y Rol Tutor ---
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
        redirectWithError('../pages/intranet.php', 'login_error', 'Acceso no autorizado.');
    }
    $tutorId = $_SESSION['user_id'];

    // --- 3. Obtener IDs de URL y Validar ---
    $lessonIdGET = filter_input(INPUT_GET, 'lesson_id', FILTER_VALIDATE_INT);
    $classIdGET = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT);

    if ($lessonIdGET) $lessonId = $lessonIdGET;
    if ($classIdGET) $classId = $classIdGET;

    // --- 4. Generar Token CSRF ---
    if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
    $csrf_token = $_SESSION['csrf_token'];

    if (!$lessonId || !$classId) {
        $pageError = "Faltan identificadores de lección o clase en la URL.";
    } else {
        // --- 5. Verificar Permisos ---
        $pdo = Conexion::obtenerConexion();
        if (!$pdo) {
            $pageError = "Error de conexión a la base de datos.";
        } else {
            try { // Verificar que el tutor es dueño de la clase de la lección
                $cursoPDO = new CursoPDO();
                $lessonDetails = $cursoPDO->findLessonById($lessonId);
                if (!$lessonDetails || $lessonDetails['class_id'] != $classId) {
                    $pageError = "Lección o Clase no válida. Verifique los IDs proporcionados.";
                    $lessonDetails = null; // Asegurar que no se usen datos incorrectos
                } else {
                    $classDetails = $cursoPDO->findClassById($classId);
                    if (!$classDetails || $classDetails['tutor_id'] != $tutorId) {
                        $pageError = "No tienes permiso para crear un examen para esta clase/lección.";
                        $lessonDetails = null; // Invalidar si no hay permiso
                        $classDetails = null;
                    }
                }
            } catch (Exception $e) {
                $pageError = "Error al verificar datos de la lección/clase.";
                error_log("Exception in crear_examen.php permission check: " . $e->getMessage()); // Corregido error_log
            }
        }
    }

    // --- Preparar Título y mensajes de error del Handler ---
    $formData = $_SESSION['exam_form_data'] ?? []; unset($_SESSION['exam_form_data']);
    $formError = $_SESSION['exam_error'] ?? null; unset($_SESSION['exam_error']);

    // Re-calculate $lessonTitle and $pageTitle based on potentially updated $lessonDetails, $classDetails
    if ($lessonDetails && isset($lessonDetails['title'])) {
        $lessonTitle = htmlspecialchars($lessonDetails['title']);
    } elseif ($lessonId && !$pageError) { // Si tenemos ID y no hubo error mayor
        $lessonTitle = "Lección ID: " . htmlspecialchars($lessonId);
    } else {
        $lessonTitle = "Lección Inválida o No Accesible";
    }

    if ($lessonDetails && $classDetails) {
        $pageTitle = "Crear Examen para Lección: " . $lessonTitle;
    } elseif ($pageError) {
        $pageTitle = "Error al Crear Examen";
    }
    // $pageTitle ya tiene un default "Crear Examen" si ninguna condición se cumple.

    ?>
    <title><?php echo $pageTitle; ?> - Intranet LEYdev</title>
    <link rel="stylesheet" href="../resources/css/intranet.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ... (tus estilos existentes de crear_examen.php) ... */
        body { background-color: var(--intranet-page-bg); color: var(--intranet-text-color); padding-top: 1rem; }
        .form-container { max-width: 850px; margin: 1rem auto; padding: 2rem; background-color: var(--intranet-content-bg); border: 1px solid var(--intranet-border-color); border-radius: 8px; }
        .form-container h1 { font-size: 1.6rem; margin-bottom: 0.5rem; } .form-container .context-info { font-size: 0.9rem; color: #6c757d; margin-bottom: 1.5rem; }
        .form-section { border: 1px solid #eee; padding: 1.5rem; margin-bottom: 1.5rem; border-radius: 5px; background-color: color-mix(in srgb, var(--intranet-page-bg) 50%, var(--intranet-content-bg)); }
        .form-section h4 { margin-top: 0; margin-bottom: 1rem; font-size: 1.1rem; border-bottom: 1px solid #eee; padding-bottom: 0.5rem;}
        .form-group { margin-bottom: 1rem; } label { display: block; margin-bottom: 0.3rem; font-weight: 500; }
        input[type="text"], input[type="number"], textarea { width: 100%; padding: 0.6rem 0.8rem; border: 1px solid var(--intranet-input-border); border-radius: 4px; }
        textarea { min-height: 60px; resize: vertical; }
        .options-container { margin-top: 1rem; padding-left: 1rem; border-left: 3px solid var(--intranet-accent); }
        .option-group { display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem; }
        .option-group input[type="radio"] { flex-shrink: 0; width: auto; margin-top:0; }
        .option-group input[type="text"] { flex-grow: 1; }
        .option-group label { margin-bottom: 0; font-weight: normal; flex-grow: 1; }
        .option-group .remove-option-btn { width: auto; padding: 0.1rem 0.4rem; font-size: 0.7rem; background-color: #dc3545; color: white; border: none; border-radius: 3px; cursor: pointer; flex-shrink: 0; }
        .question-actions { margin-top: 1rem; text-align: right; }
        .btn-remove, .btn-add-option { padding: 0.2rem 0.5rem; font-size: 0.8rem; border-radius: 3px; cursor: pointer; border: none; margin-left: 0.5rem; }
        .btn-remove { background-color: #6c757d; color: white; }
        .btn-add-option { background-color: #0d6efd; color: white; }
        #add-question-btn { margin-top: 1rem; }
        body.modo-oscuro .form-section { border-color: var(--intranet-border-color); background-color: color-mix(in srgb, var(--intranet-page-bg) 50%, var(--intranet-content-bg)); }
        body.modo-oscuro .options-container { border-left-color: var(--intranet-accent); }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; border: 1px solid transparent; }
        .success-message { color: var(--intranet-success-text); background-color: var(--intranet-success-bg); border-color: var(--intranet-success-border); }
        .error-message { color: var(--intranet-error-text); background-color: var(--intranet-error-bg); border-color: var(--intranet-error-border); }
        .error-message ul { margin-top: 5px; padding-left: 20px; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled' ? 'modo-oscuro' : ''; ?>">

    <div class="form-container">
        <p class="back-link">
            <a href="ver_curso.php?clase_id=<?php echo htmlspecialchars($classId ?: ''); ?>"><i class="fas fa-arrow-left"></i> Volver a Lección '<?php echo $lessonTitle; // Ya está escapado si viene de $lessonDetails['title'] ?>'</a>
        </p>

        <h1><?php echo $pageTitle; // Ya está escapado si viene de $lessonTitle ?></h1>

        <?php if ($pageError): ?>
            <p class="message error-message"><?php echo htmlspecialchars($pageError); ?></p>
        <?php elseif ($lessonDetails && $classDetails): ?>
            <p class="context-info">Estás creando un examen para la clase "<?php echo htmlspecialchars($classDetails['course_title'] ?? ''); ?>".</p>
            
            <div id="exam-creation-ajax-message" class="message" style="display:none; margin-bottom: 15px;"></div>

            <?php if ($formError): ?><p class="message error-message"><?php echo nl2br(htmlspecialchars($formError)); ?></p><?php endif; ?>
            

            <form action="../Scripts/handle_exam_create.php" method="POST" id="create-exam-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="lesson_id" value="<?php echo htmlspecialchars($lessonId); ?>">
                <input type="hidden" name="class_id" value="<?php echo htmlspecialchars($classId); ?>">

                <div class="form-section">
                    <h4>Detalles Generales del Examen</h4>
                    <div class="form-group">
                        <label for="exam_title">Título del Examen:</label>
                        <input type="text" id="exam_title" name="exam_title" required value="<?php echo htmlspecialchars($formData['exam_title'] ?? ''); ?>">
                    </div>
                    <div class="form-group">
                        <label for="exam_description">Descripción / Instrucciones (Opcional):</label>
                        <textarea id="exam_description" name="exam_description" rows="3"><?php echo htmlspecialchars($formData['exam_description'] ?? ''); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label for="exam_points">Puntaje Máximo Total (Opcional, informativo):</label>
                        <input type="number" id="exam_points" name="exam_total_points" min="0" step="1" value="<?php echo htmlspecialchars($formData['exam_total_points'] ?? ''); ?>">
                    </div>
                </div>

                <div id="questions-container">
                    <h4>Preguntas del Examen</h4>
                    </div>

                <button type="button" id="add-question-btn" class="btn-add-option" style="margin-bottom:1rem;">
                    <i class="fas fa-plus"></i> Añadir Pregunta
                </button>

                <hr style="margin: 2rem 0;">
                <button type="submit" class="btn-submit">Guardar Examen Completo</button>
            </form>
        <?php else: // Si $lessonDetails o $classDetails son null pero no hubo $pageError (ej. IDs no pasados) ?>
            <p class="message error-message">No se pudieron cargar los detalles necesarios para crear el examen. Asegúrate de que los IDs de lección y clase sean correctos.</p>
        <?php endif; ?>
    </div>

    <template id="question-template">
        <div class="form-section question-block">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h5 style="margin: 0; font-size: 1rem;">Pregunta <span class="question-number"></span></h5>
                <button type="button" class="btn-remove remove-question-btn" title="Eliminar esta pregunta">&times; Eliminar Pregunta</button>
            </div>
            <div class="form-group">
                <label for="q_text_Q_IDX">Texto de la Pregunta:</label>
                <textarea id="q_text_Q_IDX" name="question_text[Q_IDX]" rows="2" required></textarea>
            </div>
            <div class="form-group" style="max-width: 150px;">
                <label for="q_points_Q_IDX">Puntos Pregunta:</label>
                <input type="number" id="q_points_Q_IDX" name="question_points[Q_IDX]" min="0" step="1" value="1" required>
            </div>
            <div class="options-container">
                <label>Opciones de Respuesta (Marca la correcta):</label>
            </div>
            <button type="button" class="btn-add-option add-option-btn" style="margin-top: 0.5rem;">
                <i class="fas fa-plus"></i> Añadir Opción
            </button>
        </div>
    </template>

    <template id="option-template">
        <div class="option-group">
            <input type="radio" name="correct_option[Q_IDX]" value="OPT_IDX" required id="correct_qQ_IDX_optOPT_IDX">
            <label for="option_text_qQ_IDX_optOPT_IDX" style="flex-grow: 1; margin-left: 5px; margin-right: 5px;">
                <input type="text" name="option_text[Q_IDX][]" id="option_text_qQ_IDX_optOPT_IDX" placeholder="Texto opción" required>
            </label>
            <label for="correct_qQ_IDX_optOPT_IDX" class="radio-label" style="flex-shrink: 0; width:auto; cursor:pointer;">(Correcta)</label>
            <button type="button" class="remove-option-btn" title="Eliminar esta opción">&times;</button>
        </div>
    </template>

    <script src="../resources/js/create_exam_ajax.js"></script>
    <script src="../resources/js/script.js"></script>
</body>
</html>
