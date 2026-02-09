<?php

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Return logo sources for PDF: data URIs from local assets/logos/ or raster URLs.
 * Dompdf does not support SVG – only PNG/JPG are used.
 */
function lf_get_pdf_logo_sources() {
    $plugin_dir = dirname(__DIR__);
    $logos_dir  = $plugin_dir . '/assets/logos';
    $keys       = ['fss', 'adf', 'isf'];
    $sources    = [];

    foreach ($keys as $key) {
        $path = $logos_dir . '/' . $key . '.png';
        if (file_exists($path) && is_readable($path)) {
            $raw  = file_get_contents($path);
            $sources[$key] = 'data:image/png;base64,' . base64_encode($raw);
        } else {
            $sources[$key] = '';
        }
    }

    $from_config = function_exists('lf_get_logo_urls_for_pdf') ? lf_get_logo_urls_for_pdf() : (function_exists('lf_get_logo_urls') ? lf_get_logo_urls() : []);
    foreach ($keys as $key) {
        if (!empty($sources[$key])) {
            continue;
        }
        $url = $from_config[$key] ?? '';
        if ($url !== '' && preg_match('/\.(png|jpe?g|gif)$/i', $url)) {
            $sources[$key] = $url;
        }
    }

    return $sources;
}

/**
 * Ger eina PDF-fílu við upplýsingum úr lyftiloyvisformin og returnerar stígin.
 * Krevur, at Dompdf er tøkt (t.d. via dompdf/autoload.inc.php í sama faldara).
 * Returnerar fullan filstíg ella null, um onki eydnast.
 */
