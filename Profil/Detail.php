<?php
// chemin de base du site pour que les liens marchent partout
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
);
// protection.php demarre la session et nous donne $est_connecte, $nom_affiche, $role_actuel et $pdo
require_once "../../../protection.php";
// base des comptes d'un cote, base des commandes de l'autre
$pdo_users = $pdo;
require_once "../../../../db_config_yumland.php";
$pdo_commandes = $pdo;
// getapikey nous donne la cle secrete de la banque cybank pour verifier les paiements
require_once "../CYBank/getapikey.php";
// notre petit fichier avec la fonction statut_slug
require_once __DIR__ . "/fonctions.php";

// ces deux variables serviront a afficher un message vert (succes) ou rouge (erreur) en haut de la page
$message_succes = "";
$message_erreur = "";

// cette fonction lit le fichier items.json et renvoie la liste des articles du menu
// on la range deux fois : par categorie pour l'affichage, et par id pour retrouver vite un article
function load_articles_disponibles()
{
    $path = __DIR__ . "/../data/items.json";
    // si le fichier n'existe pas on renvoie des listes vides pour eviter une erreur
    if (!is_file($path)) {
        return ["par_categorie" => [], "par_id" => []];
    }
    $json = json_decode((string) file_get_contents($path), true);
    $par_cat = [];
    $par_id = [];
    if (is_array($json)) {
        foreach ($json as $cat => $items) {
            if (!is_array($items)) {
                continue;
            }
            $par_cat[$cat] = [];
            foreach ($items as $item) {
                $id = (int) ($item["id"] ?? 0);
                if ($id <= 0) {
                    continue;
                }
                $entry = [
                    "id" => $id,
                    "name" => (string) ($item["name"] ?? ""),
                    "price" => (float) ($item["price"] ?? 0),
                    "cat" => (string) ($item["cat"] ?? $cat),
                ];
                $par_cat[$cat][] = $entry;
                $par_id[$id] = $entry;
            }
        }
    }
    return ["par_categorie" => $par_cat, "par_id" => $par_id];
}

// cette fonction recalcule le total et le nombre d'articles d'une commande a partir de ses lignes
// on l'appelle apres chaque ajout ou suppression pour que le total affiche reste juste
function recalc_commande($pdo, $cid)
{
    // on additionne prix fois quantite pour le total, et les quantites pour le nombre d'articles
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(prix * quantite), 0) AS total,
                COALESCE(SUM(quantite), 0) AS nb
           FROM commande_items
          WHERE commande_id = ?",
    );
    $stmt->execute([$cid]);
    $row = $stmt->fetch();
    $total = (float) ($row["total"] ?? 0);
    $nb = (int) ($row["nb"] ?? 0);

    $stmt = $pdo->prepare("UPDATE commandes SET total = ?, nb_articles = ? WHERE id = ?");
    $stmt->execute([$total, $nb, $cid]);

    $stmt = $pdo->prepare("SELECT total, montant_paye FROM commandes WHERE id = ?");
    $stmt->execute([$cid]);
    $r = $stmt->fetch();
    // delta c'est ce qu'il reste a payer, donc le total moins ce qui a deja ete paye
    $delta = max(0, (float) $r["total"] - (float) $r["montant_paye"]);

    return [
        "total" => (float) $r["total"],
        "nb_articles" => $nb,
        "montant_paye" => (float) $r["montant_paye"],
        "delta" => $delta,
    ];
}

