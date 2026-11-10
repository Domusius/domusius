<?php
// system/modules/elpriser/engines/google_sheets.php
// Kilde: et Google-ark, der er udgivet på nettet som CSV (Filer > Del > Publicer på nettet > CSV).
//
// Arket skal have en overskriftsrække med disse kolonnenavne (rækkefølgen er ligegyldig):
//   dato       en dato, fx 2026-11-05 eller 05-11-2026
//   spot_gns   gennemsnitlig spotpris for dagen i kr pr. kWh, uden afgifter og moms
//   spot_max   højeste timepris (valgfri, ellers bruges spot_gns)
//   spot_min   laveste timepris (valgfri, ellers bruges spot_gns)
// Afgifter og moms lægges på af modulet, så alle kilder regnes ens.

if (!function_exists('elpriser_engine_google_sheets')) {

    /**
     * Tal fra et ark: "0,85", "0.85" eller "1.234,56".
     */
    function elpriser_sheets_number(string $s): ?float
    {
        $s = trim(str_replace(["\xc2\xa0", ' '], '', $s));
        if ($s === '') {
            return null;
        }
        if (strpos($s, ',') !== false) {
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        }
        return is_numeric($s) ? (float)$s : null;
    }

    /**
     * Datoer på formerne ÅÅÅÅ-MM-DD, DD-MM-ÅÅÅÅ, DD/MM/ÅÅÅÅ og DD.MM.ÅÅÅÅ.
     */
    function elpriser_sheets_date(string $s): ?string
    {
        $s = trim($s);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})/', $s, $m)) {
            [$y, $mo, $d] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } elseif (preg_match('/^(\d{1,2})[-\/.](\d{1,2})[-\/.](\d{4})/', $s, $m)) {
            [$d, $mo, $y] = [(int)$m[1], (int)$m[2], (int)$m[3]];
        } else {
            return null;
        }
        return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
    }

    function elpriser_engine_google_sheets(PDO $pdo, string $from, string $to, ?callable $fetcher = null): array
    {
        $fetch  = $fetcher ?? 'elpriser_http_get';
        $region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));
        $url    = elpriser_setting($pdo, 'sheets_url', '');

        if (!elpriser_valid_https_url($url)) {
            return ['ok' => false, 'days' => 0, 'message' => 'Indtast adressen til dit Google-ark (https://...) under Kilde og region.'];
        }

        [$ok, $body] = $fetch($url);
        if (!$ok) {
            return ['ok' => false, 'days' => 0, 'message' => 'Kunne ikke hente arket: ' . $body];
        }

        $lines = preg_split('/\r\n|\n|\r/', trim($body));
        if (count($lines) < 2) {
            return ['ok' => false, 'days' => 0, 'message' => 'Arket er tomt eller har ikke en overskriftsrække.'];
        }

        $delim  = substr_count($lines[0], ';') > substr_count($lines[0], ',') ? ';' : ',';
        $header = array_map(function ($h) {
            return strtolower(trim($h, " \t\"\xEF\xBB\xBF"));
        }, str_getcsv($lines[0], $delim));
        $col = array_flip($header);

        if (!isset($col['dato']) || !isset($col['spot_gns'])) {
            return ['ok' => false, 'days' => 0, 'message' => 'Arket skal have kolonnerne "dato" og "spot_gns" i første række.'];
        }

        $saved = 0;
        $bad = 0;
        foreach (array_slice($lines, 1) as $line) {
            if (trim($line) === '') {
                continue;
            }
            $row  = str_getcsv($line, $delim);
            $date = elpriser_sheets_date($row[$col['dato']] ?? '');
            $avg  = elpriser_sheets_number($row[$col['spot_gns']] ?? '');
            if ($date === null || $avg === null) {
                $bad++;
                continue;
            }
            if ($date < $from || $date > $to) {
                continue;
            }
            $max = isset($col['spot_max']) ? elpriser_sheets_number($row[$col['spot_max']] ?? '') : null;
            $min = isset($col['spot_min']) ? elpriser_sheets_number($row[$col['spot_min']] ?? '') : null;
            elpriser_save_day($pdo, $date, $region, $avg, $max ?? $avg, $min ?? $avg);
            $saved++;
        }

        $msg = 'Hentede ' . $saved . ' dag(e) fra Google-arket.';
        if ($bad > 0) {
            $msg .= ' ' . $bad . ' række(r) kunne ikke læses og blev sprunget over.';
        }
        return ['ok' => true, 'days' => $saved, 'message' => $msg];
    }
}
