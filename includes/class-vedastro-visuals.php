<?php
defined('ABSPATH') || exit;

/** Dedicated presentation for charts and twelve-sign point tables. */
final class VedAstro_Visuals {
    public static function shortcode(array $a, $tag) {
        $title = $a['title'] ?? ($tag === 'vedastro_chart' ? 'Birth chart' : 'Ashtakavarga');
        $open = '<section class="vedastro-result"><h3>' . esc_html($title) . '</h3>';
        if (empty($a['date'])) { return self::error($open, 'Set date to the birth date and time (YYYY-MM-DD HH:MM).'); }
        $time = VedAstro_Plugin::time($a);
        if (is_wp_error($time)) { return self::error($open, $time->get_error_message()); }
        $ayanamsa = $a['ayanamsa'] ?? '';
        if ($tag === 'vedastro_chart') {
            $style = strtolower($a['style'] ?? 'south');
            if (!in_array($style, array('north', 'south'), true)) { return self::error($open, 'Choose style="north" or style="south".'); }
            $chart = $a['chart'] ?? 'RasiD1';
            $types = array('BhavaChalit', 'RasiD1', 'HoraD2', 'DrekkanaD3', 'ChaturthamshaD4', 'SaptamshaD7', 'NavamshaD9', 'DashamamshaD10', 'DwadashamshaD12', 'ShodashamshaD16', 'VimshamshaD20', 'ChaturvimshamshaD24', 'BhamshaD27', 'TrimshamshaD30', 'KhavedamshaD40', 'AkshavedamshaD45', 'ShashtyamshaD60');
            if (!in_array($chart, $types, true)) { return self::error($open, 'Choose a supported chart type such as RasiD1 or NavamshaD9.'); }
            $result = VedAstro_Client::calculate($style === 'north' ? 'NorthIndianChart' : 'SouthIndianChart', array('time' => $time, 'chartType' => $chart), $ayanamsa);
            if (is_wp_error($result)) { return self::error($open, $result->get_error_message()); }
            if (!is_string($result) || strpos($result, '<svg') === false) { return self::error($open, 'VedAstro did not return a chart. Please try again later.'); }
            // SVG is an image, never active inline markup, an object or an iframe.
            $src = 'data:image/svg+xml;base64,' . base64_encode($result);
            return $open . '<img class="vedastro-chart" width="1000" height="1000" src="' . esc_attr($src) . '" alt="' . esc_attr(ucfirst($style) . ' Indian ' . $chart . ' birth chart') . '"></section>';
        }
        $variant = strtolower($a['table'] ?? 'both');
        if (!in_array($variant, array('both', 'sarva', 'bhinna'), true)) { return self::error($open, 'Choose table="both", "sarva" or "bhinna".'); }
        $html = '';
        foreach (array('sarva' => 'SarvashtakavargaChart', 'bhinna' => 'BhinnashtakavargaChart') as $name => $endpoint) {
            if ($variant !== 'both' && $variant !== $name) { continue; }
            $result = VedAstro_Client::calculate($endpoint, array('birthTime' => $time), $ayanamsa);
            if (is_wp_error($result)) { return self::error($open, $result->get_error_message()); }
            $table = self::table($result, $name === 'sarva' ? 'Sarvashtakavarga' : 'Bhinnashtakavarga');
            if (is_wp_error($table)) { return self::error($open, $table->get_error_message()); }
            $html .= $table;
        }
        return $open . $html . '</section>';
    }

    public static function table($data, $caption) {
        if (!is_array($data) || !$data) { return new WP_Error('vedastro_table', 'VedAstro did not return an Ashtakavarga table.'); }
        $html = '<div class="vedastro-table-scroll" role="region" aria-label="' . esc_attr($caption) . ' table" tabindex="0"><table class="vedastro-table vedastro-points"><caption>' . esc_html($caption) . ' · zodiac signs 1–12 (Aries–Pisces)</caption><thead><tr><th scope="col">Planet</th>';
        for ($i = 1; $i <= 12; $i++) { $html .= '<th scope="col">' . $i . '</th>'; }
        $html .= '<th scope="col">Total</th></tr></thead><tbody>';
        foreach ($data as $label => $row) {
            if (!is_array($row) || !isset($row['Rows'], $row['Total']) || !is_array($row['Rows']) || count($row['Rows']) !== 12 || !is_numeric($row['Total'])) { return new WP_Error('vedastro_table', 'VedAstro returned an incomplete Ashtakavarga table.'); }
            $html .= '<tr><th scope="row">' . esc_html($label) . '</th>';
            foreach ($row['Rows'] as $value) {
                if (!is_numeric($value)) { return new WP_Error('vedastro_table', 'VedAstro returned an invalid point value.'); }
                $html .= '<td>' . esc_html($value) . '</td>';
            }
            $html .= '<td><strong>' . esc_html($row['Total']) . '</strong></td></tr>';
        }
        return $html . '</tbody></table></div>';
    }

    private static function error($open, $message) {
        return $open . '<p class="vedastro-error" role="status">' . esc_html($message) . '</p></section>';
    }
}
