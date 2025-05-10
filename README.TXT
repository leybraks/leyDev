# Mi Proyecto Web: Portafolio con Intranet y Funcionalidades Modernas

*Documentación creada el: 19 de Abril, 2025*
*Ubicación: Ventanilla, Callao, Perú*

## Introducción

Este es mi proyecto web personal, desarrollado como una plataforma para [**Describe brevemente el objetivo principal, ej: mostrar mi portafolio de desarrollo y experimentar con funcionalidades de intranet**]. He construido la aplicación usando PHP como lenguaje principal del backend, interactuando con una base de datos SQLite a través de PDO para la gestión de usuarios y sus perfiles.

En el frontend, he utilizado HTML5 semántico y CSS3 moderno, aprovechando Flexbox y Grid para el layout, Variables CSS para una fácil tematización (incluyendo modo oscuro) y animaciones/transiciones CSS para mejorar la experiencia. El JavaScript (ES6+) juega un papel crucial en la interactividad, manejo de eventos, animaciones dinámicas y la lógica de componentes como la barra de navegación y el menú modal.

## Características Implementadas

Estas son las funcionalidades principales que he implementado hasta ahora:

### Backend y Gestión de Usuarios

1.  **Sistema de Registro y Login:**
    * **Registro (`proRegistro.php`):** Implementé un script PHP que recibe los datos del formulario de registro. Valida la información (campos vacíos, formato de email, coincidencia de contraseñas), verifica que el usuario o email no existan previamente, hashea la contraseña usando `password_hash()` para seguridad, e inserta el nuevo usuario en la tabla `users` de la base de datos SQLite.
    * **Auto-Login Post-Registro:** Justo después de un registro exitoso, inicio automáticamente una sesión PHP para el nuevo usuario (`$_SESSION['user_id']`, `$_SESSION['username']`) antes de redirigir a la página de éxito.
    * **Creación de Perfil Inicial:** Simultáneamente al registro, creo una fila asociada en la tabla `user_profiles` con el `user_id` correspondiente, dejando los campos de perfil en `NULL` inicialmente. También establezco una bandera `perfil_completo` (en la tabla `users`) en `0` (falso) por defecto para este nuevo usuario.
    * **Login (Script que usa `Usuario::validarYObtenerDatos`):** Desarrollé un script y un método de clase (`validarYObtenerDatos` en `Usuario.php`) que verifica las credenciales ingresadas contra la base de datos usando `password_verify()`. Si el login es válido, obtiene los datos del usuario, incluyendo la bandera `perfil_completo` y el `full_name` (usando `LEFT JOIN`).
    * **Inicio de Sesión Seguro:** Utilizo sesiones de PHP (`session_start()`, `$_SESSION`) para mantener el estado del usuario logueado.

2.  **Flujo de "Completar Perfil":**
    * **Redirección Condicional:** Después de un login exitoso, mi script PHP verifica la bandera `perfil_completo`. Si es `0`, redirijo al usuario a una página específica (`llenarDatos.php`) para que complete su información. Si es `1`, lo redirijo directamente a la página principal de la intranet (`intranet.php`).
    * **Guardado de Datos del Perfil (`proDatosPersona.php`):** Creé un script que recibe los datos del formulario `llenarDatos.php`. Valida la información (básicamente), actualiza (`UPDATE`) la fila correspondiente en la tabla `user_profiles`, y **actualiza la bandera `perfil_completo` a `1`** en la tabla `users`.
    * **Persistencia:** Una vez que el perfil se marca como completo, los futuros logins del usuario lo llevarán directamente a la intranet.

### Frontend e Interfaz de Usuario (UI/UX)

1.  **Navbar Dinámica:**
    * Implementé una barra de navegación principal (`.full-navbar`) que es visible al cargar la página.
    * Usando JavaScript, detecto el scroll vertical del usuario. Al pasar un umbral (`scrollThreshold`), añado una clase `scrolled` al `<body>`.
    * Mediante CSS, esta clase `scrolled` oculta la `.full-navbar` (con una transición de opacidad y translación) y **muestra** un botón circular más pequeño (`.scroll-navbar`) fijo en la esquina superior derecha. Al volver arriba, el proceso se invierte.
2.  **Menú Modal:**
    * El botón circular (`.scroll-navbar`, con ID `scrollNavButton`) actúa como disparador.
    * Al hacer clic en él, un script JavaScript muestra un panel modal (`#modal`) y un fondo de overlay (`#modalOverlay`) usando una clase `.is-visible` y transiciones CSS para una aparición suave.
    * El modal contiene los enlaces de navegación principales (duplicados de la `.full-navbar`).
    * Implementé múltiples formas de cerrar el modal para buena UX: un botón 'X' (`.modal__close`), haciendo clic en el overlay, presionando la tecla 'Escape', y **automáticamente si el usuario hace scroll** mientras el modal está abierto.
3.  **Modo Oscuro:**
    * Añadí un interruptor (`.toggle`) en la interfaz.
    * Un script JavaScript detecta el cambio en el toggle, añade/quita la clase `modo-oscuro` al `<body>`, y guarda la preferencia del usuario en `localStorage` para que persista entre visitas.
    * Utilicé **Variables CSS** (`:root` y `body.modo-oscuro`) extensivamente para definir paletas de colores para ambos temas (claro y oscuro). La mayoría de los elementos (`body`, formularios, navbars, modal, footer, etc.) usan estas variables, permitiendo un cambio de tema instantáneo y consistente.
    * Incluí transiciones CSS en `background-color` y `color` en muchos elementos para que el cambio de tema sea suave.
