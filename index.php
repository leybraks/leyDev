<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leybrak | Portafolio</title>
    <link rel="stylesheet" href="resources/css/styles_p1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css">
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
    <header>
        <div class="toggle">
            <input type="checkbox" id="modoOscuroToggle"/>
            <label for="modoOscuroToggle"></label>
        </div>
        <nav class="full-navbar">
            <ul>
                <li><a href="pages/intranet.php"><i class="fa-brands fa-linkedin"></i> Intranet</a></li>
                <li><a href="#cursos"><i class="fas fa-shop"></i> Cursos</a></li>
                <li><a href="#contacto"><i class="fas fa-envelope"></i> Contacto</a></li>
            </ul>
        </nav>
        <div class="modal-overlay" id="modalOverlay"></div>
        <div class="modal" id="modal">
          <button class="modal__close" aria-label="Cerrar menú">&times;</button>
              <ul>
                <li><a href="pages/intranet.php"><i class="fa-brands fa-linkedin"></i> Intranet</a></li>
                <li><a href="#cursos"><i class="fas fa-shop"></i> Cursos</a></li>
                <li><a href="#contacto"><i class="fas fa-envelope"></i> Contacto</a></li>
              </ul>
        </div>
        <a href="#" class="scroll-navbar" id="scrollNavButton" aria-label="Abrir menú" title="Menú">
        <i class="fas fa-user"></i> </a>
    </header>
    <div class="cont">
      <div class ="circle"></div>
    </div>
    <main class="page-content">
        <section class= "seccion1">
          <div class="welcome">
            <h2 class="animate__animated animate__fadeInLeft">¡ Bienvenido a !</h2>
            <h1><p class="t1">LEY</p><p class="t2">dev</p></h1>
            <p id="t3"></p>
            <div class="tech-icons">
              <i class="fa-brands fa-html5"></i>
              <i class="fa-brands fa-css3-alt" ></i>
              <i class="fa-brands fa-js" ></i>
              <i class="fa-brands fa-php" ></i>
            </div>
            <img src="resources/img/imgInicio2.svg" id="imgInicio1" class="animate__animated animate__fadeInRight" alt="Imagen de inicio">
          </div>
        </section>
        <section class="seccion2">

          <img src="resources/img/imgInicio1.svg" id="imgInicio2" data-aos="fade-right" data-aos-duration="1000" alt="Imagen de inicio">

          <div class="seccion2-content" data-aos="fade-left" data-aos-duration="1000">
              <div class="info">
                  <h2>Con nosotros aprenderas a desarrollar increibles paginas web</h2>
              </div>
              <div class="tech-icons">
                  <i class="fa-brands fa-html5"></i>
                  <i class="fa-brands fa-css3-alt"></i>
                  <i class="fa-brands fa-js"></i>
                  <i class="fa-brands fa-php"></i>
              </div>
          </div>

      </section>
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
    <script src="resources/js/script.js" defer></script>
    <script src="https://unpkg.com/aos@next/dist/aos.js"></script>
    <script>
      AOS.init();
    </script>
</body>
</html>



