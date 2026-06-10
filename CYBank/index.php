<?php
// chemin de base du site pour que les liens marchent partout
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
);
// protection.php demarre la session et nous donne $est_connecte, $nom_affiche et $pdo
require_once "../../../protection.php";
// base des comptes d'un cote, base des commandes de l'autre
$pdo_etudiant = $pdo;
require_once __DIR__ . "/../../../../db_config_yumland.php";
$pdo_commandes = $pdo;
// getapikey donne la cle de la banque ; delivery_distance calcule les frais de livraison
require_once "getapikey.php";
require_once __DIR__ . "/delivery_distance.php";
?>

<?php
// valeurs par defaut tant qu'on n'a pas recu de retour de paiement
$statut = "En attente";
$statut_production = "En attente";
$bool_paiement_traite = false;
// identifiant de la transaction cybank : sert a ne pas enregistrer deux fois la commande si on rafraichit (F5)
$cybank_transaction_id = null;
$message = null;

// si l'url contient les parametres de cybank, c'est qu'on revient d'un paiement
if (
    isset($_GET["montant"]) &&
    isset($_GET["transaction"]) &&
    isset($_GET["status"]) &&
    isset($_GET["vendeur"]) &&
    isset($_GET["control"])
) {
    $montant = $_GET["montant"];
    $transaction = $_GET["transaction"];
    $status = $_GET["status"];
    $vendeur = $_GET["vendeur"];
    $control_output = $_GET["control"];

    $API = getAPIKey($vendeur);

    // on recalcule la signature de notre cote et on la compare a celle envoyee par la banque
    // si elles sont differentes, c'est que les donnees ont ete trafiquees, on refuse
    $control_input = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $status . "#");
    if ($control_input == $control_output) {
        // signature ok : on regarde si la banque a accepte ou refuse le paiement
        if ($status === "accepted") {
            $message = "Paiement effectué avec succès";
            $statut = "Payé";
            $cybank_transaction_id = $transaction;
            $bool_paiement_traite = true;
        } else {
            $message = "Erreur lors du paiement";
            $statut = "Erreur paiment CYBANK";
        }
    } else {
        $message = "Erreur lors de la vérification du paiement";
    }
}

// on passe $BASE en parametre : une fonction php ne voit pas les variables du fichier
// sans ca $BASE serait vide, l'url de retour perdrait le /ProjetCYJ/CYJ et cybank reviendrait sur une page 404
function PaimentCYBANK($panier_total, $BASE)
{
    $vendeur = "MI-1_E";
    $API = getAPIKey($vendeur);
    $transaction = uniqid();
    $montant = number_format($panier_total, 2, ".", "");
    // url ou cybank nous renverra une fois le paiement fait (on revient sur cette meme page)
    $retour = "https://alexandre-gourdon.fr{$BASE}/CYBank/index.php";
    $control = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $retour . "#");

    echo "<form action='https://www.plateforme-smc.fr/cybank/index.php' method='POST'>";
    echo "<input type='hidden' name='transaction' value='$transaction'>";
    echo "<input type='hidden' name='montant' value='$montant'>";
    echo "<input type='hidden' name='vendeur' value='$vendeur'>";
    echo "<input type='hidden' name='retour' value='$retour'>";
    echo "<input type='hidden' name='control' value='$control'>";
    echo "<input type='submit' value='Payer'>";
    echo "</form>";

    echo "<script>document.forms[0].submit();</script>";
    exit();
}

// on charge le catalogue depuis la base : les articles et les menus (memes tables que la page Carte)
$stmt = $pdo_commandes->prepare("SELECT * FROM articles");
$stmt->execute();
$articles_rows = $stmt->fetchAll();

$stmt = $pdo_commandes->prepare("SELECT * FROM menus");
$stmt->execute();
$menus_rows = $stmt->fetchAll();

$catalog = [];
$type_post_labels = [
    "menu" => "Menu",
    "burger" => "Burger",
    "pizza" => "Pizza",
    "wrap" => "Wrap / Tacos",
    "side" => "Accompagnement",
    "dessert" => "Dessert",
    "boisson" => "Boisson",
];
foreach ($articles_rows as $article_row) {
    $row = array_change_key_case($article_row, CASE_LOWER);
    $id = (int) ($row["code"] ?? ($row["id"] ?? 0));
    if ($id <= 0) {
        continue;
    }
    $post = strtolower(trim((string) ($row["type_post"] ?? "")));
    $prix_raw = str_replace(",", ".", (string) ($row["prix"] ?? "0"));
    $cat_label =
        isset($row["categorie"]) && $row["categorie"] !== ""
            ? (string) $row["categorie"]
            : $type_post_labels[$post] ?? ucfirst($post);
    $catalog[$id] = [
        "name" => (string) ($row["nom"] ?? ""),
        "price" => (float) $prix_raw,
        "cat" => $cat_label,
        "post" => $post,
    ];
}

foreach ($menus_rows as $menu_row) {
    $m = array_change_key_case($menu_row, CASE_LOWER);
    $mid = (int) ($m["id"] ?? 0);
    if ($mid <= 0) {
        continue;
    }
    $catKey = -$mid;
    $nom_menu = (string) ($m["nom_menu"] ?? ($m["nom"] ?? "Menu"));
    $prix_menu = str_replace(",", ".", (string) ($m["prix"] ?? "0"));
    $catalog[$catKey] = [
        "name" => $nom_menu,
        "price" => (float) $prix_menu,
        "cat" => "Menu",
        "post" => "menu",
    ];
}

