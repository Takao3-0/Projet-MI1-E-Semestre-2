<?php
    require_once '../../../protection.php';
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    // Vérification de la connexion et du rôle
    if ($est_connecte && ($role_actuel == 'livreur' || $role_actuel == 'admin' || $role_actuel == 'fakeadmin')) {

        // Confirmation d'une livraison
        if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_livraison'])) {
    
            $cid = (int) $_POST['confirm_livraison'];
    
            if ($cid > 0) {
                $stmt = $pdo_commandes->prepare(
                    "UPDATE commandes SET statut_production = 'Livré' WHERE id = ? AND statut = 'Payé' AND statut_production = 'Récupérée livreur'"
                );
                $stmt->execute([$cid]);
            }
    
            header('Location: index.php');
            exit;
        }
    
        // Récupération des commandes à livrer
        $stmt = $pdo_commandes->prepare(
            "SELECT * FROM commandes WHERE statut = 'Payé' AND statut_production = 'Récupérée livreur' ORDER BY date_commande ASC"
        );
        $stmt->execute();
        $livraisons = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    } else {
        header('Location: /ProjetCYJ/CYJ/index.php');
        exit;
    }
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="../accueil.css">
    <link rel="stylesheet" href="livraison.css">
    
    <title>Livraison</title>
    <style>
        /*Pour afficher soit l'un soit l'autre*/

        /*Pour la position sticky on là met ici et pas dans .navbar sinon bah ça s'applique pas vu que pcnavbar et mobilenavbar son parents de la classe nav */

        .mobilenavbar {  
        position: sticky;
        top: 0;
        z-index: 100;
        display: none;
        }

        @media (max-width: 900px) {

        .mobilenavbar {
            display: block;
        }
        }
    </style>
</head>
<body class="livraison-page">

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

    <main class="livraison-main">
    <header class="livraison-top">
        <a href="/ProjetCYJ/CYJ/" class="livraison-back">&larr; Accueil</a>
        <h1>Livraison</h1>
        <p class="livraison-sub">Commandes r&eacute;cup&eacute;r&eacute;es en cuisine, &agrave; livrer au client.</p>
    </header>

    <?php if (empty($livraisons)): ?>
        <section class="livraison-empty">
            <p>Aucune commande en cours de livraison pour le moment.</p>
            <p class="livraison-empty-hint">Les commandes apparaissent ici une fois marqu&eacute;es <strong>R&eacute;cup&eacute;r&eacute;e livreur</strong> depuis la cuisine.</p>
        </section>
    <?php else: ?>
        <div class="livraison-list">
        <?php foreach ($livraisons as $cmd): ?>
            <?php
                if (isset($cmd['id'])) {
                    $cid = (int) $cmd['id'];
                } else {
                    $cid = 0;
                }

                if (isset($cmd['email'])) {
                    $email = htmlspecialchars($cmd['email']);
                } else {
                    $email = '';
                }

                if (isset($cmd['total'])) {
                    $total = htmlspecialchars($cmd['total']);
                } else {
                    $total = '';
                }

                if (isset($cmd['heure_livraison'])) {
                    $heure = htmlspecialchars($cmd['heure_livraison']);
                } else {
                    $heure = '';
                }

                if (isset($cmd['date_commande'])) {
                    $date_cmd = htmlspecialchars($cmd['date_commande']);
                } else {
                    $date_cmd = '';
                }
            ?>
            <article class="livraison-card">
                <div class="livraison-card-head">
                    <h2 class="livraison-num">Commande #<?php echo $cid; ?></h2>
                    <span class="livraison-statut">En livraison</span>
                </div>
                <div class="livraison-client">
                    <p><strong>Contact :</strong> <?php echo $email !== '' ? $email : '—'; ?></p>
                    <?php if ($heure !== ''): ?>
                        <p><strong>Cr&eacute;neau souhait&eacute; :</strong> <?php echo $heure; ?></p>
                    <?php endif; ?>
                    <p><strong>Pass&eacute;e le :</strong> <?php echo $date_cmd; ?></p>
                    <p><strong>Total :</strong> <?php echo $total; ?> &euro;</p>
                </div>
                <div class="livraison-card-footer">
                    <a href="detail/index.php?id=<?php echo $cid; ?>" class="btn-confirm-livraison btn-livraison-detail-link">
                        <span class="btn-livraison-detail-text">Acc&eacute;der &agrave; cette commande</span>
                        <span class="btn-livraison-detail-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </main>

    <script src="../js/menu-toggle.js"></script>
</body>
</html>
