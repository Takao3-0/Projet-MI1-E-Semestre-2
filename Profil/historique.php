<?php 
  require_once '../../../protection.php'; 
  //pour tester
  $pdo_users = $pdo;
  require_once '../../../../db_config_yumland.php';
  $pdo_commandes = $pdo;

  $stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut = 'Payé'");
  $stmt->execute();
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

  function statut_slug($statut) {
    $sansAccents = strtr((string) $statut, [
      'à' => 'a', 'â' => 'a', 'ä' => 'a',
      'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
      'î' => 'i', 'ï' => 'i',
      'ô' => 'o', 'ö' => 'o',
      'ù' => 'u', 'û' => 'u', 'ü' => 'u',
      'ç' => 'c',
    ]);
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $sansAccents));
  }
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Mon Profil</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="../css/global.css">
  <link rel="stylesheet" href="../css/historique.css">
  <style>
    .historique > div > .commande-link {
        background: var(--card-bg);
        border-radius: 10px;
        padding: 1rem;
        border-left: 4px solid var(--accent-color);
        box-shadow: var(--shadow-sm);
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 0.8rem;
        color: inherit;
        text-decoration: none;
        cursor: pointer;
        transition: transform 0.15s ease, box-shadow 0.15s ease, border-left-color 0.15s ease;
    }
    .historique > div > .commande-link:hover {
        transform: translateY(-1px);
        box-shadow: var(--shadow-md);
        border-left-color: var(--selected-item-orange);
    }
    .historique .commande-chevron {
        font-size: 1.6rem;
        line-height: 1;
        color: var(--text-muted);
        font-weight: 600;
        align-self: center;
        transition: transform 0.15s ease, color 0.15s ease;
    }
    .historique .commande-meta {
        display: flex;
        align-items: center;
        gap: 0.6rem;
        flex-wrap: wrap;
        margin-top: 4px;
    }
    .historique .commande-statut {
        font-size: 0.68rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        padding: 3px 9px;
        border-radius: 999px;
        background: var(--surface-3);
        color: var(--text-main);
        white-space: nowrap;
        line-height: 1.4;
    }
    .historique .commande-statut-en-attente-cuisine {
        background: rgba(231, 76, 60, 0.12);
        color: #c0392b;
    }
    .historique .commande-statut-en-preparation {
        background: rgba(243, 156, 18, 0.14);
        color: #b8780b;
    }
    .historique .commande-statut-prete {
        background: rgba(52, 152, 219, 0.14);
        color: #1f6fa6;
    }
    .historique .commande-statut-recuperee-livreur {
        background: rgba(155, 89, 182, 0.14);
        color: #7d3c98;
    }
    .historique .commande-statut-livre {
        background: rgba(46, 204, 113, 0.14);
        color: #1e8449;
    }
    .historique .commande-statut-annule {
        background: rgba(108, 117, 125, 0.14);
        color: #6c757d;
        text-decoration: line-through;
    }
    @media (prefers-color-scheme: dark) {
        .historique .commande-statut-en-attente-cuisine { color: #ff8a7a; }
        .historique .commande-statut-en-preparation { color: #ffc97a; }
        .historique .commande-statut-prete { color: #82c4f0; }
        .historique .commande-statut-recuperee-livreur { color: #d2a4e6; }
        .historique .commande-statut-livre { color: #7ee2a8; }
    }
    .historique > div > .commande-link:hover .commande-chevron {
        transform: translateX(3px);
        color: var(--selected-item-orange);
    }
    @media (prefers-color-scheme: dark) {
        .historique > div > .commande-link { background: var(--surface-1); }
    }
    @media (max-width: 600px) {
        .historique > div > .commande-link {
            flex-direction: column;
            align-items: flex-start;
            gap: 0.8rem;
        }
        .historique > div > .commande-link .prix { align-self: flex-end; }
        .historique .commande-chevron { display: none; }
    }
  </style>
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
                <a href="Detail.php?id=<?= (int) $commande['id'] ?>" class="commande-link">
                    <div class="commande-infos">
                        <span class="date">Commande du <?= date('d/m/Y à H:i', strtotime($commande['date_commande'])) ?></span>
                        <div class="commande-meta">
                            <span class="nb_articles"><?= $commande['nb_articles'] ?> articles</span>
                            <span class="commande-statut commande-statut-<?= htmlspecialchars(statut_slug($commande['statut_production'])) ?>">
                                <?= htmlspecialchars($commande['statut_production']) ?>
                            </span>
                        </div>
                    </div>
                    <div class="prix">
                      <?= number_format($commande['total'], 2) ?> €
                    </div>
                    <span class="commande-chevron" aria-hidden="true">&rsaquo;</span>
                </a>
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