// ── Lecture du panier session
$panier = $_SESSION["panier"] ?? [];

$panier_count = 0;
$panier_total = 0.0;
$panier_items = [];

foreach ($panier as $items) {
    foreach ($items as $id => $qty) {
        if ($qty <= 0 || !isset($catalog[$id])) {
            continue;
        }
        $item = $catalog[$id];
        $panier_count += $qty;
        $panier_total += $item["price"] * $qty;
        $panier_items[] = [
            "id" => $id,
            "qty" => $qty,
            "name" => $item["name"],
            "price" => $item["price"],
            "cat" => $item["cat"],
            "subtotal" => $item["price"] * $qty,
        ];
    }
}

if (isset($_POST["payer"])) {
    if (isset($_POST["timing"]) && $_POST["timing"] === "later" && !empty($_POST["delivery_time"])) {
        $_SESSION["heure_livraison"] = $_POST["delivery_time"];
    } else {
        unset($_SESSION["heure_livraison"]);
    }

    // Conservé en session : après CYBank le navigateur revient en GET, le formulaire n’est plus là
    $liv = [
        "adresse" => isset($_POST["adresse"]) ? trim((string) $_POST["adresse"]) : "",
        "code_postal" => isset($_POST["code_postal"]) ? trim((string) $_POST["code_postal"]) : "",
        "ville" => isset($_POST["ville"]) ? trim((string) $_POST["ville"]) : "",
        "pays" => isset($_POST["pays"]) ? trim((string) $_POST["pays"]) : "",
        "telephone" => isset($_POST["telephone"]) ? trim((string) $_POST["telephone"]) : "",
        "email" => isset($_POST["email"]) ? trim((string) $_POST["email"]) : "",
        "nom" => isset($_POST["nom"]) ? trim((string) $_POST["nom"]) : "",
        "prenom" => isset($_POST["prenom"]) ? trim((string) $_POST["prenom"]) : "",
    ];
    $liv["adresse"] = mb_substr($liv["adresse"], 0, 255);
    $liv["code_postal"] = mb_substr($liv["code_postal"], 0, 10);
    $liv["ville"] = mb_substr($liv["ville"], 0, 100);
    $liv["pays"] = mb_substr($liv["pays"], 0, 100);
    $liv["telephone"] = mb_substr($liv["telephone"], 0, 20);
    $liv["nom"] = mb_substr($liv["nom"], 0, 100);
    $liv["prenom"] = mb_substr($liv["prenom"], 0, 100);
    $liv["email"] = mb_substr($liv["email"], 0, 255);
    $_SESSION["checkout_livraison"] = $liv;

    unset($_SESSION["cybank_checkout_total"], $_SESSION["cybank_checkout_frais"]);

    // l'utilisateur a clique sur payer : paiement = methode choisie, cb_paiement = infos de la carte
    if (isset($_POST["paiement"]) && isset($_POST["cb_paiement"])) {
        $paiement = $_POST["paiement"];
        $cb_paiement = $_POST["cb_paiement"];

        switch ($paiement) {
            case "1":
                // paiement par carte (cybank) : on verifie d'abord que l'adresse de livraison est remplie
                $addrFull = deliveryFormatClientAddress(
                    $liv["adresse"] ?? "",
                    $liv["code_postal"] ?? "",
                    $liv["ville"] ?? "",
                    $liv["pays"] ?? "",
                );
                if (mb_strlen($addrFull) < 8) {
                    $message = "Veuillez compléter l’adresse de livraison (rue, code postal, ville).";
                    break;
                }
                // mode secours : si l'utilisateur coche la case (API Google Directions indisponible,
                // ex. facturation non activee), on saute le calcul d'itineraire et on applique un forfait fixe
                $skip_delivery_check = isset($_POST["skip_delivery_check"]) && $_POST["skip_delivery_check"] === "1";
                if ($skip_delivery_check) {
                    // forfait fixe : on ne connait pas la distance, donc pas de controle des 20 km non plus
                    $frais_livraison_checkout = (float) DELIVERY_PRICING["fallback_fee"];
                } else {
                    // on calcule les frais de livraison pour cette adresse
                    $estLivraison = deliveryEstimate($addrFull, $panier_total);
                    if (empty($estLivraison["ok"])) {
                        $message =
                            (string) ($estLivraison["error"] ?? "Impossible de valider la livraison pour cette adresse.");
                        break;
                    }
                    // cout calcule a partir de la distance Google Maps
                    $frais_livraison_checkout = (float) ($estLivraison["cost"] ?? 0.0);
                }
                // total a payer (panier + livraison), garde en session pour l'enregistrement apres le retour de paiement
                $total_charge_cb = round($panier_total + $frais_livraison_checkout, 2);
                $_SESSION["cybank_checkout_total"] = $total_charge_cb;
                $_SESSION["cybank_checkout_frais"] = $frais_livraison_checkout;
                // on part vers la banque : cette fonction affiche le formulaire, le soumet tout seul et arrete le script
                PaimentCYBANK($total_charge_cb, $BASE);
                break;
            case "2":
                // paypal pas encore implemente
                $message = "Paiement par PayPal";
                break;
            case "3":
                // google pay pas encore implemente
                $message = "Paiement Google Pay";
                break;
            default:
                $message = "Erreur inconnue";
                break;
        }
    }
}

