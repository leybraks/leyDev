<?php
// pages/realizar_examen.php

/**
 * Página para que el alumno realice un examen específico.
 * Muestra las preguntas y opciones, y envía las respuestas a un handler.
 */

// --- 1. Inicialización, Sesión, Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); } else { die("Error sesión"); }
}
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- 2. Seguridad: Login y Rol Alumno ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'alumno') {
    redirectWithError('intranet.php', 'login_error', 'Debes iniciar sesión como alumno para realizar exámenes.');
}
$studentId = $_SESSION['user_id'];

// --- 3. Obtener y Validar IDs de URL ---
$examId = filter_input(INPUT_GET, 'exam_id', FILTER_VALIDATE_INT);
$classIdForBackLink = filter_input(INPUT_GET, 'class_id', FILTER_VALIDATE_INT); // Para el enlace de "volver"
$pageError = null;
$examDetails = null;        // Para info general del examen (título, desc)
$questionsWithOptions = []; // Para las preguntas y sus opciones

// --- CSRF Token para el formulario del examen ---
if (empty($_SESSION['csrf_token'])) { $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); }
$csrf_token = $_SESSION['csrf_token'];

if (!$examId || $examId <= 0) {
    $pageError = "Identificador de examen no válido."; http_response_code(400);
} else {
    // --- 4. Cargar Datos y Verificar Autorización ---
    $pdo = Conexion::obtenerConexion();
    if (!$pdo) { $pageError = "Error de conexión."; http_response_code(500); }
    else {
        try {
            $examenPDO = new ExamenPDO();
            $cursoPDO = new CursoPDO(); // Para verificar inscripción y obtener datos de clase

            // Obtener detalles del examen (incluyendo lesson_id y class_id)
            $examDetails = $examenPDO->findExamById($examId); // findExamById ya hace JOIN con lessons

            if (!$examDetails) { $pageError = "Examen no encontrado."; http_response_code(404); }
            else {
                $classId = $examDetails['class_id']; // ID de la clase a la que pertenece este examen (vía lección)
                if (!$classIdForBackLink) { $classIdForBackLink = $classId; } // Asegurar que tenemos un classId para volver

                // Verificar si el alumno está inscrito en la clase de este examen
                if (!$cursoPDO->isStudentEnrolled($studentId, $classId)) {
                    $pageError = "No estás inscrito en la clase de este examen."; http_response_code(403);
                    $examDetails = null; // No mostrar nada si no está autorizado
                } else {
                    // Verificar si ya realizó este examen (y no se permiten reintentos)
                    $existingResult = $examenPDO->getExamResultByExamAndStudent($examId, $studentId);
                    if ($existingResult) {
                        $pageError = "Ya has realizado este examen. Nota: " . ($existingResult['score'] ?? 'Pendiente');
                        // Podrías redirigir o simplemente no mostrar el formulario
                        $examDetails = null; // No mostrar formulario
                    } else {
                        // Autorizado y no realizado, obtener preguntas
                        $questionsWithOptions = $examenPDO->getQuestionsWithOptionsByExamId($examId);
                        if ($questionsWithOptions === false) {
                            $pageError = "Error al cargar las preguntas del examen.";
                        } elseif (empty($questionsWithOptions)) {
                             $pageError = "Este examen aún no tiene preguntas definidas.";
                             // No mostrar formulario si no hay preguntas
                        }
                    }
                }
            }
        } catch (Exception $e) {
             $pageError = "Error al cargar el examen: " . $e->getMessage();
             error_log("Error cargando examen $examId para alumno $studentId: " . $e->getMessage());
             http_response_code(500);
        }
    }
}

