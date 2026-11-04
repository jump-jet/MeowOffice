<?php

declare(strict_types=1);

/*
 * Chubby party-style animal avatars, drawn as inline SVG (viewBox 0 0 100 100).
 *
 * Every avatar is a round plush head (cx 50, cy 56, rx 36, ry 32) plus:
 *   - parts drawn *behind* the head (ears, mane, wool, spikes)
 *   - markings drawn *on* the head (patches, stripes, cheeks, snouts)
 *   - a face (eyes, blush, nose, mouth) built from a few presets
 *   - optional props on top (party hat, crown, bandana)
 * SVG never contains user input; keys are validated against the lists below.
 */

const AVATAR_LINE = '#4a2f25';

/** Desk animals anyone can pick. key => [name, tile background]. */
function animal_choices_meta(): array
{
    return [
        'cat' => ['Mochi the Cat', '#ffe1cc'],
        'bunny' => ['Bean the Bunny', '#efe6ff'],
        'puppy' => ['Toast the Puppy', '#ffe6cc'],
        'panda' => ['Bao the Panda', '#e6f4ea'],
        'fox' => ['Maple the Fox', '#ffe0d1'],
        'owl' => ['Noodle the Owl', '#e3eefc'],
        'bear' => ['Waffles the Bear', '#f5e6d8'],
        'koala' => ['Gumdrop the Koala', '#e4ecf5'],
        'pig' => ['Truffle the Pig', '#ffe4ec'],
        'frog' => ['Pickle the Frog', '#e2f6d8'],
        'penguin' => ['Sushi the Penguin', '#dff1ff'],
        'hamster' => ['Peanut the Hamster', '#fff1d6'],
        'duck' => ['Puddle the Duck', '#e1f3ff'],
        'raccoon' => ['Bandit the Raccoon', '#ebe8f2'],
        'sheep' => ['Cloud the Sheep', '#e9f7f1'],
        'dino' => ['Rex the Dino', '#fff0d9'],
    ];
}

function animal_choices(): array
{
    return array_map(function (array $meta): string {
        return $meta[0];
    }, animal_choices_meta());
}

/** Legacy emoji, still used in plain-text places (notification text). */
function animal_emoji(string $animal): string
{
    return [
        'cat' => '🐱', 'bunny' => '🐰', 'puppy' => '🐶', 'panda' => '🐼', 'fox' => '🦊', 'owl' => '🦉',
        'bear' => '🐻', 'koala' => '🐨', 'pig' => '🐷', 'frog' => '🐸', 'penguin' => '🐧', 'hamster' => '🐹',
        'duck' => '🦆', 'raccoon' => '🦝', 'sheep' => '🐑', 'dino' => '🦖',
    ][$animal] ?? '🐾';
}

// ---- SVG building blocks ----------------------------------------------------

function svg_mirror(string $parts): string
{
    return $parts . '<g transform="matrix(-1 0 0 1 100 0)">' . $parts . '</g>';
}

function svg_shape(string $tag, string $attrs, string $fill, bool $outline = true): string
{
    $stroke = $outline ? ' stroke="' . AVATAR_LINE . '" stroke-width="2.5" stroke-linejoin="round"' : '';

    return '<' . $tag . ' ' . $attrs . ' fill="' . $fill . '"' . $stroke . '/>';
}

function svg_head(string $fill): string
{
    return svg_shape('ellipse', 'cx="50" cy="56" rx="36" ry="32"', $fill);
}

/**
 * Face presets.
 * eyes: dot | shine | heart | closed | sly | wide | glow
 * mouth: cat | smile | open | kiss | smirk | tongue | beak | bill | frog | none
 */
