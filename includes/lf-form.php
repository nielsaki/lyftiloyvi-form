<?php

if (!defined('ABSPATH')) {
    exit;
}

function lf_render_form()
{
    $output = '';

    $clubs = lf_get_clubs();

    // Formans-email hjá feløgunum (set hesar til røttu adressurnar)
    $club_chair_emails = lf_get_club_chair_emails();

    // Init values
    $name = '';
    $email = '';
    $birthdate = '';
    $address = '';
    $city = '';
    $phone = '';
    $club = '';
    $date = '';
    $honeypot = '';
    $consent = '';
    $guardian_name = '';
    $guardian_email = '';
    $guardian_phone = '';
    $age = null;
    $is_minor = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['lf_form_submitted'])) {

        if (!isset($_POST['lf_nonce']) || !wp_verify_nonce($_POST['lf_nonce'], 'lf_submit')) {
            $output .= '<div class="lf-error">Trygdarkanning miseydnaðist. Royn aftur.</div>';
        } else {

            // Sanitize
            $honeypot = sanitize_text_field($_POST['lf_hp'] ?? '');
            $name = sanitize_text_field($_POST['lf_name'] ?? '');
            $email = sanitize_email($_POST['lf_email'] ?? '');
            $birthdate = sanitize_text_field($_POST['lf_birthdate'] ?? '');
            $address = sanitize_text_field($_POST['lf_address'] ?? '');
            // $postcode removed
            $city = sanitize_text_field($_POST['lf_city'] ?? '');
            $phone = sanitize_text_field($_POST['lf_phone'] ?? '');
            $guardian_name = sanitize_text_field($_POST['lf_guardian_name'] ?? '');
            $guardian_email = sanitize_email($_POST['lf_guardian_email'] ?? '');
            $guardian_phone = sanitize_text_field($_POST['lf_guardian_phone'] ?? '');
            $club = sanitize_text_field($_POST['lf_club'] ?? '');
            // Dagur verður settur til dags dato (læst í forminum)
            $date = date('Y-m-d');
            $consent = isset($_POST['lf_consent']) ? '1' : '';

            // Honeypot
            if (!empty($honeypot)) {
                $output .= '<div class="lf-success">Takk! Lyftiloyvið er móttikið.</div>';
                return $output; // 🟢 Stopper spam-submit
            } else {

                $errors = [];

                if (empty($name)) {
                    $errors[] = 'Vinaliga skriva fulla navn á íðkara.';
                } elseif (!preg_match('/\S+\s+\S+/', $name)) {
                    $errors[] = 'Vinaliga skriva fulla navn (for-, millum- og eftirnavn).';
                }
                if (empty($birthdate)) {
                    $errors[] = 'Vinaliga vel føðingardag.';
                }
                elseif (!preg_match('/^\d{2}\.\d{2}\.\d{4}$/', $birthdate)) {
                    $errors[] = 'Føðingardagur skal vera í forminum dd.mm.áááá.';
                } else {
                    // Rokna aldur út frá føðingardegi
                    $dob = DateTime::createFromFormat('d.m.Y', $birthdate);
                    if ($dob instanceof DateTime) {
                        $today_str  = current_time('Y-m-d');
                        $todayDate  = DateTime::createFromFormat('Y-m-d', $today_str);

                        if ($todayDate instanceof DateTime) {
                            if ($dob > $todayDate) {
                                $errors[] = 'Føðingardagur kann ikki vera í framtíðini.';
                            } else {
                                $age = $dob->diff($todayDate)->y;
                                if ($age > 100) {
                                    $errors[] = 'Vinaliga kanna, um føðingardagurin er skrivaður rætt.';
                                }
                                $is_minor = ($age < 18);
                            }
                        }
                    }
                }
                if (empty($email)) {
                    $errors[] = 'Vinaliga skriva teldupost hjá íðkara.';
                } elseif (!is_email($email)) {
                    $errors[] = 'Teldupostur er ikki í rættum sniði.';
                }
                if (empty($address)) {
                    $errors[] = 'Vinaliga skriva bústað.';
                }
                if (empty($city)) {
                    $errors[] = 'Vinaliga skriva bý.';
                }
                if (empty($phone)) {
                    $errors[] = 'Vinaliga skriva telefonnummar hjá íðkara.';
                } elseif (!preg_match('/^[0-9 +]+$/', $phone)) {
                    $errors[] = 'Telefonnummar má bara innihalda tøl, millumrúm og +.';
                }
                // Verja skal fyllast út, um íðkarin er undir 18 ár
                if ($is_minor) {
                    if (empty($guardian_name)) {
                        $errors[] = 'Um íðkarin er yngri enn 18 ár, skal navn á verja fyllast út.';
                    }
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

                if (empty($consent)) {
                    $errors[] = 'Vinaliga vátta, at tú góðtekur lyftiloyvisváttanina omanfyri.';
                }

                if (!empty($errors)) {
                    $output .= '<div class="lf-error"><ul>';
                    foreach ($errors as $e) {
                        $output .= '<li>' . esc_html($e) . '</li>';
                    }
                    $output .= '</ul></div>';
                } else {
                    // Build email subject and basic body (to be used later in approval step)
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

                    // Save submission data for approval step
                    $submission_data = [
                        'name'           => $name,
                        'birthdate'      => $birthdate,
                        'address'        => $address,
                        'city'           => $city,
                        'phone'          => $phone,
                        'email'          => $email,
                        'club'           => $club,
                        'date'           => $date,
                        'is_minor'       => $is_minor,
                        'guardian_name'  => $guardian_name,
                        'guardian_email' => $guardian_email,
                        'guardian_phone' => $guardian_phone,
                    ];

                    // (PDF verður gjørd seinni, ikki her)

                    // Goym í egnari tabell sum "pending"
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

                    $token = wp_generate_password(32, false, false);
                    $guardian_token = ($is_minor && !empty($guardian_email)) ? wp_generate_password(32, false, false) : '';
                    $fss_token = wp_generate_password(32, false, false);

                    $wpdb->insert(
                        $table_name,
                        [
                            'token'          => $token,
                            'guardian_token' => $guardian_token,
                            'fss_token'      => $fss_token,
                            'data'           => maybe_serialize($submission_data),
                            'pdf_path'       => '',
                            'status'         => 'pending',
                            'created_at'     => current_time('mysql', 1),
                        ],
                        [
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                            '%s',
                        ]
                    );

                    if ($wpdb->last_error) {
                        error_log('Lyftiloyvi DB insert error: ' . $wpdb->last_error);
                        $output .= '<div class="lf-error">Eitt mistak hentist við at goyma umsóknina. Vinarliga royn aftur ella set teg í samband við FSS.</div>';
                        return $output;
                    }

                    $attachments = [];

                    // Bygg teldupost til formannin (góðkenning)
                    $approval_link = add_query_arg(
                        'lf_approve',
                        rawurlencode($token),
                        get_site_url()
                    );

                    $fss_approval_link = add_query_arg(
                        'lf_fss_approve',
                        rawurlencode($fss_token),
                        get_site_url()
                    );

                    $chair_subject = 'Góðkenning krevst: ' . $subject;

                    $chair_body  = "Ein nýggj umsókn um lyftiloyvi er móttikin og bíðar eftir góðkenning frá formanni.\n\n";
                    $chair_body .= "Fulla navn á íðkara: {$name}\n";
                    $chair_body .= "Felag: {$club}\n";
                    $chair_body .= "Føðingardagur: {$birthdate}\n";
                    $chair_body .= "Teldupostur hjá íðkara: {$email}\n";
                    $chair_body .= "\nFyri at góðkenna umsóknina og senda hana víðari til Føroya Styrkisamband, klikk her:\n";
                    $chair_body .= $approval_link . "\n\n";
                    $chair_body .= "Tá felagið (og verji, um tað er neyðugt) hava góðkent, verður umsóknin send til FSS til endaliga góðkenning. Eftir tað fáa allir partar teldupost við endaliga PDF-skjalinum.\n";

                    $headers = [];
                    if (!empty($email)) {
                        $headers[] = 'Reply-To: ' . $email;
                    }

                    // HER: Teldupostur til nevnd (stig 1)
                    // Vel móttakara út frá felagnum. Um ongin er skrásettur, fell aftur til lyftiloyvi@fss.fo
                    $chair_recipient = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : lf_get_fss_email();

                    $sent = wp_mail($chair_recipient, $chair_subject, $chair_body, $headers, $attachments);

                    $fss_subject = 'Góðkenning krevst (FSS): ' . $subject;
                    $fss_body  = "Ein nýggj umsókn um lyftiloyvi er móttikin og krevur góðkenning frá FSS.\n\n";
                    $fss_body .= "Fulla navn á íðkara: {$name}\n";
                    $fss_body .= "Felag: {$club}\n";
                    $fss_body .= "Føðingardagur: {$birthdate}\n";
                    $fss_body .= "Teldupostur hjá íðkara: {$email}\n\n";
                    $fss_body .= "Fyri at síggja umsóknina og góðkenna (FSS), klikk her:\n";
                    $fss_body .= $fss_approval_link . "\n\n";
                    $fss_body .= "Tá allir kravdir partar hava góðkent, verða endaligu teldupostarnir sendir við PDF-skjalinum.\n";

                    $fss_sent = wp_mail(lf_get_fss_email(), $fss_subject, $fss_body, $headers, $attachments);

                    if ($sent) {
                        // Um íðkarin er undir 18 ár, send eisini serstakan góðkenningar-teldupost til verju
                        if ($is_minor && !empty($guardian_email) && !empty($guardian_token)) {
                            $guardian_approval_link = add_query_arg(
                                'lf_guardian_approve',
                                rawurlencode($guardian_token),
                                get_site_url()
                            );

                            $guardian_approve_subject = 'Góðkenning krevst (verji): ' . $subject;
                            $guardian_approve_body  = "Tú ert skrásettur sum verji hjá {$name}.\n\n";
                            $guardian_approve_body .= "Ein umsókn um lyftiloyvi er send inn og krevur tína góðkenning sum verji.\n\n";
                            $guardian_approve_body .= "Fyri at lesa váttanina og góðkenna hana, klikk á hesa leinkju:\n";
                            $guardian_approve_body .= $guardian_approval_link . "\n\n";
                            $guardian_approve_body .= "Tá tú hevur góðkent, verður umsóknin saman við góðkenningini send til Føroya Styrkisamband.\n";

                            wp_mail($guardian_email, $guardian_approve_subject, $guardian_approve_body);
                        }

                        $output .= '<div class="lf-success">Takk! Umsóknin er móttikin og er send til felagið til góðkenningar. Tá felagið (og verji, um tað er neyðugt) hava góðkent, verður hon send til Føroya Styrkisamband til endaliga góðkenning. Eftir tað fáa allir partar teldupost við endaliga PDF-skjalinum.</div>';

                        // Tøma felti eftir væl lukkaða innsending
                        $name = $email = $birthdate = $address = $city = $phone = $club = $date = $consent = $guardian_name = $guardian_email = $guardian_phone = '';
                    } else {
                        $output .= '<div class="lf-error">Eitt mistak hentist við at senda teldupost til formannin. Vinarliga royn aftur ella set teg í samband við felagið.</div>';
                    }
                }
            }
        }
    }

    // Form start
    $output .= '<form method="post" class="lf-form">';
    $output .= '<h2 class="lf-form-title">Váttan í samband við lyftiloyvi</h2>';

    $output .= wp_nonce_field('lf_submit', 'lf_nonce', true, false);
    $output .= '<input type="hidden" name="lf_form_submitted" value="1">';

    // Maybe you show the PDF text above in the page – selve formularen her:
    $output .= '<p><small>Við at fylla lyftiloyvi út, váttar tú at tú heldur galdandi reglur hjá ÍSF og teimum altjóða sambondunum, sum Føroya Styrkisamband virkar undir, umframt kanningar fyri doping sambært hesum reglum.</small></p>';
    $output .= '<p><small>Um tú skiftur felag, er neyðugt at fylla nýtt lyftiloyvið út.</small></p>';

    $output .= '<div class="lf-row">
        <div class="lf-col">
            <p>
                <label>Fulla navn á íðkara *<br>
                    <input type="text" name="lf_name" required value="' . esc_attr($name) . '" placeholder="for-, millum- og eftirnavn">
                </label>
            </p>
        </div>
        <div class="lf-col">
            <p>
                <label>Føðingardagur *<br>
                    <input type="text" name="lf_birthdate" required value="' . esc_attr($birthdate) . '" placeholder="dd.mm.áááá" pattern="\\d{2}\\.\\d{2}\\.\\d{4}">
                </label>
                <small>Skriva føðingardag sum dd.mm.áááá – punktum verða sett sjálvvirkandi.</small>
            </p>
        </div>
    </div>';

    $output .= '<div class="lf-row">
        <div class="lf-col">
            <p>
                <label>Teldupostur hjá íðkara *<br>
                    <input type="email" name="lf_email" required value="' . esc_attr($email) . '">
                </label>
            </p>
        </div>
        <div class="lf-col">
            <p>
                <label>Telefonnummar hjá íðkara *<br>
                    <input type="text" name="lf_phone" required value="' . esc_attr($phone) . '" pattern="[0-9+\s]+" title="Telefonnummar má bara innihalda tøl, millumrúm og +">
                </label>
            </p>
        </div>
    </div>';

    $output .= '<div class="lf-row">
        <div class="lf-col">
            <p>
                <label>Bústaður hjá íðkara *<br>
                    <input type="text" name="lf_address" required value="' . esc_attr($address) . '">
                </label>
            </p>
        </div>
        <div class="lf-col">
            <p>
                <label>Býur/bygd *<br>
                    <input type="text" name="lf_city" required value="' . esc_attr($city) . '">
                </label>
            </p>
        </div>
    </div>';

    // Upplýsingar um verja, um íðkarin er undir 18 ár
    $output .= '<div class="lf-guardian-block">
        <p><strong>Um íðkarin er yngri enn 18 ár:</strong></p>
        <div class="lf-row">
            <div class="lf-col">
                <p>
                    <label>Navn á verja<br>
                        <input type="text" name="lf_guardian_name" value="' . esc_attr($guardian_name) . '">
                    </label>
                </p>
            </div>
            <div class="lf-col">
                <p>
                    <label>Telefonnummar hjá verja<br>
                        <input type="text" name="lf_guardian_phone" value="' . esc_attr($guardian_phone) . '" pattern="[0-9+\s]+" title="Telefonnummar má bara innihalda tøl, millumrúm og +">
                    </label>
                </p>
            </div>
        </div>
        <p>
            <label>Teldupostur hjá verja<br>
                <input type="email" name="lf_guardian_email" value="' . esc_attr($guardian_email) . '">
            </label>
        </p>
    </div>';

    // Combined Felag only
    $output .= '<div class="lf-row">
        <div class="lf-col">
            <p>
                <label>Felag *<br>
                    <select name="lf_club" required>
                        <option value="">Vel felag</option>';
    foreach ($clubs as $c) {
        $selected = ($club === $c) ? ' selected="selected"' : '';
        $output .= '<option value="' . esc_attr($c) . '"' . $selected . '>' . esc_html($c) . '</option>';
    }
    $output .= '        </select>
                </label>
            </p>
        </div>
    </div>';

    // Dopingváttan tekstblokk (felags tekst úr config)
    $output .= '<p class="lf-info-block"><small>' . lf_get_doping_text() . '</small></p>';

    $output .= '<p>
        <label>
            <input type="checkbox" name="lf_consent" value="1"' . ($consent === '1' ? ' checked="checked"' : '') . ' required>
            Eg havi lisið og góðtikið lyftiloyvisváttanina, og góðtaki at mínar persónsupplýsingar verða viðgjørdar í hesum sambandi.
        </label>
    </p>';

    // ADD-tekstblokk (úr config)
    $output .= '<p class="lf-info-block">' . lf_get_add_block_html() . '</p>';

    // Honeypot
    $output .= '<p class="lf-hp" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;">
        <label>Ikki fyll hetta út<br>
            <input type="text" name="lf_hp" tabindex="-1" autocomplete="off">
        </label>
    </p>';

    $output .= '<p>
        <button type="submit">Lat lyftiloyvi inn</button>
    </p>';

    $output .= '</form>';

    $output .= '<script>
    document.addEventListener("DOMContentLoaded", function() {
        var form = document.querySelector(".lf-form");
        if (!form) return;

        // Føðingardagur auto-format
        var bInput = form.querySelector("input[name=\"lf_birthdate\"]");
        if (bInput) {
            bInput.addEventListener("input", function() {
                var digits = this.value.replace(/\D/g, "").slice(0, 8);
                var parts = [];
                if (digits.length > 0) {
                    parts.push(digits.substring(0, Math.min(2, digits.length)));
                }
                if (digits.length >= 3) {
                    parts.push(digits.substring(2, Math.min(4, digits.length)));
                }
                if (digits.length >= 5) {
                    parts.push(digits.substring(4, 8));
                }
                this.value = parts.join(".");
                updateGuardianBlock();
            });
        }

        var guardianBlock = form.querySelector(".lf-guardian-block");

        function updateGuardianBlock() {
            if (!guardianBlock || !bInput) return;
            var val = bInput.value.trim();
            var m = val.match(/^(\\d{2})\\.(\\d{2})\\.(\\d{4})$/);
            if (!m) {
                guardianBlock.style.display = "none";
                return;
            }
            var d = parseInt(m[1], 10),
                mo = parseInt(m[2], 10) - 1,
                y = parseInt(m[3], 10);
            var dob = new Date(y, mo, d);
            if (isNaN(dob.getTime())) {
                guardianBlock.style.display = "none";
                return;
            }
            var today = new Date();
            var age = today.getFullYear() - y;
            var mDiff = today.getMonth() - mo;
            if (mDiff < 0 || (mDiff === 0 && today.getDate() < d)) {
                age--;
            }
            if (age < 18) {
                guardianBlock.style.display = "block";
            } else {
                guardianBlock.style.display = "none";
                // Reinsa verju-felti, tá íðkarin er 18+ (valfrítt)
                var gInputs = guardianBlock.querySelectorAll("input");
                for (var i = 0; i < gInputs.length; i++) {
                    gInputs[i].value = "";
                }
            }
        }

        if (guardianBlock) {
            guardianBlock.style.display = "none";
        }
        // Uppdatera guardian-block on load (um føðingardagur evt. er settur frammanundan)
        updateGuardianBlock();
    });
    </script>';

    return $output;
}

function lf_register_shortcode() {
    add_shortcode('lyftiloyvi_form', 'lf_render_form');
}
add_action('init', 'lf_register_shortcode');

