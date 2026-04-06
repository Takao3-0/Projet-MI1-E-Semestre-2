<?php 
    require_once '../../../protection.php';
    $pdo_etudiant = $pdo;
    require_once __DIR__ . '/../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;
    require_once 'getapikey.php';
?>

<?php

  $statut = "En attente";
  $statut_production = "En attente";
  $bool_paiement_traite = false;
  /** Identifiant CYBank (GET transaction) — sert à éviter un double enregistrement au F5. */
  $cybank_transaction_id = null;
  $message = null;

    if(isset($_GET['montant']) && isset($_GET['transaction']) && isset($_GET['status']) && isset($_GET['vendeur']) && isset($_GET['control']))
    {
        $montant = $_GET['montant'];
        $transaction = $_GET['transaction'];
        $status = $_GET['status'];
        $vendeur = $_GET['vendeur'];
        $control_output = $_GET['control'];

        $API = getAPIKey($vendeur);

        $control_input = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $status . "#");
        if ($control_input == $control_output)
        {
            if ($status === "accepted")
            {
                $message = "Paiement effectué avec succès";
                $statut = "Payé";
                $cybank_transaction_id = $transaction;
                $bool_paiement_traite = true;
            }
            else
            {
                $message = "Erreur lors du paiement";
                $statut = "Erreur paiment CYBANK";
            }
        }
        else
        {
            $message = "Erreur lors de la vérification du paiement";
        }

    }

    function PaimentCYBANK($panier_total)
    {
        $vendeur = "MI-1_E";
        $API = getAPIKey($vendeur);
        $transaction = uniqid(); 
        $montant = number_format($panier_total, 2, '.', '');
        $retour = "https://alexandre-gourdon.fr/ProjetCYJ/CYJ/CYBank/index.php";
        $control = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $retour . "#");

        echo "<form action='https://www.plateforme-smc.fr/cybank/index.php' method='POST'>";
        echo "<input type='hidden' name='transaction' value='$transaction'>";
        echo "<input type='hidden' name='montant' value='$montant'>";
        echo "<input type='hidden' name='vendeur' value='$vendeur'>";
        echo "<input type='hidden' name='retour' value='$retour'>";
        echo "<input type='hidden' name='control' value='$control'>";
        echo "<input type='submit' value='Payer'>";
        echo "</form>";

        echo "<script>document.forms[0].submit();</script>";
        exit;
    }






    // ── Catalogue : même logique que Carte/index.php (tables articles + menus)
    $stmt = $pdo_commandes->prepare('SELECT * FROM articles');
    $stmt->execute();
    $articles_rows = $stmt->fetchAll();

    $stmt = $pdo_commandes->prepare('SELECT * FROM menus');
    $stmt->execute();
    $menus_rows = $stmt->fetchAll();

    $catalog = [];
    $type_post_labels = [
        'menu' => 'Menu',
        'burger' => 'Burger',
        'pizza' => 'Pizza',
        'wrap' => 'Wrap / Tacos',
        'side' => 'Accompagnement',
        'dessert' => 'Dessert',
        'boisson' => 'Boisson',
    ];
    foreach ($articles_rows as $article_row) {
        $row = array_change_key_case($article_row, CASE_LOWER);
        $id = (int) ($row['code'] ?? $row['id'] ?? 0);
        if ($id <= 0) {
            continue;
        }
        $post = strtolower(trim((string) ($row['type_post'] ?? '')));
        $prix_raw = str_replace(',', '.', (string) ($row['prix'] ?? '0'));
        $cat_label = (isset($row['categorie']) && $row['categorie'] !== '')
            ? (string) $row['categorie']
            : ($type_post_labels[$post] ?? ucfirst($post));
        $catalog[$id] = [
            'name' => (string) ($row['nom'] ?? ''),
            'price' => (float) $prix_raw,
            'cat' => $cat_label,
            'post' => $post,
        ];
    }

    foreach ($menus_rows as $menu_row) {
        $m = array_change_key_case($menu_row, CASE_LOWER);
        $mid = (int) ($m['id'] ?? 0);
        if ($mid <= 0) {
            continue;
        }
        $catKey = -$mid;
        $nom_menu = (string) ($m['nom_menu'] ?? $m['nom'] ?? 'Menu');
        $prix_menu = str_replace(',', '.', (string) ($m['prix'] ?? '0'));
        $catalog[$catKey] = [
            'name' => $nom_menu,
            'price' => (float) $prix_menu,
            'cat' => 'Menu',
            'post' => 'menu',
        ];
    }

    // ── Lecture du panier session 
    $panier = $_SESSION['panier'] ?? [];

    $panier_count = 0;
    $panier_total = 0.0;
    $panier_items = [];

    foreach ($panier as $items) {
        foreach ($items as $id => $qty) {
            if ($qty <= 0 || !isset($catalog[$id])) continue;
            $item = $catalog[$id];
            $panier_count += $qty;
            $panier_total += $item['price'] * $qty;
            $panier_items[] = [
                'id'       => $id,
                'qty'      => $qty,
                'name'     => $item['name'],
                'price'    => $item['price'],
                'cat'      => $item['cat'],
                'subtotal' => $item['price'] * $qty,
            ];
        }
    }



    if(isset($_POST['payer'])) {
        if (isset($_POST['timing']) && $_POST['timing'] === 'later' && !empty($_POST['delivery_time'])) {
            $_SESSION['heure_livraison'] = $_POST['delivery_time'];
        } else {
            unset($_SESSION['heure_livraison']);
        }

        // Conservé en session : après CYBank le navigateur revient en GET, le formulaire n’est plus là
        $liv = [
            'adresse'     => isset($_POST['adresse']) ? trim((string) $_POST['adresse']) : '',
            'code_postal' => isset($_POST['code_postal']) ? trim((string) $_POST['code_postal']) : '',
            'ville'       => isset($_POST['ville']) ? trim((string) $_POST['ville']) : '',
            'pays'        => isset($_POST['pays']) ? trim((string) $_POST['pays']) : '',
            'telephone'   => isset($_POST['telephone']) ? trim((string) $_POST['telephone']) : '',
            'email'       => isset($_POST['email']) ? trim((string) $_POST['email']) : '',
            'nom'         => isset($_POST['nom']) ? trim((string) $_POST['nom']) : '',
            'prenom'      => isset($_POST['prenom']) ? trim((string) $_POST['prenom']) : '',
        ];
        $liv['adresse'] = mb_substr($liv['adresse'], 0, 255);
        $liv['code_postal'] = mb_substr($liv['code_postal'], 0, 10);
        $liv['ville'] = mb_substr($liv['ville'], 0, 100);
        $liv['pays'] = mb_substr($liv['pays'], 0, 100);
        $liv['telephone'] = mb_substr($liv['telephone'], 0, 20);
        $liv['nom'] = mb_substr($liv['nom'], 0, 100);
        $liv['prenom'] = mb_substr($liv['prenom'], 0, 100);
        $liv['email'] = mb_substr($liv['email'], 0, 255);
        $_SESSION['checkout_livraison'] = $liv;

        if(isset($_POST['paiement']) && isset($_POST['cb_paiement'])) {
            $paiement = $_POST['paiement'];
            $cb_paiement = $_POST['cb_paiement'];

            switch($paiement)
            {
                case "1":
                    PaimentCYBANK($panier_total	);
                    break;
                case "2":
                    $message = "Paiement par PayPal";
                    break;
                case "3":
                    $message = "Paiement Google Pay";
                    break;
                default: 
                    $message = "Erreur inconnue";
                    break;
            }
        }
    }

    if ($statut === "Payé" && $cybank_transaction_id !== null)
    {
        if (!isset($_SESSION['cybank_transactions_traitees'])) {
            $_SESSION['cybank_transactions_traitees'] = [];
        }

        if (isset($_SESSION['cybank_transactions_traitees'][$cybank_transaction_id])) {
            $message = "Paiement effectué avec succès. Cette transaction était déjà enregistrée.";
        } elseif (empty($panier_items)) {
            $message = "Paiement reçu, mais le panier est vide : aucune commande enregistrée.";
        } else {
            $db_user_id = null;
            $db_user_email = null;
            if ($est_connecte && !empty($_SESSION['nom_utilisateur'])) {
                $stmtUser = $pdo_etudiant->prepare("SELECT id, email FROM users WHERE username = ? LIMIT 1");
                $stmtUser->execute([$_SESSION['nom_utilisateur']]);
                $row = $stmtUser->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    $db_user_id = (int) $row['id'];
                    if (!empty($row['email'])) {
                        $db_user_email = (string) $row['email'];
                    }
                }
            }

            if ($db_user_id === null) {
                $message = "Erreur : impossible d'associer la commande à un compte utilisateur.";
            } else {
                $date_commande = date('Y-m-d H:i:s');
                $heure_livraison = $_SESSION['heure_livraison'] ?? null;
                $liv = $_SESSION['checkout_livraison'] ?? [];
                $email_commande = !empty($liv['email']) ? $liv['email'] : $db_user_email;
                $strOrNull = static function ($v) {
                    if ($v === null || $v === '') {
                        return null;
                    }
                    return is_string($v) ? $v : (string) $v;
                };
                try {
                    $pdo_commandes->beginTransaction();

                    $stmt = $pdo_commandes->prepare(
                        "INSERT INTO commandes (user_id, email, date_commande, total, nb_articles, statut, heure_livraison, adresse, code_postal, ville, pays, telephone, nom, prenom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    $stmt->execute([
                        $db_user_id,
                        $email_commande,
                        $date_commande,
                        $panier_total,
                        $panier_count,
                        $statut,
                        $heure_livraison,
                        $strOrNull($liv['adresse'] ?? null),
                        $strOrNull($liv['code_postal'] ?? null),
                        $strOrNull($liv['ville'] ?? null),
                        $strOrNull($liv['pays'] ?? null),
                        $strOrNull($liv['telephone'] ?? null),
                        $strOrNull($liv['nom'] ?? null),
                        $strOrNull($liv['prenom'] ?? null),
                    ]);

                    $commande_id = (int) $pdo_commandes->lastInsertId();
                    if ($commande_id <= 0) {
                        throw new PDOException("Identifiant de commande invalide après insertion.");
                    }

                    $stmtItem = $pdo_commandes->prepare(
                        "INSERT INTO commande_items (commande_id, produit_id, nom, prix, quantite) VALUES (?, ?, ?, ?, ?)"
                    );
                    foreach ($panier_items as $item) {
                        $stmtItem->execute([
                            $commande_id,
                            $item['id'],
                            $item['name'],
                            $item['price'],
                            $item['qty'],
                        ]);
                    }

                    $pdo_commandes->commit();

                    $_SESSION['cybank_transactions_traitees'][$cybank_transaction_id] = true;
                    $_SESSION['panier'] = [
                        'menus'    => [],
                        'burgers'  => [],
                        'pizzas'   => [],
                        'wraps'    => [],
                        'sides'    => [],
                        'desserts' => [],
                        'boissons' => [],
                    ];
                    unset($_SESSION['heure_livraison'], $_SESSION['checkout_livraison']);
                } catch (PDOException $e) {
                    if ($pdo_commandes->inTransaction()) {
                        $pdo_commandes->rollBack();
                    }
                    $message = "Erreur lors de l'ajout de la commande : " . $e->getMessage();
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
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="paiement.css">
    <link rel="stylesheet" href="../accueil.css">
    <title>Panier - CY Restaurant</title>
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
<body class="checkout-page">
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

    <div class="checkout-shell">
        <div class="checkout-frame">
            <div class="checkout-frame__corners" aria-hidden="true"></div>
            <div class="checkout-frame__inner">
                <header class="checkout-frame__head">
                    <div class="checkout-frame__brand">CY Restaurant · Paiement</div>
                    <h1><?php echo $bool_paiement_traite ? 'R&eacute;sultat' : 'Finaliser la commande'; ?></h1>
                    <p><?php echo $bool_paiement_traite
                        ? 'Votre transaction a &eacute;t&eacute; trait&eacute;e. Retrouvez le d&eacute;tail ci-dessous.'
                        : 'V&eacute;rifiez votre panier, choisissez l&rsquo;horaire et le mode de r&egrave;glement.'; ?></p>
                </header>

                <div class="checkout-frame__body">
        <?php
        $show_error_alert = !empty($message) && !$bool_paiement_traite
            && (strpos((string) $message, 'succès') === false && stripos((string) $message, 'déjà enregistrée') === false);
        $show_success_alert = !empty($message) && !$bool_paiement_traite
            && (strpos((string) $message, 'succès') !== false || stripos((string) $message, 'déjà enregistrée') !== false);
        ?>
        <?php if ($show_error_alert): ?>
            <div class="checkout-alert checkout-alert--error" role="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php elseif ($show_success_alert): ?>
            <div class="checkout-alert checkout-alert--success" role="status"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (!$bool_paiement_traite): ?>
        <main>
            <div class="checkout-block">
                <div class="checkout-block__title">
                    <span>R&eacute;capitulatif</span>
                    <?php if (!empty($panier_items)): ?>
                        <span class="checkout-badge"><?php echo (int) $panier_count; ?> article<?php echo $panier_count > 1 ? 's' : ''; ?></span>
                    <?php endif; ?>
                </div>
                <div class="checkout-block__body">
                    <?php if (empty($panier_items)): ?>
                        <div class="checkout-empty">
                            <p>Votre panier est vide.</p>
                            <p><a href="/ProjetCYJ/CYJ/Carte/">Retour &agrave; la carte</a></p>
                        </div>
                    <?php else: ?>
                        <ul class="checkout-lines">
                            <?php foreach ($panier_items as $item): ?>
                                <li class="checkout-line">
                                    <div class="checkout-line__name">
                                        <?php echo htmlspecialchars($item['name']); ?>
                                        <div class="checkout-line__meta">Quantit&eacute; &times;<?php echo (int) $item['qty']; ?></div>
                                    </div>
                                    <div class="checkout-line__price"><?php echo number_format($item['subtotal'], 2, ',', ' '); ?> &euro;</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="checkout-total">
                            <span>Total</span>
                            <span><?php echo number_format($panier_total, 2, ',', ' '); ?> &euro;</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

            <form class="checkout-form" method="POST" action="index.php">
                <div class="checkout-section">
                    <div class="checkout-section__title">Horaire de livraison</div>
                    <div class="checkout-panel">
                        <label class="checkout-option">
                            <input type="radio" name="timing" value="immediate" id="timing_immediate" checked onclick="toggleTime(false)">
                            <span class="checkout-option__text">
                                <strong>Dès que possible</strong>
                                <small>Livraison dès que votre commande est prête</small>
                            </span>
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="timing" value="later" id="timing_later" onclick="toggleTime(true)">
                            <span class="checkout-option__text">
                                <strong>Programmer</strong>
                                <small>Choisissez une heure entre 11h et 23h</small>
                            </span>
                        </label>
                        <div id="time_picker" class="checkout-time-block" aria-hidden="true">
                            <label for="delivery_time">Heure souhaitée</label>
                            <input type="time" name="delivery_time" id="delivery_time" min="11:00" max="23:00">
                        </div>
                    </div>
                </div>
                <div class="checkout-section">
                    <div class="checkout-section__title">Livraison</div>
                    <div class="checkout-fields">
                        <div class="checkout-field checkout-field--full">
                            <label for="adresse">Adresse</label>
                            <input type="text" name="adresse" id="adresse" placeholder="Rue et num&eacute;ro" autocomplete="street-address">
                        </div>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="code_postal">Code postal</label>
                                <input type="text" name="code_postal" id="code_postal" placeholder="Ex. 95000" inputmode="numeric" autocomplete="postal-code">
                            </div>
                            <div class="checkout-field checkout-field--grow">
                                <label for="ville">Ville</label>
                                <input type="text" name="ville" id="ville" placeholder="Ville" autocomplete="address-level2">
                            </div>
                        </div>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="telephone">T&eacute;l&eacute;phone</label>
                                <input type="tel" name="telephone" id="telephone" placeholder="06 12 34 56 78" autocomplete="tel">
                            </div>
                        </div>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="nom">Nom</label>
                                <input type="text" name="nom" id="nom" placeholder="Nom" autocomplete="family-name">
                            </div>
                            <div class="checkout-field checkout-field--grow">
                                <label for="prenom">Pr&eacute;nom</label>
                                <input type="text" name="prenom" id="prenom" placeholder="Pr&eacute;nom" autocomplete="given-name">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="checkout-section">
                    <div class="checkout-section__title">Mode de paiement</div>
                    <div class="checkout-panel">
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="1" id="paiement_cb" required>
                            <span class="checkout-option__text">
                                <strong>Carte bancaire</strong>
                                <small>Paiement sécurisé via CYBank</small>
                            </span>
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="2" id="paiement_paypal">
                            <span class="checkout-option__text">
                                <strong>PayPal</strong>
                                <small>Règlement avec votre compte PayPal</small>
                            </span>
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="3" id="paiement_gpay">
                            <span class="checkout-option__text">
                                <strong>Google Pay</strong>
                                <small>Paiement rapide sur appareil compatible</small>
                            </span>
                        </label>
                    </div>
                </div>

                <label class="checkout-cgv" for="cb_paiement">
                    <input type="checkbox" name="cb_paiement" id="cb_paiement" value="1" required>
                    <span>J&rsquo;accepte les conditions g&eacute;n&eacute;rales de vente</span>
                </label>

                <button type="submit" name="payer" value="1" class="btn-checkout" <?php echo empty($panier_items) ? 'disabled' : ''; ?>>
                    Payer <?php echo empty($panier_items) ? '' : number_format($panier_total, 2, ',', ' ') . ' &euro;'; ?>
                </button>
            </form>
        <?php else: ?>
            <div class="checkout-success-block">
                <?php
                $is_ok = isset($message) && (stripos((string) $message, 'Erreur') === false && stripos((string) $message, 'impossible') === false && stripos((string) $message, 'vide') === false);
                ?>
                <div class="checkout-alert <?php echo $is_ok ? 'checkout-alert--success' : 'checkout-alert--error'; ?>" role="<?php echo $is_ok ? 'status' : 'alert'; ?>">
                    <?php echo htmlspecialchars($message ?? ''); ?>
                </div>
                <a class="checkout-back" href="/ProjetCYJ/CYJ/Carte/">Retour &agrave; la carte</a>
            </div>
        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        function toggleTime(show) {
            var picker = document.getElementById('time_picker');
            var input = document.getElementById('delivery_time');
            if (!picker || !input) return;
            picker.classList.toggle('is-visible', show);
            picker.setAttribute('aria-hidden', show ? 'false' : 'true');
            input.required = !!show;
        }
    </script>
    <script src="../js/menu-toggle.js"></script>

</body>
</html>
