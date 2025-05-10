<?php
    // pages/verificarCorreo.php
    
    if (session_status() === PHP_SESSION_NONE) {
        if (!headers_sent()) { // Comprobar antes de iniciar
            session_start();
        }
        if (!isset($_SESSION['verifying_email'])) {
            header('Location: ../pages/intranet.php');
            exit;
        }
    }
    $email_verificando = $_SESSION['verifying_email'] ?? null;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../resources/css/llenarDatos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <title>Document</title>
</head>
<body>
<div class="cont">
      <div class ="circle"></div>
    </div>
    <main>
            
    <form action="../Scripts/handle_verificacion_codigo.php" method="post" class="datos-persona-form" id="formVerificacionCodigo">
        <p style="text-align: center; margin-bottom: 20px;">
            Introduce el código de 8 caracteres que enviamos a
            <?php if ($email_verificando): ?>
                <strong><?php echo htmlspecialchars($email_verificando); ?></strong>
            <?php else: ?>
                tu correo electrónico
            <?php endif; ?>.
        </p>
        <div class="input-field">
            <i class="fas fa-code"></i>
            <input type="text" id="codigo" name="codigo" required
                   placeholder="Código de 8 caracteres"
                   maxlength="8" minlength="8"
                   pattern="[a-fA-F0-9]{8}"
                   title="Introduce los 8 caracteres hexadecimales (letras a-f, números 0-9)."
                   autocomplete="off" >
        </div>

        <div id="verification-ajax-message" style="display:none; margin-bottom: 15px; text-align: center; width: 100%; padding: 8px; border-radius: 4px;"></div>
        <input type="submit" value="Verificar" class="btn solid">
        <p style="margin-top: 20px; text-align: center; font-size: 0.9em;">
            ¿No recibiste el código o el enlace expiró? <br>
            <a href="solicitar_reenvio.php" style="color: white;">Solicitar un nuevo correo de verificación</a>
        </p>
    </form>
    <img src="../resources/img/llenarDatos.svg" alt="" id="image" class="animate__animated animate__fadeInRight">
    </main>
    <script src="../resources/js/script.js" defer></script>
    <script src="../resources/js/verification_ajax.js" defer></script>
</body>
</html>