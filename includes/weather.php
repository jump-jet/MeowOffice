<?php

declare(strict_types=1);

/*
 * Office Weather: real weather from Open-Meteo (free, no API key) for the city
 * an admin picks, cached in app_meta, plus a playful "office forecast"
 * computed from what is happening inside MeowOffice.
 */

const WEATHER_CACHE_SECONDS = 1200;

function http_get_json(string $url, int $timeout = 4): ?array
{
    $body = false;
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => $timeout,
            CURLOPT_USERAGENT => 'MeowOffice/1.0',
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200) {
            $body = false;
        }
    } elseif (ini_get('allow_url_fopen')) {
        $ctx = stream_context_create(['http' => ['timeout' => $timeout, 'header' => "User-Agent: MeowOffice/1.0\r\n"]]);
        $body = @file_get_contents($url, false, $ctx);
    }
    if ($body === false || $body === '') {
        return null;
    }
    $data = json_decode((string) $body, true);

    return is_array($data) ? $data : null;
}

function weather_location(): ?array
{
    $raw = meta_get('weather_location');
    $loc = $raw ? json_decode($raw, true) : null;

    return is_array($loc) && isset($loc['lat'], $loc['lon'], $loc['name']) ? $loc : null;
}

/** Look up a city and save it. Returns an error message, or null on success. */
function weather_set_city(string $city): ?string
{
    $city = trim($city);
    if ($city === '' || mb_strlen($city) > 80) {
        return 'Type a city name.';
    }
    $data = http_get_json('https://geocoding-api.open-meteo.com/v1/search?count=1&language=en&format=json&name=' . rawurlencode($city));
    if ($data === null) {
        return 'Could not reach the weather service. Check the internet connection.';
    }
    $hit = $data['results'][0] ?? null;
    if (!$hit) {
        return 'No city called "' . $city . '" found.';
    }
    $name = $hit['name'] . (isset($hit['country']) ? ', ' . $hit['country'] : '');
    meta_set('weather_location', json_encode([
        'name' => $name,
        'lat' => (float) $hit['latitude'],
        'lon' => (float) $hit['longitude'],
    ]));
    meta_set('weather_cache', '');

    return null;
}

/**
 * Current weather + 3-day forecast for the saved city.
 * @return array{status: string, location?: array, current?: array, daily?: array, fetched_at?: int}
 */
function weather_current(): array
{
    $loc = weather_location();
    if (!$loc) {
        return ['status' => 'unset'];
    }

    $cache = json_decode((string) meta_get('weather_cache', ''), true);
    if (is_array($cache) && ($cache['fetched_at'] ?? 0) > time() - WEATHER_CACHE_SECONDS && ($cache['key'] ?? '') === $loc['name']) {
        return $cache;
    }

    $url = 'https://api.open-meteo.com/v1/forecast?' . http_build_query([
        'latitude' => $loc['lat'],
        'longitude' => $loc['lon'],
        'current' => 'temperature_2m,apparent_temperature,relative_humidity_2m,weather_code,wind_speed_10m,is_day',
        'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max',
        'timezone' => 'auto',
        'forecast_days' => 3,
    ]);
    $data = http_get_json($url);
    if ($data === null || !isset($data['current'])) {
        meta_set('weather_last_error', date('Y-m-d H:i:s'));
        // Serve stale data rather than nothing.
        return is_array($cache) && isset($cache['current']) ? $cache + ['stale' => true] : ['status' => 'offline', 'location' => $loc];
    }

    $daily = [];
    foreach (($data['daily']['time'] ?? []) as $i => $day) {
        $daily[] = [
            'date' => $day,
            'code' => (int) $data['daily']['weather_code'][$i],
            'max' => round((float) $data['daily']['temperature_2m_max'][$i]),
            'min' => round((float) $data['daily']['temperature_2m_min'][$i]),
            'rain' => (int) ($data['daily']['precipitation_probability_max'][$i] ?? 0),
        ];
    }
    $result = [
        'status' => 'ok',
        'key' => $loc['name'],
        'location' => $loc,
        'fetched_at' => time(),
        'current' => [
            'temp' => round((float) $data['current']['temperature_2m']),
            'feels' => round((float) $data['current']['apparent_temperature']),
            'humidity' => (int) $data['current']['relative_humidity_2m'],
            'wind' => round((float) $data['current']['wind_speed_10m']),
            'code' => (int) $data['current']['weather_code'],
            'day' => (bool) $data['current']['is_day'],
        ],
        'daily' => $daily,
    ];
    meta_set('weather_cache', json_encode($result));
    meta_set('weather_last_ok', date('Y-m-d H:i:s'));

    return $result;
}

