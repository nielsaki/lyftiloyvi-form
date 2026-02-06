<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Render a readable summary of the application data (instead of print_r array).
 */
function lf_build_application_summary_html($data) {
    $is_minor = !empty($data['is_minor']);

    $rows_app = [
        'Navn' => ($data['name'] ?? ''),
        'Føðingardagur' => ($data['birthdate'] ?? ''),
        'Teldupostur' => ($data['email'] ?? ''),
        'Telefonnummar' => ($data['phone'] ?? ''),
        'Bústaður' => ($data['address'] ?? ''),
        'Býur/bygd' => ($data['city'] ?? ''),
        'Felag' => ($data['club'] ?? ''),
        'Dagur' => ($data['date'] ?? ''),
    ];

    $html  = '<div style="max-width:560px;margin:0.6rem auto 0;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:13px;">';
    $html .= '<h3 style="margin:0 0 0.5rem;">Umsókn</h3>';
    $html .= '<table style="width:100%;border-collapse:collapse;background:#fff;border:1px solid #ddd;border-radius:6px;overflow:hidden;">';

    foreach ($rows_app as $k => $v) {
        $html .= '<tr>';
        $html .= '<th style="text-align:left;padding:4px 6px;border-bottom:1px solid #eee;width:40%;background:#fafafa;">' . esc_html($k) . '</th>';
        $html .= '<td style="padding:4px 6px;border-bottom:1px solid #eee;">' . esc_html($v) . '</td>';
        $html .= '</tr>';
    }

    if ($is_minor) {
        $html .= '<tr><th style="text-align:left;padding:4px 6px;border-bottom:1px solid #eee;background:#fafafa;">Verji navn</th><td style="padding:4px 6px;border-bottom:1px solid #eee;">' . esc_html($data['guardian_name'] ?? '') . '</td></tr>';
        $html .= '<tr><th style="text-align:left;padding:4px 6px;border-bottom:1px solid #eee;background:#fafafa;">Verji teldupostur</th><td style="padding:4px 6px;border-bottom:1px solid #eee;">' . esc_html($data['guardian_email'] ?? '') . '</td></tr>';
        $html .= '<tr><th style="text-align:left;padding:4px 6px;background:#fafafa;">Verji telefonnummar</th><td style="padding:4px 6px;">' . esc_html($data['guardian_phone'] ?? '') . '</td></tr>';
    }

    $html .= '</table>';
    $html .= '</div>';

    return $html;
}

function lf_request_fss_approval($row, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $name = $data['name'] ?? '';
    $club = $data['club'] ?? '';
    $email = $data['email'] ?? '';

    $fss_token = !empty($row->fss_token) ? $row->fss_token : wp_generate_password(32, false, false);
    if (empty($row->fss_token)) {
        $wpdb->update($table_name, ['fss_token' => $fss_token], ['id' => $row->id], ['%s'], ['%d']);
    }

    $subject_parts = [];
    if ($name) $subject_parts[] = $name;
    if ($club) $subject_parts[] = '(' . $club . ')';
    $subject = 'Lyftiloyvi: ' . trim(implode(' ', $subject_parts));

    $link = add_query_arg('lf_fss_approve', rawurlencode($fss_token), get_site_url());

    $body  = "Ein umsókn um lyftiloyvi er nú klár til endaliga góðkenning frá FSS.\n\n";
    $body .= "Navn: {$name}\nFelag: {$club}\nTeldupostur: {$email}\n\n";
    $body .= "Góðkenn her:\n{$link}\n";

    $fss_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';

    // valfrit: marker at den venter på FSS
    $wpdb->update($table_name, ['status' => 'pending_fss'], ['id' => $row->id], ['%s'], ['%d']);

    return wp_mail($fss_email, 'Endalig góðkenning krevst (FSS): ' . $subject, $body);
}

/**
 * Send a fresh approval link to the club (chairman/board).
 */
