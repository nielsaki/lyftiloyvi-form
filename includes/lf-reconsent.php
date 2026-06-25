<?php

if (!defined('ABSPATH')) {
    exit;
}

// ─── Consent labels (must match lf-pdf.php) ───────────────────────────────
function lf_get_consent_labels() {
    return [
        'Eg játti at lata meg kanna fyri doping.',
        'Eg játti at endurrinda FSS allar útreiðslur frá seinastu 12 mánaðunum undan brotinum, um eg verð funnin sekur í broti á anti-doping reglunar.',
        'Eg játti at fylgja galdandi anti-doping reglum hjá ÍSF og teimum viðkomandi altjóða sambondunum, sum FSS er limur í.',
        'Eg játti, at FSS kann goyma eitt eintak av kappingarloyvinum.',
        'Eg játti, at eg havi lokið skeiðið „Antidoping 1 – for idrætsudøvere", áðrenn eg umboði Føroyar og Merkið í altjóða kapping. Verði eg biðin um at skráseta whereabouts, játti eg eisini at taka skeiðið „Whereabouts – en guide for atleter".',
    ];
}

// ─── Trigger ──────────────────────────────────────────────────────────────

/**
 * Trigger a re-consent cycle for one row. Called from the admin bulk action.
 * - Deletes old PDF and clears approval data
 * - Generates fresh tokens for all parties
 * - Sends invitation emails
 */
function lf_trigger_reconsent($row, $data) {
    global $wpdb;
    $table = $wpdb->prefix . 'lf_kappingarloyvi_requests';

    $is_minor       = !empty($data['is_minor']);
    $guardian_email = $data['guardian_email'] ?? '';

    // Delete old PDF
    if (!empty($row->pdf_path) && is_string($row->pdf_path) && file_exists($row->pdf_path)) {
        @unlink($row->pdf_path);
    }

    // Fresh tokens
    $athlete_token  = wp_generate_password(40, false, false);
    $club_token     = wp_generate_password(40, false, false);
    $guardian_token = ($is_minor && !empty($guardian_email) && is_email($guardian_email))
        ? wp_generate_password(40, false, false) : '';
    $fss_token      = wp_generate_password(40, false, false);

    // Clear approval fields so they must be re-entered
    $data['approved_by']            = '';
    $data['club_approved_date']     = '';
    $data['fss_approved_by']        = '';
    $data['fss_approved_date']      = '';
    $data['guardian_approved_by']   = '';
    $data['guardian_approved_date'] = '';
    unset($data['denied'], $data['denied_role'], $data['denied_by'],
          $data['denied_reason'], $data['denied_at']);

    // Write to DB (use raw query so we can set NULL on datetime columns)
    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET
            status = 'pending',
            pdf_path = '',
            data = %s,
            approved_at = NULL,
            fss_approved_at = NULL,
            reconsent_status = 'pending',
            reconsent_athlete_token = %s,
            reconsent_club_token = %s,
            reconsent_guardian_token = %s,
            reconsent_fss_token = %s,
            reconsent_athlete_at = NULL,
            reconsent_club_at = NULL,
            reconsent_guardian_at = NULL,
            reconsent_fss_at = NULL
         WHERE id = %d",
        maybe_serialize($data),
        $athlete_token,
        $club_token,
        $guardian_token,
        $fss_token,
        $row->id
    ));

    // ── Send emails ───────────────────────────────────────────────────────
    $name  = $data['name'] ?? '';
    $club  = $data['club'] ?? '';
    $email = $data['email'] ?? '';
    $site  = get_site_url();

    $subject_base = 'Nýggjar skilmálar – kappingarloyvi';
    if ($name) $subject_base .= ': ' . $name;
    if ($club) $subject_base .= ' (' . $club . ')';

    $athlete_link = add_query_arg('lf_reconsent', rawurlencode($athlete_token), $site);
    $club_link    = add_query_arg('lf_reconsent', rawurlencode($club_token), $site);
    $fss_link     = add_query_arg('lf_reconsent', rawurlencode($fss_token), $site);

    // Athlete
    if ($email && is_email($email)) {
        $body  = "Góðan dag,\n\n";
        $body .= "Skilmálarnir fyri kappingarloyvi eru broyttir. Tú verður biðin/ur um at lesa og staðfesta aftur.\n\n";
        $body .= "Upplýsingarnar í forminum eru fyritfyltar – rætta tær, um nakað er broytt.\n\n";
        $body .= "Klikk hér at staðfesta:\n{$athlete_link}\n\n";
        $body .= "Sent frá: {$site}\n";
        wp_mail($email, $subject_base, $body);
    }

    // Club chair
    $club_chair_emails = function_exists('lf_get_club_chair_emails') ? lf_get_club_chair_emails() : [];
    $club_email = $club_chair_emails[$club] ?? '';
    if (!$club_email) {
        $club_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : '';
    }
    if ($club_email && is_email($club_email)) {
        $body  = "Góðan dag,\n\n";
        $body .= "Skilmálarnir fyri kappingarloyvi eru broyttir. Felagið verður biðin/ur um at lesa og góðkenna aftur.\n\n";
        $body .= "Íðkari: {$name}\nFelag: {$club}\n\n";
        $body .= "Klikk hér at góðkenna:\n{$club_link}\n\n";
        $body .= "Sent frá: {$site}\n";
        wp_mail($club_email, $subject_base, $body);
    }

    // Guardian
    if ($guardian_token && !empty($guardian_email) && is_email($guardian_email)) {
        $guardian_link = add_query_arg('lf_reconsent', rawurlencode($guardian_token), $site);
        $body  = "Góðan dag,\n\n";
        $body .= "Skilmálarnir fyri kappingarloyvi fyri {$name} eru broyttir. Tú verður biðin/ur um at lesa og góðkenna aftur sum verji.\n\n";
        $body .= "Klikk hér at góðkenna:\n{$guardian_link}\n\n";
        $body .= "Sent frá: {$site}\n";
        wp_mail($guardian_email, $subject_base, $body);
    }

    // FSS
    $fss_email = function_exists('lf_get_fss_email') ? lf_get_fss_email() : '';
    if ($fss_email && is_email($fss_email)) {
        $body  = "Góðan dag,\n\n";
        $body .= "Kappingarloyvi fyri {$name} ({$club}) krevur nýggja góðkenning frá FSS (skilmálar eru broyttir).\n\n";
        $body .= "Klikk hér at góðkenna:\n{$fss_link}\n\n";
        $body .= "Sent frá: {$site}\n";
        wp_mail($fss_email, $subject_base, $body);
    }

    return true;
}

