<?php
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        http_response_code(500);
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
    'message' => 'No se pudo procesar la solicitud.',
    'action' => null,
];

$courseCatalogUrlReference = BASE_URL . '/intranet/intranet.php#catalogo';
$loginPageUrl = BASE_URL . '/pages/intranet.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'Debes iniciar sesión para inscribirte.';
    $response['action'] = 'login_required';
    echo json_encode($response);
    exit;
}
$userId = $_SESSION['user_id'];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token_catalogo']) || !hash_equals($_SESSION['csrf_token_catalogo'], $_POST['csrf_token'])) {
    error_log("Error CSRF detectado en intento de inscripción para user ID: $userId");
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Recarga la página e intenta de nuevo.';
    $response['action'] = 'csrf_error';
    echo json_encode($response);
    exit;
}

$action = $_POST['action'] ?? null;
$classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$courseId = filter_input(INPUT_POST, 'course_id', FILTER_VALIDATE_INT);

if ($action !== 'enroll' || $classId === false || $classId <= 0 || $courseId === false || $courseId <= 0) {
    http_response_code(400);
    $response['message'] = 'Solicitud de inscripción inválida. Faltan datos o son incorrectos.';
    echo json_encode($response);
    exit;
}

$pdo = null;
$errorMessage = 'Ocurrió un error inesperado al procesar la inscripción.';

try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        error_log("Error CRÍTICO en handle_enrollment (AJAX): No se pudo obtener conexión a la BD.");
        http_response_code(500);
        $response['message'] = 'Error interno del servidor [DBC].';
        echo json_encode($response);
        exit;
    }

    $cursoPDO = new CursoPDO();

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    $isEnrolledInThisClass = $cursoPDO->isStudentEnrolled($userId, $classId);
    if ($isEnrolledInThisClass === null) { 
        throw new Exception("Error al verificar la inscripción previa en la clase.", 500);
    }
    if ($isEnrolledInThisClass === true) {
        if ($pdo->inTransaction()) { $pdo->rollBack(); }
        $response['message'] = 'Ya estás inscrito en esta clase específica.';
        $response['action'] = 'already_enrolled_in_class';
        http_response_code(409); 
        echo json_encode($response);
        exit;
    }

    $classDetails = $cursoPDO->findClassById($classId);
    if (!$classDetails) {
        throw new Exception("La clase seleccionada no existe o no está disponible.", 404);
    }

    $capacity = $classDetails['capacity'] ?? null;
    if ($capacity !== null && $capacity > 0) {
        $currentCount = $cursoPDO->getEnrollmentCountByClassId($classId);
        if ($currentCount === false) { 
            throw new Exception("Error al verificar la capacidad de la clase.", 500);
        }
        if ($currentCount >= $capacity) {
            throw new Exception("Lo sentimos, esta clase ya ha alcanzado su capacidad máxima.", 409);
        }
    }

    $enrollSuccess = $cursoPDO->enrollStudent($userId, $classId);

    if ($enrollSuccess) {
        if ($pdo->inTransaction()) { $pdo->commit(); }
        error_log("Inscripción AJAX exitosa: User $userId a Class $classId");
        
        $response['success'] = true;
        $courseTitleForMessage = $classDetails['course_title'] ?? 'la clase';
        $response['message'] = '¡Inscripción exitosa en ' . htmlspecialchars($courseTitleForMessage) . '!';
        $response['action'] = 'enrollment_success';
    } else {
        throw new Exception("No se pudo procesar la inscripción en este momento [Insert Fail].", 500);
    }

} catch (RuntimeException $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Error CRÍTICO (RuntimeException) en handle_enrollment (AJAX): " . $e->getMessage());
    $response['message'] = 'Error interno del servidor [PDO Init].';
    http_response_code(500);
} catch (PDOException $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Error PDOException en handle_enrollment (AJAX): (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = 'Error de base de datos durante la inscripción.';
    http_response_code(500);
} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
    error_log("Excepción manejada en handle_enrollment (AJAX): (" . $e->getCode() . ") " . $e->getMessage());
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
