<?php require_once "../../../../protection.php"; ?>

<?php
require_once "../../../../../db_config.php"; // Connexion MariaDB

$message = "";
if (isset($_GET["ip_bannie"]) && $_GET["ip_bannie"] === "1") {
    $message =
        'Cette adresse IP a été bannie. Vous ne pouvez pas utiliser le site avec ce réseau. Contactez l\'administration si vous pensez qu\'il s\'agit d\'une erreur.';
} elseif (isset($_GET["bloque"]) && $_GET["bloque"] === "1") {
    $message =
        'Votre compte a été bloqué. Vous ne pouvez plus accéder au site. Pour toute question, contactez l\'administration.';
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (isset($_POST["username"], $_POST["password"])) {
        $user_saisi = trim($_POST["username"]);
        $pass_saisi = $_POST["password"];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$user_saisi, $user_saisi]);
        $user_data = $stmt->fetch();

        $hash = $user_data["password"] ?? "";
        if ($user_data && $hash !== "" && $hash !== null && password_verify($pass_saisi, $hash)) {
            $uid = (int) ($user_data["id"] ?? 0);
            $role_u = (string) ($user_data["role"] ?? "");
            $ip_c = cyj_client_ip();
            if ($uid > 0 && cyj_user_has_active_block($pdo, $uid)) {
                $message = 'Ce compte a été bloqué. Contactez l\'administration.';
            } elseif ($ip_c !== "" && cyj_ip_is_banned($pdo, $ip_c) && !cyj_ip_ban_skipped_for_role($role_u)) {
                $message = "Accès refusé : cette adresse IP est bloquée.";
            } else {
                cyj_session_login_reset();
                $_SESSION["nom_utilisateur"] = $user_data["username"];
                $_SESSION["role"] = $user_data["role"];
                $_SESSION["yumland"] = (bool) $user_data["yumland"];
                if ($uid > 0) {
                    $_SESSION["user_id"] = $uid;
                    cyj_touch_last_login_ip($pdo, $uid, $ip_c);
                }
                header("Location: ../../index.php");
                exit();
            }
        } else {
            $message = "Identifiants incorrects.";
        }
    }

    if (isset($_POST["google_token"])) {
        $token = $_POST["google_token"];

        $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data["email"])) {
            $email = $data["email"];
            $google_id = $data["sub"];
            $name = $data["name"];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $google_id]);
                $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
            }

            if (!$user || !is_array($user)) {
                echo "invalid_token";
                exit();
            }

            $uid_google = (int) ($user["id"] ?? 0);
            if ($uid_google > 0 && cyj_user_has_active_block($pdo, $uid_google)) {
                echo "blocked";
                exit();
            }
            $role_g = (string) ($user["role"] ?? "colab.");
            $ip_g = cyj_client_ip();
            if ($ip_g !== "" && cyj_ip_is_banned($pdo, $ip_g) && !cyj_ip_ban_skipped_for_role($role_g)) {
                echo "ip_blocked";
                exit();
            }

            cyj_session_login_reset();
            $_SESSION["nom_utilisateur"] = $user["username"] ?? $name;
            $_SESSION["role"] = $role_g;
            $_SESSION["yumland"] = $user ? (bool) $user["yumland"] : false;
            if ($uid_google > 0) {
                $_SESSION["user_id"] = $uid_google;
                cyj_touch_last_login_ip($pdo, $uid_google, $ip_g);
            }

            echo "success";
        } else {
            echo "invalid_token";
        }
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Espace Étudiant - Accueil</title>
    <link rel="stylesheet" href="../../../../style.css">
    <link rel="stylesheet" href="../login.css">
