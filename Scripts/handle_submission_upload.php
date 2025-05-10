<?php
// Scripts/handle_submission_upload.php (Adaptado para AJAX y Edición)

// 1. Inicialización, Sesión, Carga
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) { session_start(); }
    else { http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error sesión (headers sent).']); exit; }
}
if (!headers_sent()) { header('Content-Type: application/json'); }

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// Definir constantes si no están en config.php (MEJOR EN CONFIG.PHP)
if (!defined('MAX_FILE_SIZE_SUBMISSION_MB')) { define('MAX_FILE_SIZE_SUBMISSION_MB', 10); }
$maxFileSize = (MAX_FILE_SIZE_SUBMISSION_MB) * 1024 * 1024;
$allowedExtensions = ['pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'jpg', 'jpeg', 'png']; // Ajusta según necesites

$response = ['success' => false, 'message' => 'Error desconocido al procesar la entrega.'];

// 2. Seguridad: Login y Rol Alumno
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'alumno') {
    http_response_code(403);
    $response['message'] = 'Acceso no autorizado para realizar entregas.';
    echo json_encode($response);
    exit;
}
$studentId = $_SESSION['user_id'];

// 3. Verificar Método POST
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 4. Validar Token CSRF
if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_submission']) || !hash_equals($_SESSION['csrf_token_submission'], $_POST['csrf_token'])) {
    error_log("Error CSRF en handle_submission_upload. User ID: $studentId");
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Recarga e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// unset($_SESSION['csrf_token_submission']); // Opcional: invalidar si es de un solo uso

// 5. Obtener y Validar Datos
$assignmentId = filter_input(INPUT_POST, 'assignment_id', FILTER_VALIDATE_INT);
$action = $_POST['action'] ?? null; // 'new' o 'edit'
$existingSubmissionId = ($action === 'edit') ? filter_input(INPUT_POST, 'existing_submission_id', FILTER_VALIDATE_INT) : null;
$submissionComments = trim($_POST['submission_comments'] ?? '');

$validationErrors = [];
if (!$assignmentId) { $validationErrors['assignment_id'] = "ID de tarea no válido."; }
if (!in_array($action, ['new', 'edit'])) { $validationErrors['action'] = "Acción no válida."; }
if ($action === 'edit' && !$existingSubmissionId) { $validationErrors['existing_submission_id'] = "ID de entrega existente no válido para edición."; }

// Validación del archivo subido
if (!isset($_FILES['submission_file']) || $_FILES['submission_file']['error'] == UPLOAD_ERR_NO_FILE) {
    if ($action === 'new') { // Para nueva entrega, el archivo es obligatorio
        $validationErrors['submission_file'] = "Debes seleccionar un archivo para tu entrega.";
    }
    // Para edición, si no se sube un nuevo archivo, se mantienen los comentarios (si se actualizan)
} elseif ($_FILES['submission_file']['error'] != UPLOAD_ERR_OK) {
    $validationErrors['submission_file'] = "Error al subir el archivo (código: " . $_FILES['submission_file']['error'] . ").";
} else {
    $originalFilename = basename($_FILES['submission_file']['name']);
    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    $fileSize = $_FILES['submission_file']['size'];

    if (!in_array($fileExtension, $allowedExtensions)) {
        $validationErrors['submission_file'] = "Tipo de archivo no permitido (permitidos: " . implode(', ', $allowedExtensions) . ").";
    }
    if ($fileSize > $maxFileSize) {
        $validationErrors['submission_file'] = "El archivo excede el tamaño máximo permitido de " . MAX_FILE_SIZE_SUBMISSION_MB . "MB.";
    }
}

if (!empty($validationErrors)) {
    http_response_code(400);
    $response['message'] = 'Por favor, corrige los errores.';
    $response['errors'] = $validationErrors;
    echo json_encode($response);
    exit;
}

