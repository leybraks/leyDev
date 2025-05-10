<?php
// intranet/sections/calificaciones_tutor_content.php

// 1. Iniciar sesión y verificar autenticación
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'tutor') {
    echo '<div class="main-section dynamic-section" data-section-name="calificaciones"><p class="error-message" style="padding:20px;">Acceso no autorizado o rol incorrecto para esta sección.</p></div>';
    exit;
}

// 2. Cargar dependencias necesarias
require_once __DIR__ . '/../../Config/config.php';       // Ajusta la ruta según tu estructura
require_once __DIR__ . '/../../Includes/autoload.php';   // Ajusta la ruta
require_once __DIR__ . '/../../PDO/Conexion.php';     // Ajusta la ruta

// 3. Obtener variables de sesión
$userId = $_SESSION['user_id']; // ID del tutor
$role = $_SESSION['role'];     // Rol (ya verificado como 'tutor')

// Inicializar variables
$tutorClasses = [];
// 4. Obtener selected_class_id de $_GET (el JS lo pasará en la URL del fetch)
$selectedClassId = filter_input(INPUT_GET, 'selected_class_id', FILTER_VALIDATE_INT);
$selectedClassDetails = null;
$enrolledStudents = [];
$classAssignments = [];
$gradesLookup = [];
$loadingError = null;

// Instanciar PDOs
try {
    // Es buena práctica obtener la conexión una vez y pasarla si los PDOs la requieren,
    // o asegurarse que Conexion::obtenerConexion() se pueda llamar múltiples veces sin problema.
    // $pdo = Conexion::obtenerConexion(); // Si tus PDOs no la obtienen internamente.

    $cursoPDO = new CursoPDO();
    $tareaPDO = new TareaPDO();
    $entregaPDO = new EntregaPDO();

    // 1. Obtener las clases del tutor para el selector
    $tutorClasses = $cursoPDO->getClassesByTutorId($userId);
    if ($tutorClasses === false) {
        $loadingError = "Error al cargar tus clases asignadas.";
    }

    // 2. Si se ha seleccionado una clase válida y pertenece al tutor
    if ($selectedClassId && !$loadingError) {
        $selectedClassDetails = $cursoPDO->findClassById($selectedClassId);
        if (!$selectedClassDetails || $selectedClassDetails['tutor_id'] != $userId) {
            $loadingError = "Clase no válida o no tienes permiso para verla.";
            $selectedClassDetails = null;
        } else {
            $enrolledStudents = $cursoPDO->getEnrolledStudentsByClassId($selectedClassId);
            $classAssignments = $tareaPDO->getAssignmentsByClassId($selectedClassId);
            $allSubmissionsForClass = $entregaPDO->getSubmissionsForClass($selectedClassId);

            if ($allSubmissionsForClass) {
                foreach ($allSubmissionsForClass as $sub) {
                    $gradesLookup[$sub['student_id']][$sub['assignment_id']] = [
                        'grade' => $sub['grade'],
                        'submission_id' => $sub['submission_id']
                    ];
                }
            }
            if ($enrolledStudents === false || $classAssignments === false || $allSubmissionsForClass === false) {
                $loadingError = ($loadingError ? $loadingError . '<br>' : '') . "Error al cargar detalles de la clase.";
            }
        }
    }
} catch (Exception $e) {
    $loadingError = "Error inesperado al preparar el libro de calificaciones: " . $e->getMessage();
    error_log("Excepción en calificaciones_tutor_content para tutor $userId: " . $e->getMessage());
}

