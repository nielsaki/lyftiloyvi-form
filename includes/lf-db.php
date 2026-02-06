<?php

if (!defined('ABSPATH')) {
    exit;
}

// DB-setup (tabell og schema trygging)
function lf_install_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE {$table_name} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        token VARCHAR(64) NOT NULL,
        guardian_token VARCHAR(64) DEFAULT NULL,
        fss_token VARCHAR(64) DEFAULT NULL,
        data LONGTEXT NOT NULL,
        pdf_path TEXT NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        created_at DATETIME NOT NULL,
        approved_at DATETIME DEFAULT NULL,
        fss_approved_at DATETIME DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY token (token),
        KEY guardian_token (guardian_token),
        KEY fss_token (fss_token),
        KEY status (status)
    ) {$charset_collate};";

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta($sql);
}

register_activation_hook(__FILE__, 'lf_install_table');

function lf_ensure_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if ($exists !== $table_name) {
        lf_install_table();
    }
}
add_action('plugins_loaded', 'lf_ensure_table_exists');

function lf_ensure_table_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if ($exists !== $table_name) {
        return;
    }

    $cols = $wpdb->get_results("SHOW COLUMNS FROM {$table_name}");
    if (!is_array($cols) || empty($cols)) return;

    $have = [];
    foreach ($cols as $c) {
        if (!empty($c->Field)) $have[$c->Field] = true;
    }

    $alters = [];

    if (empty($have['guardian_token']))   $alters[] = "ADD COLUMN guardian_token VARCHAR(64) DEFAULT NULL";
    if (empty($have['fss_token']))        $alters[] = "ADD COLUMN fss_token VARCHAR(64) DEFAULT NULL";
    if (empty($have['pdf_path']))         $alters[] = "ADD COLUMN pdf_path TEXT NOT NULL";
    if (empty($have['status']))           $alters[] = "ADD COLUMN status VARCHAR(20) NOT NULL DEFAULT 'pending'";
    if (empty($have['approved_at']))      $alters[] = "ADD COLUMN approved_at DATETIME DEFAULT NULL";
    if (empty($have['fss_approved_at']))  $alters[] = "ADD COLUMN fss_approved_at DATETIME DEFAULT NULL";

    if (!empty($alters)) {
        $wpdb->query("ALTER TABLE {$table_name} " . implode(', ', $alters));
    }

    $indexes = $wpdb->get_results("SHOW INDEX FROM {$table_name}");
    $idx_have = [];
    foreach ((array)$indexes as $idx) {
        if (!empty($idx->Key_name)) $idx_have[$idx->Key_name] = true;
    }

    if (empty($idx_have['token']))          $wpdb->query("ALTER TABLE {$table_name} ADD INDEX token (token)");
    if (empty($idx_have['guardian_token'])) $wpdb->query("ALTER TABLE {$table_name} ADD INDEX guardian_token (guardian_token)");
    if (empty($idx_have['fss_token']))      $wpdb->query("ALTER TABLE {$table_name} ADD INDEX fss_token (fss_token)");
    if (empty($idx_have['status']))         $wpdb->query("ALTER TABLE {$table_name} ADD INDEX status (status)");
}
add_action('plugins_loaded', 'lf_ensure_table_schema', 20);

