<?php require_once '../../../protection.php'; 
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mon Profil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/profil.css">
</head>
<body>

  <header class="prof-header">
    <div class="prof-header-left">
      <a href="/ProjetCYJ/CYJ/" class="prof-back">&larr;</a>
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
        <p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der &agrave; votre profil.</p>
        <div class="prof-card-actions">
          <a href="/ProjetCYJ/CYJ/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
          <a href="/ProjetCYJ/CYJ/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
        </div>
      </div>
    </main>

  <?php else: ?>

    <main class="prof-main">

      <section class="prof-identity">
        <div class="prof-avatar">
          <?php echo strtoupper(substr($nom_affiche, 0, 1)); ?>
        </div>
        <div class="prof-identity-info">
          <h2 class="prof-identity-name"><?php echo htmlspecialchars($nom_affiche); ?></h2>
          <span class="prof-identity-role"><?php echo htmlspecialchars($role_actuel); ?></span>
        </div>
      </section>

      <section class="prof-section">
        <div class="prof-section-header">
          <span class="prof-dot" style="background:var(--accent-color);"></span>
          <h2>Informations personnelles</h2>
        </div>
        <div class="prof-cards-grid">

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">Nom d'utilisateur</div>
            <div class="prof-card-value"><?php echo htmlspecialchars($nom_affiche); ?></div>
          </div>

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">R&ocirc;le</div>
            <div class="prof-card-value"><?php echo htmlspecialchars($role_actuel); ?></div>
          </div>

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">Statut</div>
            <div class="prof-card-value prof-status-active">Actif</div>
          </div>

        </div>
      </section>

      
      <section class="prof-section">
        <div class="prof-section-header">
          <span class="prof-dot" style="background:var(--selected-item-orange);"></span>
          <h2>Acc&egrave;s rapides</h2>
        </div>
        <div class="prof-cards-grid">

          <a href="/ProjetCYJ/CYJ/" class="prof-card prof-card-link">
            <div class="prof-card-icon">&#127968;</div>
            <div>
              <div class="prof-card-label">Accueil</div>
              <div class="prof-card-desc">Retour au restaurant</div>
            </div>
          </a>

          <?php if ($role_actuel === "chef" || $role_actuel === "admin"): ?>
            <a href="/ProjetCYJ/CYJ/Cuisinier/" class="prof-card prof-card-link">
              <div class="prof-card-icon">&#127859;</div>
              <div>
                <div class="prof-card-label">Cuisine</div>
                <div class="prof-card-desc">G&eacute;rer les commandes</div>
              </div>
            </a>
          <?php endif; ?>

          <?php if ($role_actuel === "admin"): ?>
            <a href="../Admin/" class="prof-card prof-card-link">
              <div class="prof-card-icon">&#9881;</div>
              <div>
                <div class="prof-card-label">Administration</div>
                <div class="prof-card-desc">Panneau d'administration</div>
              </div>
            </a>
          <?php endif; ?>

        </div>
      </section>

      
      <section class="prof-section">
        <div class="prof-section-header">
          <span class="prof-dot" style="background:var(--success);"></span>
          <h2>S&eacute;curit&eacute;</h2>
        </div>
        <div class="prof-cards-grid">

          <div class="prof-card prof-card-secure">
            <div class="prof-card-label">Mot de passe</div>
            <div class="prof-card-value">&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;</div>
          </div>

          <div class="prof-card prof-card-secure">
            <div class="prof-card-label">Session</div>
            <div class="prof-card-value prof-status-active">Active</div>
          </div>

        </div>
      </section>

      <section class="prof-section">
        <div class="prof-section-header">
          <span class="prof-dot" style="background:violet;"></span>
          <h2>Commandes</h2>
        </div>
        <div class="prof-cards-grid">
          <a href="historique.php" style="text-decoration:none;"><div class="prof-card prof-card-commande">
            <div class="prof-card-label">Historique de commandes</div>
            <div class="prof-card-desc">Voir mon historique</div>
            </div></a>
          <a href="commande.php" style="text-decoration:none;"><div class="prof-card prof-card-commande">
            <div class="prof-card-label">Commande en cours</div>
            <div class="prof-card-desc">Voir ma commande</div>
            </div></a>
           <a href="/ProjetCYJ/CYJ/Notation" style="text-decoration:none;"><div class="prof-card prof-card-commande">
            <div class="prof-card-label">Notation</div>
            <div class="prof-card-desc">Noter ma commande</div>
            </div></a> 
        </div>
      
      </section>

    </main>

  <?php endif; ?>
  
  <footer class="prof-footer">
    <p>&copy; 2026 Cy Restaurant &mdash; Creative Yumland</p>
    <a href="/legal" class="prof-legal">Mentions L&eacute;gales &amp; Confidentialit&eacute;</a>
  </footer>
  
</body>
</html>
