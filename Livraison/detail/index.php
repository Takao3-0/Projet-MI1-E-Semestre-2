<?php
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
);
require_once "../../../../protection.php";
$pdo_etudiant = $pdo;
require_once "../../../../../db_config_yumland.php";
$pdo_commandes = $pdo;

const LIVREUR_ID_COL = "`livreur_ID`";
const FRAIS_GESTION = 1.0;

if ($est_connecte && $role_actuel === "livreur") {
    function livraison_detail_resolve_livreur_id(PDO $pdo_etudiant, string $username): ?int
    {
        $stmt = $pdo_etudiant->prepare("SELECT id FROM users WHERE username = ? LIMIT 1");
        $stmt->execute([$username]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? (int) $row["id"] : null;
    }

    $livreur_user_id = !empty($_SESSION["nom_utilisateur"])
        ? livraison_detail_resolve_livreur_id($pdo_etudiant, (string) $_SESSION["nom_utilisateur"])
        : null;

    if (isset($_GET["id"])) {
        $cid = (int) $_GET["id"];
    } else {
        $cid = 0;
    }

    if ($cid <= 0) {
        header("Location: ../index.php");
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["confirm_livraison"])) {
        if ($livreur_user_id !== null) {
            $sql =
                'UPDATE commandes SET statut_production = \'Livré\'
                WHERE id = ? AND statut = \'Payé\' AND statut_production = \'Récupérée livreur\'
                  AND ' .
                LIVREUR_ID_COL .
                " = ?";
            $stmt = $pdo_commandes->prepare($sql);
            $stmt->execute([$cid, $livreur_user_id]);
        }
        header("Location: ../index.php");
        exit();
    }

    if ($livreur_user_id === null) {
        header("Location: ../index.php?err=no_user");
        exit();
    }

    $sql =
        'SELECT * FROM commandes WHERE id = ? AND statut = \'Payé\'
        AND statut_production = \'Récupérée livreur\' AND ' .
        LIVREUR_ID_COL .
        " = ?";
    $stmt = $pdo_commandes->prepare($sql);
    $stmt->execute([$cid, $livreur_user_id]);
    $cmd = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cmd) {
        header("Location: ../index.php");
        exit();
    }

    $cid = (int) $cmd["id"];

    $h = static function ($v) {
        if ($v === null || $v === "") {
            return "";
        }
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, "UTF-8");
    };

    $prenom = $h($cmd["prenom"] ?? "");
    $nom = $h($cmd["nom"] ?? "");
    $telephone = $h($cmd["telephone"] ?? "");
    $email = $h($cmd["email"] ?? "");
    $adresse = $h($cmd["adresse"] ?? "");
    $code_postal = $h($cmd["code_postal"] ?? "");
    $ville = $h($cmd["ville"] ?? "");
    $pays = $h($cmd["pays"] ?? "");
    $heure = $h($cmd["heure_livraison"] ?? "");

    $date_cmd_brute = $cmd["date_commande"] ?? "";
    $date_cmd = "";
    if ($date_cmd_brute !== "" && $date_cmd_brute !== null) {
        $ts = strtotime((string) $date_cmd_brute);
        $date_cmd = $ts ? date("d/m/Y \à H:i", $ts) : $h($date_cmd_brute);
    }

    $nb_articles = isset($cmd["nb_articles"]) ? (int) $cmd["nb_articles"] : 0;
    $total_fmt = isset($cmd["total"]) ? number_format((float) $cmd["total"], 2, ",", " ") : "";
    $frais_liv = (float) ($cmd["frais_livraison"] ?? 0.0);
    $gains_nets = max(0.0, $frais_liv - FRAIS_GESTION);

    $stmtItems = $pdo_commandes->prepare(
        "SELECT nom, quantite, prix FROM commande_items WHERE commande_id = ? ORDER BY id ASC",
    );
    $stmtItems->execute([$cid]);
    $lignes_commande = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

    $nom_complet = trim($prenom . " " . $nom);
    $ligne_cp_ville = trim(($code_postal !== "" ? $code_postal . " " : "") . $ville);

    $full_address = trim(
        implode(
            ", ",
            array_filter([
                (string) ($cmd["adresse"] ?? ""),
                (string) ($cmd["code_postal"] ?? ""),
                (string) ($cmd["ville"] ?? ""),
                (string) ($cmd["pays"] ?? ""),
            ]),
        ),
    );
    $full_address_js = json_encode($full_address);
} else {
    header("Location: " . $BASE . "/index.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../../index.css">
    <link rel="stylesheet" href="../../accueil.css">
    <link rel="stylesheet" href="../livraison.css">
    <link rel="stylesheet" href="detail.css">
    <?php if ($full_address !== ""): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <script defer src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
    // place dans le head (consigne : pas de script hors du head)
    // leaflet est charge en defer juste au dessus, donc pret avant DOMContentLoaded ; on attend que la page soit chargee
    document.addEventListener('DOMContentLoaded', function () {
    const addr = <?php echo $full_address_js; ?>;
    const enc  = encodeURIComponent(addr);

    // Restaurant fixe : 55 rue du Faubourg Saint-Honoré, Paris 75008
    const RESTO = { lat: 48.8696, lon: 2.3163 };

    document.getElementById('btn-gmaps').href = 'https://www.google.com/maps/search/?api=1&query=' + enc;
    document.getElementById('btn-waze').href  = 'https://waze.com/ul?q=' + enc;

    const map = L.map('livraison-map').setView([RESTO.lat, RESTO.lon], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    // Marqueur restaurant (orange)
    const iconResto = L.divIcon({ html: '<div class="map-pin map-pin--resto">🍽</div>', className: '', iconSize: [32, 32], iconAnchor: [16, 32] });
    L.marker([RESTO.lat, RESTO.lon], { icon: iconResto }).addTo(map)
        .bindPopup('<strong>Restaurant</strong><br>55 rue du Faubourg Saint-Honoré');

    let clientCoords  = null;
    let livreurCoords = null;
    let markerLivreur = null;
    let legA = null; // livreur → resto  (bleu)
    let legB = null; // resto  → client  (orange)

    function fetchRoute(from, to, color) {
        const url = 'https://router.project-osrm.org/route/v1/driving/'
            + from.lon + ',' + from.lat + ';'
            + to.lon   + ',' + to.lat
            + '?overview=full&geometries=geojson';
        return fetch(url)
            .then(r => r.json())
            .then(data => {
                if (!data.routes || !data.routes[0]) return null;
                const pts = data.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
                return L.polyline(pts, { color, weight: 5, opacity: 0.85 });
            });
    }

    function updateRoute() {
        if (!clientCoords || !livreurCoords) return;
        if (legA) { map.removeLayer(legA); legA = null; }
        if (legB) { map.removeLayer(legB); legB = null; }

        Promise.all([
            fetchRoute(livreurCoords, RESTO,        '#4285f4'), // bleu
            fetchRoute(RESTO,        clientCoords,  '#ff7d29')  // orange
        ]).then(([a, b]) => {
            if (a) { legA = a; a.addTo(map); }
            if (b) { legB = b; b.addTo(map); }
            map.fitBounds(L.latLngBounds([
                [livreurCoords.lat, livreurCoords.lon],
                [RESTO.lat, RESTO.lon],
                [clientCoords.lat, clientCoords.lon]
            ]).pad(0.15));
        });
    }

    // Géocodage adresse client
    fetch('https://nominatim.openstreetmap.org/search?q=' + enc + '&format=json&limit=1', {
        headers: { 'Accept-Language': 'fr' }
    })
    .then(r => r.json())
    .then(data => {
        if (!data.length) {
            document.getElementById('livraison-map').hidden = true;
            document.getElementById('livraison-map-err').hidden = false;
            return;
        }
        clientCoords = { lat: parseFloat(data[0].lat), lon: parseFloat(data[0].lon) };
        const iconClient = L.divIcon({ html: '<div class="map-pin map-pin--client">📦</div>', className: '', iconSize: [32, 32], iconAnchor: [16, 32] });
        L.marker([clientCoords.lat, clientCoords.lon], { icon: iconClient })
            .addTo(map).bindPopup('<strong>Livraison</strong><br>' + addr);
        updateRoute();
    })
    .catch(() => {
        document.getElementById('livraison-map').hidden = true;
        document.getElementById('livraison-map-err').hidden = false;
    });

    // Position GPS du livreur (temps réel)
    if ('geolocation' in navigator) {
        const opts = { enableHighAccuracy: true, maximumAge: 10000 };
        navigator.geolocation.getCurrentPosition(pos => {
            livreurCoords = { lat: pos.coords.latitude, lon: pos.coords.longitude };
            const iconLivreur = L.divIcon({ html: '<div class="map-pin map-pin--livreur">🛵</div>', className: '', iconSize: [32, 32], iconAnchor: [16, 32] });
            markerLivreur = L.marker([livreurCoords.lat, livreurCoords.lon], { icon: iconLivreur })
                .addTo(map).bindPopup('Votre position');
            updateRoute();

            // Mise à jour en temps réel
            navigator.geolocation.watchPosition(pos => {
                livreurCoords = { lat: pos.coords.latitude, lon: pos.coords.longitude };
                markerLivreur.setLatLng([livreurCoords.lat, livreurCoords.lon]);
                updateRoute();
            }, null, opts);
        }, null, opts);
    }
    });
    </script>
    <?php endif; ?>
    <title>Détail commande #<?php echo $cid; ?></title>
</head>
<body class="livraison-page livraison-detail">

    <main class="livraison-main">
        <header class="livraison-top">
            <a href="../index.php" class="livraison-back">&larr; Retour aux livraisons</a>
            <h1>Commande #<?php echo $cid; ?></h1>
            <span class="livraison-statut">En livraison</span>
        </header>

        <article class="livraison-card">
            <div class="livraison-detail-body">
                <section class="livraison-detail-section" aria-labelledby="detail-client">
                    <h2 id="detail-client" class="livraison-detail-section__title">Client</h2>
                    <div class="livraison-detail-lines">
                        <p><strong>Nom</strong> <span><?php echo $nom_complet !== "" ? $nom_complet : "—"; ?></span></p>
                        <p><strong>T&eacute;l&eacute;phone</strong> <span><?php echo $telephone !== ""
                            ? $telephone
                            : "—"; ?></span></p>
                        <p><strong>E-mail</strong> <span><?php echo $email !== "" ? $email : "—"; ?></span></p>
                    </div>
                </section>

                <section class="livraison-detail-section" aria-labelledby="detail-adresse">
                    <h2 id="detail-adresse" class="livraison-detail-section__title">Adresse de livraison</h2>
                    <div class="livraison-detail-lines livraison-detail-lines--address">
                        <?php if ($adresse !== ""): ?>
                            <p class="livraison-detail-address-line"><?php echo $adresse; ?></p>
                        <?php endif; ?>
                        <?php if ($ligne_cp_ville !== ""): ?>
                            <p class="livraison-detail-address-line"><?php echo $ligne_cp_ville; ?></p>
                        <?php endif; ?>
                        <?php if ($pays !== ""): ?>
                            <p class="livraison-detail-address-line"><?php echo $pays; ?></p>
                        <?php endif; ?>
                        <?php if ($adresse === "" && $ligne_cp_ville === "" && $pays === ""): ?>
                            <p class="livraison-detail-empty">Aucune adresse enregistr&eacute;e pour cette commande.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <?php if ($full_address !== ""): ?>
                <section class="livraison-detail-section" aria-labelledby="detail-map">
                    <h2 id="detail-map" class="livraison-detail-section__title">Navigation</h2>
                    <div id="livraison-map" class="livraison-map-container"></div>
                    <div class="livraison-nav-btns">
                        <a id="btn-gmaps" class="livraison-nav-btn livraison-nav-btn--gmaps" href="#" target="_blank" rel="noopener noreferrer">Google Maps</a>
                        <a id="btn-waze" class="livraison-nav-btn livraison-nav-btn--waze" href="#" target="_blank" rel="noopener noreferrer">Waze</a>
                    </div>
                    <p id="livraison-map-err" class="livraison-map-err" hidden>Adresse introuvable sur la carte.</p>
                </section>
                <?php endif; ?>

                <?php if (!empty($lignes_commande)): ?>
                <section class="livraison-detail-section" aria-labelledby="detail-panier">
                    <h2 id="detail-panier" class="livraison-detail-section__title">Contenu</h2>
                    <ul class="livraison-detail-items">
                        <?php foreach ($lignes_commande as $ligne): ?>
                            <li class="livraison-detail-item">
                                <span class="livraison-detail-item__name"><?php echo htmlspecialchars(
                                    (string) ($ligne["nom"] ?? ""),
                                    ENT_QUOTES,
                                    "UTF-8",
                                ); ?></span>
                                <span class="livraison-detail-item__meta">
                                    &times;<?php echo (int) ($ligne["quantite"] ?? 0); ?>
                                    <?php if (isset($ligne["prix"])): ?>
                                        <span class="livraison-detail-item__price"><?php echo number_format(
                                            (float) $ligne["prix"],
                                            2,
                                            ",",
                                            " ",
                                        ); ?> &euro; / u.</span>
                                    <?php endif; ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
                <?php endif; ?>

                <section class="livraison-detail-section" aria-labelledby="detail-commande">
                    <h2 id="detail-commande" class="livraison-detail-section__title">Commande</h2>
                    <div class="livraison-detail-lines">
                        <p><strong>Pass&eacute;e le</strong> <span><?php echo $date_cmd !== ""
                            ? $date_cmd
                            : "—"; ?></span></p>
                        <p><strong>Cr&eacute;neau souhait&eacute;</strong> <span><?php echo $heure !== ""
                            ? $heure
                            : "D&egrave;s que possible"; ?></span></p>
                        <p><strong>Articles</strong> <span><?php echo $nb_articles > 0
                            ? (string) $nb_articles
                            : "—"; ?></span></p>
                    </div>
                    <div class="livraison-gains-bloc">
                        <div class="livraison-gains-row">
                            <span>Frais de livraison per&ccedil;us</span>
                            <span><?php echo number_format($frais_liv, 2, ",", " "); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--deduction">
                            <span>Frais de gestion (restaurant)</span>
                            <span>&minus;<?php echo number_format(FRAIS_GESTION, 2, ",", " "); ?> &euro;</span>
                        </div>
                        <div class="livraison-gains-row livraison-gains-row--total">
                            <span>Vos gains bruts</span>
                            <span><?php echo number_format($gains_nets, 2, ",", " "); ?> &euro;</span>
                        </div>
                    </div>
                </section>
            </div>

            <form method="post" action="index.php?id=<?php echo $cid; ?>" class="livraison-confirm-form">
                <input type="hidden" name="confirm_livraison" value="<?php echo $cid; ?>">
                <button type="submit" class="btn-confirm-livraison">Confirmer la livraison</button>
            </form>
        </article>
    </main>


</body>
</html>
