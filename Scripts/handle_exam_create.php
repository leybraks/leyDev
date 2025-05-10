<?php
// Scripts/handle_exam_create.php (Versión AJAX)

// --- Inicialización y Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        // No se puede establecer Content-Type si las cabeceras ya se enviaron.
        http_response_code(500);
        // Intentar enviar JSON de todas formas, aunque podría no funcionar como se espera.
        echo json_encode(['success' => false, 'message' => 'Error fatal: Sesión no pudo iniciarse (headers sent).']);
        exit;
    }
}

// Establecer la cabecera para la respuesta JSON ANTES de cualquier otra salida.
if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php'; // Para ExamenPDO, CursoPDO
// require_once __DIR__ . '/../Includes/functions.php'; // redirectWithError/Success no se usarán
require_once __DIR__ . '/../PDO/Conexion.php';

// Array base para la respuesta JSON
$response = [
    'success' => false,
    'message' => 'No se pudo procesar la solicitud de creación del examen.',
    'errors' => [], // Para errores de validación específicos
    'exam_id' => null, // ID del examen creado en caso de éxito
    'redirect_url' => null // URL para redirigir después del éxito (opcional)
];

// --- Seguridad: Login y Rol Tutor ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(403); // Forbidden
    $response['message'] = 'Acceso no autorizado para crear exámenes.';
    echo json_encode($response);
    exit;
}
$tutorId = $_SESSION['user_id'];

