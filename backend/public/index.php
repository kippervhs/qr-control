<?php

declare(strict_types=1);

use QrControl\Auth;
use QrControl\BatchService;
use QrControl\Config;
use QrControl\HttpException;
use QrControl\QrImageService;
use QrControl\QrService;
use QrControl\Support;
use QrControl\XlsxService;
use QrControl\UserService;
use ZipStream\ZipStream;

require dirname(__DIR__) . '/vendor/autoload.php';
Config::load(dirname(__DIR__, 2) . '/.env');

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if (strtolower((string) ($_SERVER['HTTPS'] ?? '')) === 'on') {
    header('Strict-Transport-Security: max-age=31536000');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: private, no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    exit;
}

function routeCode(string $value): string
{
    $code = Support::normalizeCode($value);
    if (!$code) throw new HttpException(400, 'Código inválido.');
    return Support::formatCode($code);
}

function streamZip(array $ids): never
{
    $rows = BatchService::exportRows($ids);
    set_time_limit(300);
    Auth::startSession();
    session_write_close();
    $first = $rows[0];
    $last = $rows[array_key_last($rows)];
    $multiple = count($ids) > 1;
    $filename = $multiple
        ? sprintf('qrcodes_%s-%s_%d_lotes.zip', Support::formatCode((string) $first['start_code']), Support::formatCode((string) $last['end_code']), count($ids))
        : sprintf('qrcodes_%s-%s.zip', Support::formatCode((string) $first['start_code']), Support::formatCode((string) $first['end_code']));
    $folder = $multiple
        ? sprintf('qrcodes_%s-%s_%d_lotes/', Support::formatCode((string) $first['start_code']), Support::formatCode((string) $last['end_code']), count($ids))
        : sprintf('lote_%s-%s/', Support::formatCode((string) $first['start_code']), Support::formatCode((string) $first['end_code']));
    header('Cache-Control: private, no-store');
    $zip = new ZipStream(outputName: $filename, sendHttpHeaders: true, contentType: 'application/zip');
    foreach ($rows as $row) {
        $code = Support::formatCode((string) $row['code']);
        $zip->addFile(fileName: $folder . $code . '.png', data: QrImageService::make($code, 'png'));
    }
    $zip->addFile(fileName: $folder . 'Conteudo.xlsx', data: XlsxService::make($rows));
    $zip->finish();
    exit;
}

function qrStatusPage(string $title, string $message, int $status): never
{
    http_response_code($status);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    echo "<!doctype html><html lang=\"pt-BR\"><head><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\"><meta name=\"robots\" content=\"noindex\"><title>{$safeTitle}</title><style>body{margin:0;min-height:100dvh;display:grid;place-items:center;background:#f4f7fb;color:#111827;font:16px/1.5 Arial,sans-serif;padding:24px;box-sizing:border-box}.box{max-width:520px;background:#fff;border:1px solid #dce3ec;border-radius:14px;padding:28px;box-shadow:0 16px 50px rgba(30,48,74,.1)}h1{font-size:24px;margin:0 0 8px}p{color:#5d6878;margin:0}</style></head><body><main class=\"box\"><h1>{$safeTitle}</h1><p>{$safeMessage}</p></main></body></html>";
    exit;
}

