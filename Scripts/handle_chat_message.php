<?php
// Scripts/handle_chat_message.php (Adaptado para AJAX)

// 1. Inicialización, Sesión, Carga
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        http_response_code(500);
        if (!headers_sent()) { header('Content-Type: application/json'); }
        echo json_encode(['success' => false, 'message' => 'Error fatal: Sesión no pudo iniciarse (headers sent).']);
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php'; // Para ChatPDO, CursoPDO, UsuarioPDO
require_once __DIR__ . '/../PDO/Conexion.php';

// Definir MAX_CHAT_MESSAGE_LENGTH si no está ya definida (idealmente, esto estaría en Config/config.php)
if (!defined('MAX_CHAT_MESSAGE_LENGTH')) {
    define('MAX_CHAT_MESSAGE_LENGTH', 500); // Ejemplo: 500 caracteres
}
if (!defined('MAX_FILE_SIZE_CHAT_MB')) { // Para el mensaje en ver_curso.php
    define('MAX_FILE_SIZE_CHAT_MB', 5);
}


// Array base para la respuesta JSON
$response = [
    'success' => false,
    'message' => 'No se pudo enviar el mensaje de chat.',
    'new_message_html' => null, // Para el HTML del mensaje enviado
    'new_message_data' => null  // Para datos estructurados del mensaje
];

// 2. Seguridad: Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Debes iniciar sesión para enviar mensajes al chat.';
    echo json_encode($response);
    exit;
}
$senderId = $_SESSION['user_id'];
$senderUsername = $_SESSION['username'] ?? 'Usuario'; // Para el HTML del mensaje

// 3. Verificar Método HTTP
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405); // Method Not Allowed
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