function lf_generate_pdf($data)
{
    // Royn at lata Dompdf inn, um tað ikki longu er tøkt
    if (!class_exists('Dompdf\\Dompdf')) {
        $dompdf_autoload = __DIR__ . '/../dompdf/autoload.inc.php';
        if (file_exists($dompdf_autoload)) {
            require_once $dompdf_autoload;
        }
    }

    if (!class_exists('Dompdf\\Dompdf')) {
        // Eingin PDF verður gjørd, um Dompdf ikki er tøkt
        return null;
    }

    // Tryggja, at vit hava tað, vit brúka
    $name           = $data['name'] ?? '';
    $birthdate      = $data['birthdate'] ?? '';
    $address        = $data['address'] ?? '';
    $city           = $data['city'] ?? '';
    $phone          = $data['phone'] ?? '';
    $email          = $data['email'] ?? '';
    $club           = $data['club'] ?? '';
    $date           = $data['date'] ?? '';
    $is_minor       = !empty($data['is_minor']);
    $guardian_name        = $data['guardian_name'] ?? '';
    $guardian_email       = $data['guardian_email'] ?? '';
    $guardian_phone       = $data['guardian_phone'] ?? '';
    $approved_by          = $data['approved_by'] ?? '';
    $guardian_approved_by = $data['guardian_approved_by'] ?? '';
    $fss_approved_by      = $data['fss_approved_by'] ?? '';
    $club_approved_date    = $data['club_approved_date'] ?? '';
    $guardian_approved_date = $data['guardian_approved_date'] ?? '';
    $fss_approved_date     = $data['fss_approved_date'] ?? '';

    // LOGO for PDF: Dompdf supports only raster (PNG/JPG), not SVG. Prefer local files
    // in assets/logos/ (fss.png, adf.png, isf.png) or lf_get_logo_urls_for_pdf().
    $logos = lf_get_pdf_logo_sources();
    $logo1 = $logos['fss'] ?? '';
    $logo2 = $logos['adf'] ?? '';
    $logo3 = $logos['isf'] ?? '';

    $html  = '<html><head><meta charset="UTF-8"><style>';
    $html .= 'body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; line-height: 1.25; }';
    $html .= 'h1 { font-size: 16px; margin: 0 0 2px 0; }';
    $html .= 'h2 { font-size: 12px; margin: 10px 0 6px 0; }';
    $html .= 'p { margin: 0 0 6px 0; }';
    $html .= 'table { width: 100%; border-collapse: collapse; margin-bottom: 6px; }';
    $html .= 'th, td { text-align: left; padding: 3px 5px; border-bottom: 1px solid #ddd; }';
    $html .= '.section { margin-bottom: 10px; }';
    $html .= '.pdf-header { text-align: center; margin-bottom: 10px; }';
    $html .= '.pdf-logo-table { width: 100%; margin-bottom: 4px; }';
    $html .= '.pdf-logo-table td { width: 33%; text-align: center; border-bottom: none; padding: 0 4px 2px 4px; }';
    $html .= '.pdf-logo-table img { max-height: 42px; max-width: 100%; }';
    $html .= '</style></head><body>';

    $html .= '<div class="pdf-header">';
    $html .= '<table class="pdf-logo-table"><tr>';
    $html .= '<td>' . (!empty($logo1) ? '<img src="' . htmlspecialchars($logo1, ENT_QUOTES, "UTF-8") . '" alt="">' : '') . '</td>';
    $html .= '<td>' . (!empty($logo2) ? '<img src="' . htmlspecialchars($logo2, ENT_QUOTES, "UTF-8") . '" alt="">' : '') . '</td>';
    $html .= '<td>' . (!empty($logo3) ? '<img src="' . htmlspecialchars($logo3, ENT_QUOTES, "UTF-8") . '" alt="">' : '') . '</td>';
    $html .= '</tr></table>';
    $html .= '<h1>Lyftiloyvisváttan</h1>';
    $html .= '</div>';
    $html .= '<div class="section">';
    $html .= '<table>';
    $html .= '<tr><th>Fulla navn á íðkara</th><td>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Føðingardagur</th><td>' . htmlspecialchars($birthdate, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Bústaður</th><td>' . htmlspecialchars($address, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Býur/bygd</th><td>' . htmlspecialchars($city, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Telefonnummar hjá íðkara</th><td>' . htmlspecialchars($phone, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Teldupostur hjá íðkara</th><td>' . htmlspecialchars($email, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Felag</th><td>' . htmlspecialchars($club, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '<tr><th>Dagur</th><td>' . htmlspecialchars($date, ENT_QUOTES, 'UTF-8') . '</td></tr>';
    $html .= '</table>';
    $html .= '</div>';

    if ($is_minor) {
        $html .= '<div class="section">';
        $html .= '<h2>Upplýsingar um verja (íðkari er undir 18 ár)</h2>';
        $html .= '<table>';
        $html .= '<tr><th>Navn á verja</th><td>' . htmlspecialchars($guardian_name, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><th>Teldupostur hjá verja</th><td>' . htmlspecialchars($guardian_email, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '<tr><th>Telefonnummar hjá verja</th><td>' . htmlspecialchars($guardian_phone, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        $html .= '</table>';
        $html .= '</div>';
    }

    $html .= '<div class="section">';
    $html .= '<h2>Dopingváttan</h2>';
    $html .= '<p style="font-size:10px; line-height:1.25; margin:0;">' . (function_exists('lf_get_doping_text') ? lf_get_doping_text() : '') . '</p>';
    $html .= '</div>';

    if (!empty($approved_by) || !empty($guardian_approved_by) || !empty($fss_approved_by)) {
        $html .= '<div class="section">';
        $html .= '<h2>Góðkenning</h2>';
        $html .= '<table>';
        if (!empty($approved_by)) {
            $html .= '<tr><th>Góðkent av (formanni/nevdarlimi)</th><td>' . htmlspecialchars($approved_by . ($club_approved_date !== '' ? ' (' . $club_approved_date . ')' : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if (!empty($guardian_approved_by)) {
            $html .= '<tr><th>Góðkent av verjanum</th><td>' . htmlspecialchars($guardian_approved_by . ($guardian_approved_date !== '' ? ' (' . $guardian_approved_date . ')' : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if (!empty($fss_approved_by)) {
            $html .= '<tr><th>Góðkent av FSS</th><td>' . htmlspecialchars($fss_approved_by . ($fss_approved_date !== '' ? ' (' . $fss_approved_date . ')' : ''), ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        $html .= '</table>';
        $html .= '</div>';
    }

    $html .= '</body></html>';

    // Finn eitt stað at goyma fílu
    $upload_dir = wp_upload_dir();
    if (empty($upload_dir['path']) || !is_dir($upload_dir['path'])) {
        return null;
    }

    // Filnavn: "<name> - <date>.pdf" (safe for filesystem)
    $date_for_filename = $date !== '' ? $date : date('Y-m-d');
    $filename_raw = trim($name) . ' - ' . $date_for_filename . '-' . substr(md5(microtime(true)), 0, 6) . '.pdf';
    $filename = sanitize_file_name($filename_raw);
    $filepath = trailingslashit($upload_dir['path']) . $filename;

    try {
        $dompdf = new Dompdf\Dompdf();

        // Loyv Dompdf at henta fílur (logo) yvir HTTP(S)
        $dompdf->set_option('isRemoteEnabled', true);

        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $output = $dompdf->output();
        file_put_contents($filepath, $output);
    } catch (Exception $e) {
        return null;
    }

    return $filepath;
}

function lf_admin_build_subject($data, $prefix = 'Lyftiloyvi') {
    $name = $data['name'] ?? '';
    $club = $data['club'] ?? '';

    $subject_parts = [];
    if ($name) $subject_parts[] = $name;
    if ($club) $subject_parts[] = '(' . $club . ')';

    $suffix = trim(implode(' ', $subject_parts));
    if ($suffix === '') return $prefix;
    return $prefix . ': ' . $suffix;
}

function lf_admin_resend_pdf_to_recipients($data, $recipients, $explanation) {
    $pdf_path = lf_generate_pdf($data);

    $attachments = [];
    if (!empty($pdf_path) && file_exists($pdf_path)) {
        $attachments[] = $pdf_path;
    }

    $subject = lf_admin_build_subject($data, 'Lyftiloyvi (sendt aftur)');

    $name      = $data['name'] ?? '';
    $club      = $data['club'] ?? '';
    $birthdate = $data['birthdate'] ?? '';
    $email     = $data['email'] ?? '';

    $body  = "Ein uppdaterað útgáva av lyftiloyvinum er send aftur.\n\n";
    if ($explanation !== '') {
        $body .= "Forklaring frá admin:\n" . $explanation . "\n\n";
    }
    $body .= "Umsókn:\n";
    $body .= "Navn: {$name}\n";
    $body .= "Felag: {$club}\n";
    $body .= "Føðingardagur: {$birthdate}\n";
    $body .= "Teldupostur: {$email}\n\n";
    $body .= "Sent frá: " . get_site_url() . "\n";

    $headers = [];
    if (!empty($email) && is_email($email)) {
        $headers[] = 'Reply-To: ' . $email;
    }

    $sent_any = false;
    foreach ($recipients as $to) {
        if (!$to || !is_email($to)) continue;
        $ok = wp_mail($to, $subject, $body, $headers, $attachments);
        if ($ok) $sent_any = true;
    }

    return [
        'sent_any' => $sent_any,
        'pdf_path' => $pdf_path,
    ];
}

