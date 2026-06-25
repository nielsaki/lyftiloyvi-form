<?php

if (!defined('ABSPATH')) {
    exit;
}
 
/**
 * Teldupostar til margfeldis «send PDF aftur»: FSS, formans-mail hjá felagnum,
 * íðkari og verji (bert um undir 18 ár og galdandi teldupostur hjá verja).
 *
 * @param array<string,mixed> $data
 *
 * @return list<string>
 */
function lf_admin_bulk_pdf_recipient_emails(array $data): array {
    $recipient_list = [];

    $fss_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';
    if ($fss_email !== '') {
        $recipient_list[] = $fss_email;
    }

    $club_nm = isset($data['club']) ? (string) $data['club'] : '';
    if ($club_nm !== '' && function_exists('lf_get_club_chair_emails')) {
        $club_map = lf_get_club_chair_emails();
        if (!empty($club_map[$club_nm])) {
            $recipient_list[] = $club_map[$club_nm];
        }
    }

    $ath = $data['email'] ?? '';
    if ($ath && is_email($ath)) {
        $recipient_list[] = $ath;
    }

    if (!empty($data['is_minor'])) {
        $gmail = $data['guardian_email'] ?? '';
        if ($gmail && is_email($gmail)) {
            $recipient_list[] = $gmail;
        }
    }

    return array_values(array_unique(array_filter(array_map(static function ($x) {
        return strtolower(trim((string) $x));
    }, $recipient_list))));
}

/**
 * Admin-yvirlit yvir kappingarloyviumsóknir.
 */
function lf_register_admin_menu() {
    add_menu_page(
        'Kappingarloyvi',
        'Kappingarloyvi',
        'manage_options',
        'lf-kappingarloyvi',
        'lf_render_admin_page',
        'dashicons-forms',
        26
    );

    add_submenu_page(
        'lf-kappingarloyvi',
        'Felagsteldupostir',
        'Felagsteldupostir',
        'manage_options',
        'lf-club-emails',
        'lf_render_club_emails_settings_page'
    );
}
add_action('admin_menu', 'lf_register_admin_menu');

/**
 * Stillingar: teldupostur til formann/nevnd hjá hvørjum felagi (góðkenningarleinkja).
 */
