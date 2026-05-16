<?php 
$BASE = preg_replace('#(/(?:Admin|Carte|Cuisinier|LOG|Livraison|Notation|Profil|Sujet|CYBank)(?:/.*)?)?/[^/]*$#', '', $_SERVER['SCRIPT_NAME']);
  require_once '../../../protection.php';
  require_once '../../../../db_config_yumland.php';
  $pdo_commandes = $pdo;

?>
<?php

  function convertDate($date) //On passe la date en français pour l'affichage puisque dans la database elle est en américain
  {
    $date = date('d-m-Y H:i:s', strtotime($date));
    return $date;
  }

  /** Colonne commandes.livreur_ID (FK logique vers espace_etudiant.users.id) */
  function cuisinier_livreur_id_assigned(array $commande): bool
  {
      $v = $commande['livreur_ID'] ?? $commande['livreur_id'] ?? null;
      if ($v === null || $v === '') {
          return false;
      }
      return (int) $v > 0;
  }


  /**
   * Affiche les lignes commande_items : article seul ou menu (produit_id &lt; 0) avec composition.
   *
   * @param array<int, list<array{nom:string, description:string}>> $composition_by_menu_id  clé = id menu (menus.id), comme abs(produit_id)
   */

   /**
    * Rend les cartes d'une colonne du kanban (nouvelles / en préparation / prêtes).
    * Utilisé à la fois pour le rendu initial de la page ET pour le endpoint
    * AJAX qui renvoie le fragment HTML toutes les 5s.
    */
   function cuisinier_render_kanban_cards($type, $commandes, $items, $composition_by_menu_id)
   {
       switch ($type) {
           case 'new':
               $card_class    = 'order-new';
               $btn_class     = 'order-btn-accept';
               $btn_name      = 'accept_commande';
               $btn_label     = 'Accepter';
               $empty_text    = 'Aucune commande nouvelle';
               $check_locked  = true;
               $time_label    = 'Passé : ';
               break;
           case 'progress':
               $card_class    = 'order-progress';
               $btn_class     = 'order-btn-ready';
               $btn_name      = 'ready_commande';
               $btn_label     = 'Prête';
               $empty_text    = 'Aucune commande en préparation';
               $check_locked  = false;
               $time_label    = 'Passé : ';
               break;
           case 'done':
               $card_class    = 'order-done';
               $btn_class     = '';
               $btn_name      = '';
               $btn_label     = '';
               $empty_text    = 'Aucune commande prête';
               $check_locked  = false;
               $time_label    = 'Prête depuis : ';
               $readonly_done = true;
               break;
           default:
               return;
       }

       $readonly_done = $readonly_done ?? false;

       if (empty($commandes)) {
           echo '<div class="kanban-cards-empty">' . htmlspecialchars($empty_text) . '</div>';
           return;
       }

       foreach ($commandes as $commande) {
           $en_attente_diff = false;
           $reste_a_payer = 0;
           if ($check_locked) {
               $reste_a_payer = max(0, (float) $commande['total'] - (float) ($commande['montant_paye'] ?? 0));
               $en_attente_diff = ($reste_a_payer > 0);
           }

           $cid_attr = (int) $commande['id'];
           $card_classes = 'order-card ' . $card_class . ($en_attente_diff ? ' order-locked' : '');

           echo '<article class="' . $card_classes . '" data-commande-id="' . $cid_attr . '">';

           if ($en_attente_diff) {
               echo '<span class="order-warn-paiement"';
               echo ' title="Le client a modifié sa commande après paiement et doit régler le différentiel avant que la cuisine ne puisse l\'accepter.">';
               echo 'Différentiel à payer · ' . number_format($reste_a_payer, 2, ',', ' ') . ' €';
               echo '</span>';
           }

           if ($type === 'progress' && cuisinier_livreur_id_assigned($commande)) {
               echo '<span class="order-livreur-reserve" title="Un livreur a d&eacute;j&agrave; r&eacute;serv&eacute; cette commande et attend qu&rsquo;elle soit pr&ecirc;te">Livreur r&eacute;serv&eacute;</span>';
           }

           echo '<div class="order-top">';
           echo '<span class="order-id">#' . $cid_attr . '</span>';
           echo '<span class="order-time">' . htmlspecialchars($time_label) . convertDate($commande['date_commande']) . '</span>';
           if (!empty($commande['heure_livraison'])) {
               echo '<br><span class="order-time" style="color:var(--orange); font-weight:bold;">Pour : '
                  . htmlspecialchars($commande['heure_livraison']) . '</span>';
           }
           echo '</div>';

           echo '<ul class="order-items">';
           cuisinier_render_commande_items($items, $cid_attr, $composition_by_menu_id);
           echo '</ul>';

           $livreur_reserve = cuisinier_livreur_id_assigned($commande);

           echo '<div class="order-footer">';
           echo '<span class="order-total">' . htmlspecialchars((string) ($commande['total'] ?? ''), ENT_QUOTES, 'UTF-8') . ' &euro;</span>';
           if (!empty($readonly_done)) {
               if ($livreur_reserve) {
                   echo '<span class="order-wait-livreur order-wait-livreur--assigned" title="Un livreur a r&eacute;serv&eacute; cette commande et viendra la r&eacute;cup&eacute;rer">Livreur en route</span>';
               } else {
                   echo '<span class="order-wait-livreur" title="Un livreur doit prendre la commande depuis son espace">Attente livreur</span>';
               }
           } else {
               $action_key = ($btn_name === 'accept_commande') ? 'accept' : 'ready';
               echo '<button class="order-btn ' . htmlspecialchars($btn_class, ENT_QUOTES, 'UTF-8') . '" type="button"';
               echo ' data-action="' . $action_key . '" data-id="' . $cid_attr . '"';
               if ($en_attente_diff) {
                   echo ' disabled title="Le client doit payer le différentiel avant que vous ne puissiez accepter cette commande"';
               }
               echo '>' . htmlspecialchars($en_attente_diff ? 'En attente paiement' : $btn_label) . '</button>';
           }
           echo '</div>';

           echo '</article>';
       }
   }

   function cuisinier_render_commande_items($items, $commande_id, $composition_by_menu_id)
   {
       foreach ($items as $line) 
       {
           // On ignore les lignes qui n'appartiennent pas à cette commande
           if ($line['commande_id'] != $commande_id) {
               continue;
           }
           $pid = $line['produit_id'];
           $qty = $line['quantite'];
           $nom = htmlspecialchars($line['nom']);
   
           // C'est un menu (produit_id négatif)
           if ($pid < 0) {
   
               $menu_id = abs($pid);
   
               echo '<li class="order-line order-line--menu">';
               echo '<div class="order-line-menu-head">';
               echo '<span class="order-qty">' . $qty . '&times;</span> ';
               echo '<strong>' . $nom . '</strong> ';
               echo '<span class="order-tag">Menu</span>';
               echo '</div>';
   
               // Le menu a une composition connue
               if (isset($composition_by_menu_id[$menu_id]) && $composition_by_menu_id[$menu_id] != []) {
   
                   $parts = $composition_by_menu_id[$menu_id];
   
                   echo '<ul class="order-menu-parts">';
   
                   foreach ($parts as $p) {
                       $pn = htmlspecialchars($p['nom']);
                       $pd = $p['description'];
   
                       echo '<li>';
                       echo '<span class="order-qty-sub">' . $qty . '&times;</span> ' . $pn;
   
   
                       echo '</li>';
                   }
   
                   echo '</ul>';
   
               // Le menu n'a pas de composition
               } else {
                   echo '<p class="order-menu-empty">Composition du menu non renseignée (code_menu = ' . $menu_id . ').</p>';
               }
   
               echo '</li>';
   
           // C'est un produit simple (produit_id positif)
           } else {
               echo '<li class="order-line">';
               echo '<span class="order-qty">' . $qty . '&times;</span> ' . $nom;
               echo '</li>';
           }
       }
   }

  if($est_connecte && ($role_actuel === "chef" || $role_actuel === "fakeadmin" || $role_actuel === "admin")) 
  {
    $stmt = $pdo_commandes->prepare('SELECT * FROM articles');
    $stmt->execute();
    $articles_rows = $stmt->fetchAll();

    $articles_by_code = [];
    foreach ($articles_rows as $ar) {
        $r = array_change_key_case($ar, CASE_LOWER);
        $code = (int) ($r['code'] ?? $r['id'] ?? 0);
        if ($code > 0) {
            $articles_by_code[$code] = [
                'nom' => (string) ($r['nom'] ?? ''),
                'description' => (string) ($r['description'] ?? ''),
            ];
        }
    }

    $stmt = $pdo_commandes->prepare('SELECT * FROM composition_menu');
    $stmt->execute();
    $composition_menu_rows = $stmt->fetchAll();

    $composition_by_menu_id = [];
    foreach ($composition_menu_rows as $crow) {
        $cr = array_change_key_case($crow, CASE_LOWER);
        $menu_key = (int) ($cr['code_menu'] ?? 0);
        $article_code = (int) ($cr['code_article'] ?? 0);
        if ($menu_key <= 0 || $article_code <= 0) {
            continue;
        }
        $art = $articles_by_code[$article_code] ?? null;
        $nom_part = $art['nom'] ?? ('Article #' . $article_code);
        $desc_part = $art['description'] ?? '';
        if (!isset($composition_by_menu_id[$menu_key])) {
            $composition_by_menu_id[$menu_key] = [];
        }
        $composition_by_menu_id[$menu_key][] = [
            'nom' => $nom_part,
            'description' => $desc_part,
        ];
    }

    //Partie Nouvelles
      //On recupère toutes les commandes en attente cuisine et payées.
      //Celles dont montant_paye < total (différentiel non payé par le client après modif)
      //seront affichées avec un bandeau rouge et un bouton Accepter désactivé.
      $stmt = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
      $stmt->execute(['En attente cuisine']);
      $commandes_order_new = $stmt->fetchAll();

      //On fait le tableau des ids des commandes
      $ids_commandes = [];
      $count = [];
      foreach ($commandes_order_new as $commande) {
        $ids_commandes[] = $commande['id'];
        $count [] = '?';
      }
      $count = implode(',', $count);

      //On recup les articles des commandes 
      if($count != ''){
        //On recup les articles des commandes
        $stmt = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count)");
        $stmt->execute($ids_commandes);
        $commande_items = $stmt->fetchAll();
      }
      else{
        $commande_items = [];
      }

    //Partie En préparation

      $stmt1 = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
      $stmt1->execute(['En préparation']);
      $commandes_order_progress = $stmt1->fetchAll();

      //On fait le tableau des ids des commandes
      $ids_commandes_progress = [];
      $count_progress = [];
      foreach ($commandes_order_progress as $commande) {
        $ids_commandes_progress[] = $commande['id'];
        $count_progress [] = '?';
      }
      $count_progress = implode(',', $count_progress);
      if($count_progress != ''){
        //On recup les articles des commandes
        $stmt2 = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count_progress)");
        $stmt2->execute($ids_commandes_progress);
        $commande_items_progress = $stmt2->fetchAll();
      }
      else{
        $commande_items_progress = [];
      }

    //Partie Prêtes

      $stmt3 = $pdo_commandes->prepare("SELECT * FROM commandes WHERE statut_production = ? AND statut = 'Payé'");
      $stmt3->execute(['Prête']);
      $commandes_order_done = $stmt3->fetchAll();

      //On fait le tableau des ids des commandes
      $ids_commandes_done = [];
      $count_done = [];
      foreach ($commandes_order_done as $commande) {
        $ids_commandes_done[] = $commande['id'];
        $count_done [] = '?';
      }
      $count_done = implode(',', $count_done);
      if($count_done != ''){
        //On recup les articles des commandes
        $stmt4 = $pdo_commandes->prepare("SELECT * FROM commande_items WHERE commande_id IN ($count_done)");
        $stmt4->execute($ids_commandes_done);
        $commande_items_done = $stmt4->fetchAll();
      }
      else{
        $commande_items_done = [];
      }

    // Synthèse efficacité (pipeline + historique livreur)
      $sum_items_qty = static function (array $rows): int 
      {
        $n = 0;
        foreach ($rows as $row) {
          $n += (int) ($row['quantite'] ?? 0);
        }
        return $n;
      };
      $stats_articles_pipeline = $sum_items_qty($commande_items)
        + $sum_items_qty($commande_items_progress)
        + $sum_items_qty($commande_items_done);

      $sum_commandes_total = static function (array $commandes): float 
      {
        $s = 0.0;
        foreach ($commandes as $c) {
          $s += (float) str_replace(',', '.', (string) ($c['total'] ?? '0'));
        }
        return $s;
      };
      
      $stats_ca_pipeline = $sum_commandes_total($commandes_order_new)
        + $sum_commandes_total($commandes_order_progress)
        + $sum_commandes_total($commandes_order_done);

      $stats_pipeline_total = count($commandes_order_new) + count($commandes_order_progress) + count($commandes_order_done);
      $stats_pct_new = $stats_pipeline_total > 0
        ? (int) round(100 * count($commandes_order_new) / $stats_pipeline_total) : 0;
      $stats_pct_progress = $stats_pipeline_total > 0
        ? (int) round(100 * count($commandes_order_progress) / $stats_pipeline_total) : 0;
      $stats_pct_done = $stats_pipeline_total > 0
        ? max(0, 100 - $stats_pct_new - $stats_pct_progress) : 0;

      $stats_max_wait_new_min = null;
      foreach ($commandes_order_new as $c) {
        $ts = strtotime($c['date_commande'] ?? '');
        if ($ts) {
          $mins = (time() - $ts) / 60;
          if ($stats_max_wait_new_min === null || $mins > $stats_max_wait_new_min) {
            $stats_max_wait_new_min = $mins;
          }
        }
      }

      $stmt_stats = $pdo_commandes->query(
        "SELECT COUNT(*) FROM commandes WHERE statut_production = 'Récupérée livreur' AND DATE(date_commande) = CURDATE()"
      );
      $stats_delivered_today = $stmt_stats ? (int) $stmt_stats->fetchColumn() : 0;

      $stmt_stats_w = $pdo_commandes->query(
        "SELECT COUNT(*) FROM commandes WHERE statut_production = 'Récupérée livreur' AND date_commande >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
      );
      $stats_delivered_week = $stmt_stats_w ? (int) $stmt_stats_w->fetchColumn() : 0;

      if ($stats_max_wait_new_min === null) {
        $stats_flow_label = 'Aucune commande en file « nouvelles »';
        $stats_flow_class = 'cook-synth-flow--ok';
      } elseif ($stats_max_wait_new_min > 30) {
        $stats_flow_label = 'Attention : une commande attend plus de 30 min en file nouvelle';
        $stats_flow_class = 'cook-synth-flow--alert';
      } elseif ($stats_max_wait_new_min > 15) {
        $stats_flow_label = 'File nouvelle un peu chargée (max ~' . (int) round($stats_max_wait_new_min) . ' min)';
        $stats_flow_class = 'cook-synth-flow--warn';
      } else {
        $stats_flow_label = 'File nouvelle sous contrôle (attente max ~' . (int) round($stats_max_wait_new_min) . ' min)';
        $stats_flow_class = 'cook-synth-flow--ok';
      }
  } else 
  {
    $commandes_order_new = $commandes_order_progress = $commandes_order_done = [];
    $commande_items = $commande_items_progress = $commande_items_done = [];
    $count = $count_progress = $count_done = '';
    $composition_by_menu_id = [];
  }


  // Endpoint AJAX : changement de statut (accept / ready)
  if (isset($_GET['ajax']) && $_GET['ajax'] === 'action') {
      header('Content-Type: application/json; charset=utf-8');
      if (!$est_connecte || !in_array($role_actuel, ['chef', 'fakeadmin', 'admin'], true)) {
          http_response_code(403);
          echo json_encode(['ok' => false, 'error' => 'Non autorisé']);
          exit;
      }
      $input  = json_decode(file_get_contents('php://input'), true) ?? [];
      $action = $input['action'] ?? '';
      $id     = (int) ($input['id'] ?? 0);
      if ($id <= 0) {
          echo json_encode(['ok' => false, 'error' => 'ID invalide']);
          exit;
      }
      if ($action === 'accept') {
          $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'En préparation' WHERE id = ? AND montant_paye >= total");
          $stmt->execute([$id]);
      } elseif ($action === 'ready') {
          $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'Prête' WHERE id = ?");
          $stmt->execute([$id]);
      } else {
          echo json_encode(['ok' => false, 'error' => 'Action inconnue']);
          exit;
      }
      echo json_encode(['ok' => true]);
      exit;
  }

  // ===== Endpoint AJAX : renvoie le HTML des 3 colonnes en JSON =====
  // Appelé toutes les 5s par le polling JS pour rafraichir le kanban
  // sans recharger la page entière.
  if (isset($_GET['ajax']) && $_GET['ajax'] === 'fragment') {
      if (!$est_connecte || !in_array($role_actuel, ['chef', 'fakeadmin', 'admin'], true)) {
          http_response_code(403);
          header('Content-Type: application/json; charset=utf-8');
          echo json_encode(['ok' => false, 'error' => 'Non autorisé']);
          exit;
      }

      header('Content-Type: application/json; charset=utf-8');

      ob_start();
      cuisinier_render_kanban_cards('new', $commandes_order_new, $commande_items, $composition_by_menu_id);
      $html_new = ob_get_clean();

      ob_start();
      cuisinier_render_kanban_cards('progress', $commandes_order_progress, $commande_items_progress, $composition_by_menu_id);
      $html_progress = ob_get_clean();

      ob_start();
      cuisinier_render_kanban_cards('done', $commandes_order_done, $commande_items_done, $composition_by_menu_id);
      $html_done = ob_get_clean();

      echo json_encode([
          'ok'      => true,
          'columns' => [
              'new'      => $html_new,
              'progress' => $html_progress,
              'done'     => $html_done,
          ],
          'counts'  => [
              'new'      => count($commandes_order_new),
              'progress' => count($commandes_order_progress),
              'done'     => count($commandes_order_done),
          ],
          'server_time' => date('H:i:s'),
      ]);
      exit;
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
</head>
<body>

  <header class="cook-header">
    <div class="cook-header-left">
      <a href="<?= $BASE ?>/" class="cook-back">&larr;</a>
      <h1 class="cook-title">Cuisine</h1>
    </div>
    <div class="cook-header-right">
      <span class="cook-badge cook-badge-new"><span id="badge-count-new"><?php echo count($commandes_order_new); ?></span> nouvelles</span>
      <span class="cook-badge cook-badge-progress"><span id="badge-count-progress"><?php echo count($commandes_order_progress); ?></span> en préparation</span>
      <span class="cook-badge cook-badge-done"><span id="badge-count-done"><?php echo count($commandes_order_done); ?></span> prêtes</span>
      <span class="cook-sync" id="cookSync" title="Dernière synchronisation">
        <span class="cook-sync-dot"></span>
        <span class="cook-sync-label">Synchro à <span id="cookSyncTime">—</span></span>
      </span>
      <span class="cook-clock" id="cookClock"></span>
    </div>
  </header>

  <main class="kanban">

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-new">
        <span class="kanban-col-dot" style="background:#e74c3c;"></span>
        <h2>Nouvelles</h2>
        <span class="kanban-count" id="kanban-count-new"><?php echo count($commandes_order_new); ?></span>
      </div>
      <div class="kanban-cards" id="kanban-cards-new" data-col="new">
        <?php cuisinier_render_kanban_cards('new', $commandes_order_new, $commande_items, $composition_by_menu_id); ?>
      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-progress">
        <span class="kanban-col-dot" style="background:#f39c12;"></span>
        <h2>En pr&eacute;paration</h2>
        <span class="kanban-count" id="kanban-count-progress"><?php echo count($commandes_order_progress); ?></span>
      </div>
      <div class="kanban-cards" id="kanban-cards-progress" data-col="progress">
        <?php cuisinier_render_kanban_cards('progress', $commandes_order_progress, $commande_items_progress, $composition_by_menu_id); ?>
      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-done">
        <span class="kanban-col-dot" style="background:#2ecc71;"></span>
        <h2>Pr&ecirc;tes (en attente livreur)</h2>
        <span class="kanban-count" id="kanban-count-done"><?php echo count($commandes_order_done); ?></span>
      </div>
      <div class="kanban-cards" id="kanban-cards-done" data-col="done">
        <?php cuisinier_render_kanban_cards('done', $commandes_order_done, $commande_items_done, $composition_by_menu_id); ?>
      </div>
    </section>

    <?php if ($est_connecte && ($role_actuel === 'chef' || $role_actuel === 'fakeadmin' || $role_actuel === 'admin')): ?>
      <aside class="cook-synthesis" aria-label="Synthèse efficacité cuisine">
        <div class="cook-synth-header">
          <h2 class="cook-synth-title">Synthèse</h2>
          <p class="cook-synth-sub">Efficacité &amp; flux</p>
        </div>
        <div class="cook-synth-body">
          <div class="cook-synth-stat">
            <span class="cook-synth-kpi"><?php echo (int) $stats_pipeline_total; ?></span>
            <span class="cook-synth-label">Commandes en cuisine</span>
          </div>
          <div class="cook-synth-stat">
            <span class="cook-synth-kpi"><?php echo (int) $stats_articles_pipeline; ?></span>
            <span class="cook-synth-label">Articles &agrave; traiter</span>
          </div>
          <div class="cook-synth-stat">
            <span class="cook-synth-kpi"><?php echo number_format($stats_ca_pipeline, 2, ',', ' '); ?> &euro;</span>
            <span class="cook-synth-label">CA en flux cuisine</span>
          </div>
          <div class="cook-synth-stat">
            <span class="cook-synth-kpi"><?php echo (int) $stats_delivered_today; ?></span>
            <span class="cook-synth-label">R&eacute;cup. livreur aujourd&rsquo;hui</span>
          </div>
          <div class="cook-synth-stat">
            <span class="cook-synth-kpi"><?php echo (int) $stats_delivered_week; ?></span>
            <span class="cook-synth-label">R&eacute;cup. sur 7 jours</span>
          </div>
          <div class="cook-synth-block">
            <span class="cook-synth-block-title">R&eacute;partition pipeline</span>
            <div class="cook-synth-bars" role="img" aria-label="Répartition nouvelles, en préparation, prêtes">
              <div class="cook-synth-bar-track">
                <?php if ($stats_pipeline_total > 0): ?>
                <div class="cook-synth-bar-seg cook-synth-bar--new" style="flex-basis:<?php echo (int) $stats_pct_new; ?>%;"></div>
                <div class="cook-synth-bar-seg cook-synth-bar--progress" style="flex-basis:<?php echo (int) $stats_pct_progress; ?>%;"></div>
                <div class="cook-synth-bar-seg cook-synth-bar--done" style="flex-basis:<?php echo (int) $stats_pct_done; ?>%;"></div>
                <?php else: ?>
                <div class="cook-synth-bar-empty">Vide</div>
                <?php endif; ?>
              </div>
              <ul class="cook-synth-legend">
                <li><span class="cook-synth-dot cook-synth-dot--new"></span> Nouv. <?php echo (int) $stats_pct_new; ?> %</li>
                <li><span class="cook-synth-dot cook-synth-dot--progress"></span> Prep. <?php echo (int) $stats_pct_progress; ?> %</li>
                <li><span class="cook-synth-dot cook-synth-dot--done"></span> Pr&ecirc;tes <?php echo (int) $stats_pct_done; ?> %</li>
              </ul>
            </div>
          </div>
          <div class="cook-synth-block cook-synth-flow <?php echo htmlspecialchars($stats_flow_class, ENT_QUOTES, 'UTF-8'); ?>">
            <span class="cook-synth-block-title">File &laquo; nouvelles &raquo;</span>
            <p class="cook-synth-flow-msg"><?php echo htmlspecialchars($stats_flow_label, ENT_QUOTES, 'UTF-8'); ?></p>
          </div>
        </div>
      </aside>
    <?php endif; ?>

  </main>


  <!-- script pour l'heure -->
  <script>
    function updateClock() {
      var now = new Date();
      var h = String(now.getHours()).padStart(2, '0');
      var m = String(now.getMinutes()).padStart(2, '0');
      var s = String(now.getSeconds()).padStart(2, '0');
      document.getElementById('cookClock').textContent = h + ':' + m + ':' + s;
    }
    setInterval(updateClock, 1000);
    updateClock();
  </script>

  <!-- Polling automatique du kanban (toutes les 5s) -->
  <script>
  (function () {
    var INTERVAL_MS = 5000;
    var ENDPOINT    = 'index.php?ajax=fragment';
    var COLUMNS     = ['new', 'progress', 'done'];

    var syncDot   = document.querySelector('#cookSync .cook-sync-dot');
    var syncTime  = document.getElementById('cookSyncTime');
    var syncBox   = document.getElementById('cookSync');

    // On suit l'élément que l'utilisateur survole pour ne PAS le détruire
    // au milieu d'une interaction (sinon le bouton "Accepter" s'évapore sous son doigt).
    var hoveredCardId = null;
    document.addEventListener('mouseover', function (e) {
      var card = e.target.closest('.order-card');
      hoveredCardId = card ? card.getAttribute('data-commande-id') : null;
    });
    document.addEventListener('mouseout', function (e) {
      var card = e.target.closest('.order-card');
      if (card && card.getAttribute('data-commande-id') === hoveredCardId) {
        hoveredCardId = null;
      }
    });

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

    function refreshKanban() {
      if (document.hidden) return; // onglet caché → on économise

      setSyncStatus('pending');

      fetch(ENDPOINT, { credentials: 'same-origin', cache: 'no-store' })
        .then(function (r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.json();
        })
        .then(function (data) {
          if (!data || !data.ok) throw new Error('Réponse invalide');

          // 1. Compteurs (header + colonnes)
          for (var i = 0; i < COLUMNS.length; i++) {
            var col = COLUMNS[i];
            var n = data.counts[col];

            var badge = document.getElementById('badge-count-' + col);
            if (badge) badge.textContent = n;

            var head = document.getElementById('kanban-count-' + col);
            if (head) head.textContent = n;
          }

          // 2. Cartes : on remplace le HTML de chaque colonne, mais
          //    si l'utilisateur survole une carte on garde son DOM existant
          //    pour cette carte précise (évite de casser le clic en cours).
          for (var j = 0; j < COLUMNS.length; j++) {
            var colName = COLUMNS[j];
            var container = document.getElementById('kanban-cards-' + colName);
            if (!container) continue;

            // Cas spécial : on conserve la carte survolée si elle est encore présente
            // dans la nouvelle réponse, sinon on remplace tout.
            if (hoveredCardId) {
              var oldHovered = container.querySelector('.order-card[data-commande-id="' + hoveredCardId + '"]');
              if (oldHovered) {
                // Parser le nouveau HTML pour voir si la carte y est toujours
                var tmp = document.createElement('div');
                tmp.innerHTML = data.columns[colName];
                var stillThere = tmp.querySelector('.order-card[data-commande-id="' + hoveredCardId + '"]');
                if (stillThere) {
                  // On reporte l'élément survolé tel quel à sa place
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
        })
        .catch(function () {
          setSyncStatus('error');
        });
    }

    // Boutons async : Accepter / Prête (event delegation sur le kanban)
    document.querySelector('.kanban').addEventListener('click', function (e) {
      var btn = e.target.closest('[data-action]');
      if (!btn || btn.disabled) return;

      var action   = btn.getAttribute('data-action');
      var id       = parseInt(btn.getAttribute('data-id'), 10);
      var origText = btn.textContent;
      if (!id) return;

      btn.disabled    = true;
      btn.textContent = '…';

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

    // 1er rafraichissement immédiat puis toutes les 5s
    refreshKanban();
    setInterval(refreshKanban, INTERVAL_MS);

    // Rafraichir tout de suite quand on revient sur l'onglet
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) refreshKanban();
    });
  })();
  </script>

</body>
</html>
