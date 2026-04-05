<?php  

    require_once '../../../protection.php';
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    function atributs_code_article($pdo_commandes, $category, $code_base_categorie) 
    {
        // 1. On cherche le code maximum existant pour cette catégorie précise
        $stmt = $pdo_commandes->prepare("SELECT MAX(code) as max_code FROM articles WHERE categorie = ?");
        $stmt->execute([$category]);
        $resultat = $stmt->fetch();
        
        $code_actuel = $resultat['max_code'];

        // 2. SÉCURITÉ : Si la catégorie est totalement vide (aucun article)
        // Exemple : si la base est 1400, le tout premier article sera 1401
        if ($code_actuel === null) {
            return $code_base_categorie + 1; 
        }

        // 3. LE SAUT : Si on a atteint la limite des 99 (ex: 1499)
        if ($code_actuel == ($code_base_categorie + 99)) {
            // On multiplie la base par 10 (ex: 1400 devient 14000)
            $code_actuel = ($code_base_categorie * 10);
        }

        // 4. On retourne le code suivant
        // Cas normal : 1403 -> 1404
        // Cas du saut : 14000 -> 14001
        return $code_actuel + 1;
    }
    
    if ($role_actuel === "admin" || $role_actuel === "fakeadmin") {
        $stmt = $pdo_commandes->prepare("SELECT * FROM articles");
        $stmt->execute();
        $articles = $stmt->fetchAll();

        if(isset($_POST['category'], $_POST['name'], $_POST['description'], $_POST['prix'])) {
            $category = $_POST['category'];
            $nom = $_POST['name'];
            $description = $_POST['description'];
            $prix = $_POST['prix'];

            if($prix <= 0)
                $message = "Le prix doit être supérieur à 0";
            else {
                switch($category) 
                {
                    case "Burger":
                        $type_post = "burger";
                        $code = atributs_code_article($pdo_commandes, $category, 1000);
                        break;
                    case "Pizza":
                        $type_post = "pizza";
                        $code = atributs_code_article($pdo_commandes, $category, 1100);
                        break;
                    case "Wrap":
                    case "Tacos":
                        $type_post = "wrap";
                        $code = atributs_code_article($pdo_commandes, $category, 1200);
                        break;
                    case "Accompagnement":
                        $type_post = "side";
                        $code = atributs_code_article($pdo_commandes, $category, 1300);
                        break;
                    case "Dessert":
                        $type_post = "dessert";
                        $code = atributs_code_article($pdo_commandes, $category, 1400);
                        break;
                    case "Boisson":
                        $type_post = "boisson";
                        $code = atributs_code_article($pdo_commandes, $category, 1500);
                        break;
                    default:
                        $message = "Catégorie invalide";
                        header("Location: ../index.php");
                        exit;
                        
                }
                $stmt = $pdo_commandes->prepare("INSERT INTO articles (code, nom, prix, categorie, type_post, description) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$code, $nom, $prix, $category, $type_post, $description]);
            }
        }

    }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ajouter un nouvel article </title>
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
        <h1>Ajouter un nouvel article</h1>
        <p class="subtitle">Configuration de la carte du restaurant</p>

        <form action="" method="POST"> <div class="form-group">
                <label for="category">Type d'article</label>
                <select id="category" name="category" required>
                    <option value="" disabled selected>Choisir...</option>
                    <option value="Burger">Burger</option>
                    <option value="Pizza">Pizza</option>
                    <option value="Wrap">Wraps</option> 
                    <option value="Tacos">Tacos</option>
                    <option value="Accompagnement">Accompagnement</option>
                    <option value="Dessert">Dessert</option>
                    <option value="Boisson">Boisson</option>
                </select>
            </div>

            <div class="name">
                <label for="name">Nom de l'article</label>
                <input type="text" id="name" name="name" required> 
            </div>
            
            <div class="description">
                <label for="description">Description de l'article</label>
                <input type="text" id="description" name="description" required>
            </div>

            <div class="prix">
                <label for="prix">Prix (€)</label>
                <input type="number" id="prix" name="prix" step="0.50" placeholder="0.00" required>
            </div>

            <button type="submit" class="submit">Enregistrer l'article</button>
        </form>
    </div>
    <script src="../js/menu-toggle.js"></script>
    
</body>
</html>