4.  **Estilo de Formularios:**
    * Diseñé los formularios (Login, Registro, Llenar Datos) con un estilo consistente, usando la estructura `div.input-field` que incluye un icono de Font Awesome a la izquierda y un input con bordes redondeados y fondo suave.
    * Los botones de envío (`.btn.solid`) también tienen un estilo coherente (redondeados, color de acento).
5.  **Página de Portafolio (Index/Secciones):**
    * Estructuré la página principal con secciones de altura completa (`seccion1`, `seccion2`).
    * Implementé una animación de fondo con un círculo gradiente que se mueve basado en el scroll (`.circle::before`, controlado por JS).
    * Añadí efectos de "máquina de escribir" (typewriter) para algunos textos.
    * Configuré animaciones de aparición para iconos tecnológicos (`.tech-icons`) usando Intersection Observer y un efecto escalonado/direccional con JavaScript y CSS.
6.  **Página de Intranet:**
    * Diseñé una estructura típica de dashboard con un `<header>`, un menú lateral (`aside.sidebar`) y un área de contenido principal (`main.content`) usando Flexbox.
    * El header muestra un icono de usuario y un botón de logout.
    * El contenido principal muestra un **mensaje de bienvenida personalizado** ("Bienvenido, [Primer Nombre]!") extrayendo el nombre desde `$_SESSION['full_name']`.
    * El sidebar contiene la navegación principal de la intranet.
7.  **Mensaje de Éxito:** Utilicé la librería SweetAlert2 para mostrar un mensaje estilizado y coherente con el tema (claro/oscuro) después de un registro exitoso.

## Tecnologías Utilizadas

* **Backend:** PHP (con manejo de sesiones)
* **Base de Datos:** SQLite (accedida vía PDO)
* **Frontend:**
    * HTML5
    * CSS3 (Flexbox, Grid, Variables CSS, Transiciones, Animaciones)
    * JavaScript (ES6+, Vanilla JS, manipulación del DOM, Event Listeners, `localStorage`, `Workspace`/AJAX no usado directamente aquí pero sí por Barba/Swup si se implementaran)
* **Librerías/Externos:**
    * Font Awesome (para iconos)
    * Google Fonts (Poppins)
    * SweetAlert2 (para alertas estilizadas)
    * AOS (Animate On Scroll) - *Mencionar si la usaste al final*
    * Barba.js / Swup / Animsition - *Mencionar si implementaste transiciones de página*

## Estructura de Archivos (General)

/
|-- Class/
|   |-- Usuario.php       # Clase para lógica de usuario (login)
|-- DB/
|   |-- usuarios.db       # Base de datos SQLite
|-- PDO/
|   |-- Conexion.php      # Clase para conexión PDO (si se usa)
|-- intranet/
|   |-- proRegistro.php   # Script procesador de registro
|   |-- proDatosPersona.php # Script procesador de datos de perfil
|   |-- proIntranet.php   # Script procesador de login (o como se llame)
|   |-- logout.php        # Script para cerrar sesión
|-- pages/
|   |-- intranet.php      # Dashboard principal de la intranet
|   |-- llenarDatos.php   # Formulario para completar perfil
|   |-- login.php         # Página/Formulario de Login (si renombraste)
|   |-- register.php      # Página/Formulario de Registro (si existe separada)
|   |-- registro_exitoso.php # Página de mensaje post-registro
|-- resources/
|   |-- css/
|   |   |-- style.css       # Estilos principales/compartidos
|   |   |-- intranet.css    # Estilos específicos de intranet
|   |   |-- llenarDatos.css # Estilos específicos de llenar datos
|   |-- js/
|   |   |-- script.js       # Scripts generales (modo oscuro, animaciones portafolio?)
|   |   |-- app.js          # Scripts específicos (login animado, modal/navbar scroll?)
|   |   |-- barba-init.js   # (Si usaste Barba.js)
|   |-- img/
|       |-- logo.png
|       |-- *.svg           # Imágenes de login/registro/portfolio
|-- index.php               # Página principal / Portafolio
|-- (otros archivos PHP/HTML)

*(Nota: Ajusta esta estructura a tu organización real)*

## Configuración / Ejecución

Para ejecutar este proyecto, normalmente necesitarás:
1.  Un servidor web local o remoto con soporte para PHP (ej. Apache, Nginx con PHP-FPM).
2.  La extensión PDO SQLite habilitada en PHP.
3.  Asegurarte de que el servidor web tenga permisos de escritura sobre el archivo `usuarios.db` y el directorio `DB/` donde reside.

## Posibles Mejoras Futuras

* Refactorizar el CSS para unificar mejor las variables y evitar redundancias.
* Implementar validaciones más robustas en el lado del servidor (PHP).
* Mejorar la responsividad del diseño, especialmente de la intranet y el menú modal.
* Añadir más funcionalidades a la intranet (gestión de usuarios, perfiles, etc.).
* Refactorizar el JavaScript, quizás separando la lógica por módulos o clases.
* Implementar transiciones de página más avanzadas (quizás con View Transitions API cuando sea más estable).
* Optimizar el rendimiento general.

---