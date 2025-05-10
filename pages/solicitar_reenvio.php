<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../Includes/functions.php'; // Para mostrar mensajes

// Recuperar mensajes de éxito/error de la sesión
$resendMessage = $_SESSION['resend_message'] ?? null; unset($_SESSION['resend_message']);
$resendError = $_SESSION['resend_error'] ?? null; unset($_SESSION['resend_error']);

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reenviar Verificación - LEYdev</title>
    <style>
        body { font-family: sans-serif; display: flex; align-items: center; justify-content: center; min-height: 90vh; background-color: #f4f7f6; }
        .container { background-color: #fff; padding: 30px 40px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); text-align: center; max-width: 450px; width: 90%; }
        h1 { margin-bottom: 15px; color: #333; font-size: 1.4em; }
        p { margin-bottom: 25px; line-height: 1.6; color: #555; font-size: 0.95em;}
        .form-group { margin-bottom: 20px; text-align: left; }
        label { display: block; margin-bottom: 8px; font-weight: bold; color: #333; }
        input[type="email"] { width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; padding: 12px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 1em; width: 100%; transition: background-color 0.3s ease; }
        button:hover { background-color: #0056b3; }
        .message { padding: 10px; margin-bottom: 20px; border-radius: 4px; border: 1px solid transparent; font-size: 0.9em; }
        .success-message { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
        .error-message { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
        .login-link { margin-top: 20px; font-size: 0.9em;}
        .login-link a { color: #007bff; text-decoration: none; }
        .login-link a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reenviar Correo de Verificación</h1>
        <p>Ingresa la dirección de correo electrónico asociada a tu cuenta no verificada.</p>

        <?php // Mostrar mensajes de la operación anterior ?>
        <?php if ($resendMessage): ?>
            <p class="message success-message"><?php echo htmlspecialchars($resendMessage); ?></p>
        <?php endif; ?>
        <?php if ($resendError): ?>
            <p class="message error-message"><?php echo htmlspecialchars($resendError); ?></p>
        <?php endif; ?>

        <form action="../Scripts/handle_resend_verification.php" method="post">
            <div class="form-group">
                <label for="email">Correo Electrónico:</label>
                <input type="email" id="email" name="email" required>
            </div>
            <button type="submit">Enviar Nuevo Correo</button>
        </form>

        <p class="login-link">
            ¿Ya verificaste tu cuenta? <a href="intranet.php">Iniciar Sesión</a>
        </p>
         <p class="login-link" style="margin-top: 5px;">
            ¿Necesitas introducir el código? <a href="verificarCorreo.php">Verificar Código</a>
        </p>
    </div>
</body>
</html>