<?php
// PDO/ExamenPDO.php

/**
 * Clase para manejar las operaciones de BD para exámenes y sus resultados.
 */
require_once __DIR__ . '/Conexion.php';

class ExamenPDO {
    private $tablaQuestions = 'questions';
    private $tablaQuestionOptions = 'question_options';
    private $tablaExams = 'exams';
    private $tablaExamResults = 'exam_results';
    private $tablaUsers = 'users'; // Para nombres de alumnos en listados
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) { throw new RuntimeException("ExamenPDO: No DB connection"); }
    }
    private function isConnected() { return $this->pdo !== null; }

    /**
     * Crea un nuevo examen asociado a una lección.
     * @return int|false El ID del nuevo examen o false en error.
     */
    public function createExam(int $lessonId, string $title, ?string $description, ?int $totalPoints) {
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaExams} (lesson_id, title, description, total_points)
                VALUES (?, ?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$lessonId, $title, $description, $totalPoints]);
            return $success ? (int)$this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::createExam para Lesson $lessonId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene el examen (si existe) asociado a una lección.
     * Asumimos un examen por lección por ahora.
     * @return array|false
     */
    public function getExamByLessonId(int $lessonId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT * FROM {$this->tablaExams} WHERE lesson_id = ? LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$lessonId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::getExamByLessonId para Lesson $lessonId: " . $e->getMessage());
            return false;
        }
    }

    // Dentro de la clase ExamenPDO

    /**
     * Guarda o actualiza el resultado (nota/feedback) de un examen para un alumno.
     * Registra el tiempo de entrega si es la primera vez que se guarda.
     * Registra el tiempo de calificación si se proporciona nota o feedback.
     *
     * @param int $examId ID del examen.
     * @param int $studentId ID del alumno.
     * @param ?float $score Nota numérica (null si no se califica aún).
     * @param ?string $feedback Comentario del tutor (null o '').
     * @param bool $isFirstSubmission Indica si esta llamada es por la primera entrega del alumno (para fijar submission_time).
     *
     * @return bool True en éxito, false en error.
     */
    public function saveExamResult(int $examId, int $studentId, ?float $score, ?string $feedback, bool $isFirstSubmission = false) {
        if (!$this->isConnected()) return false;

        // Buscar si ya existe
        $existingResult = $this->getExamResultByExamAndStudent($examId, $studentId);

        $now = "datetime('now', 'localtime')"; // Función de SQLite para la fecha/hora actual
        // La fecha de calificación solo se establece/actualiza si se da una nota o feedback
        $gradedAtValue = ($score !== null || !empty($feedback)) ? $now : "NULL";

        if ($existingResult) { // Ya existe un resultado -> Actualizar nota/feedback
            // No actualizamos submission_time aquí, solo grade, feedback, graded_at
            $sql = "UPDATE {$this->tablaExamResults}
                    SET score = ?, tutor_feedback = ?, graded_at = {$gradedAtValue}
                    WHERE result_id = ?";
            $params = [
                ($score !== null ? $score : null),
                ($feedback !== null ? $feedback : null),
                $existingResult['result_id']
            ];
        } else { // No existe -> Insertar nuevo resultado
            // Establecer submission_time si es la primera entrega
            $submissionTimeValue = $isFirstSubmission ? $now : "NULL"; // Poner la hora actual si es la primera entrega

            $sql = "INSERT INTO {$this->tablaExamResults}
                        (exam_id, student_id, score, tutor_feedback, graded_at, submission_time)
                    VALUES (?, ?, ?, ?, {$gradedAtValue}, {$submissionTimeValue})";
            $params = [
                $examId,
                $studentId,
                ($score !== null ? $score : null),
                ($feedback !== null ? $feedback : null)
            ];
        }

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            // Manejar error UNIQUE si intenta insertar dos veces (aunque getExamResultByExamAndStudent debería prevenirlo)
             if ($e->getCode() == '23000' || $e->getCode() == 19) {
                 error_log("Intento de resultado de examen duplicado: Exam $examId, Student $studentId");
             } else {
                error_log("Error ExamenPDO::saveExamResult para Exam $examId, Student $studentId: " . $e->getMessage());
             }
            return false;
        }
    }

    /**
     * Obtiene el resultado de un examen específico para un alumno.
     * @return array|false
     */
    public function getExamResultByExamAndStudent(int $examId, int $studentId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT * FROM {$this->tablaExamResults} WHERE exam_id = ? AND student_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$examId, $studentId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::getExamResultByExamAndStudent: " . $e->getMessage());
            return false;
        }
    }

     /**
     * Obtiene todos los resultados de un examen específico (para el tutor).
     * @param int $examId
     * @return array|false
     */
    public function getResultsByExamId(int $examId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT er.*, u.username as student_username
                FROM {$this->tablaExamResults} er
                JOIN {$this->tablaUsers} u ON er.student_id = u.id
                WHERE er.exam_id = ?
                ORDER BY u.username ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$examId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en ExamenPDO::getResultsByExamId para exam_id $examId: " . $e->getMessage());
            return false;
        }
    }
     /**
     * Encuentra un examen por su ID.
     * @param int $examId
     * @return array|false
     */
    public function findExamById(int $examId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT e.*, l.class_id
                FROM {$this->tablaExams} e
                JOIN lessons l ON e.lesson_id = l.lesson_id
                WHERE e.exam_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$examId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::findExamById: " . $e->getMessage());
            return false;
        }
    }
