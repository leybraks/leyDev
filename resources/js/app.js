document.addEventListener("DOMContentLoaded", () => {

const sign_in_btn = document.querySelector("#sign-in-btn");
const sign_up_btn = document.querySelector("#sign-up-btn");
const container = document.querySelector(".container");

sign_up_btn.addEventListener("click", () => {
  container.classList.add("sign-up-mode");
});

sign_in_btn.addEventListener("click", () => {
  container.classList.remove("sign-up-mode");
});

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
        } else {

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

    const signUpButton = document.getElementById('sign-up-btn');
    const signInButton = document.getElementById('sign-in-btn');

    if (signUpButton) {
        signUpButton.addEventListener('click', () => {
            document.body.classList.add('navbar-signup-active');
            console.log("Body class added: navbar-signup-active"); 
        });
    } else {
        console.warn("Botón #sign-up-btn no encontrado.");
    }

    if (signInButton) {
        signInButton.addEventListener('click', () => {
            document.body.classList.remove('navbar-signup-active');
        });
    }

    


  }); 