// 4. Obtener Datos del Formulario y Validar CSRF
$classId = filter_input(INPUT_POST, 'class_id', FILTER_VALIDATE_INT);
$messageText = trim($_POST['message_text'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';
// El archivo adjunto se manejaría aquí si se implementa la subida
// $attachment = $_FILES['chat_attachment'] ?? null;

// Validar el token CSRF (asumiendo que el token en ver_curso.php se guarda en $_SESSION['csrf_token_ver_curso'])
if (empty($csrfToken) || !isset($_SESSION['csrf_token_ver_curso']) || !hash_equals($_SESSION['csrf_token_ver_curso'], $csrfToken)) {
    error_log("Error CSRF en handle_chat_message.php. Sender ID: $senderId, Class ID: " . ($classId ?: 'N/A') . ". SessionTokenExpected: csrf_token_ver_curso");
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Recarga la página e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// unset($_SESSION['csrf_token_ver_curso']); // Opcional: invalidar token si es de un solo uso

// 5. Validaciones de Datos
$validationErrors = [];
if (!$classId || $classId <= 0) {
    $validationErrors['class_id'] = "ID de clase no válido.";
}
if (empty($messageText) && (empty($_FILES['chat_attachment']) || $_FILES['chat_attachment']['error'] == UPLOAD_ERR_NO_FILE) ) {
    $validationErrors['message_text'] = "El mensaje no puede estar vacío si no hay adjunto.";
}
if (mb_strlen($messageText) > MAX_CHAT_MESSAGE_LENGTH) {
    $validationErrors['message_text'] = "El mensaje es demasiado largo (máx. " . MAX_CHAT_MESSAGE_LENGTH . " caracteres).";
}

// Lógica de validación y subida de adjuntos (simplificada)
$attachmentPath = null;
$originalFilename = null;
if (isset($_FILES['chat_attachment']) && $_FILES['chat_attachment']['error'] == UPLOAD_ERR_OK) {
    $uploadDir = __DIR__ . '/../resources/uploads/chat_attachments/';
    if (!is_dir($uploadDir)) { mkdir($uploadDir, 0775, true); }
    
    $originalFilename = basename($_FILES['chat_attachment']['name']);
    $fileExtension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
    $safeFilename = uniqid('chat_', true) . '.' . $fileExtension;
    $attachmentPathForDB = 'resources/uploads/chat_attachments/' . $safeFilename; // Ruta relativa desde la raíz del proyecto para la BD
    $destinationOnServer = $uploadDir . $safeFilename;

    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar', 'xls', 'xlsx', 'ppt', 'pptx']; // Lista más amplia
    $maxFileSize = (MAX_FILE_SIZE_CHAT_MB ?? 5) * 1024 * 1024;

    if (!in_array($fileExtension, $allowedExtensions)) {
        $validationErrors['chat_attachment'] = "Tipo de archivo no permitido ('." . $fileExtension . "').";
    } elseif ($_FILES['chat_attachment']['size'] > $maxFileSize) {
        $validationErrors['chat_attachment'] = "El archivo es demasiado grande (máx. " . (MAX_FILE_SIZE_CHAT_MB ?? 5) . "MB).";
    } elseif (!move_uploaded_file($_FILES['chat_attachment']['tmp_name'], $destinationOnServer)) {
        $validationErrors['chat_attachment'] = "Error al subir el archivo adjunto.";
        $attachmentPathForDB = null; $originalFilename = null;
    } else {
        $attachmentPath = $attachmentPathForDB; // Usar la ruta relativa para la BD
    }
} elseif (isset($_FILES['chat_attachment']) && $_FILES['chat_attachment']['error'] != UPLOAD_ERR_NO_FILE) {
    $validationErrors['chat_attachment'] = "Error con el archivo adjunto (código: " . $_FILES['chat_attachment']['error'] . ").";
}


if (!empty($validationErrors)) {
    http_response_code(400);
    $response['message'] = 'Por favor, corrige los errores.';
    $response['errors'] = $validationErrors;
    echo json_encode($response);
    exit;
}

// 6. Lógica para Guardar Mensaje de Chat
$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        throw new Exception("Error de conexión a la base de datos.", 500);
    }

    $chatPDO = new ChatPDO();
    $cursoPDO = new CursoPDO();

    $isAuthorizedToSend = false;
    if ($_SESSION['role'] === 'tutor') {
        $classDetails = $cursoPDO->findClassById($classId);
        if ($classDetails && $classDetails['tutor_id'] == $senderId) {
            $isAuthorizedToSend = true;
        }
    } else {
        if ($cursoPDO->isStudentEnrolled($senderId, $classId)) {
            $isAuthorizedToSend = true;
        }
    }

    if (!$isAuthorizedToSend) {
        throw new Exception("No tienes permiso para enviar mensajes en el chat de esta clase.", 403);
    }

    $pdo->beginTransaction();

    // --- CORRECCIÓN AQUÍ: Usar el nombre de método correcto ---
    // Antes: $messageId = $chatPDO->addMessage($classId, $senderId, $messageText, $attachmentPath, $originalFilename);
    // Ahora:
    $messageId = $chatPDO->createMessage($classId, $senderId, $messageText, $attachmentPath, $originalFilename);

    if ($messageId) {
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Mensaje enviado al chat.';
        
        $sentAt = date('Y-m-d H:i:s');
        $response['new_message_data'] = [
            'message_id' => $messageId,
            'user_id' => $senderId,
            'sender_username' => $senderUsername,
            'message_text' => $messageText,
            'created_at' => $sentAt,
            'attachment_path' => $attachmentPath,
            'original_filename' => $originalFilename,
            'is_current_user' => true
        ];

        $timeFormatted = date('d/m/y H:i', strtotime($sentAt));
        $textFormatted = nl2br(htmlspecialchars($messageText));
        $attachmentHtml = '';
        if ($attachmentPath && $originalFilename) {
            $downloadUrl = BASE_URL . '/' . htmlspecialchars($attachmentPath);
            $escapedOriginalFilename = htmlspecialchars($originalFilename);
            $attachmentHtml = <<<HTML
                <div class="message-attachment" style="margin-top: 5px;">
                    <a href="{$downloadUrl}" target="_blank" download="{$escapedOriginalFilename}">
                        <i class="fas fa-paperclip"></i> {$escapedOriginalFilename}
                    </a>
                </div>
HTML;
        }
        $senderDisplay = "Tú"; // Para el mensaje del usuario actual

        $response['new_message_html'] = <<<HTML
            <div class="chat-message current-user-message" data-message-id="{$messageId}">
                <span class="message-time">{$timeFormatted}</span>
                <div class="message-text">{$textFormatted}</div>
                {$attachmentHtml}
            </div>
HTML;

    } else {
        throw new Exception("Error al guardar el mensaje del chat en la base de datos.", 500);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en handle_chat_message.php para class $classId: (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = "Error al enviar el mensaje al chat: " . $e->getMessage();
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
