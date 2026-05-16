<?php 
    require_once '../../../protection.php';
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    if (isset($_GET['ajax']) && $_GET['ajax'] === 'blocage' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        header('Content-Type: application/json; charset=utf-8');
        if ($role_actuel !== 'admin' && $role_actuel !== 'fakeadmin') {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Accès refusé.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $csrf_in = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        if (!cyj_csrf_check($csrf_in)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'error' => 'Jeton CSRF invalide.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            echo json_encode(['ok' => false, 'error' => 'Requête JSON invalide.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $action = isset($data['action']) ? (string) $data['action'] : '';
        $target_id = (int) ($data['user_id'] ?? 0);
        $motif = isset($data['motif']) ? trim((string) $data['motif']) : '';
        if ($motif === '') {
            $motif = null;
        }
        try {
            $stAdmin = $pdo_users->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stAdmin->execute([(string) ($_SESSION['nom_utilisateur'] ?? '')]);
            $admin_id = (int) $stAdmin->fetchColumn();
            if ($admin_id <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Administrateur introuvable.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($target_id <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Utilisateur invalide.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($target_id === $admin_id) {
                echo json_encode(['ok' => false, 'error' => 'Vous ne pouvez pas bloquer votre propre compte.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $stT = $pdo_users->prepare('SELECT id, username, role, yumland FROM users WHERE id = ? LIMIT 1');
            $stT->execute([$target_id]);
            $target = $stT->fetch(PDO::FETCH_ASSOC);
            if (!$target) {
                echo json_encode(['ok' => false, 'error' => 'Utilisateur introuvable.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ((int) ($target['yumland'] ?? 0) !== 1) {
                echo json_encode(['ok' => false, 'error' => 'Cet utilisateur ne fait pas partie de Yumland.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $role_t = (string) ($target['role'] ?? '');
            if ($role_t === 'admin' || $role_t === 'fakeadmin') {
                echo json_encode(['ok' => false, 'error' => 'Impossible de bloquer un compte administrateur.'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($action === 'block') {
                // Transaction + verrou de ligne : évite l'insertion concurrente
                // de deux blocages ouverts pour le même user (race condition).
                $pdo_users->beginTransaction();
                try {
                    $stOpen = $pdo_users->prepare(
                        'SELECT id FROM blocages
                         WHERE user_id = ? AND date_deblocage IS NULL
                         FOR UPDATE'
                    );
                    $stOpen->execute([$target_id]);
                    if ($stOpen->fetchColumn() !== false) {
                        $pdo_users->commit();
                        echo json_encode(
                            ['ok' => true, 'blocked' => true, 'message' => 'Déjà bloqué.'],
                            JSON_UNESCAPED_UNICODE
                        );
                        exit;
                    }
                    $ins = $pdo_users->prepare(
                        'INSERT INTO blocages (user_id, bloque_par, motif) VALUES (?, ?, ?)'
                    );
                    $ins->execute([$target_id, $admin_id, $motif]);
                    $pdo_users->commit();
                } catch (Throwable $e) {
                    if ($pdo_users->inTransaction()) {
                        $pdo_users->rollBack();
                    }
                    throw $e;
                }
                $ip_banned = false;
                try {
                    $stLip = $pdo_users->prepare('SELECT last_login_ip FROM users WHERE id = ? LIMIT 1');
                    $stLip->execute([$target_id]);
                    $lip_raw = $stLip->fetchColumn();
                    $lip = is_string($lip_raw) ? trim($lip_raw) : '';
                    if ($lip !== '' && filter_var($lip, FILTER_VALIDATE_IP) !== false) {
                        $insIp = $pdo_users->prepare(
                            'INSERT INTO bannissements_ip (ip, user_id, bloque_par, motif, date_ban) VALUES (?, ?, ?, ?, NOW())'
                        );
                        $motif_ip = $motif !== null && $motif !== ''
                            ? ('Blocage compte : ' . $motif)
                            : 'Blocage compte (ban IP automatique)';
                        $insIp->execute([$lip, $target_id, $admin_id, $motif_ip]);
                        $ip_banned = true;
                    }
                } catch (Throwable $e) {
                    // Table / colonne absente : le blocage compte reste effectif.
                }
                echo json_encode(['ok' => true, 'blocked' => true, 'ip_banned' => $ip_banned], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if ($action === 'unblock') {
                $up = $pdo_users->prepare(
                    'UPDATE blocages SET date_deblocage = NOW() WHERE user_id = ? AND date_deblocage IS NULL'
                );
                $up->execute([$target_id]);
                try {
                    $upIp = $pdo_users->prepare(
                        'UPDATE bannissements_ip SET date_deban = NOW() WHERE user_id = ? AND date_deban IS NULL'
                    );
                    $upIp->execute([$target_id]);
                } catch (Throwable $e) {
                }
                echo json_encode(['ok' => true, 'blocked' => false], JSON_UNESCAPED_UNICODE);
                exit;
            }
            echo json_encode(['ok' => false, 'error' => 'Action inconnue.'], JSON_UNESCAPED_UNICODE);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Erreur serveur (blocages).'], JSON_UNESCAPED_UNICODE);
        }
        exit;
    }

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

        $current_admin_user_id = null;
        try {
            $stAd = $pdo_users->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
            $stAd->execute([(string) ($_SESSION['nom_utilisateur'] ?? '')]);
            $current_admin_user_id = (int) $stAd->fetchColumn() ?: null;
        } catch (Throwable $e) {
            $current_admin_user_id = null;
        }

        $blocked_user_ids = [];
        $uids_page = [];
        foreach ($tableau_users_page as $u) {
            if (!empty($u['id'])) {
                $uids_page[] = (int) $u['id'];
            }
        }
        if ($uids_page !== []) {
            try {
                $ph = implode(',', array_fill(0, count($uids_page), '?'));
                $stb = $pdo_users->prepare(
                    "SELECT DISTINCT user_id FROM blocages WHERE date_deblocage IS NULL AND user_id IN ($ph)"
                );
                $stb->execute($uids_page);
                foreach ($stb->fetchAll(PDO::FETCH_COLUMN, 0) as $bid) {
                    $blocked_user_ids[(int) $bid] = true;
                }
            } catch (Throwable $e) {
            }
        }

        $stats_clients_yumland = $total_users;
        $stats_articles = count($articles);
        $stats_cmd_total = 0;
        $stats_cmd_payees = 0;
        $stats_ca_paye = 0.0;
        $stats_frais_livraison = 0.0;
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
            $q = $pdo_commandes->query("SELECT COALESCE(SUM(frais_livraison), 0) FROM commandes WHERE statut = 'Payé'");
            if ($q) {
                $stats_frais_livraison = (float) str_replace(',', '.', (string) $q->fetchColumn());
            }
            //ajoute la condition que les frais sont >0
            $q = $pdo_commandes->query("SELECT COUNT(*) FROM commandes WHERE statut = 'Payé' AND frais_livraison > 0");
            if ($q) {
                $stats_frais_livraison -= (int) $q->fetchColumn() * 1;
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
    <meta name="csrf-token" content="<?php echo htmlspecialchars(cyj_csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

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
            <?php if ($est_connecte && $role_actuel === "livreur"): ?>
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


            <?php if ($est_connecte && $role_actuel === "livreur"): ?>
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

    <p id="admin-blocage-feedback" class="admin-blocage-feedback" role="status" aria-live="polite"></p>

    <section class="table">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Date d'inscription</th>
                    <th>Commandes</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
            <?php foreach ($tableau_users_page as $user): ?>
                <?php if ($user['yumland'] === 1): ?>
                <?php
                    $uid_row = (int) ($user['id'] ?? 0);
                    $is_blocked_row = $uid_row > 0 && !empty($blocked_user_ids[$uid_row]);
                    $role_row = (string) ($user['role'] ?? '');
                    $is_protected_row = ($current_admin_user_id !== null && $uid_row === $current_admin_user_id)
                        || $role_row === 'admin'
                        || $role_row === 'fakeadmin';
                ?>
                <tr class="admin-user-row<?php echo $is_blocked_row ? ' admin-user-row--blocked' : ''; ?>" data-user-id="<?php echo $uid_row; ?>">
                    <td><?php echo htmlspecialchars((string) ($user['username'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php if ($user['email'] === null): ?>
                        <td>Ancienne connexion pas de mail</td>
                    <?php else: ?>
                        <td><?php echo htmlspecialchars((string) $user['email'], ENT_QUOTES, 'UTF-8'); ?></td>
                    <?php endif; ?>
                    <td><?php echo convertDate($user['created_at']); ?></td>
                    <td>Pas dans la table</td>
                    <td><?php echo transform_role_description($user['role']); ?></td>
                    <td class="admin-user-row-actions">
                        <?php if ($is_protected_row): ?>
                            <span class="admin-action-muted" title="Compte protégé">Blocage N/A</span>
                        <?php elseif ($is_blocked_row): ?>
                            <button type="button" class="admin-blocage-btn admin-blocage-btn--unblock" data-action="unblock">Débloquer</button>
                        <?php else: ?>
                            <button type="button" class="admin-blocage-btn admin-blocage-btn--block" data-action="block">Bloquer</button>
                        <?php endif; ?>
                        <button type="button" class="admin-fake-action-btn admin-fake-action-btn--vip" title="Fonction fictive — à brancher plus tard">VIP</button>
                    </td>
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
                        <td>Frais de livraison reversé au livreur</td>
                        <td><?php echo number_format($stats_frais_livraison, 2, ',', ' '); ?> &euro;</td>
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



    <script>
    (function () {
        var csrfMeta = document.querySelector('meta[name="csrf-token"]');
        var csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';
        var feedback = document.getElementById('admin-blocage-feedback');
        function setFeedback(text, isError) {
            if (!feedback) return;
            feedback.textContent = text || '';
            feedback.classList.toggle('admin-blocage-feedback--error', !!isError);
        }
        function swapBlockButton(tr, nowBlocked) {
            var cell = tr.querySelector('.admin-user-row-actions');
            if (!cell) return;
            var vip = cell.querySelector('.admin-fake-action-btn--vip');
            cell.innerHTML = '';
            if (nowBlocked) {
                var u = document.createElement('button');
                u.type = 'button';
                u.className = 'admin-blocage-btn admin-blocage-btn--unblock';
                u.setAttribute('data-action', 'unblock');
                u.textContent = 'Débloquer';
                cell.appendChild(u);
            } else {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'admin-blocage-btn admin-blocage-btn--block';
                b.setAttribute('data-action', 'block');
                b.textContent = 'Bloquer';
                cell.appendChild(b);
            }
            if (vip) {
                cell.appendChild(vip);
            }
            bindOneRow(tr);
        }
        function bindOneRow(tr) {
            var btn = tr.querySelector('.admin-blocage-btn');
            if (!btn) return;
            btn.addEventListener('click', function () {
                var userId = parseInt(tr.getAttribute('data-user-id') || '0', 10);
                var action = btn.getAttribute('data-action') || '';
                var motif = null;
                if (action === 'block') {
                    motif = window.prompt('Motif du blocage (optionnel) :', '');
                    if (motif === null) return;
                }
                setFeedback('Traitement…', false);
                btn.disabled = true;
                fetch('index.php?ajax=blocage', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-Token': csrfToken
                    },
                    body: JSON.stringify({
                        action: action,
                        user_id: userId,
                        motif: motif === null ? '' : String(motif)
                    })
                })
                    .then(function (res) { return res.json(); })
                    .then(function (data) {
                        btn.disabled = false;
                        if (!data || !data.ok) {
                            setFeedback((data && data.error) ? String(data.error) : 'Échec de la requête.', true);
                            return;
                        }
                        var blocked = !!data.blocked;
                        tr.classList.toggle('admin-user-row--blocked', blocked);
                        setFeedback(
                            blocked
                                ? (data.ip_banned
                                    ? 'Compte bloqué et dernière IP de connexion bannie.'
                                    : 'Compte bloqué (aucune IP enregistrée — connexion requise pour bannir l’IP).')
                                : 'Utilisateur débloqué (IP levée si elle avait été bannie).',
                            false
                        );
                        swapBlockButton(tr, blocked);
                    })
                    .catch(function () {
                        btn.disabled = false;
                        setFeedback('Erreur réseau.', true);
                    });
            });
        }
        document.querySelectorAll('tr.admin-user-row[data-user-id]').forEach(bindOneRow);
    })();
    </script>
    <script src="../js/menu-toggle.js"></script>
</body>
</html>