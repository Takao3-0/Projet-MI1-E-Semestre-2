<?php 
require_once '../../../protection.php'; 
$pdo_users = $pdo; 
require_once '../../../../db_config_yumland.php';
$pdo_commandes = $pdo; 

$stmt = $pdo_users->prepare("SELECT * FROM users WHERE username = ?");
$stmt->execute([$user_actuel['username']]);
$infos_completas_user = $stmt->fetch();


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $commentaire = $_POST['commentaire'] ?? '';
        $note_livraison = $_POST['note_livraison'] ?? 0;
        $note_qualite = $_POST['note_qualite'] ?? 0;

        $id_client   = $infos_completas_user['id'] ?? 0; 
        $nom_client  = $infos_completas_user['username'] ?? 'Anonyme';
        $mail_client = $infos_completas_user['email'] ?? 'anonyme@cy-restaurant.fr';

        $sql = "INSERT INTO avis_clients (id_client, nom_client, mail_client, commentaire, note_livraison, note_qualite) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $stmt = $pdo_commandes->prepare($sql);
        
        if ($stmt->execute([$id_client, $nom_client, $mail_client, $commentaire, $note_livraison, $note_qualite])) {
            echo "<script>
                alert('Avis enregistré avec succès !');
                window.location.href = '/ProjetCYJ/CYJ/index.php';
            </script>";
            exit();
        }

    } catch (PDOException $e) {
        die("Erreur SQL : " . $e->getMessage());
    }

}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CY RESTAURANT - Votre Avis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../index.css">
    <link rel="stylesheet" href="../css/profil.css">
    <style>
        /* 1. On cache les boutons radio */
        .hidden-radio {
            position: absolute; /* Mieux que display:none pour l'accessibilité */
            opacity: 0;
            width: 0;
            height: 0;
        }

        /* 2. Style par défaut (Gris) */
        .star-rating label {
            color: #374151; /* text-gray-700 */
            cursor: pointer;
            transition: color 0.2s ease;
        }

        /* 3. L'EFFET MAGIQUE : Remplissage dans le bon sens */
        /* On utilise :has() pour dire "Si cet élément contient un input coché,
        colore toutes les étoiles qui sont après" */
        
        /* Quand on SURVOLE une étoile */
        .star-rating label:hover,
        .star-rating label:hover ~ label {
            color: #f97316 !important; /* Orange */
        }

        /* Quand on CLIQUE (Sélectionné) */
        /* On colore l'étoile cliquée ET toutes celles qui la suivent */
        .star-rating input:checked ~ label {
            color: #f97316 !important; /* Orange */
        }
    </style>
</head>

<body class="gradient-min-h-screen flex items-center justify-center p-4">
    
    <div class="max-w-2xl w-full">

        <div class="text-center mb-8">
            <h1 class="text-3xl font-black tracking-tighter italic">
                <span class="text-white">CY</span> <span class="cy-orange">RESTAURANT</span>
            </h1>
            <p class="text-gray-400 text-sm mt-2">Merci d'avoir choisi l'excellence culinaire.</p>
        </div>

        <div class="glass-card rounded-3xl p-8 md:p-12 shadow-2xl relative overflow-hidden">

            <div class="absolute -top-24 -left-24 w-48 h-48 bg-orange-600/10 blur-[100px] rounded-full"></div>

            <div class="relative z-10">
                <h2 class="text-2xl font-bold text-center mb-2 uppercase tracking-widest">
                    VOTRE ÉVALUATION
                </h2>
                <p class="text-gray-400 text-center text-sm mb-8">Votre retour nous aide à perfectionner nos recettes.
                </p>


                <div class="flex items-center justify-between p-4 bg-white/5 rounded-2xl mb-10 border border-white/10">
                    <div class="flex items-center gap-4">
                        <div class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-2xl">
                            📦
                        </div>
                        <div>
                            <p class="text-xs text-gray-500 uppercase font-semibold">Commande</p>
                            <p class="font-bold text-lg text-white">#CY-7742</p>
                        </div>
                    </div>
                    <div class="order-tag px-3 py-1 rounded-full text-xs font-bold flex items-center gap-1">
                        <i class="fas fa-check-circle"></i> LIVRÉE
                    </div>
                </div>

                <form id="ratingForm" class="space-y-8" method="POST" action="">

                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-utensils cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Qualité gastronomique</h3>
                        </div>
                        <p class="text-xs text-gray-500 italic">Goût, température et présentation de vos plats.</p>
                        <div class="star-rating flex flex-row-reverse justify-end gap-3 text-3xl">
                            <input type="radio" name="note_qualite" value="5" id="q5" class="hidden-radio" required>
                            <label for="q5" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_qualite" value="4" id="q4" class="hidden-radio">
                            <label for="q4" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_qualite" value="3" id="q3" class="hidden-radio">
                            <label for="q3" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_qualite" value="2" id="q2" class="hidden-radio">
                            <label for="q2" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_qualite" value="1" id="q1" class="hidden-radio">
                            <label for="q1" class="fas fa-star cursor-pointer"></label>
                        </div>
                    </div>


                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-motorcycle cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Performance Livraison</h3>
                        </div>
                        <p class="text-xs text-gray-500 italic">Rapidité et courtoisie du livreur.</p>
                        <div class="star-rating flex flex-row-reverse justify-end gap-3 text-3xl">
                            <input type="radio" name="note_livraison" value="5" id="l5" class="hidden-radio" required>
                            <label for="l5" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_livraison" value="4" id="l4" class="hidden-radio">
                            <label for="l4" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_livraison" value="3" id="l3" class="hidden-radio">
                            <label for="l3" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_livraison" value="2" id="l2" class="hidden-radio">
                            <label for="l2" class="fas fa-star cursor-pointer"></label>

                            <input type="radio" name="note_livraison" value="1" id="l1" class="hidden-radio">
                            <label for="l1" class="fas fa-star cursor-pointer"></label>
                        </div>
                    </div>


                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-pen-nib cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Commentaire</h3>
                        </div>
                        <textarea name="commentaire"
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white placeholder:text-gray-600 focus:outline-none focus:border-orange-500/50 transition-colors"
                            rows="4" placeholder="Dites-nous ce que vous avez aimé (ou moins aimé)..." required></textarea>
                    </div>


                    <button type="submit"
                        class="w-full bg-cy-orange text-white py-5 rounded-2xl font-bold text-lg uppercase tracking-widest btn-glow transition-all active:scale-[0.98] flex items-center justify-center gap-3">
                        <i class="fas fa-paper-plane"></i>
                        Envoyer le rapport
                    </button>
                </form>
            </div>
        </div>


        <div id="successMessage"
            class="hidden fixed inset-0 bg-black/80 backdrop-blur-md z-50 flex items-center justify-center p-6">
            <div class="glass-card p-10 rounded-3xl text-center max-w-sm border-orange-500/30">
                <div
                    class="w-20 h-20 bg-green-500/20 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">
                    <i class="fas fa-check"></i>
                </div>
                <h3 class="text-2xl font-bold mb-2">Merci !</h3>
                <p class="text-gray-400">Votre évaluation a été transmise avec succès à l'équipe CY Restaurant.</p>
                <button onclick="location.reload()"
                    class="mt-8 text-orange-500 font-bold uppercase tracking-widest text-sm hover:underline">
                    Retour au site
                </button>
            </div>
        </div>
    </div>

</body>

</html>