<?php
require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

$pageTitle = "Verificación de Cuenta";
$message = "Error desconocido.";
$messageType = "error";
$showLoginLink = false;

$token = $_GET['token'] ?? null;  // Obtener el token de verificación de la URL

if (empty($token)) {  // Si no se proporciona el token
    $message = "Token de verificación no proporcionado o inválido.";
} else {
    $pdo = Conexion::obtenerConexion();  // Obtener conexión a la base de datos

    if ($pdo === null) {  // Si no se pudo establecer la conexión a la base de datos
        error_log("Error CRÍTICO en handle_link_verification: No se pudo obtener conexión a la BD.");
        $message = 'Error interno del servidor. Por favor, intenta más tarde o contacta a soporte.';
    } else {
        try {
            $userPDO = new UsuarioPDO();  // Crear una instancia de UsuarioPDO
            $user = $userPDO->findByVerificationToken($token);  // Buscar el usuario asociado al token

            if ($user) {  // Si se encuentra un usuario con ese token
                $userIdToActivate = $user['id'];
                $tiempoExpiracionGuardado = $user['token_expires_at'];
                $yaActivo = ($user['active'] == 1 && $user['id_eCon'] == 1);  // Verificar si la cuenta ya está activa

                if ($yaActivo) {  // Si la cuenta ya está activada
                    $message = "Esta cuenta ya ha sido activada anteriormente.";
                    $messageType = "success";
                    $showLoginLink = true;
                } elseif ($tiempoExpiracionGuardado !== null && time() > $tiempoExpiracionGuardado) {  // Si el token ha expirado
                    error_log("Intento de verificación con token de enlace EXPIRADO: $token para user ID: $userIdToActivate");
                    $message = 'Este enlace de verificación ha expirado. Por favor, solicita uno nuevo si es posible.';
                } else {  // Si el token es válido y no ha expirado
                    $activationSuccess = $userPDO->activateUser($userIdToActivate);  // Activar el usuario

                    if ($activationSuccess) {  // Si la activación es exitosa
                        error_log("Usuario ID: $userIdToActivate activado mediante token de enlace.");
                        $message = '¡Tu cuenta ha sido verificada exitosamente!';
                        $messageType = "success";
                        $showLoginLink = true;
                    } else {  // Si falló la activación del usuario
                        error_log("Error al ejecutar activateUser para user ID: $userIdToActivate con token de enlace.");
                        $message = 'Error al actualizar el estado de la cuenta. Inténtalo de nuevo o contacta a soporte.';
                    }
                }
            } else {  // Si el token no es válido o ya ha sido utilizado
                error_log("Intento de verificación con token de enlace INVÁLIDO/INEXISTENTE: $token");
                $message = 'El enlace de verificación no es válido o ya fue utilizado.';
            }

        } catch (RuntimeException $e) {  // Capturar errores de tipo RuntimeException
            error_log("Error CRÍTICO (RuntimeException) en handle_link_verification: " . $e->getMessage());
            $message = 'Error interno del servidor [PDO Init].';
        } catch (Exception $e) {  // Capturar otros errores inesperados
            error_log("Excepción inesperada en handle_link_verification: " . $e->getMessage());
            $message = 'Ocurrió un error inesperado durante la verificación.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - LEYdev</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 90vh; background-color: #f4f7f6; }
        .container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 500px; width: 90%; }
        h1 { margin-bottom: 20px; color: #333; font-size: 1.5em; }
        p { margin-bottom: 25px; line-height: 1.6; }
        .message { padding: 15px; margin-bottom: 20px; border-radius: 5px; font-weight: bold; border: 1px solid transparent; }
        .message.success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .message.error { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .login-link a { display: inline-block; background-color: #007bff; color: #ffffff; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold; transition: background-color 0.3s ease; }
        .login-link a:hover { background-color: #0056b3; }
    </style>
</head>
<body>
    <div class="container">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="message <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </p>

        <?php if ($showLoginLink): ?>
            <p class="login-link">
                <a href="<?php echo defined('LOGIN_URL') ? htmlspecialchars(LOGIN_URL) : '../pages/intranet.php'; ?>">Ir a Iniciar Sesión</a>
            </p>
        <?php endif; ?>

    </div>
</body>
</html>
<?php
?>