// --- Verificar Método POST ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// --- Obtener Datos Principales del Formulario ---
$lessonId = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
$classIdForContext = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT); // Para autorización
$examTitle = trim($_POST['exam_title'] ?? '');
$examDescription = trim($_POST['exam_description'] ?? '');
$examTotalPoints = filter_input(INPUT_POST, 'exam_total_points', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
if ($examTotalPoints === false || $examTotalPoints === null) { $examTotalPoints = null; } // Permitir null si no se especifica
$csrfToken = $_POST['csrf_token'] ?? '';

// Datos de Preguntas y Opciones (llegan como arrays)
$questionTexts = $_POST['question_text'] ?? [];
$questionPoints = $_POST['question_points'] ?? [];
$optionTexts = $_POST['option_text'] ?? [];
$correctOptions = $_POST['correct_option'] ?? [];

// --- Validar Token CSRF ---
// Asumimos que el token CSRF en crear_examen.php se guarda en $_SESSION['csrf_token']
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Por favor, recarga la página e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// Considera invalidar el token después de su uso si es de un solo uso.
// unset($_SESSION['csrf_token']);

// --- Validaciones de Datos ---
$validationErrors = [];
if (!$lessonId) { $validationErrors['lesson_id'] = "ID de lección no válido."; }
if (empty($examTitle)) { $validationErrors['exam_title'] = "El título del examen es obligatorio."; }
if (empty($questionTexts) || !is_array($questionTexts)) {
    $validationErrors['questions'] = "Debes añadir al menos una pregunta.";
} else {
    foreach ($questionTexts as $qIndex => $qText) {
        if (empty(trim($qText))) { $validationErrors["question_text_{$qIndex}"] = "El texto de la pregunta " . ($qIndex + 1) . " no puede estar vacío."; }
        if (!isset($optionTexts[$qIndex]) || !is_array($optionTexts[$qIndex]) || count($optionTexts[$qIndex]) < 2) {
            $validationErrors["options_{$qIndex}"] = "La pregunta " . ($qIndex + 1) . " debe tener al menos dos opciones.";
        } else {
            foreach($optionTexts[$qIndex] as $optIndex => $optText) {
                if (empty(trim($optText))) { $validationErrors["option_text_{$qIndex}_{$optIndex}"] = "El texto de la opción " . ($optIndex + 1) . " en la pregunta " . ($qIndex + 1) . " no puede estar vacío."; }
            }
        }
        if (!isset($correctOptions[$qIndex]) || $correctOptions[$qIndex] === '' || !is_numeric($correctOptions[$qIndex])) {
            $validationErrors["correct_option_{$qIndex}"] = "Debes marcar una opción correcta para la pregunta " . ($qIndex + 1) . ".";
        } elseif (!isset($optionTexts[$qIndex][(int)$correctOptions[$qIndex]])) {
            $validationErrors["correct_option_value_{$qIndex}"] = "La opción marcada como correcta para la pregunta " . ($qIndex + 1) . " no es válida.";
        }
        if (!isset($questionPoints[$qIndex]) || !is_numeric($questionPoints[$qIndex]) || (int)$questionPoints[$qIndex] < 0) {
            $validationErrors["question_points_{$qIndex}"] = "Los puntos para la pregunta " . ($qIndex + 1) . " deben ser un número positivo o cero.";
        }
    }
}

if (!empty($validationErrors)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Por favor, corrige los errores en el formulario.';
    $response['errors'] = $validationErrors;
    echo json_encode($response);
    exit;
}

// --- Lógica de Creación ---
$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        throw new Exception("Error de conexión a la base de datos.", 500);
    }

    $cursoPDO = new CursoPDO();
    $examenPDO = new ExamenPDO();

    // Autorización: Verificar que el tutor es dueño de la clase de esta lección
    $lessonDetails = $cursoPDO->findLessonById($lessonId);
    if (!$lessonDetails || ($classIdForContext && $lessonDetails['class_id'] != $classIdForContext)) {
        throw new Exception("Lección no válida o no pertenece a la clase especificada.", 404);
    }
    $classDetails = $cursoPDO->findClassById($lessonDetails['class_id']); // Usar class_id de la lección para seguridad
    if (!$classDetails || $classDetails['tutor_id'] != $tutorId) {
        throw new Exception("No tienes permiso para crear un examen en esta lección/clase.", 403);
    }

    $pdo->beginTransaction();

    // 1. Crear el Examen principal
    $examId = $examenPDO->createExam($lessonId, $examTitle, $examDescription ?: null, $examTotalPoints);
    if (!$examId) {
        throw new Exception("Error al crear el registro principal del examen en la base de datos.");
    }

    // 2. Iterar y crear Preguntas y Opciones
    foreach ($questionTexts as $qIndex => $qText) {
        $qPoints = (int)($questionPoints[$qIndex] ?? 1);
        $questionId = $examenPDO->createQuestion($examId, trim($qText), 'multiple_choice', $qPoints, $qIndex);
        if (!$questionId) {
            throw new Exception("Error al guardar la pregunta " . ($qIndex + 1) . ".");
        }

        $correctOptionIndexThisQuestion = (int)($correctOptions[$qIndex] ?? -1);

        if (isset($optionTexts[$qIndex]) && is_array($optionTexts[$qIndex])) {
            foreach ($optionTexts[$qIndex] as $optIndex => $optText) {
                $isCorrect = ($optIndex == $correctOptionIndexThisQuestion) ? 1 : 0;
                $optionId = $examenPDO->createOption($questionId, trim($optText), $isCorrect, $optIndex);
                if (!$optionId) {
                    throw new Exception("Error al guardar la opción " . ($optIndex + 1) . " para la pregunta " . ($qIndex + 1) . ".");
                }
            }
        }
    }

    $pdo->commit();
    $response['success'] = true;
    $response['message'] = 'Examen "' . htmlspecialchars($examTitle) . '" creado exitosamente con sus preguntas.';
    $response['exam_id'] = $examId;
    // URL para volver a la vista del curso/clase donde se añadió el examen
    $response['redirect_url'] = BASE_URL . "/intranet/ver_curso.php?clase_id=" . htmlspecialchars($classDetails['class_id']);

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al crear examen completo para lesson $lessonId (AJAX): (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = "Error al crear el examen: " . $e->getMessage();
    $httpCode = $e->getCode();
    if (is_int($httpCode) && $httpCode >= 400 && $httpCode < 600) {
        http_response_code($httpCode);
    } else {
        http_response_code(500); // Error genérico del servidor
    }
}

echo json_encode($response);
exit;
?>