function svg_face(array $o = []): string
{
    $o += [
        'eyes' => 'dot', 'mouth' => 'smile', 'eyeY' => 54, 'eyeX' => 14, 'eyeR' => 5.2,
        'nose' => '#5c3d2e', 'noseY' => 62, 'blush' => true, 'iris' => '#ffd84d', 'lid' => '#ffb86b',
    ];
    $y = $o['eyeY'];
    $dx = $o['eyeX'];
    $r = $o['eyeR'];
    $line = AVATAR_LINE;
    $out = '';

    if ($o['blush']) {
        $out .= '<ellipse cx="' . (50 - $dx - 9) . '" cy="' . ($y + 10) . '" rx="6" ry="3.4" fill="#ff8fab" opacity="0.55"/>';
        $out .= '<ellipse cx="' . (50 + $dx + 9) . '" cy="' . ($y + 10) . '" rx="6" ry="3.4" fill="#ff8fab" opacity="0.55"/>';
    }

    foreach ([50 - $dx, 50 + $dx] as $i => $x) {
        switch ($o['eyes']) {
            case 'heart':
                $out .= '<path d="M' . $x . ' ' . ($y + 5) . ' l-5.5 -5.5 a3.2 3.2 0 0 1 5.5 -3.6 a3.2 3.2 0 0 1 5.5 3.6 z" fill="#ff4f7b"/>';
                break;
            case 'closed':
                $out .= '<path d="M' . ($x - 5) . ' ' . ($y + 1) . ' Q' . $x . ' ' . ($y - 5) . ' ' . ($x + 5) . ' ' . ($y + 1) . '" fill="none" stroke="' . $line . '" stroke-width="2.6" stroke-linecap="round"/>';
                break;
            case 'sly':
                $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="' . $line . '"/>';
                $out .= '<rect x="' . ($x - 7) . '" y="' . ($y - 8) . '" width="14" height="7.5" fill="' . $o['lid'] . '"/>';
                $out .= '<path d="M' . ($x - 6) . ' ' . ($y - 0.5) . ' L' . ($x + 6) . ' ' . ($y - 0.5) . '" stroke="' . $line . '" stroke-width="2.4" stroke-linecap="round"/>';
                break;
            case 'wide':
                $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($r + 2.4) . '" fill="#ffffff" stroke="' . $line . '" stroke-width="2"/>';
                $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="2" fill="' . $line . '"/>';
                break;
            case 'glow':
                $out .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="' . ($r + 0.8) . '" ry="' . ($r + 1.6) . '" fill="' . $o['iris'] . '"/>';
                $out .= '<ellipse cx="' . $x . '" cy="' . $y . '" rx="1.6" ry="' . ($r + 0.2) . '" fill="#2b1d18"/>';
                $out .= '<circle cx="' . ($x + 1.8) . '" cy="' . ($y - 2.4) . '" r="1.5" fill="#ffffff"/>';
                break;
            case 'shine':
                $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . ($r + 1) . '" fill="#ffffff"/>';
                $out .= '<circle cx="' . $x . '" cy="' . ($y + 0.4) . '" r="' . ($r - 0.8) . '" fill="#2b1d18"/>';
                $out .= '<circle cx="' . ($x + 1.4) . '" cy="' . ($y - 1.4) . '" r="1.5" fill="#ffffff"/>';
                break;
            default:
                $out .= '<circle cx="' . $x . '" cy="' . $y . '" r="' . $r . '" fill="#2b1d18"/>';
                $out .= '<circle cx="' . ($x + 1.7) . '" cy="' . ($y - 1.9) . '" r="1.8" fill="#ffffff"/>';
        }
    }

    $ny = $o['noseY'];
    if (!in_array($o['mouth'], ['beak', 'bill', 'frog', 'none'], true) && $o['nose']) {
        $out .= '<ellipse cx="50" cy="' . $ny . '" rx="3.4" ry="2.4" fill="' . $o['nose'] . '"/>';
    }

    $m = $ny + 2.5;
    $stroke = '" fill="none" stroke="' . $line . '" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>';
    switch ($o['mouth']) {
        case 'cat':
            $out .= '<path d="M44 ' . $m . ' Q47 ' . ($m + 4) . ' 50 ' . $m . ' Q53 ' . ($m + 4) . ' 56 ' . $m . $stroke;
            break;
        case 'open':
            $out .= '<path d="M45 ' . ($m + 1) . ' Q50 ' . ($m + 11) . ' 55 ' . ($m + 1) . ' Z" fill="#7a2e3a" stroke="' . $line . '" stroke-width="2" stroke-linejoin="round"/>';
            $out .= '<ellipse cx="50" cy="' . ($m + 6) . '" rx="2.6" ry="1.6" fill="#ff8fab"/>';
            break;
        case 'kiss':
            $out .= '<path d="M48 ' . $m . ' Q52 ' . ($m + 2) . ' 48 ' . ($m + 4) . $stroke;
            $out .= '<path d="M60 ' . ($m + 6) . ' l-3.5 -3.5 a2 2 0 0 1 3.5 -2.3 a2 2 0 0 1 3.5 2.3 z" fill="#ff4f7b"/>';
            break;
        case 'smirk':
            $out .= '<path d="M44 ' . ($m + 2) . ' Q52 ' . ($m + 5) . ' 57 ' . ($m - 1) . $stroke;
            break;
        case 'tongue':
            $out .= '<ellipse cx="50" cy="' . ($m + 5) . '" rx="3.6" ry="4.4" fill="#ff7b95" stroke="' . $line . '" stroke-width="1.8"/>';
            $out .= '<path d="M43 ' . $m . ' Q46.5 ' . ($m + 4) . ' 50 ' . $m . ' Q53.5 ' . ($m + 4) . ' 57 ' . $m . $stroke;
            break;
        case 'beak':
            $out .= '<path d="M44 ' . ($ny - 1) . ' Q50 ' . ($ny - 5) . ' 56 ' . ($ny - 1) . ' Q50 ' . ($ny + 8) . ' 44 ' . ($ny - 1) . ' Z" fill="#ffa53d" stroke="' . $line . '" stroke-width="2.2" stroke-linejoin="round"/>';
            break;
        case 'bill':
            $out .= '<ellipse cx="50" cy="' . ($ny + 2) . '" rx="12" ry="6.5" fill="#ff9f40" stroke="' . $line . '" stroke-width="2.4"/>';
            $out .= '<path d="M40 ' . ($ny + 2) . ' Q50 ' . ($ny + 5) . ' 60 ' . ($ny + 2) . $stroke;
            break;
        case 'frog':
            $out .= '<path d="M34 ' . $m . ' Q50 ' . ($m + 12) . ' 66 ' . $m . $stroke;
            break;
        case 'none':
            break;
        default:
            $out .= '<path d="M44 ' . $m . ' Q50 ' . ($m + 6) . ' 56 ' . $m . $stroke;
    }

    return $out;
}

