<?php
// PDO/MensajePDO.php

/**
 * Clase para manejar las operaciones de Base de Datos para el sistema
 * de mensajería privada (tablas: conversations, conversation_participants, messages).
 */
require_once __DIR__ . '/Conexion.php';

class MensajePDO {

    private $tablaConversations = 'conversations';
    private $tablaParticipants = 'conversation_participants';
    private $tablaMessages = 'messages';
    private $tablaUsers = 'users'; // Para obtener info de participantes/remitentes
    private $tablaUserProfiles = 'user_profiles'; // Para nombres completos
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) { throw new RuntimeException("MensajePDO: No DB connection"); }
    }
    private function isConnected() { return $this->pdo !== null; }
    public function getConversationParticipants(int $conversationId, int $excludeUserId) {
            // Esta consulta asume que tienes una tabla 'users' y opcionalmente 'user_profiles'
            // y una tabla de enlace 'conversation_participants' con 'conversation_id' y 'user_id'.
            $sql = "SELECT u.user_id, u.username, up.first_name, up.paternal_last_name, up.maternal_last_name
                    FROM {$this->tablaParticipants} cp
                    JOIN users u ON cp.user_id = u.user_id
                    LEFT JOIN user_profiles up ON u.user_id = up.user_id
                    WHERE cp.conversation_id = :conversationId AND cp.user_id != :excludeUserId";
            try {
                $stmt = $this->pdo->prepare($sql);
                $stmt->bindParam(':conversationId', $conversationId, PDO::PARAM_INT);
                $stmt->bindParam(':excludeUserId', $excludeUserId, PDO::PARAM_INT);
                $stmt->execute();
                return $stmt->fetchAll(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log("Error en MensajePDO::getConversationParticipants para conversation_id {$conversationId}: " . $e->getMessage());
                return false;
            }
    }
    /**
     * Busca una conversación PRIVADA existente entre dos usuarios específicos.
     * IMPORTANTE: Asume que las conversaciones privadas son solo entre 2 personas.
     * Si no existe, la crea y añade a ambos participantes.
     *
     * @param int $userId1 ID del primer usuario.
     * @param int $userId2 ID del segundo usuario.
     * @return int|false El ID de la conversación (conversation_id) existente o recién creada, o false en error.
     */
    public function findOrCreatePrivateConversation(int $userId1, int $userId2) { // Sin tipo retorno
        if (!$this->isConnected() || $userId1 == $userId2) return false; // No se puede conversar consigo mismo

        // Ordenar IDs para buscar siempre de la misma forma (evitar duplicados inversos)
        $u1 = min($userId1, $userId2);
        $u2 = max($userId1, $userId2);

        // Buscar si YA existe una conversación SOLO entre estos dos usuarios
        // Esta consulta es un poco compleja: busca conversaciones que tengan EXACTAMENTE 2 participantes
        // y que esos participantes sean $u1 y $u2.
        $sqlFind = "SELECT p1.conversation_id
                    FROM {$this->tablaParticipants} p1
                    JOIN {$this->tablaParticipants} p2 ON p1.conversation_id = p2.conversation_id
                    WHERE p1.user_id = ? AND p2.user_id = ?
                    AND (SELECT COUNT(*) FROM {$this->tablaParticipants} p_count WHERE p_count.conversation_id = p1.conversation_id) = 2
                    LIMIT 1";
        try {
            $stmtFind = $this->pdo->prepare($sqlFind);
            $stmtFind->execute([$u1, $u2]);
            $existingConversation = $stmtFind->fetch();

            if ($existingConversation) {
                return (int)$existingConversation['conversation_id']; // Conversación encontrada
            } else {
                // No existe, crear una nueva conversación y añadir participantes
                $this->pdo->beginTransaction();

                // 1. Crear conversación (sin asunto por ahora)
                $sqlCreateConv = "INSERT INTO {$this->tablaConversations} (last_message_at) VALUES (datetime('now', 'localtime'))";
                $stmtCreateConv = $this->pdo->prepare($sqlCreateConv);
                if (!$stmtCreateConv->execute()) {
                    $this->pdo->rollBack(); return false;
                }
                $newConversationId = (int)$this->pdo->lastInsertId();

                // 2. Añadir participante 1
                $sqlAddP1 = "INSERT INTO {$this->tablaParticipants} (conversation_id, user_id) VALUES (?, ?)";
                $stmtAddP1 = $this->pdo->prepare($sqlAddP1);
                if (!$stmtAddP1->execute([$newConversationId, $u1])) {
                     $this->pdo->rollBack(); return false;
                }

                // 3. Añadir participante 2
                $sqlAddP2 = "INSERT INTO {$this->tablaParticipants} (conversation_id, user_id) VALUES (?, ?)";
                $stmtAddP2 = $this->pdo->prepare($sqlAddP2);
                if (!$stmtAddP2->execute([$newConversationId, $u2])) {
                     $this->pdo->rollBack(); return false;
                }

                // Todo OK
                $this->pdo->commit();
                return $newConversationId;
            }

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log("Error en MensajePDO::findOrCreatePrivateConversation entre $userId1 y $userId2: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Envía un nuevo mensaje a una conversación específica.
     * Actualiza el timestamp 'last_message_at' de la conversación.
     *
     * @param int $conversationId ID de la conversación.
     * @param int $senderId ID del usuario que envía.
     * @param string $messageText Texto del mensaje.
     * @param ?string $attachmentPath Ruta al adjunto (si lo hay).
     * @param ?string $originalFilename Nombre original del adjunto (si lo hay).
     * @return int|false El ID del nuevo mensaje o false en error.
     */
    public function sendMessage(int $conversationId, int $senderId, string $messageText, ?string $attachmentPath = null, ?string $originalFilename = null) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Verificar que el remitente sea participante (medida de seguridad extra)
        // Podrías hacer esto aquí o en el script handler antes de llamar a sendMessage
        // $isParticipantSql = "SELECT 1 FROM {$this->tablaParticipants} WHERE conversation_id = ? AND user_id = ? LIMIT 1";
        // $stmtCheck = $this->pdo->prepare($isParticipantSql);
        // $stmtCheck->execute([$conversationId, $senderId]);
        // if ($stmtCheck->fetchColumn() === false) {
        //      error_log("Seguridad: Usuario $senderId intentó enviar mensaje a conversación $conversationId sin ser participante.");
        //      return false; // No autorizado a enviar
        // }

        $sqlInsertMsg = "INSERT INTO {$this->tablaMessages}
                            (conversation_id, sender_id, message_text, attachment_path, original_filename, sent_at)
                         VALUES (?, ?, ?, ?, ?, datetime('now', 'localtime'))";
        $sqlUpdateConv = "UPDATE {$this->tablaConversations} SET last_message_at = datetime('now', 'localtime') WHERE conversation_id = ?";

        try {
            $this->pdo->beginTransaction();

            // Insertar mensaje
            $stmtInsert = $this->pdo->prepare($sqlInsertMsg);
            $msgSuccess = $stmtInsert->execute([$conversationId, $senderId, $messageText, $attachmentPath, $originalFilename]);
            if (!$msgSuccess) { $this->pdo->rollBack(); return false; }
            $newMessageId = (int)$this->pdo->lastInsertId();

            // Actualizar fecha último mensaje en conversación
            $stmtUpdate = $this->pdo->prepare($sqlUpdateConv);
            if (!$stmtUpdate->execute([$conversationId])) {
                // No es error fatal, pero loguear
                error_log("Advertencia: No se pudo actualizar last_message_at para conversation_id $conversationId");
            }

            $this->pdo->commit();
            return $newMessageId;

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log("Error en MensajePDO::sendMessage: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene las conversaciones en las que participa un usuario.
     * Incluye el último mensaje y los otros participantes para la vista de inbox.
     * (Esta es una versión simplificada, se puede hacer más compleja/eficiente)
     *
     * @param int $userId ID del usuario.
     * @return array|false Lista de conversaciones o false en error.
     */
    public function getConversationsForUser(int $userId) { // Sin tipo retorno
        if (!$this->isConnected()) return false;

        // Obtener las conversation_id en las que participa el usuario
        // y ordenar por el último mensaje de la conversación
        $sqlConvos = "SELECT
                        c.conversation_id, c.subject, c.last_message_at
                      FROM {$this->tablaConversations} c
                      JOIN {$this->tablaParticipants} p ON c.conversation_id = p.conversation_id
                      WHERE p.user_id = ?
                      ORDER BY c.last_message_at DESC";

        try {
            $stmtConvos = $this->pdo->prepare($sqlConvos);
            $stmtConvos->execute([$userId]);
            $conversations = $stmtConvos->fetchAll();

            if (empty($conversations)) return [];

            // Para cada conversación, obtener los otros participantes y el último mensaje (esto puede ser ineficiente)
            $result = [];
            $sqlOtherP = "SELECT u.id AS user_id, u.username, up.first_name, up.paternal_last_name
                          FROM {$this->tablaParticipants} p
                          JOIN {$this->tablaUsers} u ON p.user_id = u.id
                          LEFT JOIN {$this->tablaUserProfiles} up ON u.id = up.user_id
                          WHERE p.conversation_id = ? AND p.user_id != ?";
            $sqlLastMsg = "SELECT message_text, sender_id, sent_at FROM {$this->tablaMessages}
                           WHERE conversation_id = ? ORDER BY sent_at DESC LIMIT 1";

            $stmtOtherP = $this->pdo->prepare($sqlOtherP);
            $stmtLastMsg = $this->pdo->prepare($sqlLastMsg);

            foreach ($conversations as $convo) {
                $convoId = $convo['conversation_id'];
                // Obtener otros participantes
                $stmtOtherP->execute([$convoId, $userId]);
                $convo['participants'] = $stmtOtherP->fetchAll();
                // Obtener último mensaje
                $stmtLastMsg->execute([$convoId]);
                $convo['last_message'] = $stmtLastMsg->fetch();
                // Podrías añadir conteo de mensajes no leídos aquí con otra consulta más compleja
                $convo['unread_count'] = 0; // Placeholder

                $result[] = $convo;
            }
            return $result;

        } catch (PDOException $e) {
            error_log("Error en MensajePDO::getConversationsForUser para User $userId: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Obtiene los mensajes de una conversación específica.
     * Verifica que el usuario solicitante sea participante.
     *
     * @param int $conversationId ID de la conversación.
     * @param int $userId ID del usuario que solicita (para autorización).
     * @param int $limit Límite de mensajes a obtener.
     * @return array|false Lista de mensajes o false en error/no autorizado.
     */
    public function getMessagesForConversation(int $conversationId, int $userId, int $limit = 100) { // Sin tipo retorno
         if (!$this->isConnected()) return false;

         // 1. Verificar si el usuario pertenece a esta conversación
         $sqlCheck = "SELECT 1 FROM {$this->tablaParticipants} WHERE conversation_id = ? AND user_id = ? LIMIT 1";
          try {
               $stmtCheck = $this->pdo->prepare($sqlCheck);
               $stmtCheck->execute([$conversationId, $userId]);
               if ($stmtCheck->fetchColumn() === false) {
                    error_log("Acceso denegado: User $userId intentó leer conversación $conversationId");
                    return false; // No autorizado
               }

               // 2. Obtener mensajes si está autorizado
               $sqlMsgs = "SELECT
                              m.message_id, m.conversation_id, m.sender_id, m.message_text,
                              m.attachment_path, m.original_filename, m.sent_at,
                              u.username AS sender_username
                          FROM {$this->tablaMessages} AS m
                          LEFT JOIN {$this->tablaUsers} AS u ON m.sender_id = u.id
                          WHERE m.conversation_id = ?
                          ORDER BY m.sent_at ASC -- Mostrar mensajes más antiguos primero
                          LIMIT ?"; // O usar offset para paginación

               $stmtMsgs = $this->pdo->prepare($sqlMsgs);
               $stmtMsgs->bindValue(1, $conversationId, PDO::PARAM_INT);
               $stmtMsgs->bindValue(2, $limit, PDO::PARAM_INT);
               $stmtMsgs->execute();
               return $stmtMsgs->fetchAll();

               // 3. (Opcional) Actualizar last_read_at para este usuario en esta conversación
               // $sqlUpdateRead = "UPDATE {$this->tablaParticipants} SET last_read_at = datetime('now', 'localtime') WHERE conversation_id = ? AND user_id = ?";
               // $stmtUpdateRead = $this->pdo->prepare($sqlUpdateRead);
               // $stmtUpdateRead->execute([$conversationId, $userId]);

          } catch (PDOException $e) {
               error_log("Error en MensajePDO::getMessagesForConversation para Convo $conversationId, User $userId: " . $e->getMessage());
               return false;
          }
    }


     /**
     * Verifica si un usuario específico es participante de una conversación.
     *
     * @param int $conversationId ID de la conversación.
     * @param int $userId ID del usuario.
     * @return bool True si es participante, False si no o en error.
     * @throws PDOException Si ocurre un error de BD.
     */
    public function isUserParticipant(int $conversationId, int $userId) { // Sin tipo :bool
        if (!$this->isConnected()) return false; // O lanzar excepción

        $sql = "SELECT 1 FROM {$this->tablaParticipants}
                WHERE conversation_id = ? AND user_id = ? LIMIT 1";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$conversationId, $userId]);
            return ($stmt->fetchColumn() !== false); // True si encuentra la fila, false si no
        } catch (PDOException $e) {
             error_log("Error en MensajePDO::isUserParticipant para Convo $conversationId, User $userId: " . $e->getMessage());
             throw $e; // Relanzar para que el handler lo capture como error grave
        }
    }

    

} // Fin clase MensajePDO
?>