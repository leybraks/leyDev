<?php
// Scripts/handle_assignment_create.php (Versión AJAX con CSRF Corregido)

// --- Inicialización y Carga ---
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
    'message' => 'No se pudo procesar la solicitud de creación de tarea.',
    'errors' => [],
    'assignment_id' => null,
    'redirect_url' => null
];

// --- Seguridad: Login y Rol Tutor ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado para crear tareas.';
    echo json_encode($response);
    exit;
}
$tutorId = $_SESSION['user_id'];

// --- Verificar Método POST ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// --- Obtener Datos Principales del Formulario ---
$lessonId = filter_input(INPUT_POST, 'lesson_id', FILTER_VALIDATE_INT);
$classIdForContext = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$title = trim($_POST['title'] ?? '');
$description = trim($_POST['description'] ?? '');
$dueDate = $_POST['due_date'] ?? null;
$totalPoints = filter_input(INPUT_POST, 'total_points', FILTER_VALIDATE_INT, ["options" => ["min_range" => 0]]);
if ($totalPoints === false || $totalPoints === null) { $totalPoints = null; }
$csrfToken = $_POST['csrf_token'] ?? '';

// --- Validar Token CSRF ---
// CORRECCIÓN: Usar el nombre de sesión del token específico del formulario de creación de tareas
if (empty($csrfToken) || !isset($_SESSION['csrf_token_assignment_create']) || !hash_equals($_SESSION['csrf_token_assignment_create'], $csrfToken)) {
    error_log("Error CSRF en handle_assignment_create.php. Sesión: " . ($_SESSION['csrf_token_assignment_create'] ?? 'NO SET') . ", POST: " . $csrfToken);
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Por favor, recarga la página e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// Opcional: Invalidar el token después de su uso si es de un solo uso.
// unset($_SESSION['csrf_token_assignment_create']);

// --- Validaciones de Datos ---
$validationErrors = [];
if (!$lessonId) { $validationErrors['lesson_id'] = "ID de lección no válido."; }
if (empty($title)) { $validationErrors['title'] = "El título de la tarea es obligatorio."; }
if (strlen($title) > 255) { $validationErrors['title'] = "El título no puede exceder los 255 caracteres."; }
if (!empty($dueDate)) {
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $dueDate);
    if (!$d || $d->format('Y-m-d\TH:i') !== $dueDate) {
        $validationErrors['due_date'] = "El formato de la fecha límite no es válido (AAAA-MM-DDTHH:MM).";
    }
} else {
    $dueDate = null;
}
if ($totalPoints !== null && $totalPoints < 0) {
    $validationErrors['total_points'] = "Los puntos deben ser un número positivo o cero.";
}

if (!empty($validationErrors)) {
    http_response_code(400);
    $response['message'] = 'Por favor, corrige los errores en el formulario.';
    $response['errors'] = $validationErrors;
    echo json_encode($response);
    exit;
}

// --- Lógica de Creación de Tarea ---
$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        throw new Exception("Error de conexión a la base de datos.", 500);
    }

    $cursoPDO = new CursoPDO();
    $tareaPDO = new TareaPDO();

    $lessonDetails = $cursoPDO->findLessonById($lessonId);
    if (!$lessonDetails || ($classIdForContext && $lessonDetails['class_id'] != $classIdForContext)) {
        throw new Exception("Lección no válida o no pertenece a la clase especificada.", 404);
    }
    $classDetails = $cursoPDO->findClassById($lessonDetails['class_id']);
    if (!$classDetails || $classDetails['tutor_id'] != $tutorId) {
        throw new Exception("No tienes permiso para crear tareas en esta lección/clase.", 403);
    }

    $pdo->beginTransaction();

    $assignmentId = $tareaPDO->createAssignment($lessonId, $title, $description ?: null, $dueDate, $totalPoints);

    if ($assignmentId) {
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Tarea "' . htmlspecialchars($title) . '" creada exitosamente.';
        $response['assignment_id'] = $assignmentId;
        $response['redirect_url'] = BASE_URL . "/intranet/ver_curso.php?clase_id=" . htmlspecialchars($classDetails['class_id']);
    } else {
        throw new Exception("Error al guardar la tarea en la base de datos.", 500);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error al crear tarea para lesson $lessonId (AJAX): (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = "Error al crear la tarea: " . $e->getMessage();
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
