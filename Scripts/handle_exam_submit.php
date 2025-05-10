<?php
// Scripts/handle_exam_submit.php

/**
 * Procesa las respuestas de un examen enviado por un alumno.
 * Verifica la autorización, calcula la nota automáticamente,
 * guarda el resultado en la base de datos y redirige.
 */

// --- 1. Inicialización, Sesión, Carga ---
if (session_status() === PHP_SESSION_NONE) { session_start(); }
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php'; // Carga ExamenPDO, CursoPDO, etc.
require_once __DIR__ . '/../Includes/functions.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// --- 2. Seguridad: Login y Rol Alumno ---
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'alumno') {
    redirectWithError(LOGIN_URL ?? '../pages/intranet.php', 'login_error', 'Debes ser un alumno para entregar exámenes.');
}
$studentId = $_SESSION['user_id'];

// --- 3. Verificar Método POST ---
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header('Location: ' . (defined('DASHBOARD_URL') ? DASHBOARD_URL : '../intranet/intranet.php')); exit;
}

// --- 4. Obtener Datos y Validar CSRF ---
$examId = filter_input(INPUT_POST, 'exam_id', FILTER_VALIDATE_INT);
$classIdForRedirect = filter_input(INPUT_POST, 'class_id_for_redirect', FILTER_VALIDATE_INT); // Para volver
$csrfToken = $_POST['csrf_token'] ?? '';
$answers = $_POST['answers'] ?? []; // Array de respuestas [question_id] => selected_option_id

// URL para redirigir en caso de error (idealmente de vuelta al examen)
$redirectUrlError = $examId ? "../pages/realizar_examen.php?exam_id=$examId&class_id=$classIdForRedirect" : (DASHBOARD_URL ?? '../intranet/intranet.php');
// URL para redirigir en caso de éxito (a la página del curso/clase)
$redirectUrlSuccess = $classIdForRedirect ? "../intranet/ver_curso.php?clase_id=$classIdForRedirect" : (DASHBOARD_URL ?? '../intranet/intranet.php');

if (!$examId) { redirectWithError($redirectUrlError, 'exam_submit_error', 'ID de examen inválido.'); }
if (empty($csrfToken) || !isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    redirectWithError($redirectUrlError, 'exam_submit_error', 'Error de validación de seguridad.');
}

// --- 5. Lógica de Procesamiento y Calificación ---
$pdo = Conexion::obtenerConexion();
if (!$pdo) { redirectWithError($redirectUrlError, 'exam_submit_error', 'Error de conexión.'); }

try {
    $examenPDO = new ExamenPDO();
    $cursoPDO = new CursoPDO(); // Necesario para verificar inscripción

    // 5.1 Obtener detalles del examen y verificar autorización
    $examDetails = $examenPDO->findExamById($examId);
    if (!$examDetails) { throw new Exception("Examen no encontrado.", 404); }

    $lessonDetails = $cursoPDO->findLessonById($examDetails['lesson_id']);
    if (!$lessonDetails) { throw new Exception("Lección no encontrada.", 404); }

    $classId = $lessonDetails['class_id'];
    if (!$cursoPDO->isStudentEnrolled($studentId, $classId)) { throw new Exception("No estás autorizado para entregar este examen.", 403); }

    // 5.2 Verificar si ya existe una entrega (prevenir re-entrega)
    $existingResult = $examenPDO->getExamResultByExamAndStudent($examId, $studentId);
    if ($existingResult) { throw new Exception("Ya has completado este examen.", 409); } // Conflict

    // 5.3 Obtener la estructura del examen (preguntas y opciones CORRECTAS)
    $questionsWithOptions = $examenPDO->getQuestionsWithOptionsByExamId($examId);
    if ($questionsWithOptions === false || empty($questionsWithOptions)) {
        throw new Exception("Error al cargar la estructura del examen o no hay preguntas.", 500);
    }

    // --- 5.4 Calcular Nota Automáticamente ---
    $totalScore = 0;
    $maxPossibleScore = 0;
    $correctAnswersLookup = []; // Para buscar rápido la opción correcta de cada pregunta

    // Construir lookup de respuestas correctas
    foreach ($questionsWithOptions as $question) {
        $maxPossibleScore += ($question['points'] ?? 1); // Sumar puntos posibles
        foreach ($question['options'] as $option) {
            if ($option['is_correct'] == 1) {
                $correctOptionsLookup[$question['question_id']] = $option['option_id'];
                break; // Asume una sola correcta por pregunta
            }
        }
    }

    // Comparar respuestas del alumno con las correctas
    foreach ($questionsWithOptions as $question) {
        $questionId = $question['question_id'];
        $questionPoints = $question['points'] ?? 1;
        $studentAnswerOptionId = $answers[$questionId] ?? null; // ID de la opción que seleccionó el alumno
        $correctOptionId = $correctOptionsLookup[$questionId] ?? null; // ID de la opción correcta

        // Si el alumno respondió y su respuesta coincide con la correcta
        if ($studentAnswerOptionId !== null && $correctOptionId !== null && $studentAnswerOptionId == $correctOptionId) {
            $totalScore += $questionPoints; // Sumar puntos de la pregunta
        }
        // (Si no respondió o la respuesta es incorrecta, no suma puntos)
    }
    // --- Fin Cálculo Nota ---

    // --- 5.5 Guardar el resultado en la BD ---
    $saveSuccess = $examenPDO->saveExamResult(
        $examId,
        $studentId,
        (float)$totalScore, // Guardar la nota calculada
        "Calificado automáticamente.", // Feedback inicial
        true // Indicar que es la primera entrega (para submission_time)
    );

    if ($saveSuccess) {
        // Éxito: redirigir a la página de la clase con mensaje de éxito y nota
        $scoreMessage = "Examen entregado exitosamente. Tu nota: " . $totalScore . ($maxPossibleScore > 0 ? "/" . $maxPossibleScore : "");
        session_write_close();
        redirectWithSuccess($redirectUrlSuccess, 'page_message', $scoreMessage);
    } else {
         throw new Exception("Hubo un error al guardar tu resultado.", 500);
    }

} catch (Exception $e) {
    error_log("Error en handle_exam_submit para Exam $examId, User $studentId: " . $e->getMessage());
    $userMessage = ($e->getCode() >= 400 && $e->getCode() < 500) ? $e->getMessage() : 'Ocurrió un error al procesar tu examen.';
    redirectWithError($redirectUrlError, 'exam_submit_error', $userMessage);
}

?>