function svg_muzzle(string $fill, float $cy = 66, float $rx = 14, float $ry = 10): string
{
    return '<ellipse cx="50" cy="' . $cy . '" rx="' . $rx . '" ry="' . $ry . '" fill="' . $fill . '"/>';
}

function svg_pointy_ears(string $fill, string $inner, ?string $tip = null): string
{
    $ear = svg_shape('path', 'd="M20 44 L22 12 L46 30 Z"', $fill)
        . '<path d="M25 37 L26 20 L39 30 Z" fill="' . $inner . '"/>';
    if ($tip) {
        $ear .= '<path d="M21.6 18 L22 12 L29 17 Z" fill="' . $tip . '"/>';
    }

    return svg_mirror($ear);
}

function svg_round_ears(string $fill, string $inner, float $r = 11, float $cx = 24, float $cy = 30): string
{
    return svg_mirror(
        svg_shape('circle', 'cx="' . $cx . '" cy="' . $cy . '" r="' . $r . '"', $fill)
        . '<circle cx="' . $cx . '" cy="' . $cy . '" r="' . ($r * 0.55) . '" fill="' . $inner . '"/>'
    );
}

// ---- Props --------------------------------------------------------------------

function svg_party_hat(): string
{
    return '<g transform="rotate(18 64 22)">'
        . svg_shape('path', 'd="M53 30 L64 0 L75 30 Z"', '#7ad3f7')
        . '<path d="M57 19 L71 19 M55 25 L73 25 M60 11 L68 11" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/>'
        . svg_shape('circle', 'cx="64" cy="1" r="4.5"', '#ffd166')
        . '</g>';
}

