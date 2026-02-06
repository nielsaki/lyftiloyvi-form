<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin-yvirlit yvir lyftiloyvisumsóknir.
 */
function lf_register_admin_menu() {
    add_menu_page(
        'Lyftiloyvi',
        'Lyftiloyvi',
        'manage_options',
        'lf-lyftiloyvi',
        'lf_render_admin_page',
        'dashicons-forms',
        26
    );
}
add_action('admin_menu', 'lf_register_admin_menu');

/**
 * Render admin-síðu við yvirliti yvir seinastu umsóknirnar.
 */
function lf_render_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Tú hevur ikki rættindi at síggja hesa síðuna.', 'lf'));
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $message = '';

    // Edit view
    if (isset($_GET['edit_id'])) {
        $edit_id = intval($_GET['edit_id']);
        if ($edit_id > 0) {
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $edit_id));
            if (!$row) {
                echo '<div class="wrap"><h1>Lyftiloyvi</h1><p>Umsókn fannst ikki.</p></div>';
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

                // minor/guardian fields
                $data['is_minor']        = !empty($_POST['is_minor']) ? true : false;
                $data['guardian_name']   = sanitize_text_field($_POST['guardian_name'] ?? '');
                $data['guardian_email']  = sanitize_email($_POST['guardian_email'] ?? '');
                $data['guardian_phone']  = sanitize_text_field($_POST['guardian_phone'] ?? '');

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

            // Reload freshest data
            $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table_name} WHERE id = %d LIMIT 1", $edit_id));
            $data = maybe_unserialize($row->data);
            if (!is_array($data)) $data = [];

            $clubs = lf_get_clubs();

            echo '<div class="wrap">';
            echo '<h1>Rætta umsókn #' . intval($row->id) . '</h1>';
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=lf-lyftiloyvi')) . '">← Aftur til yvirlit</a></p>';
            if (!empty($message)) {
                echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
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
            $gname = esc_attr($data['guardian_name'] ?? '');
            $gemail = esc_attr($data['guardian_email'] ?? '');
            $gphone = esc_attr($data['guardian_phone'] ?? '');

            echo '<h2>Upplýsingar</h2>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="lf_name">Navn</label></th><td><input id="lf_name" name="name" type="text" class="regular-text" value="' . $name . '" required></td></tr>';
            echo '<tr><th><label for="lf_birthdate">Føðingardagur</label></th><td><input id="lf_birthdate" name="birthdate" type="text" class="regular-text" value="' . $birthdate . '" placeholder="dd.mm.áááá"></td></tr>';
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
            echo '<tr><th><label for="lf_gname">Verji navn</label></th><td><input id="lf_gname" name="guardian_name" type="text" class="regular-text" value="' . $gname . '"></td></tr>';
            echo '<tr><th><label for="lf_gemail">Verji teldupostur</label></th><td><input id="lf_gemail" name="guardian_email" type="email" class="regular-text" value="' . $gemail . '"></td></tr>';
            echo '<tr><th><label for="lf_gphone">Verji telefonnummar</label></th><td><input id="lf_gphone" name="guardian_phone" type="text" class="regular-text" value="' . $gphone . '"></td></tr>';

            echo '</tbody></table>';

            echo '<p><button type="submit" name="lf_admin_save" value="1" class="button button-primary">Goym broytingar</button></p>';

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
            })();
            </script>';

            echo '</div>';
            return;
        }
    }

    // Handtera strikan av einstøkum umsóknum (einki nonce-check fyri at gera tað einfaldari)
    if (
        isset($_POST['lf_delete_request']) &&
        isset($_POST['lf_delete_id'])
    ) {
        $delete_id = intval($_POST['lf_delete_id']);
        if ($delete_id > 0) {
            $deleted = $wpdb->delete($table_name, ['id' => $delete_id], ['%d']);
            if ($deleted) {
                $message = 'Umsókn nr. ' . $delete_id . ' er strikað.';
            } else {
                $message = 'Eitt mistak hentist við at strika umsóknina.';
            }
        }
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
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 100");
    }

    echo '<div class="wrap">';
    echo '<h1>Lyftiloyvisumsóknir</h1>';

    if (!empty($message)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    echo '<p>Her sært tú seinastu umsóknirnar, sum eru sendar gjøgnum lyftiloyvisformið.</p>';

    if (empty($rows)) {
        echo '<p>Ongar umsóknir funnar enn í ' . esc_html($table_name) . '.</p>';
        echo '</div>';
        return;
    }

    echo '<table class="widefat fixed striped">';
    echo '<thead><tr>';
    echo '<th>ID</th>';
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

    foreach ($rows as $row) {
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

        $status_label = $row->status === 'approved'
            ? 'Góðkent'
            : ($row->status === 'pending' ? 'Bíðar' : $row->status);

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
        echo '<td>' . intval($row->id) . '</td>';
        echo '<td>' . esc_html($row->created_at) . '</td>';
        echo '<td>' . esc_html($name) . '</td>';
        echo '<td>' . esc_html($club) . '</td>';
        echo '<td>' . esc_html($status_label) . '</td>';
        $denied_label = '';
        if ($row->status === 'denied') {
            $denied_label = trim($denied_role . ($denied_by ? ' (' . $denied_by . ')' : ''));
            if (!empty($denied_reason)) {
                $denied_label .= ' – ' . $denied_reason;
            }
        }
        echo '<td>' . esc_html($denied_label) . '</td>';
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
        $edit_url = admin_url('admin.php?page=lf-lyftiloyvi&edit_id=' . intval($row->id));
        echo '<a class="button button-small" href="' . esc_url($edit_url) . '">Rætta</a>';
        echo '</td>';

        // Lítill formur til at strika hesa røðina
        echo '<td>';
        echo '<form method="post" style="display:inline;" onsubmit="return confirm(\'Ert tú viss(ur) í, at tú vilt strika hesa umsóknina? Hetta kann ikki angraðast.\');">';
        echo '<input type="hidden" name="lf_delete_request" value="1" />';
        echo '<input type="hidden" name="lf_delete_id" value="' . intval($row->id) . '" />';
        echo '<button type="submit" class="button button-small button-link-delete">Strika</button>';
        echo '</form>';
        echo '</td>';

        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<p><small>Vísir upp til 100 seinastu umsóknirnar úr ' . esc_html($table_name) . '.</small></p>';
    echo '</div>';
}

