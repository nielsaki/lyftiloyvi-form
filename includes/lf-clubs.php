<?php

if (!defined('ABSPATH')) {
    exit;
}

function lf_get_clubs() {
    return [
        'Burn Íðkarar',
        'FullPower Kúlulyft',
        'Megingjørð',
        'Styrkifelagið Stoyt',
        'Trúðheimur',
        'ÍF Tvørmegi',
    ];
}

/**
 * Default formannsteldupostir (brúkt um onki er sett í WordPress-stillingunum).
 */
function lf_get_club_chair_emails_defaults() {
    return [
        'Burn Íðkarar'        => 'athletics@burn-athletics.fo',
        'FullPower Kúlulyft'  => 'lyftiloyvi@fss.fo',
        'Megingjørð'          => 'lyftiloyvi@fss.fo',
        'Styrkifelagið Stoyt' => 'stoyt@stoyt.fo',
        'Trúðheimur'          => 'rhjaltalin@hotmail.com',
        'ÍF Tvørmegi'         => 'lyftiloyvi@fss.fo',
    ];
}

/**
 * Formannsteldupostir: fyrst WordPress-stillingar, síðan sjálvgevnar.
 * Tómt ella ógyldugt teldupost → fellur aftur til sjálvgevið.
 */
function lf_get_club_chair_emails() {
    $defaults = lf_get_club_chair_emails_defaults();
    $saved    = get_option('lf_club_chair_emails', []);
    if (!is_array($saved)) {
        $saved = [];
    }
    $fallback = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';
    $out      = [];
    foreach (lf_get_clubs() as $club) {
        $raw = isset($saved[$club]) ? trim((string) $saved[$club]) : '';
        if ($raw !== '' && is_email($raw)) {
            $out[$club] = $raw;
        } else {
            $out[$club] = $defaults[$club] ?? $fallback;
        }
    }
    return $out;
}