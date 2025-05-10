<?php 
require_once __DIR__ . '/../Config/config.php';
session_start();
if (empty($_SESSION['csrf_token'])) {
  //Generar un token aleatorio seguro (32 bytes = 64 caracteres hexadecimales)
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <script src="https://accounts.google.com/gsi/client" async defer></script>
    <title>Leybrak | Portafolio</title>
    <link rel="stylesheet" href="../resources/css/style.css">
    <script
      src="https://kit.fontawesome.com/64d58efce2.js"
      crossorigin="anonymous"
    ></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body> 

    <header>
        <nav class="full-navbar">
            <ul>
                <li><a href="../index.php"><i class="fas fa-home"></i> Inicio</a></li>
                <li><a href="#cursos"><i class="fas fa-shop"></i> Cursos</a></li>
                <li><a href="#contacto"><i class="fas fa-envelope"></i> Contacto</a></li>
            </ul>
        </nav>
        <div class="modal-overlay" id="modalOverlay"></div>
        <div class="modal" id="modal">
          <button class="modal__close" aria-label="Cerrar menú">&times;</button>
              <ul>
                <li><a href="../index.php"><i class="fas fa-home"></i>Inicio</a></li>
                <li><a href="#cursos"><i class="fas fa-shop"></i> Cursos</a></li>
                <li><a href="#contacto"><i class="fas fa-envelope"></i> Contacto</a></li>
              </ul>
        </div>
        <a href="#" class="scroll-navbar" id="scrollNavButton" aria-label="Abrir menú" title="Menú">
        <i class="fas fa-user"></i> </a>
    </header>
    <main>
    <div class="container">
      <div class="forms-container">
        <div class="signin-signup">
          <form action="../Scripts/handle_login.php" method="post" class="sign-in-form">
            <h2 class="title">Iniciar sesion</h2>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
            <div class="input-field">
              <i class="fas fa-user"></i>
              <input type="text" placeholder="Usuario" name="usuario"required/>
            </div>
            <div class="input-field">
              <i class="fas fa-lock"></i>
              <input type="password" placeholder="Contraseña" name="clave" required/>
            </div>
            <?php
            if (isset($_SESSION['login_error']) && !empty($_SESSION['login_error'])) {
                echo '<p class="error-message login-form-error" style="margin-bottom: 15px; text-align: center; width: 100%;">' // Añadida clase login-form-error
                    . htmlspecialchars($_SESSION['login_error'])
                    . '</p>';
                unset($_SESSION['login_error']);
            }
            ?>
            <div id="login-ajax-error" class="error-message" style="display:none; margin-bottom: 15px; text-align: center; width: 100%;"></div>
            <input type="submit" value="Iniciar" class="btn solid" />
            <p class="social-text">O inicia sesión con redes sociales</p>
            <div class="social-media">
              <a href="#" class="social-icon">
                <i class="fab fa-facebook-f"></i>
              </a>
              <a href="#" class="social-icon">
                <i class="fab fa-twitter"></i>
              </a>
              <a href="#" class="social-icon">
                <i class="fab fa-google"></i>
              </a>
              <a href="#" class="social-icon">
                <i class="fab fa-linkedin-in"></i>
              </a>
            </div>
          </form>
          <form action="../Scripts/handle_registro.php" method="post" class="sign-up-form">
            <h2 class="title">Regístrate</h2>
            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

            <div class="input-field">
                <i class="fas fa-user"></i>
                <input type="text" placeholder="Usuario" name="newUsuario" required/>
            </div>
            <small class="field-error" id="newUsuario-error" style="display:none; color:var(--intranet-error-text, #721c24); font-size:0.8em; text-align:left; width:100%; padding-left:10px;"></small>

            <div class="input-field">
                <i class="fas fa-envelope"></i>
                <input type="email" placeholder="Email" name="newEmail" required/>
            </div>
            <small class="field-error" id="newEmail-error" style="display:none; color:var(--intranet-error-text, #721c24); font-size:0.8em; text-align:left; width:100%; padding-left:10px;"></small>

            <div class="input-field">
                <i class="fas fa-lock"></i>
                <input type="password" placeholder="Nueva contraseña" name="newContraseña" required/>
            </div>
            <small class="field-error" id="newContraseña-error" style="display:none; color:var(--intranet-error-text, #721c24); font-size:0.8em; text-align:left; width:100%; padding-left:10px;"></small>

            <div class="input-field">
                <i class="fas fa-lock"></i>
                <input type="password" placeholder="Repetir contraseña" name="newValidarContraseña" required/>
            </div>
            <small class="field-error" id="newValidarContraseña-error" style="display:none; color:var(--intranet-error-text, #721c24); font-size:0.8em; text-align:left; width:100%; padding-left:10px;"></small>


            <?php // Tu bloque PHP para mostrar errores de SESIÓN (de recargas completas)
                // AHORA DEBERÍAS AÑADIR AQUÍ EL CÓDIGO PARA MOSTRAR $_SESSION['register_error']
                // como lo hiciste para login_error, por si el JS falla o está deshabilitado.
                // Ejemplo:
                if (isset($_SESSION['register_error']) && !empty($_SESSION['register_error'])) {
                    echo '<p class="error-message register-form-error" style="margin-bottom: 10px; text-align: center; width: 100%;">'
                        . htmlspecialchars($_SESSION['register_error'])
                        . '</p>';
                    unset($_SESSION['register_error']);
                }
                // También para mensajes de éxito de registro que redirigen aquí (si aplica)
                if (isset($_SESSION['register_success_message']) && !empty($_SESSION['register_success_message'])) {
                    echo '<p class="success-message register-form-success" style="margin-bottom: 10px; text-align: center; width: 100%;">'
                          . htmlspecialchars($_SESSION['register_success_message'])
                          . '</p>';
                    unset($_SESSION['register_success_message']);
                }
            ?>
            <div id="register-ajax-error" class="error-message" style="display:none; margin-top: 10px; margin-bottom: 10px; text-align: center; width: 100%;"></div>
            <input type="submit" class="btn" value="Registrarse" name="registrar"/>
            <p class="social-text">O registrate con redes sociales</p>
            <div class="social-media">
              <div id="g_id_onload"
                data-client_id="<?php echo htmlspecialchars(GOOGLE_CLIENT_ID); ?>"
                data-callback="handleGoogleCredentialResponse" <?php // <-- NOMBRE DE NUESTRA FUNCIÓN JS ?>
                data-cancel_on_tap_outside="false"
                data-context="signin"> <?php // O signup si está en el form de registro ?>
                <?php // Quitamos data-ux_mode y data-login_uri ?>
              </div>

              <div class="g_id_signin"
                  data-type="standard"
                  data-shape="rectangular"
                  data-theme="outline"
                  data-text="signin_with"
                  data-size="large"
                  data-logo_alignment="left">
              </div>
          </div>
          </form>
        </div>
      </div>

      <div class="panels-container">
        <div class="panel left-panel">
          <div class="content">
            <h3>Nuevo aqui ?</h3>
            <p>
              Lorem ipsum, dolor sit amet consectetur adipisicing elit. Debitis,
              ex ratione. Aliquid!
            </p>
            <button class="btn transparent" id="sign-up-btn">
              Registrate
            </button>
          </div>
          <img src="../resources/img/log.svg" class="image" alt="" />
        </div>
        <div class="panel right-panel">
          <div class="content">
            <h3>Ya estas inscrito ?</h3>
            <p>
              Lorem ipsum dolor sit amet consectetur adipisicing elit. Nostrum
              laboriosam ad deleniti.
            </p>
            <button class="btn transparent" id="sign-in-btn">
              Iniciar sesion
            </button>
          </div>
          <img src="../resources/img/register.svg" class="image" alt="" />
        </div>
      </div>
    </div>

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
    <script src="../resources/js/login_ajax.js"></script>
    <script src="../resources/js/script.js" defer></script>
    <script src="../resources/js/app.js"></script>
    <script src="../resources/js/register_ajax.js"></script>
    <script>
      function handleGoogleCredentialResponse(response) {
  // response.credential contiene el ID Token (JWT)
        console.log("Encoded JWT ID token: " + response.credential);

        // Mostrar un indicador de carga (opcional)
        // document.getElementById('google-signin-status').innerText = "Verificando...";

        // Enviar el token a nuestro NUEVO script backend usando fetch
        fetch('../../Scripts/handle_google_token_signin.php', { // <<< NUEVO SCRIPT PHP (¡Verifica Case!)
            method: 'POST',
            headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest' // Para que PHP sepa que es AJAX (opcional)
            },
            // Enviar el token en el cuerpo
            body: 'id_token=' + encodeURIComponent(response.credential)
                // También enviamos el token CSRF (leyéndolo del input oculto, por ejemplo)
                + '&csrf_token=' + encodeURIComponent(document.querySelector('input[name="csrf_token"]').value)
        })
        .then(response => response.json()) // Esperamos una respuesta JSON
        .then(data => {
            console.log("Respuesta del backend:", data);
            if (data.success && data.redirectUrl) {
            // ¡Éxito! Redirigir a donde diga el backend
            window.location.href = data.redirectUrl;
            } else {
            // Error: Mostrar mensaje de error (en el div de errores del login, por ejemplo)
            const errorElement = document.querySelector('.sign-in-form .error-message'); // Ajusta selector si es necesario
            if (errorElement) {
                errorElement.textContent = data.message || 'Error desconocido al procesar inicio de sesión con Google.';
                errorElement.style.display = 'block'; // Asegurarse que sea visible
            } else {
                alert(data.message || 'Error desconocido.'); // Fallback con alert
            }
            }
        })
        .catch(error => {
            console.error('Error en fetch a handle_google_token_signin:', error);
            const errorElement = document.querySelector('.sign-in-form .error-message'); // Ajusta selector
            if (errorElement) {
                errorElement.textContent = 'Error de comunicación al iniciar sesión con Google.';
                errorElement.style.display = 'block';
            } else {
                alert('Error de comunicación al iniciar sesión con Google.');
            }
        });
        }
    </script>
</body>
</html>