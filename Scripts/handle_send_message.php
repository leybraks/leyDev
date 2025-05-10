<?php
// Scripts/handle_send_message.php (Adaptado para AJAX)

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
require_once __DIR__ . '/../Includes/autoload.php'; // Para MensajePDO, UsuarioPDO
require_once __DIR__ . '/../PDO/Conexion.php';

// Definir MAX_MESSAGE_LENGTH si no está ya definida (idealmente, esto estaría en Config/config.php)
if (!defined('MAX_MESSAGE_LENGTH')) {
    define('MAX_MESSAGE_LENGTH', 1000); // Ejemplo: 1000 caracteres
}

// Array base para la respuesta JSON
$response = [
    'success' => false,
    'message' => 'No se pudo enviar el mensaje.',
    'new_message_html' => null, // Para el HTML del mensaje enviado
    'new_message_data' => null  // Para datos estructurados del mensaje
];

// 2. Seguridad: Login
if (!isset($_SESSION['user_id'])) {
    http_response_code(403); // Forbidden
    $response['message'] = 'Debes iniciar sesión para enviar mensajes.';
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
$conversationId = filter_input(INPUT_POST, 'conversation_id', FILTER_VALIDATE_INT);
$messageText = trim($_POST['message_text'] ?? '');
$csrfToken = $_POST['csrf_token'] ?? '';

// Validar el token CSRF
// Asumiendo que el token en ver_conversacion.php se guarda en $_SESSION['csrf_token_conversation']
// o si es un token general, podría ser $_SESSION['csrf_token']
// Ajusta 'csrf_token_conversation' si usas un nombre diferente en el formulario de envío de mensajes.
if (empty($csrfToken) || !isset($_SESSION['csrf_token_conversation']) || !hash_equals($_SESSION['csrf_token_conversation'], $csrfToken)) {
    error_log("Error CSRF en handle_send_message.php. Sender ID: $senderId. Session Token Expected: csrf_token_conversation");
    http_response_code(403);
    $response['message'] = 'Error de validación de seguridad. Recarga la página e intenta de nuevo.';
    echo json_encode($response);
    exit;
}
// unset($_SESSION['csrf_token_conversation']); // Opcional: invalidar token si es de un solo uso


// 5. Validaciones de Datos
$validationErrors = [];
if (!$conversationId || $conversationId <= 0) {
    $validationErrors['conversation_id'] = "ID de conversación no válido.";
}
if (empty($messageText)) {
    $validationErrors['message_text'] = "El mensaje no puede estar vacío.";
}
// Ahora MAX_MESSAGE_LENGTH está definida
if (mb_strlen($messageText) > MAX_MESSAGE_LENGTH) {
    $validationErrors['message_text'] = "El mensaje es demasiado largo (máx. " . MAX_MESSAGE_LENGTH . " caracteres).";
}


if (!empty($validationErrors)) {
    http_response_code(400); // Bad Request
    $response['message'] = 'Por favor, corrige los errores.';
    $response['errors'] = $validationErrors; // Enviar errores específicos
    echo json_encode($response);
    exit;
}

// 6. Lógica para Guardar Mensaje
$pdo = null;
try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        throw new Exception("Error de conexión a la base de datos.", 500);
    }

    $mensajePDO = new MensajePDO();

    if (!$mensajePDO->isUserParticipant($senderId, $conversationId)) {
        throw new Exception("No tienes permiso para enviar mensajes en esta conversación.", 403);
    }

    $pdo->beginTransaction();

    $messageId = $mensajePDO->sendMessage($conversationId, $senderId, $messageText);

    if ($messageId) {
        $pdo->commit();
        $response['success'] = true;
        $response['message'] = 'Mensaje enviado exitosamente.';
        
        $sentAt = date('Y-m-d H:i:s');
        $response['new_message_data'] = [
            'message_id' => $messageId,
            'sender_id' => $senderId,
            'sender_username' => $senderUsername,
            'message_text' => $messageText,
            'sent_at' => $sentAt,
            'is_current_user' => true
        ];

        $timeFormatted = date('d/m H:i', strtotime($sentAt));
        $textFormatted = nl2br(htmlspecialchars($messageText));
        $response['new_message_html'] = <<<HTML
            <div class="chat-message current-user-message" data-message-id="{$messageId}">
                <span class="message-time">{$timeFormatted}</span>
                <div class="message-text">{$textFormatted}</div>
            </div>
HTML;

    } else {
        throw new Exception("Error al guardar el mensaje en la base de datos.", 500);
    }

} catch (Exception $e) {
    if ($pdo && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Error en handle_send_message.php para conversation $conversationId: (" . $e->getCode() . ") " . $e->getMessage());
    $response['message'] = "Error al enviar el mensaje: " . $e->getMessage();
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