function svg_crown(): string
{
    return svg_shape('path', 'd="M32 26 L34 8 L42 18 L50 4 L58 18 L66 8 L68 26 Z"', '#ffd34d')
        . '<circle cx="50" cy="19" r="3" fill="#ff5d8f"/><circle cx="39" cy="22" r="2" fill="#6bd6ff"/><circle cx="61" cy="22" r="2" fill="#6bd6ff"/>';
}

function svg_bandana(string $fill): string
{
    return svg_shape('path', 'd="M24 80 Q50 94 76 80 L68 92 Q50 100 32 92 Z"', $fill)
        . '<circle cx="40" cy="89" r="1.6" fill="#ffffff"/><circle cx="50" cy="92" r="1.6" fill="#ffffff"/><circle cx="60" cy="89" r="1.6" fill="#ffffff"/>';
}

// ---- Species --------------------------------------------------------------------

function animal_svg_parts(string $animal): array
{
    $ink = AVATAR_LINE;

    switch ($animal) {
        case 'bunny':
            $ear = '<g transform="rotate(-12 38 24)">' . svg_shape('ellipse', 'cx="38" cy="21" rx="8.5" ry="18"', '#f7f3ff') . '<ellipse cx="38" cy="22" rx="4" ry="12" fill="#ffc2d6"/></g>';
            return [svg_mirror($ear), svg_head('#f7f3ff'), '', svg_face(['mouth' => 'cat', 'nose' => '#ff8fab'])
                . '<rect x="47.4" y="66.5" width="5.2" height="5" rx="1.4" fill="#ffffff" stroke="' . $ink . '" stroke-width="1.6"/>'];
        case 'puppy':
            $ear = '<g transform="rotate(18 20 50)">' . svg_shape('ellipse', 'cx="20" cy="50" rx="10" ry="19"', '#b9835a') . '</g>';
            return ['', svg_head('#ebbd8c'), svg_muzzle('#fff1df') . '<ellipse cx="64" cy="50" rx="9" ry="8" fill="#d39a66"/>' . svg_mirror($ear),
                svg_face(['mouth' => 'tongue', 'nose' => '#3a2a26', 'noseY' => 61])];
        case 'panda':
            $patch = '<ellipse cx="36" cy="55" rx="8.5" ry="10.5" fill="#3a3340" transform="rotate(28 36 55)"/>';
            return [svg_round_ears('#3a3340', '#5a5162'), svg_head('#ffffff'), svg_mirror($patch), svg_face(['eyes' => 'shine', 'eyeR' => 3.6, 'nose' => '#3a3340'])];
        case 'fox':
            return [svg_pointy_ears('#ff8a3d', '#fff1e6', '#4a2f25'), svg_head('#ff8a3d'),
                svg_shape('path', 'd="M17 62 Q30 58 50 72 Q70 58 83 62 Q78 86 50 88 Q22 86 17 62 Z"', '#fff6ee', false),
                svg_face(['mouth' => 'smile', 'nose' => '#3a2a26'])];
        case 'owl':
            $tuft = svg_shape('path', 'd="M24 34 L20 12 L40 27 Z"', '#a5795a');
            $disc = '<circle cx="37" cy="53" r="12.5" fill="#f6e3cf"/>';
            return [svg_mirror($tuft), svg_head('#b98a68'), svg_mirror($disc) . '<ellipse cx="50" cy="76" rx="14" ry="7" fill="#d9b597"/>',
                svg_face(['eyes' => 'shine', 'eyeR' => 6, 'eyeX' => 13, 'eyeY' => 53, 'mouth' => 'beak', 'noseY' => 63, 'blush' => false])];
        case 'bear':
            return [svg_round_ears('#a9744f', '#e8c3a0'), svg_head('#a9744f'), svg_muzzle('#e8c9a8', 66, 13, 10), svg_face(['mouth' => 'smile', 'nose' => '#3a2a26', 'noseY' => 62])];
        case 'koala':
            $ear = svg_shape('circle', 'cx="20" cy="38" r="16"', '#b8bcc6') . '<circle cx="21" cy="39" r="9" fill="#ffffff"/>';
            return [svg_mirror($ear), svg_head('#b8bcc6'), '<ellipse cx="50" cy="62" rx="7" ry="9" fill="#4b4552"/><ellipse cx="48" cy="58" rx="2" ry="2.5" fill="#ffffff" opacity="0.5"/>',
                svg_face(['mouth' => 'none', 'nose' => '', 'eyeX' => 16, 'eyeY' => 52]) . '<path d="M45 74 Q50 77 55 74" fill="none" stroke="' . $ink . '" stroke-width="2.2" stroke-linecap="round"/>'];
        case 'pig':
            $ear = svg_shape('path', 'd="M22 38 L20 16 L42 30 Z"', '#ff99b5');
            return [svg_mirror($ear), svg_head('#ffb7ca'),
                svg_shape('ellipse', 'cx="50" cy="64" rx="10" ry="7"', '#ff8fad') . '<ellipse cx="46" cy="64" rx="1.8" ry="2.6" fill="#a8445f"/><ellipse cx="54" cy="64" rx="1.8" ry="2.6" fill="#a8445f"/>',
                svg_face(['mouth' => 'none', 'nose' => '', 'eyeY' => 51]) . '<path d="M45 75 Q50 78 55 75" fill="none" stroke="' . $ink . '" stroke-width="2.2" stroke-linecap="round"/>'];
        case 'frog':
            $bump = svg_shape('circle', 'cx="33" cy="32" r="13"', '#8fd16a');
            return [svg_mirror($bump), svg_head('#8fd16a'), '<ellipse cx="50" cy="72" rx="20" ry="11" fill="#d8f5c4"/>',
                svg_face(['eyes' => 'shine', 'eyeY' => 31, 'eyeX' => 17, 'eyeR' => 6, 'mouth' => 'frog', 'noseY' => 60, 'blush' => true])];
        case 'penguin':
            return ['', svg_head('#3b4252'),
                '<path d="M50 40 C40 28 18 38 22 58 C26 78 44 86 50 86 C56 86 74 78 78 58 C82 38 60 28 50 40 Z" fill="#ffffff"/>',
                svg_face(['mouth' => 'beak', 'noseY' => 63, 'eyeY' => 54])];
        case 'hamster':
            return [svg_round_ears('#f0b46e', '#ffc9b5', 8, 28, 30), svg_head('#f5c98c'),
                '<ellipse cx="50" cy="70" rx="27" ry="17" fill="#fff6ea"/><path d="M50 26 L50 40" stroke="#e3a35c" stroke-width="5" stroke-linecap="round"/>',
                svg_face(['mouth' => 'cat', 'nose' => '#ff8fab', 'eyeY' => 53])];
        case 'duck':
            return ['<path d="M50 26 Q44 12 52 10 M50 26 Q56 14 62 16" fill="none" stroke="' . $ink . '" stroke-width="2.5" stroke-linecap="round"/>',
                svg_head('#ffe066'), '', svg_face(['mouth' => 'bill', 'noseY' => 64, 'eyeY' => 52])];
        case 'raccoon':
            return [svg_pointy_ears('#9aa0a8', '#e9e6ef', '#4b4552'), svg_head('#9aa0a8'),
                '<path d="M17 54 Q33 42 50 51 Q67 42 83 54 Q81 64 66 64 Q56 62 50 58 Q44 62 34 64 Q19 64 17 54 Z" fill="#4b4552"/>' . svg_muzzle('#f3f1f6', 70, 13, 9),
                svg_face(['eyes' => 'shine', 'eyeR' => 4, 'mouth' => 'smile', 'nose' => '#2b1d18', 'noseY' => 66])];
        case 'sheep':
            $wool = '';
            foreach ([[30, 32], [42, 26], [58, 26], [70, 32], [50, 30], [22, 44], [78, 44]] as [$x, $y]) {
                $wool .= svg_shape('circle', 'cx="' . $x . '" cy="' . $y . '" r="10"', '#ffffff');
            }
            $ear = '<g transform="rotate(-18 14 54)">' . svg_shape('ellipse', 'cx="14" cy="54" rx="10" ry="5.5"', '#e9d3c0') . '</g>';
            return [svg_mirror($ear), svg_head('#f7e6d6'), $wool, svg_face(['mouth' => 'smile', 'nose' => '#b07a6a', 'eyeY' => 56])];
        case 'dino':
            $spikes = '';
            foreach ([[34, 27], [50, 22], [66, 27]] as [$x, $y]) {
                $spikes .= svg_shape('path', 'd="M' . ($x - 7) . ' ' . ($y + 6) . ' L' . $x . ' ' . ($y - 9) . ' L' . ($x + 7) . ' ' . ($y + 6) . ' Z"', '#ffb04d');
            }
            return [$spikes, svg_head('#7ad3c4'), svg_muzzle('#c8f1ea', 68, 18, 11) . '<circle cx="45" cy="64" r="1.6" fill="' . $ink . '"/><circle cx="55" cy="64" r="1.6" fill="' . $ink . '"/>',
                svg_face(['mouth' => 'smile', 'nose' => '', 'eyeY' => 51, 'noseY' => 67])];
        case 'cat':
        default:
            return [svg_pointy_ears('#ffb86b', '#ffd1dc'), svg_head('#ffb86b'), svg_muzzle('#fff1dc', 66, 13, 9) . cat_whiskers(),
                svg_face(['mouth' => 'cat', 'nose' => '#ff8fab'])];
    }
}

