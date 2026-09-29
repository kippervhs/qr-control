<?php

declare(strict_types=1);

use QrControl\Config;
use QrControl\HttpException;
use QrControl\Support;
use QrControl\XlsxService;

require dirname(__DIR__) . '/vendor/autoload.php';
Config::load(dirname(__DIR__, 2) . '/.env');

$checks = [
    Support::normalizeCode('0001') === '1',
    Support::normalizeCode('ADM-000A') === 'ADM-000A',
    Support::normalizeCode('0') === null,
    Support::normalizeCode('1a') === null,
    Support::formatCode(16) === '0016',
    Support::formatCode('ADM-000A') === 'ADM-000A',
    Support::sequenceCode('ADM', 1) === 'ADM-0001',
    Support::sequenceCode('ADM', 10) === 'ADM-000A',
    Support::sequenceCode('ADM', 36) === 'ADM-0010',
    Support::formatCode(10000) === '10000',
    Support::destinationUrl('https://example.com/path') === 'https://example.com/path',
    Support::ids(['7e050c70-367f-42c5-a3c3-9ef6ae7ca635', '7e050c70-367f-42c5-a3c3-9ef6ae7ca635']) === ['7e050c70-367f-42c5-a3c3-9ef6ae7ca635'],
];

$xlsx = XlsxService::make([['code' => 1], ['code' => 2]]);
$temp = tempnam(sys_get_temp_dir(), 'qr-xlsx-test-');
file_put_contents($temp, $xlsx);
$archive = new ZipArchive();
$opened = $archive->open($temp) === true;
$sheet = $opened ? $archive->getFromName('xl/worksheets/sheet1.xml') : false;
$archive->close();
unlink($temp);
$checks[] = $opened && is_string($sheet) && str_contains($sheet, '0001') && str_contains($sheet, Config::appUrl() . '/q/0001');

foreach (['javascript:alert(1)', 'data:text/html,test', 'google.com'] as $invalidUrl) {
    try {
        Support::destinationUrl($invalidUrl);
        $checks[] = false;
    } catch (HttpException) {
        $checks[] = true;
    }
}

if (in_array(false, $checks, true)) {
    fwrite(STDERR, "Falha nos testes PHP.\n");
    exit(1);
}

echo count($checks) . " testes PHP aprovados.\n";
