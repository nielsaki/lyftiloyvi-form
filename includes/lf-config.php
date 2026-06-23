<?php

if (!defined('ABSPATH')) {
    exit;
}

function lf_get_fss_email() {
    return 'lyftiloyvi@fss.fo';
}

/**
 * Logo URLs for the website (can be SVG).
 */
function lf_get_logo_urls() {
    return [
        'fss' => 'https://fss.fo/wp-content/uploads/2025/12/fss-logo.svg',
        'adf' => 'https://fss.fo/wp-content/uploads/2025/12/adf-logo.svg',
        'isf' => 'https://fss.fo/wp-content/uploads/2025/12/isf-logo.png',
    ];
}

/**
 * Logo URLs for PDF only. Dompdf does NOT support SVG – use PNG or JPG.
 * Default: same as lf_get_logo_urls() but .svg → .png for fss/adf.
 * Upload fss-logo.png and adf-logo.png to the same folder on fss.fo, or
 * override this in a child theme / mu-plugin, or place PNGs in plugin folder assets/logos/.
 */
function lf_get_logo_urls_for_pdf() {
    $urls = lf_get_logo_urls();
    foreach (['fss', 'adf'] as $key) {
        if (isset($urls[$key]) && strtolower(substr($urls[$key], -4)) === '.svg') {
            $urls[$key] = preg_replace('/\.svg$/i', '.png', $urls[$key]);
        }
    }
    return $urls;
}

/**
 * Dopingváttan tekst (same inhoud brúkt í form og PDF).
 */
function lf_get_doping_text() {
    return 'Sum limur í einum felag undir Føroya Styrkisamband (FSS) vátti eg, at eg havi yvirhildið og frameftir fari at yvirhalda galdandi anti-doping reglur hjá ÍSF og teimum viðkomandi altjóða sambondunum, sum FSS er limur í.<br><br>'
        . 'Eg játti at lata meg kanna fyri doping, bæði í og uttanfyri kapping. Noktan at lata urin- ella blóðroynd verður roknað sum brot á anti-doping reglunar.<br><br>'
        . 'Verð eg funnin sekur í broti á anti-doping reglunar, játti eg at endurrinda FSS allar útreiðslur, sum sambandið hevur havt av mær seinastu 12 mánaðarnar undan brotinum, herundir útreiðslur til ferðing, venjingarlegur, útgerð, stuðul og annað. Somuledis verða føroysk met, sum eg havi sett eftir undirskrift av hesum skjali, strikað.<br><br>'
        . 'Ósemjur millum meg og FSS um tulking alla fremjan av hesum skjali, herundir spurningar um endurgjald og aðar skyldur sambært skjalinum, kunnu leggjast fyri gerðarætt sambært teimum reglum, sum tá eru galdandi. Hvør partur útnevnir ein gerðarættarlim innan 14 dagar eftir fráboðan frá hinum partinum. Gerðarættarlimirnir velja síðani ein óheftan uppmann. Hvør partur rindar útreiðslurnar til sín gerðarættarlim, meðan FSS rindar samsýning og møguligar útreiðslur hjá uppmanninum og í samband við gerðarættarviðgerðina.';
}

/**
 * ADD-tekstblokk (HTML), brúkt í forminum.
 */
function lf_get_add_block_html() {
    return '<small>
        <strong>Umboðan fyri Føroyar:</strong><br>
        Um íðkarin skal umboða Føroyar ella Merkið, til eina kapping, so er krav at hava eitt virkið og fullfíggja <em>"Antidoping 1 - for idrætsudøvere"</em> skeið. Skeiðið kann gerast á hesi síðuni: <a href="https://uddannelse.antidoping.dk/" target="_blank" rel="noopener">https://uddannelse.antidoping.dk/</a>. Skal íðkarin eisini skráseta whereabouts, so er eisini krav at hava eitt virkið og fullfíggja "WHEREABOUTS - EN GUIDE FOR ATLETER" skeið.<br><br>
        ADD-skeiðið gongur út eftir tvey ár.<br><br>
        Er hetta ikki fullfíggja, sleppur íðkarin ikki at luttaka til kappingar umboðandi fyri Føroyar, ella undir Merkiðnum.<br><br>
    </small>';
}

