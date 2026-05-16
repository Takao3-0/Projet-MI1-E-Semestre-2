<?php
    require_once '../../../protection.php';
    $pdo_etudiant = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

    /**
     * Colonne sur commandes (base commandes_yumland) : référence users.id (base espace_etudiant).
     * Backticks = nom exact côté MySQL.
     */
    const LIVREUR_ID_COL = '`livreur_ID`';
    const LIVRAISON_SQL_UNASSIGNED = '(' . LIVREUR_ID_COL . ' IS NULL OR ' . LIVREUR_ID_COL . ' = 0)';

    if (!$est_connecte || $role_actuel !== 'livreur') {
        header('Location: /ProjetCYJ/CYJ/index.php');
        exit;
    }

    /**
     * @return int|null espace_etudiant.users.id
     */
    function livraison_resolve_livreur_id(PDO $pdo_etudiant, string $username): ?int
    {
        $stmt = $pdo_etudiant->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row['id'] : null;
    }

    $livreur_user_id = !empty($_SESSION['nom_utilisateur'])
        ? livraison_resolve_livreur_id($pdo_etudiant, (string) $_SESSION['nom_utilisateur'])
        : null;

    // Choisir une commande prête : enregistre users.id dans commandes.livreur_ID + statut livreur
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accept_course'])) {
        if ($livreur_user_id === null) {
            header('Location: index.php?err=no_user');
            exit;
        }
        $cid = (int) $_POST['accept_course'];
        if ($cid > 0) {
            $sql = 'UPDATE commandes SET ' . LIVREUR_ID_COL . ' = ?, statut_production = \'Récupérée livreur\'
                 WHERE id = ? AND statut = \'Payé\' AND statut_production = \'Prête\'
                   AND ' . LIVRAISON_SQL_UNASSIGNED;
            $stmt = $pdo_commandes->prepare($sql);
            $stmt->execute([$livreur_user_id, $cid]);
        }
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_livraison'])) {
        $cid = (int) $_POST['confirm_livraison'];
        if ($cid > 0 && $livreur_user_id !== null) {
            $sql = 'UPDATE commandes SET statut_production = \'Livré\'
                     WHERE id = ? AND statut = \'Payé\' AND statut_production = \'Récupérée livreur\'
                       AND ' . LIVREUR_ID_COL . ' = ?';
            $stmt = $pdo_commandes->prepare($sql);
            $stmt->execute([$cid, $livreur_user_id]);
        }
        header('Location: index.php');
        exit;
    }

    const FRAIS_GESTION = 1.00;

    // File d'attente : prêtes, non choisies (tous les livreurs voient la même liste en haut)
    $stmt_disp = $pdo_commandes->prepare(
        "SELECT id, email, date_commande, heure_livraison, total, frais_livraison, ville, code_postal
           FROM commandes
          WHERE statut = 'Payé'
            AND statut_production = 'Prête'
            AND " . LIVRAISON_SQL_UNASSIGNED . "
          ORDER BY date_commande ASC"
    );
    $stmt_disp->execute();
    $disponibles = $stmt_disp->fetchAll(PDO::FETCH_ASSOC);

    // Uniquement les courses assignées à ce compte (même id que users.id)
    $mes_livraisons = [];
    if ($livreur_user_id !== null) {
        $sql = 'SELECT * FROM commandes
              WHERE statut = \'Payé\' AND statut_production = \'Récupérée livreur\'
                AND ' . LIVREUR_ID_COL . ' = ?
              ORDER BY date_commande ASC';
        $stmt_mes = $pdo_commandes->prepare($sql);
        $stmt_mes->execute([$livreur_user_id]);
        $mes_livraisons = $stmt_mes->fetchAll(PDO::FETCH_ASSOC);
    }

    $livreur_warn_no_uid = $livreur_user_id === null;
    $pool_count = count($disponibles);
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
        <p class="livraison-sub">En haut : commandes <strong>prêtes</strong> encore disponibles. En dessous : <strong>vos</strong> courses en cours uniquement.</p>
    </header>

    <?php if (isset($_GET['err']) && $_GET['err'] === 'no_user'): ?>
        <div class="livraison-alert livraison-alert--error" role="alert">
            Impossible de lire votre <code>id</code> dans la base <strong>espace_etudiant</strong>, table <code>users</code>.
        </div>
    <?php endif; ?>

    <?php if ($livreur_warn_no_uid): ?>
        <div class="livraison-alert livraison-alert--error" role="alert">
            Compte sans <code>id</code> utilisateur : vous ne pouvez pas choisir de commande.
        </div>
    <?php endif; ?>

    <section class="livraison-pool-sticky" aria-label="Commandes prêtes sans livreur">

        <?php if (empty($disponibles)): ?>
        <section class="livraison-empty livraison-empty--inline">
            <p>Aucune commande disponible pour le moment.</p>
            <p class="livraison-empty-hint">Elles apparaissent ici lorsque la cuisine a mis la commande en <strong>Pr&ecirc;te</strong>.</p>
        </section>
        <?php else: ?>
        <div class="livraison-list livraison-list--pool">
        <?php foreach ($disponibles as $cmd): ?>
            <?php
                $cid = (int) ($cmd['id'] ?? 0);
                $frais_liv = (float) ($cmd['frais_livraison'] ?? 0.0);
                $gains_nets = max(0.0, $frais_liv - FRAIS_GESTION);
                $heure = isset($cmd['heure_livraison']) ? htmlspecialchars((string) $cmd['heure_livraison'], ENT_QUOTES, 'UTF-8') : '';
                $date_cmd = isset($cmd['date_commande']) ? htmlspecialchars((string) $cmd['date_commande'], ENT_QUOTES, 'UTF-8') : '';
                $ville = isset($cmd['ville']) ? htmlspecialchars((string) $cmd['ville'], ENT_QUOTES, 'UTF-8') : '';
            ?>
            <article class="livraison-card livraison-card--pool">
                <div class="livraison-card-head">
                    <h3 class="livraison-num">Commande #<?php echo $cid; ?></h3>
                    <span class="livraison-statut livraison-statut--pool">Pr&ecirc;te</span>
                </div>
                <div class="livraison-client livraison-client--minimal">
                    <?php if ($ville !== ''): ?>
                        <p><strong>Zone</strong> <?php echo $ville; ?></p>
                    <?php endif; ?>
                    <?php if ($heure !== ''): ?>
                        <p><strong>Cr&eacute;neau</strong> <?php echo $heure; ?></p>
                    <?php endif; ?>
                    <p><strong>Pass&eacute;e le</strong> <?php echo $date_cmd !== '' ? $date_cmd : '—'; ?></p>
                    <div class="livraison-gains-bloc">
                        <div class="livraison-gains-row">
                            <span>Frais de livraison</span>
                            <span><?php echo number_format($frais_liv, 2, ',', ' '); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--deduction">
                            <span>Frais de gestion</span>
                            <span>&minus;<?php echo number_format(FRAIS_GESTION, 2, ',', ' '); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--total">
                            <span>Vos gains bruts</span>
                            <span><?php echo number_format($gains_nets, 2, ',', ' '); ?> &euro;</span>
                        </div>
                    </div>
                    <p class="livraison-pool-note">Adresse et d&eacute;tail apr&egrave;s avoir choisi la commande.</p>
                </div>
                <div class="livraison-card-footer">
                    <form method="post" action="index.php" class="livraison-accept-form">
                        <input type="hidden" name="accept_course" value="<?php echo $cid; ?>">
                        <button type="submit" class="btn-accept-course" <?php echo $livreur_warn_no_uid ? 'disabled' : ''; ?>>
                            Choisir cette commande
                        </button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>

    <section class="livraison-section livraison-section--mine" aria-labelledby="liv-mes">
        <h2 id="liv-mes" class="livraison-section-title">Mes livraisons</h2>

    <?php if (empty($mes_livraisons)): ?>
        <section class="livraison-empty">
            <p>Vous n&rsquo;avez aucune course en cours.</p>
            <p class="livraison-empty-hint">Choisissez une commande dans la section du haut lorsqu&rsquo;elle est disponible.</p>
        </section>
    <?php else: ?>
        <div class="livraison-list">
        <?php foreach ($mes_livraisons as $cmd): ?>
            <?php
                $cid = (int) ($cmd['id'] ?? 0);
                $email = isset($cmd['email']) ? htmlspecialchars((string) $cmd['email'], ENT_QUOTES, 'UTF-8') : '';
                $frais_liv = (float) ($cmd['frais_livraison'] ?? 0.0);
                $gains_nets = max(0.0, $frais_liv - FRAIS_GESTION);
                $heure = isset($cmd['heure_livraison']) ? htmlspecialchars((string) $cmd['heure_livraison'], ENT_QUOTES, 'UTF-8') : '';
                $date_cmd = isset($cmd['date_commande']) ? htmlspecialchars((string) $cmd['date_commande'], ENT_QUOTES, 'UTF-8') : '';
            ?>
            <article class="livraison-card">
                <div class="livraison-card-head">
                    <h3 class="livraison-num">Commande #<?php echo $cid; ?></h3>
                    <span class="livraison-statut">En livraison</span>
                </div>
                <div class="livraison-client">
                    <p><strong>Contact :</strong> <?php echo $email !== '' ? $email : '—'; ?></p>
                    <?php if ($heure !== ''): ?>
                        <p><strong>Cr&eacute;neau souhait&eacute; :</strong> <?php echo $heure; ?></p>
                    <?php endif; ?>
                    <p><strong>Pass&eacute;e le :</strong> <?php echo $date_cmd; ?></p>
                    <div class="livraison-gains-bloc">
                        <div class="livraison-gains-row">
                            <span>Frais de livraison</span>
                            <span><?php echo number_format($frais_liv, 2, ',', ' '); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--deduction">
                            <span>Frais de gestion</span>
                            <span>&minus;<?php echo number_format(FRAIS_GESTION, 2, ',', ' '); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--total">
                            <span>Vos gains bruts</span>
                            <span><?php echo number_format($gains_nets, 2, ',', ' '); ?> &euro;</span>
                        </div>
                    </div>

                </div>
                <div class="livraison-card-footer">
                    <a href="detail/index.php?id=<?php echo $cid; ?>" class="btn-confirm-livraison btn-livraison-detail-link">
                        <span class="btn-livraison-detail-text">D&eacute;tail &amp; confirmer</span>
                        <span class="btn-livraison-detail-arrow" aria-hidden="true">&rarr;</span>
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    </section>
    </main>

    <script src="../js/menu-toggle.js"></script>
</body>
</html>