function cat_whiskers(): string
{
    return '<path d="M20 62 L32 64 M20 69 L32 68 M80 62 L68 64 M80 69 L68 68" stroke="' . AVATAR_LINE . '" stroke-width="1.6" stroke-linecap="round" opacity="0.6"/>';
}

/**
 * Collectible cats (unlocked by achievements).
 * key => [emoji, label, tile bg, fur, inner ear, extras...]
 */
function cat_avatars(): array
{
    return [
        'tabby' => ['😺', 'Tabby Grin', '#f3e6da'],
        'sunny' => ['😸', 'Sunny Smile', '#fff1c9'],
        'smitten' => ['😻', 'Smitten Kitten', '#ffe0ec'],
        'sly' => ['😼', 'Sly Whiskers', '#e7ebf1'],
        'smooch' => ['😽', 'Smooch', '#fff3e3'],
        'giggles' => ['😹', 'Giggles', '#e5f7ff'],
        'spooked' => ['🙀', 'Spooked', '#efe9ff'],
        'shadow' => ['🐈‍⬛', 'Shadow', '#e3defa'],
        'alley' => ['🐈', 'Alley Cat', '#e9eef2'],
        'tiger' => ['🐯', 'Office Tiger', '#ffe8cc'],
        'lion' => ['🦁', 'Big Boss Lion', '#fff0c2'],
        'disco' => ['😎', 'Disco Kitty', '#f3e3ff'],
    ];
}

