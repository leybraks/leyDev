<?php
// PDO/TareaPDO.php (Actualizado para lesson_id)

require_once __DIR__ . '/Conexion.php';

class TareaPDO {
    private $tablaAssignments = 'assignments';
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) { throw new RuntimeException("TareaPDO: No DB connection"); }
    }
    private function isConnected() { return $this->pdo !== null; }

    /**
     * Crea una nueva tarea asociada a una LECCIÓN específica.
     *
     * @param int $lessonId ID de la lección a la que pertenece.
     * @param string $title Título.
     * @param ?string $description Descripción (opcional).
     * @param ?string $dueDate Fecha límite (opcional).
     * @param ?int $totalPoints Puntaje (opcional).
     * @return int|false ID de la nueva tarea o false en error.
     */
    public function createAssignment(int $lessonId, string $title, ?string $description, ?string $dueDate, ?int $totalPoints) {
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaAssignments} (lesson_id, title, description, due_date, total_points)
                VALUES (?, ?, ?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$lessonId, $title, $description, $dueDate, $totalPoints]);
            return $success ? (int)$this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error TareaPDO::createAssignment para Lesson $lessonId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene TODAS las tareas asociadas a una LECCIÓN específica.
     *
     * @param int $lessonId ID de la lección.
     * @return array|false Un array con todas las tareas encontradas (puede estar vacío)
     * o false si hay un error de BD.
     */
    public function getAssignmentsByLessonId(int $lessonId) { // Cambiado a plural
        if (!$this->isConnected()) return false;
        // Quitado LIMIT 1
        $sql = "SELECT * FROM {$this->tablaAssignments}
                WHERE lesson_id = ?
                ORDER BY created_at ASC"; // Ordenar por fecha de creación, por ejemplo
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$lessonId]);
            return $stmt->fetchAll(); // <<< CAMBIO: Obtener todas las filas
        } catch (PDOException $e) {
            error_log("Error TareaPDO::getAssignmentsByLessonId para Lesson $lessonId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Encuentra los detalles de una tarea específica por su ID.
     * Útil para la página de entrega del alumno o calificación del tutor.
     *
     * @param int $assignmentId ID de la tarea.
     * @return array|false Datos de la tarea o false si no existe/error.
     */
    public function findAssignmentById(int $assignmentId) {
         if (!$this->isConnected()) return false;
         $sql = "SELECT * FROM {$this->tablaAssignments} WHERE assignment_id = ?";
         try {
              $stmt = $this->pdo->prepare($sql);
              $stmt->execute([$assignmentId]);
              return $stmt->fetch();
         } catch (PDOException $e) {
              error_log("Error en TareaPDO::findAssignmentById para ID $assignmentId: " . $e->getMessage());
              return false;
         }
    }

    // --- Métodos Futuros ---
    // public function updateAssignment(int $assignmentId, array $data) { ... }
    // public function deleteAssignment(int $assignmentId) { ... }

    // El método getAssignmentsByClassId fue eliminado porque ahora las tareas se ligan a lecciones.
    // Si necesitaras listar todas las tareas de una CLASE, harías un JOIN con lessons:
    /*
    public function getAllAssignmentsForClass(int $classId) {
         $sql = "SELECT a.* FROM assignments a JOIN lessons l ON a.lesson_id = l.lesson_id WHERE l.class_id = ?";
         // ... preparar, ejecutar, fetchAll ...
    }
    */

     /**
     * Obtiene todas las tareas asociadas a una clase específica (a través de sus lecciones).
     *
     * @param int $classId El ID de la clase.
     * @return array|false Un array con los detalles de las tareas (id, título, puntos, fecha límite)
     * o false en caso de error. Array vacío si no hay tareas.
     */
    public function getAssignmentsByClassId(int $classId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Asumimos que tablaAssignments y tablaLessons están definidas
        // o usamos nombres literales.
        $tablaAssignments = 'assignments'; // O $this->tablaAssignments
        $tablaLessons = 'lessons';       // O $this->tablaLessons (pertenece a CursoPDO)

        $sql = "SELECT
                    a.assignment_id, a.title, a.total_points, a.due_date,
                    l.lesson_id -- Opcional, por si se necesita
                FROM {$tablaAssignments} a
                JOIN {$tablaLessons} l ON a.lesson_id = l.lesson_id
                WHERE l.class_id = ?
                ORDER BY a.due_date ASC, a.created_at ASC"; // O el orden que prefieras
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en TareaPDO::getAssignmentsByClassId para class_id $classId: " . $e->getMessage());
            return false;
        }
    }

} // Fin clase TareaPDO
?>