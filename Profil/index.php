<?php $BASE = preg_replace('#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#', '', $_SERVER['SCRIPT_NAME']); ?>
<?php require_once '../../../protection.php'; 
    $pdo_users = $pdo;
    require_once '../../../../db_config_yumland.php';
    $pdo_commandes = $pdo;


    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_update'])) {
    $nouveau_nom = trim($_POST['new_username'] ?? '');
    $nouveau_pass = $_POST['new_password'] ?? '';
    $id_user = $_SESSION['user_id'] ?? 0;

    if ($id_user > 0 && strlen($nouveau_nom) >= 3) {
        // 1. Update du nom
        $stmt = $pdo_users->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$nouveau_nom, $id_user]);
        $_SESSION['nom_utilisateur'] = $nouveau_nom;

        if (!empty($nouveau_pass)) {
            $hash = password_hash($nouveau_pass, PASSWORD_BCRYPT);
            $stmt = $pdo_users->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $id_user]);
        }
        echo "success";
    } else {
        echo "error";
    }
    exit;
}


    $user_credit = 0.0;
    if ($est_connecte) {
        $stmt = $pdo_users->prepare("SELECT credit FROM users WHERE username = ?");
        $stmt->execute([$nom_affiche]);
        $row = $stmt->fetch();
        if ($row) {
            $user_credit = (float) ($row['credit'] ?? 0);
        }
    }
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
      <a href="<?= $BASE ?>/" class="prof-back">&larr;</a>
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
          <a href="<?= $BASE ?>/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
          <a href="<?= $BASE ?>/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
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

          <div class="prof-card prof-card-info" id="card-username" style="cursor:pointer;">
              <div class="prof-card-label">NOM D'UTILISATEUR</div>
              <div class="prof-card-value" id="val-username"><?php echo htmlspecialchars($nom_affiche); ?></div>
              
              <div id="edit-username-zone" style="display:none; margin-top:5px;">
                  <input type="text" id="input-username" value="<?php echo htmlspecialchars($nom_affiche); ?>" maxlength="20" 
                        style="width:100%; background:rgba(255,255,255,0.1); color:white; border:1px solid #555; border-radius:4px; padding:5px;">
                  <small id="counter" style="display:block; color:#888; font-size:10px; margin-top:3px;">0/20 caractères</small>
              </div>
          </div>

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">R&ocirc;le</div>
            <div class="prof-card-value"><?php echo htmlspecialchars($role_actuel); ?></div>
          </div>

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">Statut</div>
            <div class="prof-card-value prof-status-active">Actif</div>
          </div>

          <div class="prof-card prof-card-info">
            <div class="prof-card-label">Cr&eacute;dit fid&eacute;lit&eacute;</div>
            <div class="prof-card-value" style="color: var(--selected-item-orange);">
              <?= number_format($user_credit, 2, ',', ' ') ?> &euro;
            </div>
          </div>

        </div>
      </section>

      
      <section class="prof-section">
        <div class="prof-section-header">
          <span class="prof-dot" style="background:var(--selected-item-orange);"></span>
          <h2>Acc&egrave;s rapides</h2>
        </div>
        <div class="prof-cards-grid">

          <a href="<?= $BASE ?>/" class="prof-card prof-card-link">
            <div class="prof-card-icon">&#127968;</div>
            <div>
              <div class="prof-card-label">Accueil</div>
              <div class="prof-card-desc">Retour au restaurant</div>
            </div>
          </a>

          <?php if ($role_actuel === "chef" || $role_actuel === "admin"): ?>
            <a href="<?= $BASE ?>/Cuisinier/" class="prof-card prof-card-link">
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

          <div class="prof-card prof-card-secure" id="card-password" style="cursor:pointer;">
              <div class="prof-card-label">MOT DE PASSE</div>
              <div class="prof-card-value" id="val-password">••••••••</div>
              
              <div id="edit-password-zone" style="display:none; margin-top:5px;">
                  <input type="password" id="input-password" placeholder="Nouveau mot de passe" 
                        style="width:100%; background:rgba(255,255,255,0.1); color:white; border:1px solid #555; border-radius:4px; padding:5px;">
              </div>
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
           <a href="<?= $BASE ?>/Notation" style="text-decoration:none;"><div class="prof-card prof-card-commande">
            <div class="prof-card-label">Notation</div>
            <div class="prof-card-desc">Noter ma commande</div>
            </div></a> 
        </div>
      
      </section>


      <div style="text-align: center; margin-top: 20px;">
          <button id="save-all" style="display:none; padding: 12px 25px; background:#28a745; color:white; border-radius:8px; border:none; cursor:pointer; font-weight:bold; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);">
              ✅ Enregistrer les modifications
          </button>
          <div id="status-ajax" style="margin-top:15px; font-weight:bold;"></div>
      </div>


    </main>

  <?php endif; ?>
  
  <footer class="prof-footer">
    <p>&copy; 2026 Cy Restaurant &mdash; Creative Yumland</p>
    <a href="/legal" class="prof-legal">Mentions L&eacute;gales &amp; Confidentialit&eacute;</a>
  </footer>
  


  <script>
    const cardUser = document.getElementById('card-username');
    const cardPass = document.getElementById('card-password');
    const btnSave = document.getElementById('save-all');
    const inputUser = document.getElementById('input-username');
    const statusDiv = document.getElementById('status-ajax');

    [cardUser, cardPass].forEach(card => {
        card.addEventListener('click', () => {
            document.getElementById('edit-username-zone').style.display = 'block';
            document.getElementById('edit-password-zone').style.display = 'block';
            document.getElementById('val-username').style.display = 'none';
            document.getElementById('val-password').style.display = 'none';
            btnSave.style.display = 'inline-block';
        });
    });

    inputUser.addEventListener('input', () => {
        document.getElementById('counter').textContent = `${inputUser.value.length}/20 caractères`;
    });

    btnSave.addEventListener('click', (e) => {
        e.preventDefault();
        
        const fd = new FormData();
        fd.append('ajax_update', '1');
        fd.append('new_username', inputUser.value);
        fd.append('new_password', document.getElementById('input-password').value);

        statusDiv.style.color = "orange";
        statusDiv.textContent = "⏳ Enregistrement...";

        fetch(window.location.href, { 
            method: 'POST', 
            body: fd 
        })
        .then(r => r.text())
        .then(txt => {
            if(txt.trim() === "success") {
                statusDiv.style.color = "#28a745";
                statusDiv.textContent = "✅ Profil mis à jour avec succès !";
                setTimeout(() => location.reload(), 1500);
            } else {
                statusDiv.style.color = "#ff4b2b";
                statusDiv.textContent = "❌ Erreur : Pseudo trop court ou problème serveur.";
            }
        })
        .catch(() => {
            statusDiv.textContent = "❌ Erreur de connexion.";
        });
    });
  </script>



</body>
</html>
