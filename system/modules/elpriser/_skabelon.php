<?php
// system/modules/elpriser/engines/_skabelon.php
// Skabelon til en ny kilde. Filer, hvis navn begynder med _, vises ikke i listen over kilder.
//
// Sådan laver du en ny kilde:
// 1. Kopiér filen, og kald kopien fx minkilde.php (små bogstaver, tal og _, må ikke begynde med _).
// 2. Omdøb funktionen herunder til elpriser_engine_minkilde.
// 3. Hent priserne for hver dag mellem $from og $to (format ÅÅÅÅ-MM-DD) som kr pr. kWh UDEN afgifter og moms.
// 4. Kald elpriser_save_day() for hver dag. Den lægger afgifter og moms på og gemmer dagen.
// 5. Brug $fetcher (eller elpriser_http_get) til at hente fra internettet. Kun https er tilladt.

if (!function_exists('elpriser_engine_minkilde')) {
    function elpriser_engine_minkilde(PDO $pdo, string $from, string $to, ?callable $fetcher = null): array
    {
        $fetch  = $fetcher ?? 'elpriser_http_get';
        $region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));

        // [$ok, $body] = $fetch('https://eksempel.dk/priser.json');
        // ... læs $body, og find gennemsnit, højeste og laveste pris for hver dag ...
        // elpriser_save_day($pdo, '2026-01-01', $region, $gennemsnit, $hoejeste, $laveste);

        return ['ok' => false, 'days' => 0, 'message' => 'Skabelonen henter ikke noget.'];
    }
}