// quand le montant deja paye couvre tout le total de la commande, on marque toutes les lignes comme payees
// comme ca si l'utilisateur supprime une ligne apres, on saura qu'il faut le rembourser en credit
function sync_lignes_paye_si_solde_couvert(PDO $pdo, int $commande_id): void
{
    $stmt = $pdo->prepare("SELECT total, montant_paye FROM commandes WHERE id = ?");
    $stmt->execute([$commande_id]);
    $r = $stmt->fetch();
    if (!$r) {
        return;
    }
    // le petit 1e-4 sert a eviter les soucis d'arrondi avec les nombres a virgule
    if ((float) $r["montant_paye"] + 1e-4 >= (float) $r["total"]) {
        $u = $pdo->prepare("UPDATE commande_items SET paye = 1 WHERE commande_id = ? AND paye = 0");
        $u->execute([$commande_id]);
    }
}
// si on n'est pas connecte on ne prepare aucune commande, la page affichera juste le message d'acces restreint
if (!$est_connecte) {
    $commande = null;
    $lignes_commande = [];
} else {
    // on retrouve le compte connecte pour avoir son email et son id
    $stmt = $pdo_users->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$nom_affiche]);
    $user_actuel = $stmt->fetch();

    // retour de la banque cybank apres un paiement de complement
    // cybank revient sur notre page avec des infos dans l'url : id, transaction, status, montant, vendeur, control
    if (
        $_SERVER["REQUEST_METHOD"] === "GET" &&
        isset(
            $_GET["id"],
            $_GET["transaction"],
            $_GET["status"],
            $_GET["montant"],
            $_GET["vendeur"],
            $_GET["control"],
        ) &&
        $user_actuel
    ) {
        $cid_ret = (int) $_GET["id"];
        $transaction = (string) $_GET["transaction"];
        $status = (string) $_GET["status"];
        $montant = (string) $_GET["montant"];
        $vendeur = (string) $_GET["vendeur"];
        $control_recu = (string) $_GET["control"];

        // on recalcule nous memes la signature attendue et on la comparera a celle envoyee par la banque
        // si elles sont differentes c'est que quelqu'un a bidouille l'url, on refusera le paiement
        $API = getAPIKey($vendeur);
        $control_attendu = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $status . "#");

        // on garde en session la liste des transactions deja traitees pour ne pas compter un paiement deux fois
        if (!isset($_SESSION["cybank_complements_traites"])) {
            $_SESSION["cybank_complements_traites"] = [];
        }

        if (isset($_SESSION["cybank_complements_traites"][$transaction])) {
            $message_succes = "Complément déjà enregistré pour cette transaction.";
        } elseif ($control_recu !== $control_attendu) {
            $message_erreur = "Vérification CYBank échouée (signature invalide).";
        } elseif ($status !== "accepted") {
            $message_erreur = "Paiement du complément refusé par CYBank.";
        } else {
            // tout est bon : on ajoute le montant paye a la commande, mais seulement si elle est encore en attente cuisine
            $stmt = $pdo_commandes->prepare(
                "UPDATE commandes
                    SET montant_paye = montant_paye + ?
                  WHERE id = ?
                    AND email = ?
                    AND statut_production = 'En attente cuisine'",
            );
            $stmt->execute([(float) $montant, $cid_ret, $user_actuel["email"]]);

            if ($stmt->rowCount() > 0) {
                // on note cette transaction comme traitee pour ne pas la recompter si l'utilisateur recharge la page
                $_SESSION["cybank_complements_traites"][$transaction] = true;
                sync_lignes_paye_si_solde_couvert($pdo_commandes, $cid_ret);
                $message_succes =
                    "Paiement du complément enregistré (" . number_format((float) $montant, 2, ",", " ") . " €).";
            } else {
                $message_erreur = "Commande introuvable ou plus modifiable.";
            }
        }
    }

    // ici on traite les actions ajax envoyees par le javascript : ajouter ou supprimer un article
    if ($_SERVER["REQUEST_METHOD"] === "POST" && $user_actuel && isset($_POST["ajax_action"])) {
        // on previent que la reponse sera du json et pas du html
        header("Content-Type: application/json; charset=utf-8");

        $action = (string) $_POST["ajax_action"];
        $cid_post = isset($_POST["commande_id"]) ? (int) $_POST["commande_id"] : 0;

        // securite : on verifie que la commande appartient bien au compte connecte (on filtre par email)
        $stmt = $pdo_commandes->prepare("SELECT id, statut_production FROM commandes WHERE id = ? AND email = ?");
        $stmt->execute([$cid_post, $user_actuel["email"]]);
        $cmd_check = $stmt->fetch();

        if (!$cmd_check) {
            echo json_encode(["ok" => false, "error" => "Commande introuvable."]);
            exit();
        }
        // on n'autorise les modifs que tant que la cuisine n'a pas pris la commande en charge
        if ($cmd_check["statut_production"] !== "En attente cuisine") {
            echo json_encode(["ok" => false, "error" => "Commande verrouillée (déjà en cuisine)."]);
            exit();
        }

        try {
            switch ($action) {
                case "add_item":
                    $produit_id = (int) ($_POST["produit_id"] ?? 0);
                    // on force la quantite entre 1 et 20 pour eviter les valeurs farfelues
                    $quantite = max(1, min(20, (int) ($_POST["quantite"] ?? 1)));

                    // on va chercher l'article dans le menu, comme ca le prix vient de chez nous et pas du formulaire
                    // ca empeche quelqu'un de s'ajouter un article a 0 euro en bidouillant la requete
                    $catalog = load_articles_disponibles();
                    $article = $catalog["par_id"][$produit_id] ?? null;

                    if (!$article) {
                        echo json_encode(["ok" => false, "error" => "Article inconnu."]);
                        exit();
                    }

                    $stmt = $pdo_commandes->prepare(
                        "INSERT INTO commande_items (commande_id, produit_id, nom, prix, quantite, paye)
                         VALUES (?, ?, ?, ?, ?, 0)",
                    );
                    $stmt->execute([$cid_post, $article["id"], $article["name"], $article["price"], $quantite]);
                    $new_item_id = (int) $pdo_commandes->lastInsertId();

                    $totaux = recalc_commande($pdo_commandes, $cid_post);

                    echo json_encode(
                        [
                            "ok" => true,
                            "item" => [
                                "id" => $new_item_id,
                                "nom" => $article["name"],
                                "prix" => (float) $article["price"],
                                "quantite" => $quantite,
                            ],
                        ] + $totaux,
                    );
                    exit();

                case "remove_item":
                    $item_id = (int) ($_POST["item_id"] ?? 0);

                    // on lit la ligne avant de la supprimer pour savoir combien elle vaut et si elle etait deja payee
                    $stmt = $pdo_commandes->prepare(
                        "SELECT prix, quantite, paye FROM commande_items WHERE id = ? AND commande_id = ?",
                    );
                    $stmt->execute([$item_id, $cid_post]);
                    $ligne = $stmt->fetch();

                    if (!$ligne) {
                        echo json_encode(["ok" => false, "error" => "Article introuvable."]);
                        exit();
                    }

                    $valeur_ligne = (float) $ligne["prix"] * (int) $ligne["quantite"];
                    $ligne_payee = (int) ($ligne["paye"] ?? 0) === 1;

                    // on supprime la ligne
                    $stmt = $pdo_commandes->prepare("DELETE FROM commande_items WHERE id = ? AND commande_id = ?");
                    $stmt->execute([$item_id, $cid_post]);

                    // on recalcule le total de la commande maintenant qu'il y a une ligne en moins
                    $totaux = recalc_commande($pdo_commandes, $cid_post);

                    // on ne rembourse en credit que si la ligne supprimee avait deja ete payee
                    $credit_ajoute = 0.0;
                    if ($ligne_payee && $valeur_ligne > 0) {
                        $stmt = $pdo_users->prepare("UPDATE users SET credit = credit + ? WHERE id = ?");
                        $stmt->execute([$valeur_ligne, (int) $user_actuel["id"]]);
                        $credit_ajoute = $valeur_ligne;
                    }

                    echo json_encode(
                        [
                            "ok" => true,
                            "item_id" => $item_id,
                            "credit_ajoute" => $credit_ajoute,
                        ] + $totaux,
                    );
                    exit();

                default:
                    echo json_encode(["ok" => false, "error" => "Action inconnue."]);
                    exit();
            }
        } catch (PDOException $e) {
            // si la base plante, on ecrit l'erreur dans les logs du serveur et on renvoie un message simple a l'utilisateur
            error_log("Detail.php AJAX PDO: " . $e->getMessage());
            echo json_encode([
                "ok" => false,
                "error" => "Erreur d’enregistrement en base. Vérifiez la connexion ou réessayez.",
            ]);
            exit();
        }
    }

    // ici on traite le clic sur le bouton payer le complement
    // on fabrique un petit formulaire qui part directement vers le site de la banque cybank
    if ($_SERVER["REQUEST_METHOD"] === "POST" && $user_actuel && isset($_POST["pay_complement"])) {
        $cid_post = isset($_POST["commande_id"]) ? (int) $_POST["commande_id"] : 0;

        $stmt = $pdo_commandes->prepare(
            "SELECT id, total, montant_paye, statut_production
               FROM commandes
              WHERE id = ? AND email = ?",
        );
        $stmt->execute([$cid_post, $user_actuel["email"]]);
        $cmd_pay = $stmt->fetch();

        if (!$cmd_pay) {
            $message_erreur = "Commande introuvable.";
        } elseif ($cmd_pay["statut_production"] !== "En attente cuisine") {
            $message_erreur = "Commande verrouillée (déjà en cuisine).";
        } else {
            $delta_a_payer = max(0, (float) $cmd_pay["total"] - (float) $cmd_pay["montant_paye"]);
            if ($delta_a_payer <= 0) {
                $message_erreur = "Aucun complément à payer.";
            } else {
                // on prepare les infos pour la banque : le vendeur, un numero de transaction unique et le montant
                // retour_url c'est la page ou la banque nous renverra une fois le paiement fait
                // control c'est une signature pour que la banque sache que la demande vient bien de nous
                $vendeur = "MI-1_E";
                $API = getAPIKey($vendeur);
                $transaction = uniqid();
                $montant_fmt = number_format($delta_a_payer, 2, ".", "");
                $retour_url = "https://alexandre-gourdon.fr{$BASE}/Profil/Detail.php?id=" . $cid_post;
                $control = md5(
                    $API . "#" . $transaction . "#" . $montant_fmt . "#" . $vendeur . "#" . $retour_url . "#",
                );

                // cette mini page n'affiche presque rien, le formulaire est envoye tout seul par le petit script en bas
                // du coup l'utilisateur arrive directement sur le site de la banque
                echo "<!DOCTYPE html><html><body>";
                echo "<form id='cybank_form' action='https://www.plateforme-smc.fr/cybank/index.php' method='POST'>";
                echo "<input type='hidden' name='transaction' value='" . htmlspecialchars($transaction) . "'>";
                echo "<input type='hidden' name='montant' value='" . htmlspecialchars($montant_fmt) . "'>";
                echo "<input type='hidden' name='vendeur' value='" . htmlspecialchars($vendeur) . "'>";
                echo "<input type='hidden' name='retour' value='" . htmlspecialchars($retour_url) . "'>";
                echo "<input type='hidden' name='control' value='" . htmlspecialchars($control) . "'>";
                echo "<noscript><button type='submit'>Continuer vers CYBank</button></noscript>";
                echo "</form>";
                echo "<script>document.getElementById('cybank_form').submit();</script>";
                echo "</body></html>";
                exit();
            }
        }
    }

    // ici on traite soit l'annulation de la commande, soit la modif de l'adresse et du creneau
    if (
        $_SERVER["REQUEST_METHOD"] === "POST" &&
        $user_actuel &&
        (isset($_POST["cancel_commande"]) || isset($_POST["update_commande"]))
    ) {
        $cid_post = isset($_POST["commande_id"]) ? (int) $_POST["commande_id"] : 0;

        // encore une fois on verifie que la commande appartient bien au compte connecte
        $stmt = $pdo_commandes->prepare("SELECT id, statut_production FROM commandes WHERE id = ? AND email = ?");
        $stmt->execute([$cid_post, $user_actuel["email"]]);
        $cmd_check = $stmt->fetch();

        if (!$cmd_check) {
            $message_erreur = "Commande introuvable.";
        } elseif ($cmd_check["statut_production"] !== "En attente cuisine") {
            $message_erreur = "Cette commande ne peut plus être modifiée (déjà en cuisine).";
        } else {
            if (isset($_POST["cancel_commande"])) {
                // on passe la commande en annule, puis on renvoie vers l'historique
                $stmt = $pdo_commandes->prepare(
                    "UPDATE commandes SET statut_production = 'Annulé' WHERE id = ? AND statut_production = 'En attente cuisine'",
                );
                $stmt->execute([$cid_post]);
                header("Location: historique.php?annulee=1");
                exit();
            }

            if (isset($_POST["update_commande"])) {
                // on recupere les nouvelles infos de livraison saisies dans le formulaire
                $heure = trim((string) ($_POST["heure_livraison"] ?? ""));
                $adresse = trim((string) ($_POST["adresse"] ?? ""));
                $code_postal = trim((string) ($_POST["code_postal"] ?? ""));
                $ville = trim((string) ($_POST["ville"] ?? ""));
                $pays = trim((string) ($_POST["pays"] ?? ""));

                $stmt = $pdo_commandes->prepare(
                    "UPDATE commandes
                       SET heure_livraison = ?, adresse = ?, code_postal = ?, ville = ?, pays = ?
                     WHERE id = ? AND statut_production = 'En attente cuisine'",
                );
                $stmt->execute([$heure, $adresse, $code_postal, $ville, $pays, $cid_post]);
                // on recharge la page avec maj=1 dans l'url pour afficher un petit message de confirmation
                header("Location: Detail.php?id=" . $cid_post . "&maj=1");
                exit();
            }
        }
    }

    // a partir d'ici on charge la commande a afficher, son numero vient de l'url (?id=...)
    $cid = isset($_GET["id"]) ? (int) $_GET["id"] : 0;

    // si l'id n'est pas valide on repart vers l'historique
    if ($cid <= 0 || !$user_actuel) {
        header("Location: historique.php");
        exit();
    }

    // on charge la commande en verifiant au passage qu'elle appartient bien au compte connecte (filtre email)
    $stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE id = ? AND email = ?");
    $stmt->execute([$cid, $user_actuel["email"]]);
    $commande = $stmt->fetch();

    // si la commande n'existe pas ou n'est pas la notre, on repart vers l'historique
    if (!$commande) {
        header("Location: historique.php");
        exit();
    }

    // on charge les articles (les lignes) de cette commande
    $stmtItems = $pdo_commandes->prepare(
        "SELECT id, nom, quantite, prix FROM commande_items WHERE commande_id = ? ORDER BY id ASC",
    );
    $stmtItems->execute([$cid]);
    $lignes_commande = $stmtItems->fetchAll();

    // si on revient ici juste apres une modif (maj=1 dans l'url), on prepare le message de confirmation
    if (isset($_GET["maj"]) && $_GET["maj"] === "1") {
        $message_succes = "Commande mise à jour.";
    }
}