try {
    if ($path === '/api/auth/session' && $method === 'GET') jsonResponse(Auth::session());
    if ($path === '/api/auth/login' && $method === 'POST') jsonResponse(Auth::login(Support::jsonBody()));
    if ($path === '/api/auth/logout' && $method === 'POST') {
        Auth::logout();
        jsonResponse(['ok' => true]);
    }

    if (preg_match('#^/q/([A-Za-z0-9-]+)$#', $path, $match) && $method === 'GET') {
        try {
            $code = routeCode($match[1]);
            $qr = QrService::redirect($code);
            $target = $qr['destinationUrl'] ?: '/register/' . Support::formatCode($code);
            if ($qr['destinationUrl']) {
                try {
                    Support::destinationUrl($target);
                } catch (HttpException) {
                    qrStatusPage('Destino indisponível', 'O destino deste QR Code precisa ser atualizado.', 503);
                }
            }
            header('Cache-Control: no-store, max-age=0');
            header('Pragma: no-cache');
            header('Location: ' . $target, true, 307);
            exit;
        } catch (HttpException $error) {
            $titles = [400 => 'Código inválido', 404 => 'QR Code não encontrado', 410 => 'QR Code inativo'];
            qrStatusPage($titles[$error->status] ?? 'Destino indisponível', $error->getMessage(), $error->status);
        }
    }

    if (str_starts_with($path, '/api/')) {
        Auth::requireAdmin();
        if (!in_array($method, ['GET', 'HEAD'], true)) Auth::requireCsrf();

        if (str_starts_with($path, '/api/users')) Auth::requireUserManager();
        elseif (str_starts_with($path, '/api/batches') || str_starts_with($path, '/api/qrcodes')) Auth::requireQrManager();

        if ($path === '/api/users' && $method === 'GET') jsonResponse(UserService::page());
        if ($path === '/api/users' && $method === 'POST') jsonResponse(UserService::create(Support::jsonBody()), 201);
        if (preg_match('#^/api/users/([0-9a-f-]+)$#i', $path, $match)) {
            if ($method === 'PATCH') jsonResponse(UserService::update($match[1], Support::jsonBody()));
            if ($method === 'DELETE') jsonResponse(UserService::delete($match[1]));
        }

        if ($path === '/api/batches' && $method === 'GET') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            jsonResponse(BatchService::page($page));
        }
        if ($path === '/api/batches' && $method === 'POST') jsonResponse(BatchService::create(Support::jsonBody()['quantity'] ?? null), 201);
        if ($path === '/api/batches/delete' && $method === 'DELETE') jsonResponse(BatchService::delete(Support::ids(Support::jsonBody()['batchIds'] ?? null)));
        if ($path === '/api/batches/export' && $method === 'POST') streamZip(Support::ids(Support::jsonBody()['batchIds'] ?? null));
        if (preg_match('#^/api/batches/([0-9a-f-]+)/export$#i', $path, $match) && $method === 'GET') streamZip(Support::ids([$match[1]]));
        if (preg_match('#^/api/batches/([0-9a-f-]+)$#i', $path, $match) && $method === 'GET') jsonResponse(BatchService::find($match[1]));

        if ($path === '/api/qrcodes/suggestions' && $method === 'GET') jsonResponse(['items' => QrService::suggestions((string) ($_GET['q'] ?? ''))]);
        if ($path === '/api/qrcodes' && $method === 'GET') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $pageSize = min(100, max(1, (int) ($_GET['pageSize'] ?? 20)));
            jsonResponse(QrService::page($page, $pageSize, trim((string) ($_GET['q'] ?? ''))));
        }
        if (preg_match('#^/api/qrcodes/([A-Za-z0-9-]+)/image$#', $path, $match) && $method === 'GET') {
            $code = routeCode($match[1]);
            QrService::find($code);
            $format = ($_GET['format'] ?? 'svg') === 'png' ? 'png' : 'svg';
            $inline = ($_GET['inline'] ?? '') === '1';
            header('Content-Type: ' . ($format === 'png' ? 'image/png' : 'image/svg+xml; charset=utf-8'));
            header(sprintf('Content-Disposition: %s; filename="%s.%s"', $inline ? 'inline' : 'attachment', Support::formatCode($code), $format));
            header('Cache-Control: private, no-store');
            echo QrImageService::make($code, $format);
            exit;
        }
        if (preg_match('#^/api/qrcodes/([A-Za-z0-9-]+)/register$#', $path, $match) && $method === 'POST') jsonResponse(QrService::register(routeCode($match[1]), Support::jsonBody()));
        if (preg_match('#^/api/qrcodes/([A-Za-z0-9-]+)$#', $path, $match)) {
            $code = routeCode($match[1]);
            if ($method === 'GET') jsonResponse(QrService::find($code));
            if ($method === 'PUT') jsonResponse(QrService::update($code, Support::jsonBody()));
        }
        throw new HttpException(404, 'Rota não encontrada.');
    }

    $index = __DIR__ . '/app/index.html';
    if (is_file($index)) {
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-cache');
        readfile($index);
        exit;
    }
    throw new HttpException(503, 'Frontend ainda não foi compilado.');
} catch (HttpException $error) {
    if ($error->status === 429) header('Retry-After: 900');
    jsonResponse(['error' => $error->getMessage()], $error->status);
} catch (Throwable $error) {
    error_log((string) $error);
    jsonResponse(['error' => 'Não foi possível concluir a operação. Tente novamente.'], 500);
}