function cat_svg_parts(string $key): array
{
    $stripes = function (string $color): string {
        return '<path d="M42 30 L44 40 M50 27 L50 39 M58 30 L56 40" stroke="' . $color . '" stroke-width="3.4" stroke-linecap="round"/>'
            . svg_mirror('<path d="M15 52 L26 54 M16 60 L25 60" stroke="' . $color . '" stroke-width="3" stroke-linecap="round"/>');
    };
    $muzzle = function (string $fill): string {
        return svg_muzzle($fill, 66, 13, 9) . cat_whiskers();
    };

    switch ($key) {
        case 'tabby':
            return [svg_pointy_ears('#c9a27e', '#ffd1dc'), svg_head('#c9a27e'), $stripes('#8a6446') . $muzzle('#f6ead9'), svg_face(['mouth' => 'smile', 'nose' => '#ff8fab'])];
        case 'sunny':
            return [svg_pointy_ears('#ffb04d', '#ffd1dc'), svg_head('#ffb04d'), $stripes('#e9862a') . $muzzle('#fff4dc'), svg_face(['eyes' => 'closed', 'mouth' => 'open', 'nose' => '#ff8fab'])];
        case 'smitten':
            return [svg_pointy_ears('#ffb3d1', '#ffffff'), svg_head('#ffb3d1'), $muzzle('#ffe6f0'), svg_face(['eyes' => 'heart', 'mouth' => 'cat', 'nose' => '#ff4f7b'])];
        case 'sly':
            return [svg_pointy_ears('#9ea7b3', '#ffd1dc'), svg_head('#9ea7b3'), $muzzle('#e8ecf1'), svg_face(['eyes' => 'sly', 'lid' => '#9ea7b3', 'mouth' => 'smirk', 'nose' => '#ff8fab'])];
        case 'smooch':
            return [svg_pointy_ears('#fff0d9', '#ffc2d6'), svg_head('#fff0d9'), $muzzle('#ffffff'), svg_face(['eyes' => 'closed', 'mouth' => 'kiss', 'nose' => '#ff8fab'])];
        case 'giggles':
            return [svg_pointy_ears('#ffffff', '#ffd1dc'), svg_head('#ffffff'),
                '<path d="M17 50 Q20 30 40 27 Q36 44 17 50 Z" fill="#ffa24d"/><path d="M83 46 Q80 30 62 27 Q66 42 83 46 Z" fill="#4b4552"/>' . $muzzle('#fff6ee'),
                svg_face(['eyes' => 'closed', 'mouth' => 'open', 'nose' => '#ff8fab']) . svg_party_hat()];
        case 'spooked':
            return [svg_pointy_ears('#f7f5ff', '#ffd1dc'), svg_head('#f7f5ff'), $muzzle('#ffffff'), svg_face(['eyes' => 'wide', 'mouth' => 'open', 'nose' => '#ff8fab'])];
        case 'shadow':
            return [svg_pointy_ears('#3a3340', '#7a6b85'), svg_head('#3a3340'), cat_whiskers(),
                svg_face(['eyes' => 'glow', 'iris' => '#ffd84d', 'mouth' => 'cat', 'nose' => '#ff8fab'])
                . '<path d="M44 66.5 Q47 70.5 50 66.5 Q53 70.5 56 66.5" fill="none" stroke="#f3eaff" stroke-width="2.2" stroke-linecap="round"/>'];
        case 'alley':
            return [svg_pointy_ears('#b0b6bd', '#ffd1dc'), svg_head('#b0b6bd'), $stripes('#7d848c') . $muzzle('#eef0f2'),
                svg_face(['mouth' => 'smirk', 'nose' => '#ff8fab']) . svg_bandana('#ff5d6c')];
        case 'tiger':
            return [svg_pointy_ears('#ff9f2e', '#fff1dc', '#2b1d18'), svg_head('#ff9f2e'), $stripes('#2b1d18') . $muzzle('#fff6ea'),
                svg_face(['mouth' => 'open', 'nose' => '#ff8fab'])];
        case 'lion':
            $mane = '';
            for ($i = 0; $i < 14; $i++) {
                $a = $i / 14 * M_PI * 2;
                $mane .= '<circle cx="' . round(50 + cos($a) * 38, 1) . '" cy="' . round(57 + sin($a) * 34, 1) . '" r="12" fill="#d9822b"/>';
            }
            return [$mane . svg_round_ears('#f3b54a', '#ffd1a8', 8, 28, 30), svg_head('#f3b54a'), $muzzle('#fff1d6'),
                svg_face(['mouth' => 'smile', 'nose' => '#7a4b2e']) . svg_crown()];
        case 'disco':
            $shades = '<rect x="27" y="47" width="19" height="13" rx="6" fill="#2b1d18"/><rect x="54" y="47" width="19" height="13" rx="6" fill="#2b1d18"/>'
                . '<path d="M46 52 L54 52" stroke="#2b1d18" stroke-width="3"/><path d="M31 51 L36 49 M58 51 L63 49" stroke="#ffffff" stroke-width="2" stroke-linecap="round" opacity="0.8"/>';
            return [svg_pointy_ears('#b48cf2', '#ffd1dc'), svg_head('#b48cf2'), $muzzle('#f1e6ff'),
                svg_face(['eyes' => 'closed', 'mouth' => 'smile', 'nose' => '#ff8fab', 'blush' => true]) . $shades
                . svg_shape('path', 'd="M30 86 Q50 96 70 86"', 'none') . '<circle cx="50" cy="93" r="4" fill="#ffd34d" stroke="' . AVATAR_LINE . '" stroke-width="2"/>'];
        default:
            return animal_svg_parts('cat');
    }
}

