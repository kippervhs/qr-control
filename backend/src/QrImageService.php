<?php

declare(strict_types=1);

namespace QrControl;

use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\SvgWriter;

final class QrImageService
{
    public static function make(string $code, string $format): string
    {
        $qr = new QrCode(
            data: Config::appUrl() . '/q/' . Support::formatCode($code),
            encoding: new Encoding('ISO-8859-1'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $format === 'png' ? 1600 : 1200,
            margin: 4,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
            foregroundColor: new Color(17, 24, 39),
            backgroundColor: new Color(255, 255, 255),
        );
        return ($format === 'png' ? new PngWriter() : new SvgWriter())->write($qr)->getString();
    }
}
