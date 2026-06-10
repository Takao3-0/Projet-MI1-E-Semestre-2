<?php
// chemin de base du site pour que les liens marchent partout
$BASE = preg_replace(
    '#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#',
    "",
    $_SERVER["SCRIPT_NAME"],
);
// protection.php demarre la session et nous donne $est_connecte, $role_actuel et $pdo
require_once "../../../protection.php";
// ici on n'a besoin que de la base des commandes (et des articles)
require_once "../../../../db_config_yumland.php";
$pdo_commandes = $pdo;
?>
<?php
function convertDate(
    $date, //On passe la date en français pour l'affichage puisque dans la database elle est en américain
) {
    $date = date("d-m-Y H:i:s", strtotime($date));
    return $date;
} /** Colonne commandes.livreur_ID (FK logique vers espace_etudiant.users.id) */
function cuisinier_livreur_id_assigned(array $commande): bool
{
    $v = $commande["livreur_ID"] ?? ($commande["livreur_id"] ?? null);
    if ($v === null || $v === "") {
        return false;
    }

    return (int) $v > 0;
} /**
 * Affiche les lignes commande_items : article seul ou menu (produit_id &lt; 0) avec composition.
 *
 * @param array<int, list<array{nom:string, description:string}>> $composition_by_menu_id  clé = id menu (menus.id), comme abs(produit_id)
 */ /**
 * Rend les cartes d'une colonne du kanban (nouvelles / en préparation / prêtes).
 * Utilisé à la fois pour le rendu initial de la page ET pour le endpoint
 * AJAX qui renvoie le fragment HTML toutes les 5s.
 */ // genere le html des cartes d'une colonne du kanban ; reutilisee pour l'affichage initial ET pour le rafraichissement