/** WMO weather code => [emoji, label, animal comment]. */
function weather_code_info(int $code, bool $day = true): array
{
    if ($code === 0) {
        return $day ? ['☀️', 'Clear sky', 'Prime sunbeam napping weather.'] : ['🌙', 'Clear night', 'Perfect night for zoomies.'];
    }
    if ($code <= 2) {
        return [$day ? '🌤️' : '🌙', 'Partly cloudy', 'Some sun, some shade. Pick your windowsill.'];
    }
    if ($code === 3) {
        return ['☁️', 'Overcast', 'Grey skies. Extra blankets recommended.'];
    }
    if ($code <= 48) {
        return ['🌫️', 'Foggy', 'Whiskers on high alert in the fog.'];
    }
    if ($code <= 57) {
        return ['🌦️', 'Drizzle', 'Light drizzle. Paws may get damp.'];
    }
    if ($code <= 67 || ($code >= 80 && $code <= 82)) {
        return ['🌧️', 'Rain', 'Rainy day. Stay in and file paperwork.'];
    }
    if ($code <= 77 || ($code >= 85 && $code <= 86)) {
        return ['❄️', 'Snow', 'Snow! Penguin is thrilled.'];
    }
    if ($code >= 95) {
        return ['⛈️', 'Thunderstorm', 'Thunder! Everyone under the desk.'];
    }

    return ['🌡️', 'Mixed weather', 'Unpredictable, like a cat at 3am.'];
}

/** The playful "office forecast" built from in-app activity. */
function office_forecast(): array
{
    $pdo = db();
    $today = date('Y-m-d');
    $one = function (string $sql, array $params = []) use ($pdo): int {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    };
    $overdue = $one("SELECT COUNT(*) FROM tasks WHERE status = 'pending' AND due_date < ?", [$today]);
    $doneToday = $one("SELECT COUNT(*) FROM tasks WHERE status = 'completed' AND completed_at >= ?", [$today . ' 00:00:00']);
    $failed = $one("SELECT COUNT(*) FROM activity_logs WHERE action = 'login_failed' AND created_at >= NOW() - INTERVAL 1 DAY");
    $active = $one('SELECT COUNT(DISTINCT user_id) FROM activity_logs WHERE created_at >= NOW() - INTERVAL 1 HOUR');

    if ($failed >= 10) {
        $f = ['⛈️', 'Security storm warning', "$failed failed logins in 24h. Check the Notification Center."];
    } elseif ($overdue >= 5) {
        $f = ['🌧️', 'Heavy overdue showers', "$overdue tasks are past due. Umbrellas and to-do lists out."];
    } elseif ($overdue > 0) {
        $f = ['🌦️', 'Scattered overdue showers', "$overdue overdue " . ($overdue === 1 ? 'task' : 'tasks') . ' drifting around.'];
    } elseif ($doneToday >= 5) {
        $f = ['☀️', 'Sunny productivity', "$doneToday tasks done today. The office is glowing."];
    } elseif ($active === 0) {
        $f = ['🌙', 'Calm and sleepy', 'Nobody active in the last hour. Shhh.'];
    } else {
        $f = ['🌤️', 'Mostly purrfect', 'No overdue tasks. Light breeze of productivity.'];
    }

    return ['emoji' => $f[0], 'title' => $f[1], 'detail' => $f[2], 'overdue' => $overdue, 'done_today' => $doneToday, 'active' => $active];
}
