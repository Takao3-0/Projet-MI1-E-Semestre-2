<?php 
    require_once '../../../protection.php';
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    if ($role_actuel === "admin" || $role_actuel === "fakeadmin") {
        $stmt = $pdo_commandes->prepare("SELECT * FROM articles");
        $stmt->execute();
        $articles = $stmt->fetchAll();

        $yumland = 1;


        /*
        Pour filtrer les recherches ont fonctionne par brique LEGO
        On a un bloc initial vide, en fonction des parametres passé par $_GET on ajoute les briques necessaires à la requete qu'on va envoyer à la base de données. 
        A la fin on a un $sql qui contient une requete qui répond au recherches fait par l'utilisateur. 
        */

        $sql = "SELECT * FROM users WHERE yumland = ?";
        $exec = [$yumland];

        if(isset($_GET['recherche'])) 
        {
            $recherche = $_GET['recherche'];
            $sql .= " AND (username LIKE ? OR email LIKE ? OR role LIKE ? or id = ?) ";
            array_push($exec, "%$recherche%", "%$recherche%", "%$recherche%", $recherche);
        }

        /*if(isset($_GET['filtre_statut'])) //On l'utilise pas pour le moment puisqu'on a pas d'info correspondante dans la database
        {
            $filtre_statut = $_GET['filtre_statut'];
            $sql .= " AND actif_user = ?";
            array_push($exec, $filtre_statut);
        }*/

        /*if(isset($_GET['tri'])) //On l'utilise pas pour le moment puisqu'on a pas d'info correspondante dans la database
        {
            $tri = $_GET['tri'];
            $sql .= " ORDER BY $tri";
            array_push($exec, $tri);
        }*/

        if(isset($_GET['role']))
        {
            $role = $_GET['role'];
            switch($role)
            {
                case "admin":
                    $sql .= " AND (role =? OR role =?)";
                    array_push($exec, "admin", "fakeadmin");
                    break;
                case "etudiant":
                    $sql .= " AND (role = ? OR role = ?)";
                    array_push($exec, "etudiant", "colab.");
                    break;
                case "livreur":
                    $sql .= " AND role = ?";
                    array_push($exec, "livreur");
                    break;
                case "chef":
                    $sql .= " AND role = ?";
                    array_push($exec, "chef");
                    break;
                default:
                    break;
            }
        }

        $stmt = $pdo_users->prepare($sql);
        $stmt->execute($exec);
        $users = $stmt->fetchAll();

        $users_per_page = 5;
        $total_users = count($users);
        $max_page = ceil($total_users / $users_per_page);

        if(isset($_GET['page'])) {
            $page = (int) $_GET['page'];
        } else {
            $page = 1;
        }

        if ($page < 1) {
            $page = 1;
        }
        else if ($page > $max_page) {
            $page = $max_page;
        }

        //array_slicearray_slice($tableau_complet, $point_de_depart, $combien_on_en_garde);
        $tableau_users_page = array_slice($users, ($page - 1) * $users_per_page, $users_per_page);

        $stats_clients_yumland = $total_users;
        $stats_articles = count($articles);
        $stats_cmd_total = 0;
        $stats_cmd_payees = 0;
        $stats_ca_paye = 0.0;
        $stats_cmd_aujourdhui = 0;
        $stats_cmd_7j = 0;
        $stats_cmd_cuisine_actives = 0;
        $stats_lignes_articles = 0;

        try {
            $q = $pdo_commandes->query("SELECT COUNT(*) FROM commandes");
            if ($q) {
                $stats_cmd_total = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query("SELECT COUNT(*) FROM commandes WHERE statut = 'Payé'");
            if ($q) {
                $stats_cmd_payees = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query("SELECT COALESCE(SUM(total), 0) FROM commandes WHERE statut = 'Payé'");
            if ($q) {
                $stats_ca_paye = (float) str_replace(',', '.', (string) $q->fetchColumn());
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query(
                "SELECT COUNT(*) FROM commandes WHERE statut = 'Payé' AND DATE(date_commande) = CURDATE()"
            );
            if ($q) {
                $stats_cmd_aujourdhui = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query(
                "SELECT COUNT(*) FROM commandes WHERE statut = 'Payé' AND date_commande >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            if ($q) {
                $stats_cmd_7j = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query(
                "SELECT COUNT(*) FROM commandes WHERE statut = 'Payé' AND statut_production != 'Récupérée livreur'"
            );
            if ($q) {
                $stats_cmd_cuisine_actives = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_commandes->query("SELECT COUNT(*) FROM commande_items");
            if ($q) {
                $stats_lignes_articles = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }
        try {
            $q = $pdo_users->query("SELECT COUNT(*) FROM users WHERE yumland = 1");
            if ($q) {
                $stats_clients_yumland = (int) $q->fetchColumn();
            }
        } catch (Throwable $e) {
        }

    }
    else { //on dégage l'utilisateur qui n'est pas admin ou fakeadmin
        header("Location: ../index.php");
        exit();
    }

    //Sur mon site j'utilise des abreviations pour octroyer à un utilisateur des acces à certaines pages. Pour avoir une cohérence dans l'affichage pour le projet je rassemble les abreviations en leur donnant un nom plus explicite.
    function transform_role_description($role)
    {
        switch($role)
        {
            case "admin":
            case "fakeadmin":
                return "Administrateur";
            case "livreur":
                return "Livreur";
            case "chef":
                return "Cuisinier";
            case "colab.":
                return "Client";
            case "etudiant":
                return "Client";
            default:
                return "Rôle inconnu (".$role.")";
        }
    }

    function convertDate($date) //On passe la date en français pour l'affichage puisque dans la database elle est en américain
    {
      $date = date('d-m-Y H:i:s', strtotime($date));
      return $date;
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="admin.css">
    <title>Admin</title>
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
            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
                <li><a href="/ProjetCYJ/CYJ/Admin/createmenu.php">Création menu</a></li>
            <?php endif; ?>
            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
                <li><a href="/ProjetCYJ/CYJ/Admin/additems.php">Ajouter un article</a></li>
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
            <a href="#menu">
                <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">La Carte</div>
            </a>
            </div>

            <div class="mainMenuItemLogin">
            <a href="#features">
                <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">Le Restaurant</div>
            </a>
            </div>

            <div class="mainMenuItemLogin">
            <a href="/ProjetCYJ/CYJ/Carte/">
                <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">Menu complet</div>
            </a>
            </div>

            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
            <div class="mainMenuItemLogin">
                <a href="/ProjetCYJ/CYJ/Admin/">
                <span class="mainMenuItemCollapsable">
                    <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">Pannel Admin</div>
                </a>
            </div>
            <?php endif; ?>

            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
            <div class="mainMenuItemLogin">
                <a href="/ProjetCYJ/CYJ/Admin/createmenu.php">
                <span class="mainMenuItemCollapsable">
                    <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">Création menu</div>
                </a>
            </div>
            <?php endif; ?>

            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
            <div class="mainMenuItemLogin">
                <a href="/ProjetCYJ/CYJ/Admin/additems.php">
                <span class="mainMenuItemCollapsable">
                    <img src="../images/accueil.png" alt="Menu item">
                </span>
                <div class="mainMenuItemCollapsable">Ajouter un article</div>
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


            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "chef" || $role_actuel === "fakeadmin")): ?>
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

    <form method="GET" action="index.php">
        <section class="Filtres">
            <div class="recherche">
                <label for="rechercher">Rechercher :</label>
                <input type="text" name="recherche" id="rechercher" placeholder="Nom ou email">
            </div>
            
            <div class="filtres">
                <label for="filtre_statut">Filtrer :</label>
                <select name="filtre_statut" id="filtre_statut">
                    <option value="tous">Tous les utilisateurs</option>
                    <option value="commandes">Ayant commandé</option>
                    <option value="inactifs">Comptes inactifs</option>
                </select>
            </div>
            
            <div class="trier">
                <label for="tri">Trier :</label>
                <select name="tri" id="tri">
                    <option value="recent">Récent</option>
                    <option value="ancien">Ancien</option>
                    <option value="nb_commandes">Nombre de commandes</option>
                </select>
            </div>

            <div class="role">

                <label for="role">Rôle :</label>
                <select name="role" id="role">
                    <option value="tous">Tous les rôles</option>
                    <option value="admin">Administrateur</option>
                    <option value="etudiant">Client</option>
                    <option value="livreur">Livreur</option>
                    <option value="chef">Cuisinier</option>
                </select>
            </div>

            <div class="action-filtre">
                <span class="action-filtre-label" aria-hidden="true">&nbsp;</span>
                <button type="submit" class="btn-appliquer-filtres">Appliquer</button>
            </div>
        </section>
    </form>

    <section class="table">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Date d'inscription</th>
                    <th>Commandes</th>
                    <th>Statut</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($tableau_users_page as $user): ?>
                <?php if ($user['yumland'] === 1): ?>
                <tr>
                    <td><?php echo $user['username']; ?></td>
                    <?php if ($user['email'] === null): ?>
                        <td>Connexion classique pas de mail renseigné</td>
                    <?php else: ?>
                        <td><?php echo $user['email']; ?></td>
                    <?php endif; ?>
                    <td><?php echo convertDate($user['created_at']); ?></td>
                    <td>Pas dans la table</td>
                    <td><?php echo transform_role_description($user['role']); ?></td>
                </tr>
                <?php endif; ?>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
    <nav>
        <form method="GET" action="index.php">
            <?php if($page == 1): ?>
                <button class="btn-disabled" disabled>Précédent</button>
            <?php else: ?>
                <button type="submit" name="page" value="<?php echo $page - 1; ?>">Précédent</button>
            <?php endif; ?>
            <span>Page <?php echo $page; ?> sur <?php echo $max_page; ?></span>
            <?php if($page == $max_page): ?>
                <button class="btn-disabled" disabled>Suivant</button>
            <?php else: ?>
                <button type="submit" name="page" value="<?php echo $page + 1; ?>">Suivant</button>
            <?php endif; ?>
        </form>
    </nav>

    <section class="admin-dash" aria-labelledby="admin-dash-title">
        <div class="admin-dash-head">
            <h2 id="admin-dash-title" class="admin-dash-title">Synth&egrave;se d&rsquo;activit&eacute;</h2>
            <a href="additems.php" class="admin-dash-cta">Ajouter un article</a>
        </div>
        <div class="admin-dash-card">
            <div class="admin-dash-card-inner">
            <table class="admin-dash-table">
                <thead>
                    <tr>
                        <th scope="col">Indicateur</th>
                        <th scope="col">Valeur</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Clients Yumland</td>
                        <td><?php echo (int) $stats_clients_yumland; ?></td>
                    </tr>
                    <tr>
                        <td>R&eacute;f&eacute;rences au catalogue</td>
                        <td><?php echo (int) $stats_articles; ?></td>
                    </tr>
                    <tr>
                        <td>Commandes (tous statuts)</td>
                        <td><?php echo (int) $stats_cmd_total; ?></td>
                    </tr>
                    <tr>
                        <td>Commandes pay&eacute;es</td>
                        <td><?php echo (int) $stats_cmd_payees; ?></td>
                    </tr>
                    <tr>
                        <td>CA cumul&eacute; (pay&eacute;)</td>
                        <td><?php echo number_format($stats_ca_paye, 2, ',', ' '); ?> &euro;</td>
                    </tr>
                    <tr>
                        <td>Commandes pay&eacute;es aujourd&rsquo;hui</td>
                        <td><?php echo (int) $stats_cmd_aujourdhui; ?></td>
                    </tr>
                    <tr>
                        <td>Commandes pay&eacute;es (7 jours)</td>
                        <td><?php echo (int) $stats_cmd_7j; ?></td>
                    </tr>
                    <tr>
                        <td>En cours cuisine / livraison</td>
                        <td><?php echo (int) $stats_cmd_cuisine_actives; ?></td>
                    </tr>
                    <tr>
                        <td>Lignes produit (historique)</td>
                        <td><?php echo (int) $stats_lignes_articles; ?></td>
                    </tr>
                </tbody>
            </table>
            </div>
        </div>
    </section>



    <script src="../js/menu-toggle.js"></script>
</body>
</html>