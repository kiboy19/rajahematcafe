<?php

namespace App\Services;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

/**
 * Pembuat SVG QR code mandiri (server-rendered).
 *
 * Menggunakan backend SVG murni sehingga tidak membutuhkan ekstensi Imagick atau GD
 * di shared hosting, dan menghasilkan gambar vektor tajam untuk dicetak pada kertas
 * thermal/stiker tanpa artefak kompresi.
 */
class QrCodeService
{
    /**
     * Hasilkan markup SVG lengkap untuk sebuah URL atau teks.
     */
    public function svg(string $text, int $size = 280, int $margin = 2): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd,
        );

        $writer = new Writer($renderer);

        return $writer->writeString(
            content: $text,
            ecLevel: ErrorCorrectionLevel::M(),
        );
    }
}
