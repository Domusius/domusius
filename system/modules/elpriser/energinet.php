<?php
// system/modules/elpriser/engines/energinet.php
// Kilde: Energi Data Service (Energinet). Åbne data uden nøgle. Se vilkårene for brug på energidataservice.dk.
//
// Datasættet Elspotprices fik ingen nye priser efter 2025-09-30. Fra 2025-10-01 bruges DayAheadPrices
// (felter: TimeUTC, TimeDK, PriceArea, DayAheadPriceDKK i kr pr. MWh). Ældre dage hentes stadig fra Elspotprices.
// Priserne grupperes pr. time (også når kilden har kvarterspriser), så gennemsnit, højeste og laveste
// er sammenlignelige med de ældre data.

if (!function_exists('elpriser_engine_energinet')) {

    /**
     * Henter og regner én periode (højst ca. 30 dage) for ét datasæt.
     * Giver [ok, fejlbesked, [dato => ['avg'=>, 'max'=>, 'min'=>, 'hours'=>]]].
     */
    function elpriser_energinet_chunk(callable $fetch, string $dataset, string $timeField, string $utcField, string $priceField, string $region, string $from, string $to): array
    {
        $url = 'https://api.energidataservice.dk/dataset/' . $dataset . '?' . http_build_query([
            // Bredere end nødvendigt, så tidszoneforskelle ikke koster en dag. Dagene sorteres lokalt.
            'start'  => date('Y-m-d', strtotime($from . ' -1 day')),
            'end'    => date('Y-m-d', strtotime($to . ' +2 days')),
            'filter' => json_encode(['PriceArea' => [$region]]),
            'sort'   => $timeField . ' ASC',
            'limit'  => 5000,
        ]);

        [$ok, $body] = $fetch($url);
        if (!$ok) {
            return [false, 'Kunne ikke hente fra Energi Data Service: ' . $body, []];
        }
        $json = json_decode($body, true);
        if (!is_array($json) || !isset($json['records']) || !is_array($json['records'])) {
            return [false, 'Uventet svar fra Energi Data Service.', []];
        }

        // dato (dansk tid) => time (UTC) => priser i kr/kWh
        $byDate = [];
        foreach ($json['records'] as $rec) {
            $local = (string)($rec[$timeField] ?? '');
            $utc   = (string)($rec[$utcField] ?? $local);
            $price = $rec[$priceField] ?? null;
            if (!preg_match('/^(\d{4}-\d{2}-\d{2})T\d{2}:/', $local, $m) || $price === null || !is_numeric($price)) {
                continue;
            }
            $hourKey = substr($utc, 0, 13); // ÅÅÅÅ-MM-DDTHH
            $byDate[$m[1]][$hourKey][] = (float)$price / 1000.0;
        }

        $days = [];
        foreach ($byDate as $date => $hours) {
            if ($date < $from || $date > $to) {
                continue;
            }
            $hourly = [];
            foreach ($hours as $values) {
                $hourly[] = array_sum($values) / count($values);
            }
            $days[$date] = [
                'avg'   => array_sum($hourly) / count($hourly),
                'max'   => max($hourly),
                'min'   => min($hourly),
                'hours' => count($hourly),
            ];
        }
        return [true, '', $days];
    }

    function elpriser_engine_energinet(PDO $pdo, string $from, string $to, ?callable $fetcher = null): array
    {
        $fetch  = $fetcher ?? 'elpriser_http_get';
        $region = strtoupper(elpriser_setting($pdo, 'region', 'DK1'));
        if (!in_array($region, ['DK1', 'DK2'], true)) {
            $region = 'DK1';
        }

        $cut   = '2025-09-30';
        $parts = [];
        if ($from <= $cut) {
            $parts[] = ['Elspotprices', 'HourDK', 'HourUTC', 'SpotPriceDKK', $from, min($to, $cut)];
        }
        if ($to > $cut) {
            $parts[] = ['DayAheadPrices', 'TimeDK', 'TimeUTC', 'DayAheadPriceDKK', max($from, '2025-10-01'), $to];
        }

        $saved = 0;
        $skipped = 0;
        foreach ($parts as [$dataset, $timeField, $utcField, $priceField, $a, $b]) {
            $cursor = $a;
            while ($cursor <= $b) {
                $end = min($b, date('Y-m-d', strtotime($cursor . ' +29 days')));
                [$ok, $err, $days] = elpriser_energinet_chunk($fetch, $dataset, $timeField, $utcField, $priceField, $region, $cursor, $end);
                if (!$ok) {
                    return ['ok' => false, 'days' => $saved, 'message' => $err];
                }
                foreach ($days as $date => $d) {
                    // En dag skal være (næsten) komplet: 23-25 timer
                    if ($d['hours'] < 23) {
                        $skipped++;
                        continue;
                    }
                    elpriser_save_day($pdo, $date, $region, $d['avg'], $d['max'], $d['min']);
                    $saved++;
                }
                $cursor = date('Y-m-d', strtotime($end . ' +1 day'));
            }
        }

        $msg = 'Hentede ' . $saved . ' dag(e) fra Energi Data Service for ' . $region . '.';
        if ($skipped > 0) {
            $msg .= ' ' . $skipped . ' dag(e) blev sprunget over, fordi prisen ikke er komplet endnu.';
        }
        return ['ok' => true, 'days' => $saved, 'message' => $msg];
    }
}