function lf_admin_send_club_approval_link($row, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $name  = $data['name'] ?? '';
    $club  = $data['club'] ?? '';
    $email = $data['email'] ?? '';

    // Ensure we have a token
    $token = !empty($row->token) ? $row->token : wp_generate_password(32, false, false);
    if (empty($row->token)) {
        $wpdb->update(
            $table_name,
            ['token' => $token],
            ['id' => $row->id],
            ['%s'],
            ['%d']
        );
    }

    // Build link
    $approval_link = add_query_arg(
        'lf_approve',
        rawurlencode($token),
        get_site_url()
    );

    // Subject/body – reuse helper if available
    if (function_exists('lf_admin_build_subject')) {
        $subject = lf_admin_build_subject($data, 'Góðkenning krevst (felag)');
    } else {
        $subject_parts = [];
        if ($name) $subject_parts[] = $name;
        if ($club) $subject_parts[] = '(' . $club . ')';
        $subject_suffix = trim(implode(' ', $subject_parts));
        $subject = $subject_suffix === '' ? 'Góðkenning krevst (felag)' : 'Góðkenning krevst (felag): ' . $subject_suffix;
    }

    $body  = "Ein umsókn um lyftiloyvi krevur góðkenning frá felagnum.\n\n";
    $body .= "Navn: {$name}\n";
    $body .= "Felag: {$club}\n";
    $body .= "Teldupostur hjá íðkara: {$email}\n\n";
    $body .= "Fyri at síggja umsóknina og góðkenna hana, klikk á hesa leinkju:\n";
    $body .= $approval_link . "\n\n";
    $body .= "Hetta er ein nýggj/uppaftur send leinkja send frá admin.\n";

    $club_chair_emails = lf_get_club_chair_emails();
    $chair_recipient = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : (function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo');

    $headers = [];
    if (!empty($email) && is_email($email)) {
        $headers[] = 'Reply-To: ' . $email;
    }

    return wp_mail($chair_recipient, $subject, $body, $headers);
}

/**
 * Send a fresh approval link to the guardian.
 */
function lf_admin_send_guardian_approval_link($row, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $is_minor       = !empty($data['is_minor']);
    $name           = $data['name'] ?? '';
    $guardian_email = $data['guardian_email'] ?? '';

    if (!$is_minor || empty($guardian_email) || !is_email($guardian_email)) {
        return false;
    }

    // Ensure we have a guardian token
    $guardian_token = !empty($row->guardian_token) ? $row->guardian_token : wp_generate_password(32, false, false);
    if (empty($row->guardian_token)) {
        $wpdb->update(
            $table_name,
            ['guardian_token' => $guardian_token],
            ['id' => $row->id],
            ['%s'],
            ['%d']
        );
    }

    $guardian_approval_link = add_query_arg(
        'lf_guardian_approve',
        rawurlencode($guardian_token),
        get_site_url()
    );

    if (function_exists('lf_admin_build_subject')) {
        $subject = lf_admin_build_subject($data, 'Góðkenning krevst (verji)');
    } else {
        $subject = 'Góðkenning krevst (verji)';
    }

    $body  = "Tú ert skrásettur sum verji hjá {$name}.\n\n";
    $body .= "Ein umsókn um lyftiloyvi krevur tína góðkenning sum verji.\n\n";
    $body .= "Fyri at lesa váttanina og góðkenna hana, klikk á hesa leinkju:\n";
    $body .= $guardian_approval_link . "\n\n";
    $body .= "Hetta er ein nýggj/uppaftur send leinkja send frá admin.\n";

    return wp_mail($guardian_email, $subject, $body);
}

/**
 * Finalize approval: generate final PDF, send to FSS, send receipts, update DB.
 */
function lf_maybe_finalize($row, $data) {
    if (!empty($row->status) && $row->status === 'denied') {
        return false;
    }

    $is_minor = !empty($data['is_minor']);
    $club_ok = !empty($data['approved_by']);
    $guardian_ok = !$is_minor || !empty($data['guardian_approved_by']);
    $fss_ok = !empty($data['fss_approved_by']);

    if ($club_ok && $guardian_ok && $fss_ok) {
        return lf_finalize_approval($row, $data);
    }
    return false;
}

function lf_send_denial_notifications($data, $role_label, $denied_by, $reason) {
    $athlete_email  = $data['email'] ?? '';
    $guardian_email = $data['guardian_email'] ?? '';
    $club           = $data['club'] ?? '';
    $name           = $data['name'] ?? '';
    $birthdate      = $data['birthdate'] ?? '';

    $club_chair_emails = lf_get_club_chair_emails();
    $club_email = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : '';

    $subject_parts = [];
    if ($name) $subject_parts[] = $name;
    if ($club) $subject_parts[] = '(' . $club . ')';
    $subject = 'Lyftiloyvi NOKTAÐ: ' . trim(implode(' ', $subject_parts));

    $body  = "Ein umsókn um lyftiloyvi er NOKTAÐ.\n\n";
    $body .= "Umsókn: {$name}\n";
    $body .= "Felag: {$club}\n";
    $body .= "Føðingardagur: {$birthdate}\n\n";
    $body .= "Noktað av: {$role_label}" . ($denied_by ? " ({$denied_by})" : "") . "\n";
    $body .= "Viðmerking/orsøk:\n{$reason}\n\n";
    $body .= "Sent frá: " . get_site_url() . "\n";

    $fss_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';

    $recipients = [$fss_email];
    if ($club_email) $recipients[] = $club_email;
    if ($athlete_email && is_email($athlete_email)) $recipients[] = $athlete_email;
    if ($guardian_email && is_email($guardian_email)) $recipients[] = $guardian_email;

    $recipients = array_values(array_unique($recipients));

    foreach ($recipients as $to) {
        wp_mail($to, $subject, $body);
    }
}

function lf_mark_denied($row, $data, $role_label, $denied_by, $reason) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $data['denied'] = true;
    $data['denied_role'] = $role_label;
    $data['denied_by'] = $denied_by;
    $data['denied_reason'] = $reason;
    $data['denied_at'] = current_time('mysql', 1);

    $wpdb->update(
        $table_name,
        [
            'status' => 'denied',
            'data'   => maybe_serialize($data),
        ],
        ['id' => $row->id],
        ['%s', '%s'],
        ['%d']
    );

    lf_send_denial_notifications($data, $role_label, $denied_by, $reason);
    return true;
}

