<?php
// on calcule le chemin de base du site (par exemple /ProjetCYJ/CYJ) pour que tous les liens marchent partout
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
); ?>
<?php // protection.php demarre la session et nous donne $est_connecte, $nom_affiche, $role_actuel et $pdo


require_once "../../../protection.php"; // ici on a deux bases differentes, une pour les comptes et une pour les commandes
// protection.php a ouvert la base des comptes dans $pdo, on la garde de cote dans pdo_users
$pdo_users = $pdo; // ce fichier ecrase $pdo avec la base des commandes, donc on la range dans pdo_commandes
require_once "../../../../db_config_yumland.php";
$pdo_commandes = $pdo; // ce bloc repond au formulaire de modif du profil envoye par le javascript plus bas (pas de rechargement de page)
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["ajax_update"])) {
    $nouveau_nom = trim($_POST["new_username"] ?? "");
    $nouveau_pass = $_POST["new_password"] ?? ""; // on prend l'id dans la session et pas dans le formulaire, comme ca on ne peut pas modifier le compte d'un autre
    $id_user = $_SESSION["user_id"] ?? 0; // on accepte seulement si le compte existe et si le nouveau pseudo fait au moins 3 lettres
    if ($id_user > 0 && strlen($nouveau_nom) >= 3) {
        // on change le pseudo
        $stmt = $pdo_users->prepare("UPDATE users SET username = ? WHERE id = ?");
        $stmt->execute([$nouveau_nom, $id_user]);
        $_SESSION["nom_utilisateur"] = $nouveau_nom; // le mot de passe est optionnel, on le change seulement si l'utilisateur en a tape un nouveau
        if (!empty($nouveau_pass)) {
            // password_hash chiffre le mot de passe, on ne garde jamais le vrai mot de passe en clair dans la base
            $hash = password_hash($nouveau_pass, PASSWORD_BCRYPT);
            $stmt = $pdo_users->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $id_user]);
        } // on repond juste success ou error, c'est le javascript qui lira cette reponse
        echo "success";
    } else {
        echo "error";
    } // exit pour s'arreter la et ne pas renvoyer toute la page html apres une reponse ajax
    exit();
} // on va chercher le credit fidelite du compte pour pouvoir l'afficher sur la carte plus bas
$user_credit = 0.0;
if ($est_connecte) {
    $stmt = $pdo_users->prepare("SELECT credit FROM users WHERE username = ?");
    $stmt->execute([$nom_affiche]);
    $row = $stmt->fetch();
    if ($row) {
        $user_credit = (float) ($row["credit"] ?? 0);
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
    <script>
        // ce script est bien dans le head comme demande, et pas en bas du body
        // DOMContentLoaded veut dire qu'on attend que la page soit chargee avant de toucher aux elements
        document.addEventListener('DOMContentLoaded', () => {
            // on recupere les cartes et les champs dont on a besoin
            const cardUser = document.getElementById('card-username');
            const cardPass = document.getElementById('card-password');
            const btnSave = document.getElementById('save-all');
            const inputUser = document.getElementById('input-username');
            const statusDiv = document.getElementById('status-ajax');

            // ces elements existent seulement quand on est connecte, sinon on arrete tout de suite
            if (!cardUser || !cardPass || !btnSave || !inputUser) return;

            // quand on clique sur la carte pseudo ou la carte mot de passe, on fait apparaitre les champs de modif
            [cardUser, cardPass].forEach(card => {
                    card.addEventListener('click', () => {
                            document.getElementById('edit-username-zone').style.display = 'block';
                            document.getElementById('edit-password-zone').style.display = 'block';
                            document.getElementById('val-username').style.display = 'none';
                            document.getElementById('val-password').style.display = 'none';
                            btnSave.style.display = 'inline-block';
                    });
            });

            // pendant qu'on tape le pseudo on met a jour le petit compteur de caracteres
            inputUser.addEventListener('input', () => {
                    document.getElementById('counter').textContent = `${inputUser.value.length}/20 caractères`;
            });

            // au clic sur enregistrer on envoie les nouvelles valeurs au serveur sans recharger la page
            btnSave.addEventListener('click', (e) => {
                    e.preventDefault();

                    // on prepare les donnees a envoyer comme si c'etait un formulaire
                    const fd = new FormData();
                    fd.append('ajax_update', '1');
                    fd.append('new_username', inputUser.value);
                    fd.append('new_password', document.getElementById('input-password').value);

                    statusDiv.style.color = "orange";
                    statusDiv.textContent = "⏳ Enregistrement...";

                    // fetch envoie la requete, et on lit la reponse texte du serveur
                    fetch(window.location.href, {
                            method: 'POST',
                            body: fd
                    })
                    .then(r => r.text())
                    .then(txt => {
                            // le serveur a repondu success ou error, on affiche le message qui correspond
                            if(txt.trim() === "success") {
                                    statusDiv.style.color = "#28a745";
                                    statusDiv.textContent = "✅ Profil mis à jour avec succès !";
                                    // on recharge la page apres 1.5s pour voir les nouvelles infos
                                    setTimeout(() => location.reload(), 1500);
                            } else {
                                    statusDiv.style.color = "#ff4b2b";
                                    statusDiv.textContent = "❌ Erreur : Pseudo trop court ou problème serveur.";
                            }
                    })
                    .catch(() => {
                            // ici on arrive si le serveur est injoignable (coupure reseau par exemple)
                            statusDiv.textContent = "❌ Erreur de connexion.";
                    });
            });
        });
    </script>
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
    <!-- si la personne n'est pas connectee on montre seulement un message qui demande de se connecter -->

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

      /* on prend juste la premiere lettre du pseudo en majuscule pour faire un avatar tout simple */<?php // le lien vers la cuisine ne s'affiche que pour les chefs et les admins
            // et le lien vers l'administration seulement pour les admins

            else: ?>
    <!-- sinon on affiche tout le profil de la personne connectee -->

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
                                    <input type="text" id="input-username" value="<?php echo htmlspecialchars(
                                            $nom_affiche,
                                    ); ?>" maxlength="20" 
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
                            <?= number_format($user_credit, 2, ",", " ") ?> &euro;
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

                    <?php
            // le lien vers la cuisine ne s'affiche que pour les chefs et les admins
            ?>
                    <?php if ($role_actuel === "chef" || $role_actuel === "admin"): ?>
                        <a href="<?= $BASE ?>/Cuisinier/" class="prof-card prof-card-link">
                            <div class="prof-card-icon">&#127859;</div>
                            <div>
                                <div class="prof-card-label">Cuisine</div>
                                <div class="prof-card-desc">G&eacute;rer les commandes</div>
                            </div>
                        </a>
                    <?php endif; ?>

                    <?php
            // et le lien vers l'administration seulement pour les admins
            ?>
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
    





</body>
</html>
