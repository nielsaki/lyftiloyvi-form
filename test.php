<?php
error_reporting(E_ALL & ~E_DEPRECATED);

/**
 * Standalone LOCAL test harness only (not part of WordPress at runtime).
 *
 * Run from this folder:   php -S localhost:8080 test.php
 * Then open:              http://localhost:8080
 *
 * This file is committed to Git for developers but excluded from production
 * deploy (see .github/workflows/deploy-wordpress.yml). If it ever ends up on
 * a real WordPress host, the guard below stops it from running under Apache/nginx.
 */

$wp_load = __DIR__ . '/../../../wp-load.php';
if (is_file($wp_load) && php_sapi_name() !== 'cli-server') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'This script is for local testing only (PHP built-in server). It is not used on the live site.';
    exit;
}

// ─── WordPress stubs ────────────────────────────────────────────────────────

define('ABSPATH', __DIR__ . '/');
define('OBJECT', 'OBJECT');

function wp_verify_nonce($nonce, $action) { return true; }
function wp_nonce_field($action, $name, $referer = true, $echo = true) {
    $f = '<input type="hidden" name="' . htmlspecialchars($name, ENT_QUOTES) . '" value="test_nonce">';
    if ($echo) { echo $f; return ''; }
    return $f;
}
function sanitize_text_field($str)      { return trim(strip_tags((string)$str)); }
function sanitize_textarea_field($str)  { return trim(strip_tags((string)$str)); }
function sanitize_email($email)         { return trim((string)$email); }
function is_email($email)               { return (bool) filter_var($email, FILTER_VALIDATE_EMAIL); }
function esc_html($str)                 { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function esc_attr($str)                 { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function esc_textarea($str)             { return htmlspecialchars((string)$str, ENT_QUOTES, 'UTF-8'); }
function wp_unslash($val)               { return is_string($val) ? stripslashes($val) : $val; }
function current_time($type, $gmt = 0) {
    return ($type === 'mysql') ? date('Y-m-d H:i:s') : date($type);
}
function wp_generate_password($length = 12, $special = true, $extra_special = true) {
    return bin2hex(random_bytes((int)ceil($length / 2)));
}
function maybe_serialize($data)   { return is_array($data) ? serialize($data) : $data; }
function maybe_unserialize($data) { if (!is_string($data)) return $data; $u = @unserialize($data); return ($u !== false || $data === 'b:0;') ? $u : $data; }
function add_query_arg($key, $val, $url) { return $url . (str_contains($url, '?') ? '&' : '?') . $key . '=' . rawurlencode($val); }
function get_site_url()           { return 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost:8080'); }
function add_action($a, $b)       {}
function add_shortcode($a, $b)    {}
function wp_add_inline_style($a, $b) {}

function wp_upload_dir() {
    $dir = __DIR__ . '/test_pdfs';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return ['path' => $dir, 'url' => '/test_pdfs', 'basedir' => $dir, 'baseurl' => '/test_pdfs'];
}
function sanitize_file_name($name) {
    return preg_replace('/[^a-zA-Z0-9._\-]/', '_', $name);
}
function trailingslashit($str) {
    return rtrim($str, '/\\') . '/';
}

function wp_die($html, $title = '', $args = []) {
    global $_LF_DEBUG, $lf_css;
    echo '<!DOCTYPE html><html lang="fo"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">';
    echo '<title>' . esc_html($title ?: 'Lyftiloyvi') . ' — Test</title>';
    echo '<style>' . $lf_css . '</style></head><body>';
    echo '<div class="test-banner">&#x1F6A7;&nbsp;<strong>TESTUMHVØRVI</strong>&nbsp;&#x1F6A7;&nbsp;Eingin teldupostur er sendur &middot; Lokalt JSON-DB</div>';
    echo '<div style="max-width:700px;margin:2rem auto;padding:1.5rem;background:#fff;border-radius:10px;box-shadow:0 4px 12px rgba(0,0,0,0.08);font-family:system-ui,-apple-system,sans-serif;">';
    echo $html;
    echo '</div>';
    echo lf_render_debug_panel($_LF_DEBUG);
    echo '<div style="text-align:center;margin:1.5rem auto;"><a href="/" style="color:#007cba;">&#x2190; Aftur til formið</a></div>';
    echo '</body></html>';
    exit;
}

// ─── Debug log collector ────────────────────────────────────────────────────

$_LF_DEBUG = [
    'db_insert'  => null,
    'db_updates' => [],
    'emails'     => [],
    'errors'     => [],
];

// ─── JSON-file fake database ────────────────────────────────────────────────

class FakeWpdb {
    public string $prefix     = 'wp_';
    public string $last_error = '';

    private string $db_file;

    public function __construct() {
        $this->db_file = __DIR__ . '/test_db.json';
    }

    private function load(): array {
        if (!file_exists($this->db_file)) return [];
        $data = json_decode(file_get_contents($this->db_file), true);
        return is_array($data) ? $data : [];
    }

    private function save(array $rows): void {
        file_put_contents($this->db_file, json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function insert(string $table, array $data, array $formats = []) {
        global $_LF_DEBUG;
        $rows = $this->load();
        $id = count($rows) ? max(array_column($rows, 'id')) + 1 : 1;
        $data['id'] = $id;
        $data['_table'] = $table;
        $rows[] = $data;
        $this->save($rows);
        $_LF_DEBUG['db_insert'] = ['table' => $table, 'data' => $data];
    }

    public function update(string $table, array $data, array $where, $formats = null, $where_formats = null) {
        global $_LF_DEBUG;
        $rows = $this->load();
        foreach ($rows as &$row) {
            $match = true;
            foreach ($where as $k => $v) {
                if (($row[$k] ?? null) != $v) { $match = false; break; }
            }
            if ($match) {
                foreach ($data as $k => $v) { $row[$k] = $v; }
                $_LF_DEBUG['db_updates'][] = ['table' => $table, 'set' => $data, 'where' => $where];
                break;
            }
        }
        unset($row);
        $this->save($rows);
    }

    public function get_row($query_ignored, $output = OBJECT) {
        return $this->_last_get_row_result;
    }

    public $_last_get_row_result = null;

    /**
     * Minimal prepare that just returns the column and value for our lookup.
     * In our stub the caller is always: prepare("...WHERE col = %s...", $val)
     */
    public function prepare(string $query, ...$args): string {
        $rows = $this->load();
        $this->_last_get_row_result = null;

        if (preg_match('/WHERE\s+(\w+)\s*=\s*%s/i', $query, $m) && !empty($args)) {
            $col = $m[1];
            $val = is_array($args[0]) ? $args[0][0] : $args[0];
            foreach ($rows as $row) {
                if (($row[$col] ?? '') === $val) {
                    $this->_last_get_row_result = (object) $row;
                    break;
                }
            }
        }
        return $query;
    }
}

$wpdb = new FakeWpdb();

function wp_mail($to, $subject, $body, $headers = [], $attachments = []) {
    global $_LF_DEBUG;
    $_LF_DEBUG['emails'][] = compact('to', 'subject', 'body');
    return true;
}

// ─── Load plugin logic ───────────────────────────────────────────────────────

require_once __DIR__ . '/includes/lf-config.php';
require_once __DIR__ . '/includes/lf-clubs.php';
require_once __DIR__ . '/includes/lf-pdf.php';
require_once __DIR__ . '/includes/lf-approvals.php';

// ─── CSS ─────────────────────────────────────────────────────────────────────

$lf_css = '
body {
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    background: #f0f2f5; margin: 0; color: #333;
}
.test-banner {
    background: #1a1a2e; color: #e0e0ff; text-align: center;
    padding: 0.55rem 1rem; font-size: 13px; letter-spacing: 0.04em;
}
.test-banner strong { color: #a8d8ff; }
.lf-form {
    max-width: 900px; margin: 2rem auto 3rem; padding: 1.75rem 2.5rem 2.5rem;
    background: #ffffff; border-radius: 10px; box-shadow: 0 6px 18px rgba(0,0,0,0.06); box-sizing: border-box;
}
.lf-form-title { margin: 0 0 1rem; font-size: 1.4rem; font-weight: 700; border-bottom: 1px solid #e5e5e5; padding-bottom: 0.5rem; }
.lf-form p { margin: 0 0 1rem; }
.lf-form label { display: block; font-weight: 600; margin-bottom: 0.25rem; }
.lf-info-block { background: #f8f9fa; border-radius: 6px; padding: 0.75rem 1rem; border: 1px solid #e2e4e7; font-size: 13px; line-height: 1.5; }
.lf-guardian-block { margin-top: 1rem; padding: 0.75rem 1rem; border-radius: 6px; background: #fdfdfd; border: 1px dashed #e2e4e7; }
.lf-row { display: flex; flex-wrap: wrap; gap: 1.5rem; }
.lf-col { flex: 1 1 0; min-width: 0; }
.lf-form input[type="text"], .lf-form input[type="email"], .lf-form input[type="date"], .lf-form select {
    width: 100%; padding: 0.5em 0.6em; border-radius: 4px; border: 1px solid #ccd0d4;
    box-sizing: border-box; font-size: 14px; font-family: inherit; background-color: #fff;
}
.lf-form input[type="text"]:focus, .lf-form input[type="email"]:focus, .lf-form select:focus {
    outline: none; border-color: #007cba; box-shadow: 0 0 0 1px #007cba33;
}
.lf-form button[type="submit"] {
    display: inline-block; padding: 0.7rem 1.6rem; border-radius: 4px; border: none;
    background: #007cba; color: #ffffff; font-size: 14px; font-weight: 600; cursor: pointer; transition: background 0.15s ease;
}
.lf-form button[type="submit"]:hover { background: #006ba1; }
.lf-form input[type="checkbox"], .lf-form input[type="radio"] { width: auto; margin-right: 0.4rem; }
.lf-form .lf-hp { display: none; }
.lf-success { padding: 0.6em 0.9em; margin: 1rem auto; border-radius: 4px; border: 1px solid #4caf50; background: #e8f5e9; color: #256029; max-width: 900px; }
.lf-error   { padding: 0.6em 0.9em; margin: 1rem auto; border-radius: 4px; border: 1px solid #f44336; background: #ffebee; color: #b71c1c; max-width: 900px; }
.lf-error ul { margin: 0.25rem 0 0; padding-left: 1.2rem; }
.lf-error li { margin: 0.15rem 0; }
.lf-notice { padding: 0.6em 0.9em; margin: 1rem auto; border-radius: 4px; max-width: 900px; }
.lf-notice-warning { border: 1px solid #f9a825; background: #fff8e1; color: #7c4a03; }
@media (max-width: 600px) {
    .lf-form { margin: 1.5rem 1rem 2.5rem; padding: 1.4rem 1.4rem 2rem; }
    .lf-row  { flex-direction: column; }
}

/* ── Debug panel ── */
.debug-panel { max-width: 900px; margin: 0 auto 3rem; background: #1e1e2e; color: #cdd6f4; border-radius: 10px; overflow: hidden; font-size: 13px; line-height: 1.6; }
.debug-panel-header { background: #181825; padding: 0.65rem 1.25rem; font-weight: 700; font-size: 13px; color: #cba6f7; border-bottom: 1px solid #313244; display: flex; align-items: center; gap: 0.5rem; }
.debug-section { padding: 1rem 1.25rem; border-bottom: 1px solid #313244; }
.debug-section:last-child { border-bottom: none; }
.debug-section h4 { margin: 0 0 0.5rem; font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #89b4fa; }
.debug-kv { display: flex; gap: 0.75rem; margin-bottom: 0.2rem; }
.debug-key { color: #f38ba8; min-width: 160px; flex-shrink: 0; }
.debug-val { color: #a6e3a1; word-break: break-all; }
.debug-email-item { margin-bottom: 0.75rem; }
.debug-email-body { white-space: pre-wrap; background: #181825; padding: 0.5rem 0.75rem; border-radius: 4px; margin-top: 0.3rem; color: #cdd6f4; font-family: "SF Mono", ui-monospace, monospace; font-size: 12px; }
.debug-empty { color: #6c7086; font-style: italic; }

/* ── Pending requests table ── */
.pending-table { max-width: 900px; margin: 0 auto 2rem; }
.pending-table h3 { font-size: 1rem; margin: 0 0 0.5rem; }
.pending-table table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.05); font-size: 13px; }
.pending-table th { background: #f8f9fa; text-align: left; padding: 0.5rem 0.75rem; border-bottom: 2px solid #e5e5e5; }
.pending-table td { padding: 0.45rem 0.75rem; border-bottom: 1px solid #eee; }
.pending-table a { color: #007cba; text-decoration: none; }
.pending-table a:hover { text-decoration: underline; }
.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 4px; font-size: 11px; font-weight: 600; }
.badge-pending { background: #fff3cd; color: #856404; }
.badge-approved { background: #d4edda; color: #155724; }
.badge-denied { background: #f8d7da; color: #721c24; }
.status-ok { color: #155724; font-weight: 600; font-size: 12px; }
.status-denied { color: #b71c1c; font-weight: 600; font-size: 14px; }
.status-wait { color: #856404; font-size: 14px; }
.status-na { color: #999; font-size: 12px; }
.status-link { color: #007cba; font-weight: 600; font-size: 12px; text-decoration: none; white-space: nowrap; }
.status-link:hover { text-decoration: underline; }
';

// ─── Debug panel renderer ────────────────────────────────────────────────────

function lf_render_debug_panel(array $debug): string {
    $has_anything = $debug['db_insert'] || !empty($debug['db_updates']) || !empty($debug['emails']) || !empty($debug['errors']);
    if (!$has_anything) return '';

    $h = '<div class="debug-panel"><div class="debug-panel-header">&#x1F9EA; Test Debug Panel — inkein teldupostur sendur, JSON-fíla brúkt sum DB</div>';

    if ($debug['db_insert']) {
        $h .= '<div class="debug-section"><h4>Database Insert</h4>';
        $d = $debug['db_insert'];
        $h .= '<div class="debug-kv"><span class="debug-key">Tabell</span><span class="debug-val">' . esc_html($d['table']) . '</span></div>';
        foreach ($d['data'] as $k => $v) {
            if ($k === 'data') {
                $unserialized = @unserialize($v);
                if (is_array($unserialized)) {
                    foreach ($unserialized as $dk => $dv) {
                        $h .= '<div class="debug-kv"><span class="debug-key">data.' . esc_html($dk) . '</span><span class="debug-val">' . esc_html(is_bool($dv) ? ($dv ? 'true' : 'false') : (string)$dv) . '</span></div>';
                    }
                    continue;
                }
            }
            $h .= '<div class="debug-kv"><span class="debug-key">' . esc_html($k) . '</span><span class="debug-val">' . esc_html((string)$v) . '</span></div>';
        }
        $h .= '</div>';
    }

    if (!empty($debug['db_updates'])) {
        $h .= '<div class="debug-section"><h4>Database Updates (' . count($debug['db_updates']) . ')</h4>';
        foreach ($debug['db_updates'] as $upd) {
            $h .= '<div style="margin-bottom:0.5rem;">';
            foreach ($upd['set'] as $k => $v) {
                $display = (is_string($v) && strlen($v) > 120) ? substr($v, 0, 120) . '...' : (string)$v;
                $h .= '<div class="debug-kv"><span class="debug-key">' . esc_html($k) . '</span><span class="debug-val">' . esc_html($display) . '</span></div>';
            }
            $h .= '</div>';
        }
        $h .= '</div>';
    }

    if (!empty($debug['emails'])) {
        $h .= '<div class="debug-section"><h4>Teldupostar (' . count($debug['emails']) . ')</h4>';
        foreach ($debug['emails'] as $mail) {
            $h .= '<div class="debug-email-item">';
            $h .= '<div class="debug-kv"><span class="debug-key">Til</span><span class="debug-val">' . esc_html($mail['to']) . '</span></div>';
            $h .= '<div class="debug-kv"><span class="debug-key">Evni</span><span class="debug-val">' . esc_html($mail['subject']) . '</span></div>';
            $h .= '<div class="debug-email-body">' . esc_html($mail['body']) . '</div>';
            $h .= '</div>';
        }
        $h .= '</div>';
    }

    $h .= '</div>';
    return $h;
}

// ─── Pending requests dashboard ──────────────────────────────────────────────

function lf_render_pending_requests(): string {
    $db_file = __DIR__ . '/test_db.json';
    if (!file_exists($db_file)) return '';
    $rows = json_decode(file_get_contents($db_file), true);
    if (empty($rows)) return '';

    $h = '<div class="pending-table"><h3>Umsóknir í test-DB (' . count($rows) . ')</h3>';
    $h .= '<table><tr><th>#</th><th>Navn</th><th>Felag</th><th>Støða</th><th>Felag</th><th>Verji</th><th>FSS</th><th>PDF</th></tr>';

    foreach ($rows as $row) {
        $data = @unserialize($row['data'] ?? '');
        $name = is_array($data) ? ($data['name'] ?? '?') : '?';
        $club = is_array($data) ? ($data['club'] ?? '') : '';
        $status = $row['status'] ?? 'pending';
        $is_minor = is_array($data) && !empty($data['is_minor']);

        $badge_class = 'badge-pending';
        if ($status === 'approved') $badge_class = 'badge-approved';
        if ($status === 'denied')   $badge_class = 'badge-denied';

        $club_approved     = is_array($data) && !empty($data['approved_by']);
        $guardian_approved  = is_array($data) && !empty($data['guardian_approved_by']);
        $fss_approved      = is_array($data) && !empty($data['fss_approved_by']);
        $is_denied         = ($status === 'denied');

        // Felag column
        if ($is_denied) {
            $felag_cell = '<span class="status-denied" title="Noktað">&#x2716;</span>';
        } elseif ($club_approved) {
            $who = is_array($data) ? ($data['approved_by'] ?? '') : '';
            $felag_cell = '<span class="status-ok" title="Góðkent av ' . esc_attr($who) . '">&#x2714; ' . esc_html($who) . '</span>';
        } elseif (!empty($row['token'])) {
            $felag_cell = '<a href="?lf_approve=' . rawurlencode($row['token']) . '" class="status-link">&#x23F3; Góðkenn</a>';
        } else {
            $felag_cell = '<span class="status-wait">&#x23F3;</span>';
        }

        // Verji column
        if (!$is_minor) {
            $verji_cell = '<span class="status-na">—</span>';
        } elseif ($is_denied) {
            $verji_cell = '<span class="status-denied">&#x2716;</span>';
        } elseif ($guardian_approved) {
            $who = is_array($data) ? ($data['guardian_approved_by'] ?? '') : '';
            $verji_cell = '<span class="status-ok" title="Góðkent av ' . esc_attr($who) . '">&#x2714; ' . esc_html($who) . '</span>';
        } elseif (!empty($row['guardian_token'])) {
            $verji_cell = '<a href="?lf_guardian_approve=' . rawurlencode($row['guardian_token']) . '" class="status-link">&#x23F3; Góðkenn</a>';
        } else {
            $verji_cell = '<span class="status-wait">&#x23F3;</span>';
        }

        // FSS column
        if ($is_denied) {
            $fss_cell = '<span class="status-denied">&#x2716;</span>';
        } elseif ($fss_approved) {
            $who = is_array($data) ? ($data['fss_approved_by'] ?? '') : '';
            $fss_cell = '<span class="status-ok" title="Góðkent av ' . esc_attr($who) . '">&#x2714; ' . esc_html($who) . '</span>';
        } elseif (!empty($row['fss_token'])) {
            $fss_cell = '<a href="?lf_fss_approve=' . rawurlencode($row['fss_token']) . '" class="status-link">&#x23F3; Góðkenn</a>';
        } else {
            $fss_cell = '<span class="status-wait">&#x23F3;</span>';
        }

        // PDF column
        $pdf_path = $row['pdf_path'] ?? '';
        if (!empty($pdf_path) && file_exists($pdf_path)) {
            $pdf_cell = '<a href="/test_pdfs/' . rawurlencode(basename($pdf_path)) . '" target="_blank" class="status-link">&#x1F4C4; Opna PDF</a>';
        } elseif ($status === 'approved') {
            $pdf_cell = '<span class="status-na">Eingin fíla</span>';
        } else {
            $pdf_cell = '<span class="status-na">—</span>';
        }

        $h .= '<tr>';
        $h .= '<td>' . (int)$row['id'] . '</td>';
        $h .= '<td>' . esc_html($name) . '</td>';
        $h .= '<td>' . esc_html($club) . '</td>';
        $h .= '<td><span class="badge ' . $badge_class . '">' . esc_html($status) . '</span></td>';
        $h .= '<td>' . $felag_cell . '</td>';
        $h .= '<td>' . $verji_cell . '</td>';
        $h .= '<td>' . $fss_cell . '</td>';
        $h .= '<td>' . $pdf_cell . '</td>';
        $h .= '</tr>';
    }

    $h .= '</table>';
    $h .= '<form method="get" style="margin-top:0.5rem;"><button type="submit" name="lf_clear_db" value="1" style="font-size:12px;padding:0.3rem 0.8rem;background:#dc3545;color:#fff;border:none;border-radius:4px;cursor:pointer;">Reinsa test-DB</button></form>';
    $h .= '</div>';
    return $h;
}

// ─── Form renderer (same logic as lf_render_form) ───────────────────────────

function lf_render_form_test(): string
{
    global $_LF_DEBUG, $wpdb;

    $output = '';
    $clubs  = lf_get_clubs();
    $club_chair_emails = lf_get_club_chair_emails();

    $name = $email = $birthdate = $address = $city = $phone = $club = $date = '';
    $honeypot = $consent = $guardian_name = $guardian_email = $guardian_phone = '';
    $age = null;
    $is_minor = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_form_submitted'])) {
        if (!isset($_POST['lf_nonce']) || !wp_verify_nonce($_POST['lf_nonce'], 'lf_submit')) {
            $output .= '<div class="lf-error">Trygdarkanning miseydnaðist. Royn aftur.</div>';
        } else {
            $honeypot       = sanitize_text_field($_POST['lf_hp'] ?? '');
            $name           = sanitize_text_field($_POST['lf_name'] ?? '');
            $email          = sanitize_email($_POST['lf_email'] ?? '');
            $birthdate      = sanitize_text_field($_POST['lf_birthdate'] ?? '');
            $address        = sanitize_text_field($_POST['lf_address'] ?? '');
            $city           = sanitize_text_field($_POST['lf_city'] ?? '');
            $phone          = sanitize_text_field($_POST['lf_phone'] ?? '');
            $guardian_name  = sanitize_text_field($_POST['lf_guardian_name'] ?? '');
            $guardian_email = sanitize_email($_POST['lf_guardian_email'] ?? '');
            $guardian_phone = sanitize_text_field($_POST['lf_guardian_phone'] ?? '');
            $club           = sanitize_text_field($_POST['lf_club'] ?? '');
            $date           = date('Y-m-d');
            $consent        = isset($_POST['lf_consent']) ? '1' : '';

            if (!empty($honeypot)) {
                $output .= '<div class="lf-success">Takk! Lyftiloyvið er móttikið.</div>';
                return $output;
            }

            $errors = [];

            if (empty($name)) {
                $errors[] = 'Vinaliga skriva fulla navn á íðkara.';
            } elseif (!preg_match('/\S+\s+\S+/', $name)) {
                $errors[] = 'Vinaliga skriva fulla navn (for-, millum- og eftirnavn).';
            }
            if (empty($birthdate)) {
                $errors[] = 'Vinaliga vel føðingardag.';
            } elseif (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $birthdate)) {
                $errors[] = 'Føðingardagur skal vera í forminum dd.mm.áááá.';
            } else {
                $dob = DateTime::createFromFormat('d.m.Y', $birthdate);
                if ($dob instanceof DateTime) {
                    $todayDate = new DateTime();
                    if ($dob > $todayDate) {
                        $errors[] = 'Føðingardagur kann ikki vera í framtíðini.';
                    } else {
                        $age      = $dob->diff($todayDate)->y;
                        if ($age > 100) $errors[] = 'Vinaliga kanna, um føðingardagurin er skrivaður rætt.';
                        $is_minor = ($age < 18);
                    }
                }
            }
            if (empty($email)) {
                $errors[] = 'Vinaliga skriva teldupost hjá íðkara.';
            } elseif (!is_email($email)) {
                $errors[] = 'Teldupostur er ikki í rættum sniði.';
            }
            if (empty($address)) $errors[] = 'Vinaliga skriva bústað.';
            if (empty($city))    $errors[] = 'Vinaliga skriva bý.';
            if (empty($phone)) {
                $errors[] = 'Vinaliga skriva telefonnummar hjá íðkara.';
            } elseif (!preg_match('/^[0-9 +]+$/', $phone)) {
                $errors[] = 'Telefonnummar má bara innihalda tøl, millumrúm og +.';
            }
            if ($is_minor) {
                if (empty($guardian_name))  $errors[] = 'Um íðkarin er yngri enn 18 ár, skal navn á verja fyllast út.';
                if (empty($guardian_email)) {
                    $errors[] = 'Um íðkarin er yngri enn 18 ár, skal teldupostur hjá verja verða fylt út.';
                } elseif (!is_email($guardian_email)) {
                    $errors[] = 'Teldupostur hjá verja er ikki í rættum sniði.';
                }
                if (empty($guardian_phone)) {
                    $errors[] = 'Um íðkarin er yngri enn 18 ár, skal telefonnummar hjá verja verða fylt út.';
                } elseif (!preg_match('/^[0-9 +]+$/', $guardian_phone)) {
                    $errors[] = 'Telefonnummar hjá verja má bara innihalda tøl, millumrúm og +.';
                }
            }
            if (empty($club)) {
                $errors[] = 'Vinaliga vel felag.';
            } elseif (!in_array($club, $clubs, true)) {
                $errors[] = 'Valda felagið er ikki eitt gyldigt val.';
            }
            if (empty($consent)) $errors[] = 'Vinaliga vátta, at tú góðtekur lyftiloyvisváttanina omanfyri.';

            if (!empty($errors)) {
                $output .= '<div class="lf-error"><ul>';
                foreach ($errors as $e) $output .= '<li>' . esc_html($e) . '</li>';
                $output .= '</ul></div>';
            } else {
                $subject_parts  = array_filter([$name, $club ? "($club)" : '']);
                $subject_suffix = trim(implode(' ', $subject_parts));
                $subject        = $subject_suffix ? 'Lyftiloyvi: ' . $subject_suffix : 'Lyftiloyvi: nýtt skjal';

                $submission_data = [
                    'name' => $name, 'birthdate' => $birthdate, 'address' => $address,
                    'city' => $city, 'phone' => $phone, 'email' => $email, 'club' => $club,
                    'date' => $date, 'is_minor' => $is_minor, 'guardian_name' => $guardian_name,
                    'guardian_email' => $guardian_email, 'guardian_phone' => $guardian_phone,
                ];

                $token          = wp_generate_password(32, false, false);
                $guardian_token = ($is_minor && !empty($guardian_email)) ? wp_generate_password(32, false, false) : '';
                $fss_token      = wp_generate_password(32, false, false);

                $wpdb->insert($wpdb->prefix . 'lf_lyftiloyvi_requests', [
                    'token'          => $token,
                    'guardian_token' => $guardian_token,
                    'fss_token'      => $fss_token,
                    'data'           => maybe_serialize($submission_data),
                    'pdf_path'       => '',
                    'status'         => 'pending',
                    'created_at'     => current_time('mysql', 1),
                ]);

                $approval_link     = add_query_arg('lf_approve',     $token,     get_site_url());
                $fss_approval_link = add_query_arg('lf_fss_approve', $fss_token, get_site_url());

                $chair_recipient = $club_chair_emails[$club] ?? lf_get_fss_email();
                $chair_body  = "Umsókn um lyftiloyvi - bíðar eftir góðkenning.\n\nNavn: {$name}\nFelag: {$club}\nFøðingardagur: {$birthdate}\nTeldupostur: {$email}\n\nGóðkenn her:\n{$approval_link}\n";
                wp_mail($chair_recipient, 'Góðkenning krevst: ' . $subject, $chair_body);

                $fss_body = "Umsókn um lyftiloyvi - krevur FSS góðkenning.\n\nNavn: {$name}\nFelag: {$club}\n\nGóðkenn her:\n{$fss_approval_link}\n";
                wp_mail(lf_get_fss_email(), 'Góðkenning krevst (FSS): ' . $subject, $fss_body);

                if ($is_minor && !empty($guardian_email) && !empty($guardian_token)) {
                    $g_link = add_query_arg('lf_guardian_approve', $guardian_token, get_site_url());
                    wp_mail($guardian_email, 'Góðkenning krevst (verji): ' . $subject, "Verji hjá {$name}.\n\nGóðkenn her:\n{$g_link}\n");
                }

                $output .= '<div class="lf-success">Takk! Umsóknin er móttikin. Trýst á leinkjurnar í tabellini niðanfyri fyri at testa góðkenningarnar.</div>';

                $name = $email = $birthdate = $address = $city = $phone = $club = $date = $consent = '';
                $guardian_name = $guardian_email = $guardian_phone = '';
            }
        }
    }

    // ── Form HTML ──

    $output .= '<form method="post" class="lf-form">';
    $output .= '<h2 class="lf-form-title">Váttan í samband við lyftiloyvi</h2>';
    $output .= wp_nonce_field('lf_submit', 'lf_nonce', true, false);
    $output .= '<input type="hidden" name="lf_form_submitted" value="1">';

    $output .= '<p><small>Við at fylla lyftiloyvi út, váttar tú at tú heldur galdandi reglur hjá ÍSF og teimum altjóða sambondunum, sum Føroya Styrkisamband virkar undir, umframt kanningar fyri doping sambært hesum reglum.</small></p>';
    $output .= '<p><small>Um tú skiftur felag, er neyðugt at fylla nýtt lyftiloyvið út.</small></p>';

    $output .= '<div class="lf-row">
        <div class="lf-col"><p><label>Fulla navn á íðkara *<br><input type="text" name="lf_name" required value="' . esc_attr($name) . '" placeholder="for-, millum- og eftirnavn"></label></p></div>
        <div class="lf-col"><p><label>Føðingardagur *<br><input type="text" name="lf_birthdate" required value="' . esc_attr($birthdate) . '" placeholder="dd.mm.áááá" pattern="\\d{2}\\.\\d{2}\\.\\d{4}"></label><small>Skriva føðingardag sum dd.mm.áááá – punktum verða sett sjálvvirkandi.</small></p></div>
    </div>';

    $output .= '<div class="lf-row">
        <div class="lf-col"><p><label>Teldupostur hjá íðkara *<br><input type="email" name="lf_email" required value="' . esc_attr($email) . '"></label></p></div>
        <div class="lf-col"><p><label>Telefonnummar hjá íðkara *<br><input type="text" name="lf_phone" required value="' . esc_attr($phone) . '" pattern="[0-9+\s]+"></label></p></div>
    </div>';

    $output .= '<div class="lf-row">
        <div class="lf-col"><p><label>Bústaður hjá íðkara *<br><input type="text" name="lf_address" required value="' . esc_attr($address) . '"></label></p></div>
        <div class="lf-col"><p><label>Býur/bygd *<br><input type="text" name="lf_city" required value="' . esc_attr($city) . '"></label></p></div>
    </div>';

    $output .= '<div class="lf-guardian-block">
        <p><strong>Um íðkarin er yngri enn 18 ár:</strong></p>
        <div class="lf-row">
            <div class="lf-col"><p><label>Navn á verja<br><input type="text" name="lf_guardian_name" value="' . esc_attr($guardian_name) . '"></label></p></div>
            <div class="lf-col"><p><label>Telefonnummar hjá verja<br><input type="text" name="lf_guardian_phone" value="' . esc_attr($guardian_phone) . '" pattern="[0-9+\s]+"></label></p></div>
        </div>
        <p><label>Teldupostur hjá verja<br><input type="email" name="lf_guardian_email" value="' . esc_attr($guardian_email) . '"></label></p>
    </div>';

    $output .= '<div class="lf-row"><div class="lf-col"><p><label>Felag *<br><select name="lf_club" required><option value="">Vel felag</option>';
    foreach ($clubs as $c) {
        $sel = ($club === $c) ? ' selected="selected"' : '';
        $output .= '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($c) . '</option>';
    }
    $output .= '</select></label></p></div></div>';

    $output .= '<p class="lf-info-block"><small>' . lf_get_doping_text() . '</small></p>';
    $output .= '<p><label><input type="checkbox" name="lf_consent" value="1"' . ($consent === '1' ? ' checked="checked"' : '') . ' required> Eg havi lisið og góðtikið lyftiloyvisváttanina, og góðtaki at mínar persónsupplýsingar verða viðgjørdar í hesum sambandi.</label></p>';
    $output .= '<p class="lf-info-block">' . lf_get_add_block_html() . '</p>';

    $output .= '<p class="lf-hp" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;"><label>Ikki fyll hetta út<br><input type="text" name="lf_hp" tabindex="-1" autocomplete="off"></label></p>';
    $output .= '<p><button type="submit">Lat lyftiloyvi inn</button></p>';
    $output .= '</form>';

    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.querySelector(".lf-form");
        if (!form) return;
        var bInput = form.querySelector("input[name=\"lf_birthdate\"]");
        if (bInput) {
            bInput.addEventListener("input", function() {
                var digits = this.value.replace(/\D/g, "").slice(0, 8);
                var parts = [];
                if (digits.length > 0) parts.push(digits.substring(0, Math.min(2, digits.length)));
                if (digits.length >= 3) parts.push(digits.substring(2, Math.min(4, digits.length)));
                if (digits.length >= 5) parts.push(digits.substring(4, 8));
                this.value = parts.join(".");
                updateGuardianBlock();
            });
        }
        var guardianBlock = form.querySelector(".lf-guardian-block");
        function updateGuardianBlock() {
            if (!guardianBlock || !bInput) return;
            var val = bInput.value.trim();
            var m = val.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
            if (!m) { guardianBlock.style.display = "none"; return; }
            var d = parseInt(m[1], 10), mo = parseInt(m[2], 10) - 1, y = parseInt(m[3], 10);
            var dob = new Date(y, mo, d);
            if (isNaN(dob.getTime())) { guardianBlock.style.display = "none"; return; }
            var today = new Date(), age = today.getFullYear() - y;
            var mDiff = today.getMonth() - mo;
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < d)) age--;
            if (age < 18) { guardianBlock.style.display = "block"; }
            else { guardianBlock.style.display = "none"; guardianBlock.querySelectorAll("input").forEach(function(i){i.value="";}); }
        }
        if (guardianBlock) guardianBlock.style.display = "none";
        updateGuardianBlock();
    });
    </script>';

    return $output;
}

// ─── Router ──────────────────────────────────────────────────────────────────

// Serve PDF files
$request_uri = $_SERVER['REQUEST_URI'] ?? '';
if (preg_match('#^/test_pdfs/(.+\.pdf)$#i', parse_url($request_uri, PHP_URL_PATH), $m)) {
    $file = __DIR__ . '/test_pdfs/' . basename($m[1]);
    if (file_exists($file)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . basename($file) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Clear DB action
if (isset($_GET['lf_clear_db'])) {
    $db_file = __DIR__ . '/test_db.json';
    if (file_exists($db_file)) unlink($db_file);
    $pdf_dir = __DIR__ . '/test_pdfs';
    if (is_dir($pdf_dir)) {
        foreach (glob($pdf_dir . '/*.pdf') as $f) unlink($f);
    }
    header('Location: /');
    exit;
}

// Approval routes — call the real plugin handlers
if (isset($_GET['lf_approve'])) {
    lf_handle_approval();
    exit;
}
if (isset($_GET['lf_guardian_approve'])) {
    lf_handle_guardian_approval();
    exit;
}
if (isset($_GET['lf_fss_approve'])) {
    lf_handle_fss_approval();
    exit;
}

// ─── Main page output ────────────────────────────────────────────────────────

$form_html     = lf_render_form_test();
$pending_html  = lf_render_pending_requests();
$debug_html    = lf_render_debug_panel($_LF_DEBUG);
?>
<!DOCTYPE html>
<html lang="fo">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Lyftiloyvi Form — Lokalt Test</title>
    <style><?= $lf_css ?></style>
</head>
<body>

<div class="test-banner">
    &#x1F6A7;&nbsp; <strong>TESTUMHVØRVI</strong> &nbsp;&#x1F6A7;&nbsp;
    Eingin teldupostur er sendur &middot; JSON-fíla brúkt sum DB &middot;
    Góðkenningarleinkjur virka!
</div>

<?= $form_html ?>
<?= $pending_html ?>
<?= $debug_html ?>

</body>
</html>
