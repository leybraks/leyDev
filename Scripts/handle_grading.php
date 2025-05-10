<?php
// Scripts/handle_grading.php (AJAX Refinado con CSRF Corregido)

// 1. Inicialización, Sesión, Carga
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        http_response_code(500);
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'message' => 'Error fatal: Sesión no pudo iniciarse (headers sent).']);
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

$response = [
    'success' => false,
    'message' => 'No se pudo procesar la calificación.',
    'updated_grade_info' => null
];

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado para calificar.';
    echo json_encode($response);
    exit;
}
$tutorId = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 4. Validar el token CSRF
// Asegurarse que coincida con el token generado en calificar_entrega.php
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_grading']) || !hash_equals($_SESSION['csrf_token_grading'], $_POST['csrf_token'])) {
    error_log("Error CSRF detectado en intento de calificación por tutor ID: $tutorId. Token sesión: " . ($_SESSION['csrf_token_grading'] ?? 'NO SET') . ", Token POST: " . ($_POST['csrf_token'] ?? 'NO SET'));
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Recarga la página e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// Es una buena práctica invalidar el token después de su uso si es de un solo uso por acción.
// unset($_SESSION['csrf_token_grading']);


$submissionId = filter_input(INPUT_POST, 'submission_id', FILTER_VALIDATE_INT);
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$gradeInput = $_POST['grade'] ?? null;
$parsedGrade = null;
$validationErrorMessages = [];

if ($gradeInput !== null && $gradeInput !== '') {
    if (!is_numeric($gradeInput)) {
        $validationErrorMessages[] = "La nota debe ser un valor numérico.";
    } else {
        $parsedGrade = filter_var($gradeInput, FILTER_VALIDATE_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        if ($parsedGrade === false) {
            $validationErrorMessages[] = "La nota tiene un formato numérico inválido.";
        } elseif ($parsedGrade < 0) {
            $validationErrorMessages[] = "La nota no puede ser negativa.";
        } else {
            if ($assignmentId) {
                try {
                    $tareaPDO_validation = new TareaPDO();
                    $assignmentDetails_validation = $tareaPDO_validation->findAssignmentById($assignmentId);
                    if ($assignmentDetails_validation && isset($assignmentDetails_validation['total_points']) && $assignmentDetails_validation['total_points'] !== null) {
                        if ($parsedGrade > (float)$assignmentDetails_validation['total_points']) {
                            $validationErrorMessages[] = "La nota no puede exceder los puntos totales (" . htmlspecialchars($assignmentDetails_validation['total_points']) . ").";
                        }
                    }
                } catch (Exception $e_val) {
                    error_log("Error al validar total_points para assignment $assignmentId: " . $e_val->getMessage());
                }
            }
        }
    }
}

$tutorFeedback = trim($_POST['tutor_feedback'] ?? '');

if (!$submissionId || $submissionId <= 0) {
    $validationErrorMessages[] = 'ID de entrega no válido.';
}

if (!empty($validationErrorMessages)) {
    http_response_code(400);
    $response['message'] = implode(' ', $validationErrorMessages);
    $response['errors'] = $validationErrorMessages;
    echo json_encode($response);
    exit;
}

$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        throw new Exception("Error de conexión a la base de datos.", 500);
    }

    $entregaPDO = new EntregaPDO();
    $cursoPDO = new CursoPDO();
    $tareaPDO = new TareaPDO();

    $submissionDetails = $entregaPDO->findSubmissionById($submissionId);
    if (!$submissionDetails) { throw new Exception("Entrega no encontrada.", 404); }
    if ($assignmentId && $submissionDetails['assignment_id'] != $assignmentId) {
         throw new Exception("Inconsistencia de datos: ID de tarea no coincide.", 400);
    }
    $actualAssignmentId = $submissionDetails['assignment_id'];

    $assignmentForAuth = $tareaPDO->findAssignmentById($actualAssignmentId);
    if (!$assignmentForAuth) { throw new Exception("Tarea asociada no encontrada.", 404); }
    $lessonForAuth = $cursoPDO->findLessonById($assignmentForAuth['lesson_id']);
    if (!$lessonForAuth) { throw new Exception("Lección asociada no encontrada.", 404); }
    $classForAuth = $cursoPDO->findClassById($lessonForAuth['class_id']);
    if (!$classForAuth || $classForAuth['tutor_id'] != $tutorId) {
        throw new Exception("No tienes permiso para calificar esta entrega.", 403);
    }

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    $gradingSuccess = $entregaPDO->updateGradeAndFeedback(
        $submissionId,
        $parsedGrade,
        ($tutorFeedback === '' ? null : $tutorFeedback)
    );

    if ($gradingSuccess) {
        if ($pdo->inTransaction()) { $pdo->commit(); }
        $response['success'] = true;
        $response['message'] = '¡Calificación guardada exitosamente!';
        $response['updated_grade_info'] = [
            'grade' => $parsedGrade,
            'tutor_feedback' => $tutorFeedback,
            'graded_at' => date('Y-m-d H:i:s')
        ];
    } else {
        throw new Exception("No se pudo guardar la calificación en la base de datos (método devolvió false).", 500);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en handle_grading.php para submission $submissionId: (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = $e->getMessage();
    $httpCode = $e->getCode();
    if (is_int($httpCode) && $httpCode >= 400 && $httpCode < 600) {
        http_response_code($httpCode);
    } else {
        http_response_code(500);
    }
}

echo json_encode($response);
exit;
?>