// ─── Lookup ───────────────────────────────────────────────────────────────

function lf_find_reconsent_row($token) {
    global $wpdb;
    $table = $wpdb->prefix . 'lf_kappingarloyvi_requests';
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$table}
         WHERE reconsent_athlete_token = %s
            OR reconsent_club_token    = %s
            OR reconsent_guardian_token = %s
            OR reconsent_fss_token     = %s
         LIMIT 1",
        $token, $token, $token, $token
    ));
}

// ─── Completion check ─────────────────────────────────────────────────────

/**
 * After any party submits, re-fetch the row and check if all required
 * parties are done. If so, generate a new PDF and send it to everyone.
 */
function lf_reconsent_maybe_complete($row) {
    global $wpdb;
    $table = $wpdb->prefix . 'lf_kappingarloyvi_requests';

    $row = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d LIMIT 1", $row->id));
    if (!$row || $row->reconsent_status !== 'pending') return false;

    $data     = maybe_unserialize($row->data);
    if (!is_array($data)) return false;
    $is_minor = !empty($data['is_minor']);

    $athlete_done  = !empty($row->reconsent_athlete_at);
    $club_done     = !empty($row->reconsent_club_at);
    // Guardian only required when minor AND a guardian token was issued
    $guardian_done = !$is_minor || !empty($row->reconsent_guardian_at) || empty($row->reconsent_guardian_token);
    $fss_done      = !empty($row->reconsent_fss_at);

    if (!($athlete_done && $club_done && $guardian_done && $fss_done)) {
        return false;
    }

    // Generate new PDF
    $pdf_path = function_exists('lf_generate_pdf') ? lf_generate_pdf($data) : null;

    $wpdb->query($wpdb->prepare(
        "UPDATE {$table} SET
            status = 'approved',
            approved_at = %s,
            fss_approved_at = %s,
            pdf_path = %s,
            data = %s,
            reconsent_status = 'complete'
         WHERE id = %d",
        current_time('mysql', 1),
        current_time('mysql', 1),
        $pdf_path ?? '',
        maybe_serialize($data),
        $row->id
    ));

    // Send final PDF to all parties
    $attachments = (!empty($pdf_path) && file_exists($pdf_path)) ? [$pdf_path] : [];
    $name           = $data['name'] ?? '';
    $club           = $data['club'] ?? '';
    $email          = $data['email'] ?? '';
    $guardian_email = $data['guardian_email'] ?? '';

    $subj  = 'Kappingarloyvi staðfest (nýggjar skilmálar)';
    if ($name) $subj .= ': ' . $name;
    if ($club) $subj .= ' (' . $club . ')';

    $body  = "Kappingarloyvið fyri {$name} ({$club}) er nú fullstaðfest við nýggju skilmálunum.\n\n";
    $body .= "Allir partar hava staðfest. PDF er hengt við.\n\n";
    $body .= "Sent frá: " . get_site_url() . "\n";

    $fss_email         = function_exists('lf_get_fss_email') ? lf_get_fss_email() : '';
    $club_chair_emails = function_exists('lf_get_club_chair_emails') ? lf_get_club_chair_emails() : [];
    $club_email        = $club_chair_emails[$club] ?? '';

    $recipients = array_values(array_unique(array_filter(
        [$fss_email, $club_email, $email, $guardian_email],
        'is_email'
    )));
    foreach ($recipients as $to) {
        wp_mail($to, $subj, $body, [], $attachments);
    }

    return true;
}

