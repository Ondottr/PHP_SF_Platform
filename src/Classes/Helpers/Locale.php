<?php declare( strict_types=1 );

/**
 *  Copyright © 2018-2022, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 *  Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 *  granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 *  THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 *  INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 *  LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 *  RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 *  TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Classes\Helpers;

use ReflectionClass;
use ReflectionProperty;
use PHP_SF\System\Classes\Exception\UndefinedLocaleKeyException;
use PHP_SF\System\Classes\Exception\UndefinedLocaleNameException;
use function array_flip;
use function array_key_exists;

final class Locale
{

    public const af_NA       = 'Afrikaans (Namibia)';
    public const af_ZA       = 'Afrikaans (South Africa)';
    public const af          = 'Afrikaans';
    public const ak_GH       = 'Akan (Ghana)';
    public const ak          = 'Akan';
    public const sq_AL       = 'Albanian (Albania)';
    public const sq          = 'Albanian';
    public const am_ET       = 'Amharic (Ethiopia)';
    public const am          = 'Amharic';
    public const ar_DZ       = 'Arabic (Algeria)';
    public const ar_BH       = 'Arabic (Bahrain)';
    public const ar_EG       = 'Arabic (Egypt)';
    public const ar_IQ       = 'Arabic (Iraq)';
    public const ar_JO       = 'Arabic (Jordan)';
    public const ar_KW       = 'Arabic (Kuwait)';
    public const ar_LB       = 'Arabic (Lebanon)';
    public const ar_LY       = 'Arabic (Libya)';
    public const ar_MA       = 'Arabic (Morocco)';
    public const ar_OM       = 'Arabic (Oman)';
    public const ar_QA       = 'Arabic (Qatar)';
    public const ar_SA       = 'Arabic (Saudi Arabia)';
    public const ar_SD       = 'Arabic (Sudan)';
    public const ar_SY       = 'Arabic (Syria)';
    public const ar_TN       = 'Arabic (Tunisia)';
    public const ar_AE       = 'Arabic (United Arab Emirates)';
    public const ar_YE       = 'Arabic (Yemen)';
    public const ar          = 'Arabic';
    public const hy_AM       = 'Armenian (Armenia)';
    public const hy          = 'Armenian';
    public const as_IN       = 'Assamese (India)';
    public const as          = 'Assamese';
    public const asa_TZ      = 'Asu (Tanzania)';
    public const asa         = 'Asu';
    public const az_Cyrl     = 'Azerbaijani (Cyrillic)';
    public const az_Cyrl_AZ  = 'Azerbaijani (Cyrillic, Azerbaijan)';
    public const az_Latn     = 'Azerbaijani (Latin)';
    public const az_Latn_AZ  = 'Azerbaijani (Latin, Azerbaijan)';
    public const az          = 'Azerbaijani';
    public const bm_ML       = 'Bambara (Mali)';
    public const bm          = 'Bambara';
    public const eu_ES       = 'Basque (Spain)';
    public const eu          = 'Basque';
    public const be_BY       = 'Belarusian (Belarus)';
    public const be          = 'Belarusian';
    public const bem_ZM      = 'Bemba (Zambia)';
    public const bem         = 'Bemba';
    public const bez_TZ      = 'Bena (Tanzania)';
    public const bez         = 'Bena';
    public const bn_BD       = 'Bengali (Bangladesh)';
    public const bn_IN       = 'Bengali (India)';
    public const bn          = 'Bengali';
    public const bs_BA       = 'Bosnian (Bosnia and Herzegovina)';
    public const bs          = 'Bosnian';
    public const bg_BG       = 'Bulgarian (Bulgaria)';
    public const bg          = 'Bulgarian';
    public const my_MM       = 'Burmese (Myanmar [Burma])';
    public const my          = 'Burmese';
    public const yue_Hant_HK = 'Cantonese (Traditional, Hong Kong SAR China)';
    public const ca_ES       = 'Catalan (Spain)';
    public const ca          = 'Catalan';
    public const tzm_Latn    = 'Central Morocco Tamazight (Latin)';
    public const tzm_Latn_MA = 'Central Morocco Tamazight (Latin, Morocco)';
    public const tzm         = 'Central Morocco Tamazight';
    public const chr_US      = 'Cherokee (United States)';
    public const chr         = 'Cherokee';
    public const cgg_UG      = 'Chiga (Uganda)';
    public const cgg         = 'Chiga';
    public const zh_Hans     = 'Chinese (Simplified Han)';
    public const zh_Hans_CN  = 'Chinese (Simplified Han, China)';
    public const zh_Hans_HK  = 'Chinese (Simplified Han, Hong Kong SAR China)';
    public const zh_Hans_MO  = 'Chinese (Simplified Han, Macau SAR China)';
    public const zh_Hans_SG  = 'Chinese (Simplified Han, Singapore)';
    public const zh_Hant     = 'Chinese (Traditional Han)';
    public const zh_Hant_HK  = 'Chinese (Traditional Han, Hong Kong SAR China)';
    public const zh_Hant_MO  = 'Chinese (Traditional Han, Macau SAR China)';
    public const zh_Hant_TW  = 'Chinese (Traditional Han, Taiwan)';
    public const zh          = 'Chinese';
    public const kw_GB       = 'Cornish (United Kingdom)';
    public const kw          = 'Cornish';
    public const hr_HR       = 'Croatian (Croatia)';
    public const hr          = 'Croatian';
    public const cs_CZ       = 'Czech (Czech Republic)';
    public const cs          = 'Czech';
    public const da_DK       = 'Danish (Denmark)';
    public const da          = 'Danish';
    public const nl_BE       = 'Dutch (Belgium)';
    public const nl_NL       = 'Dutch (Netherlands)';
    public const nl          = 'Dutch';
    public const ebu_KE      = 'Embu (Kenya)';
    public const ebu         = 'Embu';
    public const en_AS       = 'English (American Samoa)';
    public const en_AU       = 'English (Australia)';
    public const en_BE       = 'English (Belgium)';
    public const en_BZ       = 'English (Belize)';
    public const en_BW       = 'English (Botswana)';
    public const en_CA       = 'English (Canada)';
    public const en_GU       = 'English (Guam)';
    public const en_HK       = 'English (Hong Kong SAR China)';
    public const en_IN       = 'English (India)';
    public const en_IE       = 'English (Ireland)';
    public const en_IL       = 'English (Israel)';
    public const en_JM       = 'English (Jamaica)';
    public const en_MT       = 'English (Malta)';
    public const en_MH       = 'English (Marshall Islands)';
    public const en_MU       = 'English (Mauritius)';
    public const en_NA       = 'English (Namibia)';
    public const en_NZ       = 'English (New Zealand)';
    public const en_MP       = 'English (Northern Mariana Islands)';
    public const en_PK       = 'English (Pakistan)';
    public const en_PH       = 'English (Philippines)';
    public const en_SG       = 'English (Singapore)';
    public const en_ZA       = 'English (South Africa)';
    public const en_TT       = 'English (Trinidad and Tobago)';
    public const en_UM       = 'English (U.S. Minor Outlying Islands)';
    public const en_VI       = 'English (U.S. Virgin Islands)';
    public const en_GB       = 'English (United Kingdom)';
    public const en_US       = 'English (United States)';
    public const en_ZW       = 'English (Zimbabwe)';
    public const en          = 'English';
    public const eo          = 'Esperanto';
    public const et_EE       = 'Estonian (Estonia)';
    public const et          = 'Estonian';
    public const ee_GH       = 'Ewe (Ghana)';
    public const ee_TG       = 'Ewe (Togo)';
    public const ee          = 'Ewe';
    public const fo_FO       = 'Faroese (Faroe Islands)';
    public const fo          = 'Faroese';
    public const fil_PH      = 'Filipino (Philippines)';
    public const fil         = 'Filipino';
    public const fi_FI       = 'Finnish (Finland)';
    public const fi          = 'Finnish';
    public const fr_BE       = 'French (Belgium)';
    public const fr_BJ       = 'French (Benin)';
    public const fr_BF       = 'French (Burkina Faso)';
    public const fr_BI       = 'French (Burundi)';
    public const fr_CM       = 'French (Cameroon)';
    public const fr_CA       = 'French (Canada)';
    public const fr_CF       = 'French (Central African Republic)';
    public const fr_TD       = 'French (Chad)';
    public const fr_KM       = 'French (Comoros)';
    public const fr_CG       = 'French (Congo - Brazzaville)';
    public const fr_CD       = 'French (Congo - Kinshasa)';
    public const fr_CI       = 'French (Côte d’Ivoire)';
    public const fr_DJ       = 'French (Djibouti)';
    public const fr_GQ       = 'French (Equatorial Guinea)';
    public const fr_FR       = 'French (France)';
    public const fr_GA       = 'French (Gabon)';
    public const fr_GP       = 'French (Guadeloupe)';
    public const fr_GN       = 'French (Guinea)';
    public const fr_LU       = 'French (Luxembourg)';
    public const fr_MG       = 'French (Madagascar)';
    public const fr_ML       = 'French (Mali)';
    public const fr_MQ       = 'French (Martinique)';
    public const fr_MC       = 'French (Monaco)';
    public const fr_NE       = 'French (Niger)';
    public const fr_RW       = 'French (Rwanda)';
    public const fr_RE       = 'French (Réunion)';
    public const fr_BL       = 'French (Saint Barthélemy)';
    public const fr_MF       = 'French (Saint Martin)';
    public const fr_SN       = 'French (Senegal)';
    public const fr_CH       = 'French (Switzerland)';
    public const fr_TG       = 'French (Togo)';
    public const fr          = 'French';
    public const ff_SN       = 'Fulah (Senegal)';
    public const ff          = 'Fulah';
    public const gl_ES       = 'Galician (Spain)';
    public const gl          = 'Galician';
    public const lg_UG       = 'Ganda (Uganda)';
    public const lg          = 'Ganda';
    public const ka_GE       = 'Georgian (Georgia)';
    public const ka          = 'Georgian';
    public const de_AT       = 'German (Austria)';
    public const de_BE       = 'German (Belgium)';
    public const de_DE       = 'German (Germany)';
    public const de_LI       = 'German (Liechtenstein)';
    public const de_LU       = 'German (Luxembourg)';
    public const de_CH       = 'German (Switzerland)';
    public const de          = 'German';
    public const el_CY       = 'Greek (Cyprus)';
    public const el_GR       = 'Greek (Greece)';
    public const el          = 'Greek';
    public const gu_IN       = 'Gujarati (India)';
    public const gu          = 'Gujarati';
    public const guz_KE      = 'Gusii (Kenya)';
    public const guz         = 'Gusii';
    public const ha_Latn     = 'Hausa (Latin)';
    public const ha_Latn_GH  = 'Hausa (Latin, Ghana)';
    public const ha_Latn_NE  = 'Hausa (Latin, Niger)';
    public const ha_Latn_NG  = 'Hausa (Latin, Nigeria)';
    public const ha          = 'Hausa';
    public const haw_US      = 'Hawaiian (United States)';
    public const haw         = 'Hawaiian';
    public const he_IL       = 'Hebrew (Israel)';
    public const he          = 'Hebrew';
    public const hi_IN       = 'Hindi (India)';
    public const hi          = 'Hindi';
    public const hu_HU       = 'Hungarian (Hungary)';
    public const hu          = 'Hungarian';
    public const is_IS       = 'Icelandic (Iceland)';
    public const is          = 'Icelandic';
    public const ig_NG       = 'Igbo (Nigeria)';
    public const ig          = 'Igbo';
    public const id_ID       = 'Indonesian (Indonesia)';
    public const id          = 'Indonesian';
    public const ga_IE       = 'Irish (Ireland)';
    public const ga          = 'Irish';
    public const it_IT       = 'Italian (Italy)';
    public const it_CH       = 'Italian (Switzerland)';
    public const it          = 'Italian';
    public const ja_JP       = 'Japanese (Japan)';
    public const ja          = 'Japanese';
    public const kea_CV      = 'Kabuverdianu (Cape Verde)';
    public const kea         = 'Kabuverdianu';
    public const kab_DZ      = 'Kabyle (Algeria)';
    public const kab         = 'Kabyle';
    public const kl_GL       = 'Kalaallisut (Greenland)';
    public const kl          = 'Kalaallisut';
    public const kln_KE      = 'Kalenjin (Kenya)';
    public const kln         = 'Kalenjin';
    public const kam_KE      = 'Kamba (Kenya)';
    public const kam         = 'Kamba';
    public const kn_IN       = 'Kannada (India)';
    public const kn          = 'Kannada';
    public const kk_Cyrl     = 'Kazakh (Cyrillic)';
    public const kk_Cyrl_KZ  = 'Kazakh (Cyrillic, Kazakhstan)';
    public const kk          = 'Kazakh';
    public const km_KH       = 'Khmer (Cambodia)';
    public const km          = 'Khmer';
    public const ki_KE       = 'Kikuyu (Kenya)';
    public const ki          = 'Kikuyu';
    public const rw_RW       = 'Kinyarwanda (Rwanda)';
    public const rw          = 'Kinyarwanda';
    public const kok_IN      = 'Konkani (India)';
    public const kok         = 'Konkani';
    public const ko_KR       = 'Korean (South Korea)';
    public const ko          = 'Korean';
    public const khq_ML      = 'Koyra Chiini (Mali)';
    public const khq         = 'Koyra Chiini';
    public const ses_ML      = 'Koyraboro Senni (Mali)';
    public const ses         = 'Koyraboro Senni';
    public const lag_TZ      = 'Langi (Tanzania)';
    public const lag         = 'Langi';
    public const lv_LV       = 'Latvian (Latvia)';
    public const lv          = 'Latvian';
    public const lt_LT       = 'Lithuanian (Lithuania)';
    public const lt          = 'Lithuanian';
    public const luo_KE      = 'Luo (Kenya)';
    public const luo         = 'Luo';
    public const luy_KE      = 'Luyia (Kenya)';
    public const luy         = 'Luyia';
    public const mk_MK       = 'Macedonian (Macedonia)';
    public const mk          = 'Macedonian';
    public const jmc_TZ      = 'Machame (Tanzania)';
    public const jmc         = 'Machame';
    public const kde_TZ      = 'Makonde (Tanzania)';
    public const kde         = 'Makonde';
    public const mg_MG       = 'Malagasy (Madagascar)';
    public const mg          = 'Malagasy';
    public const ms_BN       = 'Malay (Brunei)';
    public const ms_MY       = 'Malay (Malaysia)';
    public const ms          = 'Malay';
    public const ml_IN       = 'Malayalam (India)';
    public const ml          = 'Malayalam';
    public const mt_MT       = 'Maltese (Malta)';
    public const mt          = 'Maltese';
    public const gv_GB       = 'Manx (United Kingdom)';
    public const gv          = 'Manx';
    public const mr_IN       = 'Marathi (India)';
    public const mr          = 'Marathi';
    public const mas_KE      = 'Masai (Kenya)';
    public const mas_TZ      = 'Masai (Tanzania)';
    public const mas         = 'Masai';
    public const mer_KE      = 'Meru (Kenya)';
    public const mer         = 'Meru';
    public const mfe_MU      = 'Morisyen (Mauritius)';
    public const mfe         = 'Morisyen';
    public const naq_NA      = 'Nama (Namibia)';
    public const naq         = 'Nama';
    public const ne_IN       = 'Nepali (India)';
    public const ne_NP       = 'Nepali (Nepal)';
    public const ne          = 'Nepali';
    public const nd_ZW       = 'North Ndebele (Zimbabwe)';
    public const nd          = 'North Ndebele';
    public const nb          = 'Norsk Bokmål';
    public const nn          = 'Norsk Nynorsk';
    public const nyn_UG      = 'Nyankole (Uganda)';
    public const nyn         = 'Nyankole';
    public const or_IN       = 'Oriya (India)';
    public const or          = 'Oriya';
    public const om_ET       = 'Oromo (Ethiopia)';
    public const om_KE       = 'Oromo (Kenya)';
    public const om          = 'Oromo';
    public const ps_AF       = 'Pashto (Afghanistan)';
    public const ps          = 'Pashto';
    public const fa_AF       = 'Persian (Afghanistan)';
    public const fa_IR       = 'Persian (Iran)';
    public const fa          = 'Persian';
    public const pl          = 'Polski';
    public const pt_BR       = 'Portuguese (Brazil)';
    public const pt_GW       = 'Portuguese (Guinea-Bissau)';
    public const pt_MZ       = 'Portuguese (Mozambique)';
    public const pt_PT       = 'Portuguese (Portugal)';
    public const pt          = 'Portuguese';
    public const pa_Arab     = 'Punjabi (Arabic)';
    public const pa_Arab_PK  = 'Punjabi (Arabic, Pakistan)';
    public const pa_Guru     = 'Punjabi (Gurmukhi)';
    public const pa_Guru_IN  = 'Punjabi (Gurmukhi, India)';
    public const pa          = 'Punjabi';
    public const ro_MD       = 'Romanian (Moldova)';
    public const ro_RO       = 'Romanian (Romania)';
    public const ro          = 'Romanian';
    public const rm_CH       = 'Romansh (Switzerland)';
    public const rm          = 'Romansh';
    public const rof_TZ      = 'Rombo (Tanzania)';
    public const rof         = 'Rombo';
    public const ru          = 'Русский';
    public const rwk_TZ      = 'Rwa (Tanzania)';
    public const rwk         = 'Rwa';
    public const saq_KE      = 'Samburu (Kenya)';
    public const saq         = 'Samburu';
    public const sg_CF       = 'Sango (Central African Republic)';
    public const sg          = 'Sango';
    public const seh_MZ      = 'Sena (Mozambique)';
    public const seh         = 'Sena';
    public const sr_Cyrl     = 'Serbian (Cyrillic)';
    public const sr_Cyrl_BA  = 'Serbian (Cyrillic, Bosnia and Herzegovina)';
    public const sr_Cyrl_ME  = 'Serbian (Cyrillic, Montenegro)';
    public const sr_Cyrl_RS  = 'Serbian (Cyrillic, Serbia)';
    public const sr_Latn     = 'Serbian (Latin)';
    public const sr_Latn_BA  = 'Serbian (Latin, Bosnia and Herzegovina)';
    public const sr_Latn_ME  = 'Serbian (Latin, Montenegro)';
    public const sr_Latn_RS  = 'Serbian (Latin, Serbia)';
    public const sr          = 'Serbian';
    public const sn_ZW       = 'Shona (Zimbabwe)';
    public const sn          = 'Shona';
    public const ii_CN       = 'Sichuan Yi (China)';
    public const ii          = 'Sichuan Yi';
    public const si_LK       = 'Sinhala (Sri Lanka)';
    public const si          = 'Sinhala';
    public const sk_SK       = 'Slovak (Slovakia)';
    public const sk          = 'Slovak';
    public const sl_SI       = 'Slovenian (Slovenia)';
    public const sl          = 'Slovenian';
    public const xog_UG      = 'Soga (Uganda)';
    public const xog         = 'Soga';
    public const so_DJ       = 'Somali (Djibouti)';
    public const so_ET       = 'Somali (Ethiopia)';
    public const so_KE       = 'Somali (Kenya)';
    public const so_SO       = 'Somali (Somalia)';
    public const so          = 'Somali';
    public const es_AR       = 'Spanish (Argentina)';
    public const es_BO       = 'Spanish (Bolivia)';
    public const es_CL       = 'Spanish (Chile)';
    public const es_CO       = 'Spanish (Colombia)';
    public const es_CR       = 'Spanish (Costa Rica)';
    public const es_DO       = 'Spanish (Dominican Republic)';
    public const es_EC       = 'Spanish (Ecuador)';
    public const es_SV       = 'Spanish (El Salvador)';
    public const es_GQ       = 'Spanish (Equatorial Guinea)';
    public const es_GT       = 'Spanish (Guatemala)';
    public const es_HN       = 'Spanish (Honduras)';
    public const es_419      = 'Spanish (Latin America)';
    public const es_MX       = 'Spanish (Mexico)';
    public const es_NI       = 'Spanish (Nicaragua)';
    public const es_PA       = 'Spanish (Panama)';
    public const es_PY       = 'Spanish (Paraguay)';
    public const es_PE       = 'Spanish (Peru)';
    public const es_PR       = 'Spanish (Puerto Rico)';
    public const es_ES       = 'Spanish (Spain)';
    public const es_US       = 'Spanish (United States)';
    public const es_UY       = 'Spanish (Uruguay)';
    public const es_VE       = 'Spanish (Venezuela)';
    public const es          = 'Spanish';
    public const sw_KE       = 'Swahili (Kenya)';
    public const sw_TZ       = 'Swahili (Tanzania)';
    public const sw          = 'Swahili';
    public const sv_FI       = 'Swedish (Finland)';
    public const sv_SE       = 'Swedish (Sweden)';
    public const sv          = 'Swedish';
    public const gsw_CH      = 'Swiss German (Switzerland)';
    public const gsw         = 'Swiss German';
    public const shi_Latn    = 'Tachelhit (Latin)';
    public const shi_Latn_MA = 'Tachelhit (Latin, Morocco)';
    public const shi_Tfng    = 'Tachelhit (Tifinagh)';
    public const shi_Tfng_MA = 'Tachelhit (Tifinagh, Morocco)';
    public const shi         = 'Tachelhit';
    public const dav_KE      = 'Taita (Kenya)';
    public const dav         = 'Taita';
    public const ta_IN       = 'Tamil (India)';
    public const ta_LK       = 'Tamil (Sri Lanka)';
    public const ta          = 'Tamil';
    public const te_IN       = 'Telugu (India)';
    public const te          = 'Telugu';
    public const teo_KE      = 'Teso (Kenya)';
    public const teo_UG      = 'Teso (Uganda)';
    public const teo         = 'Teso';
    public const th_TH       = 'Thai (Thailand)';
    public const th          = 'Thai';
    public const bo_CN       = 'Tibetan (China)';
    public const bo_IN       = 'Tibetan (India)';
    public const bo          = 'Tibetan';
    public const ti_ER       = 'Tigrinya (Eritrea)';
    public const ti_ET       = 'Tigrinya (Ethiopia)';
    public const ti          = 'Tigrinya';
    public const to_TO       = 'Tonga (Tonga)';
    public const to          = 'Tonga';
    public const tr_TR       = 'Turkish (Turkey)';
    public const tr          = 'Turkish';
    public const uk          = 'Українська';
    public const ur_IN       = 'Urdu (India)';
    public const ur_PK       = 'Urdu (Pakistan)';
    public const ur          = 'Urdu';
    public const uz_Arab     = 'Uzbek (Arabic)';
    public const uz_Arab_AF  = 'Uzbek (Arabic, Afghanistan)';
    public const uz_Cyrl     = 'Uzbek (Cyrillic)';
    public const uz_Cyrl_UZ  = 'Uzbek (Cyrillic, Uzbekistan)';
    public const uz_Latn     = 'Uzbek (Latin)';
    public const uz_Latn_UZ  = 'Uzbek (Latin, Uzbekistan)';
    public const uz          = 'Uzbek';
    public const vi_VN       = 'Vietnamese (Vietnam)';
    public const vi          = 'Vietnamese';
    public const vun_TZ      = 'Vunjo (Tanzania)';
    public const vun         = 'Vunjo';
    public const cy_GB       = 'Welsh (United Kingdom)';
    public const cy          = 'Welsh';
    public const yo_NG       = 'Yoruba (Nigeria)';
    public const yo          = 'Yoruba';
    public const zu_ZA       = 'Zulu (South Africa)';
    public const zu          = 'Zulu';

    private static array $localesList;
    private static array $localeKeysList;

    private function __construct() {}

    public static function getLocaleKey( string $localeName ): string
    {
        if ( !self::checkLocaleName( $localeName ) )
            throw new UndefinedLocaleKeyException( $localeName );

        return self::getLocaleKeysList()[ $localeName ];
    }

    public static function checkLocaleName( string $localeName ): bool
    {
        return array_key_exists( $localeName, self::getLocaleKeysList() );
    }

    public static function getLocaleKeysList(): array
    {
        if ( !isset( self::$localeKeysList ) )
            self::setLocaleKeysList();

        return self::$localeKeysList;
    }

    private static function setLocaleKeysList(): void
    {
        self::$localeKeysList = array_flip( self::getLocalesList() );
    }

    private static function getLocalesList(): array
    {
        if ( !isset( self::$localesList ) )
            self::setLocalesList();

        return self::$localesList;
    }

    private static function setLocalesList(): void
    {
        self::$localesList = ( new ReflectionClass( self::class ) )
            ->getConstants( ReflectionProperty::IS_PUBLIC );
    }

    public static function getLocaleName( string $localeKey ): string
    {
        if ( !self::checkLocaleKey( $localeKey ) )
            throw new UndefinedLocaleNameException( $localeKey );

        return self::getLocalesList()[ $localeKey ];
    }

    public static function checkLocaleKey( string $localeKey ): bool
    {
        return array_key_exists( $localeKey, self::getLocalesList() );
    }

    private function __clone(): void {}

}
