<?php 
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


  /**
   * Affiche les lignes commande_items : article seul ou menu (produit_id &lt; 0) avec composition.
   *
   * @param array<int, list<array{nom:string, description:string}>> $composition_by_menu_id  clé = id menu (menus.id), comme abs(produit_id)
   */

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
      //On recup les commandes en attente cuisine et payées
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


  //Partie pour accepter une commande
  if(isset($_POST['accept_commande'])){
    $id_commande = $_POST['accept_commande'];
    $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'En préparation' WHERE id = ?");
    $stmt->execute([$id_commande]);
    header("Location: index.php");
    exit;
  }

  //Partie pour préparer une commande
  if(isset($_POST['ready_commande'])){
    $id_commande = $_POST['ready_commande'];
    $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'Prête' WHERE id = ?");
    $stmt->execute([$id_commande]);
    header("Location: index.php");
    exit;
  }

  //Partie pour récupérer une commande
  if(isset($_POST['done_commande'])){
    $id_commande = $_POST['done_commande'];
    $stmt = $pdo_commandes->prepare("UPDATE commandes SET statut_production = 'Récupérée livreur' WHERE id = ?");
    $stmt->execute([$id_commande]);
    header("Location: index.php");
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
      <a href="/ProjetCYJ/CYJ/" class="cook-back">&larr;</a>
      <h1 class="cook-title">Cuisine</h1>
    </div>
    <div class="cook-header-right">
      <span class="cook-badge cook-badge-new"><?php echo count($commandes_order_new); ?> nouvelles</span>
      <span class="cook-badge cook-badge-progress"><?php echo count($commandes_order_progress); ?> en préparation</span>
      <span class="cook-badge cook-badge-done"><?php echo count($commandes_order_done); ?> prêtes</span>
      <span class="cook-clock" id="cookClock"></span>
    </div>
  </header>

  <main class="kanban">

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-new">
        <span class="kanban-col-dot" style="background:#e74c3c;"></span>
        <h2>Nouvelles</h2>
        <span class="kanban-count"><?php echo count($commandes_order_new); ?></span>
      </div>
      <div class="kanban-cards">

        <?php if($count != ''): ?>
          <?php foreach ($commandes_order_new as $commande): ?>
              <article class="order-card order-new">
                <div class="order-top">
                  <span class="order-id">#<?php echo (int) $commande['id']; ?></span>
                  <span class="order-time">Passé : <?php echo convertDate($commande['date_commande']); ?></span>
                  <?php if (!empty($commande['heure_livraison'])): ?>
                    <br><span class="order-time" style="color:var(--orange); font-weight:bold;">Pour : <?php echo htmlspecialchars($commande['heure_livraison']); ?></span>
                  <?php endif; ?>
                </div>
                <ul class="order-items">
                  <?php cuisinier_render_commande_items($commande_items, (int) $commande['id'], $composition_by_menu_id); ?>
                </ul>
                <div class="order-footer">
                  <span class="order-total"><?php echo htmlspecialchars((string) ($commande['total'] ?? ''), ENT_QUOTES, 'UTF-8'); ?> &euro;</span>
                  <form action="index.php" method="post">
                      <button class="order-btn order-btn-accept" type="submit" name="accept_commande" value="<?php echo (int) $commande['id']; ?>">Accepter</button>
                  </form>
                </div>
              </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="kanban-cards-empty">Aucune commande nouvelle</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-progress">
        <span class="kanban-col-dot" style="background:#f39c12;"></span>
        <h2>En pr&eacute;paration</h2>
        <span class="kanban-count"><?php echo count($commandes_order_progress); ?></span>
      </div>
      <div class="kanban-cards">
        <?php if($count_progress != ''): ?>
          <?php foreach ($commandes_order_progress as $commande): ?>
            <article class="order-card order-progress">
              <div class="order-top">
                <span class="order-id">#<?php echo $commande['id']; ?></span>
                <span class="order-time">Passé : <?php echo convertDate($commande['date_commande']); ?></span>
                <?php if (!empty($commande['heure_livraison'])): ?>
                  <br><span class="order-time" style="color:var(--orange); font-weight:bold;">Pour : <?php echo htmlspecialchars($commande['heure_livraison']); ?></span>
                <?php endif; ?>
              </div>
              <ul class="order-items">
                <?php cuisinier_render_commande_items($commande_items_progress, (int) $commande['id'], $composition_by_menu_id); ?>
              </ul>
              <div class="order-footer">
                <span class="order-total"><?php echo $commande['total']; ?> &euro;</span>
                <form action="index.php" method="post">
                    <button class="order-btn order-btn-ready" type="submit" name="ready_commande" value="<?php echo $commande['id']; ?>">Prête</button>
                </form>
              </div>
              </article>
            <?php endforeach; ?>
        <?php else: ?>
          <div class="kanban-cards-empty">Aucune commande en préparation</div>
        <?php endif; ?>
      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-done">
        <span class="kanban-col-dot" style="background:#2ecc71;"></span>
        <h2>Pr&ecirc;tes (en attente livreur)</h2>
        <span class="kanban-count"><?php echo count($commandes_order_done); ?></span>
      </div>
      <div class="kanban-cards">
        <?php if($count_done != ''): ?>
          <?php foreach ($commandes_order_done as $commande): ?>
            <article class="order-card order-done">
              <div class="order-top">
                <span class="order-id">#<?php echo $commande['id']; ?></span>
                <span class="order-time">pr&ecirc;te depuis <?php echo convertDate($commande['date_commande']); ?></span>
                <?php if (!empty($commande['heure_livraison'])): ?>
                  <br><span class="order-time" style="color:var(--orange); font-weight:bold;">Pour : <?php echo htmlspecialchars($commande['heure_livraison']); ?></span>
                <?php endif; ?>
              </div>
              <ul class="order-items">
                <?php cuisinier_render_commande_items($commande_items_done, (int) $commande['id'], $composition_by_menu_id); ?>
              </ul>
              <div class="order-footer">
                <span class="order-total"><?php echo $commande['total']; ?> &euro;</span>
                <form action="index.php" method="post">
                    <button class="order-btn order-btn-done" type="submit" name="done_commande" value="<?php echo $commande['id']; ?>">Récupérée livreur</button>
                </form>
              </div>
            </article>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="kanban-cards-empty">Aucune commande prête</div>
        <?php endif; ?>
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

</body>
</html>