function svg_wrap(array $parts, string $label): string
{
    [$behind, $head, $marks, $face] = $parts;

    return '<svg viewBox="0 0 100 100" role="img" aria-label="' . e($label) . '" xmlns="http://www.w3.org/2000/svg">'
        . $behind . $head . $marks . $face . '</svg>';
}

function animal_svg(string $animal): string
{
    $meta = animal_choices_meta();
    if (!isset($meta[$animal])) {
        $animal = 'cat';
    }

    return svg_wrap(animal_svg_parts($animal), $meta[$animal][0]);
}

function cat_svg(string $key): string
{
    $cats = cat_avatars();

    return svg_wrap(cat_svg_parts($key), $cats[$key][1] ?? 'Cat');
}

// ---- Public helpers used by pages ------------------------------------------------

function animal_avatar(string $animal, string $size = 'md'): string
{
    $meta = animal_choices_meta()[$animal] ?? animal_choices_meta()['cat'];

    return '<span class="avatar avatar-' . e($size) . '" style="background:' . $meta[1] . '" title="' . e($meta[0]) . '">' . animal_svg($animal) . '</span>';
}

function cat_avatar_tile(string $key, string $size = 'md'): string
{
    $cat = cat_avatars()[$key] ?? null;
    if (!$cat) {
        return animal_avatar('cat', $size);
    }

    return '<span class="avatar avatar-' . e($size) . ' avatar-rare" style="background:' . $cat[2] . '" title="' . e($cat[1]) . '">' . cat_svg($key) . '</span>';
}