function lf_finalize_approval($row, $data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    // Safety: only finalize when all required approvals exist
    $is_minor_check = !empty($data['is_minor']);
    $approved_by_check = $data['approved_by'] ?? '';
    $guardian_approved_by_check = $data['guardian_approved_by'] ?? '';
    $fss_approved_by_check = $data['fss_approved_by'] ?? '';

    if (empty($approved_by_check) || empty($fss_approved_by_check) || ($is_minor_check && empty($guardian_approved_by_check))) {
        return false;
    }

    // Extract stored fields
    $name           = $data['name'] ?? '';
    $birthdate      = $data['birthdate'] ?? '';
    $address        = $data['address'] ?? '';
    $city           = $data['city'] ?? '';
    $phone          = $data['phone'] ?? '';
    $email          = $data['email'] ?? '';
    $club           = $data['club'] ?? '';
    $date           = $data['date'] ?? '';
    $is_minor       = !empty($data['is_minor']);
    $guardian_name  = $data['guardian_name'] ?? '';
    $guardian_email = $data['guardian_email'] ?? '';
    $guardian_phone = $data['guardian_phone'] ?? '';

    // Ger PDF av nýggju datunum
    $pdf_path = lf_generate_pdf($data);

    $attachments = [];
    if (!empty($pdf_path) && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    // Build email subject/body (same style as in form submit)
    $subject_parts = [];
    if (!empty($name)) {
        $subject_parts[] = $name;
    }
    if (!empty($club)) {
        $subject_parts[] = '(' . $club . ')';
    }
    $subject_suffix = trim(implode(' ', $subject_parts));
    if ($subject_suffix === '') {
        $subject = 'Lyftiloyvi: nýtt skjal';
    } else {
        $subject = 'Lyftiloyvi: ' . $subject_suffix;
    }

    $body  = "Nýtt lyftiloyvi er móttiki og nú fullgóðkent:\n\n";
    $body .= "Fulla navn á íðkara: {$name}\n";
    $body .= "Føðingardagur: {$birthdate}\n";
    $body .= "Bústaður hjá íðkara: {$address}\n";
    $body .= "Býur/bygd: {$city}\n";
    $body .= "Telefonnummar hjá íðkara: {$phone}\n";
    $body .= "Felag: {$club}\n";
    $body .= "Dagur (dags dato): {$date}\n";

    if ($is_minor) {
        $body .= "\nÍðkari er undir 18 ár. Upplýsingar um verja:\n";
        $body .= "Navn á verja: {$guardian_name}\n";
        $body .= "Teldupostur hjá verja: {$guardian_email}\n";
        $body .= "Telefonnummar hjá verja: {$guardian_phone}\n";
    }

    $body .= "\nLyftiloyvisváttan:\n";
    $body .= "Lyftari váttar, at hann/henni yvirheldur galdandi reglur hjá ÍSF og altjóða styrkiítróttarsambondum, og\n";
    $body .= "loyvir kanningar fyri doping sambært hesum reglum o.s.fr. (sí innlagda váttan á heimasíðuni).\n\n";

    // Yvirlit yvir hvørjir partar hava fingið PDF-avritið
    $body .= "PDF-avrit av hesi váttan er sent til hesar partar:\n";
    $body .= "- Føroya Styrkisamband (lyftiloyvi@fss.fo)\n";
    if (!empty($club)) {
        $body .= "- Felagið (" . $club . ")\n";
    }
    if (!empty($email)) {
        $body .= "- Íðkarin (" . $email . ")\n";
    }
    if (!empty($guardian_email)) {
        $body .= "- Verjin (" + $guardian_email . ")\n";
    }
    $body .= "\n";

    $body .= "Teldupostur hjá lyftara: {$email}\n";
    $body .= "\nSent frá: " . get_site_url() . "\n";

    $headers = [];
    if (!empty($email)) {
        $headers[] = 'Reply-To: ' . $email;
    }

    $fss_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : 'lyftiloyvi@fss.fo';

    // Final recipient: altíð til FSS
    $recipient = $fss_email;

    // Send final mail til FSS
    $sent = wp_mail($recipient, $subject, $body, $headers, $attachments);

    // Avrit til felagið (formans-email), um definerað
    $club_chair_emails = lf_get_club_chair_emails();

    $club_recipient = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : '';

    // Normalize addresses to avoid duplicates
    $recipient_norm = strtolower(trim($recipient));
    $club_norm      = strtolower(trim($club_recipient));
    $email_norm     = strtolower(trim($email));
    $guardian_norm  = strtolower(trim($guardian_email));

    // Copy to club (with PDF) unless it matches FSS recipient
    if ($sent && !empty($club_recipient) && $club_norm !== '' && $club_norm !== $recipient_norm) {
        wp_mail($club_recipient, $subject, $body, $headers, $attachments);
    }

    // Final mail to athlete (with PDF) unless it matches FSS or club
    if ($sent && !empty($email) && $email_norm !== '' && $email_norm !== $recipient_norm && $email_norm !== $club_norm) {
        wp_mail($email, $subject, $body, '', $attachments);
    }

    // Final mail to guardian (with PDF) unless it matches FSS, club or athlete
    if ($sent && !empty($guardian_email) && $guardian_norm !== '' && $guardian_norm !== $recipient_norm && $guardian_norm !== $club_norm && $guardian_norm !== $email_norm) {
        wp_mail($guardian_email, $subject, $body, '', $attachments);
    }

    // Uppdatera status í tabellini og goym eisini dagførda data
    $wpdb->update(
        $table_name,
        [
            'status'      => 'approved',
            'approved_at' => current_time('mysql', 1),
            'pdf_path'    => !empty($pdf_path) ? $pdf_path : '',
            'data'        => maybe_serialize($data),
        ],
        ['id' => $row->id],
        ['%s', '%s', '%s', '%s'],
        ['%d']
    );

    return $sent;
}

/**
 * Handle approval link from chairman (2-step flow).
 */
function lf_handle_approval() {
    if (!isset($_GET['lf_approve'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['lf_approve']));
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE token = %s LIMIT 1",
            $token
        )
    );

    if (!$row) {
        wp_die('<p>Umsókn fannst ikki ella er ikki longur virkandi.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    if ($row->status === 'approved') {
        wp_die('<p>Henda umsókn er longu endaliga góðkend og send.</p>', 'Lyftiloyvi', ['response' => 200]);
    }
    if ($row->status === 'denied') {
        $d = maybe_unserialize($row->data);
        $reason = is_array($d) ? ($d['denied_reason'] ?? '') : '';
        wp_die('<p>Henda umsókn er noktað.</p>' . ($reason ? '<p><strong>Orsøk:</strong> ' . esc_html($reason) . '</p>' : ''), 'Lyftiloyvi', ['response' => 200]);
    }

    $data = maybe_unserialize($row->data);
    if (!is_array($data)) {
        wp_die('<p>Umsóknarkanning miseydnaðist.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    // Extract stored fields
    $is_minor = !empty($data['is_minor']);

    // 2-step inni í approval: fyrst biðja um navn á góðkennarum (formanni/nevdarlimi),
    // síðani, tá navn er sent inn, dagføra data og møguliga gera fullnaðar-góðkenning.

    $approved_by_current = $data['approved_by'] ?? '';
    $guardian_approved_by_current = $data['guardian_approved_by'] ?? '';
    $fss_approved_by_current = $data['fss_approved_by'] ?? '';

    $status_html  = '<div style="max-width:480px;margin:0.6rem auto 0;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:13px;">';
    $status_html .= '<h3 style="margin:0 0 0.5rem;">Støða</h3>';
    $status_html .= '<ul style="margin:0;padding-left:1.2rem;">';
    $status_html .= '<li>Felag: ' . (!empty($approved_by_current) ? 'Góðkent (' . esc_html($approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '<li>Verji: ' . (!empty($guardian_approved_by_current) ? 'Góðkent (' . esc_html($guardian_approved_by_current) . ')' : ($is_minor ? 'Ikki góðkent enn' : 'Ikki kravt')) . '</li>';
    $status_html .= '<li>FSS: ' . (!empty($fss_approved_by_current) ? 'Góðkent (' . esc_html($fss_approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '</ul>';
    $status_html .= '</div>';
    $approved_by = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['lf_approved_name']) || isset($_POST['lf_deny']))) {
        // Deny flow
        if (isset($_POST['lf_deny'])) {
            $deny_name = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
            $deny_reason = sanitize_textarea_field(wp_unslash($_POST['lf_deny_reason'] ?? ''));
            if ($deny_name === '') {
                wp_die('<p>Vinaliga skriva navn.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            if ($deny_reason === '') {
                wp_die('<p>Vinaliga skriva eina viðmerking um, hví umsóknin verður noktað.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            lf_mark_denied($row, $data, 'Felag', $deny_name, $deny_reason);
            wp_die('<p>Umsóknin er noktað. Allir partar hava fingið teldupost um tað.</p>', 'Lyftiloyvi', ['response' => 200]);
        }

        // Approve flow
        $approved_by = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
        if ($approved_by === '') {
            wp_die('<p>Vinaliga skriva navn á tann, sum góðkennir umsóknina.</p>', 'Lyftiloyvi', ['response' => 200]);
        }
        // Goym navn á góðkennarum (felagið) í data
        $data['approved_by'] = $approved_by;
    } else {
        // Vís eitt lítið form, sum biður om navn á góðkennarum
        $form_html  = '<form method="post" style="max-width:480px;margin:2rem auto;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">';
        $form_html .= '<h2>Góðkenning av lyftiloyvi (felag)</h2>';
        $form_html .= $status_html;
        $form_html .= lf_build_application_summary_html($data);
        $form_html .= '<p>Fyri at góðkenna hesa umsókn frá felagnum, skalt tú skriva navn á tann, sum góðkennir (formaður ella nevndarlimur):</p>';
        $form_html .= '<p><label>Navn<br><input type="text" name="lf_approved_name" required style="width:100%;padding:0.5rem;"></label></p>';
        $form_html .= '<p style="margin-top:0.5rem;"><label style="font-weight:600;">Nokta við viðmerking</label><br>';
        $form_html .= '<textarea name="lf_deny_reason" rows="3" style="width:100%;padding:0.5rem;" placeholder="Skriva hví lyftiloyvið verður nokta..."></textarea></p>';
        $form_html .= '<p style="display:flex;gap:0.75rem;flex-wrap:wrap;">'
            . '<button type="submit" style="padding:0.5rem 1.2rem;">Góðkenn</button>'
            . '<button type="submit" name="lf_deny" value="1" onclick="var t=this.form.querySelector(\'textarea[name=\\\"lf_deny_reason\\\"]\'); if(!t||!t.value.trim()){alert(\'Skriva viðmerking um hví umsóknin verður noktað.\'); return false;} return confirm(\'Ert tú viss(ur) í, at tú vilt nokta lyftiloyvið?\');" style="padding:0.5rem 1.2rem;background:#b71c1c;color:#fff;border:none;border-radius:4px;">Nokta</button>'
            . '</p>';
        $form_html .= '</form>';

        wp_die($form_html, 'Lyftiloyvi', ['response' => 200]);
    }

    // Dagfør data í DB
    $wpdb->update(
        $table_name,
        [
            'data' => maybe_serialize($data),
        ],
        ['id' => $row->id],
        ['%s'],
        ['%d']
    );

    // Avgjørð um vit kunnu fullgóðkenna beinanvegin
    $finalized = lf_maybe_finalize($row, $data);

    if ($finalized) {
        wp_die('<p>Takk! Tú hevur góðkent umsóknina. Allir kravdir partar hava nú góðkent, og endaligu teldupostarnir eru sendir við PDF-skjalinum.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    wp_die('<p>Takk! Tú hevur góðkent umsóknina frá felagnum. Umsóknin bíðar nú eftir hinum góðkenningunum.</p>', 'Lyftiloyvi', ['response' => 200]);
}
add_action('template_redirect', 'lf_handle_approval');

/**
 * Handle approval link from guardian (2-step flow).
 */
function lf_handle_guardian_approval() {
    if (!isset($_GET['lf_guardian_approve'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['lf_guardian_approve']));
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE guardian_token = %s LIMIT 1",
            $token
        )
    );

    if (!$row) {
        wp_die('<p>Umsókn fannst ikki ella er ikki longur virkandi (verji).</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    if ($row->status === 'approved') {
        wp_die('<p>Henda umsókn er longu endaliga góðkend og send.</p>', 'Lyftiloyvi', ['response' => 200]);
    }
    if ($row->status === 'denied') {
        $d = maybe_unserialize($row->data);
        $reason = is_array($d) ? ($d['denied_reason'] ?? '') : '';
        wp_die('<p>Henda umsókn er noktað.</p>' . ($reason ? '<p><strong>Orsøk:</strong> ' . esc_html($reason) . '</p>' : ''), 'Lyftiloyvi', ['response' => 200]);
    }

    $data = maybe_unserialize($row->data);
    if (!is_array($data)) {
        wp_die('<p>Umsóknarkanning miseydnaðist.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    $is_minor       = !empty($data['is_minor']);

    $approved_by_current = $data['approved_by'] ?? '';
    $guardian_approved_by_current = $data['guardian_approved_by'] ?? '';
    $fss_approved_by_current = $data['fss_approved_by'] ?? '';

    $status_html  = '<div style="max-width:480px;margin:0.6rem auto 0;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:13px;">';
    $status_html .= '<h3 style="margin:0 0 0.5rem;">Støða</h3>';
    $status_html .= '<ul style="margin:0;padding-left:1.2rem;">';
    $status_html .= '<li>Felag: ' . (!empty($approved_by_current) ? 'Góðkent (' . esc_html($approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '<li>Verji: ' . (!empty($guardian_approved_by_current) ? 'Góðkent (' . esc_html($guardian_approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '<li>FSS: ' . (!empty($fss_approved_by_current) ? 'Góðkent (' . esc_html($fss_approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '</ul>';
    $status_html .= '</div>';
    $name           = $data['name'] ?? '';
    $guardian_email = $data['guardian_email'] ?? '';

    if (!$is_minor) {
        wp_die('<p>Henda umsókn krevur ikki góðkenning frá verjanum.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    $guardian_approved_by = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['lf_approved_name']) || isset($_POST['lf_deny']))) {
        // Deny flow
        if (isset($_POST['lf_deny'])) {
            $deny_name = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
            $deny_reason = sanitize_textarea_field(wp_unslash($_POST['lf_deny_reason'] ?? ''));
            if ($deny_name === '') {
                wp_die('<p>Vinaliga skriva navn.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            if ($deny_reason === '') {
                wp_die('<p>Vinaliga skriva eina viðmerking um, hví umsóknin verður noktað.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            lf_mark_denied($row, $data, 'Verji', $deny_name, $deny_reason);
            wp_die('<p>Umsóknin er noktað. Allir partar hava fingið teldupost um tað.</p>', 'Lyftiloyvi', ['response' => 200]);
        }

        // Approve flow
        $guardian_approved_by = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
        if ($guardian_approved_by === '') {
            wp_die('<p>Vinaliga skriva navn á verjan, sum góðkennir umsóknina.</p>', 'Lyftiloyvi', ['response' => 200]);
        }
        // Goym navn á verjanum í data
        $data['guardian_approved_by'] = $guardian_approved_by;
    } else {

        // Vís eitt lítið form til verjan
        $form_html  = '<div style="max-width:560px;margin:1.5rem auto;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">';
        $form_html .= '<h2>Góðkenning av lyftiloyvi (verji)</h2>';
        $form_html .= $status_html;
        $form_html .= lf_build_application_summary_html($data);
        $form_html .= '<p>Les allar upplýsingarnar ígjøggnum, og síðani góðkenn sum verji hjá' . esc_html($name) . '.</p>';
        $form_html .= '<form method="post" style="margin-top:1rem;">';
        $form_html .= '<p><label>Navn<br><input type="text" name="lf_approved_name" required style="width:100%;padding:0.5rem;"></label></p>';
        $form_html .= '<p style="margin-top:0.5rem;"><label style="font-weight:600;">Nokta við viðmerking</label><br>';
        $form_html .= '<textarea name="lf_deny_reason" rows="3" style="width:100%;padding:0.5rem;" placeholder="Skriva hví lyftiloyvið verður nokta..."></textarea></p>';
        $form_html .= '<p style="display:flex;gap:0.75rem;flex-wrap:wrap;">'
            . '<button type="submit" style="padding:0.5rem 1.2rem;">Góðkenn</button>'
            . '<button type="submit" name="lf_deny" value="1" onclick="var t=this.form.querySelector(\'textarea[name=\\\"lf_deny_reason\\\"]\'); if(!t||!t.value.trim()){alert(\'Skriva viðmerking um hví umsóknin verður noktað.\'); return false;} return confirm(\'Ert tú viss(ur) í, at tú vilt nokta lyftiloyvið?\');" style="padding:0.5rem 1.2rem;background:#b71c1c;color:#fff;border:none;border-radius:4px;">Nokta</button>'
            . '</p>';
        $form_html .= '</form>';
        $form_html .= '</div>';

        wp_die($form_html, 'Lyftiloyvi', ['response' => 200]);
    }

    // Dagfør data í DB
    $wpdb->update(
        $table_name,
        [
            'data' => maybe_serialize($data),
        ],
        ['id' => $row->id],
        ['%s'],
        ['%d']
    );

    // Kann vit fullgóðkenna beinanvegin?
    $finalized = lf_maybe_finalize($row, $data);

    if ($finalized) {
        wp_die('<p>Takk! Tú hevur góðkent umsóknina sum verji. Allir kravdir partar hava nú góðkent, og endaligu teldupostarnir eru sendir við PDF-skjalinum.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    wp_die('<p>Takk! Tú hevur góðkent umsóknina sum verji. Umsóknin bíðar nú eftir hinum góðkenningunum.</p>', 'Lyftiloyvi', ['response' => 200]);
}
add_action('template_redirect', 'lf_handle_guardian_approval');

/**
 * Handle final approval link from FSS.
 */
function lf_handle_fss_approval() {
    if (!isset($_GET['lf_fss_approve'])) {
        return;
    }

    $token = sanitize_text_field(wp_unslash($_GET['lf_fss_approve']));
    if (empty($token)) {
        return;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $row = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM {$table_name} WHERE fss_token = %s LIMIT 1",
            $token
        )
    );

    if (!$row) {
        wp_die('<p>Umsókn fannst ikki ella er ikki longur virkandi (FSS).</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    if ($row->status === 'approved') {
        wp_die('<p>Henda umsókn er longu endaliga góðkend og send.</p>', 'Lyftiloyvi', ['response' => 200]);
    }
    if ($row->status === 'denied') {
        $d = maybe_unserialize($row->data);
        $reason = is_array($d) ? ($d['denied_reason'] ?? '') : '';
        wp_die('<p>Henda umsókn er noktað.</p>' . ($reason ? '<p><strong>Orsøk:</strong> ' . esc_html($reason) . '</p>' : ''), 'Lyftiloyvi', ['response' => 200]);
    }

    $data = maybe_unserialize($row->data);
    if (!is_array($data)) {
        wp_die('<p>Umsóknarkanning miseydnaðist.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    $is_minor = !empty($data['is_minor']);
    $approved_by_current = $data['approved_by'] ?? '';
    $guardian_approved_by_current = $data['guardian_approved_by'] ?? '';
    $fss_approved_by_current = $data['fss_approved_by'] ?? '';

    $status_html  = '<div style="max-width:560px;margin:0.6rem auto 0;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;font-size:13px;">';
    $status_html .= '<h3 style="margin:0 0 0.5rem;">Støða</h3>';
    $status_html .= '<ul style="margin:0;padding-left:1.2rem;">';
    $status_html .= '<li>Felag: ' . (!empty($approved_by_current) ? 'Góðkent (' . esc_html($approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '<li>Verji: ' . (!empty($guardian_approved_by_current) ? 'Góðkent (' . esc_html($guardian_approved_by_current) . ')' : ($is_minor ? 'Ikki góðkent enn' : 'Ikki kravt')) . '</li>';
    $status_html .= '<li>FSS: ' . (!empty($fss_approved_by_current) ? 'Góðkent (' . esc_html($fss_approved_by_current) . ')' : 'Ikki góðkent enn') . '</li>';
    $status_html .= '</ul>';
    $status_html .= '</div>';

    // Prefill default denial reason for FSS (ADD course missing)
    $name = $data['name'] ?? '';
    $deny_default_reason = "{$name} hevur ikki eitt skrásett ADD skeið í ADD skipanini hjá FSS, og verður tí biðin um at fullfíggja tað áðrenn hon er játtað lyftiloyvið.\n\n";
    $deny_default_reason .= "Hevur hon tað frá øðrum ítróttagreinum, kann hon venda seg til Niels Áka Mørk.\n\n";
    $deny_default_reason .= "Skeiðið kann takast her https://uddannelse.antidoping.dk/, og tekur áleið 30 min.";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['lf_approved_name']) || isset($_POST['lf_deny']))) {
        // Deny flow
        if (isset($_POST['lf_deny'])) {
            $deny_name = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
            $deny_reason = sanitize_textarea_field(wp_unslash($_POST['lf_deny_reason'] ?? ''));
            if ($deny_name === '') {
                wp_die('<p>Vinaliga skriva navn.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            if ($deny_reason === '') {
                wp_die('<p>Vinaliga skriva eina viðmerking um, hví umsóknin verður noktað.</p>', 'Lyftiloyvi', ['response' => 200]);
            }
            lf_mark_denied($row, $data, 'FSS', $deny_name, $deny_reason);
            wp_die('<p>Umsóknin er noktað. Allir partar hafa fingið teldupost um tað.</p>', 'Lyftiloyvi', ['response' => 200]);
        }

        // Approve flow
        $fss_approved_by = sanitize_text_field(wp_unslash($_POST['lf_approved_name'] ?? ''));
        if ($fss_approved_by === '') {
            wp_die('<p>Vinaliga skriva navn á tann, sum góðkennir í FSS.</p>', 'Lyftiloyvi', ['response' => 200]);
        }

        $data['fss_approved_by'] = $fss_approved_by;

        $wpdb->update(
            $table_name,
            [
                'data' => maybe_serialize($data),
                'fss_approved_at' => current_time('mysql', 1),
            ],
            ['id' => $row->id],
            ['%s', '%s'],
            ['%d']
        );

        $finalized = lf_maybe_finalize($row, $data);

        if ($finalized) {
            wp_die('<p>Takk! FSS hevur góðkent. Allir kravdir partar hava nú góðkent, og endaligu teldupostarnir eru sendir við PDF-skjalinum.</p>', 'Lyftiloyvi', ['response' => 200]);
        }

        wp_die('<p>Takk! FSS hevur góðkent. Umsóknin bíðar nú eftir hinum góðkenningunum.</p>', 'Lyftiloyvi', ['response' => 200]);
    }

    $form_html  = '<div style="max-width:560px;margin:2rem auto;font-family:system-ui,-apple-system,BlinkMacSystemFont,\'Segoe UI\',sans-serif;">';
    $form_html .= '<h2>Endalig góðkenning (FSS)</h2>';
    $form_html .= $status_html;
    $form_html .= lf_build_application_summary_html($data);
    $form_html .= '<p>Fyri at endaliga góðkenna og senda endaligu PDF-fíluna til allar partar, skalt tú skriva navn á tann, sum góðkennir í FSS:</p>';
    $form_html .= '<form method="post" style="margin-top:1rem;">';
    $form_html .= '<p><label>Navn<br><input type="text" name="lf_approved_name" required style="width:100%;padding:0.5rem;"></label></p>';
    $form_html .= '<p style="margin-top:0.5rem;"><label style="font-weight:600;">Nokta við viðmerking</label><br>';
    $form_html .= '<textarea name="lf_deny_reason" rows="6" style="width:100%;padding:0.5rem;" placeholder="Skriva hví lyftiloyvið verður nokta...">' . esc_textarea($deny_default_reason) . '</textarea></p>';
    $form_html .= '<p style="display:flex;gap:0.75rem;flex-wrap:wrap;">'
        . '<button type="submit" style="padding:0.5rem 1.2rem;">Góðkenn</button>'
        . '<button type="submit" name="lf_deny" value="1" onclick="var t=this.form.querySelector(\'textarea[name=\\\"lf_deny_reason\\\"]\'); if(!t||!t.value.trim()){alert(\'Skriva viðmerking um hví umsóknin verður noktað.\'); return false;} return confirm(\'Ert tú viss(ur) í, at tú vilt nokta lyftiloyvið?\');" style="padding:0.5rem 1.2rem;background:#b71c1c;color:#fff;border:none;border-radius:4px;">Nokta</button>'
        . '</p>';
    $form_html .= '</form>';
    $form_html .= '</div>';

    wp_die($form_html, 'Lyftiloyvi', ['response' => 200]);
}
add_action('template_redirect', 'lf_handle_fss_approval');

