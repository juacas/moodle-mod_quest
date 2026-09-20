<?php
// This file is part of QUESTournament for Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Questournament for Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Score graph endpoint rendering SVG vector chart.
 *
 * @package    mod_quest
 * @copyright  2026 onwards EDUVALab, University of Valladolid
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login($COURSE);

use mod_quest\service\scoring_calculator;

$datestart = required_param('dst', PARAM_INT);
$dateend = required_param('dend', PARAM_INT);
$tinit = required_param('tinit', PARAM_INT);
$initialpoints = required_param('ipoints', PARAM_FLOAT);
$dateanswercorrect = optional_param('daswcorr', 0, PARAM_INT);
$datefirstanswer = optional_param('dfirstansw', 0, PARAM_INT);
$pointsmax = required_param('pointsmax', PARAM_FLOAT);
$pointsmin = optional_param('pointsmin', 0, PARAM_FLOAT);
$width = max(200, min(1200, optional_param('width', 500, PARAM_INT)));
$height = max(120, min(800, optional_param('height', 240, PARAM_INT)));
$format = optional_param('format', 'svg', PARAM_ALPHA);

$chartdata = scoring_calculator::get_chart_data(
    $datestart,
    $dateend,
    $tinit,
    $dateanswercorrect ?: null,
    $datefirstanswer ?: null,
    $initialpoints,
    $pointsmax,
    $pointsmin
);

if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($chartdata);
    exit;
}

// Generate standalone SVG.
header('Content-Type: image/svg+xml');
header('Cache-Control: private, max-age=60');

$padding = 40;
$plotwidth = $width - ($padding * 2);
$plotheight = $height - ($padding * 2);

$ymin = 0;
$ymax = max($pointsmax * 1.15, $initialpoints * 1.2, 10);

$getx = function($val) use ($padding, $plotwidth, $datestart, $dateend) {
    if ($dateend <= $datestart) {
        return $padding;
    }
    return $padding + (($val - $datestart) / ($dateend - $datestart)) * $plotwidth;
};

$gety = function($val) use ($padding, $plotheight, $ymin, $ymax) {
    if ($ymax <= $ymin) {
        return $padding + $plotheight;
    }
    return $padding + $plotheight - (($val - $ymin) / ($ymax - $ymin)) * $plotheight;
};

$pathd = '';
if (!empty($chartdata['actualcurve'])) {
    $first = $chartdata['actualcurve'][0];
    $pathd = 'M ' . round($getx($first['x']), 1) . ' ' . round($gety($first['y']), 1);
    for ($i = 1; $i < count($chartdata['actualcurve']); $i++) {
        $pt = $chartdata['actualcurve'][$i];
        $pathd .= ' L ' . round($getx($pt['x']), 1) . ' ' . round($gety($pt['y']), 1);
    }
}

// Build the SVG in PHP so that every dynamic value is escaped and codechecker can
// inspect the endpoint without parsing a mixed PHP/HTML template.
$escapedsvgpath = htmlspecialchars($pathd, ENT_QUOTES | ENT_XML1, 'UTF-8');
$startlabel = htmlspecialchars(userdate($datestart, '%d/%m'), ENT_QUOTES | ENT_XML1, 'UTF-8');
$endlabel = htmlspecialchars(userdate($dateend, '%d/%m'), ENT_QUOTES | ENT_XML1, 'UTF-8');
$maxlabel = htmlspecialchars((string)round($pointsmax), ENT_QUOTES | ENT_XML1, 'UTF-8');

$svg = [
    '<?xml version="1.0" encoding="UTF-8"?>',
    sprintf(
        '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d">',
        $width,
        $height,
        $width,
        $height
    ),
    '    <rect width="100%" height="100%" fill="#f8f9fa"/>',
    '    <!-- Axes -->',
    sprintf(
        '    <line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#ced4da" stroke-width="1"/>',
        $padding,
        $padding + $plotheight,
        $padding + $plotwidth,
        $padding + $plotheight
    ),
    sprintf(
        '    <line x1="%d" y1="%d" x2="%d" y2="%d" stroke="#ced4da" stroke-width="1"/>',
        $padding,
        $padding,
        $padding,
        $padding + $plotheight
    ),
];

if ($pathd !== '') {
    $svg[] = sprintf(
        '    <path d="%s" fill="none" stroke="#0d6efd" stroke-width="2.5" stroke-linejoin="round"/>',
        $escapedsvgpath
    );
}

$svg[] = '    <!-- Axis labels -->';
$svg[] = sprintf(
    '    <text x="%d" y="%d" font-family="sans-serif" font-size="10" fill="#6c757d">%s</text>',
    $padding,
    $height - 10,
    $startlabel
);
$svg[] = sprintf(
    '    <text x="%d" y="%d" font-family="sans-serif" font-size="10" fill="#6c757d" text-anchor="end">%s</text>',
    $padding + $plotwidth,
    $height - 10,
    $endlabel
);
$svg[] = sprintf(
    '    <text x="%d" y="%d" font-family="sans-serif" font-size="10" fill="#6c757d" text-anchor="end">%s</text>',
    $padding - 5,
    $padding + 10,
    $maxlabel
);
$svg[] = '</svg>';

echo implode("\n", $svg);
