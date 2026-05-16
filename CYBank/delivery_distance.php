<?php
declare(strict_types=1);


const DELIVERY_CONFIG_PATH = '/var/www/oc_delivery_config.php';

const DELIVERY_CONFIG_FALLBACK = '/var/www/oc_ai_config.php';

// Adresse
const RESTAURANT_ADDRESS = '55 rue du Faubourg-Saint-Honoré, Paris';

// Grille tarifaire
const DELIVERY_PRICING = [
    'base_fee'       => 1.50,   // frais fixes (€)
    'per_km'         => 0.80,   // coût par km (€)
    'min_fee'        => 2.50,   // minimum de frais de livraison (€)
    'max_distance_km' => 20.0,  // distance max acceptée (km)
    'free_above'     => 0.0,    // livraison gratuite au-dessus de X€ de commande 
];


function deliveryEstimate(string $clientAddress, ?float $orderTotal = null): array
{
    $clientAddress = trim($clientAddress);
    if ($clientAddress === '') {
        return deliveryError('Veuillez saisir une adresse.');
    }

    $config = deliveryLoadConfig();
    if ($config === null) {
        return deliveryError('Configuration livraison manquante.');
    }

    $apiKey = (string)($config['google_maps']['api_key'] ?? '');
    if ($apiKey === '') {
        return deliveryError('Clé API Google Maps non configurée.');
    }

    // Appel Google Maps Directions API
    $route = googleDirections(RESTAURANT_ADDRESS, $clientAddress, $apiKey);

    if ($route === null) {
        return deliveryError('Impossible de calculer l\'itinéraire. Vérifiez l\'adresse.');
    }

    $distanceKm = round($route['distance_m'] / 1000, 1);
    $durationMin = (int)ceil($route['duration_s'] / 60);

    // Vérifier la distance max
    $maxKm = DELIVERY_PRICING['max_distance_km'];
    if ($maxKm > 0 && $distanceKm > $maxKm) {
        return deliveryError(
            "Adresse trop éloignée ({$distanceKm} km). Livraison possible jusqu'à {$maxKm} km.",
            ['distance_km' => $distanceKm, 'duration_min' => $durationMin]
        );
    }

    // Calcul du coût
    $cost = deliveryComputeCost($distanceKm, $orderTotal);

    return [
        'ok'              => true,
        'distance_km'     => $distanceKm,
        'distance_text'   => $route['distance_text'],
        'duration_min'    => $durationMin,
        'duration_text'   => $route['duration_text'],
        'cost'            => $cost,
        'label'           => number_format($cost, 2, ',', '') . ' €',
        'free_delivery'   => $cost === 0.0,
        'client_address'  => $route['end_address'],
        'error'           => null,
    ];
}

// Calcul du coût de livraison

function deliveryComputeCost(float $distanceKm, ?float $orderTotal = null): float
{
    $p = DELIVERY_PRICING;

    // Livraison gratuite si le montant de commande dépasse le seuil
    if ($p['free_above'] > 0 && $orderTotal !== null && $orderTotal >= $p['free_above']) {
        return 0.0;
    }

    $cost = $p['base_fee'] + ($distanceKm * $p['per_km']);
    $cost = max($cost, $p['min_fee']);

    return round($cost, 2);
}

// Appel Google Maps Directions API

function googleDirections(string $origin, string $destination, string $apiKey): ?array
{
    $params = http_build_query([
        'origin'      => $origin,
        'destination' => $destination,
        'mode'        => 'driving',
        'language'    => 'fr',
        'key'         => $apiKey,
    ]);

    $url = 'https://maps.googleapis.com/maps/api/directions/json?' . $params;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode >= 400) {
        error_log('[delivery] cURL error: ' . ($curlErr ?: 'HTTP ' . $httpCode));
        return null;
    }

    $data = json_decode((string)$response, true);

    // Google retourne "OK", "NOT_FOUND", "ZERO_RESULTS", etc.
    $status = (string)($data['status'] ?? '');
    if ($status !== 'OK') {
        error_log('[delivery] Directions API status: ' . $status);
        return null;
    }

    $leg = $data['routes'][0]['legs'][0] ?? null;
    if ($leg === null) {
        return null;
    }

    return [
        'distance_m'    => (int)($leg['distance']['value'] ?? 0),
        'distance_text' => (string)($leg['distance']['text'] ?? ''),
        'duration_s'    => (int)($leg['duration']['value'] ?? 0),
        'duration_text' => (string)($leg['duration']['text'] ?? ''),
        'end_address'   => (string)($leg['end_address'] ?? ''),
    ];
}

// Helpers

function deliveryLoadConfig(): ?array
{
    foreach ([DELIVERY_CONFIG_PATH, DELIVERY_CONFIG_FALLBACK] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $config = @include $path;
        if (!is_array($config)) {
            continue;
        }
        $key = (string) ($config['google_maps']['api_key'] ?? '');
        if ($key !== '' && $key !== 'PLACEHOLDER') { //sert 2 deuxième garde fou
            return $config;
        }
    }

    return null;
}

/**
 * Adresse complète pour l'API Directions (ligne + CP ville + pays).
 */
function deliveryFormatClientAddress(string $line1, string $postal, string $city, string $country = ''): string
{
    $line1 = trim($line1);
    $postal = trim($postal);
    $city = trim($city);
    $country = trim($country);
    $cityLine = trim($postal . ' ' . $city);
    $parts = array_filter([$line1, $cityLine, $country], static fn (string $p): bool => $p !== '');

    return implode(', ', $parts);
}

function deliveryError(string $message, array $extra = []): array
{
    return array_merge([
        'ok'    => false,
        'error' => $message,
        'cost'  => null,
        'label' => null,
    ], $extra);
}
