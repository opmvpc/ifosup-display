<?php

/**
 * Génère public/og-image.png (1200×630), la carte de partage Open Graph :
 * fond navy de la marque, logo jaune, nom de l'application et sous-titre.
 *
 *   php scripts/generate-og-image.php [--font-dir=chemin]
 *
 * Les polices ne sont pas committées : fournir un dossier contenant
 * « Arial Bold.ttf » et « Arial.ttf » (par défaut storage/app/tmp-fonts).
 */
$root = dirname(__DIR__);
$fontDir = $root.'/storage/app/tmp-fonts';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--font-dir=')) {
        $fontDir = substr($arg, strlen('--font-dir='));
    }
}

$fontBold = $fontDir.'/Arial Bold.ttf';
$fontRegular = $fontDir.'/Arial.ttf';

if (! is_file($fontBold) || ! is_file($fontRegular)) {
    fwrite(STDERR, "Polices introuvables dans {$fontDir}.\n");
    exit(1);
}

$w = 1200;
$h = 630;
$canvas = imagecreatetruecolor($w, $h);

// Fond : dégradé vertical discret autour du navy #1e2d55.
for ($y = 0; $y < $h; $y++) {
    $t = $y / $h;
    $color = imagecolorallocate(
        $canvas,
        (int) round(0x16 + (0x24 - 0x16) * $t),
        (int) round(0x22 + (0x36 - 0x22) * $t),
        (int) round(0x41 + (0x62 - 0x41) * $t),
    );
    imageline($canvas, 0, $y, $w, $y, $color);
}

// Logo jaune, à gauche.
$logo = imagecreatefrompng($root.'/public/IFO_Gimmick_SUPERIEUR.png');
imagesavealpha($logo, true);
$logoTarget = 260;
$ratio = min($logoTarget / imagesx($logo), $logoTarget / imagesy($logo));
$lw = (int) round(imagesx($logo) * $ratio);
$lh = (int) round(imagesy($logo) * $ratio);
imagecopyresampled($canvas, $logo, 120, (int) (($h - $lh) / 2) - 20, 0, 0, $lw, $lh, imagesx($logo), imagesy($logo));

$white = imagecolorallocate($canvas, 0xFF, 0xFF, 0xFF);
$muted = imagecolorallocate($canvas, 0xB9, 0xC3, 0xD9);
$yellow = imagecolorallocate($canvas, 0xF2, 0xA9, 0x2E);

// Textes, alignés à droite du logo.
$textX = 120 + $lw + 80;
imagettftext($canvas, 58, 0, $textX, 300, $white, $fontBold, 'IFOSUP Display');
imagettftext($canvas, 22, 0, $textX, 360, $muted, $fontRegular, 'Affichage dynamique des plannings et annonces');
imagettftext($canvas, 22, 0, $textX, 398, $muted, $fontRegular, "sur les écrans de l'IFOSUP Wavre");

// Filet jaune sous le titre, rappel de la flèche.
imagefilledrectangle($canvas, $textX + 4, 320, $textX + 204, 326, $yellow);

imagepng($canvas, $root.'/public/og-image.png', 9);
echo "public/og-image.png généré.\n";
