<?php
// on calcule le chemin de base du site pour que les liens marchent partout
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
); ?>
<?php // protection.php demarre la session et nous donne $est_connecte, $nom_affiche, $role_actuel et $pdo


require_once "../../../protection.php"; // base des comptes d'un cote, base des commandes de l'autre
$pdo_users = $pdo;
require_once "../../../../db_config_yumland.php";
$pdo_commandes = $pdo; // on retrouve la ligne du compte connecte pour pouvoir utiliser son email juste apres
$stmt = $pdo_users->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$nom_affiche]);

$user_actuel = $stmt->fetch(); // on prend les commandes payees mais pas encore livrees, c'est a dire celles qui sont en cours de preparation
// et on les trie de la plus recente a la plus ancienne
$stmt = $pdo_commandes->prepare(
    "SELECT * FROM commandes WHERE email = ? AND statut = 'Payé' AND statut_production != 'Livré' ORDER BY date_commande DESC",
);
$stmt->execute([$user_actuel["email"]]);
$commandes_en_cours = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Suivi de mes commandes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/profil.css">
</head>
<body>

    <header class="prof-header">
        <div class="prof-header-left">
            <a href="<?= $BASE ?>/Profil/" class="prof-back">&larr;</a>
            <h1 class="prof-title">Suivi Commandes</h1>
        </div>
        <div class="prof-header-right">
      <?php if ($est_connecte): ?>
        <span class="prof-badge prof-badge-role"><?php echo htmlspecialchars($role_actuel); ?></span>
        <form method="POST" style="margin:0;">
          <input type="hidden" name="logout" value="1">
          <button type="submit" class="prof-btn-logout">D&eacute;connexion</button>
        </form>
      <?php endif; ?>
    </div>
  </header>

  <?php if (!$est_connecte): ?>
  <!-- pas connecte donc on affiche seulement le message d'acces restreint -->

    <main class="prof-main">
      <div class="prof-card prof-card-warn">
        <div class="prof-card-header">
          <span class="prof-dot" style="background:var(--danger);"></span>
          <h2>Acc&egrave;s restreint</h2>
        </div>
        <p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; vos commandes.</p>
        <div class="prof-card-actions">
          <a href="<?= $BASE ?>/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
          <a href="<?= $BASE ?>/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
        </div>
      </div>
    </main>

  <?php // si la liste est vide on previent l'utilisateur, sinon on affiche une carte par commande

      else: ?>
    <main class="prof-main">
        <section class="historique">
            <div class="titre">
                <span></span> 
                <h2>Commandes en cours de préparation</h2>
            </div>

            <div>
                <?php
      // si la liste est vide on previent l'utilisateur, sinon on affiche une carte par commande
      ?>
                <?php if (empty($commandes_en_cours)): ?>
                    <div class="prof-card prof-card-info" style="justify-content: center; opacity: 0.8;">
                        <p style="margin: 0; font-size: 0.9rem;">Vous n'avez aucune commande en cours pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($commandes_en_cours as $commande): ?>
                        <div class="prof-card prof-card-info">
                            <div>
                                <span class="date">Commande du <?= date(
                                    "d/m/Y à H:i",
                                    strtotime($commande["date_commande"]),
                                ) ?></span>
                                <div class="nb_articles">
                                    <strong>Statut : </strong>
                                    <span style="color: var(--selected-item-orange);">
                                        <?= htmlspecialchars($commande["statut_production"]) ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">
                                    <?= $commande["nb_articles"] ?> article(s)
                                </div>
                            </div>
                            <div class="prix">
                                <?= number_format($commande["total"], 2) ?> €
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </section>
    </main>
  <?php endif; ?>
    <footer class="prof-footer">
        <p>&copy; 2026 Cy Restaurant &mdash; Creative Yumland</p>
    </footer>

</body>
</html>