// on enregistre la commande en base seulement si le paiement a bien ete accepte
if ($statut === "Payé" && $cybank_transaction_id !== null) {
    if (!isset($_SESSION["cybank_transactions_traitees"])) {
        $_SESSION["cybank_transactions_traitees"] = [];
    }

    // si cette transaction a deja ete traitee (cas du F5), on ne reenregistre pas la commande
    if (isset($_SESSION["cybank_transactions_traitees"][$cybank_transaction_id])) {
        $message = "Paiement effectué avec succès. Votre commande sera prise en charge dans les plus brefs délais.";
    } elseif (empty($panier_items)) {
        $message = "Paiement reçu, mais le panier est vide : aucune commande enregistrée.";
    } else {
        $db_user_id = null;
        $db_user_email = null;
        if ($est_connecte && !empty($_SESSION["nom_utilisateur"])) {
            $stmtUser = $pdo_etudiant->prepare("SELECT id, email FROM users WHERE username = ? LIMIT 1");
            $stmtUser->execute([$_SESSION["nom_utilisateur"]]);
            $row = $stmtUser->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $db_user_id = (int) $row["id"];
                if (!empty($row["email"])) {
                    $db_user_email = (string) $row["email"];
                }
            }
        }

        if ($db_user_id === null) {
            $message = "Erreur : impossible d'associer la commande à un compte utilisateur.";
        } else {
            $date_commande = date("Y-m-d H:i:s");
            $heure_livraison = $_SESSION["heure_livraison"] ?? null;
            $liv = $_SESSION["checkout_livraison"] ?? [];
            $email_commande = !empty($liv["email"]) ? $liv["email"] : $db_user_email;
            // petit raccourci : renvoie null si la valeur est vide, sinon la valeur en texte
            $strOrNull = static function ($v) {
                if ($v === null || $v === "") {
                    return null;
                }
                return is_string($v) ? $v : (string) $v;
            };
            try {
                // on fait tout dans une transaction : soit toute la commande s'enregistre, soit rien du tout
                $pdo_commandes->beginTransaction();

                $stmt = $pdo_commandes->prepare(
                    "INSERT INTO commandes (user_id, email, date_commande, total, frais_livraison,montant_paye, nb_articles, statut, heure_livraison, adresse, code_postal, ville, pays, telephone, nom, prenom) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                );
                // le total vient de la session (calcule avant le paiement), pas du montant recu dans l'url : c'est plus sur
                $commande_total = isset($_SESSION["cybank_checkout_total"])
                    ? (float) $_SESSION["cybank_checkout_total"]
                    : (float) $panier_total;
                $frais_livraison_checkout = (float) ($_SESSION["cybank_checkout_frais"] ?? 0.0);

                $stmt->execute([
                    $db_user_id,
                    $email_commande,
                    $date_commande,
                    $commande_total,
                    $frais_livraison_checkout,
                    $commande_total,
                    $panier_count,
                    $statut,
                    $heure_livraison,
                    $strOrNull($liv["adresse"] ?? null),
                    $strOrNull($liv["code_postal"] ?? null),
                    $strOrNull($liv["ville"] ?? null),
                    $strOrNull($liv["pays"] ?? null),
                    $strOrNull($liv["telephone"] ?? null),
                    $strOrNull($liv["nom"] ?? null),
                    $strOrNull($liv["prenom"] ?? null),
                ]);

                $commande_id = (int) $pdo_commandes->lastInsertId();
                if ($commande_id <= 0) {
                    throw new PDOException("Identifiant de commande invalide après insertion.");
                }

                // on enregistre chaque article du panier, rattache a la commande, avec paye = 1
                $stmtItem = $pdo_commandes->prepare(
                    "INSERT INTO commande_items (commande_id, produit_id, nom, prix, quantite, paye) VALUES (?, ?, ?, ?, ?, 1)",
                );
                foreach ($panier_items as $item) {
                    $stmtItem->execute([$commande_id, $item["id"], $item["name"], $item["price"], $item["qty"]]);
                }

                // tout s'est bien passe, on valide la transaction en base
                $pdo_commandes->commit();

                // on marque la transaction comme traitee (anti F5), on retient l'id et on vide le panier
                $_SESSION["cybank_transactions_traitees"][$cybank_transaction_id] = true;
                $_SESSION["last_commande_id"] = $commande_id;
                unset($_SESSION["cybank_checkout_total"], $_SESSION["cybank_checkout_frais"]);
                $_SESSION["panier"] = [
                    "menus" => [],
                    "burgers" => [],
                    "pizzas" => [],
                    "wraps" => [],
                    "sides" => [],
                    "desserts" => [],
                    "boissons" => [],
                ];
                unset($_SESSION["heure_livraison"], $_SESSION["checkout_livraison"]);
            } catch (PDOException $e) {
                // en cas d'erreur on annule tout (rollback) pour ne pas laisser une commande a moitie enregistree
                if ($pdo_commandes->inTransaction()) {
                    $pdo_commandes->rollBack();
                }
                $message = "Erreur lors de l'ajout de la commande : " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="paiement.css">
    <link rel="stylesheet" href="../accueil.css">
    <title>Panier - CY Restaurant</title>
    <style>
        /*Pour afficher soit l'un soit l'autre*/

        /*Pour la position sticky on là met ici et pas dans .navbar sinon bah ça s'applique pas vu que pcnavbar et mobilenavbar son parents de la classe nav */
        .pcnavbar {
            display: block;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .mobilenavbar {
            position: sticky;
            top: 0;
            z-index: 100;
            display: none;
        }

        @media (max-width: 900px) {
            .pcnavbar {
                display: none;
            }

            .mobilenavbar {
                display: block;
            }
        }
    </style>
  <script>
    // toggleTime reste une fonction globale : elle est appelee depuis un onclick dans le html (les radios immediate / plus tard)
    // elle ne touche au html que quand on l'appelle, donc pas besoin d'attendre le chargement de la page
    function toggleTime(show) {
        const picker = document.getElementById('time_picker');
        const input = document.getElementById('delivery_time');
        if (!picker || !input) return;
        // on affiche ou on cache le choix de l'heure, et le champ devient obligatoire seulement si on choisit plus tard
        picker.classList.toggle('is-visible', show);
        picker.setAttribute('aria-hidden', show ? 'false' : 'true');
        input.required = !!show;
    }

    // tout le reste touche au html tout de suite, donc on attend que la page soit chargee
    document.addEventListener('DOMContentLoaded', function () {
        const feesEl = document.getElementById('checkout-sum-fees');
        const form = document.querySelector('.checkout-form');
        if (!feesEl || !form) return;

        // ces deux infos viennent du html, dans des attributs data- poses sur le formulaire
        const estimateUrl = form.getAttribute('data-estimate-url') || 'api_delivery_estimate.php';
        let panierTotal = parseFloat(String(form.getAttribute('data-panier-total') || '0').replace(',', '.'), 10);
        if (isNaN(panierTotal)) panierTotal = 0;

        // mode secours : case "ignorer la verification d'adresse" + forfait fixe associe (data- pose sur le formulaire)
        let fallbackFee = parseFloat(String(form.getAttribute('data-fallback-fee') || '0').replace(',', '.'), 10);
        if (isNaN(fallbackFee)) fallbackFee = 0;
        const skipEl = document.getElementById('skip_delivery_check');
        let skipMode = skipEl ? skipEl.checked : false;

        const totalEl = document.getElementById('checkout-sum-total');
        const hintEl = document.getElementById('checkout-delivery-hint');
        const feedbackEl = document.getElementById('delivery_estimate_feedback');
        const payAmountEl = document.getElementById('checkout_pay_amount');

        // met un nombre au format euro francais (virgule et symbole)
        function formatEuro(n) {
            let x = Number(n);
            if (isNaN(x)) x = 0;
            return x.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
        }

        // evite de lancer un calcul a chaque touche : on attend un petit moment apres la derniere frappe
        function debounce(fn, ms) {
            let t;
            return function () {
                const ctx = this, args = arguments;
                clearTimeout(t);
                t = setTimeout(function () { fn.apply(ctx, args); }, ms);
            };
        }

        // recolle l'adresse complete a partir des champs rue, code postal et ville
        function buildAddress() {
            const a = document.getElementById('adresse');
            const cp = document.getElementById('code_postal');
            const v = document.getElementById('ville');
            const line1 = a ? String(a.value || '').trim() : '';
            const postal = cp ? String(cp.value || '').trim() : '';
            const city = v ? String(v.value || '').trim() : '';
            const parts = [];
            if (line1) parts.push(line1);
            const cityLine = (postal + ' ' + city).trim();
            if (cityLine) parts.push(cityLine);
            return parts.join(', ');
        }

        // seq sert a ignorer les vieilles reponses si l'utilisateur retape vite : on ne garde que la derniere
        let seq = 0;

        function setLoading(on) {
            if (!feedbackEl) return;
            if (on) {
                feedbackEl.textContent = 'Calcul de l’itinéraire…';
                feedbackEl.classList.add('checkout-delivery-estimate--loading');
            } else {
                feedbackEl.classList.remove('checkout-delivery-estimate--loading');
            }
        }

        function updatePayAmount(total) {
            if (payAmountEl) payAmountEl.textContent = formatEuro(total);
        }

        // affiche le forfait fixe quand la verification d'adresse est ignoree (pas d'appel serveur)
        function applyFallback() {
            const total = panierTotal + fallbackFee;
            if (fallbackFee <= 0.0001) {
                feesEl.textContent = 'Gratuit';
                feesEl.classList.add('checkout-sum__value--free');
            } else {
                feesEl.textContent = formatEuro(fallbackFee);
                feesEl.classList.remove('checkout-sum__value--free');
            }
            if (totalEl) totalEl.textContent = formatEuro(total);
            updatePayAmount(total);
            if (hintEl) hintEl.textContent = 'Vérification d’adresse ignorée · forfait fixe';
            if (feedbackEl) {
                feedbackEl.textContent = '';
                feedbackEl.classList.remove('checkout-delivery-estimate--error');
            }
        }

        // demande au serveur le cout de livraison pour l'adresse saisie, puis met a jour les montants affiches
        function refreshEstimate() {
            // si la case "ignorer la verification" est cochee, on n'appelle pas Google : forfait fixe
            if (skipMode) { applyFallback(); return; }
            const addr = buildAddress();
            // tant que l'adresse est trop courte on n'appelle pas le serveur, on remet juste le total sans livraison
            if (addr.length < 8) {
                feesEl.textContent = '—';
                feesEl.classList.remove('checkout-sum__value--free');
                if (totalEl) totalEl.textContent = formatEuro(panierTotal);
                updatePayAmount(panierTotal);
                if (hintEl) {
                    hintEl.textContent = 'Saisissez rue, code postal et ville : estimation automatique (Google Maps).';
                }
                if (feedbackEl) {
                    feedbackEl.textContent = '';
                    feedbackEl.classList.remove('checkout-delivery-estimate--error');
                }
                return;
            }
            const my = ++seq;
            setLoading(true);
            if (feedbackEl) feedbackEl.classList.remove('checkout-delivery-estimate--error');

            fetch(estimateUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ address: addr, order_total: panierTotal })
            })
                .then(function (res) {
                    return res.json().then(function (data) {
                        return { httpOk: res.ok, data: data };
                    });
                })
                .then(function (wrapped) {
                    // si une requete plus recente est partie entre temps, on ignore cette reponse
                    if (my !== seq) return;
                    setLoading(false);
                    const data = wrapped.data || {};
                    if (!wrapped.httpOk || !data.ok) {
                        feesEl.textContent = '—';
                        feesEl.classList.remove('checkout-sum__value--free');
                        if (totalEl) totalEl.textContent = formatEuro(panierTotal);
                        updatePayAmount(panierTotal);
                        if (hintEl) hintEl.textContent = 'Estimation indisponible pour cette saisie.';
                        if (feedbackEl) {
                            feedbackEl.textContent = (data && data.error) ? String(data.error) : 'Erreur lors du calcul.';
                            feedbackEl.classList.add('checkout-delivery-estimate--error');
                        }
                        return;
                    }
                    // le cout vient du serveur, on calcule le total panier plus livraison
                    let cost = typeof data.cost === 'number' ? data.cost : parseFloat(String(data.cost || '0').replace(',', '.'));
                    if (isNaN(cost)) cost = 0;
                    const total = panierTotal + cost;
                    // livraison gratuite ou pas, on l'affiche en consequence
                    if (data.free_delivery || cost <= 0.0001) {
                        feesEl.textContent = 'Gratuit';
                        feesEl.classList.add('checkout-sum__value--free');
                    } else {
                        feesEl.textContent = formatEuro(cost);
                        feesEl.classList.remove('checkout-sum__value--free');
                    }
                    if (totalEl) totalEl.textContent = formatEuro(total);
                    updatePayAmount(total);
                    const bits = [];
                    if (data.distance_text) bits.push(String(data.distance_text));
                    if (data.duration_text) bits.push('≈ ' + String(data.duration_text));
                    if (hintEl) hintEl.textContent = bits.length ? bits.join(' · ') : 'Livraison estimée.';
                    if (feedbackEl) {
                        feedbackEl.textContent = data.client_address ? String(data.client_address) : '';
                        feedbackEl.classList.remove('checkout-delivery-estimate--error');
                    }
                })
                .catch(function () {
                    // ici on arrive si le reseau lache : on remet le total sans livraison et on previent
                    if (my !== seq) return;
                    setLoading(false);
                    feesEl.textContent = '—';
                    feesEl.classList.remove('checkout-sum__value--free');
                    if (totalEl) totalEl.textContent = formatEuro(panierTotal);
                    updatePayAmount(panierTotal);
                    if (hintEl) hintEl.textContent = 'Estimation indisponible.';
                    if (feedbackEl) {
                        feedbackEl.textContent = 'Réseau indisponible. Réessayez dans un instant.';
                        feedbackEl.classList.add('checkout-delivery-estimate--error');
                    }
                });
        }

        // a chaque modif des champs adresse on relance l'estimation, avec le petit delai du debounce
        const debounced = debounce(refreshEstimate, 550);
        ['adresse', 'code_postal', 'ville'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('input', debounced);
        });

        // case "ignorer la verification d'adresse" : on bascule entre forfait fixe et estimation Google
        if (skipEl) {
            skipEl.addEventListener('change', function () {
                skipMode = skipEl.checked;
                refreshEstimate();
            });
            // au cas ou le navigateur restaure la case cochee (retour arriere), on applique le forfait tout de suite
            if (skipMode) applyFallback();
        }
    });
  </script>
  <script defer src="../js/menu-toggle.js"></script>
</head>
<body class="checkout-page">
  <div class="pcnavbar">
    <nav class="navbar">
      <div class="navbar-inner">
        <a href="#" class="navbar-logo">
          <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
        </a>

        <ul class="navbar-links">
          <li><a href="#menu">Populaires</a></li>
          <li><a href="#features">Restaurant</a></li>
          <li><a href="#order">Commander</a></li>
          <li><a href="<?= $BASE ?>/Carte/">Carte</a></li>
          <?php if ($est_connecte && $role_actuel === "livreur"): ?>
            <li><a href="<?= $BASE ?>/Livraison/">Livraison</a></li>
          <?php endif; ?>
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "colab.")): ?>
            <li><a href="../CYF/">CYF</a></li>
          <?php else: ?>
            <li><a href="#Le lien ne fonctionne pas puisque vous n'êtes pas identifie">CYF</li>
          <?php endif; ?>  
          <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
            <li><a href="<?= $BASE ?>/Admin/">Admin</a></li>
          <?php endif; ?>
          <?php if (
              $est_connecte &&
              ($role_actuel === "admin" || $role_actuel === "chef" || $role_actuel === "fakeadmin")
          ): ?>
            <li><a href="<?= $BASE ?>/Cuisinier/">Cuisine</a></li>
          <?php endif; ?>
        </ul>

        <div class="navbar-auth">
          <?php if ($est_connecte): ?>
            <a href="<?= $BASE ?>/Profil/" style="text-decoration:none">
              <span class="nav-user">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                  <path
                    d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
                </svg>
                <?php echo htmlspecialchars($nom_affiche); ?>
              </span>
            </a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="logout" value="1">
              <button type="submit" class="btn-nav">D&eacute;connexion</button>
            </form>
          <?php else: ?>
            <a href="<?= $BASE ?>/LOG/login" class="btn-nav">Connexion</a>
            <a href="<?= $BASE ?>/LOG/signup" class="btn-nav btn-nav-primary">S'inscrire</a>
          <?php endif; ?>
        </div>
      </div>
    </nav>
  </div>

  <!--  Pour mobile  -->
  <div class="mobilenavbar">
    <nav class="navbar">
      <div class="navbar-inner">
        <button class="menu-btn" type="button" aria-label="Menu" aria-expanded="false">&#9776;</button>
        <a href="#" class="navbar-logo">
          <span class="logo-cy">CY</span><span class="logo-rest"> RESTAURANT</span>
        </a>    
      </div>
    </nav>
  </div>

  <!-- latebar menu (inchangé) -->
  <div id="mainMenuContainer">
    <div class="mainMenu">
      <div class="mainMenuItem" id="mainMenu">
        <span class="mainMenuItemCollapsable">
          <img src="../images/logo_smc_blanc.png" alt="Menu item">
        </span>
        <span class="mainMenuItemCollapsable">Cy Restaurant</span>
      </div>

      <?php if (!$est_connecte): ?>
        <nav id="menuNav">
          <div class="mainMenuItemLogin">
            <a href="<?= $BASE ?>/LOG/login">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Connexion</div>
            </a>
          </div>

          <div class="mainMenuItemSignIn">
            <a href="<?= $BASE ?>/LOG/signup">
              <span class="mainMenuItemCollapsable">
                <img src="../images/rechercher.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Inscription</div>
            </a>
          </div>
        <?php endif; ?>

        <div class="mainMenuItemLogin">
          <a href="#menu">
            <span class="mainMenuItemCollapsable">
              <img src="../images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">La Carte</div>
          </a>
        </div>

        <div class="mainMenuItemLogin">
          <a href="#features">
            <span class="mainMenuItemCollapsable">
              <img src="../images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">Le Restaurant</div>
          </a>
        </div>

        <div class="mainMenuItemLogin">
          <a href="<?= $BASE ?>/Carte/">
            <span class="mainMenuItemCollapsable">
              <img src="../images/accueil.png" alt="Menu item">
            </span>
            <div class="mainMenuItemCollapsable">Menu complet</div>
          </a>
        </div>

        <?php if ($est_connecte && ($role_actuel === "admin" || $role_actuel === "fakeadmin")): ?>
          <div class="mainMenuItemLogin">
            <a href="<?= $BASE ?>/Admin/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel Admin</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte && $role_actuel === "livreur"): ?>
          <div class="mainMenuItemLogin">
            <a href="<?= $BASE ?>/Livraison/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Pannel livreur</div>
            </a>
          </div>
        <?php endif; ?>


        <?php if (
            $est_connecte &&
            ($role_actuel === "admin" || $role_actuel === "chef" || $role_actuel === "fakeadmin")
        ): ?>
          <div class="mainMenuItemLogin">
            <a href="<?= $BASE ?>/Cuisinier/">
              <span class="mainMenuItemCollapsable">
                <img src="../images/accueil.png" alt="Menu item">
              </span>
              <div class="mainMenuItemCollapsable">Accès cuisine</div>
            </a>
          </div>
        <?php endif; ?>

        <?php if ($est_connecte): ?>
          <div class="mainMenuItem">
            <a href="#&action=logout">
              <span class="mainMenuItemCollapsable">
                <img src="../images/d&eacute;connecter.png" alt="Menu item">
              </span>
              <form method="POST">
                <button type="submit">
                  <input type="hidden" name="logout" value="1">
                  <div class="mainMenuItemCollapsable">D&eacute;connexion</div>
                </button>
              </form>
            </a>
          </div>
        <?php endif; ?>
      </nav>
    </div>
  </div>

    <div class="checkout-shell">
        <div class="checkout-frame">
            <div class="checkout-frame__corners" aria-hidden="true"></div>
            <div class="checkout-frame__inner">
                <header class="checkout-frame__head">
                    <div class="checkout-frame__brand">CY Restaurant · Paiement</div>
                    <h1><?php echo $bool_paiement_traite ? "R&eacute;sultat" : "Finaliser la commande"; ?></h1>
                    <p><?php echo $bool_paiement_traite
                        ? "Votre transaction a &eacute;t&eacute; trait&eacute;e. Retrouvez le d&eacute;tail ci-dessous."
                        : "V&eacute;rifiez votre panier, choisissez l&rsquo;horaire et le mode de r&egrave;glement."; ?></p>
                </header>

                <div class="checkout-frame__body">
        <?php
        $show_error_alert =
            !empty($message) &&
            !$bool_paiement_traite &&
            (strpos((string) $message, "succès") === false && stripos((string) $message, "déjà enregistrée") === false);
        $show_success_alert =
            !empty($message) &&
            !$bool_paiement_traite &&
            (strpos((string) $message, "succès") !== false || stripos((string) $message, "déjà enregistrée") !== false);
        ?>
        <?php if ($show_error_alert): ?>
            <div class="checkout-alert checkout-alert--error" role="alert"><?php echo htmlspecialchars(
                $message,
            ); ?></div>
        <?php elseif ($show_success_alert): ?>
            <div class="checkout-alert checkout-alert--success" role="status"><?php echo htmlspecialchars(
                $message,
            ); ?></div>
        <?php endif; ?>

        <?php if (!$bool_paiement_traite): ?>
        <?php
        $panier_total_display = round((float) $panier_total, 2);
        // Affichage initial : frais mis à jour en AJAX ; total = panier jusqu'à estimation.
        $total_a_payer_display = $panier_total_display;
        ?>
        <main>
            <div class="checkout-block">
                <div class="checkout-block__title">
                    <span>R&eacute;capitulatif</span>
                    <?php if (!empty($panier_items)): ?>
                        <span class="checkout-badge"><?php echo (int) $panier_count; ?> article<?php echo $panier_count >
 1
     ? "s"
     : ""; ?></span>
                    <?php endif; ?>
                </div>
                <div class="checkout-block__body">
                    <?php if (empty($panier_items)): ?>
                        <div class="checkout-empty">
                            <p>Votre panier est vide.</p>
                            <p><a href="<?= $BASE ?>/Carte/">Retour &agrave; la carte</a></p>
                        </div>
                    <?php else: ?>
                        <ul class="checkout-lines">
                            <?php foreach ($panier_items as $item): ?>
                                <li class="checkout-line">
                                    <div class="checkout-line__name">
                                        <?php echo htmlspecialchars($item["name"]); ?>
                                        <div class="checkout-line__meta">Quantit&eacute; &times;<?php echo (int) $item[
                                            "qty"
                                        ]; ?></div>
                                    </div>
                                    <div class="checkout-line__price"><?php echo number_format(
                                        $item["subtotal"],
                                        2,
                                        ",",
                                        " ",
                                    ); ?> &euro;</div>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                        <div class="checkout-sum" aria-label="D&eacute;tail des montants">
                            <div class="checkout-sum__row">
                                <span class="checkout-sum__label">Sous-total <span class="checkout-sum__sublabel">articles</span></span>
                                <span class="checkout-sum__value"><?php echo number_format(
                                    $panier_total,
                                    2,
                                    ",",
                                    " ",
                                ); ?> &euro;</span>
                            </div>
                            <div class="checkout-sum__row checkout-sum__row--delivery">
                                <span class="checkout-sum__label">
                                    <span class="checkout-sum__delivery-title">
                                        <svg class="checkout-sum__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M3 7h11v10H3V7zm11 5h4l3 3v2h-3" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                                            <circle cx="7.5" cy="18" r="2" stroke="currentColor" stroke-width="1.6"/>
                                            <circle cx="17" cy="18" r="2" stroke="currentColor" stroke-width="1.6"/>
                                        </svg>
                                        Frais de livraison
                                    </span>
                                    <span class="checkout-sum__hint" id="checkout-delivery-hint">Saisissez rue, code postal et ville.</span>
                                <div class="checkout-delivery-tarif">
                                    <span class="cl">1,50€ fixe</span>
                                    <span class="cl">+0,80€/km</span>
                                    <span class="cl">max 20km</span>
                                </div>
                                </span>
                                <span class="checkout-sum__value checkout-sum__value--fees" id="checkout-sum-fees" aria-live="polite"></span>
                            </div>
                            <div class="checkout-total">
                                <span>Total &agrave; payer</span>
                                <span id="checkout-sum-total"><?php echo number_format(
                                    $total_a_payer_display,
                                    2,
                                    ",",
                                    " ",
                                ); ?> &euro;</span>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>

            <form class="checkout-form" method="POST" action="index.php" data-panier-total="<?php echo htmlspecialchars(
                (string) $panier_total_display,
                ENT_QUOTES,
                "UTF-8",
            ); ?>" data-estimate-url="api_delivery_estimate.php" data-fallback-fee="<?php echo htmlspecialchars(
    (string) DELIVERY_PRICING["fallback_fee"],
    ENT_QUOTES,
    "UTF-8",
); ?>">
                <div class="checkout-section">
                    <div class="checkout-section__title">Horaire de livraison</div>
                    <div class="checkout-panel">
                        <label class="checkout-option">
                            <input type="radio" name="timing" value="immediate" id="timing_immediate" checked onclick="toggleTime(false)">
                            <span class="checkout-option__text">
                                <strong>Dès que possible</strong>
                                <small>Livraison dès que votre commande est prête</small>
                            </span>
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="timing" value="later" id="timing_later" onclick="toggleTime(true)">
                            <span class="checkout-option__text">
                                <strong>Programmer</strong>
                                <small>Choisissez une heure entre 11h et 23h</small>
                            </span>
                        </label>
                        <div id="time_picker" class="checkout-time-block" aria-hidden="true">
                            <label for="delivery_time">Heure souhaitée</label>
                            <input type="time" name="delivery_time" id="delivery_time" min="11:00" max="23:00">
                        </div>
                    </div>
                </div>
                <div class="checkout-section">
                    <div class="checkout-section__title">Livraison</div>
                    <div class="checkout-fields">
                        <div class="checkout-field checkout-field--full">
                            <label for="adresse">Adresse</label>
                            <input type="text" name="adresse" id="adresse" placeholder="Rue et num&eacute;ro" autocomplete="street-address">
                        </div>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="code_postal">Code postal</label>
                                <input type="text" name="code_postal" id="code_postal" placeholder="Ex. 95000" inputmode="numeric" autocomplete="postal-code">
                            </div>
                            <div class="checkout-field checkout-field--grow">
                                <label for="ville">Ville</label>
                                <input type="text" name="ville" id="ville" placeholder="Ville" autocomplete="address-level2">
                            </div>
                        </div>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="telephone">T&eacute;l&eacute;phone</label>
                                <input type="tel" name="telephone" id="telephone" placeholder="06 12 34 56 78" autocomplete="tel">
                            </div>
                        </div>
                        <p class="checkout-delivery-estimate" id="delivery_estimate_feedback" role="status" aria-live="polite"></p>
                        <div class="checkout-field-row">
                            <div class="checkout-field">
                                <label for="nom">Nom</label>
                                <input type="text" name="nom" id="nom" placeholder="Nom" autocomplete="family-name">
                            </div>
                            <div class="checkout-field checkout-field--grow">
                                <label for="prenom">Pr&eacute;nom</label>
                                <input type="text" name="prenom" id="prenom" placeholder="Pr&eacute;nom" autocomplete="given-name">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="checkout-section">
                    <div class="checkout-section__title">Mode de paiement</div>
                    <div class="checkout-panel">
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="1" id="paiement_cb" required>
                            <span class="checkout-option__text">
                                <strong>Carte bancaire</strong>
                                <small>Paiement sécurisé via CYBank</small>
                            </span>
                            <img src="<?= $BASE ?>/CYBank/CYBANKlogo.png" alt="CYBank" class="payment-logo">
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="2" id="paiement_paypal">
                            <span class="checkout-option__text">
                                <strong>PayPal</strong>
                                <small>Règlement avec votre compte PayPal</small>
                            </span>
                            <img src="<?= $BASE ?>/CYBank/PayPalpng.png" alt="PayPal" class="payment-logo">
                        </label>
                        <label class="checkout-option">
                            <input type="radio" name="paiement" value="3" id="paiement_gpay">
                            <span class="checkout-option__text">
                                <strong>Google Pay</strong>
                                <small>Paiement rapide sur appareil compatible</small>
                            </span>
                            <img src="<?= $BASE ?>/CYBank/googlepaypng.png" alt="Google Pay" class="payment-logo">
                        </label>
                    </div>
                </div>

                <label class="checkout-cgv checkout-skip-delivery" for="skip_delivery_check">
                    <input type="checkbox" name="skip_delivery_check" id="skip_delivery_check" value="1">
                    <span>
                        <strong>Ignorer la v&eacute;rification d&rsquo;adresse</strong> (calcul d&rsquo;itin&eacute;raire Google indisponible).
                        Un forfait de livraison fixe de <?php echo number_format(
                            (float) DELIVERY_PRICING["fallback_fee"],
                            2,
                            ",",
                            " ",
                        ); ?>&nbsp;&euro; sera appliqu&eacute;.
                    </span>
                </label>

                <label class="checkout-cgv" for="cb_paiement">
                    <input type="checkbox" name="cb_paiement" id="cb_paiement" value="1" required>
                    <span>J&rsquo;accepte les conditions g&eacute;n&eacute;rales de vente</span>
                </label>

                <button type="submit" name="payer" value="1" class="btn-checkout" id="checkout_pay_btn" <?php echo empty(
                    $panier_items
                )
                    ? "disabled"
                    : ""; ?>>
                    Payer <span id="checkout_pay_amount"><?php echo empty($panier_items)
                        ? ""
                        : number_format($total_a_payer_display, 2, ",", " ") . " &euro;"; ?></span>
                </button>
            </form>
        <?php else: ?>
            <div class="checkout-success-block">
                <?php $is_ok =
                    isset($message) &&
                    (stripos((string) $message, "Erreur") === false &&
                        stripos((string) $message, "impossible") === false &&
                        stripos((string) $message, "vide") === false); ?>
                <div class="checkout-alert <?php echo $is_ok
                    ? "checkout-alert--success"
                    : "checkout-alert--error"; ?>" role="<?php echo $is_ok ? "status" : "alert"; ?>">
                    <?php echo htmlspecialchars($message ?? ""); ?>
                </div>
                <?php if ($is_ok): ?>
                <?php $last_cid = isset($_SESSION["last_commande_id"]) ? (int) $_SESSION["last_commande_id"] : 0; ?>
                <div class="checkout-waiting-game">
                    <p class="checkout-waiting-game__text">En attendant votre livraison, nous vous proposons notre jeu en ligne&nbsp;!</p>
                    <p class="checkout-waiting-game__text">Vous pouvez suivre votre commande en cliquant sur le bouton ci-dessous. Vous pourrez ajouter des articles à votre commande tant que la préparation de votre commande n'a pas commencé!</p>
                    <div class="checkout-waiting-game__btns">
                        <a class="checkout-waiting-game__btn" href="/ProjetCYJ/CYF/" target="_blank">
                            🎮 CY Fighters
                        </a>
                        <?php if ($last_cid > 0): ?>
                        <a class="checkout-waiting-game__btn checkout-waiting-game__btn--outline" href="<?= $BASE ?>/Profil/Detail.php?id=<?php echo $last_cid; ?>">
                            📦 Suivre ma commande
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <a class="checkout-back" href="<?= $BASE ?>/Carte/">Retour &agrave; la carte</a>
            </div>
        <?php endif; ?>
                </div>
            </div>
        </div>
    </div>


</body>
</html>
