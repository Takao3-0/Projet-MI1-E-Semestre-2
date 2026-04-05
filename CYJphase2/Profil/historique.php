<?php require_once '../../../protection.php'; 
    
    //pour tester
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;
    $stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
    $stmt->execute(['En attente cuisine']);
    $commandes_order_new = $stmt->fetchAll();

  $yumland = 1;
  $sql = "SELECT * FROM users WHERE yumland = ?";
  $exec = [$yumland];


  $stmt = $pdo_users->prepare($sql);
  $stmt->execute($exec);
  $users = $stmt->fetchAll();


  $stmt = $pdo_users->prepare("SELECT * FROM users WHERE username = ?");
  $stmt->execute([$nom_affiche]);
  $user_actuel = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mon Profil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/historique.css">
</head>
<body>

  <header class="prof-header">
    <div class="prof-header-left">
      <a href="/ProjetCYJ/CYJ/Profil/" class="prof-back">&larr;</a>
      <h1 class="prof-title">Mon Profil</h1>
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
        <p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; votre historique.</p>
        <div class="prof-card-actions">
          <a href="/ProjetCYJ/CYJ/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
          <a href="/ProjetCYJ/CYJ/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
        </div>
      </div>
    </main>

  <?php else: ?>

<div class="historique">
  <h3 class="titre"><span></span> Historique des commandes</h3>
  <div>
        <?php $commande_trouve= false; ?>
        <?php foreach($commandes_order_new as $commande): ?>
          <?php if($user_actuel['email'] == $commande['email']): ?>
                <?php $commande_trouve=true; ?>
              <div>
                  <div>
                      <span class="date">Commande du <?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></span>
                      <span class="nb_articles"><?= $commande['nb_articles'] ?> articles</span>
                  </div>
                  <div class="prix">
                    <?= number_format($commande['total'], 2) ?> €
                  </div>
              </div>
          <?php endif; 
       endforeach; 
       endif; 
       if (!$commande_trouve): ?>
                <div class="prof-card prof-card-info" style="justify-content: center; opacity: 0.8;">
                    <p style="margin: 0; font-size: 0.9rem;">Vous n'avez passé aucune commande.</p>
                </div>
       <?php endif; ?>

  </div>
</div>



  
  <footer class="prof-footer">
    <p>&copy; 2026 Cy Restaurant &mdash; Creative Yumland</p>
    <a href="/legal" class="prof-legal">Mentions L&eacute;gales &amp; Confidentialit&eacute;</a>
  </footer>
  
</body>
</html>