</head>
<body>

    <header>
        <section>
            <script src="https://accounts.google.com/gsi/client" async defer></script>

            <div class="log">
                <div class="card-title">Connexion</div>

                <?php if ($message): ?>
                    <p style="color: #ff4b2b; text-align: center; font-weight: bold; margin-bottom: 15px;">
                        <?= $message ?>
                    </p>
                <?php endif; ?>

                <form action="index.php" method="POST">
                    
                    <div class="form-group floating-group">
                        <input type="text" name="username" id="username" required class="input-field" placeholder=" " autocomplete="username" maxlength="100">
                        <label for="username" class="floating-label">Nom d'utilisateur ou e-mail</label>
                    </div>

                    <div class="form-group floating-group">
                        <input type="password" name="password" id="password" required class="input-field" placeholder=" " maxlength="64">
                        <label for="password" class="floating-label">Mot de passe</label>
                        <button type="button" class="toggle-password" onclick="togglePass('password')">Afficher</button>
                    </div>

                    <button type="submit" class="btn-small">Se connecter</button><br>

                    <button type="button" class="btn-small" style="margin-top: 10px; margin-bottom: 10px;">
                        <a href="../signup" style="text-decoration: none; color: inherit;">Je n'ai pas de compte</a>
                    </button>

                </form>
                    
                <script>
                    //Partie de docu google. 
                    function togglePass(id) {
                        const input = document.getElementById(id);
                        const btn = input.nextElementSibling.nextElementSibling; // Cible le bouton spécifique
                        if (input.type === "password") {
                            input.type = "text";
                            btn.textContent = "Masquer";
                        } else {
                            input.type = "password";
                            btn.textContent = "Afficher";
                        }
                    }
                    //Fonction issue de la ducmentation
                    function handleCredentialResponse(response) {
                        // Envoie le jeton à ton serveur pour vérification
                        console.log("Token Google reçu, envoi au serveur...");
                        
                        // On peut utiliser Fetch pour envoyer le token au PHP de manière invisible
                        const formData = new FormData();
                        formData.append('google_token', response.credential);

                        fetch('index.php', { // On l'envoie à la page actuelle
                            method: 'POST',
                            body: formData
                        })
                        .then(res => res.text())
                        .then(data => {
                            if (data.includes("success")) {
                                window.location.href = "../../index.php"; // Redirige vers l'accueil du site
                            } else if (data.trim() === "blocked") {
                                alert("Ce compte a été bloqué. Contactez l'administration.");
                            } else if (data.trim() === "ip_blocked") {
                                alert("Cette adresse IP est bloquée. Contactez l'administration.");
                            }
                        });
                    }
                </script>

                <div class="separator">
                    <span class="separator-line"></span>
                    <span class="separator-text">OU</span>
                    <span class="separator-line"></span>
                </div>

                <div id="g_id_onload"
                    data-client_id="70687721788-r1ha5cre34810qo3tsmhl30hgp2ip4ck.apps.googleusercontent.com"
                    data-callback="handleCredentialResponse"
                    data-auto_prompt="false">
                </div>

                <div class="g_id_signin"
                    data-type="standard"
                    data-shape="pill"
                    data-theme="outline"
                    data-text="signin_with"
                    data-size="large"
                    data-logo_alignment="left"
                    data-width="100%">
                </div>
            </div>
        </section>
    </header>


    <footer>
        <p>&copy; 2026 - Hébergé sur Nginx <br>
            Par Alexandre Gourdon
        </p>
    </footer>

    <script>
        // Script pour le compteur de caractères
        document.addEventListener('DOMContentLoaded', () => {
            const inputsWithMaxlength = document.querySelectorAll('input[maxlength], textarea[maxlength]');
            
            inputsWithMaxlength.forEach(input => {
                const maxLength = input.getAttribute('maxlength');
                
                const counter = document.createElement('div');
                counter.className = 'char-counter';
                counter.style.fontSize = '12px';
                counter.style.color = '#888';
                counter.style.textAlign = 'right';
                counter.style.marginTop = '4px';
                
                const group = input.closest('.form-group') || input.parentElement;
                group.appendChild(counter);
                
                const updateCounter = () => {
                    const currentLength = input.value.length;
                    const remaining = maxLength - currentLength;
                    counter.textContent = `${currentLength}/${maxLength} (restants: ${remaining})`;
                    if (currentLength >= maxLength) {
                        counter.style.color = '#ff4b2b';
                    } else {
                        counter.style.color = '#888';
                    }
                };
                
                input.addEventListener('input', updateCounter);
                updateCounter();
            });
        });
    </script>
</body>
</html>
