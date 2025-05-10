<?php
// intranet/sections/catalogo_content.php

// 1. Iniciar sesión y verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['user_id'])) {
    echo '<div id="catalogo-content-loaded" class="main-section dynamic-section" data-section-name="catalogo"><p class="error-message" style="padding:20px; color:red;">Acceso denegado.</p></div>';
    exit;
}
$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'desconocido';
if (empty($_SESSION['csrf_token_catalogo'])) {
    $_SESSION['csrf_token_catalogo'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token_catalogo'];

require_once __DIR__ . '/../../Config/config.php';
require_once __DIR__ . '/../../Includes/autoload.php';
require_once __DIR__ . '/../../PDO/Conexion.php';

// --- NUEVO: Obtener mensaje de inscripción de los parámetros GET ---
$enrollmentMessageFromGet = null;
$enrollmentStatusFromGet = null;
if (isset($_GET['enrollment_msg']) && isset($_GET['enrollment_status'])) {
    $enrollmentMessageFromGet = htmlspecialchars(urldecode($_GET['enrollment_msg']));
    $enrollmentStatusFromGet = htmlspecialchars($_GET['enrollment_status']);
}
// --- FIN NUEVO ---

$groupedCourses = [];
$loadingError = null;
// ... (resto de tu lógica para obtener $groupedCourses) ...
try {
    $cursoPDO = new CursoPDO();
    $groupedCourses = $cursoPDO->getCoursesWithAvailableClasses($userId);
    if ($groupedCourses === false) {
        $loadingError = "Error al cargar el catálogo de cursos desde la base de datos.";
    }
} catch (Exception $e) {
    $loadingError = "Error inesperado al procesar la solicitud del catálogo: " . $e->getMessage();
    error_log("Error cargando catálogo agrupado para user $userId (catalogo_content.php): " . $e->getMessage());
}
?>
<div id="catalogo-content-loaded" class="main-section dynamic-section" data-section-name="catalogo">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0"><i class="fas fa-th-list"></i> Catálogo de Clases Disponibles</h3>
    </div>
    <p>Selecciona un horario de la clase a la que deseas inscribirte.</p>
    <hr>

    <?php // --- NUEVO: Mostrar mensaje de inscripción si viene de GET --- ?>
    <?php if ($enrollmentMessageFromGet): ?>
        <div id="enrollment-status-message" class="message <?php echo ($enrollmentStatusFromGet === 'success' ? 'success-message' : 'error-message'); ?>" style="margin-bottom: 15px; display: block;">
            <?php echo $enrollmentMessageFromGet; ?>
        </div>
    <?php endif; ?>
    <?php // --- FIN NUEVO --- ?>

    <?php // Div para mensajes AJAX INMEDIATOS de los formularios (ej. "selecciona una clase") ?>
    <div id="enrollment-ajax-message" style="display:none; margin-bottom: 15px;"></div>

    <?php if ($loadingError): ?>
        <p class="error-message" style="color:red;"><?php echo htmlspecialchars($loadingError); ?></p>
    <?php elseif (!empty($groupedCourses)): ?>
        <div class="catalogo-agrupado-container">
            <?php foreach ($groupedCourses as $courseId => $courseData):
                // ... (tu bucle foreach existente para mostrar cursos y formularios) ...
                if (!isset($courseData['details'])) { continue; }
                $courseDetails = $courseData['details'];
                $availableClasses = $courseData['classes'] ?? [];
                $courseTitle = htmlspecialchars($courseDetails['title'] ?? 'N/A');
                // ... (resto de la lógica de la tarjeta del curso) ...
            ?>
                <div class="curso-catalogo-item curso-catalogo-item-agrupado">
                    <div class="catalogo-imagen-container">
                        <img src="<?php echo htmlspecialchars(isset($courseDetails['image_url']) ? '../resources/img/courses/' . $courseDetails['image_url'] : '../resources/img/logo.png'); ?>" alt="<?php echo $courseTitle; ?>" class="catalogo-imagen" onerror="this.onerror=null;this.src='../resources/img/logo.png';">
                    </div>
                    <div class="catalogo-info">
                        <h4 class="catalogo-titulo"><?php echo $courseTitle; ?></h4>
                        <p class="catalogo-descripcion"><?php echo htmlspecialchars($courseDetails['description'] ?? 'N/A'); ?></p>

                        <?php if (!empty($availableClasses)): ?>
                            <form action="../Scripts/handle_enrollment.php" method="POST" class="enroll-form-select">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action" value="enroll">
                                <input type="hidden" name="course_id" value="<?php echo htmlspecialchars($courseId); ?>">
                                <div class="select-horario-container">
                                    <label for="class_id_<?php echo htmlspecialchars($courseId); ?>">Selecciona un Horario/Clase:</label>
                                    <select name="class_id" id="class_id_<?php echo htmlspecialchars($courseId); ?>" required class="select-horario">
                                        <option value="">-- Elige una opción --</option>
                                        <?php foreach ($availableClasses as $class):
                                            // ... (tu lógica para generar las opciones del select) ...
                                            $optionTextParts = [];
                                            if (!empty($class['schedule'])) $optionTextParts[] = htmlspecialchars($class['schedule']);
                                            if (!empty($class['semester'])) $optionTextParts[] = htmlspecialchars($class['semester']);
                                            if (!empty($class['tutor_username'])) $optionTextParts[] = "Tutor: " . htmlspecialchars($class['tutor_username']);
                                            $enrolledCount = (int)($class['enrolled_count'] ?? 0);
                                            $capacity = isset($class['capacity']) ? (int)$class['capacity'] : null;
                                            $isFull = ($capacity !== null && $capacity > 0 && $enrolledCount >= $capacity);
                                            $isAlreadyEnrolled = (bool)($class['is_student_enrolled'] ?? false);
                                            $optionDisplayText = !empty($optionTextParts) ? implode(' | ', $optionTextParts) : ('Clase ID: ' . htmlspecialchars($class['class_id']));
                                            if ($isAlreadyEnrolled) { $optionDisplayText .= " (Ya inscrito)"; }
                                            elseif ($isFull) { $optionDisplayText .= " (Clase llena)"; }
                                            else if ($capacity !== null && $capacity > 0) { $optionDisplayText .= " (" . ($capacity - $enrolledCount) . " lugares disp.)"; }
                                        ?>
                                            <option value="<?php echo htmlspecialchars($class['class_id']); ?>" <?php if($isFull || $isAlreadyEnrolled) echo 'disabled'; ?>>
                                                <?php echo $optionDisplayText; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="catalogo-accion">
                                    <?php
                                        // Determinar si hay alguna opción seleccionable para este curso
                                        $hasSelectableOption = false;
                                        foreach ($availableClasses as $class) {
                                            $enrolledCount = (int)($class['enrolled_count'] ?? 0);
                                            $capacity = isset($class['capacity']) ? (int)$class['capacity'] : null;
                                            $isFull = ($capacity !== null && $capacity > 0 && $enrolledCount >= $capacity);
                                            $isAlreadyEnrolled = (bool)($class['is_student_enrolled'] ?? false);
                                            if (!$isFull && !$isAlreadyEnrolled) {
                                                $hasSelectableOption = true;
                                                break;
                                            }
                                        }
                                    ?>
                                    <?php if ($hasSelectableOption): ?>
                                        <button type="submit" class="catalogo-boton-inscribir">Inscribirme a esta Clase</button>
                                    <?php else: ?>
                                        <?php endif; ?>
                                </div>
                            </form>
                        <?php else: ?>
                            <p class="mensaje-info-inline">No hay horarios disponibles para este curso en este momento.</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php elseif (!$loadingError): ?>
        <p class="mensaje-info">No hay cursos con clases disponibles para inscribirte en este momento.</p>
    <?php endif;?>
</div>
