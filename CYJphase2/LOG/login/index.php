<?php require_once '../../../../protection.php'; ?>

<?php require_once '../../../../../db_config.php'; // Connexion MariaDB

$message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    if (isset($_POST['username'], $_POST['password'])) {
        $user_saisi = trim($_POST['username']);
        $pass_saisi = $_POST['password'];

        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$user_saisi, $user_saisi]);
        $user_data = $stmt->fetch();

        $hash = $user_data['password'] ?? '';
        if ($user_data && $hash !== '' && $hash !== null && password_verify($pass_saisi, $hash)) {
            $_SESSION['nom_utilisateur'] = $user_data['username'];
            $_SESSION['role'] = $user_data['role'];
            $_SESSION['yumland'] = (bool) $user_data['yumland'];
            
            header("Location: ../../index.php");
            exit;
        } else {
            $message = "Identifiants incorrects.";
        }
    }

    if (isset($_POST['google_token'])) {
        $token = $_POST['google_token'];
        
        $url = "https://oauth2.googleapis.com/tokeninfo?id_token=" . $token;
        $response = file_get_contents($url);
        $data = json_decode($response, true);

        if (isset($data['email'])) {
            $email = $data['email'];
            $google_id = $data['sub'];
            $name = $data['name'];

            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id) VALUES (?, ?, ?)");
                $stmt->execute([$name, $email, $google_id]);
            }

            $_SESSION['nom_utilisateur'] = $name;
            $_SESSION['role'] = $user['role'];
            $_SESSION['yumland'] = $user ? (bool) $user['yumland'] : false;
            
            echo "success";
        } else {
            echo "invalid_token";
        }
        exit; 
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
                        <input type="text" name="username" id="username" required class="input-field" placeholder=" " autocomplete="username">
                        <label for="username" class="floating-label">Nom d'utilisateur ou e-mail</label>
                    </div>

                    <div class="form-group floating-group">
                        <input type="password" name="password" id="password" required class="input-field" placeholder=" ">
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

</body>
</html>
