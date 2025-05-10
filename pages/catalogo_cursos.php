<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

if (!isset($_SESSION['user_id'])) {
    redirectWithError('intranet.php', 'login_error', 'Debes iniciar sesión para ver los cursos.');
}
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? null;

if ($role !== 'alumno') {
    header('Location: ../intranet/intranet.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$availableClasses = [];
$loadingError = null;
$pdo = Conexion::obtenerConexion();

if ($pdo) {
    try {
        $cursoPDO = new CursoPDO();
        $availableClasses = $cursoPDO->getCoursesWithAvailableClasses($userId); 
        if ($availableClasses === false) {
            $loadingError = "Error al cargar las clases disponibles.";
        }
    } catch (Exception $e) {
        $loadingError = "Error al procesar la solicitud de clases.";
        error_log("Error cargando catálogo (getAvailableClasses) para user $userId: " . $e->getMessage());
    }
} else {
    $loadingError = "Error de conexión a la base de datos.";
}

$enrollmentSuccess = $_SESSION['enrollment_success'] ?? null; unset($_SESSION['enrollment_success']);
$enrollmentError = $_SESSION['enrollment_error'] ?? null; unset($_SESSION['enrollment_error']);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catálogo de Clases Disponibles - LEYdev</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../resources/css/intranet.css">
    <style>
        body { background-color: #f8f9fa; }
        .course-card:hover { transform: translateY(-5px); box-shadow: 0 .5rem 1rem rgba(0,0,0,.15)!important; }
        .card-img-top { max-height: 200px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container mt-4 mb-5">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h1 class="mb-0">Clases Disponibles</h1>
            <a href="../intranet/intranet.php" class="btn btn-outline-secondary btn-sm">Volver a Intranet</a>
        </div>
        <hr>

        <?php if ($enrollmentSuccess): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($enrollmentSuccess); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        <?php if ($enrollmentError): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($enrollmentError); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($loadingError): ?>
            <div class="alert alert-warning" role="alert">
                <?php echo htmlspecialchars($loadingError); ?>
            </div>
        <?php elseif (!empty($availableClasses)): ?>
            <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                <?php foreach ($availableClasses as $class):
                    $classId = htmlspecialchars($class['class_id']);
                    $courseTitle = htmlspecialchars($class['course_title'] ?? 'Sin Título');
                    $courseDescription = htmlspecialchars($class['course_description'] ?? 'Sin descripción.');
                    $imageFilename = $class['course_image_url'] ?? null;
                    $defaultImage = '../resources/img/logo.png';
                    $courseImagePath = $defaultImage;
                    if (!empty($imageFilename)) {
                        $courseImagePath = '../resources/img/courses/' . htmlspecialchars($imageFilename);
                    }
                    $courseImage = htmlspecialchars($courseImagePath);
                    $classSchedule = htmlspecialchars($class['class_schedule'] ?? 'N/A');
                    $classSemester = htmlspecialchars($class['class_semester'] ?? '');
                    $tutorUsername = htmlspecialchars($class['tutor_username'] ?? 'N/A');
                ?>
                    <div class="col">
                        <div class="card h-100 shadow-sm course-card">
                            <img src="<?php echo $courseImage; ?>" class="card-img-top" alt="Imagen de <?php echo $courseTitle; ?>">
                            <div class="card-body d-flex flex-column">
                                <h5 class="card-title"><?php echo $courseTitle; ?></h5>
                                <p class="card-text flex-grow-1"><?php echo $courseDescription; ?></p>
                                <p class="card-text mb-1"><small class="text-muted">Tutor: <?php echo $tutorUsername; ?></small></p>
                                <p class="card-text"><small class="text-muted">Horario: <?php echo $classSchedule; ?> (<?php echo $classSemester; ?>)</small></p>
                                <form action="../Scripts/handle_enrollment.php" method="POST" class="mt-auto">
                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                    <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                                    <input type="hidden" name="action" value="enroll">
                                    <button type="submit" class="btn btn-success w-100">Inscribirme</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info" role="alert">
                No hay nuevas clases disponibles para inscribirte en este momento.
            </div>
        <?php endif; ?>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