// 5. Imprimir solo el HTML de la sección
// Añadimos el div contenedor que antes estaba en intranet.php para esta sección
?>
<div id="calificaciones-content-loaded" class="main-section dynamic-section" data-section-name="calificaciones">
    <h3><i class="fas fa-book-reader"></i> Libro de Calificaciones</h3>
    <hr>

    <?php if ($loadingError && !$selectedClassDetails && empty($tutorClasses)): // Error crítico impidió cargar hasta las clases ?>
        <p class="error-message"><?php echo htmlspecialchars($loadingError); ?></p>
    <?php else: ?>
        <?php // Mostrar error de carga de clases si ocurrió, pero aún permitir seleccionar otra ?>
        <?php if ($loadingError && empty($tutorClasses)): ?>
             <p class="error-message"><?php echo htmlspecialchars($loadingError); ?></p>
        <?php endif; ?>

        <form method="GET" action="#" id="select-class-grades-form">
            <?php // El action="#" es placeholder, JS manejará el envío.
                  // No necesitamos data_target_manual con JS.
            ?>
            <div class="form-group" style="max-width: 400px; margin-bottom: 1.5rem;">
                <label for="selected_class_id_tutor_grades">Selecciona una Clase:</label>
                <select name="selected_class_id" id="selected_class_id_tutor_grades" class="form-control">
                    <option value="">-- Mis Clases --</option>
                    <?php if (!empty($tutorClasses)): ?>
                        <?php foreach ($tutorClasses as $class):
                            $optionText = htmlspecialchars($class['course_title'] . ' (' . ($class['schedule'] ?: 'N/A') . ' - ' . ($class['semester'] ?: 'N/A') . ')');
                            $selected = ($selectedClassId == $class['class_id']) ? 'selected' : '';
                        ?>
                            <option value="<?php echo htmlspecialchars($class['class_id']); ?>" <?php echo $selected; ?>>
                                <?php echo $optionText; ?>
                            </option>
                        <?php endforeach; ?>
                    <?php elseif (!$loadingError): // No mostrar este mensaje si ya hay un error de carga de clases ?>
                        <option value="" disabled>No tienes clases asignadas.</option>
                    <?php endif; ?>
                </select>
            </div>
        </form>

        <?php if ($selectedClassId && $loadingError && $selectedClassDetails === null): // Error específico al cargar detalles de una clase seleccionada ?>
             <p class="error-message"><?php echo htmlspecialchars($loadingError); ?></p>
        <?php endif; ?>

        <?php if ($selectedClassDetails && !$loadingError): ?>
            <h4>Calificaciones para: <?php echo htmlspecialchars($selectedClassDetails['course_title'] ?? 'Clase'); ?>
                <small class="text-muted">(<?php echo htmlspecialchars($selectedClassDetails['schedule'] ?? ''); ?> - Sem: <?php echo htmlspecialchars($selectedClassDetails['semester'] ?? ''); ?>)</small>
            </h4>

            <?php if (empty($enrolledStudents)): ?>
                <p class="mensaje-info">No hay estudiantes inscritos en esta clase.</p>
            <?php elseif (empty($classAssignments)): ?>
                <p class="mensaje-info">No hay tareas asignadas para esta clase.</p>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="tabla-calificaciones">
                        <thead>
                            <tr>
                                <th>Estudiante</th>
                                <?php foreach ($classAssignments as $assignment): ?>
                                    <th title="<?php echo htmlspecialchars($assignment['title']); ?> (<?php echo htmlspecialchars($assignment['total_points'] ?: 'N/P'); ?> pts)">
                                        <?php echo htmlspecialchars(mb_strimwidth($assignment['title'], 0, 20, "...")); ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($enrolledStudents as $student):
                                $studentId = $student['user_id'];
                                $firstName = $student['first_name'] ?? '';
                                $paternalName = $student['paternal_last_name'] ?? '';
                                $maternalName = $student['maternal_last_name'] ?? '';
                                $nameParts = array_filter([$firstName, $paternalName, $maternalName]);
                                $studentName = !empty($nameParts) ? htmlspecialchars(implode(' ', $nameParts)) : htmlspecialchars($student['username']);
                            ?>
                                <tr>
                                    <td><?php echo $studentName; ?></td>
                                    <?php foreach ($classAssignments as $assignment):
                                        $assignmentId = $assignment['assignment_id'];
                                        $submissionInfo = $gradesLookup[$studentId][$assignmentId] ?? null;
                                        $gradeDisplay = '-';
                                        $linkToGrade = null;

                                        if ($submissionInfo !== null && isset($submissionInfo['submission_id'])) {
                                            $gradeDisplay = $submissionInfo['grade'] ?? '-';
                                            // Ajusta la ruta a calificar_entrega.php si es necesario, asumiendo que está en el directorio 'intranet'
                                            $linkToGrade = "calificar_entrega.php?submission_id=" . htmlspecialchars($submissionInfo['submission_id']);
                                        }
                                    ?>
                                        <td>
                                            <?php if ($linkToGrade): ?>
                                                <a href="<?php echo $linkToGrade; ?>" title="Ver/Calificar esta entrega" class="grade-link">
                                                    <?php echo htmlspecialchars($gradeDisplay); ?>
                                                </a>
                                            <?php else: ?>
                                                <?php echo htmlspecialchars($gradeDisplay); ?>
                                            <?php endif; ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php // Fin if $selectedClassDetails && !$loadingError ?>
        <?php elseif ($selectedClassId && $loadingError): ?>
             <?php // No mostrar nada más si hubo error cargando detalles de clase, el error ya se mostró ?>
        <?php elseif (!$selectedClassId && !$loadingError && !empty($tutorClasses)): ?>
            <p class="mensaje-info">Por favor, selecciona una clase para ver el libro de calificaciones.</p>
        <?php endif; ?>

    <?php endif; // Fin if $loadingError && !$selectedClassDetails && empty($tutorClasses) ?>
</div>