// ─── Shared UI helpers ────────────────────────────────────────────────────

function lf_reconsent_css() {
    return '<style>
    *{box-sizing:border-box;}
    body{font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
         background:#f0f2f5;color:#1a1a2e;margin:0;padding:1.5rem 1rem 3rem;}
    .rc-wrap{max-width:680px;margin:0 auto;}
    .rc-card{background:#fff;border-radius:10px;
             box-shadow:0 4px 18px rgba(0,0,0,.08);
             padding:2rem 2.25rem 2.5rem;margin-bottom:1.5rem;}
    .rc-card h1{margin:0 0 1rem;font-size:1.35rem;
                border-bottom:1px solid #e8eaed;padding-bottom:.6rem;}
    .rc-card h2{font-size:1rem;margin:1.4rem 0 .6rem;color:#444;}
    .rc-info{background:#f7f9fb;border:1px solid #dde2e8;border-radius:6px;
             padding:.75rem 1rem;font-size:.88rem;margin-bottom:1rem;}
    .rc-info table{width:100%;border-collapse:collapse;}
    .rc-info th{text-align:left;width:42%;font-weight:600;padding:3px 0;color:#555;}
    .rc-info td{padding:3px 0;}
    .rc-status{background:#f0f7ff;border:1px solid #b3d4f5;border-radius:6px;
               padding:.6rem 1rem;font-size:.85rem;margin-bottom:1rem;}
    .rc-status ul{margin:.3rem 0 0;padding-left:1.2rem;}
    .rc-field{margin-bottom:1rem;}
    .rc-field label{display:block;font-weight:600;margin-bottom:.3rem;font-size:.93rem;}
    .rc-field input[type=text],
    .rc-field input[type=email],
    .rc-field input[type=date],
    .rc-field select{width:100%;padding:.5em .65em;border:1px solid #ccd0d4;
                     border-radius:4px;font-size:.95rem;font-family:inherit;background:#fff;}
    .rc-field input:focus,.rc-field select:focus{outline:none;border-color:#007cba;
                                                  box-shadow:0 0 0 2px #007cba22;}
    .rc-row{display:flex;gap:1.2rem;flex-wrap:wrap;}
    .rc-row .rc-field{flex:1 1 200px;}
    .rc-consent{list-style:none;padding:0;margin:0 0 1.2rem;}
    .rc-consent li{display:flex;align-items:flex-start;gap:.6rem;
                   margin-bottom:.8rem;font-size:.9rem;line-height:1.4;}
    .rc-consent li input[type=checkbox]{margin-top:.18rem;flex-shrink:0;width:16px;height:16px;}
    .rc-notice{background:#fff8e1;border:1px solid #f9a825;border-radius:6px;
               padding:.7rem 1rem;font-size:.88rem;margin-bottom:1.2rem;color:#6b4000;}
    .rc-btn{display:inline-block;padding:.65rem 1.6rem;border-radius:4px;border:none;
            background:#007cba;color:#fff;font-size:.95rem;font-weight:600;cursor:pointer;}
    .rc-btn:hover{background:#006ba1;}
    .rc-minor-block{background:#f9f9fb;border:1px dashed #ccd0d4;border-radius:6px;
                    padding:1rem 1rem .25rem;margin-top:.25rem;}
    @media(max-width:520px){.rc-card{padding:1.4rem 1.2rem 2rem;}.rc-row{flex-direction:column;}}
    </style>';
}

function lf_reconsent_status_block($row, $data) {
    $is_minor = !empty($data['is_minor']);
    $tick = '&#10003;';
    $html  = '<div class="rc-status"><strong>Staða:</strong>';
    $html .= '<ul>';
    $html .= '<li>Íðkari: '  . (!empty($row->reconsent_athlete_at)  ? $tick . ' Staðfest' : '○ Bíðar') . '</li>';
    $html .= '<li>Felag: '   . (!empty($row->reconsent_club_at)      ? $tick . ' Staðfest' : '○ Bíðar') . '</li>';
    if ($is_minor) {
        $html .= '<li>Verji: ' . (!empty($row->reconsent_guardian_at) ? $tick . ' Staðfest' : '○ Bíðar') . '</li>';
    }
    $html .= '<li>FSS: '     . (!empty($row->reconsent_fss_at)       ? $tick . ' Staðfest' : '○ Bíðar') . '</li>';
    $html .= '</ul></div>';
    return $html;
}

function lf_reconsent_summary_block($data) {
    $fields = [
        'Navn'          => $data['name'] ?? '',
        'Føðingardagur' => $data['birthdate'] ?? '',
        'Felag'         => $data['club'] ?? '',
        'Teldupostur'   => $data['email'] ?? '',
        'Galdandi frá'  => $data['date'] ?? '',
    ];
    $html  = '<div class="rc-info"><table>';
    foreach ($fields as $k => $v) {
        if ($v === '') continue;
        $html .= '<tr><th>' . esc_html($k) . '</th><td>' . esc_html($v) . '</td></tr>';
    }
    $html .= '</table></div>';
    return $html;
}

function lf_reconsent_done_page($css) {
    return $css . '<div class="rc-wrap"><div class="rc-card"><h1>Longu staðfest</h1><p>Tú hevur longu staðfest hetta kappingarloyvi. Takk!</p></div></div>';
}

function lf_reconsent_error_page($css, $msg = '') {
    return $css . '<div class="rc-wrap"><div class="rc-card"><h1>Leinkjan er ikki galdandi</h1><p>' . esc_html($msg ?: 'Henda leinkjan er ikki galdandi. Vinarliga kontakta FSS.') . '</p></div></div>';
}

// ─── Main URL handler ─────────────────────────────────────────────────────

function lf_handle_reconsent_url() {
    if (empty($_GET['lf_reconsent'])) return;

    $token = sanitize_text_field(wp_unslash($_GET['lf_reconsent']));
    if (!$token) return;

    $css = lf_reconsent_css();
    $row = lf_find_reconsent_row($token);

    if (!$row) {
        wp_die(lf_reconsent_error_page($css), 'Kappingarloyvi', ['response' => 200]);
        return;
    }

    if ($row->reconsent_status !== 'pending') {
        wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Longu staðfest</h1><p>Hetta kappingarloyvi er longu fullstaðfest. Takk!</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
        return;
    }

    // Determine which role this token belongs to
    $role = null;
    if ($row->reconsent_athlete_token  === $token) $role = 'athlete';
    elseif ($row->reconsent_club_token    === $token) $role = 'club';
    elseif ($row->reconsent_guardian_token === $token) $role = 'guardian';
    elseif ($row->reconsent_fss_token     === $token) $role = 'fss';

    if (!$role) {
        wp_die(lf_reconsent_error_page($css), 'Kappingarloyvi', ['response' => 200]);
        return;
    }

    // Already done?
    $already = false;
    if ($role === 'athlete')  $already = !empty($row->reconsent_athlete_at);
    if ($role === 'club')     $already = !empty($row->reconsent_club_at);
    if ($role === 'guardian') $already = !empty($row->reconsent_guardian_at);
    if ($role === 'fss')      $already = !empty($row->reconsent_fss_at);

    if ($already) {
        wp_die(lf_reconsent_done_page($css), 'Kappingarloyvi', ['response' => 200]);
        return;
    }

    $data = maybe_unserialize($row->data);
    if (!is_array($data)) $data = [];

    // Handle POST
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['lf_reconsent_submit'])) {
        lf_reconsent_handle_submit($row, $data, $role, $token);
        return;
    }

    // Render form
    lf_reconsent_render_form($row, $data, $role, $token);
}
add_action('template_redirect', 'lf_handle_reconsent_url');

// ─── Form rendering ───────────────────────────────────────────────────────

function lf_reconsent_render_form($row, $data, $role, $token) {
    $css     = lf_reconsent_css();
    $url     = esc_url(add_query_arg('lf_reconsent', rawurlencode($token), get_site_url()));
    $status  = lf_reconsent_status_block($row, $data);
    $consent = lf_get_consent_labels();

    if ($role === 'athlete')   lf_reconsent_form_athlete($data, $token, $url, $status, $consent, $css);
    elseif ($role === 'club')  lf_reconsent_form_club($row, $data, $token, $url, $status, $consent, $css);
    elseif ($role === 'guardian') lf_reconsent_form_guardian($row, $data, $token, $url, $status, $consent, $css);
    elseif ($role === 'fss')   lf_reconsent_form_fss($row, $data, $token, $url, $status, $consent, $css);
}

function lf_reconsent_form_athlete($data, $token, $url, $status, $consent, $css) {
    $clubs    = function_exists('lf_get_clubs') ? lf_get_clubs() : [];
    $is_minor = !empty($data['is_minor']);

    $h  = $css . '<div class="rc-wrap">';
    $h .= '<form class="rc-card" method="post" action="' . $url . '">';
    $h .= '<h1>Staðfesting av kappingarloyvi – nýggjar skilmálar</h1>';
    $h .= '<div class="rc-notice">Skilmálarnir eru broyttir. Tú verður biðin/ur um at lesa og staðfesta aftur. Upplýsingarnar eru fyritfyltar.</div>';
    $h .= $status;

    $h .= '<h2>Upplýsingar um íðkara</h2>';
    $h .= '<div class="rc-row">';
    $h .= '<div class="rc-field"><label>Fulla navn *<input type="text" name="name" value="' . esc_attr($data['name'] ?? '') . '" required></label></div>';
    $h .= '<div class="rc-field"><label>Føðingardagur (dd.mm.áááá) *<input type="text" name="birthdate" value="' . esc_attr($data['birthdate'] ?? '') . '" required placeholder="dd.mm.áááá"></label></div>';
    $h .= '</div><div class="rc-row">';
    $h .= '<div class="rc-field"><label>Teldupostur *<input type="email" name="email" value="' . esc_attr($data['email'] ?? '') . '" required></label></div>';
    $h .= '<div class="rc-field"><label>Telefonnummar<input type="text" name="phone" value="' . esc_attr($data['phone'] ?? '') . '"></label></div>';
    $h .= '</div><div class="rc-row">';
    $h .= '<div class="rc-field"><label>Bústaður<input type="text" name="address" value="' . esc_attr($data['address'] ?? '') . '"></label></div>';
    $h .= '<div class="rc-field"><label>Býur/bygd<input type="text" name="city" value="' . esc_attr($data['city'] ?? '') . '"></label></div>';
    $h .= '</div>';

    $h .= '<div class="rc-field"><label>Felag *<select name="club" required>';
    $h .= '<option value="">Vel felag</option>';
    foreach ($clubs as $c) {
        $sel = (($data['club'] ?? '') === $c) ? ' selected' : '';
        $h .= '<option value="' . esc_attr($c) . '"' . $sel . '>' . esc_html($c) . '</option>';
    }
    $h .= '</select></label></div>';

    $h .= '<div class="rc-field"><label><input type="checkbox" name="is_minor" value="1" id="rc_minor"' . ($is_minor ? ' checked' : '') . '> Íðkari er undir 18 ár</label></div>';

    $h .= '<div id="rc_minor_block"' . ($is_minor ? '' : ' style="display:none;"') . '>';
    $h .= '<div class="rc-minor-block">';
    $h .= '<h2>Upplýsingar um verja</h2>';
    $h .= '<div class="rc-row">';
    $h .= '<div class="rc-field"><label>Navn á verja<input type="text" name="guardian_name" value="' . esc_attr($data['guardian_name'] ?? '') . '"></label></div>';
    $h .= '<div class="rc-field"><label>Teldupostur hjá verja<input type="email" name="guardian_email" value="' . esc_attr($data['guardian_email'] ?? '') . '"></label></div>';
    $h .= '</div>';
    $h .= '<div class="rc-field"><label>Telefonnummar hjá verja<input type="text" name="guardian_phone" value="' . esc_attr($data['guardian_phone'] ?? '') . '"></label></div>';
    $h .= '</div></div>';

    $h .= '<h2>Váttanir</h2>';
    $h .= '<ul class="rc-consent">';
    foreach ($consent as $i => $label) {
        $h .= '<li><input type="checkbox" name="consent[]" value="' . $i . '" required> <span>' . esc_html($label) . '</span></li>';
    }
    $h .= '</ul>';

    $h .= '<input type="hidden" name="lf_reconsent_submit" value="1">';
    $h .= '<input type="hidden" name="lf_reconsent_token" value="' . esc_attr($token) . '">';
    $h .= '<p><button type="submit" class="rc-btn">Staðfesta</button></p>';
    $h .= '</form></div>';
    $h .= '<script>(function(){var cb=document.getElementById("rc_minor"),bl=document.getElementById("rc_minor_block");if(cb&&bl)cb.addEventListener("change",function(){bl.style.display=cb.checked?"block":"none";});})();</script>';

    wp_die($h, 'Kappingarloyvi', ['response' => 200]);
}

function lf_reconsent_form_club($row, $data, $token, $url, $status, $consent, $css) {
    $name = esc_html($data['name'] ?? '');
    $club = esc_html($data['club'] ?? '');

    $h  = $css . '<div class="rc-wrap">';
    $h .= '<form class="rc-card" method="post" action="' . $url . '">';
    $h .= '<h1>Góðkenning av kappingarloyvi – nýggjar skilmálar (felag)</h1>';
    $h .= '<div class="rc-notice">Skilmálarnir fyri kappingarloyvi eru broyttir. Felagið verður biðin/ur um at góðkenna aftur fyri <strong>' . $name . '</strong>.</div>';
    $h .= $status;
    $h .= '<h2>Umsókn</h2>';
    $h .= lf_reconsent_summary_block($data);

    $h .= '<h2>Váttanir (ítróttarmaðurin játti)</h2>';
    $h .= '<p style="font-size:.87rem;color:#555;margin-top:0;">Felagið staðfestir at hava kynnt sær nýggju skilmálana, sum íðkarinn játtar:</p>';
    $h .= '<ul class="rc-consent">';
    foreach ($consent as $i => $label) {
        $h .= '<li><input type="checkbox" name="consent[]" value="' . $i . '" required> <span>' . esc_html($label) . '</span></li>';
    }
    $h .= '</ul>';

    $h .= '<div class="rc-field"><label>Tín navn (góðkennari hjá felagnum) *<input type="text" name="approved_by" value="' . esc_attr($data['approved_by'] ?? '') . '" required placeholder="Fulla navn"></label></div>';

    $h .= '<input type="hidden" name="lf_reconsent_submit" value="1">';
    $h .= '<input type="hidden" name="lf_reconsent_token" value="' . esc_attr($token) . '">';
    $h .= '<p><button type="submit" class="rc-btn">Góðkenn</button></p>';
    $h .= '</form></div>';

    wp_die($h, 'Kappingarloyvi', ['response' => 200]);
}

function lf_reconsent_form_guardian($row, $data, $token, $url, $status, $consent, $css) {
    $name = esc_html($data['name'] ?? '');

    $h  = $css . '<div class="rc-wrap">';
    $h .= '<form class="rc-card" method="post" action="' . $url . '">';
    $h .= '<h1>Góðkenning av kappingarloyvi – nýggjar skilmálar (verji)</h1>';
    $h .= '<div class="rc-notice">Skilmálarnir eru broyttir. Tú verður biðin/ur um at staðfesta aftur sum verji hjá <strong>' . $name . '</strong>.</div>';
    $h .= $status;
    $h .= '<h2>Umsókn</h2>';
    $h .= lf_reconsent_summary_block($data);

    $h .= '<h2>Upplýsingar um verja</h2>';
    $h .= '<div class="rc-row">';
    $h .= '<div class="rc-field"><label>Navn á verja<input type="text" name="guardian_name" value="' . esc_attr($data['guardian_name'] ?? '') . '"></label></div>';
    $h .= '<div class="rc-field"><label>Teldupostur hjá verja<input type="email" name="guardian_email" value="' . esc_attr($data['guardian_email'] ?? '') . '"></label></div>';
    $h .= '</div>';
    $h .= '<div class="rc-field"><label>Telefonnummar hjá verja<input type="text" name="guardian_phone" value="' . esc_attr($data['guardian_phone'] ?? '') . '"></label></div>';

    $h .= '<h2>Váttanir</h2>';
    $h .= '<ul class="rc-consent">';
    foreach ($consent as $i => $label) {
        $h .= '<li><input type="checkbox" name="consent[]" value="' . $i . '" required> <span>' . esc_html($label) . '</span></li>';
    }
    $h .= '</ul>';

    $h .= '<div class="rc-field"><label>Tín navn (verji) *<input type="text" name="guardian_approved_by" value="' . esc_attr($data['guardian_approved_by'] ?? '') . '" required placeholder="Fulla navn"></label></div>';

    $h .= '<input type="hidden" name="lf_reconsent_submit" value="1">';
    $h .= '<input type="hidden" name="lf_reconsent_token" value="' . esc_attr($token) . '">';
    $h .= '<p><button type="submit" class="rc-btn">Góðkenn sum verji</button></p>';
    $h .= '</form></div>';

    wp_die($h, 'Kappingarloyvi', ['response' => 200]);
}

function lf_reconsent_form_fss($row, $data, $token, $url, $status, $consent, $css) {
    $name = esc_html($data['name'] ?? '');
    $club = esc_html($data['club'] ?? '');

    $h  = $css . '<div class="rc-wrap">';
    $h .= '<form class="rc-card" method="post" action="' . $url . '">';
    $h .= '<h1>Endalig góðkenning – nýggjar skilmálar (FSS)</h1>';
    $h .= '<div class="rc-notice">Kappingarloyvi fyri <strong>' . $name . '</strong> (' . $club . ') krevur nýggja góðkenning frá FSS.</div>';
    $h .= $status;
    $h .= '<h2>Umsókn</h2>';
    $h .= lf_reconsent_summary_block($data);

    $h .= '<h2>Váttanir (ítróttarmaðurin játti)</h2>';
    $h .= '<p style="font-size:.87rem;color:#555;margin-top:0;">FSS staðfestir at hava kynnt sær nýggju skilmálana:</p>';
    $h .= '<ul class="rc-consent">';
    foreach ($consent as $i => $label) {
        $h .= '<li><input type="checkbox" name="consent[]" value="' . $i . '" required> <span>' . esc_html($label) . '</span></li>';
    }
    $h .= '</ul>';

    $h .= '<div class="rc-field"><label>Tín navn (FSS) *<input type="text" name="fss_approved_by" value="' . esc_attr($data['fss_approved_by'] ?? '') . '" required placeholder="Fulla navn"></label></div>';

    $h .= '<input type="hidden" name="lf_reconsent_submit" value="1">';
    $h .= '<input type="hidden" name="lf_reconsent_token" value="' . esc_attr($token) . '">';
    $h .= '<p><button type="submit" class="rc-btn">Góðkenn (FSS)</button></p>';
    $h .= '</form></div>';

    wp_die($h, 'Kappingarloyvi', ['response' => 200]);
}

// ─── Submission handlers ──────────────────────────────────────────────────

function lf_reconsent_handle_submit($row, $data, $role, $token) {
    global $wpdb;
    $table   = $wpdb->prefix . 'lf_kappingarloyvi_requests';
    $css     = lf_reconsent_css();
    $now     = current_time('mysql', 1);
    $today   = current_time('Y-m-d');
    $back    = esc_url(add_query_arg('lf_reconsent', rawurlencode($token), get_site_url()));
    $consent = lf_get_consent_labels();
    $n_boxes = count($consent);

    $checked = isset($_POST['consent']) ? count((array) $_POST['consent']) : 0;

    switch ($role) {

        case 'athlete':
            $name      = sanitize_text_field(wp_unslash($_POST['name'] ?? ''));
            $birthdate = sanitize_text_field(wp_unslash($_POST['birthdate'] ?? ''));
            $email     = sanitize_email(wp_unslash($_POST['email'] ?? ''));
            $phone     = sanitize_text_field(wp_unslash($_POST['phone'] ?? ''));
            $address   = sanitize_text_field(wp_unslash($_POST['address'] ?? ''));
            $city      = sanitize_text_field(wp_unslash($_POST['city'] ?? ''));
            $club_val  = sanitize_text_field(wp_unslash($_POST['club'] ?? ''));
            $is_minor  = !empty($_POST['is_minor']);
            $clubs     = function_exists('lf_get_clubs') ? lf_get_clubs() : [];

            if (!$name || !$birthdate || !$email || !$club_val || !in_array($club_val, $clubs, true)) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Fyll út øll kravdu felt og vel eitt gilt felag.</p><p><a href="' . $back . '">← Aftur</a></p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            if ($checked < $n_boxes) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Tú verður at játta øllum váttanunum.</p><p><a href="' . $back . '">← Aftur</a></p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }

            $data['name']              = $name;
            $data['birthdate']         = $birthdate;
            $data['email']             = $email;
            $data['phone']             = $phone;
            $data['address']           = $address;
            $data['city']              = $city;
            $data['club']              = $club_val;
            $data['is_minor']          = $is_minor;
            $data['consent_timestamp'] = current_time('Y-m-d H:i:s');

            if ($is_minor) {
                $data['guardian_name']  = sanitize_text_field(wp_unslash($_POST['guardian_name'] ?? ''));
                $data['guardian_email'] = sanitize_email(wp_unslash($_POST['guardian_email'] ?? ''));
                $data['guardian_phone'] = sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? ''));
            } else {
                $data['guardian_name'] = $data['guardian_email'] = $data['guardian_phone'] = '';
            }

            $wpdb->update($table,
                ['data' => maybe_serialize($data), 'reconsent_athlete_at' => $now],
                ['id' => $row->id], ['%s', '%s'], ['%d']
            );
            break;

        case 'club':
            $approved_by = sanitize_text_field(wp_unslash($_POST['approved_by'] ?? ''));
            if (!$approved_by) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Skriva tín navn.</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            if ($checked < $n_boxes) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Tú verður at játta øllum váttanunum.</p><p><a href="' . $back . '">← Aftur</a></p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            $data['approved_by']        = $approved_by;
            $data['club_approved_date'] = $today;
            $wpdb->update($table,
                ['data' => maybe_serialize($data), 'reconsent_club_at' => $now],
                ['id' => $row->id], ['%s', '%s'], ['%d']
            );
            break;

        case 'guardian':
            $gby    = sanitize_text_field(wp_unslash($_POST['guardian_approved_by'] ?? ''));
            $gname  = sanitize_text_field(wp_unslash($_POST['guardian_name'] ?? ''));
            $gemail = sanitize_email(wp_unslash($_POST['guardian_email'] ?? ''));
            $gphone = sanitize_text_field(wp_unslash($_POST['guardian_phone'] ?? ''));
            if (!$gby) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Skriva tín navn.</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            if ($checked < $n_boxes) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Tú verður at játta øllum váttanunum.</p><p><a href="' . $back . '">← Aftur</a></p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            $data['guardian_approved_by']   = $gby;
            $data['guardian_approved_date'] = $today;
            if ($gname)  $data['guardian_name']  = $gname;
            if ($gemail) $data['guardian_email'] = $gemail;
            if ($gphone) $data['guardian_phone'] = $gphone;
            $wpdb->update($table,
                ['data' => maybe_serialize($data), 'reconsent_guardian_at' => $now],
                ['id' => $row->id], ['%s', '%s'], ['%d']
            );
            break;

        case 'fss':
            $fby = sanitize_text_field(wp_unslash($_POST['fss_approved_by'] ?? ''));
            if (!$fby) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Skriva tín navn.</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            if ($checked < $n_boxes) {
                wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Villa</h1><p>Tú verður at játta øllum váttanunum.</p><p><a href="' . $back . '">← Aftur</a></p></div></div>', 'Kappingarloyvi', ['response' => 200]);
                return;
            }
            $data['fss_approved_by']   = $fby;
            $data['fss_approved_date'] = $today;
            $wpdb->update($table,
                ['data' => maybe_serialize($data), 'reconsent_fss_at' => $now, 'fss_approved_at' => $now],
                ['id' => $row->id], ['%s', '%s', '%s'], ['%d']
            );
            break;

        default:
            wp_die(lf_reconsent_error_page($css), 'Kappingarloyvi', ['response' => 200]);
            return;
    }

    $completed = lf_reconsent_maybe_complete($row);

    if ($completed) {
        wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Takk!</h1><p>Tú hevur staðfest. Allir partar hava nú staðfest, og nýtt kappingarloyvi er sent til allar partar við PDF-skjalinum.</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
    } else {
        wp_die($css . '<div class="rc-wrap"><div class="rc-card"><h1>Takk!</h1><p>Tú hevur staðfest. Kappingarloyvið bíðar nú eftir staðfestingu frá hinum partunum.</p></div></div>', 'Kappingarloyvi', ['response' => 200]);
    }
}
