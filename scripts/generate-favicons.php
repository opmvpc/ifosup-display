<?php

/**
 * Génère les favicons de l'application à partir du logo jaune
 * (public/IFO_Gimmick_SUPERIEUR.png), composé sur le fond navy de la marque
 * (le #1e2d55 du slide de bienvenue). Utilisé ponctuellement :
 *
 *   php scripts/generate-favicons.php
 *
 * Produit : public/favicon.svg (vectoriel, PNG embarqué sur carré arrondi),
 * public/favicon.ico (32 px, PNG encapsulé en ICO) et
 * public/apple-touch-icon.png (180 px, iOS arrondit lui-même).
 */
$root = dirname(__DIR__);
$logoPath = $root.'/public/IFO_Gimmick_SUPERIEUR.png';
$navy = [0x1E, 0x2D, 0x55];

$logo = imagecreatefrompng($logoPath);
imagesavealpha($logo, true);
$logoW = imagesx($logo);
$logoH = imagesy($logo);

function composite(GdImage $logo, int $size, array $navy, float $scale = 0.68): GdImage
{
    $canvas = imagecreatetruecolor($size, $size);
    $bg = imagecolorallocate($canvas, $navy[0], $navy[1], $navy[2]);
    imagefill($canvas, 0, 0, $bg);

    $logoW = imagesx($logo);
    $logoH = imagesy($logo);
    $target = (int) round($size * $scale);
    $ratio = min($target / $logoW, $target / $logoH);
    $w = (int) round($logoW * $ratio);
    $h = (int) round($logoH * $ratio);

    imagecopyresampled(
        $canvas,
        $logo,
        (int) round(($size - $w) / 2),
        (int) round(($size - $h) / 2),
        0,
        0,
        $w,
        $h,
        $logoW,
        $logoH,
    );

    return $canvas;
}

// apple-touch-icon : 180 px, coins carrés (iOS applique son propre masque).
imagepng(composite($logo, 180, $navy), $root.'/public/apple-touch-icon.png', 9);

// favicon.ico : un PNG 32 px encapsulé dans un conteneur ICO (supporté partout
// depuis Vista ; seul le très vieux IE l'ignorerait).
ob_start();
imagepng(composite($logo, 32, $navy), null, 9);
$png32 = ob_get_clean();

$ico = pack('vvv', 0, 1, 1)
    .pack('CCCCvvVV', 32, 32, 0, 0, 1, 32, strlen($png32), 22)
    .$png32;
file_put_contents($root.'/public/favicon.ico', $ico);

// favicon.svg : carré arrondi navy + logo d'origine embarqué en data URI —
// net à toutes les tailles sans multiplier les fichiers.
$logoData = base64_encode(file_get_contents($logoPath));
$navyHex = sprintf('#%02x%02x%02x', $navy[0], $navy[1], $navy[2]);

// Le logo occupe ~68 % du carré, centré (mêmes proportions que le PNG composé).
$box = 512;
$target = $box * 0.68;
$ratio = min($target / $logoW, $target / $logoH);
$w = round($logoW * $ratio, 1);
$h = round($logoH * $ratio, 1);
$x = round(($box - $w) / 2, 1);
$y = round(($box - $h) / 2, 1);

$svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$box} {$box}">
  <rect width="{$box}" height="{$box}" rx="96" fill="{$navyHex}"/>
  <image x="{$x}" y="{$y}" width="{$w}" height="{$h}" href="data:image/png;base64,{$logoData}"/>
</svg>
SVG;
file_put_contents($root.'/public/favicon.svg', $svg."\n");

echo "favicon.svg, favicon.ico et apple-touch-icon.png régénérés.\n";