// 6. Lógica de Guardado/Actualización
$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) { throw new Exception("Error de conexión a la BD.", 500); }

    $entregaPDO = new EntregaPDO();
    $tareaPDO = new TareaPDO(); // Para detalles de la tarea
    $cursoPDO = new CursoPDO(); // Para verificar inscripción

    // Verificar que la tarea existe y el alumno está inscrito en la clase
    $assignmentDetails = $tareaPDO->findAssignmentById($assignmentId);
    if (!$assignmentDetails) { throw new Exception("Tarea no encontrada.", 404); }
    $lessonDetails = $cursoPDO->findLessonById($assignmentDetails['lesson_id']);
    if (!$lessonDetails) { throw new Exception("Lección no encontrada.", 404); }
    if (!$cursoPDO->isStudentEnrolled($studentId, $lessonDetails['class_id'])) {
        throw new Exception("No estás inscrito en la clase de esta tarea.", 403);
    }

    $newFilePath = null;
    $newOriginalFilename = null;

    // Procesar subida de archivo si se proporcionó uno nuevo
    if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] == UPLOAD_ERR_OK) {
        $uploadDir = __DIR__ . '/../resources/uploads/submissions/'; // Asegúrate que esta carpeta exista y tenga permisos
        if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }

        $originalFilename = basename($_FILES['submission_file']['name']);
        $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        // Crear un nombre de archivo único para evitar colisiones y problemas de seguridad
        $safeFilename = uniqid('sub_' . $studentId . '_' . $assignmentId . '_', true) . '.' . $fileExtension;
        $newFilePathOnServer = $uploadDir . $safeFilename;
        // Ruta a guardar en la BD (relativa a la raíz del proyecto)
        $newFilePathForDB = 'resources/uploads/submissions/' . $safeFilename;

        if (!move_uploaded_file($_FILES['submission_file']['tmp_name'], $newFilePathOnServer)) {
            throw new Exception("Error crítico al mover el archivo subido.", 500);
        }
        $newFilePath = $newFilePathForDB;
        $newOriginalFilename = $originalFilename;
    }

    $pdo->beginTransaction();

    if ($action === 'new') {
        if (!$newFilePath) { throw new Exception("Se requiere un archivo para una nueva entrega.", 400); }
        $submissionId = $entregaPDO->createSubmission($assignmentId, $studentId, $newFilePath, $newOriginalFilename, $submissionComments);
        if ($submissionId) {
            $response['success'] = true;
            $response['message'] = '¡Tarea enviada exitosamente!';
            $response['action_taken'] = 'created';
            $response['submission_details'] = [
                'submission_id' => $submissionId,
                'original_filename' => $newOriginalFilename,
                'submission_time' => date('Y-m-d H:i:s'), // Hora actual
                'student_comments' => $submissionComments
            ];
        } else {
            throw new Exception("Error al guardar la nueva entrega en la base de datos.", 500);
        }
    } elseif ($action === 'edit' && $existingSubmissionId) {
        // Obtener entrega anterior para posible eliminación de archivo antiguo
        $oldSubmission = $entregaPDO->findSubmissionById($existingSubmissionId);
        if (!$oldSubmission || $oldSubmission['student_id'] != $studentId || $oldSubmission['assignment_id'] != $assignmentId) {
            throw new Exception("No tienes permiso para editar esta entrega o no existe.", 403);
        }

        // Si se subió un nuevo archivo, eliminar el antiguo (opcional)
        if ($newFilePath && !empty($oldSubmission['stored_filepath'])) {
            $oldFileFullPath = __DIR__ . '/../' . $oldSubmission['stored_filepath'];
            if (file_exists($oldFileFullPath)) {
                unlink($oldFileFullPath); // Eliminar archivo antiguo del servidor
            }
        }

        // Si no se subió un nuevo archivo, se usa el existente, solo se actualizan comentarios y tiempo.
        // Si se subió, $newFilePath y $newOriginalFilename tendrán valores.
        $filePathToUpdate = $newFilePath ?: $oldSubmission['stored_filepath'];
        $originalFilenameToUpdate = $newOriginalFilename ?: $oldSubmission['original_filename'];

        $updateSuccess = $entregaPDO->updateSubmission(
            $existingSubmissionId,
            $filePathToUpdate,
            $originalFilenameToUpdate,
            $submissionComments
            // Considera si quieres actualizar submission_time al editar.
            // Si es así, tu método updateSubmission debería aceptarlo.
        );

        if ($updateSuccess) {
            $response['success'] = true;
            $response['message'] = '¡Entrega actualizada exitosamente!';
            $response['action_taken'] = 'updated';
            $response['submission_details'] = [
                'submission_id' => $existingSubmissionId,
                'original_filename' => $originalFilenameToUpdate,
                'submission_time' => date('Y-m-d H:i:s'), // Hora de la edición
                'student_comments' => $submissionComments
            ];
        } else {
            throw new Exception("Error al actualizar la entrega en la base de datos.", 500);
        }
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Error en handle_submission_upload.php: (" . $e->getCode() . ") " . $e->getMessage());
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
