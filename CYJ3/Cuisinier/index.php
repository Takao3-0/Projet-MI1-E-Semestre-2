<?php require_once '../../../protection.php'; ?>

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
      <span class="cook-badge cook-badge-new">4 nouvelles</span>
      <span class="cook-clock" id="cookClock"></span>
    </div>
  </header>

  <main class="kanban">

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-new">
        <span class="kanban-col-dot" style="background:#e74c3c;"></span>
        <h2>Nouvelles</h2>
        <span class="kanban-count">4</span>
      </div>
      <div class="kanban-cards">

        <article class="order-card order-new">
          <div class="order-top">
            <span class="order-id">#1042</span>
            <span class="order-time">il y a 2 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">2x</span> CY Smash Burger</li>
            <li><span class="order-qty">1x</span> Frites Maison</li>
            <li><span class="order-qty">2x</span> Coca-Cola 33cl</li>
          </ul>
          <div class="order-note">Note : Sans oignon sur un burger</div>
          <div class="order-footer">
            <span class="order-total">23.30 &euro;</span>
            <button class="order-btn order-btn-accept" type="button">Accepter</button>
          </div>
        </article>

        <article class="order-card order-new">
          <div class="order-top">
            <span class="order-id">#1041</span>
            <span class="order-time">il y a 5 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">1x</span> Pizza Margherita</li>
            <li><span class="order-qty">1x</span> Pizza 4 Fromages</li>
          </ul>
          <div class="order-footer">
            <span class="order-total">20.50 &euro;</span>
            <button class="order-btn order-btn-accept" type="button">Accepter</button>
          </div>
        </article>

        <article class="order-card order-new">
          <div class="order-top">
            <span class="order-id">#1040</span>
            <span class="order-time">il y a 8 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">1x</span> Tacos XL</li>
            <li><span class="order-qty">1x</span> Nuggets x8</li>
            <li><span class="order-qty">1x</span> Jus d'Orange Frais</li>
          </ul>
          <div class="order-note">Note : Sauce alg&eacute;rienne &agrave; part</div>
          <div class="order-footer">
            <span class="order-total">20.30 &euro;</span>
            <button class="order-btn order-btn-accept" type="button">Accepter</button>
          </div>
        </article>

        <article class="order-card order-new">
          <div class="order-top">
            <span class="order-id">#1039</span>
            <span class="order-time">il y a 12 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">3x</span> Chicken Burger</li>
            <li><span class="order-qty">3x</span> Frites Maison</li>
            <li><span class="order-qty">3x</span> Coca-Cola 33cl</li>
          </ul>
          <div class="order-footer">
            <span class="order-total">40.20 &euro;</span>
            <button class="order-btn order-btn-accept" type="button">Accepter</button>
          </div>
        </article>

      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-progress">
        <span class="kanban-col-dot" style="background:#f39c12;"></span>
        <h2>En pr&eacute;paration</h2>
        <span class="kanban-count">2</span>
      </div>
      <div class="kanban-cards">

        <article class="order-card order-progress">
          <div class="order-top">
            <span class="order-id">#1038</span>
            <span class="order-time">accept&eacute;e il y a 6 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">1x</span> Bacon King</li>
            <li><span class="order-qty">1x</span> Onion Rings</li>
            <li><span class="order-qty">1x</span> Brownie</li>
          </ul>
          <div class="order-timer">&#9201; 6:23</div>
          <div class="order-footer">
            <span class="order-total">19.90 &euro;</span>
            <button class="order-btn order-btn-ready" type="button">Pr&ecirc;te</button>
          </div>
        </article>

        <article class="order-card order-progress">
          <div class="order-top">
            <span class="order-id">#1037</span>
            <span class="order-time">accept&eacute;e il y a 14 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">2x</span> Wrap Poulet Avocat</li>
            <li><span class="order-qty">2x</span> Eau Min&eacute;rale 50cl</li>
          </ul>
          <div class="order-timer order-timer-late">&#9201; 14:05</div>
          <div class="order-footer">
            <span class="order-total">18.00 &euro;</span>
            <button class="order-btn order-btn-ready" type="button">Pr&ecirc;te</button>
          </div>
        </article>

      </div>
    </section>

    <section class="kanban-col">
      <div class="kanban-col-header kanban-col-done">
        <span class="kanban-col-dot" style="background:#2ecc71;"></span>
        <h2>Pr&ecirc;tes</h2>
        <span class="kanban-count">2</span>
      </div>
      <div class="kanban-cards">

        <article class="order-card order-done">
          <div class="order-top">
            <span class="order-id">#1036</span>
            <span class="order-time">pr&ecirc;te depuis 1 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">1x</span> Pizza Pepperoni</li>
            <li><span class="order-qty">1x</span> Cookie G&eacute;ant</li>
          </ul>
          <div class="order-footer">
            <span class="order-total">13.40 &euro;</span>
            <button class="order-btn order-btn-done" type="button">Livr&eacute;e</button>
          </div>
        </article>

        <article class="order-card order-done">
          <div class="order-top">
            <span class="order-id">#1035</span>
            <span class="order-time">pr&ecirc;te depuis 4 min</span>
          </div>
          <ul class="order-items">
            <li><span class="order-qty">1x</span> Veggie Burger</li>
            <li><span class="order-qty">1x</span> Glace 2 Boules</li>
            <li><span class="order-qty">1x</span> Jus d'Orange Frais</li>
          </ul>
          <div class="order-footer">
            <span class="order-total">16.00 &euro;</span>
            <button class="order-btn order-btn-done" type="button">Livr&eacute;e</button>
          </div>
        </article>

      </div>
    </section>

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
