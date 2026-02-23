<?php require_once '../../../protection.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="admin.css">
    <title>Admin</title>
</head>
<body>
    <header>
        <h1>Gestion des utilisateurs</h1>
    </header>

    <section class="Filtres">
        <div class="recherche">
            <label for="rechercher">Rechercher :</label>
            <input type="text" placeholder="Nom ou email">
        </div>
        <div class="filtres">
            <label for="filtres">Filtrer :</label>
            <select>
                <option>Tous les utilisateurs</option>
                <option>Ayant commandé</option>
                <option>Comptes inactifs</option>
            </select>
        </div>
        <div class="trier">
            <label for="trie">Trier :</label>
            <select>
                <option>Récent</option>
                <option>Ancien</option>
                <option>Nombre de commandes</option>
            </select>
        </div>
    </section>

    <section class="table">
        <table>
            <thead>
                <tr>
                    <th>Nom</th>
                    <th>Email</th>
                    <th>Date d'inscription</th>
                    <th>Commandes</th>
                    <th>Statut</th>
                </tr>
            </thead>

            <tbody>
                <tr>
                    <td>Thibault</td>
                    <td>Thibault@mail.com</td>
                    <td>18/01/2026</td>
                    <td>1</td>
                    <td>Actif</td>
                </tr>

                <tr>
                    <td>Nicolas</td>
                    <td>Nicolas@mail.com</td>
                    <td>04/12/2025</td>
                    <td>0</td>
                    <td>Bloqué</td>
                </tr>

                <tr>
                    <td>Alexandre</td>
                    <td>Alexandre@mail.com</td>
                    <td>1/09/2025</td>
                    <td>54</td>
                    <td>Actif</td>
                </tr>
            </tbody>
        </table>
    </section>
    <nav>
        <button>Précédent</button>
        <span>Page 1 sur 3</span>
        <button>Suivant</button>
    </nav>
</body>
</html>