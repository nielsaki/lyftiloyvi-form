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
    return 'Sum limur av einum felag í Føroya Styrkisambandi (FSS) vátti eg hervið, at eg teir seinastu 12 mánaðarnar havi yvirhildið og frameftir fari at yvirhalda tær til eina og hvørja tíð galdandi reglurnar ásettar av Ítróttasambandi Føroya (ÍSF) og altjóða styrkiítróttarsambondunum, sum eru viðkomandi. Hesi eru International Weightlifting Federation (IWF), International Powerlifting Federation (IPF) og World Kettlebell Sport Federation (WKSF).<br><br>'
        . 'Eg lati meg kanna til allar kanningar, ið FSS og ÍSF áleggja, herundir kanningar hjá teimum altjóða sambondunum. Hetta ger seg bæði galdandi í og uttanfyri kapping. Ein noktan at lata pissiroynd ella aðrar líknandi royndir verður roknað sum ein noktan at lata seg kanna fyri doping í mun til reglurnar hjá ÍSF.<br><br>'
        . 'Um eg innanfyri hetta tíðarskeiðið ella seinni loyvistíðarskeið verði funnin sekur í at bróta omanfyri nevndu dopingásetingar, forplikti eg meg til at rinda FSS fyri tær útreiðslur, ið FSS møguliga hevur havt av mær ísv t.d. uttanlandsferðir, venjingarlegur, stuðul til útgerð og annað mangt seinastu 12 mánaðarnar áðrenn brotið á dopingarreglurnar. Eg skilji eisini, at eitt brot á dopingreglurnar hevur við sær, at tey føroysku metini, sum eg seti, eftir at hava skrivað undir upp á hetta skjalið, verða strikað.<br><br>'
        . 'Ein og hvør ósemja ímillum meg og FSS um tulking av omanfyri nevnda, um støddina av gjalding av omanfyri nevndu upphæddunum, herundir sekt, ella í síni heild tulking av spurningum í mun til hetta skjal, kann verða løgd fyri gerðarætt í mun til tær reglur, sum til ta tíð eru galdandi fyri gerðarætt. FSS og eg útnevna hvør ein gerðarættarlim innan 14 dagar eftir móttøku av fráboðan um, at hin parturin hevur valt gerðarættarlim. Limirnir velja ein uppmann, sum skal vera púra ótengdur at báðum pørtum. Partarnir rinda hvør sín part av útreiðslunum til "sín" gerðarættarlim. FSS rindar samsýning til uppmannin og møguligar útreiðslur ísv. gerðarættarviðgerðina.';
}

/**
 * ADD-tekstblokk (HTML), brúkt í forminum.
 */
function lf_get_add_block_html() {
    return '<small>
        <strong>Anti Doping Danmark (ADD) skeið:</strong><br>
        Um íðkarin er 18 ár ella eldri, so er neyðugt at fullfíggja ADD-skeiðið <em>"Antidoping 1 - for idrætsudøvere"</em>, sum Anti Doping Danmark hevur gjørt. Skeiðið kann gerast á hesi síðuni: <a href="https://uddannelse.antidoping.dk/" target="_blank" rel="noopener">https://uddannelse.antidoping.dk/</a><br><br>
        ADD-skeiðið gongur út eftir tvey ár, tó er bert neyðugt at gera skeiðið einaferð.<br><br>
        Er hetta ikki fullfíggja, verður lyftiloyvi ikki góðtikið av FSS, og viðkomandi hevur ikki loyvið at luttaka til kappingar.<br><br>
        Er íðkarin undir 18 ár, er einki krav um at lúka skeiðið. Tó viðmæla vit altíð, at íðkarin og verjin hafa hetta skeiðið hóast alt.<br><br>
        Um íðkarin skal við landsliðinum, so er krav, at hann/hon hevur eitt ikki-útgingið skeið.
    </small>';
}

