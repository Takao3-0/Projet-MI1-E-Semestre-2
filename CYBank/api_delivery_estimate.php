<?php

declare(strict_types=1);

// petit point d'api appele en ajax par la page de paiement
// il recoit une adresse en json et renvoie le cout de livraison estime, en json aussi
require_once __DIR__ . "/../../../protection.php";

header("Content-Type: application/json; charset=utf-8");

// il faut etre connecte pour demander une estimation
if (empty($est_connecte)) {
    http_response_code(401);
    echo json_encode(["ok" => false, "error" => "Connexion requise."], JSON_UNESCAPED_UNICODE);
    exit();
}

// on n'accepte que le post : les donnees arrivent dans le corps de la requete
if (($_SERVER["REQUEST_METHOD"] ?? "") !== "POST") {
    http_response_code(405);
    echo json_encode(["ok" => false, "error" => "Méthode non autorisée."], JSON_UNESCAPED_UNICODE);
    exit();
}

require_once __DIR__ . "/delivery_distance.php";

// on lit le corps json envoye par le javascript et on verifie que c'est bien du json
$raw = file_get_contents("php://input");
$input = is_string($raw) ? json_decode($raw, true) : null;
if (!is_array($input)) {
    http_response_code(400);
    echo json_encode(["ok" => false, "error" => "Corps JSON invalide."], JSON_UNESCAPED_UNICODE);
    exit();
}

$address = trim((string) ($input["address"] ?? ""));
$orderTotal = isset($input["order_total"]) ? (float) $input["order_total"] : null;

// on calcule l'estimation de livraison et on la renvoie en json
$result = deliveryEstimate($address, $orderTotal);

echo json_encode($result, JSON_UNESCAPED_UNICODE);
