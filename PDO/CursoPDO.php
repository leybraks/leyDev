<?php
require_once __DIR__ . '/Conexion.php';

class CursoPDO {

    private $tablaCourses = 'courses';
    private $tablaClasses = 'classes';
    private $tablaEnrollments = 'enrollments';
    private $tablaLessons = 'lessons';
    private $tablaUsers = 'users';
    private $pdo;

    // Inicializa la conexión a la base de datos
    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) {
            throw new RuntimeException("CursoPDO no pudo obtener la conexión a la BD.");
        }
    }

    // Verifica si la conexión está activa
    private function isConnected() {
        return $this->pdo !== null;
    }

    // Devuelve los cursos en los que está inscrito un estudiante
    public function getCoursesByStudentId(int $studentId) {
        if (!$this->isConnected()) return false;

        $sql = "SELECT
                    c.course_id,
                    c.title AS course_title,
                    c.description AS course_description,
                    c.image_url AS course_image_url,
                    cl.class_id,
                    cl.semester AS class_semester,
                    cl.schedule AS class_schedule,
                    t.username AS tutor_username
                FROM {$this->tablaEnrollments} AS e
                JOIN {$this->tablaClasses} AS cl ON e.class_id = cl.class_id
                JOIN {$this->tablaCourses} AS c ON cl.course_id = c.course_id
                LEFT JOIN {$this->tablaUsers} AS t ON cl.tutor_id = t.id
                WHERE e.user_id = ?
                ORDER BY c.title ASC, cl.semester DESC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$studentId]);
            $result = $stmt->fetchAll();
            return ($result !== false) ? $result : false;
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getCoursesByStudentId para user ID $studentId: " . $e->getMessage());
            return false;
        }
    }

    // Obtiene todos los cursos disponibles
    public function getAllCourses() {
        if (!$this->isConnected()) return false;
        try {
            $sql = "SELECT course_id, title, description, image_url FROM {$this->tablaCourses} ORDER BY title";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getAllCourses: " . $e->getMessage());
            return false;
        }
    }

    // Busca un curso específico por su ID
    public function findCourseById(int $courseId) {
        if (!$this->isConnected()) return false;
        try {
            $sql = "SELECT course_id, title, description, image_url FROM {$this->tablaCourses} WHERE course_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$courseId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::findCourseById para ID $courseId: " . $e->getMessage());
            return false;
        }
    }

    // Verifica si un estudiante está inscrito en una clase
    public function isStudentEnrolled(int $userId, int $classId) {
        if (!$this->isConnected()) {
            throw new PDOException("No hay conexión a la BD en isStudentEnrolled.", 0);
        }
        $sql = "SELECT 1 FROM {$this->tablaEnrollments} WHERE user_id = ? AND class_id = ? LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId, $classId]);
            return ($stmt->fetchColumn() !== false);
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::isStudentEnrolled para user $userId, class $classId: " . $e->getMessage());
            throw $e;
        }
    }

    // Inscribe a un estudiante en una clase
    public function enrollStudent(int $userId, int $classId) {
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaEnrollments} (user_id, class_id) VALUES (?, ?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId, $classId]);
        } catch (PDOException $e) {
            if ($e->getCode() == '23000' || $e->getCode() == 19) {
                error_log("Intento de inscripción duplicada: User $userId, Class $classId");
            } else {
                error_log("Error en CursoPDO::enrollStudent para user $userId, class $classId: " . $e->getMessage());
            }
            return false;
        }
    }

    // Obtiene los datos de una clase específica por ID
    public function findClassById(int $classId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT
                    cl.class_id, cl.semester, cl.schedule, cl.course_id,
                    cl.capacity,
                    c.title AS course_title, c.description AS course_description, c.image_url AS course_image_url,
                    t.username AS tutor_username, cl.tutor_id
                FROM {$this->tablaClasses} AS cl
                JOIN {$this->tablaCourses} AS c ON cl.course_id = c.course_id
                LEFT JOIN {$this->tablaUsers} AS t ON cl.tutor_id = t.id
                WHERE cl.class_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::findClassById para ID $classId: " . $e->getMessage());
            return false;
        }
    }

    // Cuenta cuántos estudiantes están inscritos en una clase
    public function getEnrollmentCountByClassId(int $classId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT COUNT(*) FROM {$this->tablaEnrollments} WHERE class_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return (int)$stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getEnrollmentCountByClassId para class $classId: " . $e->getMessage());
            return false;
        }
    }

    // Devuelve los cursos con clases disponibles en las que el estudiante aún no se ha inscrito
    public function getCoursesWithAvailableClasses(int $studentId) {
        if (!$this->isConnected()) return false;

        $sql = "SELECT
                    c.course_id, c.title AS course_title, c.description AS course_description, c.image_url AS course_image_url,
                    cl.class_id, cl.semester AS class_semester, cl.schedule AS class_schedule, cl.capacity AS class_capacity,
                    t.username AS tutor_username
                FROM {$this->tablaClasses} AS cl
                JOIN {$this->tablaCourses} AS c ON cl.course_id = c.course_id
                LEFT JOIN {$this->tablaUsers} AS t ON cl.tutor_id = t.id
                WHERE NOT EXISTS (
                    SELECT 1 FROM {$this->tablaEnrollments} e
                    WHERE e.class_id = cl.class_id AND e.user_id = ?
                )
                ORDER BY c.title ASC, cl.semester DESC, cl.schedule ASC";

        $params = [$studentId];

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $availableClassesFlat = $stmt->fetchAll();

            if ($availableClassesFlat === false) {
                return false;
            }

            $groupedCourses = [];
            foreach ($availableClassesFlat as $classInfo) {
                $courseId = $classInfo['course_id'];

                if (!isset($groupedCourses[$courseId])) {
                    $groupedCourses[$courseId] = [
                        'details' => [
                            'course_id' => $courseId,
                            'title' => $classInfo['course_title'],
                            'description' => $classInfo['course_description'],
                            'image_url' => $classInfo['course_image_url']
                        ],
                        'classes' => []
                    ];
                }

                $groupedCourses[$courseId]['classes'][] = [
                    'class_id' => $classInfo['class_id'],
                    'semester' => $classInfo['class_semester'],
                    'schedule' => $classInfo['class_schedule'],
                    'tutor_username' => $classInfo['tutor_username'],
                    'capacity' => $classInfo['class_capacity']
                ];
            }

            return $groupedCourses;

        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getCoursesWithAvailableClasses para user ID $studentId: " . $e->getMessage());
            return false;
        }
    }

    // Devuelve las lecciones asociadas a una clase específica
    public function getLessonsByClassId(int $classId) {
        if (!$this->isConnected()) return false;
        $sql = "SELECT lesson_id, class_id, title, content, lesson_order
                FROM lessons
                WHERE class_id = ?
                ORDER BY lesson_order ASC, created_at ASC";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getLessonsByClassId para class $classId: " . $e->getMessage());
            return false;
        }
    }

    // Dentro de la clase CursoPDO en PDO/CursoPDO.php

    /**
     * Obtiene todas las clases asignadas a un tutor específico.
     * Incluye información del curso asociado y opcionalmente el número de inscritos.
     *
     * @param int $tutorId El ID del usuario (tutor).
     * @return array|false Un array con las clases asignadas o false en error.
     */
    public function getClassesByTutorId(int $tutorId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Consulta para obtener clases, curso asociado y número de inscritos
        $sql = "SELECT
                    cl.class_id, cl.semester, cl.schedule, cl.capacity,
                    c.course_id, c.title AS course_title,
                    (SELECT COUNT(*) FROM {$this->tablaEnrollments} e WHERE e.class_id = cl.class_id) AS enrolled_count
                FROM {$this->tablaClasses} AS cl
                JOIN {$this->tablaCourses} AS c ON cl.course_id = c.course_id
                WHERE cl.tutor_id = ?
                ORDER BY c.title ASC, cl.semester DESC, cl.schedule ASC";

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$tutorId]);
            return $stmt->fetchAll(); // Devolver todas las clases para este tutor
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getClassesByTutorId para tutor ID $tutorId: " . $e->getMessage());
            return false;
        }
    }


       /**
     * Encuentra los detalles de una lección específica por su ID.
     * Útil para verificar a qué clase pertenece antes de crear una tarea.
     *
     * @param int $lessonId ID de la lección.
     * @return array|false Datos de la lección o false si no existe/error.
     */
    public function findLessonById(int $lessonId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;
        // Asume que tablaLessons está definido como 'lessons'
        $sql = "SELECT lesson_id, class_id, title, content
                FROM {$this->tablaLessons}
                WHERE lesson_id = ?";
        try {
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$lessonId]);
             return $stmt->fetch();
        } catch (PDOException $e) {
             error_log("Error en CursoPDO::findLessonById para ID $lessonId: " . $e->getMessage());
             return false;
        }
    }