function lf_render_club_emails_settings_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Tú hevur ikki rættindi at síggja hesa síðuna.', 'lf'));
    }

    $defaults = function_exists('lf_get_club_chair_emails_defaults') ? lf_get_club_chair_emails_defaults() : [];
    $saved    = get_option('lf_club_chair_emails', []);
    if (!is_array($saved)) {
        $saved = [];
    }

    if (isset($_POST['lf_club_emails_save']) && isset($_POST['lf_club_emails_nonce']) && wp_verify_nonce($_POST['lf_club_emails_nonce'], 'lf_club_emails_save')) {
        $out = [];
        foreach (lf_get_clubs() as $club) {
            $val = isset($_POST['lf_club_email'][ $club ]) ? sanitize_email(wp_unslash($_POST['lf_club_email'][ $club ])) : '';
            $out[ $club ] = $val;
        }
        update_option('lf_club_chair_emails', $out, false);
        $saved = $out;
        echo '<div class="notice notice-success is-dismissible"><p>Teldupostir eru goymdir.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Felagsteldupostir</h1>';
    echo '<p>Set teldupost, har góðkenningarleinkjan fyri kappingarloyvi skal sendast til hvørt felag. Tómt felt brúkar sjálvgevið (sjá undir hvørjum felti).</p>';

    echo '<form method="post" action="">';
    wp_nonce_field('lf_club_emails_save', 'lf_club_emails_nonce');

    echo '<table class="form-table" role="presentation">';
    foreach (lf_get_clubs() as $club) {
        $def = isset($defaults[ $club ]) ? $defaults[ $club ] : '';
        $val = isset($saved[ $club ]) ? $saved[ $club ] : '';
        echo '<tr>';
        echo '<th scope="row"><label for="lf_ce_' . esc_attr(md5($club)) . '">' . esc_html($club) . '</label></th>';
        echo '<td>';
        echo '<input type="email" class="regular-text" id="lf_ce_' . esc_attr(md5($club)) . '" name="lf_club_email[' . esc_attr($club) . ']" value="' . esc_attr($val) . '" placeholder="' . esc_attr($def) . '" />';
        echo '<p class="description">Sjálvgevið: <code>' . esc_html($def) . '</code></p>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</table>';

    submit_button('Goym', 'primary', 'lf_club_emails_save');
    echo '</form>';
    echo '</div>';
}

/**
 * Render admin-síðu við yvirliti yvir seinastu umsóknirnar.
 */
function lf_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Tú hevur ikki rættindi at síggja hesa síðuna.', 'lf'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_kappingarloyvi_requests';

    $message           = '';
    $bulk_notice_key   = 'lf_bulk_notice_' . get_current_user_id();
    $bulk_notice_flash = get_transient($bulk_notice_key);
    if ($bulk_notice_flash !== false) {
        delete_transient($bulk_notice_key);
        if (is_string($bulk_notice_flash)) {
            $message = $bulk_notice_flash;
        }
    }

    // Margfeldis-handling (ikk í rætta-sýnin)
    if (
        !isset($_GET['edit_id']) &&
        isset($_POST['lf_bulk_submit']) &&
        isset($_POST['lf_bulk_nonce']) &&
        wp_verify_nonce(wp_unslash($_POST['lf_bulk_nonce']), 'lf_bulk_requests')
    ) {
        $bulk_action = isset($_POST['lf_bulk_action']) ? sanitize_text_field(wp_unslash($_POST['lf_bulk_action'])) : '';
        $bulk_ids    = isset($_POST['lf_bulk_ids']) ? array_map('intval', (array) wp_unslash($_POST['lf_bulk_ids'])) : [];
        $bulk_ids    = array_values(array_unique(array_filter($bulk_ids, static function ($id) {
            return $id > 0;
        })));

        $redirect_args = [
            'page'       => 'lf-kappingarloyvi',
            'lf_status'  => isset($_POST['lf_keep_status']) ? sanitize_text_field(wp_unslash($_POST['lf_keep_status'])) : '',
            'lf_club'    => isset($_POST['lf_keep_club']) ? sanitize_text_field(wp_unslash($_POST['lf_keep_club'])) : '',
            'lf_search'  => isset($_POST['lf_keep_search']) ? sanitize_text_field(wp_unslash($_POST['lf_keep_search'])) : '',
            'lf_minor'   => !empty($_POST['lf_keep_minor']) ? '1' : '',
            'paged'      => isset($_POST['lf_keep_paged']) ? max(1, intval(wp_unslash($_POST['lf_keep_paged']))) : 1,
        ];

        $bulk_notice_msg = '';
        if ($bulk_action === '' || empty($bulk_ids)) {
            $bulk_notice_msg = 'Vel handling og minst ein røð.';
        } elseif ($bulk_action === 'delete') {
            $deleted_n = 0;
            foreach ($bulk_ids as $bid) {
                if ($wpdb->delete($table_name, ['id' => $bid], ['%d'])) {
                    $deleted_n++;
                }
            }
            $bulk_notice_msg = $deleted_n === 1
                ? 'Ein umsókn er strikað.'
                : sprintf('%d umsóknir eru strikaðar.', $deleted_n);
        } elseif ($bulk_action === 'clear_club_approval') {
            $updated_n = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                $bdata['approved_by']       = '';
                $bdata['club_approved_date'] = '';

                $new_status = $brow->status;
                if ($brow->status === 'approved' || $brow->status === 'pending_fss') {
                    $new_status = 'pending';
                }

                $upd = $wpdb->update(
                    $table_name,
                    [
                        'data'   => maybe_serialize($bdata),
                        'status' => $new_status,
                    ],
                    ['id' => $bid],
                    ['%s', '%s'],
                    ['%d']
                );
                if ($upd !== false) {
                    $updated_n++;
                }
            }
            $bulk_notice_msg = $updated_n === 1
                ? '«Góðkent av felagi» er strikað á einari røð.'
                : sprintf('«Góðkent av felagi» er strikað á %d røðum.', $updated_n);
        } elseif ($bulk_action === 'reconsent') {
            $ok_rc = 0; $fail_rc = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) { $fail_rc++; continue; }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) $bdata = [];
                if (function_exists('lf_trigger_reconsent') && lf_trigger_reconsent($brow, $bdata)) {
                    $ok_rc++;
                } else {
                    $fail_rc++;
                }
            }
            $bulk_notice_msg = sprintf('Nýggjar-skilmálar-beiðni er send til %d umsókna.', $ok_rc)
                . ($fail_rc > 0 ? ' ' . sprintf('%d miseydnaðust.', $fail_rc) : '');
        } elseif ($bulk_action === 'regenerate_pdf') {
            $ok_pdf = 0;
            $fail_pdf = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                if (!empty($brow->pdf_path) && is_string($brow->pdf_path)) {
                    $old_paths   = $bdata['old_pdf_paths'] ?? [];
                    $old_paths[] = $brow->pdf_path;
                    $bdata['old_pdf_paths'] = $old_paths;
                }
                if (empty($bdata['consent_timestamp']) && !empty($brow->created_at)) {
                    $bdata['consent_timestamp'] = $brow->created_at;
                }
                $new_pdf = function_exists('lf_generate_pdf') ? lf_generate_pdf($bdata) : null;
                if ($new_pdf && file_exists($new_pdf)) {
                    $wpdb->update($table_name, ['pdf_path' => $new_pdf, 'data' => maybe_serialize($bdata)], ['id' => $bid], ['%s', '%s'], ['%d']);
                    $ok_pdf++;
                } else {
                    $fail_pdf++;
                }
            }
            $bulk_notice_msg = sprintf('PDF er endurgerð á %d røðum.', $ok_pdf)
                . ($fail_pdf > 0 ? ' ' . sprintf('%d miseydnaðust.', $fail_pdf) : '');
        } elseif ($bulk_action === 'resend_pdf') {
            $ok_mail = 0;
            $fail_mail = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                $recipients = lf_admin_bulk_pdf_recipient_emails($bdata);
                if (!function_exists('lf_admin_resend_pdf_to_recipients')) {
                    $fail_mail++;
                    continue;
                }
                if (empty($bdata['consent_timestamp']) && !empty($brow->created_at)) {
                    $bdata['consent_timestamp'] = $brow->created_at;
                }
                $res = lf_admin_resend_pdf_to_recipients($bdata, $recipients, '');
                if (!empty($res['pdf_path'])) {
                    $wpdb->update(
                        $table_name,
                        ['pdf_path' => $res['pdf_path']],
                        ['id' => $bid],
                        ['%s'],
                        ['%d']
                    );
                }
                if (!empty($res['sent_any'])) {
                    $ok_mail++;
                } else {
                    $fail_mail++;
                }
            }
            $bulk_notice_msg = sprintf(
                'PDF er send aftur hjá minst einum mótaki á %d umsóknum.%s',
                $ok_mail,
                $fail_mail > 0 ? ' ' . sprintf('Kundi ikki senda hjá %d (kanna teldupost / mótakarar).', $fail_mail) : ''
            );
        } elseif ($bulk_action === 'resend_link_club') {
            $ok_lc = 0;
            $fail_lc = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                if (lf_admin_send_club_approval_link($brow, $bdata)) {
                    $ok_lc++;
                } else {
                    $fail_lc++;
                }
            }
            $bulk_notice_msg = sprintf('Felags-leinkjan er send til %d umsókna.', $ok_lc)
                . ($fail_lc > 0 ? ' ' . sprintf('%d miseydnaðust.', $fail_lc) : '');
        } elseif ($bulk_action === 'resend_link_guardian') {
            $ok_v = 0;
            $fail_v = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                if (lf_admin_send_guardian_approval_link($brow, $bdata)) {
                    $ok_v++;
                } else {
                    $fail_v++;
                }
            }
            $bulk_notice_msg = sprintf('Verju-leinkjan er send til %d umsókna.', $ok_v)
                . ($fail_v > 0 ? ' ' . sprintf('%d miseydnaðust (ikki kravt ella manglar teldupostur hjá verja).', $fail_v) : '');
        } elseif ($bulk_action === 'resend_link_fss') {
            $ok_f = 0;
            $fail_f = 0;
            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) {
                    continue;
                }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) {
                    $bdata = [];
                }
                if (lf_request_fss_approval($brow, $bdata)) {
                    $ok_f++;
                } else {
                    $fail_f++;
                }
            }
            $bulk_notice_msg = sprintf('FSS-góðkenning er biðjað/send til kappingarloyvi hjá %d umsóknum.', $ok_f)
                . ($fail_f > 0 ? ' ' . sprintf('%d miseydnaðust.', $fail_f) : '');
        } elseif ($bulk_action === 'custom_email') {
            $custom_subject    = sanitize_text_field(wp_unslash($_POST['lf_custom_subject'] ?? ''));
            $custom_body       = sanitize_textarea_field(wp_unslash($_POST['lf_custom_body'] ?? ''));
            $send_to           = isset($_POST['lf_custom_send_to']) ? array_map('sanitize_text_field', (array) wp_unslash($_POST['lf_custom_send_to'])) : [];
            $regen_pdf         = !empty($_POST['lf_custom_regen_pdf']);
            $club_chair_emails = lf_get_club_chair_emails();
            $sent_log = []; $fail_log = [];

            foreach ($bulk_ids as $bid) {
                $brow = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $bid));
                if (!$brow) { $fail_log[] = "#$bid: fannst ikki"; continue; }
                $bdata = maybe_unserialize($brow->data);
                if (!is_array($bdata)) $bdata = [];
                $bname = $bdata['name'] ?? "#{$bid}";

                // Use existing PDF unless regeneration is requested or no PDF exists
                $pdf_path = is_string($brow->pdf_path ?? null) ? $brow->pdf_path : '';
                if ($regen_pdf || empty($pdf_path) || !file_exists($pdf_path)) {
                    if (!empty($pdf_path) && is_string($pdf_path)) {
                        $bdata_old_paths   = $bdata['old_pdf_paths'] ?? [];
                        $bdata_old_paths[] = $pdf_path;
                        $bdata['old_pdf_paths'] = $bdata_old_paths;
                    }
                    if (empty($bdata['consent_timestamp']) && !empty($brow->created_at)) {
                        $bdata['consent_timestamp'] = $brow->created_at;
                    }
                    $pdf_path = function_exists('lf_generate_pdf') ? lf_generate_pdf($bdata) : null;
                    if ($pdf_path && file_exists($pdf_path)) {
                        $wpdb->update($table_name, ['pdf_path' => $pdf_path, 'data' => maybe_serialize($bdata)], ['id' => $bid], ['%s', '%s'], ['%d']);
                    }
                }
                $attachments = (!empty($pdf_path) && file_exists($pdf_path)) ? [$pdf_path] : [];

                // Build recipient list
                $recipients = [];
                if (in_array('fss', $send_to, true)) {
                    $fss_e = function_exists('lf_get_fss_email') ? lf_get_fss_email() : '';
                    if ($fss_e) $recipients[] = $fss_e;
                }
                if (in_array('club', $send_to, true)) {
                    $club_e = $club_chair_emails[$bdata['club'] ?? ''] ?? '';
                    if ($club_e) $recipients[] = $club_e;
                    else error_log("LF custom_email: ongin teldupostur fyri felag «" . ($bdata['club'] ?? '') . "» (umsókn #{$bid})");
                }
                if (in_array('athlete', $send_to, true) && !empty($bdata['email'])) {
                    $recipients[] = $bdata['email'];
                }
                if (in_array('guardian', $send_to, true) && !empty($bdata['is_minor']) && !empty($bdata['guardian_email'])) {
                    $recipients[] = $bdata['guardian_email'];
                }
                $recipients = array_values(array_unique(array_filter(array_map(static function ($x) {
                    return strtolower(trim((string) $x));
                }, $recipients))));

                if (empty($recipients)) {
                    $fail_log[] = "{$bname}: ongin galdandi móttakari";
                    error_log("LF custom_email: no valid recipients for submission #{$bid} ({$bname})");
                    continue;
                }

                $row_ok = []; $row_fail = [];
                foreach ($recipients as $to) {
                    if (!is_email($to)) { $row_fail[] = $to; continue; }
                    $ok = wp_mail($to, $custom_subject, $custom_body, [], $attachments);
                    if ($ok) { $row_ok[] = $to; }
                    else {
                        $row_fail[] = $to;
                        error_log("LF custom_email: wp_mail() returned false for {$to} (umsókn #{$bid})");
                    }
                }
                if (!empty($row_ok)) {
                    $sent_log[] = "{$bname} → " . implode(', ', $row_ok);
                }
                if (!empty($row_fail)) {
                    $fail_log[] = "{$bname}: miseydnaðist til " . implode(', ', $row_fail);
                }
            }

            $bulk_notice_msg = '';
            if (!empty($sent_log)) {
                $bulk_notice_msg .= 'Sent: ' . implode(' | ', $sent_log) . '.';
            }
            if (!empty($fail_log)) {
                $bulk_notice_msg .= ($bulk_notice_msg ? ' ' : '') . 'Miseydnaðist: ' . implode(' | ', $fail_log) . '. Kanna teldupost-skipan og felagsteldupostir.';
            }
            if ($bulk_notice_msg === '') {
                $bulk_notice_msg = 'Ongar umsóknir funnar.';
            }
        } else {
            $bulk_notice_msg = 'Ókend handling.';
        }

        set_transient($bulk_notice_key, $bulk_notice_msg, 60);
        wp_safe_redirect(add_query_arg(array_filter($redirect_args, static function ($v) {
            return $v !== '' && $v !== null;
        }), admin_url('admin.php')));
        exit;
    }

    // Edit view
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        if ($edit_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $edit_id));
            if (!$row) {
                echo '<div class="wrap"><h1>Kappingarloyvi</h1><p>Umsókn fannst ikki.</p></div>';
                return;
            }

            $data = maybe_unserialize($row->data);
            if (!is_array($data)) $data = [];

            // Save edits
            if (isset($_POST['lf_admin_save']) && isset($_POST['lf_admin_nonce']) && wp_verify_nonce($_POST['lf_admin_nonce'], 'lf_admin_edit')) {
                $clubs = lf_get_clubs();

                $data['name']      = sanitize_text_field($_POST['name'] ?? '');
                $data['birthdate'] = sanitize_text_field($_POST['birthdate'] ?? '');
                $data['email']     = sanitize_email($_POST['email'] ?? '');
                $data['phone']     = sanitize_text_field($_POST['phone'] ?? '');
                $data['address']   = sanitize_text_field($_POST['address'] ?? '');
                $data['city']      = sanitize_text_field($_POST['city'] ?? '');
                $data['club']      = sanitize_text_field($_POST['club'] ?? '');

                // minor/guardian fields (disabled inputs send nothing – clear when ikki minor)
                $data['is_minor'] = !empty($_POST['is_minor']) ? true : false;
                if (!empty($data['is_minor'])) {
                    $data['guardian_name']  = sanitize_text_field($_POST['guardian_name'] ?? '');
                    $data['guardian_email'] = sanitize_email($_POST['guardian_email'] ?? '');
                    $data['guardian_phone'] = sanitize_text_field($_POST['guardian_phone'] ?? '');
                } else {
                    $data['guardian_name']  = '';
                    $data['guardian_email'] = '';
                    $data['guardian_phone'] = '';
                }

                // Dates & approvals (PDF / goymd data)
                $data['date'] = sanitize_text_field($_POST['submission_date'] ?? '');
                $data['approved_by'] = sanitize_text_field($_POST['approved_by'] ?? '');
                $data['club_approved_date'] = sanitize_text_field($_POST['club_approved_date'] ?? '');
                $data['fss_approved_by'] = sanitize_text_field($_POST['fss_approved_by'] ?? '');
                $data['fss_approved_date'] = sanitize_text_field($_POST['fss_approved_date'] ?? '');
                if (!empty($data['is_minor'])) {
                    $data['guardian_approved_by'] = sanitize_text_field($_POST['guardian_approved_by'] ?? '');
                    $data['guardian_approved_date'] = sanitize_text_field($_POST['guardian_approved_date'] ?? '');
                } else {
                    $data['guardian_approved_by'] = '';
                    $data['guardian_approved_date'] = '';
                }

                // Simple validation for club
                if (!in_array($data['club'], $clubs, true)) {
                    $data['club'] = '';
                }

                $wpdb->update(
                    $table_name,
                    ['data' => maybe_serialize($data)],
                    ['id' => $row->id],
                    ['%s'],
                    ['%d']
                );

                $message = 'Umsóknin er dagførd.';
            }

            // Resend PDF
            if (isset($_POST['lf_admin_resend']) && isset($_POST['lf_admin_nonce']) && wp_verify_nonce($_POST['lf_admin_nonce'], 'lf_admin_edit')) {
                $club_chair_emails = lf_get_club_chair_emails();

                $send_to = array_map('sanitize_text_field', (array)($_POST['send_to'] ?? []));
                $explanation = sanitize_textarea_field($_POST['explanation'] ?? '');

                $recipient_list = [];
                if (in_array('fss', $send_to, true)) {
                    $recipient_list[] = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';
                }
                if (in_array('club', $send_to, true)) {
                    $club = $data['club'] ?? '';
                    $club_email = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : '';
                    if ($club_email) $recipient_list[] = $club_email;
                }
                if (in_array('athlete', $send_to, true)) {
                    $recipient_list[] = $data['email'] ?? '';
                }
                if (in_array('guardian', $send_to, true)) {
                    $recipient_list[] = $data['guardian_email'] ?? '';
                }

                $recipient_list = array_values(array_unique(array_filter(array_map(function($x){ return strtolower(trim($x)); }, $recipient_list))));

                if (empty($data['consent_timestamp']) && !empty($row->created_at)) {
                    $data['consent_timestamp'] = $row->created_at;
                }
                $res = lf_admin_resend_pdf_to_recipients($data, $recipient_list, $explanation);

                // Store pdf path if generated
                if (!empty($res['pdf_path'])) {
                    $wpdb->update(
                        $table_name,
                        ['pdf_path' => $res['pdf_path']],
                        ['id' => $row->id],
                        ['%s'],
                        ['%d']
                    );
                }

                $message = $res['sent_any'] ? 'PDF er send aftur.' : 'Kundi ikki senda (kanna móttakarar / teldupost-skipan).';
            }

            // Resend approval links (club / guardian / FSS)
            if (isset($_POST['lf_admin_resend_links']) && isset($_POST['lf_admin_nonce']) && wp_verify_nonce($_POST['lf_admin_nonce'], 'lf_admin_edit')) {
                $send_links_to = array_map('sanitize_text_field', (array)($_POST['send_links_to'] ?? []));

                $sent_any_link = false;

                if (in_array('club', $send_links_to, true) && function_exists('lf_admin_send_club_approval_link')) {
                    if (lf_admin_send_club_approval_link($row, $data)) {
                        $sent_any_link = true;
                    }
                }

                if (in_array('guardian', $send_links_to, true) && function_exists('lf_admin_send_guardian_approval_link')) {
                    if (lf_admin_send_guardian_approval_link($row, $data)) {
                        $sent_any_link = true;
                    }
                }

                if (in_array('fss', $send_links_to, true) && function_exists('lf_request_fss_approval')) {
                    if (lf_request_fss_approval($row, $data)) {
                        $sent_any_link = true;
                    }
                }

                if ($sent_any_link) {
                    $message = empty($message) ? 'Nýggj góðkenningar-link eru send.' : $message . ' Nýggj góðkenningar-link eru send.';
                } else {
                    if (empty($message)) {
                        $message = 'Kundi ikki senda góðkenningar-link (kanna móttakarar / teldupost-skipan).';
                    }
                }
            }

            // Regenerate PDF
            if (isset($_POST['lf_admin_regenerate_pdf']) && isset($_POST['lf_admin_nonce']) && wp_verify_nonce($_POST['lf_admin_nonce'], 'lf_admin_edit')) {
                // Archive old PDF path instead of deleting
                if (!empty($row->pdf_path) && is_string($row->pdf_path)) {
                    $old_paths   = $data['old_pdf_paths'] ?? [];
                    $old_paths[] = $row->pdf_path;
                    $data['old_pdf_paths'] = $old_paths;
                }
                // Generate new PDF
                if (empty($data['consent_timestamp']) && !empty($row->created_at)) {
                    $data['consent_timestamp'] = $row->created_at;
                }
                $new_pdf = lf_generate_pdf($data);
                if ($new_pdf && file_exists($new_pdf)) {
                    $wpdb->update($table_name, ['pdf_path' => $new_pdf, 'data' => maybe_serialize($data)], ['id' => $row->id], ['%s', '%s'], ['%d']);
                    $message = 'PDF er endurgjørd.';
                } else {
                    $message = 'Kundi ikki endurgera PDF.';
                }
            }

            // Reload freshest data
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $edit_id));
            $data = maybe_unserialize($row->data);
            if (!is_array($data)) $data = [];

            $clubs = lf_get_clubs();

            echo '<div class="wrap">';
            echo '<h1>Rætta umsókn #' . intval($row->id) . '</h1>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=lf-kappingarloyvi')) . '">← Aftur til yvirlit</a></p>';
            if (!empty($message)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
            }
            // Re-consent status panel
            if (!empty($row->reconsent_status)) {
                $data_for_rc = maybe_unserialize($row->data);
                if (!is_array($data_for_rc)) $data_for_rc = [];
                $is_minor_rc = !empty($data_for_rc['is_minor']);
                $rc_color = ($row->reconsent_status === 'complete') ? '#e8f5e9' : '#fff8e1';
                $rc_border = ($row->reconsent_status === 'complete') ? '#4caf50' : '#f9a825';
                echo '<div style="background:' . $rc_color . ';border:1px solid ' . $rc_border . ';border-radius:6px;padding:10px 14px;margin-bottom:14px;max-width:760px;">';
                echo '<strong>Re-consent staða: ' . esc_html(ucfirst($row->reconsent_status)) . '</strong>';
                echo '<ul style="margin:6px 0 0;padding-left:1.3em;font-size:13px;">';
                echo '<li>Íðkari: '  . (empty($row->reconsent_athlete_at)  ? '○ Bíðar' : '✓ ' . esc_html(substr($row->reconsent_athlete_at, 0, 10))) . '</li>';
                echo '<li>Felag: '   . (empty($row->reconsent_club_at)      ? '○ Bíðar' : '✓ ' . esc_html(substr($row->reconsent_club_at, 0, 10))) . '</li>';
                if ($is_minor_rc) {
                    echo '<li>Verji: ' . (empty($row->reconsent_guardian_at) ? '○ Bíðar' : '✓ ' . esc_html(substr($row->reconsent_guardian_at, 0, 10))) . '</li>';
                }
                echo '<li>FSS: '     . (empty($row->reconsent_fss_at)       ? '○ Bíðar' : '✓ ' . esc_html(substr($row->reconsent_fss_at, 0, 10))) . '</li>';
                echo '</ul></div>';
            }

            echo '<form method="post" style="max-width:760px;">';
            wp_nonce_field('lf_admin_edit', 'lf_admin_nonce');

            $name = esc_attr($data['name'] ?? '');
            $birthdate = esc_attr($data['birthdate'] ?? '');
            $email = esc_attr($data['email'] ?? '');
            $phone = esc_attr($data['phone'] ?? '');
            $address = esc_attr($data['address'] ?? '');
            $city = esc_attr($data['city'] ?? '');
            $club = $data['club'] ?? '';
            $is_minor = !empty($data['is_minor']);
            $g_fields_disabled = $is_minor ? '' : ' disabled';
            $gname = esc_attr($data['guardian_name'] ?? '');
            $gemail = esc_attr($data['guardian_email'] ?? '');
            $gphone = esc_attr($data['guardian_phone'] ?? '');

            $submission_date            = esc_attr($data['date'] ?? '');
            $approved_by_val            = esc_attr($data['approved_by'] ?? '');
            $club_approved_date_val    = esc_attr($data['club_approved_date'] ?? '');
            $guardian_approved_by_val   = esc_attr($data['guardian_approved_by'] ?? '');
            $guardian_approved_date_val = esc_attr($data['guardian_approved_date'] ?? '');
            $fss_approved_by_val        = esc_attr($data['fss_approved_by'] ?? '');
            $fss_approved_date_val      = esc_attr($data['fss_approved_date'] ?? '');

            echo '<h2>Upplýsingar</h2>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="lf_name">Navn</label></th><td><input id="lf_name" name="name" type="text" class="regular-text" value="' . $name . '" required></td></tr>';
            echo '<tr><th><label for="lf_birthdate">Føðingardagur (dd.mm.áááá)</label></th><td><input id="lf_birthdate" name="birthdate" type="text" class="regular-text" value="' . $birthdate . '" placeholder="dd.mm.áááá"></td></tr>';
            echo '<tr><th><label for="lf_email">Teldupostur</label></th><td><input id="lf_email" name="email" type="email" class="regular-text" value="' . $email . '"></td></tr>';
            echo '<tr><th><label for="lf_phone">Telefonnummar</label></th><td><input id="lf_phone" name="phone" type="text" class="regular-text" value="' . $phone . '"></td></tr>';
            echo '<tr><th><label for="lf_address">Bústaður</label></th><td><input id="lf_address" name="address" type="text" class="regular-text" value="' . $address . '"></td></tr>';
            echo '<tr><th><label for="lf_city">Býur/bygd</label></th><td><input id="lf_city" name="city" type="text" class="regular-text" value="' . $city . '"></td></tr>';

            echo '<tr><th><label for="lf_club">Felag</label></th><td><select id="lf_club" name="club">';
            echo '<option value="">Vel felag</option>';
            foreach ($clubs as $c) {
                $sel = ($club === $c) ? ' selected="selected"' : '';
                echo '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($c) . '</option>';
            }
            echo '</select></td></tr>';

            echo '<tr><th>Íðkari er undir 18 ár</th><td><label><input type="checkbox" name="is_minor" value="1"' . ($is_minor ? ' checked="checked"' : '') . '> Ja</label></td></tr>';
            echo '<tr><th><label for="lf_gname">Verji navn</label></th><td><input id="lf_gname" name="guardian_name" type="text" class="regular-text" value="' . $gname . '"' . $g_fields_disabled . '></td></tr>';
            echo '<tr><th><label for="lf_gemail">Verji teldupostur</label></th><td><input id="lf_gemail" name="guardian_email" type="email" class="regular-text" value="' . $gemail . '"' . $g_fields_disabled . '></td></tr>';
            echo '<tr><th><label for="lf_gphone">Verji telefonnummar</label></th><td><input id="lf_gphone" name="guardian_phone" type="text" class="regular-text" value="' . $gphone . '"' . $g_fields_disabled . '></td></tr>';

            echo '<tr><th><label for="lf_submission_date">Galdandi frá (á skjalinum)</label></th><td><input id="lf_submission_date" name="submission_date" type="text" class="regular-text" value="' . $submission_date . '" placeholder="YYYY-MM-DD"><p class="description">Sama dagur sum verður vístur undir «Galdandi frá» á PDF. Vanligt format er <code>YYYY-MM-DD</code> (eisini brúkt í PDF-fílunavninum).</p></td></tr>';

            echo '</tbody></table>';

            echo '<h2>Góðkenning (á PDF)</h2>';
            echo '<p class="description">Her kann tú rætta navn og dagsetningar, sum koma við á PDF undir «Góðkenning». Strik tey, um tey ikki skulu koma við.</p>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="lf_approved_by">Góðkent av (felag), navn</label></th><td><input id="lf_approved_by" name="approved_by" type="text" class="regular-text" value="' . $approved_by_val . '"></td></tr>';
            echo '<tr><th><label for="lf_club_approved_date">Góðkent av (felag), dagur</label></th><td><input id="lf_club_approved_date" name="club_approved_date" type="text" class="regular-text" value="' . $club_approved_date_val . '" placeholder="YYYY-MM-DD"></td></tr>';
            echo '<tr><th><label for="lf_guardian_approved_by">Góðkent av verja, navn</label></th><td><input id="lf_guardian_approved_by" name="guardian_approved_by" type="text" class="regular-text" value="' . $guardian_approved_by_val . '"' . $g_fields_disabled . '></td></tr>';
            echo '<tr><th><label for="lf_guardian_approved_date">Góðkent av verja, dagur</label></th><td><input id="lf_guardian_approved_date" name="guardian_approved_date" type="text" class="regular-text" value="' . $guardian_approved_date_val . '" placeholder="YYYY-MM-DD"' . $g_fields_disabled . '></td></tr>';
            echo '<tr><th><label for="lf_fss_approved_by">Góðkent av FSS, navn</label></th><td><input id="lf_fss_approved_by" name="fss_approved_by" type="text" class="regular-text" value="' . $fss_approved_by_val . '"></td></tr>';
            echo '<tr><th><label for="lf_fss_approved_date">Góðkent av FSS, dagur</label></th><td><input id="lf_fss_approved_date" name="fss_approved_date" type="text" class="regular-text" value="' . $fss_approved_date_val . '" placeholder="YYYY-MM-DD"></td></tr>';
            echo '</tbody></table>';

            echo '<p><button type="submit" name="lf_admin_save" value="1" class="button button-primary">Goym broytingar</button></p>';

            echo '<hr />';
            echo '<h2>Endurgera PDF</h2>';
            echo '<p>Ger eina nýggja PDF við goymdum upplýsingum (ókmt dags- og góðkenningarfeltini omanfyri). Gomla PDF-fílan verður goymd fyri dokumentatión.</p>';

            // Show current PDF
            if (!empty($row->pdf_path) && is_string($row->pdf_path)) {
                $upload_dir_e = wp_upload_dir();
                $bdir = $upload_dir_e['basedir'] ?? '';
                $burl = $upload_dir_e['baseurl'] ?? '';
                $cur_pdf_url = '';
                if ($bdir !== '' && $burl !== '' && strpos($row->pdf_path, $bdir) === 0) {
                    $cur_pdf_url = trailingslashit($burl) . ltrim(substr($row->pdf_path, strlen($bdir)), '/');
                }
                if ($cur_pdf_url) {
                    echo '<p><strong>Núverandi PDF:</strong> <a href="' . esc_url($cur_pdf_url) . '" target="_blank" rel="noopener">' . esc_html(basename($row->pdf_path)) . '</a></p>';
                }
            }

            // Show archived old PDFs
            $old_pdf_paths = $data['old_pdf_paths'] ?? [];
            if (!empty($old_pdf_paths)) {
                $upload_dir_e = wp_upload_dir();
                $bdir = $upload_dir_e['basedir'] ?? '';
                $burl = $upload_dir_e['baseurl'] ?? '';
                echo '<p><strong>Eldri PDF-fílur:</strong></p><ul style="margin:0 0 8px 1.3em;">';
                foreach (array_reverse($old_pdf_paths) as $old_path) {
                    if (!is_string($old_path) || $old_path === '') continue;
                    $old_url = '';
                    if ($bdir !== '' && $burl !== '' && strpos($old_path, $bdir) === 0) {
                        $old_url = trailingslashit($burl) . ltrim(substr($old_path, strlen($bdir)), '/');
                    }
                    if ($old_url) {
                        $exists = file_exists($old_path) ? '' : ' <em style="color:#999;">(fíla finnst ikki)</em>';
                        echo '<li><a href="' . esc_url($old_url) . '" target="_blank" rel="noopener">' . esc_html(basename($old_path)) . '</a>' . $exists . '</li>';
                    }
                }
                echo '</ul>';
            }

            echo '<p><button type="submit" name="lf_admin_regenerate_pdf" value="1" class="button" onclick="return confirm(\'Ert tú viss(ur)? Ein nýggj PDF verður gjørd. Gomla PDF-fílan verður goymd.\');">Endurgera PDF</button></p>';

            echo '<hr />';
            echo '<h2>Send PDF aftur</h2>';
            echo '<p><button type="button" class="button" id="lf-open-resend">Send aftur…</button></p>';

            echo '<div id="lf-resend-box" style="display:none;max-width:760px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:12px 14px;">';
            echo '<p><strong>Vel hvør skal fáa PDF\'ina</strong></p>';
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="send_to[]" value="fss" checked> FSS (lyftiloyvi@fss.fo)</label>';
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="send_to[]" value="club" checked> Felag (formans-teldupostur)</label>';
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="send_to[]" value="athlete"> Íðkari</label>';
            echo '<label style="display:block;margin:6px 0;"><input type="checkbox" name="send_to[]" value="guardian"> Verji</label>';

            echo '<p style="margin-top:10px;"><label><strong>Forklaring (verður sett fremst í teldupostinum)</strong><br>';
            echo '<textarea name="explanation" rows="4" style="width:100%;max-width:720px;"></textarea></label></p>';

            echo '<p style="display:flex;gap:10px;flex-wrap:wrap;">';
            echo '<button type="submit" name="lf_admin_resend" value="1" class="button button-secondary">Send nú</button>';
            echo '<button type="button" class="button" id="lf-close-resend">Lukka</button>';
            echo '</p>';
            echo '</div>';

            echo '<hr />';
            echo '<h2>Send nýtt góðkenningar-link</h2>';
            echo '<p>Her kann tú senda nýggj góðkenningar-link til felag, verjan og FSS, um tey hava mist uppruna teldupostin.</p>';
            echo '<div style="max-width:760px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;padding:12px 14px;margin-top:8px;">';
            echo '<p><strong>Vel hvør skal fáa nýtt góðkenningar-link</strong></p>';
            echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="send_links_to[]" value="club" checked> Felag (formans-teldupostur)</label>';
            echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="send_links_to[]" value="guardian"' . (!empty($data['is_minor']) ? '' : ' disabled') . '> Verji</label>';
            echo '<label style="display:block;margin:4px 0;"><input type="checkbox" name="send_links_to[]" value="fss"> FSS</label>';
            echo '<p style="margin-top:10px;"><button type="submit" name="lf_admin_resend_links" value="1" class="button">Send góðkenningar-link</button></p>';
            echo '<p><small>Verji-leinkjan er bara virkin, um umsóknin er markerað sum undir 18 ár og verju-upplýsingar eru fyltar út.</small></p>';
            echo '</div>';

            echo '</form>';

            echo '<script>
            (function(){
                var openBtn = document.getElementById("lf-open-resend");
                var box = document.getElementById("lf-resend-box");
                var closeBtn = document.getElementById("lf-close-resend");
                if(openBtn && box){
                    openBtn.addEventListener("click", function(){ box.style.display = "block"; openBtn.style.display = "none"; });
                }
                if(closeBtn && box && openBtn){
                    closeBtn.addEventListener("click", function(){ box.style.display = "none"; openBtn.style.display = "inline-block"; });
                }
                var minorChk = document.querySelector("input[name=\"is_minor\"]");
                var gName = document.getElementById("lf_gname");
                var gMail = document.getElementById("lf_gemail");
                var gPhone = document.getElementById("lf_gphone");
                var gApprBy = document.getElementById("lf_guardian_approved_by");
                var gApprDt = document.getElementById("lf_guardian_approved_date");
                function syncMinorGuardianFields(){
                    var on = minorChk && minorChk.checked;
                    if(gName) gName.disabled = !on;
                    if(gMail) gMail.disabled = !on;
                    if(gPhone) gPhone.disabled = !on;
                    if(gApprBy) gApprBy.disabled = !on;
                    if(gApprDt) gApprDt.disabled = !on;
                }
                if(minorChk) minorChk.addEventListener("change", syncMinorGuardianFields);
                syncMinorGuardianFields();
            })();
            </script>';

            echo '</div>';
            return;
        }
    }

    // Handtera strikan av einstøkum umsóknum
    if (
        isset($_POST['lf_delete_request']) &&
        isset($_POST['lf_delete_id']) &&
        isset($_POST['lf_delete_nonce']) &&
        wp_verify_nonce(wp_unslash($_POST['lf_delete_nonce']), 'lf_delete_request')
    ) {
        $delete_id = intval($_POST['lf_delete_id']);
        if ($delete_id > 0) {
            $deleted = $wpdb->delete($table_name, ['id' => $delete_id], ['%d']);
            $msg = $deleted ? 'Umsókn nr. ' . $delete_id . ' er strikað.' : 'Eitt mistak hentist við at strika umsóknina.';
            set_transient('lf_bulk_notice_' . get_current_user_id(), $msg, 60);
        }
        wp_safe_redirect(admin_url('admin.php?page=lf-kappingarloyvi'));
        exit;
    }

    // Royn at finna tabellina
    $rows = [];
    $exists = $wpdb->get_var(
        $wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table_name
        )
    );

    if ($exists === $table_name) {
        // Hent upp til 500 umsóknir og filtrera/paginera í PHP
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 500");
    }

    echo '<div class="wrap">';
    echo '<h1>Kappingarloyviumsóknir</h1>';

    if (!empty($message)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    echo '<p>Her sært tú seinastu umsóknirnar, sum eru sendar gjøgnum kappingarloyviformið.</p>';

    // Filter / search controls
    $status_filter = isset($_GET['lf_status']) ? sanitize_text_field(wp_unslash($_GET['lf_status'])) : '';
    $club_filter   = isset($_GET['lf_club']) ? sanitize_text_field(wp_unslash($_GET['lf_club'])) : '';
    $minor_filter  = isset($_GET['lf_minor']) ? '1' : '';
    $search_term   = isset($_GET['lf_search']) ? sanitize_text_field(wp_unslash($_GET['lf_search'])) : '';

    $clubs_all = function_exists('lf_get_clubs') ? lf_get_clubs() : [];

    echo '<form method="get" class="lf-admin-filters" style="margin:1em 0 1.5em 0;padding:8px 10px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">';
    echo '<input type="hidden" name="page" value="lf-kappingarloyvi" />';

    // Status filter
    echo '<div>';
    echo '<label for="lf_status"><strong>Støða</strong><br />';
    echo '<select id="lf_status" name="lf_status">';
    $status_options = [
        ''            => 'Allar støður',
        'pending'     => 'Bíðar',
        'pending_fss' => 'Bíðar (FSS)',
        'approved'    => 'Góðkent',
        'denied'      => 'Ikki góðkent',
    ];
    foreach ($status_options as $val => $label) {
        $sel = ($status_filter === $val) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr($val) . '"' . $sel . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
    echo '</label>';
    echo '</div>';

    // Club filter
    echo '<div>';
    echo '<label for="lf_club_filter"><strong>Felag</strong><br />';
    echo '<select id="lf_club_filter" name="lf_club">';
    echo '<option value="">' . esc_html__('Øll feløg', 'lf') . '</option>';
    foreach ($clubs_all as $c) {
        $sel = ($club_filter === $c) ? ' selected="selected"' : '';
        echo '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($c) . '</option>';
    }
    echo '</select>';
    echo '</label>';
    echo '</div>';

    // Minor filter
    echo '<div>';
    echo '<label><strong>Minniálitari</strong><br />';
    echo '<input type="checkbox" name="lf_minor" value="1"' . ($minor_filter === '1' ? ' checked="checked"' : '') . ' /> ';
    echo esc_html__('Vís bara undir 18 ár', 'lf');
    echo '</label>';
    echo '</div>';

    // Search
    echo '<div style="min-width:220px;flex:1 1 220px;">';
    echo '<label for="lf_search"><strong>Leita</strong><br />';
    echo '<input type="search" id="lf_search" name="lf_search" value="' . esc_attr($search_term) . '" class="regular-text" placeholder="Navn ella teldupostur" />';
    echo '</label>';
    echo '</div>';

    echo '<div>';
    echo '<button type="submit" class="button button-primary">Filtrera</button> ';
    echo '<a href="' . esc_url(admin_url('admin.php?page=lf-kappingarloyvi')) . '" class="button">Nulstilla</a>';
    echo '</div>';

    echo '</form>';

    if (empty($rows)) {
        echo '<p>Ongar umsóknir funnar enn í ' . esc_html($table_name) . '.</p>';
        echo '</div>';
        return;
    }

    // Apply filters + pagination in PHP
    $filtered = [];
    foreach ($rows as $row) {
        $data = maybe_unserialize($row->data);
        if (!is_array($data)) {
            $data = [];
        }

        $name  = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $club  = $data['club'] ?? '';
        $is_minor = !empty($data['is_minor']);

        if ($status_filter !== '' && $row->status !== $status_filter) {
            continue;
        }
        if ($club_filter !== '' && $club !== $club_filter) {
            continue;
        }
        if ($minor_filter === '1' && !$is_minor) {
            continue;
        }
        if ($search_term !== '') {
            $haystack = strtolower($name . ' ' . $email);
            if (strpos($haystack, strtolower($search_term)) === false) {
                continue;
            }
        }

        $filtered[] = $row;
    }

    $per_page = 25;
    $paged = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $total_items = count($filtered);
    $total_pages = max(1, (int)ceil($total_items / $per_page));

    if ($paged > $total_pages) {
        $paged = $total_pages;
    }

    $offset = ($paged - 1) * $per_page;
    $paged_rows = array_slice($filtered, $offset, $per_page);

    if (empty($paged_rows)) {
        echo '<p>Ongar umsóknir samsvara teimum valdnu filturunum.</p>';
        echo '</div>';
        return;
    }

    echo '<form method="post" class="lf-bulk-requests-form" action="' . esc_url(admin_url('admin.php?page=lf-kappingarloyvi')) . '">';
    wp_nonce_field('lf_bulk_requests', 'lf_bulk_nonce');
    echo '<input type="hidden" name="lf_keep_status" value="' . esc_attr($status_filter) . '" />';
    echo '<input type="hidden" name="lf_keep_club" value="' . esc_attr($club_filter) . '" />';
    echo '<input type="hidden" name="lf_keep_search" value="' . esc_attr($search_term) . '" />';
    echo '<input type="hidden" name="lf_keep_paged" value="' . intval($paged) . '" />';
    if ($minor_filter === '1') {
        echo '<input type="hidden" name="lf_keep_minor" value="1" />';
    }

    echo '<div class="tablenav top">';
    echo '<div class="alignleft actions bulkactions" style="padding-bottom:8px;">';
    echo '<label for="lf-bulk-action" class="screen-reader-text">Margfeldis-handling</label>';
    echo '<select name="lf_bulk_action" id="lf-bulk-action">';
    echo '<option value="">Vel handling…</option>';
    echo '<option value="reconsent">&#x21BA; Send nýggjar-skilmálar-beiðni (re-consent)</option>';
    echo '<option value="regenerate_pdf">Endurgera PDF</option>';
    echo '<option value="resend_pdf">Send PDF aftur (FSS, felag, íðkari og verji)</option>';
    echo '<option value="resend_link_club">Send góðkenningarleinkju til felags</option>';
    echo '<option value="resend_link_guardian">Send góðkenningarleinkju til verja</option>';
    echo '<option value="resend_link_fss">Send góðkenningarleinkju til FSS</option>';
    echo '<option value="clear_club_approval">Strika «góðkent av felagi»</option>';
    echo '<option value="delete">Strika umsóknir</option>';
    echo '<option value="custom_email">&#x2709; Send tilpassað teldupost…</option>';
    echo '</select> ';
    echo '<button type="submit" name="lf_bulk_submit" value="1" class="button action" id="lf-bulk-ger-btn">Ger</button>';
    echo '</div>';
    echo '</div>';

    // Compose panel – shown when custom_email is selected
    echo '<div id="lf-custom-email-panel" style="display:none;margin:0 0 16px 0;padding:16px 18px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;max-width:740px;">';
    echo '<p style="margin:0 0 10px;font-weight:600;">&#x2709; Tilpassað teldupost</p>';
    echo '<p style="margin:0 0 8px;"><strong>Send til:</strong></p>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" class="lf-ce-rcpt" name="lf_custom_send_to[]" value="club" checked> Felag (formans-teldupostur)</label>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" class="lf-ce-rcpt" name="lf_custom_send_to[]" value="athlete" checked> Íðkari</label>';
    echo '<label style="display:block;margin:4px 0;"><input type="checkbox" class="lf-ce-rcpt" name="lf_custom_send_to[]" value="guardian"> Verji (bert um undir 18 ár og verji er skrásettur)</label>';
    echo '<label style="display:block;margin:4px 0 10px;"><input type="checkbox" class="lf-ce-rcpt" name="lf_custom_send_to[]" value="fss"> FSS</label>';
    echo '<label style="display:block;margin:4px 0 14px;"><input type="checkbox" name="lf_custom_regen_pdf" value="1"> Endurgera PDF áðrenn sending <small style="color:#666;">(lekari, sleppur nú um PDF longu er til)</small></label>';
    echo '<p style="margin:0 0 4px;"><label><strong>Evni</strong><br>';
    echo '<input type="text" name="lf_custom_subject" id="lf-ce-subject" class="regular-text" style="width:100%;max-width:620px;" value="Kappingarloyvi" /></label></p>';
    echo '<p style="margin:0 0 12px;"><label><strong>Tekst</strong><br>';
    echo '<textarea name="lf_custom_body" id="lf-ce-body" rows="9" style="width:100%;max-width:620px;font-family:monospace;font-size:13px;"></textarea></label></p>';
    echo '<p style="display:flex;gap:10px;flex-wrap:wrap;margin:0;">';
    echo '<button type="submit" name="lf_bulk_submit" value="1" class="button button-primary" id="lf-ce-send-btn">Send nú</button>';
    echo '<button type="button" class="button" id="lf-ce-cancel-btn">Annulla</button>';
    echo '</p>';
    echo '</div>';

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th scope="col" id="lf-cb-column" class="manage-column column-cb check-column"><label class="screen-reader-text" for="lf-bulk-select-all">Vel alla</label><input id="lf-bulk-select-all" type="checkbox" /></th>';
    echo '<th scope="col">ID</th>';
    echo '<th>Dagur</th>';
    echo '<th>Navn</th>';
    echo '<th>Felag</th>';
    echo '<th>Støða</th>';
    echo '<th>Nokt</th>';
    echo '<th>Minniálitari</th>';
    echo '<th>Góðkent av felagi</th>';
    echo '<th>Góðkent av verja</th>';
    echo '<th>Góðkent av FSS</th>';
    echo '<th>PDF</th>';
    echo '<th>Rætta</th>';
    echo '<th>Strika</th>';
    echo '</tr></thead><tbody>';

    $lf_status_labels = [
        'approved'    => 'Góðkent',
        'pending'     => 'Bíðar',
        'pending_fss' => 'Bíðar (FSS)',
        'denied'      => 'Ikki góðkent',
    ];

    foreach ($paged_rows as $row) {
        $data = maybe_unserialize($row->data);
        if (!is_array($data)) {
            $data = [];
        }

        $name                 = $data['name'] ?? '';
        $club                 = $data['club'] ?? '';
        $is_minor             = !empty($data['is_minor']);
        $approved_by          = $data['approved_by'] ?? '';
        $guardian_approved_by = $data['guardian_approved_by'] ?? '';
        $fss_approved_by      = $data['fss_approved_by'] ?? '';
        $denied_role   = $data['denied_role'] ?? '';
        $denied_by     = $data['denied_by'] ?? '';
        $denied_reason = $data['denied_reason'] ?? '';

        $status_label = $lf_status_labels[ $row->status ] ?? $row->status;

        $minor_label = $is_minor ? 'Ja' : 'Nei';

        // Build PDF URL
        $pdf_url = '';
        if (!empty($row->pdf_path) && is_string($row->pdf_path)) {
            $upload_dir = wp_upload_dir();
            $basedir = $upload_dir['basedir'] ?? '';
            $baseurl = $upload_dir['baseurl'] ?? '';
            if ($basedir !== '' && $baseurl !== '' && strpos($row->pdf_path, $basedir) === 0) {
                $rel = ltrim(substr($row->pdf_path, strlen($basedir)), '/');
                $pdf_url = trailingslashit($baseurl) . $rel;
            }
        }

        echo '<tr>';
        echo '<th scope="row" class="check-column"><input type="checkbox" name="lf_bulk_ids[]" class="lf-bulk-row-cb" value="' . intval($row->id) . '" /></th>';
        echo '<td>' . intval($row->id) . '</td>';
        echo '<td>' . esc_html($row->created_at) . '</td>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        $rc_badge = '';
        if (!empty($row->reconsent_status) && $row->reconsent_status === 'pending') {
            $rc_badge = ' <span title="Re-consent bíðar" style="display:inline-block;background:#f9a825;color:#fff;border-radius:3px;padding:1px 5px;font-size:10px;font-weight:700;vertical-align:middle;">RC</span>';
        }
        echo '<td>' . esc_html($status_label) . $rc_badge . '</td>';
        echo '<td>';
        if ($row->status === 'denied') {
            $denied_header = trim($denied_role . ($denied_by ? ' (' . $denied_by . ')' : ''));
            $has_denial_detail = ($denied_header !== '' || $denied_reason !== '');
            if ($has_denial_detail) {
                $denial_payload = wp_json_encode(
                    [
                        'header' => $denied_header,
                        'reason' => $denied_reason,
                    ],
                    JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE
                );
                echo '<button type="button" class="button-link lf-denial-comment-trigger" data-denial="' . esc_attr($denial_payload) . '" style="color:#2271b1;padding:0;border:0;background:none;cursor:pointer;font:inherit;text-decoration:underline;">'
                    . esc_html('Viðmerking')
                    . '</button>';
            } else {
                echo '&ndash;';
            }
        }
        echo '</td>';
        echo '<td>' . esc_html($minor_label) . '</td>';
        echo '<td>' . esc_html($approved_by) . '</td>';
        echo '<td>' . esc_html($guardian_approved_by) . '</td>';
        echo '<td>' . esc_html($fss_approved_by) . '</td>';
        if (!empty($pdf_url)) {
            echo '<td><a href="' . esc_url($pdf_url) . '" target="_blank" rel="noopener">Tak niður</a></td>';
        } else {
            echo '<td>-</td>';
        }

        // Rætta
        echo '<td>';
        $edit_url = admin_url('admin.php?page=lf-kappingarloyvi&edit_id=' . intval($row->id));
        echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Rætta</a>';
        echo '</td>';

        echo '<td>';
        $del_confirm_js = esc_js('Ert tú viss(ur) í, at tú vilt strika hesa umsóknina? Hetta kann ikki angraðast.');
        echo '<button type="submit" form="lf-single-delete-form" class="button button-small button-link-delete" onclick="if(!confirm(\'' . $del_confirm_js . '\'))return false;document.getElementById(\'lf-single-delete-id\').value=\'' . intval($row->id) . '\';return true;">Strika</button>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '</form>';

    echo '<form id="lf-single-delete-form" method="post" action="' . esc_url(admin_url('admin.php?page=lf-kappingarloyvi')) . '" style="display:none;">';
    echo '<input type="hidden" name="lf_delete_request" value="1" />';
    echo '<input type="hidden" name="lf_delete_id" id="lf-single-delete-id" value="" />';
    wp_nonce_field('lf_delete_request', 'lf_delete_nonce');
    echo '</form>';

    echo '<script>
    (function(){
        var bulkForm = document.querySelector(".lf-bulk-requests-form");
        var actionSel = document.getElementById("lf-bulk-action");
        var gerBtn    = document.getElementById("lf-bulk-ger-btn");
        var composePanel = document.getElementById("lf-custom-email-panel");
        var cancelBtn = document.getElementById("lf-ce-cancel-btn");

        function toggleCompose() {
            var isCustom = actionSel && actionSel.value === "custom_email";
            if (composePanel) composePanel.style.display = isCustom ? "block" : "none";
            if (gerBtn)       gerBtn.style.display       = isCustom ? "none"  : "";
        }
        if (actionSel) actionSel.addEventListener("change", toggleCompose);
        if (cancelBtn) cancelBtn.addEventListener("click", function(){
            if (actionSel) actionSel.value = "";
            toggleCompose();
        });

        if(bulkForm){
            bulkForm.addEventListener("submit", function(e){
                var action = actionSel ? actionSel.value : "";
                var boxes = bulkForm.querySelectorAll(".lf-bulk-row-cb:checked");

                if(action === "custom_email"){
                    var subj = document.getElementById("lf-ce-subject");
                    var body = document.getElementById("lf-ce-body");
                    var rcpts = bulkForm.querySelectorAll(".lf-ce-rcpt:checked");
                    if(!subj || !subj.value.trim()){ e.preventDefault(); alert("Skriva eitt evni."); return; }
                    if(!body || !body.value.trim()){ e.preventDefault(); alert("Skriva eitt tekst."); return; }
                    if(!rcpts.length){ e.preventDefault(); alert("Vel minst einn mótakara."); return; }
                    if(!boxes.length){ e.preventDefault(); alert("Vel minst eina umsókn."); return; }
                    return;
                }

                if(!action || !boxes.length){
                    e.preventDefault();
                    alert("Vel handling og minst ein røð.");
                    return;
                }
                if(action==="delete"){
                    if(!confirm("Ert tú viss(ur)? Valdu umsóknir verða strikaðar og kunnu ikki endurgevnast.")){ e.preventDefault(); }
                    return;
                }
                if(action==="clear_club_approval"){
                    if(!confirm("Strika «góðkent av felagi» hjá øllum valdum røðum? Har støðan er «Góðkent» ella «Bíðar (FSS)», verður hon sett til «Bíðar».")){ e.preventDefault(); }
                    return;
                }
                if(action==="reconsent"){
                    if(!confirm("Senda nýggjar-skilmálar-beiðni til valdu umsóknirnar?\n\nHetta mun:\n• Strika gomlu PDF\n• Senda teldupost til íðkara, felag, verja og FSS við einstøkum leinkjum\n• Seta støðuna aftur til «Bíðar»\n\nAðeins velja umsóknir har skilmálarnir eru broyttir.")){ e.preventDefault(); }
                    return;
                }
                if(action==="regenerate_pdf"){
                    if(!confirm("Endurgera PDF fyri øll vald umsóknir? Galdu PDF-fílur verða goymdar; nýggjar verða gjørðar.")){ e.preventDefault(); }
                    return;
                }
                if(action==="resend_pdf"){
                    if(!confirm("Senda PDF aftur til tøkar móttakarar (FSS, felag, íðkari og verji) hjá øllum valdum umsóknunum?")){ e.preventDefault(); }
                    return;
                }
                if(action==="resend_link_club"){
                    if(!confirm("Senda góðkenningarleinkju til felags (formans-teldupost) fyri øll vald umsóknir?")){ e.preventDefault(); }
                    return;
                }
                if(action==="resend_link_guardian"){
                    if(!confirm("Senda góðkenningarleinkju til verja fyri øll vald umsóknir sum krevja verju?")){ e.preventDefault(); }
                    return;
                }
                if(action==="resend_link_fss"){
                    if(!confirm("Senda ella áfrísa FSS til endaligi góðkenning hjá øllum valdum umsóknunum? Støðan kann verða «Bíðar (FSS)».")){ e.preventDefault(); }
                    return;
                }
            });
            var allCb = document.getElementById("lf-bulk-select-all");
            if(allCb){
                allCb.addEventListener("change", function(){
                    bulkForm.querySelectorAll(".lf-bulk-row-cb").forEach(function(cb){ cb.checked = allCb.checked; });
                });
            }
        }
    })();
    </script>';

    echo '<dialog id="lf-denial-comment-dialog" style="border:1px solid #c3c4c7;border-radius:4px;padding:0;max-width:min(560px,92vw);box-shadow:0 5px 15px rgba(0,0,0,.2);">';
    echo '<div style="padding:16px 18px;">';
    echo '<div id="lf-denial-comment-meta" style="margin:0 0 12px;color:#646970;"></div>';
    echo '<div id="lf-denial-comment-text" style="margin:0;white-space:pre-wrap;line-height:1.45;"></div>';
    echo '</div>';
    echo '<form method="dialog" style="padding:12px 18px;background:#f6f7f7;border-top:1px solid #c3c4c7;margin:0;display:flex;justify-content:flex-end;"><button type="submit" class="button button-primary">Lukka</button></form>';
    echo '</dialog>';
    echo '<script>
    (function(){
        var dlg = document.getElementById("lf-denial-comment-dialog");
        var meta = document.getElementById("lf-denial-comment-meta");
        var txt = document.getElementById("lf-denial-comment-text");
        if (!dlg || !meta || !txt) return;
        document.querySelectorAll(".lf-denial-comment-trigger").forEach(function(btn){
            btn.addEventListener("click", function(){
                var raw = btn.getAttribute("data-denial");
                if (!raw) return;
                var d;
                try { d = JSON.parse(raw); } catch (e) { return; }
                meta.textContent = d.header || "";
                meta.style.display = (d.header && String(d.header).trim() !== "") ? "block" : "none";
                txt.textContent = d.reason || "";
                if (typeof dlg.showModal === "function") dlg.showModal();
            });
        });
    })();
    </script>';

    // Simple pagination
    $base_args = [
        'page'      => 'lf-kappingarloyvi',
        'lf_status' => $status_filter,
        'lf_club'   => $club_filter,
        'lf_minor'  => $minor_filter === '1' ? '1' : '',
        'lf_search' => $search_term,
    ];

    echo '<div class="tablenav"><div class="tablenav-pages" style="margin-top:8px;">';
    echo '<span class="displaying-num">' . intval($total_items) . ' umsóknir funnar</span> ';
    echo '<span class="pagination-links">';

    if ($paged > 1) {
        $prev_url = add_query_arg(array_merge($base_args, ['paged' => $paged - 1]), admin_url('admin.php'));
        echo '<a class="prev-page button" href="' . esc_url($prev_url) . '">&laquo; Fyrra síða</a> ';
    } else {
        echo '<span class="tablenav-pages-navspan button disabled">&laquo; Fyrra síða</span> ';
    }

    echo '<span class="paging-input">' . intval($paged) . ' / <span class="total-pages">' . intval($total_pages) . '</span></span>';

    if ($paged < $total_pages) {
        $next_url = add_query_arg(array_merge($base_args, ['paged' => $paged + 1]), admin_url('admin.php'));
        echo ' <a class="next-page button" href="' . esc_url($next_url) . '">Næsta síða &raquo;</a>';
    } else {
        echo ' <span class="tablenav-pages-navspan button disabled">Næsta síða &raquo;</span>';
    }

    echo '</span></div></div>';

    echo '<p><small>Vísir upp til 500 seinastu umsóknirnar úr ' . esc_html($table_name) . ' (25 pr. síðu).</small></p>';
    echo '</div>';
}

