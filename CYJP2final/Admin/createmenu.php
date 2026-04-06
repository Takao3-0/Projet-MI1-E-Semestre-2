<?php  

    require_once '../../../protection.php';
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    function atributs_code_article($pdo_commandes, $category, $code_base_categorie)
    {
        $stmt = $pdo_commandes->prepare("SELECT MAX(code) as max_code FROM articles WHERE categorie = ?");
        $stmt->execute([$category]);
        $resultat = $stmt->fetch();
        $code_actuel = $resultat['max_code'];

        if ($code_actuel === null) {
            return $code_base_categorie + 1;
        }
        if ($code_actuel == ($code_base_categorie + 99)) {
            $code_actuel = ($code_base_categorie * 10);
        }
        return $code_actuel + 1;
    }
    
    $message = '';
    $articles = [];
    $articles_burger = [];
    $articles_pizza = [];
    $articles_wrap = [];
    $articles_accompagnement = [];
    $articles_boisson = [];

    if ($role_actuel === "admin" || $role_actuel === "fakeadmin") {
        $stmt = $pdo_commandes->prepare("SELECT * FROM articles");
        $stmt->execute();
        $articles = $stmt->fetchAll();

        foreach ($articles as $article) {
            $row = array_change_key_case($article, CASE_LOWER);
            $cat = isset($row['categorie']) ? (string) $row['categorie'] : '';
            if ($cat === 'Burger') {
                $articles_burger[] = $article;
            } elseif ($cat === 'Pizza') {
                $articles_pizza[] = $article;
            } elseif ($cat === 'Wrap' || $cat === 'Tacos') {
                $articles_wrap[] = $article;
            } elseif ($cat === 'Accompagnement') {
                $articles_accompagnement[] = $article;
            } elseif ($cat === 'Boisson') {
                $articles_boisson[] = $article;
            }
        }

        if (isset($_POST['category'], $_POST['name'], $_POST['description'], $_POST['prix'])) {
            $category = $_POST['category'];
            $nom = $_POST['name'];
            $description = trim((string) $_POST['description']);
            $prix = (float) str_replace(',', '.', (string) $_POST['prix']);

            if ($prix <= 0) {
                $message = "Le prix doit être supérieur à 0";
            } else {
                $code = null;
                $type_post = null;
                switch ($category) {
                    case 'Burger':
                        $type_post = 'burger';
                        $code = atributs_code_article($pdo_commandes, 'Burger', 1000);
                        break;
                    case 'Pizza':
                        $type_post = 'pizza';
                        $code = atributs_code_article($pdo_commandes, 'Pizza', 1100);
                        break;
                    case 'Wrap':
                        $type_post = 'wrap';
                        $code = atributs_code_article($pdo_commandes, 'Wrap', 1200);
                        break;
                    case 'Duo':
                        $type_post = 'wrap';
                        $code = atributs_code_article($pdo_commandes, 'Duo', 1800);
                        break;
                    default:
                        $message = "Type de menu non reconnu.";
                }

                if ($message === '' && $code !== null && $type_post !== null) {
                    $ids_composition = [];
                    if ($category === 'Burger') {
                        foreach (['burger_menu_burger', 'burger_menu_frite', 'burger_menu_boisson'] as $champ) {
                            if (!empty($_POST[$champ])) {
                                $ids_composition[] = (int) $_POST[$champ];
                            }
                        }
                    } elseif ($category === 'Pizza') {
                        foreach (['pizza_menu_pizza', 'pizza_menu_frite', 'pizza_menu_boisson'] as $champ) {
                            if (!empty($_POST[$champ])) {
                                $ids_composition[] = (int) $_POST[$champ];
                            }
                        }
                    } elseif ($category === 'Wrap') {
                        foreach (['wrap_menu_wrap', 'wrap_menu_frite', 'wrap_menu_boisson'] as $champ) {
                            if (!empty($_POST[$champ])) {
                                $ids_composition[] = (int) $_POST[$champ];
                            }
                        }
                    } elseif ($category === 'Duo') {
                        foreach (['duo_burger_1', 'duo_burger_2', 'duo_frite_1', 'duo_frite_2', 'duo_boisson_1', 'duo_boisson_2'] as $champ) {
                            if (!empty($_POST[$champ])) {
                                $ids_composition[] = (int) $_POST[$champ];
                            }
                        }
                    }
                    if ($description !== '') {
                        $description .= "\n\n";
                    }
                    $description .= '[composition_menu] ' . json_encode($ids_composition, JSON_UNESCAPED_UNICODE);

                    try {
                        $stmt = $pdo_commandes->prepare(
                            "INSERT INTO articles (code, nom, prix, categorie, type_post, description) VALUES (?, ?, ?, ?, ?, ?)"
                        );
                        $stmt->execute([$code, $nom, $prix, $category, $type_post, $description]);
                        $message = "Menu enregistré (code produit : " . (int) $code . ").";
                    } catch (Throwable $e) {
                        $message = "Erreur lors de l'enregistrement en base.";
                    }
                }
            }
        }
    }







