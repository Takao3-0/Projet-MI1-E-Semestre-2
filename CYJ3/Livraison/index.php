<?php require_once '../../../protection.php'; ?> <!-- Thibault!!!!! il faut les ../../../protection.php et pas ../../protection.php ..... protection n'est pas dans ProjetCYJ mais html... -->

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../index.css">
    <title>Livraison</title>
</head>
<body>
    <header>
        <div class="Commande">
            <h1 class="num">Commande 001 </h1>
            <span class="statut"> En cours de livraison </span>
        </div>
        <div class="Client">
            <p>Client : Thibault Joubert </p>
            <p>Créneau : 20h-21h </p>
        </div>
    </header>

    <section class="Adresse">
        <h2>Adresse de livraison</h2>
        <p> 
        3 villa Anatole France<br>
        78800 Houilles <br>
        France
        </p>
        <div>
            <a href="https://www.google.com/maps/search/?api=1&query=3+villa+Anatole+France+Houilles">Ouvrir dans Google Maps</a>
        </div>
    </section>

    <section class="Acces">
        <h2>Accès immeuble</h2>
        <ul>
            <li><strong>Batiment :</strong>A</li>
            <li><strong>Code interphone :</strong> 12345</li>
            <li><strong>Etage :</strong> 5 </li>
            <li><strong>Appartement :</strong>501</li>
        </ul>
    </section>

    <section class="Contact">
        <h2>Contact client</h2>
        <p>Téléphone : 06 62 40 23 24</p>
        <div>
            <a href="tel:+33662402324" class="Appeler">Appeler</a>
            <a href="sms:+33662402324" class="SMS">Envoyer un SMS</a>
        </div>
    </section>

    <section class="note">
        <h2>Instruction de livraison</h2>
        <p>
            Le client demande d'appeler avant d'arriver car la sonnette ne marche pas
        </p>
    </section>
</body>
</html>