$pageTitle = $examDetails ? 'Realizar Examen: ' . htmlspecialchars($examDetails['title']) : 'Realizar Examen';

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Intranet LEYdev</title>
    <link rel="stylesheet" href="../resources/css/intranet.css"> <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        body { background-color: var(--intranet-page-bg); color: var(--intranet-text-color); }
        .exam-container { max-width: 800px; margin: 2rem auto; padding: 2rem; background-color: var(--intranet-content-bg); border: 1px solid var(--intranet-border-color); border-radius: 8px; }
        .exam-header h1 { font-size: 1.8rem; margin-bottom: 0.5rem; } .exam-header p { margin-bottom: 1.5rem; }
        .question-block { margin-bottom: 1.5rem; padding-bottom: 1rem; border-bottom: 1px solid var(--intranet-border-color); }
        .question-block:last-child { border-bottom: none; margin-bottom: 0; }
        .question-text { font-weight: bold; margin-bottom: 0.75rem; display: block; }
        .options-list { list-style: none; padding-left: 0; }
        .options-list li { margin-bottom: 0.5rem; }
        .options-list input[type="radio"], .options-list input[type="checkbox"] { margin-right: 0.5rem; }
    </style>
</head>
<body class="<?php echo isset($_COOKIE['darkMode']) && $_COOKIE['darkMode'] === 'enabled' ? 'modo-oscuro' : ''; ?>">
    <div class="exam-container">
        <p class="back-link">
            <?php if ($classIdForBackLink): ?>
                <a href="../intranet/ver_curso.php?clase_id=<?php echo htmlspecialchars($classIdForBackLink); ?>"><i class="fas fa-arrow-left"></i> Volver a la Clase</a>
            <?php else: ?>
                <a href="../intranet/intranet.php"><i class="fas fa-arrow-left"></i> Volver al Panel</a>
            <?php endif; ?>
        </p>

        <h1><?php echo $pageTitle; ?></h1>
        <hr>

        <?php if ($pageError): // Error general o no autorizado o ya realizado ?>
            <p class="mensaje-error"><?php echo htmlspecialchars($pageError); ?></p>
        <?php elseif ($examDetails && !empty($questionsWithOptions)): // Mostrar formulario del examen ?>
            <div class="exam-header">
                <?php if(!empty($examDetails['description'])): ?>
                    <p><?php echo nl2br(htmlspecialchars($examDetails['description'])); ?></p>
                <?php endif; ?>
                 <p>Puntos totales: <?php echo htmlspecialchars($examDetails['total_points'] ?: 'N/A'); ?></p>
            </div>

            <form action="../Scripts/handle_exam_submit.php" method="POST" id="exam-form">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="exam_id" value="<?php echo htmlspecialchars($examId); ?>">
                <input type="hidden" name="class_id_for_redirect" value="<?php echo htmlspecialchars($classIdForBackLink); ?>">


                <?php foreach ($questionsWithOptions as $index => $question):
                    $questionId = htmlspecialchars($question['question_id']);
                    $questionText = htmlspecialchars($question['question_text']);
                    $options = $question['options']; // Ya es un array de opciones
                ?>
                    <div class="question-block">
                        <label class="question-text"><?php echo ($index + 1) . ". " . $questionText; ?> (<?php echo htmlspecialchars($question['points'] ?? 1); ?> pts)</label>
                        <?php if (!empty($options)): ?>
                            <ul class="options-list">
                                <?php foreach ($options as $option):
                                    $optionId = htmlspecialchars($option['option_id']);
                                    $optionText = htmlspecialchars($option['option_text']);
                                ?>
                                    <li>
                                        <input type="radio" name="answers[<?php echo $questionId; ?>]" id="option_<?php echo $optionId; ?>" value="<?php echo $optionId; ?>" required>
                                        <label for="option_<?php echo $optionId; ?>"><?php echo $optionText; ?></label>
                                    </li>
                                <?php endforeach; // Fin opciones ?>
                            </ul>
                        <?php else: ?>
                            <p><em>Esta pregunta no tiene opciones definidas.</em></p>
                        <?php endif; // Fin if opciones ?>
                    </div>
                <?php endforeach; // Fin preguntas ?>

                <button type="submit" class="btn-submit">Entregar Examen</button>
            </form>

        <?php elseif($examDetails && empty($questionsWithOptions)): // Examen existe pero no tiene preguntas ?>
             <p class="mensaje-info">Este examen no tiene preguntas configuradas todavía. Vuelve más tarde.</p>
        <?php endif; ?>

    </div>
    <script src="../resources/js/script.js"></script>
</body>
</html>