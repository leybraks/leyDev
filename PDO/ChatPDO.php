<?php
// PDO/ChatPDO.php

/**
 * Clase para manejar las operaciones de BD para la tabla 'chat_messages'.
 */
require_once __DIR__ . '/Conexion.php';

class ChatPDO {

    private $tablaChatMessages = 'chat_messages';
    private $tablaUsers = 'users'; // Para obtener nombre del remitente
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) { throw new RuntimeException("ChatPDO: No DB connection"); }
    }
    private function isConnected() { return $this->pdo !== null; }

    /**
     * Obtiene los últimos N mensajes para una clase específica.
     * Une con users para obtener el nombre del remitente.
     *
     * @param int $classId ID de la clase.
     * @param int $limit Número máximo de mensajes a obtener.
     * @return array|false Array de mensajes o false en error.
     */
    public function getMessagesByClassId(int $classId, int $limit = 50) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        $sql = "SELECT
                    cm.message_id, cm.class_id, cm.user_id, cm.message_text,
                    cm.attachment_path, cm.original_filename, cm.created_at,
                    u.username AS sender_username
                FROM {$this->tablaChatMessages} AS cm
                JOIN {$this->tablaUsers} AS u ON cm.user_id = u.id
                WHERE cm.class_id = ?
                ORDER BY cm.created_at DESC -- Mostrar los más recientes primero
                LIMIT ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            // PDO necesita que LIMIT sea un entero directamente en bindValue/bindParam o al construir SQL.
            // Usaremos bindValue para asegurar el tipo.
            $stmt->bindValue(1, $classId, PDO::PARAM_INT);
            $stmt->bindValue(2, $limit, PDO::PARAM_INT);
            $stmt->execute();
            // Invertir el resultado para tener los más antiguos primero para mostrar
            $messages = array_reverse($stmt->fetchAll());
            return $messages;
        } catch (PDOException $e) {
            error_log("Error ChatPDO::getMessagesByClassId para Class $classId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Guarda un nuevo mensaje de chat en la base de datos.
     *
     * @param int $classId ID de la clase.
     * @param int $userId ID del usuario que envía.
     * @param string $messageText Texto del mensaje.
     * @param ?string $attachmentPath Ruta al archivo adjunto (si existe).
     * @param ?string $originalFilename Nombre original del adjunto (si existe).
     *
     * @return int|false El ID del nuevo mensaje o false en error.
     */
    public function createMessage(int $classId, int $userId, string $messageText, ?string $attachmentPath = null, ?string $originalFilename = null) { // Sin tipo retorno
        if (!$this->isConnected()) return false;
        $sql = "INSERT INTO {$this->tablaChatMessages}
                    (class_id, user_id, message_text, attachment_path, original_filename, created_at)
                VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))";
        try {
            $stmt = $this->pdo->prepare($sql);
            $success = $stmt->execute([
                $classId,
                $userId,
                $messageText,
                $attachmentPath, // Puede ser NULL
                $originalFilename // Puede ser NULL
            ]);
            return $success ? (int)$this->pdo->lastInsertId() : false;
        } catch (PDOException $e) {
            error_log("Error ChatPDO::createMessage para Class $classId, User $userId: " . $e->getMessage());
            return false;
        }
    }

} // Fin clase ChatPDO
?>