/**
     * Crea una nueva pregunta para un examen.
     * @return int|false El ID de la nueva pregunta o false en error.
     */
    public function createQuestion(int $examId, string $questionText, string $questionType = 'multiple_choice', int $points = 1, int $order = 0) {
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaQuestions} (exam_id, question_text, question_type, points, question_order)
                VALUES (?, ?, ?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$examId, $questionText, $questionType, $points, $order]);
            return $success ? (int)$this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::createQuestion para Exam $examId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Crea una opción de respuesta para una pregunta.
     * @return int|false El ID de la nueva opción o false en error.
     */
    public function createOption(int $questionId, string $optionText, int $isCorrect = 0, int $order = 0) {
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaQuestionOptions} (question_id, option_text, is_correct, option_order)
                VALUES (?, ?, ?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([$questionId, $optionText, $isCorrect, $order]);
            return $success ? (int)$this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error ExamenPDO::createOption para Question $questionId: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Obtiene el resultado de un examen específico para un alumno.
     * @return array|false
     */
    public function getQuestionsWithOptionsByExamId(int $examId) {
        if (!$this->isConnected()) return false;
        $questions = [];
        $sqlQuestions = "SELECT * FROM {$this->tablaQuestions} WHERE exam_id = ? ORDER BY question_order ASC";
        $sqlOptions = "SELECT * FROM {$this->tablaQuestionOptions} WHERE question_id = ? ORDER BY option_order ASC";

        try {
            $stmtQuestions = $this->pdo->prepare($sqlQuestions);
            $stmtQuestions->execute([$examId]);
            $questionRows = $stmtQuestions->fetchAll();

            if ($questionRows === false) return false;

            $stmtOptions = $this->pdo->prepare($sqlOptions);
            foreach ($questionRows as $questionRow) {
                $stmtOptions->execute([$questionRow['question_id']]);
                $options = $stmtOptions->fetchAll();
                $questionRow['options'] = ($options !== false) ? $options : [];
                $questions[] = $questionRow;
            }
            return $questions;

        } catch (PDOException $e) {
            error_log("Error ExamenPDO::getQuestionsWithOptionsByExamId para Exam $examId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene todos los resultados de exámenes de un estudiante con detalles
     * del examen, lección y curso asociados.
     *
     * @param int $studentId El ID del usuario (alumno).
     * @return array|false Un array con todos los resultados de exámenes y sus detalles,
     * o false en caso de error.
     */
    public function getResultsWithDetailsByStudentId(int $studentId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Nombres de tablas (usar los nombres exactos de tu BD)
        $tablaExamResults = 'exam_results'; // O $this->tablaExamResults
        $tablaExams = 'exams';             // O $this->tablaExams
        $tablaLessons = 'lessons';
        $tablaClasses = 'classes';
        $tablaCourses = 'courses';

        $sql = "SELECT
                    er.result_id, er.submission_time AS item_submission_time, er.score AS item_score,
                    er.tutor_feedback AS item_feedback, er.graded_at AS item_graded_at,
                    ex.exam_id AS item_id, ex.title AS item_title, ex.total_points AS item_total_points,
                    l.lesson_id, l.title AS lesson_title,
                    crs.course_id, crs.title AS course_title
                FROM
                    {$tablaExamResults} AS er
                JOIN
                    {$tablaExams} AS ex ON er.exam_id = ex.exam_id
                JOIN
                    {$tablaLessons} AS l ON ex.lesson_id = l.lesson_id
                JOIN
                    {$tablaClasses} AS cl ON l.class_id = cl.class_id
                JOIN
                    {$tablaCourses} AS crs ON cl.course_id = crs.course_id
                WHERE
                    er.student_id = ?
                ORDER BY
                    crs.title ASC, l.lesson_order ASC, ex.created_at ASC"; // Orden similar al de tareas

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$studentId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en ExamenPDO::getResultsWithDetailsByStudentId para student ID $studentId: " . $e->getMessage());
            return false;
        }
    }

} // Fin clase ExamenPDO
?>