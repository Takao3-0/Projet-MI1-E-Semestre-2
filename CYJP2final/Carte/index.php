<?php 

    require_once '../../../protection.php';

    require_once '../../../../db_config_yumland.php';
    //On recupere les itemps depuis la database (table articles)

    $stmt = $pdo->prepare("SELECT * FROM articles");
    $stmt->execute();
    $articles = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM menus");
    $stmt->execute();
    $menus = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM composition_menu");
    $stmt->execute();
    $composition_menu = $stmt->fetchAll();


    // Catalogue indexé par code produit (même clés que l’ancien JSON, données = table articles) 
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
    foreach ($articles as $article_row) {
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

    /* Menus (table menus) : clés négatives dans le catalogue pour ne jamais entrer en conflit avec les codes articles (> 0). */
    foreach ($menus as $menu_row) {
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

    // Initialisation session 
    if (!isset($_SESSION['panier'])) {
        $_SESSION['panier'] = [
            'menus'    => [],
            'burgers'  => [],
            'pizzas'   => [],
            'wraps'    => [],
            'sides'    => [],
            'desserts' => [],
            'boissons' => [],
        ];
    }

    $base_url = strtok($_SERVER['REQUEST_URI'], '?') . '?cart=open';

    // Ajout depuis les cartes menu
    $add_keys = ['menu', 'burger', 'pizza', 'wrap', 'side', 'dessert', 'boisson'];
    foreach ($add_keys as $cat) {
        if (isset($_POST['add_' . $cat])) {
            if ($cat === 'menu') {
                $id = (int) $_POST['add_menu'];
                if ($id < 0 && isset($catalog[$id])) {
                    $key = 'menus';
                    $_SESSION['panier'][$key][$id] = ($_SESSION['panier'][$key][$id] ?? 0) + 1;
                }
                header('Location: ' . $base_url . '#menus');
                exit;
            }
            $id = (int) $_POST['add_' . $cat];
            if ($id > 0) {
                $key = $cat . 's';
                $_SESSION['panier'][$key][$id] = ($_SESSION['panier'][$key][$id] ?? 0) + 1;
            }
            header('Location: ' . $base_url . '#' . $cat . 's');
            exit;
        }
    }

    // Actions depuis le panneau panier (redirect avec ?cart=open)

    // Ajouter un article depuis le panier
    foreach ($add_keys as $cat) {
        if (isset($_POST['cart_add_' . $cat])) {
            $id = (int) $_POST['cart_add_' . $cat];
            if ($cat === 'menu') {
                if ($id < 0 && isset($catalog[$id])) {
                    $_SESSION['panier']['menus'][$id] = ($_SESSION['panier']['menus'][$id] ?? 0) + 1;
                }
            } elseif ($id > 0) {
                $key = $cat . 's';
                $_SESSION['panier'][$key][$id] = ($_SESSION['panier'][$key][$id] ?? 0) + 1;
            }
            header('Location: ' . $base_url);
            exit;
        }
    }

    // Retirer une unité
    if (isset($_POST['cart_remove'])) {
        $id = (int) $_POST['cart_remove'];
        foreach ($_SESSION['panier'] as $key => &$items) {
            if (isset($items[$id])) {
                $items[$id]--;
                if ($items[$id] <= 0) unset($items[$id]);
                break;
            }
        }
        unset($items);
        header('Location: ' . $base_url);
        exit;
    }

    // Supprimer un article entièrement
    if (isset($_POST['cart_delete'])) {
        $id = (int) $_POST['cart_delete'];
        foreach ($_SESSION['panier'] as $key => &$items) {
            if (isset($items[$id])) { unset($items[$id]); break; }
        }
        unset($items);
        header('Location: ' . $base_url);
        exit;
    }

    // Vider le panier
    if (isset($_POST['cart_clear'])) {
        foreach ($_SESSION['panier'] as $key => $_) {
            $_SESSION['panier'][$key] = [];
        }
        header('Location: ' . $base_url);
        exit;
    }



    // Calcul du total et liste à afficher 
    $panier_count = 0;
    $panier_total = 0.0;
    $panier_items = [];

    foreach ($_SESSION['panier'] as $items) {
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
                'post'     => $item['post'],
                'subtotal' => $item['price'] * $qty,
            ];
        }
    }
