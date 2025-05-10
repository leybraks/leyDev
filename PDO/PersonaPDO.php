<?php
require_once __DIR__ . '/Conexion.php';

class PersonaPDO {

    private $tablaProfiles = 'user_profiles';
    private $pdo;

    // Constructor: inicializa la conexión con la base de datos
    public function __construct() {
        $this->pdo = Conexion::obtenerConexion();
        if ($this->pdo === null) {
            throw new RuntimeException("PersonaPDO no pudo obtener la conexión a la BD.");
        }
    }

    // Verifica si la conexión PDO está disponible
    private function isConnected() {
        return $this->pdo !== null;
    }

    // Devuelve el perfil del usuario según su ID
    public function findByUserId(int $userId) {
        if (!$this->isConnected()) return false;

        $sql = "SELECT user_id, first_name, paternal_last_name, maternal_last_name,
                       birth_date, phone, address, city, country, avatar_url, gender
                FROM {$this->tablaProfiles}
                WHERE user_id = ?";
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            error_log("Error en PersonaPDO::findByUserId para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

    // Actualiza los datos del perfil del usuario
    public function saveOrUpdate(int $userId, array $profileData) {
        if (!$this->isConnected() || empty($profileData)) return false;

        $setParts = [];
        $params = [];
        foreach ($profileData as $key => $value) {
            $setParts[] = "`" . $key . "` = ?";
            $params[] = $value;
        }

        if (empty($setParts)) return true;

        $sqlSet = implode(', ', $setParts);
        $params[] = $userId;

        $sql = "UPDATE {$this->tablaProfiles} SET {$sqlSet} WHERE user_id = ?";

        try {
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            error_log("Error en PersonaPDO::saveOrUpdate para user ID $userId: " . $e->getMessage());
            return false;
        }
    }

}
?>
