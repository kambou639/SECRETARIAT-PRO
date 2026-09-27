<?php
/**
 * Wrapper simplifié autour de QRCodeLib.php (Kazuhiko Arase, licence MIT)
 * pour générer des QR codes en image PNG, sans dépendance externe.
 */
require_once __DIR__ . '/QRCodeLib.php';

class QRHelper
{
    /**
     * Génère un QR code PNG (binaire) pour le texte donné.
     * @param string $text     Contenu à encoder
     * @param int    $pixelSize Taille en pixels de chaque module
     * @param int    $margin    Marge (en modules) autour du code
     */
    public static function generatePNG(string $text, int $pixelSize = 6, int $margin = 4): string
    {
        $qr = QRCode::getMinimumQRCode($text, QR_ERROR_CORRECT_LEVEL_M);
        $count = $qr->getModuleCount();
        $imgSize = ($count + $margin * 2) * $pixelSize;

        $img = imagecreatetruecolor($imgSize, $imgSize);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $imgSize, $imgSize, $white);

        for ($row = 0; $row < $count; $row++) {
            for ($col = 0; $col < $count; $col++) {
                if ($qr->isDark($row, $col)) {
                    $x = ($col + $margin) * $pixelSize;
                    $y = ($row + $margin) * $pixelSize;
                    imagefilledrectangle($img, $x, $y, $x + $pixelSize - 1, $y + $pixelSize - 1, $black);
                }
            }
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    public static function generateDataUri(string $text, int $pixelSize = 6, int $margin = 4): string
    {
        return 'data:image/png;base64,' . base64_encode(self::generatePNG($text, $pixelSize, $margin));
    }
}
