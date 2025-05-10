<?php
define('DB_PATH', __DIR__ . '/../DB/usuarios.db');
define('TOKEN_VALIDITY_SECONDS', 3600);

define('BASE_URL', 'http://localhost:3000');
//define('BASE_URL', 'https://leydev.programador-de-software.com');
define('UPDATE_URL', BASE_URL . '/Scripts/update.php');

define('SMTP_HOST', 'cloud.theplanetserver.net'); 
define('SMTP_PORT', 465);                   
define('SMTP_USERNAME', 'jesus@leydev.programador-de-software.com'); 
define('SMTP_PASSWORD', 'top_sebas123');     
define('SMTP_SECURE', 'tls'); 

define('EMAIL_FROM_ADDRESS', 'jesus@leydev.programador-de-software.com'); 
define('EMAIL_FROM_NAME', 'LEYdev');

define('GOOGLE_CLIENT_ID', ''); // El ID
define('GOOGLE_CLIENT_SECRET', ''); // El secreto
define('GOOGLE_REDIRECT_URI', 'https://leydev.programador-de-software.com/Scripts/handle_google_callback.php');
//define('GOOGLE_REDIRECT_URI', 'http://localhost:3000/Scripts/handle_google_callback.php');

define('DASHBOARD_URL', BASE_URL . '/intranet/intranet.php');

define('LOGIN_URL', BASE_URL . '/pages/intranet.php'); // << ASEGÚRATE DE TENER ESTA

/** URL del panel principal del usuario logueado. */

define('PROFILE_SETUP_URL', BASE_URL . '/pages/llenarDatos.php'); 

define('LOG_PATH', __DIR__ . '/../logs/app_debug.log');
// Crea la carpeta 'logs' si no existe y dale permisos de escritura.

/** @var string Ruta ABSOLUTA en el servidor donde se guardarán las entregas. ¡DEBE SER ESCRIBIBLE por PHP! */
define('SUBMISSIONS_UPLOAD_DIR', __DIR__ . '/../resources/uploads/submissions/');

define('CHAT_UPLOAD_DIR', __DIR__ . '/../resources/uploads/chat_attachments/');


?>