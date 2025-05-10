<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

$profileError = $_SESSION['profile_error'] ?? null;
unset($_SESSION['profile_error']);

if (!isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/../includes/functions.php';
    redirectWithError('intranet.php', 'login_error', 'Debes iniciar sesión.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="../resources/css/llenarDatos.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <title>Document</title>
</head>
<body>
    <div class="cont">
      <div class="circle"></div>
    </div>
    <main>
    <form action="../Scripts/handle_profile_setup.php" method="post" class="datos-persona-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
        <h2 class="title">Completa tu Perfil</h2>

        <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" id="first_name" name="first_name" required placeholder="Nombre"
                value="<?php echo htmlspecialchars($formData['first_name'] ?? ''); ?>">
        </div>

        <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" id="paternal_last_name" name="paternal_last_name" required placeholder="Apellido Paterno"
                value="<?php echo htmlspecialchars($formData['paternal_last_name'] ?? ''); ?>">
        </div>

        <div class="input-field">
            <i class="fas fa-user"></i>
            <input type="text" id="maternal_last_name" name="maternal_last_name" placeholder="Apellido Materno"
                value="<?php echo htmlspecialchars($formData['maternal_last_name'] ?? ''); ?>">
        </div>

        <div class="input-field">
            <i class="fas fa-calendar-days"></i>
            <input type="text" name="birth_date" placeholder="Fecha Nacimiento (AAAA-MM-DD)" required
                   onfocus="(this.type='date')"
                   onblur="(this.type='text')">
        </div>

        <div class="input-field">
            <i class="fas fa-city"></i> <input type="text" name="city" placeholder="Ciudad" required>
        </div>

        <div class="input-field select-wrapper">
            <i class="fas fa-venus-mars"></i>
            <select id="gender" name="gender">
                <?php
                $opcionesGenero = [
                    '' => 'Seleccione', 
                    'Masculino' => 'Masculino',
                    'Femenino' => 'Femenino',
                    'No binario' => 'No binario',
                    'Otro' => 'Otro'
                ];
                $generoActual = $formData['gender'] ?? '';
                foreach ($opcionesGenero as $valor => $texto) {
                    $selected = ($generoActual === $valor) ? 'selected' : '';
                    echo '<option value="' . htmlspecialchars($valor) . '" ' . $selected . '>' . htmlspecialchars($texto) . '</option>';
                }
                ?> 
            </select>
        </div>

        <input type="submit" value="Guardar Datos" class="btn solid">
    </form>
    <img src="../resources/img/llenarDatos.svg" alt="" id="image" class="animate__animated animate__fadeInRight">
    </main>
    <footer class="footer">
        <div class="footer-info">
            <p>&copy; 2025 Sebastián Silva Mendoza</p>
            <p>Estudiante de Ciencia de Datos y Desarrollo Web</p>
        </div>
        <ul class="footer-socials">
            <li><a href="#"><i class="fab fa-github"></i></a></li>
            <li><a href="#"><i class="fab fa-linkedin-in"></i></a></li>
            <li><a href="#"><i class="fab fa-instagram"></i></a></li>
        </ul>
    </footer>
    <script src="../resources/js/script.js" defer></script>
</body>
</html>
