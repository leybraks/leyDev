<?php
require_once __DIR__ . '/Conexion.php';

class UsuarioPDO {

    private $tablaUsers = 'users';
    private $tablaProfiles = 'user_profiles';
    private $pdo;

    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) {
            throw new RuntimeException("UsuarioPDO no pudo obtener la conexión a la BD.");
        }
    }

    private function isConnected() {
        return $this->pdo !== null;
    }

    // Busca un usuario por su nombre de usuario
    public function findByUsername(string $username) {
        if (!$this->isConnected()) return false;
        try {
            $sql = "SELECT id, username, email, password_hash, active, id_eCon, perfil_completo, role
                    FROM {$this->tablaUsers} WHERE username = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$username]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::findByUsername para '$username': " . $e->getMessage());
            return false;
        }
    }

    // Busca un usuario por su correo electrónico
    public function findByEmail(string $email) {
        if (!$this->isConnected()) return false;
        try {
            $sql = "SELECT id, username, email, active, id_eCon
                    FROM {$this->tablaUsers}
                    WHERE email = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$email]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::findByEmail para '$email': " . $e->getMessage());
            return false;
        }
    }

    // Crea un nuevo usuario en la base de datos
    // Dentro de la clase UsuarioPDO en PDO/UsuarioPDO.php

    /**
     * Crea un nuevo usuario y su perfil asociado en la base de datos.
     * Maneja tanto registro normal (con verificación) como registro social (sin verificación).
     *
     * @param string $username
     * @param string $email
     * @param string $hashedPassword Contraseña hasheada (o placeholder para social).
     * @param ?string $codigoManual Código corto o NULL si es login social/verificado.
     * @param ?string $tokenEnlace Token largo o NULL si es login social/verificado.
     * @param ?int $expiresAt Timestamp de expiración o NULL si es login social/verificado.
     * @param ?string $googleId (NUEVO) ID de Google si es registro social, NULL si no.
     *
     * @return int|false El ID del nuevo usuario o false en caso de error.
     */
    // Ajustamos parámetros para aceptar NULL y añadimos googleId opcional
    public function create(string $username, string $email, string $hashedPassword, ?string $codigoManual, ?string $tokenEnlace, ?int $expiresAt, ?string $googleId = null) { // Sin tipo retorno int|false
        if (!$this->isConnected()) return false;

        // Determinar estado inicial basado en si es registro normal o social (Google)
        $initialActive = ($googleId !== null) ? 1 : 0; // Activo si viene de Google
        $initialIdEcon = ($googleId !== null) ? 1 : 0; // Verificado si viene de Google
        $initialPerfilCompleto = 0; // Siempre empieza incompleto
        $initialRole = 'alumno';    // Rol por defecto

        // Adaptar SQL para incluir google_id (asegúrate que la columna exista)
        $sqlUser = "INSERT INTO {$this->tablaUsers}
                        (username, email, password_hash, active, id_eCon, codigo, verification_token, token_expires_at, perfil_completo, role, google_id)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"; // 11 parámetros ahora

        try {
            $this->pdo->beginTransaction(); // Iniciar transacción

            $stmtUser = $this->pdo->prepare($sqlUser);
            // Pasar los valores (NULL es aceptado por PDO para columnas nullable)
            $userSuccess = $stmtUser->execute([
                $username,
                $email,
                $hashedPassword,
                $initialActive,
                $initialIdEcon,
                $codigoManual,      // Puede ser NULL
                $tokenEnlace,       // Puede ser NULL
                $expiresAt,         // Puede ser NULL
                $initialPerfilCompleto,
                $initialRole,
                $googleId           // Puede ser NULL
            ]);

            if (!$userSuccess) {
                $this->pdo->rollBack();
                error_log("Error en UsuarioPDO::create - Falló la inserción del usuario.");
                return false;
            }

            $lastUserId = (int)$this->pdo->lastInsertId();
            // createProfile no necesita cambios, solo recibe el ID
            $profileSuccess = $this->createProfile($lastUserId);

            if ($profileSuccess) {
                $this->pdo->commit();
                return $lastUserId;
            } else {
                $this->pdo->rollBack();
                error_log("Error en UsuarioPDO::create - Falló createProfile para user ID: " . $lastUserId);
                return false;
            }
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            error_log("Error PDO en UsuarioPDO::create: " . $e->getMessage() . " | SQL Code: " . $e->getCode());
            return false;
        }
    } // Fin del método create modificado

    // Crea un perfil vacío para un usuario
    private function createProfile(int $userId) {
        $sql = "INSERT INTO {$this->tablaProfiles} (user_id) VALUES (?)";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$userId]);
        } catch (PDOException $e) {
            error_log("Error PDO en createProfile para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Busca un usuario por su código de verificación
    public function findByVerificationCode(string $code) {
         if (!$this->isConnected() || empty($code)) return false;
         try {
             $sql = "SELECT id, username, active, id_eCon, token_expires_at FROM {$this->tablaUsers} WHERE codigo = ?";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$code]);
             return $stmt->fetch();
         } catch (PDOException $e) {
             error_log("Error en UsuarioPDO::findByVerificationCode: " . $e->getMessage());
             return false;
         }
    }

    // Busca un usuario por su token de verificación
    public function findByVerificationToken(string $token) {
        if (!$this->isConnected() || empty($token)) return false;
         try {
             $sql = "SELECT id, username, active, id_eCon, token_expires_at FROM {$this->tablaUsers} WHERE verification_token = ?";
             $stmt = $this->pdo->prepare($sql);
             $stmt->execute([$token]);
             return $stmt->fetch();
         } catch (PDOException $e) {
             error_log("Error en UsuarioPDO::findByVerificationToken: " . $e->getMessage());
             return false;
         }
    }

    // Activa un usuario (verificación)
    public function activateUser(int $userId) {
        if (!$this->isConnected()) return false;
        $sql = "UPDATE {$this->tablaUsers}
                SET active = 1, id_eCon = 1, codigo = NULL, verification_token = NULL, token_expires_at = NULL
                WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::activateUser para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Marca el perfil de un usuario como completo
    public function markProfileAsComplete(int $userId) {
        if (!$this->isConnected()) return false;
        $sql = "UPDATE {$this->tablaUsers} SET perfil_completo = 1 WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return true;
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::markProfileAsComplete para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Busca un usuario por su ID
    public function findById(int $userId) {
        if (!$this->isConnected()) return false;
        try {
            $sql = "SELECT id, username, email, password_hash, active, id_eCon, role
                    FROM {$this->tablaUsers}
                    WHERE id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::findById para ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Elimina un usuario de la base de datos
    public function deleteUser(int $userId) {
        if (!$this->isConnected()) return false;
        $sql = "DELETE FROM {$this->tablaUsers} WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::deleteUser para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Actualiza los datos de verificación para un usuario
    public function updateVerificationData(int $userId, string $newCodigo, string $newToken, int $newExpiresAt) {
        if (!$this->isConnected()) return false;
        $sql = "UPDATE {$this->tablaUsers}
                SET codigo = ?, verification_token = ?, token_expires_at = ?
                WHERE id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([$newCodigo, $newToken, $newExpiresAt, $userId]);
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::updateVerificationData para user ID $userId: " . $e->getMessage());
            return false;
        }
    }


    // Dentro de la clase UsuarioPDO en PDO/UsuarioPDO.php

    /**
     * Busca un usuario por su google_id.
     * @param string $googleId El ID único de Google.
     * @return array|false Los datos del usuario o false si no se encuentra.
     */
    public function findByGoogleId(string $googleId) { // Sin tipo retorno
        if (!$this->isConnected() || empty($googleId)) return false;
        try {
            // Seleccionar los campos necesarios para iniciar sesión / verificar perfil
            $sql = "SELECT id, username, email, password_hash, active, id_eCon, perfil_completo, role, google_id
                    FROM {$this->tablaUsers}
                    WHERE google_id = ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$googleId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en UsuarioPDO::findByGoogleId para Google ID '$googleId': " . $e->getMessage());
            return false;
        }
   }

   /**
    * Actualiza la columna google_id para un usuario existente (vincula cuenta).
    * @param int $userId El ID del usuario en tu tabla 'users'.
    * @param string $googleId El ID único de Google a guardar.
    * @return bool True en éxito, False en error.
    */
   public function updateGoogleId(int $userId, string $googleId) { // Sin tipo retorno
        if (!$this->isConnected() || empty($googleId)) return false;
        $sql = "UPDATE {$this->tablaUsers} SET google_id = ? WHERE id = ?";
        try {
             $stmt = $this->pdo->prepare($sql);
             return $stmt->execute([$googleId, $userId]);
        } catch (PDOException $e) {
             error_log("Error en UsuarioPDO::updateGoogleId para User ID $userId: " . $e->getMessage());
             return false;
        }
   }

// } // Fin clase UsuarioPDO
}
?>
