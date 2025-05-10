document.addEventListener("DOMContentLoaded", () => {

  const textoA = document.getElementById("t3");
  const toggle = document.getElementById("modoOscuroToggle");
  const circleElement = document.querySelector(".circle"); 
  const footerElement = document.querySelector(".footer"); 
  const mobileBreakpoint = 768; 

  let animationTargetScrollDistance = 0; 
  let calculationComplete = false; 

  const texto1 = "El mejor bootCamp de desarrollo web";
  let i = 0;
  let parpadeo1 = null;
  const cursorRef1 = { value: true };


  const estadoGuardado = localStorage.getItem("modoOscuro") === "true";
  if (estadoGuardado) {
      document.body.classList.add("modo-oscuro");
      if (toggle) toggle.checked = true;
  }
  toggle?.addEventListener("change", () => {
      const oscuroActivo = toggle.checked;
      document.body.classList.toggle("modo-oscuro", oscuroActivo);
      localStorage.setItem("modoOscuro", oscuroActivo);
  });

  function iniciarParpadeo(elemento, textoBase, cursorRef) {
      if (elemento && elemento.parpadeoInterval) { clearInterval(elemento.parpadeoInterval); }
      const intervalId = setInterval(() => {
          if (!elemento) { clearInterval(intervalId); return; }
          cursorRef.value = !cursorRef.value;
          elemento.textContent = (textoBase || '') + (cursorRef.value ? "|" : " ");
      }, 500);
      if (elemento) { elemento.parpadeoInterval = intervalId; }
      return intervalId;
  }
  function escribirBucle1() {
      if (!textoA) return;
      if (parpadeo1) { clearInterval(parpadeo1); parpadeo1 = null; }
      if (i < texto1.length) {
          textoA.textContent = texto1.slice(0, i + 1) + "|";
          i++;
          setTimeout(escribirBucle1, 150);
      } else {
          parpadeo1 = iniciarParpadeo(textoA, texto1, cursorRef1);
      }
  }

  function escribirBucle({ elemento, texto, estado }) {
       if (!elemento || !texto || !estado) return;
       if (estado.parpadeo) { clearInterval(estado.parpadeo); estado.parpadeo = null; }
       if (elemento.parpadeoInterval) { clearInterval(elemento.parpadeoInterval); elemento.parpadeoInterval = null; } 
       estado.escribiendo = true; estado.animacionTerminada = false; estado.o = 0; elemento.textContent = "|";
       const escribir = () => {
           if (estado.o < texto.length) {
               elemento.textContent = texto.slice(0, estado.o + 1) + "|"; estado.o++; setTimeout(escribir, 200);
           } else {
               estado.escribiendo = false; estado.animacionTerminada = true; estado.yaSeMostroUnaVez = true;
               estado.parpadeo = iniciarParpadeo(elemento, texto, estado.cursorRef);
           }
       }; escribir();
  }

   function observerTypewriter({ elemento, texto, estado }) {
       if (!elemento) return;
       const observer = new IntersectionObserver(entries => { entries.forEach(entry => { if (!elemento) return; if (entry.isIntersecting && !estado.escribiendo && !estado.animacionTerminada) { const delay = estado.yaSeMostroUnaVez ? 400 : 0; setTimeout(() => { if (observer.takeRecords().some(record => record.target === elemento && record.isIntersecting)) { escribirBucle({ elemento, texto, estado }); } }, delay); } if (!entry.isIntersecting && estado.animacionTerminada) { if (estado.parpadeo) clearInterval(estado.parpadeo); if (elemento.parpadeoInterval) clearInterval(elemento.parpadeoInterval); estado.parpadeo = null; elemento.parpadeoInterval = null; estado.o = 0; estado.cursorRef.value = true; estado.escribiendo = false; estado.animacionTerminada = false; elemento.textContent = ""; } }); }, { threshold: 1.0 });
       observer.observe(elemento);
  }

  const textosAnimados = [
      { elemento: document.getElementById("typewriter1"), texto: "LEY" },
      { elemento: document.getElementById("typewriter2"), texto: "dev" }
  ];
  textosAnimados.forEach(({ elemento, texto }) => {
      if (elemento) {
          const estado = { o: 0, escribiendo: false, animacionTerminada: false, yaSeMostroUnaVez: false, parpadeo: null, cursorRef: { value: true } };
          observerTypewriter({ elemento, texto, estado });
      } 
  });

  function calculateAnimationDistanceToEndAtFooter() {
    if (!footerElement) {
        animationTargetScrollDistance = Math.max(0, document.body.scrollHeight - window.innerHeight);
        calculationComplete = true;
        return;
    }
    try {
        const windowHeight = window.innerHeight;
        const footerRect = footerElement.getBoundingClientRect();
        const footerOffsetTop = footerRect.top + window.scrollY;
        const endScrollY = Math.max(0, footerOffsetTop - windowHeight);
        animationTargetScrollDistance = endScrollY;
        calculationComplete = true;

    } catch (error) {
        calculationComplete = false;
    }
}

  function updateCircleAnimation() {
    if (!circleElement || !calculationComplete || window.innerWidth < mobileBreakpoint) {return; }
    const scrollY = window.scrollY;
    let scrollProgress = 0;

    if (animationTargetScrollDistance <= 0) {
        scrollProgress = (scrollY > 0) ? 1 : 0;
    } else {
        scrollProgress = Math.min(1, Math.max(0, scrollY / animationTargetScrollDistance));
    }

    const startPercentage = 48;
    const endPercentage = -55;
    const newLeftPercentage = startPercentage + (endPercentage - startPercentage) * scrollProgress;
    circleElement.style.setProperty("--circle-left", `${newLeftPercentage}%`);
}

  window.addEventListener("scroll", updateCircleAnimation);
  window.addEventListener("resize", () => {
      calculateAnimationDistanceToEndAtFooter();
      updateCircleAnimation();
      if (circleElement && window.innerWidth < mobileBreakpoint) {
          circleElement.style.removeProperty('--circle-left');
      }
  });


  if (textoA) {
     setTimeout(escribirBucle1, 500); 
  }

  setTimeout(() => {
      calculateAnimationDistanceToEndAtFooter();
      updateCircleAnimation(); 
  }, 150);

  const techIcons1 = document.querySelector(".seccion1 .tech-icons");
  const techIcons2 = document.querySelector(".seccion2 .tech-icons");
  const allTechIcons = [techIcons1, techIcons2].filter(el => el !== null);

  const staggerDelayMs = 100;
  let lastScrollY = window.scrollY;
  const intersectingContainers = new Set(); 

  if (allTechIcons.length > 0) {
      console.log(`[Tech Icons] Configurando ${allTechIcons.length} contenedores...`);
      const iconObserverCallback = (entries, observerInstance) => {
        entries.forEach(entry => {
            const container = entry.target; 
            const icons = container.querySelectorAll("i");

            if (entry.isIntersecting) {
                intersectingContainers.add(container);
                if (!container.classList.contains('icons-visible')) {
                    if (container === techIcons2) {
                        const appearanceDelayMs = 300; 

                        setTimeout(() => {
                            if (intersectingContainers.has(container)) {
                                 container.classList.add('icons-visible');
                                 icons.forEach((icon, index) => {
                                     icon.style.transitionDelay = `${index * staggerDelayMs}ms`;
                                 });
                            } 
                        }, appearanceDelayMs);

                    } else {
                        container.classList.add('icons-visible');
                        icons.forEach((icon, index) => {
                            icon.style.transitionDelay = `${index * staggerDelayMs}ms`;
                        });
                    }
                }
            } else {
                intersectingContainers.delete(container); 
                container.classList.remove('icons-visible');
            }
        });
    };
      const iconObserverOptions = { root: null, rootMargin: '0px', threshold: 0.1 }; 
      const iconObserver = new IntersectionObserver(iconObserverCallback, iconObserverOptions);
      allTechIcons.forEach((container, index) => {
          if (!container.id) container.id = `tech-icons-container-${index + 1}`;
          iconObserver.observe(container);
      });

      window.addEventListener('scroll', () => {
          const currentScrollY = window.scrollY;
          const tolerance = 2; 

          let scrollDirection = 'none';
          if (currentScrollY > lastScrollY + tolerance) {
              scrollDirection = 'down';
          } else if (currentScrollY < lastScrollY - tolerance) {
              scrollDirection = 'up';
          }

          if (scrollDirection === 'down') {
              if (techIcons1 && intersectingContainers.has(techIcons1) && techIcons1.classList.contains('icons-visible')) {
                  techIcons1.classList.remove('icons-visible');
              }
          } else if (scrollDirection === 'up') {
              if (techIcons2 && intersectingContainers.has(techIcons2) && techIcons2.classList.contains('icons-visible')) {
                  techIcons2.classList.remove('icons-visible');
              }
          }

          lastScrollY = Math.max(0, currentScrollY);

      }, { passive: true });

  }


    const scrollNavButton = document.getElementById('scrollNavButton');
    const modal = document.getElementById('modal');
    const modalOverlay = document.getElementById('modalOverlay');
    const closeButton = modal ? modal.querySelector('.modal__close') : null;
    const panelLinks = modal ? modal.querySelectorAll('nav a') : [];

    const scrollThreshold = 80;
    function openModal() {
        if (!modal || !modalOverlay) return;
        modal.classList.add('is-visible');
        modalOverlay.classList.add('is-visible');
    }

    function closeModal() {
        if (!modal || !modalOverlay) return;
        modal.classList.remove('is-visible');
        modalOverlay.classList.remove('is-visible');
    }

    function handleScroll() {
        const isModalVisible = modal && modal.classList.contains('is-visible');

        if (window.scrollY > scrollThreshold) {
            document.body.classList.add('scrolled');
        } else {
            document.body.classList.remove('scrolled');
        }
        if (isModalVisible) {
            closeModal();
        }
    }

    window.addEventListener('scroll', handleScroll);

    function checkScrollOnLoad() {
        if (window.scrollY > scrollThreshold) {
            document.body.classList.add('scrolled');
        } else {
            document.body.classList.remove('scrolled');
        }
    }
    checkScrollOnLoad();

    if (scrollNavButton && modal && modalOverlay) {
        scrollNavButton.addEventListener('click', (event) => {
            event.preventDefault();
            if (!modal.classList.contains('is-visible')) {
                 openModal();
            }
        });

        if (closeButton) {
            closeButton.addEventListener('click', closeModal);
        } 

        modalOverlay.addEventListener('click', closeModal);
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && modal.classList.contains('is-visible')) {
                closeModal();
            }
        });
        panelLinks.forEach(link => {
            if (link.getAttribute('href').startsWith('#')) {
                 link.addEventListener('click', closeModal);
            }
        });

    } 


    const customToast = document.getElementById('custom-toast');
    // Verificar si tiene contenido
    if (customToast && customToast.textContent.trim().length > 0) {
         customToast.classList.add('show'); // Mostrarlo
         // Ocultarlo después de unos segundos
         setTimeout(function() {
             customToast.classList.remove('show');
         }, 5000); // 5 segundos
     }
    
}); 










