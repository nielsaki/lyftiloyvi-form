<?php
/**
 * Plugin Name: Lyftiloyvi Form
 * Description: Online-Form til lyftiloyvi, sum sendir teldupost til FSS og felagið.
 * Version: 1.1.0
 * Author: Niels Áki Mørk
 */


if (!defined('ABSPATH')) {
    exit;
}

// Split logic into smaller files
require_once __DIR__ . '/includes/lf-config.php';
require_once __DIR__ . '/includes/lf-clubs.php';
require_once __DIR__ . '/includes/lf-pdf.php';
require_once __DIR__ . '/includes/lf-approvals.php';
require_once __DIR__ . '/includes/lf-admin.php';
require_once __DIR__ . '/includes/lf-form.php';
require_once __DIR__ . '/includes/lf-styles.php';
require_once __DIR__ . '/includes/lf-db.php';