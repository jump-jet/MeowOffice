<?php

declare(strict_types=1);

/*
 * Tiny server-rendered SVG charts (no JS library). Hover tooltips come from
 * assets/js/dashboard.js reading data-tip on .hit elements.
 *
 * Specs follow the dataviz guidance: bars <= 24px with 4px rounded data-end,
 * 2px lines, >= 8px end dots with a 2px surface ring, hairline solid grid,
 * text in ink colours (never the series colour), selective direct labels,
 * a legend for 2+ series, and a table view for every chart.
 */

const CHART_W = 640;
const CHART_H = 220;
const CHART_PAD = ['l' => 34, 'r' => 30, 't' => 20, 'b' => 26];

/** Axis max split into 4 whole-number steps (counts never get 7.5 ticks). */
function chart_nice_max(float $value): float
{
    $step = max(1, $value / 4);
    $mag = 10 ** floor(log10($step));
    foreach ([1, 2, 5, 10] as $m) {
        if ($step <= $m * $mag) {
            return max(4, $m * $mag * 4);
        }
    }

    return 40 * $mag;
}

/** Whether x label $i should be drawn: every Nth, plus the last one if it has room. */
function chart_show_label(int $i, int $n, int $every): bool
{
    if ($i % $every === 0) {
        return true;
    }

    return $i === $n - 1 && ($n - 1) % $every >= max(1, $every * 0.75);
}

function chart_grid(float $max, float $plotW): string
{
    $out = '';
    $pad = CHART_PAD;
    $plotH = CHART_H - $pad['t'] - $pad['b'];
    for ($i = 0; $i <= 4; $i++) {
        $v = $max / 4 * $i;
        $y = $pad['t'] + $plotH - $plotH * $i / 4;
        $out .= '<line x1="' . $pad['l'] . '" x2="' . ($pad['l'] + $plotW) . '" y1="' . $y . '" y2="' . $y . '" class="grid"/>';
        $out .= '<text x="' . ($pad['l'] - 6) . '" y="' . ($y + 4) . '" class="tick" text-anchor="end">' . e(number_format($v)) . '</text>';
    }

    return $out;
}

function chart_rounded_bar(float $x, float $y, float $w, float $h): string
{
    if ($h <= 0) {
        return '';
    }
    $r = min(4, $h, $w / 2);
    $b = $y + $h;

    return 'M' . $x . ' ' . $b . ' V' . ($y + $r) . ' Q' . $x . ' ' . $y . ' ' . ($x + $r) . ' ' . $y
        . ' H' . ($x + $w - $r) . ' Q' . ($x + $w) . ' ' . $y . ' ' . ($x + $w) . ' ' . ($y + $r) . ' V' . $b . ' Z';
}

/** Show at most ~one x label per 46px. */
function chart_label_every(int $n, float $plotW): int
{
    return max(1, (int) ceil($n / max(1, floor($plotW / 46))));
}

/**
 * Single-series column chart.
 * @param array $points [['label' => 'Mon 3', 'value' => 5, 'tip' => '...'], ...]
 */
function column_chart(array $points, string $title, string $series = 'var(--series-1)'): string
{
    $pad = CHART_PAD;
    $plotW = CHART_W - $pad['l'] - $pad['r'];
    $plotH = CHART_H - $pad['t'] - $pad['b'];
    $n = max(1, count($points));
    $values = array_column($points, 'value');
    $max = chart_nice_max((float) max($values ?: [0]));
    $band = $plotW / $n;
    $barW = min(24, $band * 0.62);
    $every = chart_label_every($n, $plotW);
    $maxIdx = $values ? array_search(max($values), $values, true) : -1;

    $svg = '<svg class="chart" viewBox="0 0 ' . CHART_W . ' ' . CHART_H . '" role="img" aria-label="' . e($title) . '">' . chart_grid($max, $plotW);
    foreach ($points as $i => $p) {
        $h = $plotH * $p['value'] / $max;
        $x = $pad['l'] + $band * $i + ($band - $barW) / 2;
        $y = $pad['t'] + $plotH - $h;
        $svg .= '<path d="' . chart_rounded_bar($x, $y, $barW, $h) . '" fill="' . $series . '"/>';
        if (($i === $maxIdx || $i === $n - 1) && $p['value'] > 0) {
            $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . ($y - 6) . '" class="val" text-anchor="middle">' . (int) $p['value'] . '</text>';
        }
        if (chart_show_label($i, $n, $every)) {
            $svg .= '<text x="' . ($x + $barW / 2) . '" y="' . (CHART_H - 8) . '" class="tick" text-anchor="middle">' . e($p['label']) . '</text>';
        }
        $svg .= '<rect class="hit" x="' . ($pad['l'] + $band * $i) . '" y="' . $pad['t'] . '" width="' . $band . '" height="' . $plotH . '" data-tip="' . e($p['tip']) . '" tabindex="0"><title>' . e($p['tip']) . '</title></rect>';
    }

    return $svg . '<line x1="' . $pad['l'] . '" x2="' . ($pad['l'] + $plotW) . '" y1="' . ($pad['t'] + $plotH) . '" y2="' . ($pad['t'] + $plotH) . '" class="axis"/></svg>';
}

