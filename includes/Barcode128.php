<?php
/**
 * Générateur de code-barres Code128 (sous-jeu B) en PHP pur, basé sur GD.
 * Aucune dépendance externe. Licence libre (implémentation originale du
 * standard public Code128).
 */
class Barcode128
{
    // Table des motifs Code128 (index 0-106) : chaîne de 6 largeurs (barre,espace x3)
    private static array $patterns = [
        '212222','222122','222221','121223','121322','131222','122213','122312','132212','221213',
        '221312','231212','112232','122132','122231','113222','123122','123221','223211','221132',
        '221231','213212','223112','312131','311222','321122','321221','312212','322112','322211',
        '212123','212321','232121','111323','131123','131321','112313','132113','132311','211313',
        '231113','231311','112133','112331','132131','113123','113321','133121','313121','211331',
        '231131','213113','213311','213131','311123','311321','331121','312113','312311','332111',
        '314111','221411','431111','111224','111422','121124','121421','141122','141221','112214',
        '112412','122114','122411','142112','142211','241211','221114','413111','241112','134111',
        '111242','121142','121241','114212','124112','124211','411212','421112','421211','212141',
        '214121','412121','111143','111341','131141','114113','114311','411113','411311','113141',
        '114131','311141','411131','211412','211214','211232','2331112',
    ];
    private const START_B = 104;
    private const STOP = 106;

    /**
     * Encode $text (ASCII imprimable, jeu B) en tableau de valeurs de code.
     */
    private static function encode(string $text): array
    {
        $codes = [self::START_B];
        $sum = self::START_B;
        for ($i = 0; $i < strlen($text); $i++) {
            $val = ord($text[$i]) - 32;
            if ($val < 0 || $val > 94) {
                $val = ord('?') - 32; // caractère non supporté -> substitué
            }
            $codes[] = $val;
            $sum += $val * ($i + 1);
        }
        $checksum = $sum % 103;
        $codes[] = $checksum;
        $codes[] = self::STOP;
        return $codes;
    }

    /**
     * Génère une image PNG (GD) du code-barres et l'envoie ou la retourne en binaire.
     */
    public static function generatePNG(string $text, int $barHeight = 60, int $scale = 2, bool $showText = true): string
    {
        $codes = self::encode($text);
        $bars = [];
        $totalWidth = 0;
        foreach ($codes as $code) {
            $pattern = self::$patterns[$code];
            foreach (str_split($pattern) as $i => $w) {
                $isBar = ($i % 2 === 0);
                $width = (int)$w * $scale;
                $bars[] = [$isBar, $width];
                $totalWidth += $width;
            }
        }
        $quiet = 10 * $scale;
        $textHeight = $showText ? 18 : 0;
        $imgWidth = $totalWidth + $quiet * 2;
        $imgHeight = $barHeight + $textHeight + 6;

        $img = imagecreatetruecolor($imgWidth, $imgHeight);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $imgWidth, $imgHeight, $white);

        $x = $quiet;
        foreach ($bars as [$isBar, $width]) {
            if ($isBar) {
                imagefilledrectangle($img, $x, 0, $x + $width - 1, $barHeight, $black);
            }
            $x += $width;
        }

        if ($showText) {
            $font = 3;
            $textWidth = imagefontwidth($font) * strlen($text);
            $tx = max(0, (int)(($imgWidth - $textWidth) / 2));
            imagestring($img, $font, $tx, $barHeight + 4, $text, $black);
        }

        ob_start();
        imagepng($img);
        $data = ob_get_clean();
        imagedestroy($img);
        return $data;
    }

    /** Renvoie une data-URI base64 prête à insérer dans un <img src="...">. */
    public static function generateDataUri(string $text, int $barHeight = 60, int $scale = 2, bool $showText = true): string
    {
        $png = self::generatePNG($text, $barHeight, $scale, $showText);
        return 'data:image/png;base64,' . base64_encode($png);
    }
}
