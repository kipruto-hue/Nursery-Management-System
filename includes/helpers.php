<?php
require_once __DIR__ . '/db.php';

// Never show stack traces to visitors in production (§7)
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');
    error_reporting(E_ALL & ~E_DEPRECATED);
    set_exception_handler(function ($ex) {
        error_log($ex);
        $isApi = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/api/');
        http_response_code(500);
        if ($isApi) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Something went wrong. Please try again.']);
        } else {
            echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width">'
               . '<body style="font-family:system-ui;padding:40px;text-align:center">'
               . '<h2>Something went wrong</h2><p>Please go back and try again.</p>'
               . '<p><a href="/app/dashboard">Back to Home</a></p></body>';
        }
        exit;
    });
}

/** Escape for HTML output. */
function e(?string $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Format money as "KES 12,500" (drop cents when whole). */
function money($amount): string {
    $f = (float)$amount;
    $dec = (floor($f) == $f) ? 0 : 2;
    return 'KES ' . number_format($f, $dec);
}

/** Read and decode the JSON request body (empty array if none). */
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === '' || $raw === false) return [];
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Money keys never shown to workers. Cost keys are stripped from every
 * worker response; sale-price keys are stripped too unless the endpoint
 * explicitly allows them (workers do record sales and payments).
 */
const COST_KEYS = ['cost', 'total_cost', 'direct_expenses', 'direct_labour',
    'overhead_share', 'revenue', 'profit', 'margin', 'expense_total', 'labour_total',
    'estimated_value'];
const SALE_PRICE_KEYS = ['amount', 'unit_price', 'line_total', 'total_amount',
    'amount_paid', 'balance', 'default_sale_price', 'sale_price_override',
    'agreed_unit_price', 'deposit_paid'];

function strip_money_fields($data, bool $allow_sale_prices) {
    if (!is_array($data)) return $data;
    $out = [];
    foreach ($data as $k => $v) {
        if (is_string($k)) {
            if (in_array($k, COST_KEYS, true)) continue;
            if (!$allow_sale_prices && in_array($k, SALE_PRICE_KEYS, true)) continue;
        }
        $out[$k] = strip_money_fields($v, $allow_sale_prices);
    }
    return $out;
}

/**
 * Success response. For worker sessions, money fields are removed before
 * sending. Pass $allow_sale_prices = true only on sale/payment/price
 * endpoints where workers legitimately see selling prices (§3).
 */
function json_ok($data = null, bool $allow_sale_prices = false): void {
    if (($_SESSION['role'] ?? '') === 'worker') {
        $data = strip_money_fields($data, $allow_sale_prices);
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => true, 'data' => $data]);
    exit;
}

function json_fail(string $message, int $status = 400): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

/** Record a mutation in the activity log (§7). */
function activity_log(string $action, string $entity, ?int $entityId,
                      ?array $before = null, ?array $after = null): void {
    $stmt = db()->prepare(
        'INSERT INTO activity_log (user_id, action, entity, entity_id, before_json, after_json)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $_SESSION['user_id'] ?? null,
        $action, $entity, $entityId,
        $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE),
        $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE),
    ]);
}

/** Next batch code for a species, e.g. AVO-2026-014. */
function generate_batch_code(int $speciesId): string {
    $stmt = db()->prepare('SELECT code_prefix FROM species WHERE id = ?');
    $stmt->execute([$speciesId]);
    $prefix = $stmt->fetchColumn();
    if ($prefix === false) {
        throw new RuntimeException('Species not found');
    }
    $year = date('Y');
    $like = $prefix . '-' . $year . '-%';
    $stmt = db()->prepare(
        "SELECT MAX(CAST(SUBSTRING_INDEX(batch_code, '-', -1) AS UNSIGNED))
         FROM batches WHERE batch_code LIKE ?"
    );
    $stmt->execute([$like]);
    $next = (int)$stmt->fetchColumn() + 1;
    return sprintf('%s-%s-%03d', $prefix, $year, $next);
}

/** Read an app setting (nursery name etc.). */
function setting(string $key, string $default = ''): string {
    $stmt = db()->prepare('SELECT v FROM settings WHERE k = ?');
    $stmt->execute([$key]);
    $v = $stmt->fetchColumn();
    return $v === false || $v === null ? $default : $v;
}

function save_setting(string $key, string $value): void {
    $stmt = db()->prepare(
        'INSERT INTO settings (k, v) VALUES (?, ?) ON DUPLICATE KEY UPDATE v = VALUES(v)'
    );
    $stmt->execute([$key, $value]);
}

/** Require a value from the request body or fail with a plain message. */
function need(array $body, string $key, string $label) {
    if (!isset($body[$key]) || $body[$key] === '' || $body[$key] === null) {
        json_fail("Please fill in: $label");
    }
    return $body[$key];
}

/** Positive whole number or fail. */
function need_qty(array $body, string $key, string $label): int {
    $v = need($body, $key, $label);
    if (!is_numeric($v) || (int)$v != $v || (int)$v <= 0) {
        json_fail("$label must be a whole number above 0");
    }
    return (int)$v;
}

/** Non-negative money value or fail. */
function need_money(array $body, string $key, string $label, bool $allow_zero = true): float {
    $v = need($body, $key, $label);
    if (!is_numeric($v) || (float)$v < 0 || (!$allow_zero && (float)$v == 0)) {
        json_fail("$label must be a valid amount");
    }
    return round((float)$v, 2);
}

/** Valid Y-m-d date string (defaults to today) or fail. */
function opt_date(array $body, string $key): string {
    $v = $body[$key] ?? '';
    if ($v === '' || $v === null) return date('Y-m-d');
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) {
        json_fail('That date does not look right');
    }
    return $v;
}
