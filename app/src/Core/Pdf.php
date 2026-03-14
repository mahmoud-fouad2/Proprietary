<?php
declare(strict_types=1);

namespace Zaco\Core;

final class Pdf
{
    public static function isAvailable(): bool
    {
        return class_exists('Mpdf\\Mpdf');
    }

    /**
     * @param array<string,mixed> $config
     */
    public static function download(string $filename, string $html, array $config = []): void
    {
        if (!self::isAvailable()) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo "PDF library is not installed. Run composer install (mpdf/mpdf) and deploy vendor/.";
            return;
        }

        $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $filename) ?: 'document.pdf';
        if (!str_ends_with(mb_strtolower($safeName), '.pdf')) {
            $safeName .= '.pdf';
        }

        $tmp = __DIR__ . '/../../../storage/mpdf_tmp';
        if (!is_dir($tmp)) {
            @mkdir($tmp, 0775, true);
        }

        $mpdfConfig = array_merge([
            'mode' => 'utf-8',
            'tempDir' => $tmp,
            'default_font' => 'dejavusans',
        ], $config);

        try {
            /** @var \Mpdf\Mpdf $mpdf */
            $mpdf = new \Mpdf\Mpdf($mpdfConfig);
            $mpdf->WriteHTML($html);
            $mpdf->Output($safeName, \Mpdf\Output\Destination::DOWNLOAD);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Failed to generate PDF.';
        }
    }
}