?>

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

        .mobiletext {
            display: none;
        }

        .pctext {
            display: block;
        }

        @media (max-width: 900px) {
            .pcnavbar {
                display: none;
            }

            .mobilenavbar {
                display: block;
            }

            .mobiletext {
                display: block;
            }

            .pctext {
                display: none;
            }
        }


        .cart-icon-wrap {
            position: relative;
            display: inline-flex;
            align-items: center;
            background: none;
            border: none;
            outline: none;
            padding: 0;
            cursor: pointer;
            color: inherit;
        }

        /* Zone de tap suffisante sur mobile (≥ 44px recommandé) */
        button.cart-icon-wrap {
            min-width: 44px;
            min-height: 44px;
            justify-content: center;
        }

        .cart-badge {
            position: absolute;
            top: -7px;
            right: -7px;
            background: var(--orange);
            color: #fff;
            border-radius: 50%;
            min-width: 16px;
            height: 16px;
            font-size: 10px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            padding: 0 2px;
            box-shadow: 0 1px 4px rgba(0,0,0,0.25);
            pointer-events: none;
        }

        /* ── Panier sidebar ── */
        #cartContainer {
            position: fixed;
            inset: 0;
            z-index: 300;
            pointer-events: none;
        }

        #cartContainer.cart-open {
            pointer-events: all;
        }

        #cartContainer::before {
            content: '';
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0);
            transition: background 0.3s ease;
        }

        #cartContainer.cart-open::before {
            background: rgba(0, 0, 0, 0.45);
        }

        .cart-panel {
            position: absolute;
            top: 0;
            right: 0;
            width: 380px;
            max-width: 100vw;
            height: 100%;
            background: #fff;
            box-shadow: -6px 0 32px rgba(0, 0, 0, 0.15);
            transform: translateX(100%);
            transition: transform 0.3s ease;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
        }

        #cartContainer.cart-open .cart-panel {
            transform: translateX(0);
        }

        .cart-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.2rem 1.5rem;
            border-bottom: 1px solid #eee;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 1;
        }

        .cart-panel-header h2 {
            font-size: 1.1rem;
            font-weight: 800;
            color: var(--dark);
            margin: 0;
        }

        .cart-close-btn {
            background: none;
            border: none;
            outline: none;
            font-size: 1.3rem;
            cursor: pointer;
            color: var(--grey-text);
            padding: 0.2rem 0.4rem;
            line-height: 1;
            border-radius: 50%;
            transition: background var(--transition);
        }

        .cart-close-btn:hover { background: #f0f0f0; }

        .cart-panel-body {
            flex: 1;
            padding: 1rem 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.8rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.8rem;
            padding: 0.75rem;
            background: var(--grey-bg);
            border-radius: var(--radius-sm);
        }

        .cart-item-info {
            flex: 1;
            min-width: 0;
        }

        .cart-item-cat {
            display: block;
            font-size: 0.65rem;
            text-transform: uppercase;
            color: var(--orange);
            font-weight: 700;
            letter-spacing: 0.05em;
        }

        .cart-item-name {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .cart-item-subtotal {
            display: block;
            font-size: 0.8rem;
            color: var(--grey-text);
            margin-top: 2px;
        }

        .cart-item-controls {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .cart-item-controls form { margin: 0; }

        .cart-qty-btn {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            border: 1.5px solid #ddd;
            background: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            line-height: 1;
            transition: background var(--transition), border-color var(--transition);
        }

        .cart-qty-btn:hover { background: var(--orange); border-color: var(--orange); color: #fff; }

        .cart-qty-btn.delete { border-color: #ffcdd2; color: #e53935; }
        .cart-qty-btn.delete:hover { background: #e53935; border-color: #e53935; color: #fff; }

        .cart-qty-num {
            min-width: 22px;
            text-align: center;
            font-size: 0.9rem;
            font-weight: 700;
        }

        .cart-empty {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.8rem;
            color: var(--grey-text);
            padding: 2rem;
        }

        .cart-empty-icon { font-size: 3rem; opacity: 0.4; }

        .cart-panel-footer {
            padding: 1.2rem 1.5rem;
            border-top: 1px solid #eee;
            position: sticky;
            bottom: 0;
            background: #fff;
        }

        .cart-total-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            margin-bottom: 1rem;
        }

        .cart-total-label { font-size: 0.9rem; color: var(--grey-text); }

        .cart-total-amount {
            font-size: 1.3rem;
            font-weight: 900;
            color: var(--orange);
        }

        .cart-footer-actions {
            display: flex;
            gap: 0.6rem;
        }

        .btn-commander {
            flex: 1;
            padding: 0.75rem;
            background: var(--orange);
            color: #fff;
            border: none;
            border-radius: 50px;
            font-size: 0.95rem;
            font-weight: 800;
            cursor: pointer;
            transition: background var(--transition);
        }

        .btn-commander:hover { background: var(--orange-dk); }

        .btn-vider {
            padding: 0.75rem 1rem;
            background: none;
            color: var(--grey-text);
            border: 1.5px solid #ddd;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition);
        }

        .btn-vider:hover { border-color: #e53935; color: #e53935; }

        /* Sous-navigation carte : même ADN que la navbar (accueil.css : --dark / --orange) */
        .restaurant-content .sticky-nav {
            display: block;
            overflow: visible;
            top: 64px;
            z-index: 95;
            background: var(--dark);
            border: none;
            border-bottom: 3px solid var(--orange);
            box-shadow: 0 6px 18px rgba(0, 0, 0, 0.22);
            padding: 0.45rem 1rem;
            margin: 0;
        }

        .restaurant-content .sticky-nav-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            flex-wrap: nowrap;
            gap: 0.35rem;
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
            -ms-overflow-style: none;
        }

        .restaurant-content .sticky-nav-inner::-webkit-scrollbar {
            display: none;
        }

        .restaurant-content .sticky-nav a.sticky-nav-link {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            white-space: nowrap;
            padding: 0.42rem 0.95rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.78);
            border: 1px solid transparent;
            transition: color var(--transition), background var(--transition), border-color var(--transition);
        }

        .restaurant-content .sticky-nav a.sticky-nav-link:hover {
            color: var(--orange);
            background: rgba(255, 125, 41, 0.14);
        }

        .restaurant-content .sticky-nav a.sticky-nav-link:focus-visible {
            outline: 2px solid var(--orange);
            outline-offset: 2px;
        }

        .restaurant-content .sticky-nav a.sticky-nav-link.active {
            color: var(--dark);
            background: var(--orange);
            font-weight: 800;
            border-color: var(--orange);
        }

        /* Ancres : section visible sous navbar + sous-nav */
        .restaurant-content .section-header[id] {
            scroll-margin-top: calc(64px + 52px);
        }

        @media (max-width: 900px) {
            .restaurant-content .section-header[id] {
                scroll-margin-top: calc(64px + 48px);
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
            <li><a href="/ProjetCYJ/CYJ/">Restaurant</a></li>
            <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "livreur")): ?>
                <li><a href="/ProjetCYJ/CYJ/Livraison/">Livraison</a></li>
            <?php endif; ?>
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
                <span class="cart-icon-wrap">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 
                                2-2-.9-2-2-2zm10 
                                0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 
                                2-2-.9-2-2-2zM7.16 14h9.45c.75 
                                0 1.41-.41 1.75-1.03l3.58-6.49A1 
                                1 0 0 0 21.08 5H5.21l-.94-2H1v2h2l3.6 
                                7.59-1.35 2.44C4.52 15.37 5.48 
                                17 7 17h12v-2H7l1.16-2z"/>
                    </svg>
                    <?php if ($panier_count > 0): ?>
                        <span class="cart-badge"><?= $panier_count ?></span>
                    <?php endif; ?>
                </span>
            <?php else: ?>
                <a href="/ProjetCYJ/CYJ/LOG/login" class="btn-nav">Connexion</a>
                <a href="/ProjetCYJ/CYJ/LOG/signup" class="btn-nav btn-nav-primary">S'inscrire</a>
                <button type="button" class="cart-icon-wrap" aria-label="Panier" aria-expanded="false">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 
                                    2-2-.9-2-2-2zm10 
                                    0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 
                                    2-2-.9-2-2-2zM7.16 14h9.45c.75 
                                    0 1.41-.41 1.75-1.03l3.58-6.49A1 
                                    1 0 0 0 21.08 5H5.21l-.94-2H1v2h2l3.6 
                                    7.59-1.35 2.44C4.52 15.37 5.48 
                                    17 7 17h12v-2H7l1.16-2z"/>
                        </svg>
                        <?php if ($panier_count > 0): ?>
                            <span class="cart-badge"><?= $panier_count ?></span>
                        <?php endif; ?>
                </button>
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
                <button type="button" class="cart-icon-wrap" aria-label="Panier" aria-expanded="false">
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M7 18c-1.1 0-1.99.9-1.99 2S5.9 22 7 22s2-.9 
                                2-2-.9-2-2-2zm10 
                                0c-1.1 0-1.99.9-1.99 2S15.9 22 17 22s2-.9 
                                2-2-.9-2-2-2zM7.16 14h9.45c.75 
                                0 1.41-.41 1.75-1.03l3.58-6.49A1 
                                1 0 0 0 21.08 5H5.21l-.94-2H1v2h2l3.6 
                                7.59-1.35 2.44C4.52 15.37 5.48 
                                17 7 17h12v-2H7l1.16-2z"/>
                    </svg>
                    <?php if ($panier_count > 0): ?>
                        <span class="cart-badge"><?= $panier_count ?></span>
                    <?php endif; ?>
                </button>
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

    <!-- Les items dispo sur le restaurant -->
    <main class="restaurant-content">

        <nav class="sticky-nav" aria-label="Navigation du menu">
            <div class="sticky-nav-inner">
                <a href="#menu" class="sticky-nav-link active">Vos menus préférés</a>
                <a href="#populaires" class="sticky-nav-link">Populaires</a>
                <a href="#burgers" class="sticky-nav-link">Burgers</a>
                <a href="#pizzas" class="sticky-nav-link">Pizzas</a>
                <a href="#wraps" class="sticky-nav-link">Wraps &amp; Tacos</a>
                <a href="#sides" class="sticky-nav-link">Accompagnements</a>
                <a href="#desserts" class="sticky-nav-link">Desserts</a>
                <a href="#boissons" class="sticky-nav-link">Boissons</a>
            </div>
        </nav>

        <!-- Pour le moment les populaires sont géré manuellement mais à terme sera géré via la database (articles les plus vendus) -->
        <section class="menu-section">
            <div class="section-header" id="menu">
                <h2>Vos menus préférés</h2>
                <p>Découvrez nos menus personnalisés, conçus pour répondre à vos envies et besoins.</p>
            </div>
            <div class="menu-grid">
                <?php foreach ($menus as $menu): ?>
                    <div class="menu-card">
                        <div class="menu-card-img" style="background: linear-gradient(135deg, #ffe0b2, #fff59d, #bbdefb); padding: 20px; border-radius: 15px;">
                            <div class="menu-emoji-trio">
                                <span class="menu-emoji">&#127828;</span>
                                <span class="menu-emoji">&#127839;</span>
                                <span class="menu-emoji">&#129380;</span>
                            </div>
                        </div>
                        <div class="menu-card-body">
                            <span class="menu-cat">Menu</span>
                            <h3><?php echo $menu['nom_menu']; ?></h3>
                            <p><?php echo $menu['description']; ?></p>
                            <div class="menu-card-footer">
                                <span class="menu-price"><?php echo $menu['prix']; ?> &euro;</span>
                                <form method="POST" action="index.php">
                                    <?php
                                    $menu_row = array_change_key_case($menu, CASE_LOWER);
                                    $menu_id_cart = (int) ($menu_row['id'] ?? 0);
                                    $menu_id_cart = $menu_id_cart > 0 ? -$menu_id_cart : 0;
                                    ?>
                                    <button type="submit" name="add_menu" value="<?php echo $menu_id_cart; ?>" class="menu-add">+</button>
                                </form>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="section-header" id="populaires">
                <h2>Nos Populaires</h2>
                <p>Les plats pr&eacute;f&eacute;r&eacute;s de nos clients, disponibles tous les jours.</p>
            </div>

            <div class="menu-grid">

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fff3e0,#ffe0b2)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>CY Smash Burger</h3>
                        <p>Double steak, cheddar, sauce maison</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">8.90 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_burger" value="1001" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fce4ec,#f8bbd0)">
                        <span class="menu-emoji">&#127829;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Pizza</span>
                        <h3>Pizza 4 Fromages</h3>
                        <p>Mozzarella, gorgonzola, ch&egrave;vre, parmesan</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">11.00 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_pizza" value="1102" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#e8f5e9,#c8e6c9)">
                        <span class="menu-emoji">&#127790;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Wrap</span>
                        <h3>Wrap Poulet Avocat</h3>
                        <p>Poulet grill&eacute;, avocat, sauce ranch</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">7.50 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_wrap" value="1201" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fff3e0,#ffccbc)">
                        <span class="menu-emoji">&#127828;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Burger</span>
                        <h3>Bacon King</h3>
                        <p>Triple steak, bacon grill&eacute;, cheddar, BBQ</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">11.90 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_burger" value="1005" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fff9c4,#fff176)">
                        <span class="menu-emoji">&#127839;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Accompagnements</span>
                        <h3>Nuggets x8</h3>
                        <p>Croustillants avec sauce au choix</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">5.90 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_side" value="1302" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#e8f5e9,#a5d6a7)">
                        <span class="menu-emoji">&#127790;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Tacos</span>
                        <h3>Tacos XL</h3>
                        <p>Double viande, double fromage, frites</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">10.90 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_wrap" value="1203" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fce4ec,#f48fb1)">
                        <span class="menu-emoji">&#127846;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Dessert</span>
                        <h3>Cookie G&eacute;ant</h3>
                        <p>P&eacute;pites de chocolat, tout chaud</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">2.90 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_dessert" value="1401" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#ffebee,#ef9a9a)">
                        <span class="menu-emoji">&#127829;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Pizza</span>
                        <h3>Pizza Pepperoni</h3>
                        <p>Sauce tomate, mozzarella, pepperoni</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">10.50 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_pizza" value="1103" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#e3f2fd,#90caf9)">
                        <span class="menu-emoji">&#129380;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Boisson</span>
                        <h3>Jus d'Orange Frais</h3>
                        <p>Press&eacute; sur place, 100% pur jus</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_boisson" value="1503" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>

                <div class="menu-card">
                    <div class="menu-card-img" style="background: linear-gradient(135deg,#fff9c4,#ffee58)">
                        <span class="menu-emoji">&#127839;</span>
                    </div>
                    <div class="menu-card-body">
                        <span class="menu-cat">Accompagnements</span>
                        <h3>Frites Maison</h3>
                        <p>Fra&icirc;ches, sel de Gu&eacute;rande</p>
                        <div class="menu-card-footer">
                            <span class="menu-price">3.50 &euro;</span>
                            <form method="POST">
                                <button type="submit" name="add_side" value="1301" class="menu-add">+</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div><br>
            <!-- Burgers -->
            <div class="section-header" id="burgers">
                <h2>Nos Burgers</h2>
                <p>Steaks smashés, recettes généreuses, sauces maison : du classique au plus gourmand.</p>
            </div>
            <div class="menu-grid">

                <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'burger'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#FFE0B2,#FFB74D)">
                                <span class="menu-emoji">&#127828;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Burger</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_burger" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
            </div><br>
            <!-- Pizzas -->
            <div class="section-header" id="pizzas">
                <h2>Nos Pizzas</h2>
                <p>Pâte croustillante, mozzarella fondante et toppings bien chargés : simples et efficaces.</p>
            </div>
            <div class="menu-grid">
                <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'pizza'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#FCE4EC,#F48FB1)">
                                <span class="menu-emoji">&#127829;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Pizza</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_pizza" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div><br>
            <!-- Wraps & Tacos -->
            <div class="section-header" id="wraps">
                <h2>Nos Wraps &amp; Tacos</h2>
                <p>Format pratique, ultra gourmand : poulet, frites, fromage et sauces au choix.</p>
            </div>
            <div class="menu-grid">
                <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'wrap'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#E8F5E9,#A5D6A7)">
                                <span class="menu-emoji">&#127790;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Wrap</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_wrap" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div><br>
            <!-- Accompagnements -->
            <div class="section-header" id="sides">

            <!-- Pour afficher soit l'un soit l'autre en fonction du terminal utilisateur. Effectivement sur téléphone le mot "accompagnements" est trop long et on préfère "sides"-->
                <h2 class="pctext">Nos accompagnements</h2>
                <h2 class="mobiletext">Nos sides</h2>

                <p>À partager (ou pas) : frites maison, onion rings, nuggets… le bonus qui fait plaisir.</p>
            </div>
            <div class="menu-grid">
                <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'side'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#FFF3E0,#FFCC80)">
                                <span class="menu-emoji">&#127839;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Accompagnements</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_side" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div><br>
            <!-- Desserts -->
            <div class="section-header" id="desserts">
                <h2>Nos Desserts</h2>
                <p>La touche sucrée : cookie géant, brownie fondant, glaces au choix.</p>
            </div>
            <div class="menu-grid">
            <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'dessert'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#FCE4EC,#F48FB1)">
                                <span class="menu-emoji">&#127846;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Dessert</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_dessert" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div><br>
            <!-- Boissons -->
            <div class="section-header" id="boissons">
                <h2>Nos Boissons</h2>
                <p>Pour accompagner : sodas bien frais, eau plate/pétillante, jus pressé.</p>
            </div>
            <div class="menu-grid">
            <?php foreach ($articles as $article): ?>
                    <?php if ($article['type_post'] == 'boisson'): ?>
                        <div class="menu-card">
                            <div class="menu-card-img" style="background: linear-gradient(135deg,#E3F2FD,#90CAF9)">
                                <span class="menu-emoji">&#129380;</span>
                            </div>
                            <div class="menu-card-body">
                                <span class="menu-cat">Boisson</span>
                                <h3><?php echo $article['nom']; ?></h3>
                                <p><?php echo $article['description']; ?></p>
                                <div class="menu-card-footer">
                                    <span class="menu-price"><?php echo $article['prix']; ?> &euro;</span>
                                    <form method="POST" action="index.php">
                                        <button type="submit" name="add_boisson" value="<?php echo $article['code']; ?>" class="menu-add">+</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div><br>
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
    <!-- Panier sidebar -->
    <div id="cartContainer">
        <div class="cart-panel">

            <div class="cart-panel-header">
                <h2>Mon Panier <?php if ($panier_count > 0): ?><span style="font-weight:400;color:var(--grey-text);font-size:0.85rem">(<?= $panier_count ?> article<?= $panier_count > 1 ? 's' : '' ?>)</span><?php endif; ?></h2>
                <button type="button" class="cart-close-btn" id="cartClose" title="Fermer">&#x2715;</button>
            </div>

            <?php if (!empty($panier_items)): ?>

                <div class="cart-panel-body">
                    <?php foreach ($panier_items as $item): ?>
                        <div class="cart-item">
                            <div class="cart-item-info">
                                <span class="cart-item-cat"><?= htmlspecialchars($item['cat']) ?></span>
                                <span class="cart-item-name"><?= htmlspecialchars($item['name']) ?></span>
                                <span class="cart-item-subtotal"><?= number_format($item['price'], 2, ',', '') ?> € × <?= $item['qty'] ?> = <?= number_format($item['subtotal'], 2, ',', '') ?> €</span>
                            </div>
                            <div class="cart-item-controls">
                                <form method="POST">
                                    <button type="submit" name="cart_remove" value="<?= $item['id'] ?>" class="cart-qty-btn" title="Retirer un">&#8722;</button>
                                </form>
                                <span class="cart-qty-num"><?= $item['qty'] ?></span>
                                <form method="POST">
                                    <button type="submit" name="cart_add_<?= $item['post'] ?>" value="<?= $item['id'] ?>" class="cart-qty-btn" title="Ajouter un">+</button>
                                </form>
                                <form method="POST">
                                    <button type="submit" name="cart_delete" value="<?= $item['id'] ?>" class="cart-qty-btn delete" title="Supprimer">&#x2715;</button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="cart-panel-footer">
                    <div class="cart-total-row">
                        <span class="cart-total-label">Total</span>
                        <span class="cart-total-amount"><?= number_format($panier_total, 2, ',', '') ?> &euro;</span>
                    </div>
                    <div class="cart-footer-actions">
                        <form method="POST">
                            <button type="submit" name="cart_clear" value="1" class="btn-vider">Vider</button>
                        </form>
                        <!-- <button type="button" class="btn-commander">Commander</button> -->
                        <a href="/ProjetCYJ/CYJ/CYBank/" class="btn-commander" style="text-decoration: none; text-align: center;">Commander</a>
                    </div>
                </div>

            <?php else: ?>

                <div class="cart-empty">
                    <span class="cart-empty-icon">&#128722;</span>
                    <p>Votre panier est vide</p>
                    <p style="font-size:0.8rem">Ajoutez des articles depuis la carte</p>
                </div>

            <?php endif; ?>

        </div>
    </div>

    <script src="../js/menu-toggle.js"></script>
</body>

</html>