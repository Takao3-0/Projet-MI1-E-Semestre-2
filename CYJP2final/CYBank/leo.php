<?php 
    require_once 'getapikey.php';
?>

<?php

    //Fausse variable d'essais

    $panier_total = 10.00;


    $bool_paiement_traite = false;
    $cybank_transaction_id = null;

    //Cette partie s'execute que quand on a un retour de cy bank.
    if(isset($_GET['montant']) && isset($_GET['transaction']) && isset($_GET['status']) && isset($_GET['vendeur']) && isset($_GET['control']))
    {
        $montant = $_GET['montant']; //Le montant de la commande 
        $transaction = $_GET['transaction'];
        $status = $_GET['status'];
        $vendeur = $_GET['vendeur'];
        $control_output = $_GET['control'];

        $API = getAPIKey($vendeur);

        $control_input = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $status . "#");
        if ($control_input == $control_output)
        {
            if ($status === "accepted")
            {
                $message = "Paiement effectué avec succès";
                $statut = "Payé";
                $cybank_transaction_id = $transaction;
                $bool_paiement_traite = true; //Cette variable est utilisée pour afficher le message de succès ou d'erreur dans le HTML.
            }
            else
            {
                $bool_paiement_traite = true;
                $message = "Paiement refusé";
                $statut = "Erreur paiment CYBANK";
            }
        }
        else
        {
            $message = "Erreur lors de la vérification du paiement";
        }
        //Au dessus pareil touche pas

    }

    function PaimentCYBANK($panier_total)
    {
        $vendeur = "MI-1_E"; //Faut que tu mette ton groupe id
        $API = getAPIKey($vendeur); //Tu peux laisser comme ça
        $transaction = uniqid(); //Tu peux laisser comme ça
        $montant = number_format($panier_total, 2, '.', ''); //Tu peux laisser comme ça
        $retour = "https://alexandre-gourdon.fr/ProjetCYJ/CYJ/CYBank/leo.php";  //L'url de ta page à la place
        $control = md5($API . "#" . $transaction . "#" . $montant . "#" . $vendeur . "#" . $retour . "#"); //Tu peux laisser comme ça

        echo "<form action='https://www.plateforme-smc.fr/cybank/index.php' method='POST'>"; //Tu peux laisser comme ça 
        echo "<input type='hidden' name='transaction' value='$transaction'>"; //Tu peux laisser comme ça
        echo "<input type='hidden' name='montant' value='$montant'>"; //Tu peux laisser comme ça
        echo "<input type='hidden' name='vendeur' value='$vendeur'>"; //Tu peux laisser comme ça
        echo "<input type='hidden' name='retour' value='$retour'>"; //Tu peux laisser comme ça
        echo "<input type='hidden' name='control' value='$control'>"; //Tu peux laisser comme ça
        echo "<input type='submit' value='Payer'>"; //Tu peux laisser comme ça
        echo "</form>"; //Tu peux laisser comme ça

        echo "<script>document.forms[0].submit();</script>"; //Tu peux laisser comme ça
        exit; //Tu peux laisser comme ça
    }

    //Cette partie s'execute que quand on clique sur le bouton payer par carte.
    if(isset($_POST['payer'])) {
        if(isset($_POST['paiement']) && isset($_POST['cb_paiement'])) {
            $paiement = $_POST['paiement'];
            $cb_paiement = $_POST['cb_paiement'];

            switch($paiement)
            {
                case "1":
                    PaimentCYBANK($panier_total	);
                    break;
                case "2":
                    $message = "Paiement par PayPal";
                    break;
                case "3":
                    $message = "Paiement Google Pay";
                    break;
                default: 
                    $message = "Erreur inconnue";
                    break;
            }
        }
    }

    //J'ai mis PayPal et Google Pay mais c'est pour le style surtout c'est bien sur pas fonctionnel.

    
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>

    <?php if (!$bool_paiement_traite): ?>
        <form method="POST" action="leo.php">
            <input type="radio" name="paiement" value="1">
            <label for="paiement">Paiement par carte</label>
            <br>
            <input type="radio" name="paiement" value="2">
            <label for="paiement">Paiement par PayPal</label>
            <br>
            <input type="radio" name="paiement" value="3">
            <label for="paiement">Paiement Google Pay</label>
            <br>
            <input type="checkbox" name="cb_paiement" value="1" required>
            <label for="cb_paiement">J'accepte les conditions générales de vente</label>
            <br>
            <button type="submit" name="payer" value="1">Payer</button>
        </form>
    <?php endif; ?>
    <p><?php echo $message; ?></p>
</body>
</html>