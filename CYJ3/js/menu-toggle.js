/**
 * Menu Toggle - ProjetCYJ Restaurant
 * Gère l'ouverture/fermeture de la sidebar via le bouton burger
 */

(function() {
  'use strict';

  // Attendre que le DOM soit complètement chargé
  document.addEventListener('DOMContentLoaded', function() {

    // Sélection des éléments
    const menuBtn = document.querySelector('.menu-btn');
    const menuContainer = document.getElementById('mainMenuContainer');
    const mainMenu = document.querySelector('.mainMenu');

    // Vérification que les éléments existent
    if (!menuBtn || !menuContainer || !mainMenu) {
      console.error('Menu toggle: Éléments requis non trouvés');
      return;
    }

    /**
     * Toggle le menu ouvert/fermé
     */
    function toggleMenu(event) {
      event.preventDefault(); // Empêcher le comportement par défaut du lien

      const isOpen = menuContainer.classList.contains('menu-open');

      if (isOpen) {
        closeMenu();
      } else {
        openMenu();
      }
    }

    /**
     * Ouvre le menu
     */
    function openMenu() {
      menuContainer.classList.add('menu-open');
      menuBtn.setAttribute('aria-expanded', 'true');
    }

    /**
     * Ferme le menu
     */
    function closeMenu() {
      menuContainer.classList.remove('menu-open');
      menuBtn.setAttribute('aria-expanded', 'false');
    }

    /**
     * Gère le clic en dehors du menu
     */
    function handleClickOutside(event) {
      // Ne rien faire si le menu est fermé
      if (!menuContainer.classList.contains('menu-open')) {
        return;
      }

      // Vérifier si le clic est sur la sidebar elle-même (pas sur l'overlay)
      const isClickInsideMenu = mainMenu.contains(event.target);
      const isClickOnButton = menuBtn.contains(event.target);

      // Fermer si le clic est en dehors de la sidebar ET du bouton
      if (!isClickInsideMenu && !isClickOnButton) {
        closeMenu();
      }
    }

    // Event listeners
    menuBtn.addEventListener('click', toggleMenu);
    document.addEventListener('click', handleClickOutside);

    // Initialiser l'attribut aria-expanded
    menuBtn.setAttribute('aria-expanded', 'false');

  });
})();
