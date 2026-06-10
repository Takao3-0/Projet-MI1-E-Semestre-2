<?php
// petites fonctions partagees par les pages du dossier profil

// cette fonction transforme un statut comme "En preparation" en classe css "en-preparation"
// elle sert dans historique.php et dans Detail.php pour mettre une couleur selon le statut
function statut_slug($statut)
{
    // on enleve d'abord les accents pour que le nom de la classe css reste propre
    $sansAccents = strtr((string) $statut, [
        "à" => "a",
        "â" => "a",
        "ä" => "a",
        "é" => "e",
        "è" => "e",
        "ê" => "e",
        "ë" => "e",
        "î" => "i",
        "ï" => "i",
        "ô" => "o",
        "ö" => "o",
        "ù" => "u",
        "û" => "u",
        "ü" => "u",
        "ç" => "c",
    ]);
    // ensuite on met tout en minuscule et on remplace les espaces et caracteres bizarres par des tirets
    return strtolower(preg_replace("/[^a-z0-9]+/i", "-", $sansAccents));
}