function cuisinier_render_kanban_cards($type, $commandes, $items, $composition_by_menu_id)
{
    switch ($type) {
        case "new":
            $card_class = "order-new";
            $btn_class = "order-btn-accept";
            $btn_name = "accept_commande";
            $btn_label = "Accepter";
            $empty_text = "Aucune commande nouvelle";
            $check_locked = true;
            $time_label = "Passé : ";
            break;
        case "progress":
            $card_class = "order-progress";
            $btn_class = "order-btn-ready";
            $btn_name = "ready_commande";
            $btn_label = "Prête";
            $empty_text = "Aucune commande en préparation";
            $check_locked = false;
            $time_label = "Passé : ";
            break;
        case "done":
            $card_class = "order-done";
            $btn_class = "";
            $btn_name = "";
            $btn_label = "";
            $empty_text = "Aucune commande prête";
            $check_locked = false;
            $time_label = "Prête depuis : ";
            $readonly_done = true;
            break;
        default:
            return;
    }
    $readonly_done = $readonly_done ?? false;
    if (empty($commandes)) {
        echo '<div class="kanban-cards-empty">' . htmlspecialchars($empty_text) . "</div>";
        return;
    }
    foreach ($commandes as $commande) {
        $en_attente_diff = false;
        $reste_a_payer = 0;
        if ($check_locked) {
            $reste_a_payer = max(0, (float) $commande["total"] - (float) ($commande["montant_paye"] ?? 0));
            $en_attente_diff = $reste_a_payer > 0;
        }
        $cid_attr = (int) $commande["id"];
        $card_classes = "order-card " . $card_class . ($en_attente_diff ? " order-locked" : "");
        echo '<article class="' . $card_classes . '" data-commande-id="' . $cid_attr . '">';
        if ($en_attente_diff) {
            echo '<span class="order-warn-paiement"';
            echo ' title="Le client a modifié sa commande après paiement et doit régler le différentiel avant que la cuisine ne puisse l\'accepter.">';
            echo "Différentiel à payer · " . number_format($reste_a_payer, 2, ",", " ") . " €";
            echo "</span>";
        }
        if ($type === "progress" && cuisinier_livreur_id_assigned($commande)) {
            echo '<span class="order-livreur-reserve" title="Un livreur a d&eacute;j&agrave; r&eacute;serv&eacute; cette commande et attend qu&rsquo;elle soit pr&ecirc;te">Livreur r&eacute;serv&eacute;</span>';
        }
        echo '<div class="order-top">';
        echo '<span class="order-id">#' . $cid_attr . "</span>";
        echo '<span class="order-time">' .
            htmlspecialchars($time_label) .
            convertDate($commande["date_commande"]) .
            "</span>";
        if (!empty($commande["heure_livraison"])) {
            echo '<br><span class="order-time" style="color:var(--orange); font-weight:bold;">Pour : ' .
                htmlspecialchars($commande["heure_livraison"]) .
                "</span>";
        }
        echo "</div>";
        echo '<ul class="order-items">';
        cuisinier_render_commande_items($items, $cid_attr, $composition_by_menu_id);
        echo "</ul>";
        $livreur_reserve = cuisinier_livreur_id_assigned($commande);
        echo '<div class="order-footer">';
        echo '<span class="order-total">' .
            htmlspecialchars((string) ($commande["total"] ?? ""), ENT_QUOTES, "UTF-8") .
            " &euro;</span>";
        if (!empty($readonly_done)) {
            if ($livreur_reserve) {
                echo '<span class="order-wait-livreur order-wait-livreur--assigned" title="Un livreur a r&eacute;serv&eacute; cette commande et viendra la r&eacute;cup&eacute;rer">Livreur en route</span>';
            } else {
                echo '<span class="order-wait-livreur" title="Un livreur doit prendre la commande depuis son espace">Attente livreur</span>';
            }
        } else {
            $action_key = $btn_name === "accept_commande" ? "accept" : "ready";
            echo '<button class="order-btn ' . htmlspecialchars($btn_class, ENT_QUOTES, "UTF-8") . '" type="button"';
            echo ' data-action="' . $action_key . '" data-id="' . $cid_attr . '"';
            if ($en_attente_diff) {
                echo ' disabled title="Le client doit payer le différentiel avant que vous ne puissiez accepter cette commande"';
            }
            echo ">" . htmlspecialchars($en_attente_diff ? "En attente paiement" : $btn_label) . "</button>";
        }
        echo "</div>";
        echo "</article>";
    }
}
function cuisinier_render_commande_items($items, $commande_id, $composition_by_menu_id)
{
    foreach ($items as $line) {
        // On ignore les lignes qui n'appartiennent pas à cette commande
        if ($line["commande_id"] != $commande_id) {
            continue;
        }
        $pid = $line["produit_id"];
        $qty = $line["quantite"];
        $nom = htmlspecialchars($line["nom"]); // C'est un menu (produit_id négatif)
        if ($pid < 0) {
            $menu_id = abs($pid);
            echo '<li class="order-line order-line--menu">';
            echo '<div class="order-line-menu-head">';
            echo '<span class="order-qty">' . $qty . "&times;</span> ";
            echo "<strong>" . $nom . "</strong> ";
            echo '<span class="order-tag">Menu</span>';
            echo "</div>"; // Le menu a une composition connue
            if (isset($composition_by_menu_id[$menu_id]) && $composition_by_menu_id[$menu_id] != []) {
                $parts = $composition_by_menu_id[$menu_id];
                echo '<ul class="order-menu-parts">';
                foreach ($parts as $p) {
                    $pn = htmlspecialchars($p["nom"]);
                    $pd = $p["description"];
                    echo "<li>";
                    echo '<span class="order-qty-sub">' . $qty . "&times;</span> " . $pn;
                    echo "</li>";
                }
                echo "</ul>"; // Le menu n'a pas de composition
            } else {
                echo '<p class="order-menu-empty">Composition du menu non renseignée (code_menu = ' .
                    $menu_id .
                    ").</p>";
            }
            echo "</li>"; // C'est un produit simple (produit_id positif)
        } else {
            echo '<li class="order-line">';
            echo '<span class="order-qty">' . $qty . "&times;</span> " . $nom;
            echo "</li>";
        }
    }
} // toute la cuisine n'est accessible qu'au chef (et admin) : verifie cote serveur, pas juste a l'affichage
if ($est_connecte && ($role_actuel === "chef" || $role_actuel === "fakeadmin" || $role_actuel === "admin")) {
    // on charge tous les articles, ranges par code, pour afficher le detail des menus dans les cartes
    $stmt = $pdo_commandes->prepare("SELECT * FROM articles");
    $stmt->execute();
    $articles_rows = $stmt->fetchAll();
    $articles_by_code = [];
    foreach ($articles_rows as $ar) {
        $r = array_change_key_case($ar, CASE_LOWER);
        $code = (int) ($r["code"] ?? ($r["id"] ?? 0));
        if ($code > 0) {
            $articles_by_code[$code] = [
                "nom" => (string) ($r["nom"] ?? ""),
                "description" => (string) ($r["description"] ?? ""),
            ];
        }
    } // la table composition_menu dit quels articles composent chaque menu ; on la range par code de menu
    $stmt = $pdo_commandes->prepare("SELECT * FROM composition_menu");
    $stmt->execute();
    $composition_menu_rows = $stmt->fetchAll();
    $composition_by_menu_id = [];
    foreach ($composition_menu_rows as $crow) {
        $cr = array_change_key_case($crow, CASE_LOWER);
        $menu_key = (int) ($cr["code_menu"] ?? 0);
        $article_code = (int) ($cr["code_article"] ?? 0);
        if ($menu_key <= 0 || $article_code <= 0) {
            continue;
        }
        $art = $articles_by_code[$article_code] ?? null;
        $nom_part = $art["nom"] ?? "Article #" . $article_code;
        $desc_part = $art["description"] ?? "";
        if (!isset($composition_by_menu_id[$menu_key])) {
            $composition_by_menu_id[$menu_key] = [];
        }
        $composition_by_menu_id[$menu_key][] = [
            "nom" => $nom_part,
            "description" => $desc_part,
        ];
    } //Partie Nouvelles
    //On recupère toutes les commandes en attente cuisine et payées.
    //Celles dont montant_paye < total (différentiel non payé par le client après modif)
    //seront affichées avec un bandeau rouge et un bouton Accepter désactivé.
    $stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
    $stmt->execute(["En attente cuisine"]);
    $commandes_order_new = $stmt->fetchAll(); //On fait le tableau des ids des commandes
    $ids_commandes = [];
    $count = [];
    foreach ($commandes_order_new as $commande) {
        $ids_commandes[] = $commande["id"];
        $count[] = "?";
    }
    $count = implode(",", $count); //On recup les articles des commandes
    if ($count != "") {
        //On recup les articles des commandes
        $stmt = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count)");
        $stmt->execute($ids_commandes);
        $commande_items = $stmt->fetchAll();
    } else {
        $commande_items = [];
    }
    //Partie En préparation
    $stmt1 = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
    $stmt1->execute(["En préparation"]);
    $commandes_order_progress = $stmt1->fetchAll(); //On fait le tableau des ids des commandes
    $ids_commandes_progress = [];
    $count_progress = [];
    foreach ($commandes_order_progress as $commande) {
        $ids_commandes_progress[] = $commande["id"];
        $count_progress[] = "?";
    }
    $count_progress = implode(",", $count_progress);
    if ($count_progress != "") {
        //On recup les articles des commandes
        $stmt2 = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count_progress)");
        $stmt2->execute($ids_commandes_progress);
        $commande_items_progress = $stmt2->fetchAll();
    } else {
        $commande_items_progress = [];
    } //Partie Prêtes
    $stmt3 = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
    $stmt3->execute(["Prête"]);
    $commandes_order_done = $stmt3->fetchAll(); //On fait le tableau des ids des commandes
    $ids_commandes_done = [];
    $count_done = [];
    foreach ($commandes_order_done as $commande) {
        $ids_commandes_done[] = $commande["id"];
        $count_done[] = "?";
    }
    $count_done = implode(",", $count_done);
    if ($count_done != "") {
        //On recup les articles des commandes
        $stmt4 = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count_done)");
        $stmt4->execute($ids_commandes_done);
        $commande_items_done = $stmt4->fetchAll();
    } else {
        $commande_items_done = [];
    } // Synthèse efficacité (pipeline + historique livreur)
    $sum_items_qty = static function (array $rows): int {
        $n = 0;
        foreach ($rows as $row) {
            $n += (int) ($row["quantite"] ?? 0);
        }
        return $n;
    };
    $stats_articles_pipeline =
        $sum_items_qty($commande_items) +
        $sum_items_qty($commande_items_progress) +
        $sum_items_qty($commande_items_done);
    $sum_commandes_total = static function (array $commandes): float {
        $s = 0.0;
        foreach ($commandes as $c) {
            $s += (float) str_replace(",", ".", (string) ($c["total"] ?? "0"));
        }
        return $s;
    };
    $stats_ca_pipeline =
        $sum_commandes_total($commandes_order_new) +
        $sum_commandes_total($commandes_order_progress) +
        $sum_commandes_total($commandes_order_done);
    $stats_pipeline_total =
        count($commandes_order_new) + count($commandes_order_progress) + count($commandes_order_done);
    $stats_pct_new =
        $stats_pipeline_total > 0 ? (int) round((100 * count($commandes_order_new)) / $stats_pipeline_total) : 0;
    $stats_pct_progress =
        $stats_pipeline_total > 0 ? (int) round((100 * count($commandes_order_progress)) / $stats_pipeline_total) : 0;
    $stats_pct_done = $stats_pipeline_total > 0 ? max(0, 100 - $stats_pct_new - $stats_pct_progress) : 0;
    $stats_max_wait_new_min = null;
    foreach ($commandes_order_new as $c) {
        $ts = strtotime($c["date_commande"] ?? "");
        if ($ts) {
            $mins = (time() - $ts) / 60;
            if ($stats_max_wait_new_min === null || $mins > $stats_max_wait_new_min) {
                $stats_max_wait_new_min = $mins;
            }
        }
    }
    $stats_max_wait_progress_min = null;
    foreach ($commandes_order_progress as $c) {
        $ts = strtotime($c["date_commande"] ?? "");
        if ($ts) {
            $mins = (time() - $ts) / 60;
            if ($stats_max_wait_progress_min === null || $mins > $stats_max_wait_progress_min) {
                $stats_max_wait_progress_min = $mins;
            }
        }
    }
    $stmt_stats = $pdo_commandes->query(
        "SELECT COUNT(*) FROM commandes WHERE statut_production = 'Récupérée livreur' AND DATE(date_commande) = CURDATE()",
    );
    $stats_delivered_today = $stmt_stats ? (int) $stmt_stats->fetchColumn() : 0;
    $stmt_stats_w = $pdo_commandes->query(
        "SELECT COUNT(*) FROM commandes WHERE statut_production = 'Récupérée livreur' AND date_commande >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)",
    );
    $stats_delivered_week = $stmt_stats_w ? (int) $stmt_stats_w->fetchColumn() : 0;
    if ($stats_max_wait_new_min === null) {
        $stats_flow_label = "Aucune commande en file « nouvelles »";
        $stats_flow_class = "cook-synth-flow--ok";
    } elseif ($stats_max_wait_new_min > 30) {
        $stats_flow_label = "Attention : une commande attend plus de 30 min en file nouvelle";
        $stats_flow_class = "cook-synth-flow--alert";
    } elseif ($stats_max_wait_new_min > 15) {
        $stats_flow_label = "File nouvelle un peu chargée (max ~" . (int) round($stats_max_wait_new_min) . " min)";
        $stats_flow_class = "cook-synth-flow--warn";
    } else {
        $stats_flow_label =
            "File nouvelle sous contrôle (attente max ~" . (int) round($stats_max_wait_new_min) . " min)";
        $stats_flow_class = "cook-synth-flow--ok";
    }
    if ($stats_max_wait_progress_min === null) {
        $stats_progress_flow_label = "Aucune commande en préparation";
        $stats_progress_flow_class = "cook-synth-flow--ok";
    } elseif ($stats_max_wait_progress_min > 30) {
        $stats_progress_flow_label = "Attention : en préparation depuis +" . (int) round($stats_max_wait_progress_min) . " min";
        $stats_progress_flow_class = "cook-synth-flow--alert";
    } elseif ($stats_max_wait_progress_min > 15) {
        $stats_progress_flow_label = "Préparation lente (~" . (int) round($stats_max_wait_progress_min) . " min max)";
        $stats_progress_flow_class = "cook-synth-flow--warn";
    } else {
        $stats_progress_flow_label = "Préparation fluide (~" . (int) round($stats_max_wait_progress_min) . " min max)";
        $stats_progress_flow_class = "cook-synth-flow--ok";
    }
} else {
    $commandes_order_new = $commandes_order_progress = $commandes_order_done = [];
    $commande_items = $commande_items_progress = $commande_items_done = [];
    $count = $count_progress = $count_done = "";
    $composition_by_menu_id = [];
    $stats_max_wait_progress_min = null;
    $stats_progress_flow_label = "Aucune commande en préparation";
    $stats_progress_flow_class = "cook-synth-flow--ok";
} // endpoint ajax appele quand le chef clique sur Accepter ou Prete : ca change le statut d'une commande
if (isset($_GET["ajax"]) && $_GET["ajax"] === "action") {
    header("Content-Type: application/json; charset=utf-8"); // securite : on verifie le role cote serveur (un non-chef ne peut pas changer un statut)
    if (!$est_connecte || !in_array($role_actuel, ["chef", "fakeadmin", "admin"], true)) {
        http_response_code(403);
        echo json_encode(["ok" => false, "error" => "Non autorisé"]);
        exit();
    }
    $input = json_decode(file_get_contents("php://input"), true) ?? [];
    $action = $input["action"] ?? "";
    $id = (int) ($input["id"] ?? 0);
    if ($id <= 0) {
        echo json_encode(["ok" => false, "error" => "ID invalide"]);
        exit();
    }
    if ($action === "accept") {
        // on ne passe en preparation que si la commande est vraiment payee en entier (montant_paye >= total)
        $stmt = $pdo_commandes->prepare(
            "UPDATE commandes SET statut_production = 'En préparation' WHERE id = ? AND montant_paye >= total",
        );
        $stmt->execute([$id]);
    } elseif ($action === "ready") {
        $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'Prête' WHERE id = ?");
        $stmt->execute([$id]);
    } else {
        echo json_encode(["ok" => false, "error" => "Action inconnue"]);
        exit();
    }
    echo json_encode(["ok" => true]);
    exit();
} // endpoint ajax appele toutes les 5 secondes par le javascript : il renvoie le html des 3 colonnes en json
// ca permet de rafraichir le kanban sans recharger toute la page
if (isset($_GET["ajax"]) && $_GET["ajax"] === "fragment") {
    if (!$est_connecte || !in_array($role_actuel, ["chef", "fakeadmin", "admin"], true)) {
        http_response_code(403);
        header("Content-Type: application/json; charset=utf-8");
        echo json_encode(["ok" => false, "error" => "Non autorisé"]);
        exit();
    }
    header("Content-Type: application/json; charset=utf-8");
    ob_start();
    cuisinier_render_kanban_cards("new", $commandes_order_new, $commande_items, $composition_by_menu_id);
    $html_new = ob_get_clean();
    ob_start();
    cuisinier_render_kanban_cards(
        "progress",
        $commandes_order_progress,
        $commande_items_progress,
        $composition_by_menu_id,
    );
    $html_progress = ob_get_clean();
    ob_start();
    cuisinier_render_kanban_cards("done", $commandes_order_done, $commande_items_done, $composition_by_menu_id);
    $html_done = ob_get_clean();
    echo json_encode([
        "ok" => true,
        "columns" => [
            "new" => $html_new,
            "progress" => $html_progress,
            "done" => $html_done,
        ],
        "counts" => [
            "new" => count($commandes_order_new),
            "progress" => count($commandes_order_progress),
            "done" => count($commandes_order_done),
        ],
        "stats" => [
            "pipeline_total"      => (int) $stats_pipeline_total,
            "articles_pipeline"   => (int) $stats_articles_pipeline,
            "ca_pipeline"         => number_format($stats_ca_pipeline, 2, ",", " ") . " €",
            "delivered_today"     => (int) $stats_delivered_today,
            "delivered_week"      => (int) $stats_delivered_week,
            "pct_new"             => (int) $stats_pct_new,
            "pct_progress"        => (int) $stats_pct_progress,
            "pct_done"            => (int) $stats_pct_done,
            "flow_label"          => $stats_flow_label,
            "flow_class"          => $stats_flow_class,
            "progress_flow_label" => $stats_progress_flow_label,
            "progress_flow_class" => $stats_progress_flow_class,
        ],
        "server_time" => date("H:i:s"),
    ]);
    exit();
}
?>


