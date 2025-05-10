<?php
// scripts/handle_profile_update.php

// --- Inicialización y Carga ---
if (session_status() === PHP_SESSION_NONE) {
    if (!headers_sent()) {
        session_start();
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Error fatal: Sesión no pudo iniciarse (headers sent).'
        ]);
        exit;
    }
}

if (!headers_sent()) {
    header('Content-Type: application/json');
}

require_once __DIR__ . '/../Config/config.php';
require_once __DIR__ . '/../Includes/autoload.php';
require_once __DIR__ . '/../PDO/Conexion.php';

// Array base para la respuesta JSON
$response = [
    'success' => false,
    'message' => '',
    'errors' => [],
    'redirectUrl' => '',
    'updated_greeting_data' => null // Para los datos del saludo
];

$profileEditPageReference = BASE_URL . '/intranet/intranet.php#configuracion-content';
$loginPageUrl = BASE_URL . '/pages/intranet.php'; // O tu página de login real

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    $response['message'] = 'Debes iniciar sesión para editar tu perfil.';
    $response['redirectUrl'] = $loginPageUrl;
    echo json_encode($response);
    exit;
}
$user_id = $_SESSION['user_id'];
$current_username_from_session = $_SESSION['username'] ?? 'Usuario'; // Para fallback

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $response['message'] = 'Método no permitido.';
    echo json_encode($response);
    exit;
}

$profileDataInput = [
    'first_name' => trim($_POST['first_name'] ?? ''),
    'paternal_last_name' => trim($_POST['paternal_last_name'] ?? ''),
    'maternal_last_name' => trim($_POST['maternal_last_name'] ?? ''),
    'birth_date' => trim($_POST['birth_date'] ?? ''),
    'phone' => trim($_POST['phone'] ?? ''),
    'address' => trim($_POST['address'] ?? ''),
    'city' => trim($_POST['city'] ?? ''),
    'country' => trim($_POST['country'] ?? ''),
    'gender' => $_POST['gender'] ?? '',
];

$validationErrorMessages = [];
if (empty($profileDataInput['first_name'])) { $validationErrorMessages[] = "El nombre es obligatorio."; }
if (empty($profileDataInput['paternal_last_name'])) { $validationErrorMessages[] = "El apellido paterno es obligatorio."; }
if (empty($profileDataInput['birth_date'])) {
    $validationErrorMessages[] = "La fecha de nacimiento es obligatoria.";
} else {
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $profileDataInput['birth_date'])) {
        $validationErrorMessages[] = "El formato de la fecha debe ser AAAA-MM-DD.";
    }
}

if (!empty($validationErrorMessages)) {
    http_response_code(400);
    $response['message'] = 'Por favor, corrige los errores.';
    $response['errors'] = $validationErrorMessages;
    echo json_encode($response);
    exit;
}

try {
    $pdo = Conexion::obtenerConexion();
    if ($pdo === null) {
        error_log("Error CRÍTICO en handle_profile_update (AJAX): No se pudo obtener conexión a la BD.");
        http_response_code(500);
        $response['message'] = 'Error interno del servidor [DBC]. No se pudo conectar a la base de datos.';
        echo json_encode($response);
        exit;
    }

    $personaPDO = new PersonaPDO();
    $updateSuccess = $personaPDO->saveOrUpdate($user_id, $profileDataInput);

    if ($updateSuccess) {
        $response['success'] = true;
        $response['message'] = '¡Perfil actualizado exitosamente!';

        // --- OBTENER Y ENVIAR DATOS ACTUALIZADOS PARA EL SALUDO ---
        $updatedProfileDataDB = $personaPDO->findByUserId($user_id);
        if ($updatedProfileDataDB) {
            // El 'first_name' para el saludo es el primer nombre de la cadena completa
            $nombreCompletoActualizado = $updatedProfileDataDB['first_name'] ?? $profileDataInput['first_name'];
            $partesNombreActualizado = explode(' ', $nombreCompletoActualizado, 2);
            $primerNombreActualizado = $partesNombreActualizado[0];

            $response['updated_greeting_data'] = [
                'first_name' => $primerNombreActualizado, // Enviar solo el primer nombre para el saludo
                'gender'     => $updatedProfileDataDB['gender'] ?? $profileDataInput['gender'],
                'role'       => $_SESSION['role'] ?? 'desconocido'
            ];
            // Actualizar el nombre en la sesión si es que este formulario también puede cambiar el 'username' o 'nombre completo' que se usa en la sesión.
            // Por ejemplo, si 'first_name' en la base de datos es el que se usa para $_SESSION['username'] o parte de él.
            // Si 'first_name' de $profileDataInput es solo el primer nombre y tu sesión usa el nombre completo,
            // tendrías que reconstruir el nombre completo y actualizar $_SESSION['username'] si es necesario.
            // Ejemplo simple si $_SESSION['username'] es solo el primer nombre:
            // $_SESSION['username'] = $primerNombreActualizado;

        } else {
             // Si no se pudieron recuperar los datos actualizados, enviar los que se intentaron guardar
             // o los de la sesión como fallback para el saludo
            $partesNombreFallback = explode(' ', $profileDataInput['first_name'], 2);
            $response['updated_greeting_data'] = [
                'first_name' => $partesNombreFallback[0],
                'gender'     => $profileDataInput['gender'],
                'role'       => $_SESSION['role'] ?? 'desconocido'
            ];
        }
        // --- FIN DE MODIFICACIÓN PARA EL SALUDO ---

        unset($_SESSION['form_data']);
        unset($_SESSION['profile_error']);
    } else {
        error_log("Falló PersonaPDO::saveOrUpdate para user ID: $user_id en update (AJAX)");
        http_response_code(500);
        $response['message'] = 'Error al actualizar los datos del perfil en la base de datos. Por favor, intenta de nuevo.';
    }

} catch (RuntimeException $e) {
    error_log("Error CRÍTICO (RuntimeException) en handle_profile_update (AJAX): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Error interno del servidor [PDO Init].';
} catch (Exception $e) {
    error_log("Excepción inesperada en handle_profile_update (AJAX): " . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Ocurrió un error inesperado al actualizar el perfil.';
}

echo json_encode($response);
exit;
?>