/**
 * Multi-series line chart on one shared axis.
 * @param string[] $labels x labels
 * @param array $series [['name' => 'Created', 'color' => 'var(--series-1)', 'values' => [...]], ...]
 * @param string[] $tips one tooltip per x position
 */
function line_chart(array $labels, array $series, array $tips, string $title): string
{
    $pad = CHART_PAD;
    $plotW = CHART_W - $pad['l'] - $pad['r'];
    $plotH = CHART_H - $pad['t'] - $pad['b'];
    $n = max(2, count($labels));
    $all = [0];
    foreach ($series as $s) {
        $all = array_merge($all, $s['values']);
    }
    $max = chart_nice_max((float) max($all));
    $step = $plotW / ($n - 1);
    $every = chart_label_every($n, $plotW);
    $xy = function (int $i, float $v) use ($pad, $plotH, $step, $max): array {
        return [round($pad['l'] + $step * $i, 1), round($pad['t'] + $plotH - $plotH * $v / $max, 1)];
    };

    $legend = '<div class="legend">';
    foreach ($series as $s) {
        $legend .= '<span><i style="background:' . $s['color'] . '"></i>' . e($s['name']) . '</span>';
    }
    $legend .= '</div>';

    $svg = '<svg class="chart" viewBox="0 0 ' . CHART_W . ' ' . CHART_H . '" role="img" aria-label="' . e($title) . '">' . chart_grid($max, $plotW);
    foreach ($labels as $i => $label) {
        if (chart_show_label($i, count($labels), $every)) {
            $svg .= '<text x="' . $xy($i, 0)[0] . '" y="' . (CHART_H - 8) . '" class="tick" text-anchor="middle">' . e($label) . '</text>';
        }
    }
    $svg .= '<line class="crosshair" x1="0" x2="0" y1="' . $pad['t'] . '" y2="' . ($pad['t'] + $plotH) . '"/>';
    foreach ($series as $s) {
        $pts = [];
        foreach ($s['values'] as $i => $v) {
            $pts[] = implode(',', $xy($i, (float) $v));
        }
        $area = 'M' . $xy(0, 0)[0] . ',' . $xy(0, 0)[1] . ' L' . implode(' L', $pts) . ' L' . $xy(count($pts) - 1, 0)[0] . ',' . $xy(0, 0)[1] . ' Z';
        $svg .= '<path d="' . $area . '" fill="' . $s['color'] . '" opacity="0.1"/>';
        $svg .= '<polyline points="' . implode(' ', $pts) . '" fill="none" stroke="' . $s['color'] . '" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"/>';
    }
    // End dots + end labels (value only; the legend carries the name).
    $ends = [];
    foreach ($series as $s) {
        $last = count($s['values']) - 1;
        [$x, $y] = $xy($last, (float) $s['values'][$last]);
        $ends[] = $y;
        $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="4" fill="' . $s['color'] . '" stroke="var(--surface)" stroke-width="2"/>';
    }
    $collide = count($ends) === 2 && abs($ends[0] - $ends[1]) < 14;
    if (!$collide) {
        foreach ($series as $k => $s) {
            $last = count($s['values']) - 1;
            $svg .= '<text x="' . ($xy($last, 0)[0] + 8) . '" y="' . ($ends[$k] + 4) . '" class="val">' . (int) $s['values'][$last] . '</text>';
        }
    }
    foreach ($tips as $i => $tip) {
        $x = $xy($i, 0)[0];
        $svg .= '<rect class="hit" x="' . ($x - $step / 2) . '" y="' . $pad['t'] . '" width="' . $step . '" height="' . $plotH . '" data-x="' . $x . '" data-tip="' . e($tip) . '" tabindex="0"><title>' . e($tip) . '</title></rect>';
    }

    return $legend . $svg . '</svg>';
}

/** Horizontal ranked bars (HTML), single series, value at the bar tip. */
function bar_list(array $rows): string
{
    $max = max(1, max(array_column($rows, 'value') ?: [1]));
    $out = '<div class="barlist">';
    foreach ($rows as $row) {
        $pct = round($row['value'] / $max * 100, 1);
        $out .= '<div class="barlist-row hit" tabindex="0" data-tip="' . e($row['label'] . ': ' . number_format($row['value'])) . '">'
            . '<span class="barlist-label">' . e($row['label']) . '</span>'
            . '<span class="barlist-track"><span class="barlist-bar" style="width:' . $pct . '%"></span><span class="barlist-val">' . number_format($row['value']) . '</span></span>'
            . '</div>';
    }

    return $out . '</div>';
}

/** Collapsible table view of a chart's data. */
function chart_table(array $headers, array $rows): string
{
    $out = '<details class="chart-data"><summary>View as table</summary><div class="table-wrap"><table><thead><tr>';
    foreach ($headers as $h) {
        $out .= '<th>' . e($h) . '</th>';
    }
    $out .= '</tr></thead><tbody>';
    foreach ($rows as $row) {
        $out .= '<tr>';
        foreach ($row as $cell) {
            $out .= '<td>' . e((string) $cell) . '</td>';
        }
        $out .= '</tr>';
    }

    return $out . '</tbody></table></div></details>';
}
