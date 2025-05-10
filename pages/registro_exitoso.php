<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro exitoso</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        body.modo-oscuro-active { 
            background-color: #121212; 
        }

        .swal2-popup.custom-swal-popup {
            border-radius: 12px !important; 
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15) !important;
        }
        .swal2-title.custom-swal-title {
             font-weight: 600 !important;
        }
        .swal2-html-container.custom-swal-text {
            line-height: 1.6 !important;
        }
        .swal2-confirm.custom-swal-confirm {
            border-radius: 25px !important; 
            font-weight: 500 !important;
            padding: 0.6rem 1.5rem !important;
            transition: background-color 0.3s ease, color 0.3s ease !important;
        }
         body.modo-oscuro-active .swal2-confirm.custom-swal-confirm {
             color: #333 !important;
         }
    </style>
</head>
<body>

<script>
    const isDarkMode = localStorage.getItem('modoOscuro') === 'true';
    if (isDarkMode) {
        document.body.classList.add('modo-oscuro-active');
    }
    const alertBgColor = isDarkMode ? '#1e1e1e' : '#ffffff';
    const alertTextColor = isDarkMode ? '#e0e0e0' : '#333333';
    const confirmBtnBgColor = isDarkMode ? '#ffeb00' : '#5995fd'; 
    const successIconColor = isDarkMode ? '#66bb6a' : '#4caf50'; 

    Swal.fire({
        title: "¡Registro exitoso!",
        text: "Tu cuenta ha sido creada correctamente.",
        icon: "success",
        iconColor: successIconColor, 

        background: alertBgColor,   
        color: alertTextColor,   
        fontFamily: 'Poppins, sans-serif', 

        confirmButtonText: "Continuar",
        confirmButtonColor: confirmBtnBgColor,

        customClass: {
            popup: 'custom-swal-popup',
            title: 'custom-swal-title',
            htmlContainer: 'custom-swal-text', 
            confirmButton: 'custom-swal-confirm'
        },

        showConfirmButton: true,
        allowOutsideClick: false,
        allowEscapeKey: false

    }).then((result) => {
        if (result.isConfirmed) {
             window.location.href = "../pages/intranet.php";
        }
    });
</script>

</body>
</html>
