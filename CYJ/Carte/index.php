<?php require_once '../../../protection.php'; ?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <title>Carte</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="../index.css">
    <!--On utilise restaurant.css surtout pour ce fichier -->
    <style>
        /*Pour afficher soit l'un soit l'autre*/
        .pcnavbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: block;
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
            <li><a href="/ProjetCYJ/CYJ/">Restaurant</a></li>
            <li><a href="#order">Commander</a></li>
            <li><a href="/ProjetCYJ/CYJ/Carte/">Carte</a></li>
            <li><a href="/ProjetCYJ/CYJ/Livraison/">Livraison</a></li>
            <li><a href="/ProjetCYJ/CYF">CYF</a></li>
            <?php if ($est_connecte && $role_actuel === "admin"): ?>
                <li><a href="/ProjetCYJ/CYJ/Admin/">Admin</a></li>
            <?php endif; ?>
            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "chef")): ?>
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

                <div class="navbar-auth">
                    <?php if ($est_connecte): ?>
                        <span class="nav-user">
                            <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24"
                                fill="currentColor">
                                <path
                                    d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                            </svg>
                            <?php echo htmlspecialchars($nom_affiche); ?>
                        </span>
                    <?php else: ?>
                        <a href="/ProjetCYJ/CYJ/LOG/login" class="btn-nav">Connexion</a>
                        <a href="/ProjetCYJ/CYJ/LOG/signup" class="btn-nav btn-nav-primary">S'inscrire</a>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </div>

    <!-- latebar menu (inchangé) -->
  <div id="mainMenuContainer">
    <div class="mainMenu">
      <div class="mainMenuItem" id="mainMenu">
        <span class="mainMenuItemCollapsable">
          <img src="../images/logo_smc_blanc.png" alt="Menu item">
        </span>
        <span class="mainMenuItemCollapsable">Cy Restaurant</span>
      </div>

      <?php if (!($est_connecte)): ?>
        <nav id="menuNav">
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/LOG/login">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Connexion</div>
            </a>
          </div>

          <div class="mainMenuItemSignIn">
            <a href="/ProjetCYJ/CYJ/LOG/signup">
              <span class="mainMenuItemCollapsable">
                <img src="../images/rechercher.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Inscription</div>
            </a>
          </div>
        <?php endif; ?>

        <div class="mainMenuItemLogin">
          <a href="/ProjetCYJ/CYJ/Carte/">
            <span class="mainMenuItemCollapsable">
              <img src="../images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">Menu complet</div>
          </a>
        </div>

        <div class="mainMenuItemLogin">
          <a href="/ProjetCYJ/CYF/">
            <span class="mainMenuItemCollapsable">
              <img src="../images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">CYF</div>
          </a>
        </div>

        <?php if ($est_connecte && $role_actuel === "admin"): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Admin/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel Admin</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "livreur")): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Livraison/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel livreur</div>
            </a>
          </div>
        <?php endif; ?>


        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "chef")): ?>
          <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Cuisinier/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Accès cuisine</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte): ?>
          <div class="mainMenuItem">
            <a href="#&action=logout">
              <span class="mainMenuItemCollapsable">
                <img src="../images/d&eacute;connecter.png" alt="Menu item">
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

    <!-- Les items dispo sur le restaurant (grid-area: section via menuprof.css) -->
    <main class="restaurant-content">

        <nav class="sticky-nav" aria-label="Navigation du menu">
            <a href="#populaires" class="active">Populaires</a>
            <a href="#burgers">Burgers</a>
            <a href="#pizzas">Pizzas</a>
            <a href="#wraps">Wraps &amp; Tacos</a>
            <a href="#sides">Sides</a>
            <a href="#desserts">Desserts</a>
            <a href="#boissons">Boissons</a>
        </nav>

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
                        <span class="menu-cat">Side</span>
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
                        <span class="menu-cat">Side</span>
                        <h3>Frites Maison</h3>
                        <p>Fra&icirc;ches, sel de Gu&eacute;rande</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Burgers -->
            <div class="section-header">
                <h2>Nos Burgers</h2>
                <p>Steaks smashés, recettes généreuses, sauces maison : du classique au plus gourmand.</p>
            </div>
            <div class="menu-grid">
                <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>CY Smash Burger &#9415;</h3>
                        <p>Double steak hach&eacute;, cheddar fondu, sauce maison, oignons croustillants</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">8.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>Triple Cheese Burger &#9415;</h3>
                        <p>Triple steak hach&eacute;, cheddar fondu, sauce maison, oignons croustillants</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">10.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>Chicken Burger</h3>
                        <p>Filet de poulet pan&eacute;, salade, tomate, mayo citronn&eacute;e</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">7.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>Veggie Burger</h3>
                        <p>Galette de l&eacute;gumes, fromage, roquette, sauce yaourt</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">8.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#burgers" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>Bacon King</h3>
                        <p>Triple steak, bacon grill&eacute;, cheddar, cornichons, sauce BBQ</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">11.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Pizzas -->
            <div class="section-header">
                <h2>Nos Pizzas</h2>
                <p>Pâte croustillante, mozzarella fondante et toppings bien chargés : simples et efficaces.</p>
            </div>
            <div class="menu-grid">
                <a href="/ProjetCYJ/CYJ/Carte/#pizzas" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FCE4EC,#F48FB1)">
                        <span class="menu-emoji">&#127829;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Pizza</span>
                        <h3>Pizza Margherita</h3>
                        <p>Sauce tomate, mozzarella, basilic frais</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">9.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#pizzas" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FCE4EC,#F48FB1)">
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

                <a href="/ProjetCYJ/CYJ/Carte/#pizzas" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FCE4EC,#F48FB1)">
                        <span class="menu-emoji">&#127829;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Pizza</span>
                        <h3>Pizza Pepperoni</h3>
                        <p>Sauce tomate, mozzarella, pepperoni piquant</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">10.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Wraps & Tacos -->
            <div class="section-header">
                <h2>Nos Wraps &amp; Tacos</h2>
                <p>Format pratique, ultra gourmand : poulet, frites, fromage et sauces au choix.</p>
            </div>
            <div class="menu-grid">
                <a href="/ProjetCYJ/CYJ/Carte/#wraps" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E8F5E9,#A5D6A7)">
                        <span class="menu-emoji">&#127790;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Wrap</span>
                        <h3>Wrap Poulet Avocat</h3>
                        <p>Poulet grill&eacute;, avocat, salade, sauce ranch</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">7.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#wraps" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E8F5E9,#A5D6A7)">
                        <span class="menu-emoji">&#127790;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Tacos</span>
                        <h3>Tacos Classique</h3>
                        <p>Viande hach&eacute;e, frites, sauce fromag&egrave;re, salade</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">8.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#wraps" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E8F5E9,#A5D6A7)">
                        <span class="menu-emoji">&#127790;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Tacos</span>
                        <h3>Tacos XL</h3>
                        <p>Double viande, double fromage, frites, sauce alg&eacute;rienne</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">10.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Accompagnements -->
            <div class="section-header">
                <h2>Nos accompagnements</h2>
                <p>À partager (ou pas) : frites maison, onion rings, nuggets… le bonus qui fait plaisir.</p>
            </div>
            <div class="menu-grid">
                <a href="/ProjetCYJ/CYJ/Carte/#sides" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFF3E0,#FFCC80)">
                        <span class="menu-emoji">&#127839;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Accompagnements</span>
                        <h3>Frites Maison</h3>
                        <p>Frites fra&icirc;ches, sel de Gu&eacute;rande</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#sides" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFF3E0,#FFCC80)">
                        <span class="menu-emoji">&#127839;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Accompagnements</span>
                        <h3>Nuggets x8</h3>
                        <p>Nuggets croustillants avec sauce au choix</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">5.90 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#sides" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#FFF3E0,#FFCC80)">
                        <span class="menu-emoji">&#127839;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Accompagnements</span>
                        <h3>Onion Rings</h3>
                        <p>Rondelles d&#39;oignon pan&eacute;es, sauce barbecue</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">4.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Desserts -->
            <div class="section-header">
                <h2>Nos Desserts</h2>
                <p>La touche sucrée : cookie géant, brownie fondant, glaces au choix.</p>
            </div>
            <div class="menu-grid">
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

                <a href="/ProjetCYJ/CYJ/Carte/#desserts" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fce4ec,#f48fb1)">
                        <span class="menu-emoji">&#127846;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Dessert</span>
                        <h3>Brownie</h3>
                        <p>Brownie fondant au chocolat noir</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
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
                        <h3>Glace 2 Boules</h3>
                        <p>Vanille, chocolat, fraise ou caramel</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">4.00 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>
            </div><br>
            <!-- Boissons -->
            <div class="section-header">
                <h2>Nos Boissons</h2>
                <p>Pour accompagner : sodas bien frais, eau plate/pétillante, jus pressé.</p>
            </div>
            <div class="menu-grid">
                <a href="/ProjetCYJ/CYJ/Carte/#boissons" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E3F2FD,#90CAF9)">
                        <span class="menu-emoji">&#129380;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Boisson</span>
                        <h3>Coca-Cola 33cl</h3>
                        <p>Canette classique bien fra&icirc;che</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">2.00 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#boissons" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E3F2FD,#90CAF9)">
                        <span class="menu-emoji">&#129380;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Boisson</span>
                        <h3>Eau Min&eacute;rale 50cl</h3>
                        <p>Eau plate ou p&eacute;tillante</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">1.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

                <a href="/ProjetCYJ/CYJ/Carte/#boissons" class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#E3F2FD,#90CAF9)">
                        <span class="menu-emoji">&#129380;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Boisson</span>
                        <h3>Jus d&#39;Orange Frais</h3>
                        <p>Press&eacute; sur place, 100% pur jus</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
                            <span class="menu-add">+</span>
                        </div>
                    </div>
                </a>

            </div>
        </section>


    </main>
    </div>
    <!-- FOOTER (le même que pour les autres pages) -->
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
    <script src="../js/menu-toggle.js"></script>
</body>

</html>