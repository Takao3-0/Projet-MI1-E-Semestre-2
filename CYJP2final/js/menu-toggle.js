/**
 * Menu Toggle - ProjetCYJ Restaurant
 * Gère l'ouverture/fermeture de la sidebar nav (gauche) et du panier (droite)
 */

(function () {
  'use strict';

  document.addEventListener('DOMContentLoaded', function () {

    var menuBtn       = document.querySelector('.menu-btn');
    var menuContainer = document.getElementById('mainMenuContainer');
    var mainMenu      = document.querySelector('.mainMenu');
    var cartContainer = document.getElementById('cartContainer');

    /* ── Fonctions ────────────────────────────────────────────────── */

    function openMenu() {
      closeCart();
      if (menuContainer) menuContainer.classList.add('menu-open');
      if (menuBtn) menuBtn.setAttribute('aria-expanded', 'true');
    }

    function closeMenu() {
      if (menuContainer) menuContainer.classList.remove('menu-open');
      if (menuBtn) menuBtn.setAttribute('aria-expanded', 'false');
    }

    function openCart() {
      closeMenu();
      if (cartContainer) cartContainer.classList.add('cart-open');
    }

    function closeCart() {
      if (cartContainer) cartContainer.classList.remove('cart-open');
    }

    /* ── Hamburger ────────────────────────────────────────────────── */

    if (menuBtn) {
      menuBtn.setAttribute('aria-expanded', 'false');
      menuBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        menuContainer && menuContainer.classList.contains('menu-open') ? closeMenu() : openMenu();
      });
    }

    /* ── Délégation globale (cart + fermeture au clic extérieur) ──── */

    document.addEventListener('click', function (e) {

      // Clic sur le déclencheur panier (ou un de ses enfants SVG)
      if (e.target.closest && e.target.closest('.cart-icon-wrap')) {
        e.stopPropagation();
        cartContainer && cartContainer.classList.contains('cart-open') ? closeCart() : openCart();
        return;
      }

      // Fermer le panier si clic en dehors du panneau
      if (cartContainer && cartContainer.classList.contains('cart-open')) {
        var cartPanel = cartContainer.querySelector('.cart-panel');
        if (cartPanel && !cartPanel.contains(e.target)) {
          closeCart();
          return;
        }
      }

      // Fermer le menu hamburger si clic en dehors
      if (menuContainer && menuContainer.classList.contains('menu-open')) {
        if (mainMenu && !mainMenu.contains(e.target) &&
            menuBtn && !menuBtn.contains(e.target)) {
          closeMenu();
        }
      }
    });

    /* ── Bouton ✕ du panneau panier ───────────────────────────────── */

    var cartCloseBtn = document.getElementById('cartClose');
    if (cartCloseBtn) {
      cartCloseBtn.addEventListener('click', function (e) {
        e.stopPropagation();
        closeCart();
      });
    }

    /* ── Touche Échap ─────────────────────────────────────────────── */

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') { closeCart(); closeMenu(); }
    });

    /* ── Auto-ouverture après action POST ─────────────────────────── */

    if (window.location.search.indexOf('cart=open') !== -1) {
      openCart();
      if (window.history.replaceState) {
        var url = new URL(window.location.href);
        url.searchParams.delete('cart');
        window.history.replaceState({}, '', url.toString());
      }
    }

  });
})();
