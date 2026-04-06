<?php
require_once '../../../../protection.php';
require_once '../../../../../db_config_yumland.php';
$pdo_commandes = $pdo;

if ($est_connecte && ($role_actuel == 'livreur' || $role_actuel == 'admin' || $role_actuel == 'fakeadmin')) {

    if (isset($_GET['id'])) {
        $cid = (int) $_GET['id'];
    } else {
        $cid = 0;
    }

    if ($cid <= 0) {
        header('Location: ../index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['confirm_livraison'])) {
        $stmt = $pdo_commandes->prepare(
            "UPDATE commandes SET statut_production = 'Livré' WHERE id = ? AND statut = 'Payé' AND statut_production = 'Récupérée livreur'"
        );
        $stmt->execute([$cid]);
        header('Location: ../index.php');
        exit;
    }

    $stmt = $pdo_commandes->prepare(
        "SELECT * FROM commandes WHERE id = ? AND statut = 'Payé' AND statut_production = 'Récupérée livreur'"
    );
    $stmt->execute([$cid]);
    $cmd = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cmd) {
        header('Location: ../index.php');
        exit;
    }

    $cid = (int) $cmd['id'];

    $h = static function ($v) {
        if ($v === null || $v === '') {
            return '';
        }
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    };

    $prenom = $h($cmd['prenom'] ?? '');
    $nom = $h($cmd['nom'] ?? '');
    $telephone = $h($cmd['telephone'] ?? '');
    $email = $h($cmd['email'] ?? '');
    $adresse = $h($cmd['adresse'] ?? '');
    $code_postal = $h($cmd['code_postal'] ?? '');
    $ville = $h($cmd['ville'] ?? '');
    $pays = $h($cmd['pays'] ?? '');
    $heure = $h($cmd['heure_livraison'] ?? '');

    $date_cmd_brute = $cmd['date_commande'] ?? '';
    $date_cmd = '';
    if ($date_cmd_brute !== '' && $date_cmd_brute !== null) {
        $ts = strtotime((string) $date_cmd_brute);
        $date_cmd = $ts ? date('d/m/Y \à H:i', $ts) : $h($date_cmd_brute);
    }

    $nb_articles = isset($cmd['nb_articles']) ? (int) $cmd['nb_articles'] : 0;
    $total_fmt = isset($cmd['total']) ? number_format((float) $cmd['total'], 2, ',', ' ') : '';

    $stmtItems = $pdo_commandes->prepare(
        'SELECT nom, quantite, prix FROM commande_items WHERE commande_id = ? ORDER BY id ASC'
    );
    $stmtItems->execute([$cid]);
    $lignes_commande = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $nom_complet = trim($prenom . ' ' . $nom);
    $ligne_cp_ville = trim(($code_postal !== '' ? $code_postal . ' ' : '') . $ville);

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
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../../accueil.css">
    <link rel="stylesheet" href="../livraison.css">
    <link rel="stylesheet" href="detail.css">
    <title>Détail commande #<?php echo $cid; ?></title>
</head>
<body class="livraison-page livraison-detail">

    <main class="livraison-main">
        <header class="livraison-top">
            <a href="../index.php" class="livraison-back">&larr; Retour aux livraisons</a>
            <h1>Commande #<?php echo $cid; ?></h1>
            <span class="livraison-statut">En livraison</span>
        </header>

        <article class="livraison-card">
            <div class="livraison-detail-body">
                <section class="livraison-detail-section" aria-labelledby="detail-client">
                    <h2 id="detail-client" class="livraison-detail-section__title">Client</h2>
                    <div class="livraison-detail-lines">
                        <p><strong>Nom</strong> <span><?php echo $nom_complet !== '' ? $nom_complet : '—'; ?></span></p>
                        <p><strong>T&eacute;l&eacute;phone</strong> <span><?php echo $telephone !== '' ? $telephone : '—'; ?></span></p>
                        <p><strong>E-mail</strong> <span><?php echo $email !== '' ? $email : '—'; ?></span></p>
                    </div>
                </section>

                <section class="livraison-detail-section" aria-labelledby="detail-adresse">
                    <h2 id="detail-adresse" class="livraison-detail-section__title">Adresse de livraison</h2>
                    <div class="livraison-detail-lines livraison-detail-lines--address">
                        <?php if ($adresse !== ''): ?>
                            <p class="livraison-detail-address-line"><?php echo $adresse; ?></p>
                        <?php endif; ?>
                        <?php if ($ligne_cp_ville !== ''): ?>
                            <p class="livraison-detail-address-line"><?php echo $ligne_cp_ville; ?></p>
                        <?php endif; ?>
                        <?php if ($pays !== ''): ?>
                            <p class="livraison-detail-address-line"><?php echo $pays; ?></p>
                        <?php endif; ?>
                        <?php if ($adresse === '' && $ligne_cp_ville === '' && $pays === ''): ?>
                            <p class="livraison-detail-empty">Aucune adresse enregistr&eacute;e pour cette commande.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if (!empty($lignes_commande)): ?>
                <section class="livraison-detail-section" aria-labelledby="detail-panier">
                    <h2 id="detail-panier" class="livraison-detail-section__title">Contenu</h2>
                    <ul class="livraison-detail-items">
                        <?php foreach ($lignes_commande as $ligne): ?>
                            <li class="livraison-detail-item">
                                <span class="livraison-detail-item__name"><?php echo htmlspecialchars((string) ($ligne['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></span>
                                <span class="livraison-detail-item__meta">
                                    &times;<?php echo (int) ($ligne['quantite'] ?? 0); ?>
                                    <?php if (isset($ligne['prix'])): ?>
                                        <span class="livraison-detail-item__price"><?php echo number_format((float) $ligne['prix'], 2, ',', ' '); ?> &euro; / u.</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <section class="livraison-detail-section" aria-labelledby="detail-commande">
                    <h2 id="detail-commande" class="livraison-detail-section__title">Commande</h2>
                    <div class="livraison-detail-lines">
                        <p><strong>Pass&eacute;e le</strong> <span><?php echo $date_cmd !== '' ? $date_cmd : '—'; ?></span></p>
                        <p><strong>Cr&eacute;neau souhait&eacute;</strong> <span><?php echo $heure !== '' ? $heure : 'D&egrave;s que possible'; ?></span></p>
                        <p><strong>Articles</strong> <span><?php echo $nb_articles > 0 ? (string) $nb_articles : '—'; ?></span></p>
                    </div>
                    <div class="livraison-detail-total">
                        <span class="livraison-detail-total__label">Total</span>
                        <span class="livraison-detail-total__value"><?php echo $total_fmt !== '' ? $total_fmt . ' €' : '—'; ?></span>
                    </div>
                </section>
            </div>

            <form method="post" action="index.php?id=<?php echo $cid; ?>" class="livraison-confirm-form">
                <input type="hidden" name="confirm_livraison" value="<?php echo $cid; ?>">
                <button type="submit" class="btn-confirm-livraison">Confirmer la livraison</button>
            </form>
        </article>
    </main>

</body>
</html>
