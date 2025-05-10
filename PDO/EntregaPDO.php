<?php
// PDO/EntregaPDO.php

/**
 * Clase para manejar las operaciones de Base de Datos para la tabla 'submissions'.
 * Gestiona las entregas de tareas de los estudiantes, incluyendo la referencia
 * al archivo y la calificación.
 */
require_once __DIR__ . '/Conexion.php';

class EntregaPDO {

    private $tablaSubmissions = 'submissions'; // Nombre de la tabla de entregas
    private $tablaUsers = 'users';             // Para obtener nombres de alumnos
    private $tablaUserProfiles = 'user_profiles'; // Nombre de la tabla de perfiles de usuario
    private $pdo; // Instancia PDO

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) {
            throw new RuntimeException("EntregaPDO no pudo obtener la conexión a la BD.");
        }
    }

    private function isConnected() {
        return $this->pdo !== null;
    }

    public function createSubmission(int $assignmentId, int $studentId, string $storedFilepath, string $originalFilename, ?string $studentComments = null) {
        if (!$this->isConnected()) return false;

        $sql = "INSERT INTO {$this->tablaSubmissions}
                    (assignment_id, student_id, original_filename, stored_filepath, student_comments, submission_time)
                VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $assignmentId,
                $studentId,
                $originalFilename,
                $storedFilepath,
                $studentComments
            ]);

            if ($success) {
                return (int)$this->pdo->lastInsertId();
            } else {
                error_log("Error en EntregaPDO::createSubmission - execute() falló. Info: " . implode(" | ", $stmt->errorInfo()));
                return false;
            }
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' || $e->getCode() == 19) {
                error_log("Intento de entrega duplicada o error de constraint: Assign $assignmentId, Student $studentId. Error: " . $e->getMessage());
            } else {
                error_log("Error en EntregaPDO::createSubmission para Assign $assignmentId, Student $studentId: " . $e->getMessage());
            }
            return false;
        }
    }

    public function updateSubmission(int $submissionId, string $newStoredFilepath, string $newOriginalFilename, ?string $newStudentComments = null) {
        if (!$this->isConnected()) return false;

        $sql = "UPDATE {$this->tablaSubmissions}
                SET stored_filepath = ?,
                    original_filename = ?,
                    student_comments = ?,
                    submission_time = datetime('now', 'localtime'),
                    grade = NULL,
                    tutor_feedback = NULL,
                    graded_at = NULL
                WHERE submission_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $newStoredFilepath,
                $newOriginalFilename,
                $newStudentComments,
                $submissionId
            ]);

            if (!$success) {
                // Registrar información detallada del error PDO si execute() falla
                $errorInfo = $stmt->errorInfo();
                error_log("Error en EntregaPDO::updateSubmission - execute() falló para Submission ID {$submissionId}. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}");
            }
            return $success;

        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::updateSubmission para Submission ID $submissionId: " . $e->getMessage());
            return false;
        }
    }

    public function findSubmissionByAssignmentAndStudent(int $assignmentId, int $studentId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT * FROM {$this->tablaSubmissions}
                WHERE assignment_id = ? AND student_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$assignmentId, $studentId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::findSubmissionByAssignmentAndStudent para Assign $assignmentId, Student $studentId: " . $e->getMessage());
            return false;
        }
    }

    public function getSubmissionsByAssignmentId(int $assignmentId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT s.*, u.username AS student_username, up.first_name, up.paternal_last_name
                FROM {$this->tablaSubmissions} AS s
                JOIN {$this->tablaUsers} AS u ON s.student_id = u.id
                LEFT JOIN {$this->tablaUserProfiles} up ON u.user_id = up.user_id
                WHERE s.assignment_id = ?
                ORDER BY u.username ASC, s.submission_time DESC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$assignmentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::getSubmissionsByAssignmentId para Assign ID $assignmentId: " . $e->getMessage());
            return false;
        }
    }

    public function findSubmissionById(int $submissionId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT s.*, u.username AS student_username
                FROM {$this->tablaSubmissions} s
                JOIN {$this->tablaUsers} u ON s.student_id = u.id
                WHERE s.submission_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$submissionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::findSubmissionById para ID $submissionId: " . $e->getMessage());
            return false;
        }
    }

    public function updateGradeAndFeedback(int $submissionId, $grade, $feedback) {
        if (!$this->isConnected()) return false;
        $gradedAtValueSQL = ($grade !== null || (is_string($feedback) && $feedback !== '')) ? "datetime('now', 'localtime')" : "NULL";
        $sql = "UPDATE {$this->tablaSubmissions}
                SET grade = :grade, tutor_feedback = :feedback, graded_at = {$gradedAtValueSQL}
                WHERE submission_id = :submission_id";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->bindValue(':grade', ($grade !== null ? (float)$grade : null), PDO::PARAM_STR);
            $stmt->bindValue(':feedback', ($feedback !== null && $feedback !== '' ? (string)$feedback : null), PDO::PARAM_STR);
            $stmt->bindValue(':submission_id', $submissionId, PDO::PARAM_INT);
            
            $success = $stmt->execute();
            if (!$success) {
                $errorInfo = $stmt->errorInfo();
                error_log("Error en EntregaPDO::updateGradeAndFeedback - execute() falló. SQLSTATE: {$errorInfo[0]}, Code: {$errorInfo[1]}, Message: {$errorInfo[2]}");
            }
            return $success;
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::updateGradeAndFeedback para Submission ID $submissionId: " . $e->getMessage());
            return false;
        }
    }

    public function getSubmissionsWithDetailsByStudentId(int $studentId) {
        if (!$this->isConnected()) return false;
        $tablaAssignments = 'assignments';
        $tablaLessons = 'lessons';
        $tablaClasses = 'classes';
        $tablaCourses = 'courses';
        $sql = "SELECT
                    s.submission_id, s.submission_time, s.grade, s.tutor_feedback, s.graded_at,
                    s.original_filename, s.stored_filepath, s.student_comments,
                    a.assignment_id, a.title AS assignment_title, a.total_points AS assignment_total_points,
                    l.lesson_id, l.title AS lesson_title,
                    crs.course_id, crs.title AS course_title
                FROM {$this->tablaSubmissions} AS s
                INNER JOIN {$tablaAssignments} AS a ON s.assignment_id = a.assignment_id
                INNER JOIN {$tablaLessons} AS l ON a.lesson_id = l.lesson_id
                INNER JOIN {$tablaClasses} AS cl ON l.class_id = cl.class_id
                INNER JOIN {$tablaCourses} AS crs ON cl.course_id = crs.course_id
                WHERE s.student_id = ?
                ORDER BY crs.title ASC, l.lesson_order ASC, a.created_at DESC, s.submission_time DESC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$studentId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::getSubmissionsWithDetailsByStudentId para student ID $studentId: " . $e->getMessage());
            return false;
        }
    }

    public function getSubmissionsForClass(int $classId) {
        if (!$this->isConnected()) return false;
        $tablaAssignments = 'assignments';
        $tablaLessons = 'lessons';
        $sql = "SELECT s.student_id, s.assignment_id, s.grade, s.submission_id
                FROM {$this->tablaSubmissions} AS s
                JOIN {$tablaAssignments} AS a ON s.assignment_id = a.assignment_id
                JOIN {$tablaLessons} AS l ON a.lesson_id = l.lesson_id
                WHERE l.class_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error en EntregaPDO::getSubmissionsForClass para class_id $classId: " . $e->getMessage());
            return false;
        }
    }
}
?>