/**
     * Obtiene las clases de un tutor junto con los estudiantes inscritos en cada una.
     *
     * @param int $tutorId El ID del usuario (tutor).
     * @return array|false Un array donde cada elemento es una clase con sus detalles
     * y una sub-lista de 'students' inscritos. Devuelve un array vacío si
     * el tutor no tiene clases, o false en caso de error de BD.
     * Ejemplo de estructura de retorno:
     * [
     * CLASS_ID_1 => [
     * 'class_details' => ['class_id' => 1, 'course_title' => 'PHP', ...],
     * 'students' => [
     * ['user_id' => 10, 'username' => 'alumno_a', 'email' => 'a@a.com'],
     * ['user_id' => 12, 'username' => 'alumno_b', 'email' => 'b@b.com']
     * ]
     * ],
     * CLASS_ID_2 => [ ... ]
     * ]
     */
    public function getClassesWithEnrolledStudentsByTutorId(int $tutorId) { // <<< SE QUITÓ EL : bool
        if (!$this->isConnected()) {
            error_log("Error en CursoPDO::getClassesWithEnrolledStudentsByTutorId - No hay conexión a BD.");
            return false; // Error de conexión
        }

        // Paso 1: Obtener todas las clases asignadas a este tutor
        $sqlClasses = "SELECT
                            cl.class_id, cl.semester, cl.schedule,
                            c.course_id, c.title AS course_title
                        FROM {$this->tablaClasses} AS cl
                        JOIN {$this->tablaCourses} AS c ON cl.course_id = c.course_id
                        WHERE cl.tutor_id = ?
                        ORDER BY c.title ASC, cl.semester DESC";
        try {
            $stmtClasses = $this->pdo->prepare($sqlClasses);
            $stmtClasses->execute([$tutorId]);
            $classes = $stmtClasses->fetchAll();

            if ($classes === false) { // Error en fetchAll
                error_log("Error en CursoPDO::getClassesWithEnrolledStudentsByTutorId - Falló fetchAll para clases del tutor ID $tutorId.");
                return false;
            }
            if (empty($classes)) {
                return []; // El tutor no tiene clases asignadas, devuelve array vacío (éxito, pero sin datos)
            }

            // Paso 2: Para cada clase, obtener los estudiantes inscritos
            $result = [];
            // Definir nombres de tabla explícitamente si no son propiedades de esta clase
            $tablaEnrollments = 'enrollments'; // O $this->tablaEnrollments
            $tablaUsers = 'users';         // O $this->tablaUsers

            $sqlStudents = "SELECT
                                u.id AS user_id, u.username, u.email
                            FROM {$tablaEnrollments} AS e
                            JOIN {$tablaUsers} AS u ON e.user_id = u.id
                            WHERE e.class_id = ?";
            $stmtStudents = $this->pdo->prepare($sqlStudents);

            foreach ($classes as $class) {
                $stmtStudents->execute([$class['class_id']]);
                $studentsInClass = $stmtStudents->fetchAll();

                if ($studentsInClass === false) { // Error en fetchAll para estudiantes
                     error_log("Error en CursoPDO::getClassesWithEnrolledStudentsByTutorId - Falló fetchAll para estudiantes de class_id " . $class['class_id']);
                     // Decidir si continuar con otras clases o devolver error general.
                     // Por ahora, continuamos y esa clase no tendrá estudiantes.
                     $studentsInClass = []; // Asignar array vacío para esta clase
                }

                $result[$class['class_id']] = [
                    'class_details' => $class,
                    'students' => $studentsInClass
                ];
            }
            return $result; // Devuelve el array con todas las clases y sus estudiantes

        } catch (PDOException $e) {
            error_log("Error PDO en CursoPDO::getClassesWithEnrolledStudentsByTutorId para tutor ID $tutorId: " . $e->getMessage());
            return false; // Error de BD
        }
    }
 /**
     * Obtiene todos los estudiantes inscritos en una clase específica.
     *
     * @param int $classId El ID de la clase.
     * @return array|false Un array con los detalles de los estudiantes (id, username, email, nombres, apellidos)
     * o false en caso de error. Array vacío si no hay estudiantes.
     */
    public function getEnrolledStudentsByClassId(int $classId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Nombres de tablas (asumimos que tu tabla de perfiles se llama 'user_profiles')
        $tablaEnrollments = 'enrollments'; // O $this->tablaEnrollments
        $tablaUsers = 'users';           // O $this->tablaUsers
        $tablaUserProfiles = 'user_profiles'; // Nombre de tu tabla de perfiles

        $sql = "SELECT
                    u.id AS user_id, u.username, u.email,
                    up.first_name, up.paternal_last_name, up.maternal_last_name
                FROM {$tablaEnrollments} e
                JOIN {$tablaUsers} u ON e.user_id = u.id
                LEFT JOIN {$tablaUserProfiles} up ON u.id = up.user_id -- <<< CORRECCIÓN AQUÍ
                WHERE e.class_id = ?
                ORDER BY up.paternal_last_name ASC, up.maternal_last_name ASC, up.first_name ASC, u.username ASC"; // Ordenar por apellidos y nombre
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$classId]);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            error_log("Error en CursoPDO::getEnrolledStudentsByClassId para class_id $classId: " . $e->getMessage());
            return false;
        }
    }

}
?>
