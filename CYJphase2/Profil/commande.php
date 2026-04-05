<?php require_once '../../../protection.php'; 
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;

$stmt = $pdo_users->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$nom_affiche]);
$user_actuel = $stmt->fetch();

$stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE email = ? AND statut = 'Payé' AND statut_production != 'Livré' ORDER BY date_commande DESC");
$stmt->execute([$user_actuel['email']]);
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
            <a href="/ProjetCYJ/CYJ/Profil/" class="prof-back">&larr;</a>
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

    <main class="prof-main">
      <div class="prof-card prof-card-warn">
        <div class="prof-card-header">
          <span class="prof-dot" style="background:var(--danger);"></span>
          <h2>Acc&egrave;s restreint</h2>
        </div>
        <p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; vos commandes.</p>
        <div class="prof-card-actions">
          <a href="/ProjetCYJ/CYJ/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
          <a href="/ProjetCYJ/CYJ/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
        </div>
      </div>

  <?php else: ?>
    <main class="prof-main">
        <section class="historique">
            <div class="titre">
                <span></span> 
                <h2>Commandes en cours de préparation</h2>
            </div>

            <div>
                <?php if (empty($commandes_en_cours)): ?>
                    <div class="prof-card prof-card-info" style="justify-content: center; opacity: 0.8;">
                        <p style="margin: 0; font-size: 0.9rem;">Vous n'avez aucune commande en cours pour le moment.</p>
                    </div>
                <?php else: ?>
                    <?php foreach($commandes_en_cours as $commande): ?>
                        <div class="prof-card prof-card-info">
                            <div>
                                <span class="date">Commande du <?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></span>
                                <div class="nb_articles">
                                    <strong>Statut : </strong>
                                    <span style="color: var(--selected-item-orange);">
                                        <?= htmlspecialchars($commande['statut_production']) ?>
                                    </span>
                                </div>
                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 5px;">
                                    <?= $commande['nb_articles'] ?> article(s)
                                </div>
                            </div>
                            <div class="prix">
                                <?= number_format($commande['total'], 2) ?> €
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