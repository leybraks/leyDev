<?php
// PDO/Conexion.php (Corregido para compatibilidad PHP < 7.1)

require_once __DIR__ . '/../Config/config.php';

class Conexion {
    // Propiedad estática sin tipo explícito para mayor compatibilidad
    private static $pdoInstance = null;

    /**
     * Método estático PÚBLICO para obtener la conexión PDO.
     * @return PDO|null Retorna el objeto PDO o null si falla.
     */
    // Quitado ': ?PDO' del tipo de retorno
    public static function obtenerConexion() {
        if (self::$pdoInstance === null) {
            try {
                $dsn = 'sqlite:' . DB_PATH;
                self::$pdoInstance = new PDO($dsn);
                self::$pdoInstance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$pdoInstance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                self::$pdoInstance->exec('PRAGMA foreign_keys = ON;');
            } catch (PDOException $e) {
                error_log("Error CRÍTICO de conexión PDO: " . $e->getMessage());
                self::$pdoInstance = null;
            }
        }
        return self::$pdoInstance;
    }

    // Prevenir instanciación y clonación
    private function __construct() {}
    private function __clone() {}
    public function __wakeup() {}
}
?>