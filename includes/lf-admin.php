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
        // Hent upp til 500 umsóknir og filtrera/paginera í PHP
        $rows = $wpdb->get_results("SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT 500");
    }

    echo '<div class="wrap">';
    echo '<h1>Lyftiloyvisumsóknir</h1>';

    if (!empty($message)) {
        echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
    }

    echo '<p>Her sært tú seinastu umsóknirnar, sum eru sendar gjøgnum lyftiloyvisformið.</p>';

    // Filter / search controls
    $status_filter = isset($_GET['lf_status']) ? sanitize_text_field(wp_unslash($_GET['lf_status'])) : '';
    $club_filter   = isset($_GET['lf_club']) ? sanitize_text_field(wp_unslash($_GET['lf_club'])) : '';
    $minor_filter  = isset($_GET['lf_minor']) ? '1' : '';
    $search_term   = isset($_GET['lf_search']) ? sanitize_text_field(wp_unslash($_GET['lf_search'])) : '';

    $clubs_all = function_exists('lf_get_clubs') ? lf_get_clubs() : [];

    echo '<form method="get" class="lf-admin-filters" style="margin:1em 0 1.5em 0;padding:8px 10px;background:#fff;border:1px solid #ccd0d4;border-radius:6px;display:flex;flex-wrap:wrap;gap:10px;align-items:flex-end;">';
    echo '<input type="hidden" name="page" value="lf-lyftiloyvi" />';

    // Status filter
    echo '<div>';
    echo '<label for="lf_status"><strong>Støða</strong><br />';
    echo '<select id="lf_status" name="lf_status">';
    $status_options = [
        ''            => 'Allar støður',
        'pending'     => 'Bíðar',
        'pending_fss' => 'Bíðar (FSS)',
        'approved'    => 'Góðkent',
        'denied'      => 'Noktað',
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
    echo '<a href="' . esc_url(admin_url('admin.php?page=lf-lyftiloyvi')) . '" class="button">Nulstilla</a>';
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

    // Simple pagination
    $base_args = [
        'page'      => 'lf-lyftiloyvi',
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