<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Cuisine - Commandes</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/global.css">
    <link rel="stylesheet" href="cuisinier.css">
    <script>
    // place dans le head (consigne : pas de script hors du head)
    // DOMContentLoaded : on attend que la page soit chargee avant de toucher aux elements
    document.addEventListener('DOMContentLoaded', function () {

        // petite horloge en haut de l'ecran cuisine (seulement si l'element existe)
        const clockEl = document.getElementById('cookClock');
        if (clockEl) {
            function updateClock() {
                const now = new Date();
                const h = String(now.getHours()).padStart(2, '0');
                const m = String(now.getMinutes()).padStart(2, '0');
                const s = String(now.getSeconds()).padStart(2, '0');
                clockEl.textContent = h + ':' + m + ':' + s;
            }
            setInterval(updateClock, 1000);
            updateClock();
        }

        // toute la partie kanban n'existe que pour les chefs : si pas de kanban, on s'arrete la
        const kanban = document.querySelector('.kanban');
        if (!kanban) return;

        // rafraichissement automatique du kanban toutes les 5 secondes, sans recharger la page
        const INTERVAL_MS = 5000;
        const ENDPOINT    = 'index.php?ajax=fragment';
        const COLUMNS     = ['new', 'progress', 'done'];

        const syncTime = document.getElementById('cookSyncTime');
        const syncBox  = document.getElementById('cookSync');

        // on retient la carte survolee par la souris pour ne pas la detruire pendant qu'on clique dessus
        // (sinon le bouton Accepter disparaitrait sous le doigt au moment du rafraichissement)
        let hoveredCardId = null;
        document.addEventListener('mouseover', function (e) {
            const card = e.target.closest('.order-card');
            hoveredCardId = card ? card.getAttribute('data-commande-id') : null;
        });
        document.addEventListener('mouseout', function (e) {
            const card = e.target.closest('.order-card');
            if (card && card.getAttribute('data-commande-id') === hoveredCardId) {
                hoveredCardId = null;
            }
        });

        // change la petite pastille de synchro : vert (ok), orange (en cours), rouge (erreur)
        function setSyncStatus(state, time) {
            if (!syncBox) return;
            syncBox.classList.remove('is-ok', 'is-pending', 'is-error');
            switch (state) {
                case 'ok':      syncBox.classList.add('is-ok');      break;
                case 'pending': syncBox.classList.add('is-pending'); break;
                case 'error':   syncBox.classList.add('is-error');   break;
            }
            if (time && syncTime) syncTime.textContent = time;
        }

        // va chercher l'etat a jour des 3 colonnes aupres du serveur et met l'affichage a jour
        function refreshKanban() {
            if (document.hidden) return; // onglet cache, on economise

            setSyncStatus('pending');

            fetch(ENDPOINT, { credentials: 'same-origin', cache: 'no-store' })
                .then(function (r) {
                    if (!r.ok) throw new Error('HTTP ' + r.status);
                    return r.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) throw new Error('Réponse invalide');

                    // on met a jour les compteurs : le badge du header et le compteur de chaque colonne
                    for (let i = 0; i < COLUMNS.length; i++) {
                        const col = COLUMNS[i];
                        const n = data.counts[col];

                        const badge = document.getElementById('badge-count-' + col);
                        if (badge) badge.textContent = n;

                        const head = document.getElementById('kanban-count-' + col);
                        if (head) head.textContent = n;
                    }

                    // on remplace le contenu de chaque colonne, sauf la carte survolee qu'on garde telle quelle
                    for (let j = 0; j < COLUMNS.length; j++) {
                        const colName = COLUMNS[j];
                        const container = document.getElementById('kanban-cards-' + colName);
                        if (!container) continue;

                        // si on survole une carte et qu'elle est toujours dans la nouvelle reponse, on garde l'ancienne (clic en cours)
                        if (hoveredCardId) {
                            const oldHovered = container.querySelector('.order-card[data-commande-id="' + hoveredCardId + '"]');
                            if (oldHovered) {
                                const tmp = document.createElement('div');
                                tmp.innerHTML = data.columns[colName];
                                const stillThere = tmp.querySelector('.order-card[data-commande-id="' + hoveredCardId + '"]');
                                if (stillThere) {
                                    stillThere.replaceWith(oldHovered.cloneNode(true));
                                    container.innerHTML = '';
                                    while (tmp.firstChild) container.appendChild(tmp.firstChild);
                                    continue;
                                }
                            }
                        }
                        container.innerHTML = data.columns[colName];
                    }

                    setSyncStatus('ok', data.server_time || new Date().toLocaleTimeString());

                    // mise à jour des stats du panneau Synthèse
                    if (data.stats) {
                        var s = data.stats;
                        var upd = function(id, val) { var el = document.getElementById(id); if (el) el.textContent = val; };
                        upd('synth-kpi-pipeline', s.pipeline_total);
                        upd('synth-kpi-articles', s.articles_pipeline);
                        upd('synth-kpi-ca', s.ca_pipeline);
                        upd('synth-kpi-today', s.delivered_today);
                        upd('synth-kpi-week', s.delivered_week);
                        upd('synth-pct-new', s.pct_new + ' %');
                        upd('synth-pct-progress', s.pct_progress + ' %');
                        upd('synth-pct-done', s.pct_done + ' %');
                        var barNew  = document.getElementById('synth-bar-new');
                        var barProg = document.getElementById('synth-bar-progress');
                        var barDone = document.getElementById('synth-bar-done');
                        if (barNew)  barNew.style.flexBasis  = s.pct_new + '%';
                        if (barProg) barProg.style.flexBasis = s.pct_progress + '%';
                        if (barDone) barDone.style.flexBasis = s.pct_done + '%';
                        var flowBlock = document.getElementById('synth-flow-block');
                        var flowMsg   = document.getElementById('synth-flow-msg');
                        if (flowBlock) flowBlock.className = 'cook-synth-block cook-synth-flow ' + s.flow_class;
                        if (flowMsg)   flowMsg.textContent = s.flow_label;
                        var progBlock = document.getElementById('synth-progress-flow-block');
                        var progMsg   = document.getElementById('synth-progress-flow-msg');
                        if (progBlock) progBlock.className = 'cook-synth-block cook-synth-flow ' + s.progress_flow_class;
                        if (progMsg)   progMsg.textContent = s.progress_flow_label;
                    }
                })
                .catch(function () {
                    setSyncStatus('error');
                });
        }

        // clics sur les boutons Accepter / Prete : on ecoute au niveau du kanban (delegation d'evenement)
        kanban.addEventListener('click', function (e) {
            const btn = e.target.closest('[data-action]');
            if (!btn || btn.disabled) return;

            const action   = btn.getAttribute('data-action');
            const id       = parseInt(btn.getAttribute('data-id'), 10);
            const origText = btn.textContent;
            if (!id) return;

            btn.disabled    = true;
            btn.textContent = '…';

            // on envoie l'action (accepter ou prete) au serveur, puis on rafraichit le kanban
            fetch('index.php?ajax=action', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                credentials: 'same-origin',
                body: JSON.stringify({ action: action, id: id })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    refreshKanban();
                } else {
                    btn.disabled    = false;
                    btn.textContent = origText;
                }
            })
            .catch(function () {
                btn.disabled    = false;
                btn.textContent = origText;
            });
        });

        // premier rafraichissement tout de suite, puis toutes les 5 secondes
        refreshKanban();
        setInterval(refreshKanban, INTERVAL_MS);

        // et on rafraichit aussi des qu'on revient sur l'onglet
        document.addEventListener('visibilitychange', function () {
            if (!document.hidden) refreshKanban();
        });
    });
    </script>
