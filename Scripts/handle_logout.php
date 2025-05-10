<?php
session_start();  // Inicia la sesión
session_unset();  // Elimina todas las variables de sesión
session_destroy();  // Destruye la sesión

header("Location:../pages/intranet.php");  // Redirige al usuario a la página de inicio de sesión
exit();  // Termina la ejecución del script
?>