?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Creation menu</title>
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="admin.css">
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
    <div class="pcnavbar">
        <nav class="navbar">
        <div class="navbar-inner">
            <a href="#" class="navbar-logo">
            <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
            </a>

            <ul class="navbar-links">
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

    <br><br>

    
    <div class="admin-container">
        <h1>Ajouter un nouvel menu à la carte</h1>
        <p class="subtitle">Configuration de la carte du restaurant</p>

        <?php if ($message !== ''): ?>
            <p class="subtitle" style="margin-bottom:1rem"><?php echo htmlspecialchars($message); ?></p>
        <?php endif; ?>

        <form action="" method="POST"> 

            <div class="name" id="name">
                    <label for="name">Nom du menu</label>
                    <input type="text" id="name" name="name" required> 
            </div>

            <div class="category" id="category">
                <label for="menu_category">Type de menu</label>
                <select id="menu_category" name="category" required>
                    <option value="" disabled selected>Choisir...</option>
                    <option value="Burger">Burger + Frite + Boisson</option> <!-- 3 articles -->
                    <option value="Pizza">Pizza + Frite + Boisson</option> <!-- 3 articles -->
                    <option value="Wrap">Wrap + Frite + Boisson</option> <!-- 3 articles -->
                    <option value="Duo">Formule duo (6 choix : 2 burgers, 2 accompagnements, 2 boissons)</option>
                </select>
            </div>

            <div id="bloc-choix-burger" class="ChoixArticle choix-menu-type" style="display: none;">
                <div class="form-group">
                    <label for="burger_menu_burger">Burger</label>
                    <select id="burger_menu_burger" name="burger_menu_burger">
                        <option value="">Choisir un burger...</option>
                        <?php foreach ($articles_burger as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="burger_menu_frite">Frite / accompagnement</label>
                    <select id="burger_menu_frite" name="burger_menu_frite">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_accompagnement as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="burger_menu_boisson">Boisson</label>
                    <select id="burger_menu_boisson" name="burger_menu_boisson">
                        <option value="">Choisir une boisson...</option>
                        <?php foreach ($articles_boisson as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="bloc-choix-pizza" class="ChoixArticle choix-menu-type" style="display: none;">
                <div class="form-group">
                    <label for="pizza_menu_pizza">Pizza</label>
                    <select id="pizza_menu_pizza" name="pizza_menu_pizza">
                        <option value="">Choisir une pizza...</option>
                        <?php foreach ($articles_pizza as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pizza_menu_frite">Frite / accompagnement</label>
                    <select id="pizza_menu_frite" name="pizza_menu_frite">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_accompagnement as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="pizza_menu_boisson">Boisson</label>
                    <select id="pizza_menu_boisson" name="pizza_menu_boisson">
                        <option value="">Choisir une boisson...</option>
                        <?php foreach ($articles_boisson as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="bloc-choix-wrap" class="ChoixArticle choix-menu-type" style="display: none;">
                <div class="form-group">
                    <label for="wrap_menu_wrap">Wrap / tacos</label>
                    <select id="wrap_menu_wrap" name="wrap_menu_wrap">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_wrap as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="wrap_menu_frite">Frite / accompagnement</label>
                    <select id="wrap_menu_frite" name="wrap_menu_frite">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_accompagnement as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="wrap_menu_boisson">Boisson</label>
                    <select id="wrap_menu_boisson" name="wrap_menu_boisson">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_boisson as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div id="bloc-choix-duo" class="ChoixArticle choix-menu-type" style="display: none;">
                <p class="subtitle" style="margin-bottom:1rem">Formule duo : 2 burgers, 2 accompagnements, 2 boissons</p>
                <div class="form-group">
                    <label for="duo_burger_1">Burger 1</label>
                    <select id="duo_burger_1" name="duo_burger_1">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_burger as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duo_burger_2">Burger 2</label>
                    <select id="duo_burger_2" name="duo_burger_2">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_burger as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duo_frite_1">Accompagnement 1</label>
                    <select id="duo_frite_1" name="duo_frite_1">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_accompagnement as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duo_frite_2">Accompagnement 2</label>
                    <select id="duo_frite_2" name="duo_frite_2">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_accompagnement as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duo_boisson_1">Boisson 1</label>
                    <select id="duo_boisson_1" name="duo_boisson_1">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_boisson as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="duo_boisson_2">Boisson 2</label>
                    <select id="duo_boisson_2" name="duo_boisson_2">
                        <option value="">Choisir...</option>
                        <?php foreach ($articles_boisson as $article) : ?>
                        <option value="<?php echo (int) $article['id']; ?>"><?php echo htmlspecialchars($article['nom'], ENT_QUOTES, 'UTF-8'); ?> | <?php echo htmlspecialchars((string) $article['prix'], ENT_QUOTES, 'UTF-8'); ?> €</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="description" id="description" style="display: none;">
                <label for="description">Description du menu</label>
                <input type="text" id="description" name="description" required>
            </div>

            <div class="prix" id="prix" style="display: none;">
                <label for="prix">Prix du menu (€)</label>
                <input type="number" id="prix" name="prix" step="0.50" placeholder="0.00" required>
            </div>

            <button type="submit" class="submit">Enregistrer le menu</button>
        </form>
    </div>
    <script src="../js/menu-toggle.js"></script>
    <script>
        function afficherChoixMenu() {
            var type = document.getElementById('menu_category').value;

            document.getElementById('bloc-choix-burger').style.display = 'none';
            document.getElementById('bloc-choix-pizza').style.display = 'none';
            document.getElementById('bloc-choix-wrap').style.display = 'none';
            document.getElementById('bloc-choix-duo').style.display = 'none';

            var i;
            var duoIds = ['duo_burger_1', 'duo_burger_2', 'duo_frite_1', 'duo_frite_2', 'duo_boisson_1', 'duo_boisson_2'];

            document.getElementById('burger_menu_burger').required = false;
            document.getElementById('burger_menu_frite').required = false;
            document.getElementById('burger_menu_boisson').required = false;
            document.getElementById('pizza_menu_pizza').required = false;
            document.getElementById('pizza_menu_frite').required = false;
            document.getElementById('pizza_menu_boisson').required = false;
            document.getElementById('wrap_menu_wrap').required = false;
            document.getElementById('wrap_menu_frite').required = false;
            document.getElementById('wrap_menu_boisson').required = false;
            for (i = 0; i < duoIds.length; i++) {
                document.getElementById(duoIds[i]).required = false;
            }

            document.querySelector('.description').style.display = 'none';
            document.querySelector('.prix').style.display = 'none';

            if (type == 'Burger') {
                document.getElementById('bloc-choix-burger').style.display = 'block';
                document.getElementById('burger_menu_burger').required = true;
                document.getElementById('burger_menu_frite').required = true;
                document.getElementById('burger_menu_boisson').required = true;
                document.querySelector('.description').style.display = 'block';
                document.querySelector('.prix').style.display = 'block';
            } else if (type == 'Pizza') {
                document.getElementById('bloc-choix-pizza').style.display = 'block';
                document.getElementById('pizza_menu_pizza').required = true;
                document.getElementById('pizza_menu_frite').required = true;
                document.getElementById('pizza_menu_boisson').required = true;
                document.querySelector('.description').style.display = 'block';
                document.querySelector('.prix').style.display = 'block';
            } else if (type == 'Wrap') {
                document.getElementById('bloc-choix-wrap').style.display = 'block';
                document.getElementById('wrap_menu_wrap').required = true;
                document.getElementById('wrap_menu_frite').required = true;
                document.getElementById('wrap_menu_boisson').required = true;
                document.querySelector('.description').style.display = 'block';
                document.querySelector('.prix').style.display = 'block';
            } else if (type == 'Duo') {
                document.getElementById('bloc-choix-duo').style.display = 'block';
                for (i = 0; i < duoIds.length; i++) {
                    document.getElementById(duoIds[i]).required = true;
                }
                document.querySelector('.description').style.display = 'block';
                document.querySelector('.prix').style.display = 'block';
            }
        }

        document.getElementById('menu_category').addEventListener('change', afficherChoixMenu);
        afficherChoixMenu();
    </script>
    
</body>
</html>