</head>
<body>

    <header class="cook-header">
        <div class="cook-header-left">
            <a href="<?= $BASE ?>/" class="cook-back">&larr;</a>
            <h1 class="cook-title">Cuisine</h1>
        </div>
        <div class="cook-header-right">
            <span class="cook-badge cook-badge-new"><span id="badge-count-new"><?php echo count(
                    $commandes_order_new,
            ); ?></span> nouvelles</span>
            <span class="cook-badge cook-badge-progress"><span id="badge-count-progress"><?php echo count(
                    $commandes_order_progress,
            ); ?></span> en préparation</span>
            <span class="cook-badge cook-badge-done"><span id="badge-count-done"><?php echo count(
                    $commandes_order_done,
            ); ?></span> prêtes</span>
            <span class="cook-sync" id="cookSync" title="Dernière synchronisation">
                <span class="cook-sync-dot"></span>
                <span class="cook-sync-label">Synchro à <span id="cookSyncTime">—</span></span>
            </span>
            <span class="cook-clock" id="cookClock"></span>
        </div>
    </header>

    <?php if (!$est_connecte || !in_array($role_actuel, ["chef", "fakeadmin", "admin"], true)): ?>
    <main style="display:flex; align-items:center; justify-content:center; height:80vh; text-align:center;">
        <div>
            <p style="font-size:1.1rem; margin-bottom:1rem;">Accès réservé au personnel de cuisine.</p>
            <a href="<?= $BASE ?>/" style="color:var(--orange);">← Retour à l'accueil</a>
        </div>
    </main>
    <?php else: ?>
    <main class="kanban">

        <section class="kanban-col">
            <div class="kanban-col-header kanban-col-new">
                <span class="kanban-col-dot" style="background:#e74c3c;"></span>
                <h2>Nouvelles</h2>
                <span class="kanban-count" id="kanban-count-new"><?php echo count($commandes_order_new); ?></span>
            </div>
            <div class="kanban-cards" id="kanban-cards-new" data-col="new">
                <?php cuisinier_render_kanban_cards("new", $commandes_order_new, $commande_items, $composition_by_menu_id); ?>
            </div>
        </section>

        <section class="kanban-col">
            <div class="kanban-col-header kanban-col-progress">
                <span class="kanban-col-dot" style="background:#f39c12;"></span>
                <h2>En pr&eacute;paration</h2>
                <span class="kanban-count" id="kanban-count-progress"><?php echo count($commandes_order_progress); ?></span>
            </div>
            <div class="kanban-cards" id="kanban-cards-progress" data-col="progress">
                <?php cuisinier_render_kanban_cards(
                        "progress",
                        $commandes_order_progress,
                        $commande_items_progress,
                        $composition_by_menu_id,
                ); ?>
            </div>
        </section>

        <section class="kanban-col">
            <div class="kanban-col-header kanban-col-done">
                <span class="kanban-col-dot" style="background:#2ecc71;"></span>
                <h2>Pr&ecirc;tes (en attente livreur)</h2>
                <span class="kanban-count" id="kanban-count-done"><?php echo count($commandes_order_done); ?></span>
            </div>
            <div class="kanban-cards" id="kanban-cards-done" data-col="done">
                <?php cuisinier_render_kanban_cards(
                        "done",
                        $commandes_order_done,
                        $commande_items_done,
                        $composition_by_menu_id,
                ); ?>
            </div>
        </section>

        <?php if (
                $est_connecte &&
                ($role_actuel === "chef" || $role_actuel === "fakeadmin" || $role_actuel === "admin")
        ): ?>
            <aside class="cook-synthesis" aria-label="Synthèse efficacité cuisine">
                <div class="cook-synth-header">
                    <h2 class="cook-synth-title">Synthèse</h2>
                    <p class="cook-synth-sub">Efficacité &amp; flux</p>
                </div>
                <div class="cook-synth-body">
                    <div class="cook-synth-stat">
                        <span class="cook-synth-kpi" id="synth-kpi-pipeline"><?php echo (int) $stats_pipeline_total; ?></span>
                        <span class="cook-synth-label">Commandes en cuisine</span>
                    </div>
                    <div class="cook-synth-stat">
                        <span class="cook-synth-kpi" id="synth-kpi-articles"><?php echo (int) $stats_articles_pipeline; ?></span>
                        <span class="cook-synth-label">Articles &agrave; traiter</span>
                    </div>
                    <div class="cook-synth-stat">
                        <span class="cook-synth-kpi" id="synth-kpi-ca"><?php echo number_format($stats_ca_pipeline, 2, ",", " "); ?> &euro;</span>
                        <span class="cook-synth-label">CA en flux cuisine</span>
                    </div>
                    <div class="cook-synth-stat">
                        <span class="cook-synth-kpi" id="synth-kpi-today"><?php echo (int) $stats_delivered_today; ?></span>
                        <span class="cook-synth-label">R&eacute;cup. livreur aujourd&rsquo;hui</span>
                    </div>
                    <div class="cook-synth-stat">
                        <span class="cook-synth-kpi" id="synth-kpi-week"><?php echo (int) $stats_delivered_week; ?></span>
                        <span class="cook-synth-label">R&eacute;cup. sur 7 jours</span>
                    </div>
                    <div class="cook-synth-block">
                        <span class="cook-synth-block-title">R&eacute;partition pipeline</span>
                        <div class="cook-synth-bars" role="img" aria-label="Répartition nouvelles, en préparation, prêtes">
                            <div class="cook-synth-bar-track">
                                <?php if ($stats_pipeline_total > 0): ?>
                                <div id="synth-bar-new" class="cook-synth-bar-seg cook-synth-bar--new" style="flex-basis:<?php echo (int) $stats_pct_new; ?>%;"></div>
                                <div id="synth-bar-progress" class="cook-synth-bar-seg cook-synth-bar--progress" style="flex-basis:<?php echo (int) $stats_pct_progress; ?>%;"></div>
                                <div id="synth-bar-done" class="cook-synth-bar-seg cook-synth-bar--done" style="flex-basis:<?php echo (int) $stats_pct_done; ?>%;"></div>
                                <?php else: ?>
                                <div class="cook-synth-bar-empty">Vide</div>
                                <?php endif; ?>
                            </div>
                            <ul class="cook-synth-legend">
                                <li><span class="cook-synth-dot cook-synth-dot--new"></span> Nouv. <span id="synth-pct-new"><?php echo (int) $stats_pct_new; ?> %</span></li>
                                <li><span class="cook-synth-dot cook-synth-dot--progress"></span> Prep. <span id="synth-pct-progress"><?php echo (int) $stats_pct_progress; ?> %</span></li>
                                <li><span class="cook-synth-dot cook-synth-dot--done"></span> Pr&ecirc;tes <span id="synth-pct-done"><?php echo (int) $stats_pct_done; ?> %</span></li>
                            </ul>
                        </div>
                    </div>
                    <div id="synth-flow-block" class="cook-synth-block cook-synth-flow <?php echo htmlspecialchars($stats_flow_class, ENT_QUOTES, "UTF-8"); ?>">
                        <span class="cook-synth-block-title">File &laquo; nouvelles &raquo;</span>
                        <p id="synth-flow-msg" class="cook-synth-flow-msg"><?php echo htmlspecialchars($stats_flow_label, ENT_QUOTES, "UTF-8"); ?></p>
                    </div>
                    <div id="synth-progress-flow-block" class="cook-synth-block cook-synth-flow <?php echo htmlspecialchars($stats_progress_flow_class, ENT_QUOTES, "UTF-8"); ?>">
                        <span class="cook-synth-block-title">File &laquo; en pr&eacute;paration &raquo;</span>
                        <p id="synth-progress-flow-msg" class="cook-synth-flow-msg"><?php echo htmlspecialchars($stats_progress_flow_label, ENT_QUOTES, "UTF-8"); ?></p>
                    </div>
                </div>
            </aside>
        <?php endif; ?>

    </main>
    <?php endif; ?>


</body>
</html>