// la commande est modifiable seulement tant que la cuisine ne l'a pas prise, donc statut en attente cuisine
$est_modifiable = $commande && ($commande["statut_production"] ?? "") === "En attente cuisine";
// on ne charge le menu (pour le formulaire d'ajout) que si la commande est encore modifiable
$catalog_articles = $est_modifiable ? load_articles_disponibles() : ["par_categorie" => [], "par_id" => []];

$montant_paye = $commande ? (float) ($commande["montant_paye"] ?? 0) : 0;
// reste a payer = total moins ce qui a deja ete paye, jamais en dessous de zero
$reste_a_payer = $commande ? max(0, (float) $commande["total"] - $montant_paye) : 0;

// petit raccourci pour echapper le texte avant de l'afficher, ca protege contre le xss (du code injecte dans la page)
$h = static function ($v) {
    if ($v === null || $v === "") {
        return "";
    }
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, "UTF-8");
};
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Détail de ma commande<?php echo $commande ? " #" . (int) $commande["id"] : ""; ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="../css/historique.css">
    <link rel="stylesheet" href="detail.css">
    <?php if ($est_modifiable): ?>
    <script>
    // ce script est bien dans le head comme demande, et pas en bas du body
    // DOMContentLoaded veut dire qu'on attend que la page soit chargee avant de toucher aux elements
    document.addEventListener('DOMContentLoaded', function () {
        // le numero de la commande vient du php, on s'en sert pour parler au serveur
        const COMMANDE_ID = <?= (int) $commande["id"] ?>;
        const ENDPOINT = 'Detail.php?id=' + COMMANDE_ID;

        // petite fonction qui envoie une action au serveur en ajax et recupere sa reponse en json
        function postAction(payload) {
            const body = new FormData();
            Object.keys(payload).forEach(function (k) { body.append(k, payload[k]); });
            return fetch(ENDPOINT, { method: 'POST', body: body, credentials: 'same-origin' })
                .then(function (r) {
                    return r.text().then(function (text) {
                        // on enleve un eventuel caractere invisible au debut, puis on essaie de lire le json
                        const raw = text.replace(/^\uFEFF/, '').trim();
                        try {
                            return JSON.parse(raw);
                        } catch (e2) {
                            console.error('[Detail AJAX] Réponse non-JSON (HTTP ' + r.status + ') : ', raw.slice(0, 400));
                            if (r.redirected || /^<!DOCTYPE|^<html/i.test(raw)) {
                                return Promise.reject(new Error('SESSION'));
                            }
                            if (r.status >= 400) {
                                return Promise.reject(new Error('HTTP_' + r.status));
                            }
                            return Promise.reject(new Error('BAD_JSON'));
                        }
                    });
                });
        }

        // selon le type d'erreur attrape plus haut, on renvoie un message clair a montrer a l'utilisateur
        function ajaxErrorMessage(err) {
            if (err && err.message === 'SESSION') {
                return 'Session expirée ou déconnecté(e). Rechargez la page et reconnectez-vous.';
            }
            if (err && err.message === 'HTTP_500') {
                return 'Erreur serveur. Réessayez dans un instant.';
            }
            if (err && err.message === 'BAD_JSON') {
                return 'Réponse serveur invalide. Si le problème continue, vérifiez les logs PHP.';
            }
            if (err && err.name === 'TypeError') {
                return 'Impossible de joindre le serveur (connexion coupée ou adresse incorrecte).';
            }
            return 'Erreur réseau, réessayez.';
        }

        // protege le texte avant de l'injecter dans la page, en remplacant les caracteres speciaux du html
        function escapeHtml(s) {
            return String(s)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        // met un nombre au format prix : deux chiffres apres la virgule, et une virgule au lieu du point
        function formatPrice(n) {
            return Number(n).toFixed(2).replace('.', ',');
        }

        // met a jour les compteurs affiches : total, nombre d'articles, reste a payer et bouton payer
        function refreshTotals(data) {
            const totalEl = document.getElementById('detail-total-value');
            if (totalEl) totalEl.textContent = formatPrice(data.total) + ' €';

            const nbEl = document.getElementById('detail-nb-articles');
            if (nbEl) nbEl.textContent = data.nb_articles;

            const payeEl = document.getElementById('detail-montant-paye');
            if (payeEl) payeEl.textContent = formatPrice(data.montant_paye) + ' €';

            const resteRow = document.getElementById('detail-reste-row');
            const resteVal = document.getElementById('detail-reste-value');
            const payForm  = document.getElementById('detail-pay-complement');
            const payAmt   = document.getElementById('detail-pay-amount');

            if (data.delta > 0.001) {
                if (resteRow) resteRow.style.display = '';
                if (resteVal) resteVal.textContent = formatPrice(data.delta) + ' €';
                if (payForm)  payForm.style.display = '';
                if (payAmt)   payAmt.textContent = formatPrice(data.delta);
            } else {
                if (resteRow) resteRow.style.display = 'none';
                if (payForm)  payForm.style.display = 'none';
            }
        }

        // branche la croix d'un article pour pouvoir le supprimer quand on clique dessus
        function attachRemoveListener(btn) {
            btn.addEventListener('click', function () {
                if (!window.confirm('Supprimer cet article de la commande ?')) return;
                const itemId = btn.getAttribute('data-item-id');
                btn.disabled = true;

                postAction({
                    ajax_action: 'remove_item',
                    commande_id: COMMANDE_ID,
                    item_id: itemId
                }).then(function (data) {
                    if (!data || !data.ok) {
                        btn.disabled = false;
                        window.alert('Erreur : ' + ((data && data.error) || 'inconnue'));
                        return;
                    }

                    const li = btn.closest('.detail-item');
                    if (li) {
                        li.style.transition = 'opacity .2s ease, transform .2s ease';
                        li.style.opacity = '0';
                        li.style.transform = 'translateX(8px)';
                        setTimeout(function () {
                            li.remove();

                            // si la liste est devenue vide, on remet le message qui dit qu'il n'y a aucun article
                            const list = document.getElementById('detail-items-list');
                            if (list && list.children.length === 0) {
                                list.remove();
                                const card = document.querySelector('.detail-card');
                                if (card) {
                                    const p = document.createElement('p');
                                    p.className = 'detail-empty';
                                    p.id = 'detail-empty-msg';
                                    p.textContent = 'Aucun article enregistré pour cette commande.';
                                    const addBlock = document.querySelector('.detail-add-block');
                                    card.insertBefore(p, addBlock);
                                }
                            }
                        }, 200);
                    }

                    refreshTotals(data);

                    if (data.credit_ajoute && data.credit_ajoute > 0) {
                        showAddFeedback('success',
                            'Article supprimé. ' + formatPrice(data.credit_ajoute) + ' € ajoutés à votre crédit.');
                    } else {
                        showAddFeedback('success', 'Article retiré.');
                    }
                }).catch(function (err) {
                    btn.disabled = false;
                    window.alert(ajaxErrorMessage(err));
                });
            });
        }

        // au chargement on branche la croix de tous les articles deja affiches
        document.querySelectorAll('.detail-item-remove').forEach(attachRemoveListener);

        const feedback = document.getElementById('detail-add-feedback');

        // affiche un petit message vert ou rouge sous le formulaire d'ajout
        function showAddFeedback(kind, msg) {
            if (!feedback) return;
            feedback.className = 'detail-add-feedback';
            if (kind === 'error')   feedback.classList.add('is-error');
            if (kind === 'success') feedback.classList.add('is-success');
            feedback.textContent = msg;
        }

        const addForm = document.getElementById('detail-add-form');
        if (addForm) {
            const addBtn = addForm.querySelector('.detail-add-btn');
            const select = document.getElementById('detail-add-select');
            const qtyInput = document.getElementById('detail-add-qty');

            // quand on valide le formulaire d'ajout, on envoie l'article choisi au serveur sans recharger la page
            addForm.addEventListener('submit', function (e) {
                e.preventDefault();
                showAddFeedback('', '');

                const produitId = select.value;
                let quantite = parseInt(qtyInput.value, 10) || 1;

                if (!produitId) {
                    showAddFeedback('error', 'Veuillez choisir un article.');
                    return;
                }
                if (quantite < 1) quantite = 1;
                if (quantite > 20) quantite = 20;

                addBtn.disabled = true;
                showAddFeedback('', 'Ajout en cours…');

                postAction({
                    ajax_action: 'add_item',
                    commande_id: COMMANDE_ID,
                    produit_id: produitId,
                    quantite: quantite
                }).then(function (data) {
                    addBtn.disabled = false;
                    if (!data || !data.ok) {
                        showAddFeedback('error', (data && data.error) || 'Erreur inconnue');
                        return;
                    }

                    // on construit la nouvelle ligne d'article pour l'afficher tout de suite sans recharger
                    const sousTotal = data.item.prix * data.item.quantite;
                    const li = document.createElement('li');
                    li.className = 'detail-item';
                    li.setAttribute('data-item-id', data.item.id);
                    li.style.opacity = '0';
                    li.innerHTML =
                        '<span class="detail-item-qte">' + data.item.quantite + '&times;</span>' +
                        '<span class="detail-item-nom">' + escapeHtml(data.item.nom) + '</span>' +
                        '<span class="detail-item-prix">' + formatPrice(sousTotal) + ' €' +
                            (data.item.quantite > 1
                                ? '<small>(' + formatPrice(data.item.prix) + ' € / u.)</small>'
                                : '') +
                        '</span>' +
                        '<button type="button" class="detail-item-remove" ' +
                            'data-item-id="' + data.item.id + '" ' +
                            'title="Supprimer cet article" aria-label="Supprimer">&times;</button>';

                    // on recupere la liste des articles, et si elle n'existait pas encore on la cree
                    let list = document.getElementById('detail-items-list');
                    if (!list) {
                        const emptyMsg = document.getElementById('detail-empty-msg');
                        if (emptyMsg) emptyMsg.remove();
                        list = document.createElement('ul');
                        list.id = 'detail-items-list';
                        list.className = 'detail-items';
                        const addBlock = document.querySelector('.detail-add-block');
                        addBlock.parentNode.insertBefore(list, addBlock);
                    }
                    list.appendChild(li);

                    // petite animation pour faire apparaitre la ligne en douceur
                    requestAnimationFrame(function () {
                        li.style.transition = 'opacity .25s ease';
                        li.style.opacity = '1';
                    });

                    // on branche la croix du nouvel article pour pouvoir le supprimer lui aussi
                    attachRemoveListener(li.querySelector('.detail-item-remove'));

                    // on remet a jour les totaux et le bouton payer
                    refreshTotals(data);

                    showAddFeedback('success',
                        'Article ajouté.' +
                        (data.delta > 0.001 ? ' Pensez à payer le complément (' + formatPrice(data.delta) + ' €).' : ''));

                    // on vide le formulaire pour preparer le prochain ajout
                    select.value = '';
                    qtyInput.value = '1';
                }).catch(function (err) {
                    addBtn.disabled = false;
                    showAddFeedback('error', ajaxErrorMessage(err));
                });
            });
        }
    });
    </script>
    <?php endif; ?>
</head>
<body>

    <header class="prof-header">
        <div class="prof-header-left">
            <a href="historique.php" class="prof-back">&larr;</a>
            <h1 class="prof-title">Détail de la commande</h1>
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
    <!-- pas connecte, on montre juste le message d'acces restreint -->

        <main class="prof-main">
            <div class="prof-card prof-card-warn">
                <div class="prof-card-header">
                    <span class="prof-dot" style="background:var(--danger);"></span>
                    <h2>Acc&egrave;s restreint</h2>
                </div>
                <p>Vous devez &ecirc;tre connect&eacute; pour acc&eacute;der au détail d'une commande.</p>
                <div class="prof-card-actions">
                    <a href="<?= $BASE ?>/LOG/login" class="prof-btn prof-btn-primary">Se connecter</a>
                    <a href="<?= $BASE ?>/LOG/signup" class="prof-btn prof-btn-secondary">S'inscrire</a>
                </div>
            </div>
        </main>

    <?php // ces deux blocs affichent un bandeau vert (succes) ou rouge (erreur) seulement s'il y a un message
            // si la commande n'a aucun article on met un message, sinon on liste les articles un par un
            // la croix de suppression n'apparait que si la commande est encore modifiable
            // ce bloc pour ajouter un article ne s'affiche que si la commande est encore modifiable
            // la liste deroulante est remplie avec les articles du menu, groupes par categorie
            // ce bouton de paiement n'apparait que s'il reste quelque chose a payer et que la commande est modifiable
            // on prepare les morceaux de l'adresse et on regarde s'il y en a au moins un de rempli
            // on n'affiche le bloc adresse que si on a vraiment une adresse a montrer
            // tout ce bloc de modification n'apparait que tant que la commande est modifiable
            // le confirm() demande une validation avant d'annuler, parce que c'est irreversible
            // si la commande est livree, on propose de la noter, sauf si elle a deja ete notee
            // a sortir un jour dans une migration : ce ALTER TABLE tourne a chaque affichage d'une commande livree
            // on regarde s'il existe deja un avis pour cette commande

            else: ?>
    <!-- connecte : on affiche tout le detail de la commande -->

    <main class="prof-main">
        <section class="detail-commande">

            <?php
            // ces deux blocs affichent un bandeau vert (succes) ou rouge (erreur) seulement s'il y a un message
            ?>
            <?php if ($message_succes !== ""): ?>
                <div class="detail-flash detail-flash-success"><?= htmlspecialchars($message_succes) ?></div>
            <?php endif; ?>
            <?php if ($message_erreur !== ""): ?>
                <div class="detail-flash detail-flash-error"><?= htmlspecialchars($message_erreur) ?></div>
            <?php endif; ?>

            <div class="detail-head">
                <div>
                    <span class="detail-id">Commande #<?= (int) $commande["id"] ?></span>
                    <span class="detail-date">
                        Passée le <?= date("d/m/Y à H:i", strtotime($commande["date_commande"])) ?>
                    </span>
                </div>
                <span class="detail-statut detail-statut-<?= htmlspecialchars(statut_slug($commande["statut_production"])) ?>">
                    <?= htmlspecialchars($commande["statut_production"]) ?>
                </span>
            </div>


            <div class="detail-grid">

                <article class="detail-card">
                    <h2 class="detail-card-title">Articles commandés</h2>

                    <?php
            // si la commande n'a aucun article on met un message, sinon on liste les articles un par un
            ?>
                    <?php if (empty($lignes_commande)): ?>
                        <p class="detail-empty" id="detail-empty-msg">Aucun article enregistré pour cette commande.</p>
                    <?php else: ?>
                        <ul class="detail-items" id="detail-items-list">
                            <?php foreach ($lignes_commande as $ligne): ?>
                                <?php
                                $qte = (int) ($ligne["quantite"] ?? 0);
                                $prix_u = isset($ligne["prix"]) ? (float) $ligne["prix"] : 0.0;
                                $sous_total = $prix_u * $qte;
                                ?>
                                <li class="detail-item" data-item-id="<?= (int) ($ligne["id"] ?? 0) ?>">
                                    <span class="detail-item-qte"><?= $qte ?>&times;</span>
                                    <span class="detail-item-nom"><?= $h($ligne["nom"] ?? "") ?></span>
                                    <span class="detail-item-prix">
                                        <?= number_format($sous_total, 2, ",", " ") ?> €
                                        <?php if ($qte > 1): ?>
                                            <small>(<?= number_format($prix_u, 2, ",", " ") ?> € / u.)</small>
                                        <?php endif; ?>
                                    </span>
                                    <?php
                                    // la croix de suppression n'apparait que si la commande est encore modifiable
                                    ?>
                                    <?php if ($est_modifiable): ?>
                                        <button type="button" class="detail-item-remove"
                                                        data-item-id="<?= (int) ($ligne["id"] ?? 0) ?>"
                                                        title="Supprimer cet article"
                                                        aria-label="Supprimer">&times;</button>
                                    <?php endif; ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php
            // ce bloc pour ajouter un article ne s'affiche que si la commande est encore modifiable
            ?>
                    <?php if ($est_modifiable): ?>
                        <div class="detail-add-block">
                            <h3 class="detail-add-title">Ajouter un article</h3>
                            <p class="detail-edit-note">
                                Tant que la cuisine n'a pas pris la commande en charge, vous pouvez ajouter des articles à la commande et en supprimer.
                            </p>
                            <form class="detail-add-form" id="detail-add-form">
                                <div class="detail-add-fields">
                                    <?php
                            // la liste deroulante est remplie avec les articles du menu, groupes par categorie
                            ?>
                                    <select id="detail-add-select" required>
                                        <option value="">— Choisir un article —</option>
                                        <?php foreach ($catalog_articles["par_categorie"] as $cat => $items): ?>
                                            <?php if (empty($items)) {
                                                    continue;
                                            } ?>
                                            <optgroup label="<?= htmlspecialchars($cat) ?>">
                                                <?php foreach ($items as $item): ?>
                                                    <option value="<?= (int) $item["id"] ?>">
                                                        <?= htmlspecialchars($item["name"]) ?> — <?= number_format((float) $item["price"],2,","," ",) ?> €
                                                    </option>
                                                <?php endforeach; ?>
                                            </optgroup>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="number" id="detail-add-qty" value="1" min="1" max="20" aria-label="Quantité">
                                    <button type="submit" class="prof-btn prof-btn-primary detail-add-btn">+ Ajouter</button>
                                </div>
                                <p class="detail-add-feedback" id="detail-add-feedback" role="status"></p>
                            </form>
                        </div>
                    <?php endif; ?>

                    <div class="detail-total">
                        <span>Total</span>
                        <span class="detail-total-value" id="detail-total-value">
                            <?= number_format((float) $commande["total"], 2, ",", " ") ?> €
                        </span>
                    </div>
                </article>

                <article class="detail-card">
                    <h2 class="detail-card-title">Informations</h2>
                    <dl class="detail-infos">
                        <div>
                            <dt>Nombre d'articles</dt>
                            <dd id="detail-nb-articles"><?= (int) $commande["nb_articles"] ?></dd>
                        </div>
                        <div>
                            <dt>Statut paiement</dt>
                            <dd><?= $h($commande["statut"] ?? "") ?></dd>
                        </div>
                        <div>
                            <dt>Statut commande</dt>
                            <dd><?= $h($commande["statut_production"] ?? "") ?></dd>
                        </div>
                        <?php if (!empty($commande["heure_livraison"])): ?>
                            <div>
                                <dt>Créneau souhaité</dt>
                                <dd><?= $h($commande["heure_livraison"]) ?></dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>Montant payé</dt>
                            <dd id="detail-montant-paye"><?= number_format($montant_paye, 2, ",", " ") ?> €</dd>
                        </div>
                        <div class="detail-reste-row" id="detail-reste-row" style="<?= $reste_a_payer > 0
                                ? ""
                                : "display:none;" ?>">
                            <dt>Reste à payer</dt>
                            <dd id="detail-reste-value" class="detail-reste-value"><?= number_format(
                                    $reste_a_payer,
                                    2,
                                    ",",
                                    " ",
                            ) ?> €</dd>
                        </div>
                    </dl>
                        </br>
                    <?php
            // ce bouton de paiement n'apparait que s'il reste quelque chose a payer et que la commande est modifiable
            ?>
                    <form method="POST" action="Detail.php?id=<?= (int) $commande["id"] ?>"
                                class="detail-pay-complement" id="detail-pay-complement"
                                style="<?= $reste_a_payer > 0 && $est_modifiable ? "" : "display:none;" ?>">
                        <input type="hidden" name="commande_id" value="<?= (int) $commande["id"] ?>">
                        <button type="submit" name="pay_complement" value="1" class="prof-btn prof-btn-primary detail-pay-btn">
                            Payer le complément (<span id="detail-pay-amount"><?= number_format(
                                    $reste_a_payer,
                                    2,
                                    ",",
                                    " ",
                            ) ?></span> €)
                        </button>
                    </form>

                    <p class="detail-pay-complement">
                        Le total de votre commande est : <?php echo number_format((float) $commande["total"], 2, ",", " "); ?> €
                    </p>
                </article>

                <?php
                $adresse = trim((string) ($commande["adresse"] ?? ""));
                $cp = trim((string) ($commande["code_postal"] ?? ""));
                $ville = trim((string) ($commande["ville"] ?? ""));
                $pays = trim((string) ($commande["pays"] ?? ""));
                $a_une_adresse = $adresse !== "" || $cp !== "" || $ville !== "" || $pays !== "";
                ?>
                <?php
            // on n'affiche le bloc adresse que si on a vraiment une adresse a montrer
            ?>
                <?php if ($a_une_adresse): ?>
                    <article class="detail-card">
                        <h2 class="detail-card-title">Adresse de livraison</h2>
                        <address class="detail-adresse">
                            <?php if ($adresse !== ""): ?><div><?= $h($adresse) ?></div><?php endif; ?>
                            <?php if ($cp !== "" || $ville !== ""): ?>
                                <div><?= $h(trim($cp . " " . $ville)) ?></div>
                            <?php endif; ?>
                            <?php if ($pays !== ""): ?><div><?= $h($pays) ?></div><?php endif; ?>
                        </address>
                    </article>
                <?php endif; ?>

            </div>

            <?php
            // tout ce bloc de modification n'apparait que tant que la commande est modifiable
            ?>
            <?php if ($est_modifiable): ?>
                <article class="detail-card detail-edit">
                    <h2 class="detail-card-title">Modifier la commande</h2>
                    <p class="detail-edit-note">
                        Tant que la cuisine n'a pas pris la commande en charge, vous pouvez encore ajuster l'adresse, le créneau.
                    </p>

                    <details class="detail-edit-details">
                        <summary class="prof-btn prof-btn-secondary detail-edit-toggle">Modifier l'adresse / le créneau</summary>
                        <form method="POST" action="Detail.php?id=<?= (int) $commande["id"] ?>" class="detail-edit-form">
                            <input type="hidden" name="commande_id" value="<?= (int) $commande["id"] ?>">

                            <label>
                                <span>Créneau souhaité</span>
                                <input type="text" name="heure_livraison"
                                              value="<?= $h($commande["heure_livraison"] ?? "") ?>"
                                              placeholder="Dès que possible">
                            </label>

                            <label>
                                <span>Adresse</span>
                                <input type="text" name="adresse"
                                              value="<?= $h($commande["adresse"] ?? "") ?>">
                            </label>

                            <div class="detail-edit-row">
                                <label>
                                    <span>Code postal</span>
                                    <input type="text" name="code_postal"
                                                  value="<?= $h($commande["code_postal"] ?? "") ?>">
                                </label>
                                <label>
                                    <span>Ville</span>
                                    <input type="text" name="ville"
                                                  value="<?= $h($commande["ville"] ?? "") ?>">
                                </label>
                            </div>

                            <label>
                                <span>Pays</span>
                                <input type="text" name="pays"
                                              value="<?= $h($commande["pays"] ?? "") ?>">
                            </label>

                            <button type="submit" name="update_commande" value="1"
                                            class="prof-btn prof-btn-primary">Enregistrer les modifications</button>
                        </form>
                    </details>

                    <?php
                    // le confirm() demande une validation avant d'annuler, parce que c'est irreversible
                    ?>
                    <form method="POST" action="Detail.php?id=<?= (int) $commande["id"] ?>"
                                class="detail-cancel-form"
                                onsubmit="return confirm('Êtes-vous sûr de vouloir annuler cette commande ? Cette action est irréversible.');">
                        <input type="hidden" name="commande_id" value="<?= (int) $commande["id"] ?>">
                        <button type="submit" name="cancel_commande" value="1"
                                        class="prof-btn prof-btn-danger">Annuler la commande</button>
                    </form>
                </article>
            <?php endif; ?>

            <div class="detail-actions">
                <a href="historique.php" class="prof-btn prof-btn-secondary">&larr; Retour à l'historique</a>
                <?php
            // si la commande est livree, on propose de la noter, sauf si elle a deja ete notee
            ?>
                <?php if ($commande["statut_production"] === "Livré"): ?>
                    <?php
                    try {
                            $pdo_commandes->exec("ALTER TABLE avis_clients ADD COLUMN id_commande INT DEFAULT NULL");
                    } catch (Exception $e) {
                    }
                    $stmtAvis = $pdo_commandes->prepare("SELECT 1 FROM avis_clients WHERE id_commande = ?");
                    $stmtAvis->execute([$commande["id"]]);
                    $deja_note = $stmtAvis->fetch();
                    ?>
                    <?php if (!$deja_note): ?>
                        <a href="../Notation/index.php?id=<?= (int) $commande[
                                "id"
                        ] ?>" class="prof-btn prof-btn-primary">Noter cette commande</a>
                    <?php else: ?>
                        <button class="prof-btn prof-btn-secondary" disabled style="opacity: 0.6; cursor: not-allowed;">Commande déjà notée</button>
                    <?php endif; ?>
                <?php endif; ?>
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
