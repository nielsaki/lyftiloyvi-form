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

function lf_get_club_chair_emails() {
    return [
        'Burn Íðkarar'        => 'athletics@burn-athletics.fo',
        'FullPower Kúlulyft'  => 'lyftiloyvi@fss.fo',
        'Megingjørð'          => 'lyftiloyvi@fss.fo',
        'Styrkifelagið Stoyt' => 'stoyt@stoyt.fo',
        'Trúðheimur'          => 'rhjaltalin@hotmail.com',
        // TODO: update to the correct chair email
        'ÍF Tvørmegi'         => 'lyftiloyvi@fss.fo',
    ];
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
                    $chair_recipient = isset($club_chair_emails[$club]) ? $club_chair_emails[$club] : 'lyftiloyvi@fss.fo';

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

                    $fss_sent = wp_mail('lyftiloyvi@fss.fo', $fss_subject, $fss_body, $headers, $attachments);

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

    // Dopingváttan tekstblokk
    $output .= '<p class="lf-info-block"><small>
        Sum limur av einum felag í Føroya Styrkisambandi (FSS) vátti eg hervið, at eg teir seinastu 12 mánaðarnar havi yvirhildið og frameftir fari at yvirhalda tær til eina og hvørja tíð galdandi reglurnar ásettar av Ítróttasambandi Føroya (ÍSF) og altjóða styrkiítróttarsambondunum, sum eru viðkomandi. Hesi eru International Weightlifting Federation (IWF), International Powerlifting Federation (IPF) og World Kettlebell Sport Federation (WKSF).<br><br>
        Eg lati meg kanna til allar kanningar, ið FSS og ÍSF áleggja, herundir kanningar hjá teimum altjóða sambondunum. Hetta ger seg bæði galdandi í og uttanfyri kapping. Ein noktan at lata pissiroynd ella aðrar líknandi royndir verður roknað sum ein noktan at lata seg kanna fyri doping í mun til reglurnar hjá ÍSF.<br><br>
        Um eg innanfyri hetta tíðarskeiðið ella seinni loyvistíðarskeið verði funnin sekur í at bróta omanfyri nevndu dopingásetingar, forplikti eg meg til at rinda FSS fyri tær útreiðslur, ið FSS møguliga hevur havt av mær ísv t.d. uttanlandsferðir, venjingarlegur, stuðul til útgerð og annað mangt seinastu 12 mánaðarnar áðrenn brotið á dopingarreglurnar. Eg skilji eisini, at eitt brot á dopingreglurnar hevur við sær, at tey føroysku metini, sum eg seti, eftir at hava skrivað undir upp á hetta skjalið, verða strikað.<br><br>
        Ein og hvør ósemja ímillum meg og FSS um tulking av omanfyri nevnda, um støddina av gjalding av omanfyri nevndu upphæddunum, herundir sekt, ella í síni heild tulking av spurningum í mun til hetta skjal, kann verða løgd fyri gerðarætt í mun til tær reglur, sum til ta tíð eru galdandi fyri gerðarætt. FSS og eg útnevna hvør ein gerðarættarlim innan 14 dagar eftir móttøku av fráboðan um, at hin parturin hevur valt gerðarættarlim. Limirnir velja ein uppmann, sum skal vera púra ótengdur at báðum pørtum. Partarnir rinda hvør sín part av útreiðslunum til "sín" gerðarættarlim. FSS rindar samsýning til uppmannin og møguligar útreiðslur ísv. gerðarættarviðgerðina.
    </small></p>';

    $output .= '<p>
        <label>
            <input type="checkbox" name="lf_consent" value="1"' . ($consent === '1' ? ' checked="checked"' : '') . ' required>
            Eg havi lisið og góðtikið lyftiloyvisváttanina, og góðtaki at mínar persónsupplýsingar verða viðgjørdar í hesum sambandi.
        </label>
    </p>';
    $output .= '<p class="lf-info-block"><small>
        <strong>Anti Doping Danmark (ADD) skeið:</strong><br>
        Um íðkarin er 18 ár ella eldri, so er neyðugt at fullfíggja ADD-skeiðið <em>"Antidoping 1 - for idrætsudøvere"</em>, sum Anti Doping Danmark hevur gjørt. Skeiðið kann gerast á hesi síðuni: <a href="https://uddannelse.antidoping.dk/" target="_blank" rel="noopener">https://uddannelse.antidoping.dk/</a><br><br>
        ADD-skeiðið gongur út eftir tvey ár, tó er bert neyðugt at gera skeiðið einaferð.<br><br>
        Er hetta ikki fullfíggja, verður lyftiloyvi ikki góðtikið av FSS, og viðkomandi hevur ikki loyvið at luttaka til kappingar.<br><br>
        Er íðkarin undir 18 ár, er einki krav um at lúka skeiðið. Tó viðmæla vit altíð, at íðkarin og verjin hava hetta skeiðið hóast alt.<br><br>
        Um íðkarin skal við landsliðinum, so er krav, at hann/hon hevur eitt ikki-útgingið skeið.
    </small></p>';

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
            var m = val.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
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

/**
 * Shortcode [lyftiloyvi_form]
 */
function lf_register_shortcode() {
    add_shortcode('lyftiloyvi_form', 'lf_render_form');
}
add_action('init', 'lf_register_shortcode');

/**
 * Ger eina PDF-fílu við upplýsingum úr lyftiloyvisformin og returnerar stígin.
 * Krevur, at Dompdf er tøkt (t.d. via dompdf/autoload.inc.php í sama faldara).
 * Returnerar fullan filstíg ella null, um onki eydnast.
 */
function lf_generate_pdf($data)
{
    // Royn at lata Dompdf inn, um tað ikki longu er tøkt
    if (!class_exists('Dompdf\\Dompdf')) {
        $dompdf_autoload = __DIR__ . '/dompdf/autoload.inc.php';
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
    // LOGO-URLS – BROYT HESAR TIL RÆTTAR LOGO-ADRESSUR
    // Dømi: upload logo í Media Library og kopier URL inn her.
    $logo1 = 'https://fss.fo/wp-content/uploads/2025/12/fss-logo.svg';
    $logo2 = 'https://fss.fo/wp-content/uploads/2025/12/adf-logo.svg';
    $logo3 = 'https://fss.fo/wp-content/uploads/2025/12/isf-logo.png';

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
    $html .= '<p style="font-size:10px; line-height:1.25; margin:0;">';
    $html .= 'Sum limur av einum felag í Føroya Styrkisambandi (FSS) vátti eg hervið, at eg teir seinastu 12 mánaðarnar havi yvirhildið og frameftir fari at yvirhalda tær til eina og hvørja tíð galdandi reglurnar ásettar av Ítróttasambandi Føroya (ÍSF) og teimum altjóða styrkiítróttarsambondunum, sum eru viðkomandi fyri mína ella mínar ítróttagrein/ir.<br><br>';
    $html .= 'Eg lati meg kanna til allar kanningar, ið FSS og ÍSF áleggja, herundir kanningar hjá teimum altjóða sambondunum. Hetta ger seg bæði galdandi í og uttanfyri kapping. Ein noktan at lata pissiroynd ella aðrar líknandi royndir verður roknað sum ein noktan at lata seg kanna fyri doping í mun til reglurnar hjá ÍSF.<br><br>';
    $html .= 'Um eg innanfyri hetta tíðarskeiðið ella seinni loyvistíðarskeið verði funnin sekur í at bróta omanfyri nevndu dopingásetingar, forplikti eg meg til at rinda FSS fyri tær útreiðslur, ið FSS møguliga hevur havt av mær í sambandi við til dømis uttanlandsferðir, venjingarlegur, stuðul til útgerð og annað mangt seinastu 12 mánaðarnar áðrenn brotið á dopingarreglurnar. Eg skilji eisini, at eitt brot á dopingreglurnar hevur við sær, at tey føroysku metini, sum eg seti eftir at hava skrivað undir upp á hetta skjalið, verða strikað.<br><br>';
    $html .= 'Ein og hvør ósemja ímillum meg og FSS um tulking av omanfyri nevnda, um støddina av gjalding av omanfyri nevndu upphæddunum, herundir sekt, ella í síni heild tulking av spurningum í mun til hetta skjal, kann verða løgd fyri gerðarætt í mun til tær reglur, sum til ta tíð eru galdandi fyri gerðarætt. FSS og eg útnevna hvør ein gerðarættarlim innan 14 dagar eftir móttøku av fráboðan um, at hin parturin hevur valt gerðarættarlim. Limirnir velja ein uppmann, sum skal vera púra ótengdur at báðum pørtum. Partarnir rinda hvør sín part av útreiðslunum til sín gerðarættarlim. FSS rindar samsýning til uppmannin og møguligar útreiðslur í sambandi við gerðarættarviðgerðina.';
    $html .= '</p>';
    $html .= '</div>';

    if (!empty($approved_by) || !empty($guardian_approved_by) || !empty($fss_approved_by)) {
        $html .= '<div class="section">';
        $html .= '<h2>Góðkenning</h2>';
        $html .= '<table>';
        if (!empty($approved_by)) {
            $html .= '<tr><th>Góðkent av (formanni/nevdarlimi)</th><td>' . htmlspecialchars($approved_by, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if (!empty($guardian_approved_by)) {
            $html .= '<tr><th>Góðkent av verjanum</th><td>' . htmlspecialchars($guardian_approved_by, ENT_QUOTES, 'UTF-8') . '</td></tr>';
        }
        if (!empty($fss_approved_by)) {
            $html .= '<tr><th>Góðkent av FSS</th><td>' . htmlspecialchars($fss_approved_by, ENT_QUOTES, 'UTF-8') . '</td></tr>';
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
    $filename_raw = trim($name) . ' - ' . $date_for_filename . '.pdf';
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

/**
 * Simple styling
 */
function lf_enqueue_styles()
{
    $css = '
    .lf-form {
        max-width: 900px;
        margin: 2rem auto 3rem;
        padding: 1.75rem 2.5rem 2.5rem;
        background: #ffffff;
        border-radius: 10px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.06);
        box-sizing: border-box;
    }
    .lf-form-title {
        margin: 0 0 1rem;
        font-size: 1.4rem;
        font-weight: 700;
        border-bottom: 1px solid #e5e5e5;
        padding-bottom: 0.5rem;
    }
    .lf-form p {
        margin: 0 0 1rem;
    }
   .lf-form label {
        display: block;
        font-weight: 600;
        margin-bottom: 0.25rem;
    }
    .lf-info-block {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 0.75rem 1rem;
        border: 1px solid #e2e4e7;
        font-size: 13px;
        line-height: 1.5;
    }
    .lf-guardian-block {
        margin-top: 1rem;
        padding: 0.75rem 1rem;
        border-radius: 6px;
        background: #fdfdfd;
        border: 1px dashed #e2e4e7;
    }

    .lf-row {
        display: flex;
        flex-wrap: wrap;
        gap: 1.5rem;
    }
    .lf-col {
        flex: 1 1 0;
        min-width: 0;
    }
    .lf-col-center {
        flex: 0 0 auto;
        min-width: 180px;
        text-align: center;
    }
    .lf-col-center .lf-inline-options {
        justify-content: center;
    }
    .lf-inline-options {
        display: flex;
        align-items: center;
        gap: 1.5rem;
        margin-top: 0.25rem;
    }
    .lf-inline-options .lf-radio-option {
        display: inline-flex;
        align-items: center;
        font-weight: 400;
        margin: 0;
    }
    .lf-form input[type="text"],
    .lf-form input[type="email"],
    .lf-form input[type="date"],
    .lf-form select {
        width: 100%;
        padding: 0.5em 0.6em;
        border-radius: 4px;
        border: 1px solid #ccd0d4;
        box-sizing: border-box;
        font-size: 14px;
        font-family: inherit;
        background-color: #fff;
    }
    .lf-form input[type="text"]:focus,
    .lf-form input[type="email"]:focus,
    .lf-form input[type="date"]:focus,
    .lf-form select:focus {
        outline: none;
        border-color: #007cba;
        box-shadow: 0 0 0 1px #007cba33;
    }
    .lf-form select:disabled {
        background-color: #f3f4f5;
        color: #888;
        cursor: not-allowed;
    }
    .lf-form button[type="submit"] {
        display: inline-block;
        padding: 0.7rem 1.6rem;
        border-radius: 4px;
        border: none;
        background: #007cba;
        color: #ffffff;
        font-size: 14px;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease, transform 0.05s ease, box-shadow 0.15s ease;
    }
    .lf-form button[type="submit"]:hover {
        background: #006ba1;
        box-shadow: 0 2px 6px rgba(0,0,0,0.15);
    }
    .lf-form button[type="submit"]:active {
        transform: translateY(1px);
        box-shadow: none;
    }
    .lf-form input[type="checkbox"],
    .lf-form input[type="radio"] {
        width: auto;
        margin-right: 0.4rem;
    }
    .lf-form .lf-hp {
        display: none;
    }
    .lf-success {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #4caf50;
        background: #e8f5e9;
        color: #256029;
        max-width: 900px;
    }
    .lf-error {
        padding: 0.6em 0.9em;
        margin: 1rem auto;
        border-radius: 4px;
        border: 1px solid #f44336;
        background: #ffebee;
        color: #b71c1c;
        max-width: 900px;
    }
    .lf-error ul {
        margin: 0.25rem 0 0;
        padding-left: 1.2rem;
    }
    .lf-error li {
        margin: 0.15rem 0;
    }
    @media (max-width: 600px) {
        .lf-form {
            margin: 1.5rem 1rem 2.5rem;
            padding: 1.4rem 1.4rem 2rem;
        }
        .lf-row {
            flex-direction: column;
        }
    }
    ';
    wp_add_inline_style('wp-block-library', $css);
}
add_action('wp_enqueue_scripts', 'lf_enqueue_styles');

/**
 * Create custom table for pending lyftiloyvi requests (2-step flow).
 */
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

/**
 * Ensure custom table exists on normal page loads (in case activation hook did not run).
 */
function lf_ensure_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    // Check if table exists; if not, create it.
    $exists = $wpdb->get_var( $wpdb->prepare(
        "SHOW TABLES LIKE %s",
        $table_name
    ) );

    if ($exists !== $table_name) {
        lf_install_table();
    }
}
add_action('plugins_loaded', 'lf_ensure_table_exists');

/**
 * Ensure the custom table has the required columns (lightweight migration for existing installs).
 */
function lf_ensure_table_schema() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_lyftiloyvi_requests';

    $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_name));
    if ($exists !== $table_name) {
        return; // lf_ensure_table_exists() will create it
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

    // Best-effort indexes
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

    // valfrit: marker at den venter på FSS
    $wpdb->update($table_name, ['status' => 'pending_fss'], ['id' => $row->id], ['%s'], ['%d']);

    return wp_mail('lyftiloyvi@fss.fo', 'Endalig góðkenning krevst (FSS): ' . $subject, $body);
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

    $recipients = ['lyftiloyvi@fss.fo'];
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
        $body .= "- Verjin (" . $guardian_email . ")\n";
    }
    $body .= "\n";

    $body .= "Teldupostur hjá lyftara: {$email}\n";
    $body .= "\nSent frá: " . get_site_url() . "\n";

    $headers = [];
    if (!empty($email)) {
        $headers[] = 'Reply-To: ' . $email;
    }

    // Final recipient: altíð til FSS
    $recipient = 'lyftiloyvi@fss.fo';

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
        // Vís eitt lítið form, sum biður um navn á góðkennarum
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
            wp_die('<p>Umsóknin er noktað. Allir partar hava fingið teldupost um tað.</p>', 'Lyftiloyvi', ['response' => 200]);
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
                    $recipient_list[] = 'lyftiloyvi@fss.fo';
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