/** Avatar for a user row: their unlocked cat avatar if chosen, else their desk animal. */
function user_avatar(array $user, string $size = 'md'): string
{
    $cat = $user['cat_avatar'] ?? null;
    if ($cat && isset(cat_avatars()[$cat])) {
        return cat_avatar_tile($cat, $size);
    }

    return animal_avatar((string) ($user['animal'] ?? 'cat'), $size);
}

/** Bare SVG for a user (for the game sprite and JSON feeds). */
function user_avatar_svg(array $user): string
{
    $cat = $user['cat_avatar'] ?? null;

    return ($cat && isset(cat_avatars()[$cat])) ? cat_svg($cat) : animal_svg((string) ($user['animal'] ?? 'cat'));
}

/** Plain-text emoji for a user (notification text, page titles). */
function user_emoji(array $user): string
{
    $cat = $user['cat_avatar'] ?? null;
    $cats = cat_avatars();

    return ($cat && isset($cats[$cat])) ? $cats[$cat][0] : animal_emoji((string) ($user['animal'] ?? 'cat'));
}

/** Radio-button grid for picking a desk animal (register, install, profile). */
function animal_picker(string $name, string $selected): string
{
    $out = '<div class="animal-picker">';
    foreach (animal_choices_meta() as $key => [$label, $bg]) {
        $out .= '<label class="pick' . ($selected === $key ? ' chosen' : '') . '">'
            . '<input type="radio" name="' . e($name) . '" value="' . e($key) . '"' . ($selected === $key ? ' checked' : '') . '>'
            . animal_avatar($key, 'md')
            . '<span>' . e(explode(' the ', $label)[0]) . '</span></label>';
    }

    return $out . '</div>';
}
