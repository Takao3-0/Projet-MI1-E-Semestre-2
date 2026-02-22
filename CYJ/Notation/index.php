<?php require_once '../../../protection.php'; ?>

<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CY RESTAURANT - Votre Avis</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="../index.css">
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

                <form id="ratingForm" class="space-y-8">

                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-utensils cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Qualité gastronomique</h3>
                        </div>
                        <p class="text-xs text-gray-500 italic">Goût, température et présentation de vos plats.</p>
                        <div class="star-rating flex gap-3 text-3xl text-gray-700" data-category="food">
                            <i class="fas fa-star" data-value="1"></i>
                            <i class="fas fa-star" data-value="2"></i>
                            <i class="fas fa-star" data-value="3"></i>
                            <i class="fas fa-star" data-value="4"></i>
                            <i class="fas fa-star" data-value="5"></i>
                        </div>
                    </div>


                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-motorcycle cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Performance Livraison</h3>
                        </div>
                        <p class="text-xs text-gray-500 italic">Rapidité et courtoisie du livreur.</p>
                        <div class="star-rating flex gap-3 text-3xl text-gray-700" data-category="delivery">
                            <i class="fas fa-star" data-value="1"></i>
                            <i class="fas fa-star" data-value="2"></i>
                            <i class="fas fa-star" data-value="3"></i>
                            <i class="fas fa-star" data-value="4"></i>
                            <i class="fas fa-star" data-value="5"></i>
                        </div>
                    </div>


                    <div class="space-y-4">
                        <div class="flex items-center gap-3">
                            <i class="fas fa-pen-nib cy-orange"></i>
                            <h3 class="font-semibold uppercase tracking-wider text-sm">Commentaire</h3>
                        </div>
                        <textarea
                            class="w-full bg-white/5 border border-white/10 rounded-2xl p-4 text-white placeholder:text-gray-600 focus:outline-none focus:border-orange-500/50 transition-colors"
                            rows="4" placeholder="Dites-nous ce que vous avez aimé (ou moins aimé)..."></textarea>
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