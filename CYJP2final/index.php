<?php require_once '../../protection.php'; ?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cy Restaurant</title>
  <link rel="stylesheet" href="accueil.css">
  <link rel="stylesheet" href="index.css">

 
  <style>
    /*Pour afficher soit l'un soit l'autre*/

    /*Pour la position sticky on là met ici et pas dans .navbar sinon bah ça s'applique pas vu que pcnavbar et mobilenavbar son parents de la classe nav */
    .pcnavbar {
      display: block;
      position: sticky;
      top: 0;
      z-index: 100;
    }

    .mobilenavbar {  
      position: sticky;
      top: 0;
      z-index: 100;
      display: none;
    }

    @media (max-width: 900px) {
      .pcnavbar {
        display: none;
      }

      .mobilenavbar {
        display: block;
      }
    }
  </style>
</head>

<body>

<!--  La barre du haut qui s'adapte en fonction du terminal utilisateur  -->

  <!--  Pour pc  -->
  <div class="pcnavbar">
    <nav class="navbar">
      <div class="navbar-inner">
        <a href="#" class="navbar-logo">
          <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
        </a>

        <ul class="navbar-links">
          <li><a href="#menu">Populaires</a></li>
          <li><a href="#features">Restaurant</a></li>
          <li><a href="#order">Commander</a></li>
          <li><a href="/ProjetCYJ/CYJ/Carte/">Carte</a></li>
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin" || $role_actuel === "livreur")): ?>
            <li><a href="/ProjetCYJ/CYJ/Livraison/">Livraison</a></li>
          <?php endif; ?>
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "colab.")): ?>
            <li><a href="../CYF/">CYF</a></li>
          <?php else: ?>
            <li><a href="#Le lien ne fonctionne pas puisque vous n'êtes pas identifie">CYF</li>
          <?php endif; ?>  
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
            <li><a href="/ProjetCYJ/CYJ/Admin/">Admin</a></li>
          <?php endif; ?>
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "chef" || $role_actuel === "fakeadmin")): ?>
            <li><a href="/ProjetCYJ/CYJ/Cuisinier/">Cuisine</a></li>
          <?php endif; ?>
        </ul>

        <div class="navbar-auth">
          <?php if ($est_connecte): ?>
            <a href="/ProjetCYJ/CYJ/Profil/" style="text-decoration:none">
              <span class="nav-user">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                </svg>
                <?php echo htmlspecialchars($nom_affiche); ?>
              </span>
            </a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="logout" value="1">
              <button type="submit" class="btn-nav">D&eacute;connexion</button>
            </form>
          <?php else: ?>
            <a href="/ProjetCYJ/CYJ/LOG/login" class="btn-nav">Connexion</a>
            <a href="/ProjetCYJ/CYJ/LOG/signup" class="btn-nav btn-nav-primary">S'inscrire</a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </div>

  <!--  Pour mobile  -->
  <div class="mobilenavbar">
    <nav class="navbar">
      <div class="navbar-inner">
        <button class="menu-btn" type="button" aria-label="Menu" aria-expanded="false">&#9776;</button>
        <a href="#" class="navbar-logo">
          <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
        </a>    
      </div>
    </nav>
  </div>

  <!-- latebar menu (inchangé) -->
  <div id="mainMenuContainer">
    <div class="mainMenu">
      <div class="mainMenuItem" id="mainMenu">
        <span class="mainMenuItemCollapsable">
          <img src="images/logo_smc_blanc.png" alt="Menu item">
        </span>
        <span class="mainMenuItemCollapsable">Cy Restaurant</span>
      </div>

      <?php if (!($est_connecte)): ?>
        <nav id="menuNav">
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/LOG/login">
              <span class="mainMenuItemCollapsable">
                <img src="images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Connexion</div>
            </a>
          </div>

          <div class="mainMenuItemSignIn">
            <a href="/ProjetCYJ/CYJ/LOG/signup">
              <span class="mainMenuItemCollapsable">
                <img src="images/rechercher.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Inscription</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Profil/">
              <span class="mainMenuItemCollapsable">
                <img src="images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Mon compte</div>
            </a>
          </div>
        <?php endif; ?>

        <div class="mainMenuItemLogin">
          <a href="#menu">
            <span class="mainMenuItemCollapsable">
              <img src="images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">La Carte</div>
          </a>
        </div>

        <div class="mainMenuItemLogin">
          <a href="#features">
            <span class="mainMenuItemCollapsable">
              <img src="images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">Le Restaurant</div>
          </a>
        </div>

        <div class="mainMenuItemLogin">
          <a href="/ProjetCYJ/CYJ/Carte/">
            <span class="mainMenuItemCollapsable">
              <img src="images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">Menu complet</div>
          </a>
        </div>

        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Admin/">
              <span class="mainMenuItemCollapsable">
                <img src="images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel Admin</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "livreur")): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Livraison/">
              <span class="mainMenuItemCollapsable">
                <img src="images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel livreur</div>
            </a>
          </div>
        <?php endif; ?>


        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "chef" || $role_actuel === "fakeadmin")): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Cuisinier/">
              <span class="mainMenuItemCollapsable">
                <img src="images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Accès cuisine</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte): ?>
          <div class="mainMenuItem">
            <a href="#&action=logout">
              <span class="mainMenuItemCollapsable">
                <img src="images/d&eacute;connecter.png" alt="Menu item">
              </span>
              <form method="POST">
                <button type="submit">
                  <input type="hidden" name="logout" value="1">
                  <div class="mainMenuItemCollapsable">D&eacute;connexion</div>
                </button>
              </form>
            </a>
          </div>
        <?php endif; ?>
      </nav>
    </div>
  </div>

  <!--  Il s'agit la du carre vert/orange en haut de l'écran  -->
  <section class="hero">
    <div class="hero-overlay"></div>
    <div class="hero-content">
      <p class="hero-eyebrow">&#127869; Burgers &bull; Pizzas &bull; Wraps &bull; Livraison</p>
      <h1 class="hero-title">CY<br><span>RESTAURANT</span></h1>
      <h1 class="hero-titlemobile">CY<span>RESTAURANT</span></h1>
      <p class="hero-sub">Des saveurs authentiques pr&eacute;par&eacute;es avec soin.<br>Commandez en ligne ou venez
        nous rendre visite.</p>
      <div class="hero-ctas">
        <a href="/ProjetCYJ/CYJ/Carte/" class="btn-hero-primary">Voir la carte</a>
        <a href="/ProjetCYJ/CYJ/Livraison/" class="btn-hero-secondary">Commander en livraison</a>
      </div>
    </div>
    <div class="hero-scroll">
      <svg xmlns="http://www.w3.org/2000/svg" width="30" height="30" viewBox="0 0 24 24" fill="white" opacity="0.6">
        <path d="M7.41 8.59L12 13.17l4.59-4.58L18 10l-6 6-6-6 1.41-1.41z" />
      </svg>
    </div>
  </section>

  <!-- Zone du best seller -->
  <section class="featured-banner">
    <div class="featured-inner">
      <div class="featured-visual">
        <div class="featured-emoji-wrap">
          <span class="featured-big-emoji">&#127828;</span>
        </div>
      </div>
      <div class="featured-text">
        <span class="featured-tag">Le Best-seller</span>
        <h2>CY Smash Burger</h2>
        <p>Double steak hach&eacute;, cheddar fondu, sauce maison et oignons croustillants. Notre burger signature qui
          fait l'unanimit&eacute; depuis l'ouverture.</p>
        <div class="featured-price-row">
          <span class="featured-price">8.90 &euro;</span>
          <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="btn-dark">Commander</a>
        </div>
      </div>
    </div>
  </section>

  <!--  Les populaires -->
  <section class="menu-section" id="menu">
    <div class="section-header">
      <h2>Nos Populaires</h2>
      <p>Les plats pr&eacute;f&eacute;r&eacute;s de nos clients, disponibles tous les jours.</p>
    </div>

    <div class="menu-grid">

      <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fff3e0,#ffe0b2)">
          <span class="menu-emoji">&#127828;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Burger</span>
          <h3>CY Smash Burger</h3>
          <p>Double steak, cheddar, sauce maison</p>
          <div class="menu-card-footer">
            <span class="menu-price">8.90 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#pizzas" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fce4ec,#f8bbd0)">
          <span class="menu-emoji">&#127829;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Pizza</span>
          <h3>Pizza 4 Fromages</h3>
          <p>Mozzarella, gorgonzola, ch&egrave;vre, parmesan</p>
          <div class="menu-card-footer">
            <span class="menu-price">11.00 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#wraps" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#e8f5e9,#c8e6c9)">
          <span class="menu-emoji">&#127790;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Wrap</span>
          <h3>Wrap Poulet Avocat</h3>
          <p>Poulet grill&eacute;, avocat, sauce ranch</p>
          <div class="menu-card-footer">
            <span class="menu-price">7.50 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fff3e0,#ffccbc)">
          <span class="menu-emoji">&#127828;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Burger</span>
          <h3>Bacon King</h3>
          <p>Triple steak, bacon grill&eacute;, cheddar, BBQ</p>
          <div class="menu-card-footer">
            <span class="menu-price">11.90 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#sides" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fff9c4,#fff176)">
          <span class="menu-emoji">&#127839;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Accompagnements</span>
          <h3>Nuggets x8</h3>
          <p>Croustillants avec sauce au choix</p>
          <div class="menu-card-footer">
            <span class="menu-price">5.90 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#wraps" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#e8f5e9,#a5d6a7)">
          <span class="menu-emoji">&#127790;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Tacos</span>
          <h3>Tacos XL</h3>
          <p>Double viande, double fromage, frites</p>
          <div class="menu-card-footer">
            <span class="menu-price">10.90 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#desserts" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fce4ec,#f48fb1)">
          <span class="menu-emoji">&#127846;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Dessert</span>
          <h3>Cookie G&eacute;ant</h3>
          <p>P&eacute;pites de chocolat, tout chaud</p>
          <div class="menu-card-footer">
            <span class="menu-price">2.90 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#pizzas" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#ffebee,#ef9a9a)">
          <span class="menu-emoji">&#127829;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Pizza</span>
          <h3>Pizza Pepperoni</h3>
          <p>Sauce tomate, mozzarella, pepperoni</p>
          <div class="menu-card-footer">
            <span class="menu-price">10.50 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#boissons" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#e3f2fd,#90caf9)">
          <span class="menu-emoji">&#129380;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Boisson</span>
          <h3>Jus d'Orange Frais</h3>
          <p>Press&eacute; sur place, 100% pur jus</p>
          <div class="menu-card-footer">
            <span class="menu-price">3.50 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

      <a href="/ProjetCYJ/CYJ/Carte/#sides" class="menu-card">
        <div class="menu-card-img" style="background: linear-gradient(135deg,#fff9c4,#ffee58)">
          <span class="menu-emoji">&#127839;</span>
        </div>
        <div class="menu-card-body">
          <span class="menu-cat">Accompagnements</span>
          <h3>Frites Maison</h3>
          <p>Fra&icirc;ches, sel de Gu&eacute;rande</p>
          <div class="menu-card-footer">
            <span class="menu-price">3.50 &euro;</span>
            <span class="menu-add">+</span>
          </div>
        </div>
      </a>

    </div>

    <div class="voir-tout-wrap">
      <a href="/ProjetCYJ/CYJ/Carte/" class="voir-tout-btn">Voir toute la carte &rarr;</a>
    </div>
  </section>

  <!-- Les infos additionelles -->
  <section class="features-section" id="features">
    <div class="section-header light">
      <h2>Le Restaurant</h2>
      <p>Ce qui fait la diff&eacute;rence chez Cy Restaurant</p>
    </div>

    <div class="features-grid">

      <div class="feature-card">
        <div class="feature-icon">&#127859;</div>
        <h3>Ingr&eacute;dients frais</h3>
        <p>Tous nos plats sont pr&eacute;par&eacute;s avec des produits s&eacute;lectionn&eacute;s chaque matin. Aucun
          compromis sur la qualit&eacute;.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">&#127881;</div>
        <h3>Recettes maison</h3>
        <p>Nos sauces, nos p&acirc;tes, nos marinades. Tout est fait sur place par notre &eacute;quipe de cuisiniers
          passionn&eacute;s.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">&#127949;</div>
        <h3>Livraison rapide</h3>
        <p>Commandez en ligne et recevez votre repas en 30 &agrave; 45 minutes. Suivi en temps r&eacute;el inclus.</p>
      </div>

      <div class="feature-card">
        <div class="feature-icon">&#11088;</div>
        <h3>4.8 / 5 &eacute;toiles</h3>
        <p>Plus de 300000 avis clients positifs. La satisfaction de nos clients est notre premi&egrave;re priorit&eacute;.
        </p>
      </div>

    </div>
  </section>

  <!-- Les infos (c'est du bullshit) -->
  <section class="about-section">
    <div class="about-inner">
      <div class="about-text">
        <span class="featured-tag">Notre histoire</span>
        <h2>N&eacute; sur le campus de CY Tech</h2>
        <p>Cy Restaurant est n&eacute; d'une id&eacute;e simple : proposer une vraie bonne cuisine, accessible, dans une
          ambiance d&eacute;contract&eacute;e. Burgers, pizzas, wraps &mdash; une carte vari&eacute;e pour tous les
          go&ucirc;ts.</p>
        <a href="/ProjetCYJ/CYJ/Carte/" class="btn-dark">D&eacute;couvrir la carte</a>
      </div>
      <div class="about-badges">
        <div class="about-badge">
          <span class="about-badge-num">15+</span>
          <span class="about-badge-label">Plats au menu</span>
        </div>
        <div class="about-badge">
          <span class="about-badge-num">30000+</span>
          <span class="about-badge-label">Avis clients</span>
        </div>
        <div class="about-badge">
          <span class="about-badge-num">4.8</span>
          <span class="about-badge-label">Note moyenne</span>
        </div>
        <div class="about-badge">
          <span class="about-badge-num">30'</span>
          <span class="about-badge-label">Livraison</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Redirection Carte/Commande -->
  <section class="order-section" id="order">
    <div class="order-inner">
      <div class="order-text">
        <h2>Envie de manger ?</h2>
        <p>Commandez directement en ligne ou venez nous rendre visite sur le campus. La cuisine est ouverte tous les
          jours de 11h &agrave; 23h.</p>
        <div class="order-btns">
          <a href="/ProjetCYJ/CYJ/Carte/" class="btn-order-primary">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path
                d="M19 6h-2c0-2.8-2.2-5-5-5S7 3.2 7 6H5c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V8c0-1.1-.9-2-2-2zm-7-3c1.7 0 3 1.3 3 3H9c0-1.7 1.3-3 3-3z" />
            </svg>
            Voir la carte
          </a>
          <a href="/ProjetCYJ/CYJ/Livraison/" class="btn-order-secondary">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
              <path
                d="M20 8h-3V4H3c-1.1 0-2 .9-2 2v11h2c0 1.7 1.3 3 3 3s3-1.3 3-3h6c0 1.7 1.3 3 3 3s3-1.3 3-3h2v-5l-3-4zM6 18.5c-.8 0-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5zm13.5-9l1.96 2.5H17V9.5h2.5zm-1.5 9c-.8 0-1.5-.7-1.5-1.5s.7-1.5 1.5-1.5 1.5.7 1.5 1.5-.7 1.5-1.5 1.5z" />
            </svg>
            Commander en livraison
          </a>
          <?php if ($est_connecte): ?>
            <a href="/ProjetCYJ/CYJ/Profil" class="btn-order-ghost">Mon compte</a>
          <?php endif; ?>
        </div>
      </div>
      <div class="order-visual">
        <div class="order-emojis">
          <span>&#127828;</span>
          <span>&#127829;</span>
          <span>&#127790;</span>
          <span>&#127839;</span>
        </div>
      </div>
    </div>
  </section>

  <!-- Infos du restaurant -->
  <section class="info-section">
    <div class="info-grid">
      <div class="info-card info-card-orange">
        <h3>Horaires</h3>
        <p class="info-big">11h&ndash;23h</p>
        <p>Tous les jours, 7j/7</p>
      </div>
      <div class="info-card info-card-dark">
        <h3>Plats au menu</h3>
        <p class="info-big">15+</p>
        <p>Burgers, Pizzas, Wraps, Sides, Desserts</p>
      </div>
      <div class="info-card info-card-orange">
        <h3>Note clients</h3>
        <p class="info-big">4.8&#9733;</p>
        <p>Plus de 30000 avis positifs</p>
      </div>
      <div class="info-card info-card-dark">
        <h3>Livraison</h3>
        <p class="info-big">30'</p>
        <p>Suivi en temps r&eacute;el inclus</p>
      </div>
    </div>
  </section>

  <!-- FOOTER -->
  <footer class="site-footer">
    <div class="footer-inner">
      <div class="footer-brand">
        <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
        <p>Creative Yumland &mdash; CY Tech, Cergy</p>
      </div>
      <div class="footer-links">
        <a href="/ProjetCYJ/CYJ/Carte/">La Carte</a>
        <a href="/ProjetCYJ/CYJ/Livraison/">Livraison</a>
        <?php if ($est_connecte): ?>
          <a href="/ProjetCYJ/CYJ/Profil">Mon compte</a>
        <?php else: ?>
          <a href="/ProjetCYJ/CYJ/LOG/login">Connexion</a>
        <?php endif; ?>
        <a href="/legal">Mentions l&eacute;gales</a>
      </div>
    </div>
    <div class="footer-bottom">
      <p>&copy; 2026 Cy Restaurant &mdash; Creative Yumland</p>
    </div>
  </footer>
  <script src="js/menu-toggle.js"></script>
</body>

</html>