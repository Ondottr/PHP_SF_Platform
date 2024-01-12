<?php /** @noinspection SpellCheckingInspection @noinspection PhpUnused */
declare( strict_types=1 );
/**
 *  Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
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

use PHP_SF\System\Classes\Abstracts\AbstractCacheAdapter;
use PHP_SF\System\Classes\Exception\UndefinedLocaleKeyException;
use PHP_SF\System\Classes\Exception\UndefinedLocaleNameException;
use ReflectionClass;
use ReflectionProperty;

use function array_flip;
use function array_key_exists;

/**
 * This class represents a collection of locales and provides methods for retrieving and managing them.
 *
 * The locales are stored as constants within the class and can be retrieved using reflection.
 *
 * The class also provides methods for converting between locale names and keys, and caching the results for faster access.
 *
 * The cache is managed using the {@link AbstractCacheAdapter} {@link ca()}.
 */
final class Locale
{

    # region Locale constants
    public const af = 'Afrikaans';
    public const af_NA = 'Afrikaans (Namibia)';
    public const af_ZA = 'Afrikaans (South Africa)';
    public const ak = 'Akan';
    public const ak_GH = 'Akan (Ghana)';
    public const am = 'Amharic';
    public const am_ET = 'Amharic (Ethiopia)';
    public const ar = 'Arabic';
    public const ar_001 = 'Arabic (world)';
    public const ar_AE = 'Arabic (United Arab Emirates)';
    public const ar_BH = 'Arabic (Bahrain)';
    public const ar_DJ = 'Arabic (Djibouti)';
    public const ar_DZ = 'Arabic (Algeria)';
    public const ar_EG = 'Arabic (Egypt)';
    public const ar_EH = 'Arabic (Western Sahara)';
    public const ar_ER = 'Arabic (Eritrea)';
    public const ar_IL = 'Arabic (Israel)';
    public const ar_IQ = 'Arabic (Iraq)';
    public const ar_JO = 'Arabic (Jordan)';
    public const ar_KM = 'Arabic (Comoros)';
    public const ar_KW = 'Arabic (Kuwait)';
    public const ar_LB = 'Arabic (Lebanon)';
    public const ar_LY = 'Arabic (Libya)';
    public const ar_MA = 'Arabic (Morocco)';
    public const ar_MR = 'Arabic (Mauritania)';
    public const ar_OM = 'Arabic (Oman)';
    public const ar_PS = 'Arabic (Palestinian Territories)';
    public const ar_QA = 'Arabic (Qatar)';
    public const ar_SA = 'Arabic (Saudi Arabia)';
    public const ar_SD = 'Arabic (Sudan)';
    public const ar_SO = 'Arabic (Somalia)';
    public const ar_SS = 'Arabic (South Sudan)';
    public const ar_SY = 'Arabic (Syria)';
    public const ar_TD = 'Arabic (Chad)';
    public const ar_TN = 'Arabic (Tunisia)';
    public const ar_YE = 'Arabic (Yemen)';
    public const as = 'Assamese';
    public const as_IN = 'Assamese (India)';
    public const az = 'Azerbaijani';
    public const az_AZ = 'Azerbaijani (Azerbaijan)';
    public const az_Cyrl = 'Azerbaijani (Cyrillic)';
    public const az_Cyrl_AZ = 'Azerbaijani (Cyrillic, Azerbaijan)';
    public const az_Latn = 'Azerbaijani (Latin)';
    public const az_Latn_AZ = 'Azerbaijani (Latin, Azerbaijan)';
    public const be = 'Belarusian';
    public const be_BY = 'Belarusian (Belarus)';
    public const bg = 'Bulgarian';
    public const bg_BG = 'Bulgarian (Bulgaria)';
    public const bm = 'Bambara';
    public const bm_ML = 'Bambara (Mali)';
    public const bn = 'Bangla';
    public const bn_BD = 'Bangla (Bangladesh)';
    public const bn_IN = 'Bangla (India)';
    public const bo = 'Tibetan';
    public const bo_CN = 'Tibetan (China)';
    public const bo_IN = 'Tibetan (India)';
    public const br = 'Breton';
    public const br_FR = 'Breton (France)';
    public const bs = 'Bosnian';
    public const bs_BA = 'Bosnian (Bosnia & Herzegovina)';
    public const bs_Cyrl = 'Bosnian (Cyrillic)';
    public const bs_Cyrl_BA = 'Bosnian (Cyrillic, Bosnia & Herzegovina)';
    public const bs_Latn = 'Bosnian (Latin)';
    public const bs_Latn_BA = 'Bosnian (Latin, Bosnia & Herzegovina)';
    public const ca = 'Catalan';
    public const ca_AD = 'Catalan (Andorra)';
    public const ca_ES = 'Catalan (Spain)';
    public const ca_FR = 'Catalan (France)';
    public const ca_IT = 'Catalan (Italy)';
    public const ce = 'Chechen';
    public const ce_RU = 'Chechen (russia)';
    public const cs = 'Czech';
    public const cs_CZ = 'Czech (Czechia)';
    public const cv = 'Chuvash';
    public const cv_RU = 'Chuvash (russia)';
    public const cy = 'Welsh';
    public const cy_GB = 'Welsh (United Kingdom)';
    public const da = 'Danish';
    public const da_DK = 'Danish (Denmark)';
    public const da_GL = 'Danish (Greenland)';
    public const de = 'German';
    public const de_AT = 'German (Austria)';
    public const de_BE = 'German (Belgium)';
    public const de_CH = 'German (Switzerland)';
    public const de_DE = 'German (Germany)';
    public const de_IT = 'German (Italy)';
    public const de_LI = 'German (Liechtenstein)';
    public const de_LU = 'German (Luxembourg)';
    public const dz = 'Dzongkha';
    public const dz_BT = 'Dzongkha (Bhutan)';
    public const ee = 'Ewe';
    public const ee_GH = 'Ewe (Ghana)';
    public const ee_TG = 'Ewe (Togo)';
    public const el = 'Greek';
    public const el_CY = 'Greek (Cyprus)';
    public const el_GR = 'Greek (Greece)';
    public const en = 'English';
    public const en_001 = 'English (world)';
    public const en_150 = 'English (Europe)';
    public const en_AE = 'English (United Arab Emirates)';
    public const en_AG = 'English (Antigua & Barbuda)';
    public const en_AI = 'English (Anguilla)';
    public const en_AS = 'English (American Samoa)';
    public const en_AT = 'English (Austria)';
    public const en_AU = 'English (Australia)';
    public const en_BB = 'English (Barbados)';
    public const en_BE = 'English (Belgium)';
    public const en_BI = 'English (Burundi)';
    public const en_BM = 'English (Bermuda)';
    public const en_BS = 'English (Bahamas)';
    public const en_BW = 'English (Botswana)';
    public const en_BZ = 'English (Belize)';
    public const en_CA = 'English (Canada)';
    public const en_CC = 'English (Cocos [Keeling] Islands)';
    public const en_CH = 'English (Switzerland)';
    public const en_CK = 'English (Cook Islands)';
    public const en_CM = 'English (Cameroon)';
    public const en_CX = 'English (Christmas Island)';
    public const en_CY = 'English (Cyprus)';
    public const en_DE = 'English (Germany)';
    public const en_DK = 'English (Denmark)';
    public const en_DM = 'English (Dominica)';
    public const en_ER = 'English (Eritrea)';
    public const en_FI = 'English (Finland)';
    public const en_FJ = 'English (Fiji)';
    public const en_FK = 'English (Falkland Islands)';
    public const en_FM = 'English (Micronesia)';
    public const en_GB = 'English (United Kingdom)';
    public const en_GD = 'English (Grenada)';
    public const en_GG = 'English (Guernsey)';
    public const en_GH = 'English (Ghana)';
    public const en_GI = 'English (Gibraltar)';
    public const en_GM = 'English (Gambia)';
    public const en_GU = 'English (Guam)';
    public const en_GY = 'English (Guyana)';
    public const en_HK = 'English (Hong Kong SAR China)';
    public const en_IE = 'English (Ireland)';
    public const en_IL = 'English (Israel)';
    public const en_IM = 'English (Isle of Man)';
    public const en_IN = 'English (India)';
    public const en_IO = 'English (British Indian Ocean Territory)';
    public const en_JE = 'English (Jersey)';
    public const en_JM = 'English (Jamaica)';
    public const en_KE = 'English (Kenya)';
    public const en_KI = 'English (Kiribati)';
    public const en_KN = 'English (St. Kitts & Nevis)';
    public const en_KY = 'English (Cayman Islands)';
    public const en_LC = 'English (St. Lucia)';
    public const en_LR = 'English (Liberia)';
    public const en_LS = 'English (Lesotho)';
    public const en_MG = 'English (Madagascar)';
    public const en_MH = 'English (Marshall Islands)';
    public const en_MO = 'English (Macao SAR China)';
    public const en_MP = 'English (Northern Mariana Islands)';
    public const en_MS = 'English (Montserrat)';
    public const en_MT = 'English (Malta)';
    public const en_MU = 'English (Mauritius)';
    public const en_MV = 'English (Maldives)';
    public const en_MW = 'English (Malawi)';
    public const en_MY = 'English (Malaysia)';
    public const en_NA = 'English (Namibia)';
    public const en_NF = 'English (Norfolk Island)';
    public const en_NG = 'English (Nigeria)';
    public const en_NL = 'English (Netherlands)';
    public const en_NR = 'English (Nauru)';
    public const en_NU = 'English (Niue)';
    public const en_NZ = 'English (New Zealand)';
    public const en_PG = 'English (Papua New Guinea)';
    public const en_PH = 'English (Philippines)';
    public const en_PK = 'English (Pakistan)';
    public const en_PN = 'English (Pitcairn Islands)';
    public const en_PR = 'English (Puerto Rico)';
    public const en_PW = 'English (Palau)';
    public const en_RW = 'English (Rwanda)';
    public const en_SB = 'English (Solomon Islands)';
    public const en_SC = 'English (Seychelles)';
    public const en_SD = 'English (Sudan)';
    public const en_SE = 'English (Sweden)';
    public const en_SG = 'English (Singapore)';
    public const en_SH = 'English (St. Helena)';
    public const en_SI = 'English (Slovenia)';
    public const en_SL = 'English (Sierra Leone)';
    public const en_SS = 'English (South Sudan)';
    public const en_SX = 'English (Sint Maarten)';
    public const en_SZ = 'English (Eswatini)';
    public const en_TC = 'English (Turks & Caicos Islands)';
    public const en_TK = 'English (Tokelau)';
    public const en_TO = 'English (Tonga)';
    public const en_TT = 'English (Trinidad & Tobago)';
    public const en_TV = 'English (Tuvalu)';
    public const en_TZ = 'English (Tanzania)';
    public const en_UG = 'English (Uganda)';
    public const en_UM = 'English (U.S. Outlying Islands)';
    public const en_US = 'English (United States)';
    public const en_VC = 'English (St. Vincent & Grenadines)';
    public const en_VG = 'English (British Virgin Islands)';
    public const en_VI = 'English (U.S. Virgin Islands)';
    public const en_VU = 'English (Vanuatu)';
    public const en_WS = 'English (Samoa)';
    public const en_ZA = 'English (South Africa)';
    public const en_ZM = 'English (Zambia)';
    public const en_ZW = 'English (Zimbabwe)';
    public const eo = 'Esperanto';
    public const eo_001 = 'Esperanto (world)';
    public const es = 'Spanish';
    public const es_419 = 'Spanish (Latin America)';
    public const es_AR = 'Spanish (Argentina)';
    public const es_BO = 'Spanish (Bolivia)';
    public const es_BR = 'Spanish (Brazil)';
    public const es_BZ = 'Spanish (Belize)';
    public const es_CL = 'Spanish (Chile)';
    public const es_CO = 'Spanish (Colombia)';
    public const es_CR = 'Spanish (Costa Rica)';
    public const es_CU = 'Spanish (Cuba)';
    public const es_DO = 'Spanish (Dominican Republic)';
    public const es_EC = 'Spanish (Ecuador)';
    public const es_ES = 'Spanish (Spain)';
    public const es_GQ = 'Spanish (Equatorial Guinea)';
    public const es_GT = 'Spanish (Guatemala)';
    public const es_HN = 'Spanish (Honduras)';
    public const es_MX = 'Spanish (Mexico)';
    public const es_NI = 'Spanish (Nicaragua)';
    public const es_PA = 'Spanish (Panama)';
    public const es_PE = 'Spanish (Peru)';
    public const es_PH = 'Spanish (Philippines)';
    public const es_PR = 'Spanish (Puerto Rico)';
    public const es_PY = 'Spanish (Paraguay)';
    public const es_SV = 'Spanish (El Salvador)';
    public const es_US = 'Spanish (United States)';
    public const es_UY = 'Spanish (Uruguay)';
    public const es_VE = 'Spanish (Venezuela)';
    public const et = 'Estonian';
    public const et_EE = 'Estonian (Estonia)';
    public const eu = 'Basque';
    public const eu_ES = 'Basque (Spain)';
    public const fa = 'Persian';
    public const fa_AF = 'Persian (Afghanistan)';
    public const fa_IR = 'Persian (Iran)';
    public const ff = 'Fulah';
    public const ff_Adlm = 'Fulah (Adlam)';
    public const ff_Adlm_BF = 'Fulah (Adlam, Burkina Faso)';
    public const ff_Adlm_CM = 'Fulah (Adlam, Cameroon)';
    public const ff_Adlm_GH = 'Fulah (Adlam, Ghana)';
    public const ff_Adlm_GM = 'Fulah (Adlam, Gambia)';
    public const ff_Adlm_GN = 'Fulah (Adlam, Guinea)';
    public const ff_Adlm_GW = 'Fulah (Adlam, Guinea-Bissau)';
    public const ff_Adlm_LR = 'Fulah (Adlam, Liberia)';
    public const ff_Adlm_MR = 'Fulah (Adlam, Mauritania)';
    public const ff_Adlm_NE = 'Fulah (Adlam, Niger)';
    public const ff_Adlm_NG = 'Fulah (Adlam, Nigeria)';
    public const ff_Adlm_SL = 'Fulah (Adlam, Sierra Leone)';
    public const ff_Adlm_SN = 'Fulah (Adlam, Senegal)';
    public const ff_CM = 'Fulah (Cameroon)';
    public const ff_GN = 'Fulah (Guinea)';
    public const ff_Latn = 'Fulah (Latin)';
    public const ff_Latn_BF = 'Fulah (Latin, Burkina Faso)';
    public const ff_Latn_CM = 'Fulah (Latin, Cameroon)';
    public const ff_Latn_GH = 'Fulah (Latin, Ghana)';
    public const ff_Latn_GM = 'Fulah (Latin, Gambia)';
    public const ff_Latn_GN = 'Fulah (Latin, Guinea)';
    public const ff_Latn_GW = 'Fulah (Latin, Guinea-Bissau)';
    public const ff_Latn_LR = 'Fulah (Latin, Liberia)';
    public const ff_Latn_MR = 'Fulah (Latin, Mauritania)';
    public const ff_Latn_NE = 'Fulah (Latin, Niger)';
    public const ff_Latn_NG = 'Fulah (Latin, Nigeria)';
    public const ff_Latn_SL = 'Fulah (Latin, Sierra Leone)';
    public const ff_Latn_SN = 'Fulah (Latin, Senegal)';
    public const ff_MR = 'Fulah (Mauritania)';
    public const ff_SN = 'Fulah (Senegal)';
    public const fi = 'Finnish';
    public const fi_FI = 'Finnish (Finland)';
    public const fo = 'Faroese';
    public const fo_DK = 'Faroese (Denmark)';
    public const fo_FO = 'Faroese (Faroe Islands)';
    public const fr = 'French';
    public const fr_BE = 'French (Belgium)';
    public const fr_BF = 'French (Burkina Faso)';
    public const fr_BI = 'French (Burundi)';
    public const fr_BJ = 'French (Benin)';
    public const fr_BL = 'French (St. Barthélemy)';
    public const fr_CA = 'French (Canada)';
    public const fr_CD = 'French (Congo - Kinshasa)';
    public const fr_CF = 'French (Central African Republic)';
    public const fr_CG = 'French (Congo - Brazzaville)';
    public const fr_CH = 'French (Switzerland)';
    public const fr_CI = 'French (Côte d’Ivoire)';
    public const fr_CM = 'French (Cameroon)';
    public const fr_DJ = 'French (Djibouti)';
    public const fr_DZ = 'French (Algeria)';
    public const fr_FR = 'French (France)';
    public const fr_GA = 'French (Gabon)';
    public const fr_GF = 'French (French Guiana)';
    public const fr_GN = 'French (Guinea)';
    public const fr_GP = 'French (Guadeloupe)';
    public const fr_GQ = 'French (Equatorial Guinea)';
    public const fr_HT = 'French (Haiti)';
    public const fr_KM = 'French (Comoros)';
    public const fr_LU = 'French (Luxembourg)';
    public const fr_MA = 'French (Morocco)';
    public const fr_MC = 'French (Monaco)';
    public const fr_MF = 'French (St. Martin)';
    public const fr_MG = 'French (Madagascar)';
    public const fr_ML = 'French (Mali)';
    public const fr_MQ = 'French (Martinique)';
    public const fr_MR = 'French (Mauritania)';
    public const fr_MU = 'French (Mauritius)';
    public const fr_NC = 'French (New Caledonia)';
    public const fr_NE = 'French (Niger)';
    public const fr_PF = 'French (French Polynesia)';
    public const fr_PM = 'French (St. Pierre & Miquelon)';
    public const fr_RE = 'French (Réunion)';
    public const fr_RW = 'French (Rwanda)';
    public const fr_SC = 'French (Seychelles)';
    public const fr_SN = 'French (Senegal)';
    public const fr_SY = 'French (Syria)';
    public const fr_TD = 'French (Chad)';
    public const fr_TG = 'French (Togo)';
    public const fr_TN = 'French (Tunisia)';
    public const fr_VU = 'French (Vanuatu)';
    public const fr_WF = 'French (Wallis & Futuna)';
    public const fr_YT = 'French (Mayotte)';
    public const fy = 'Western Frisian';
    public const fy_NL = 'Western Frisian (Netherlands)';
    public const ga = 'Irish';
    public const ga_GB = 'Irish (United Kingdom)';
    public const ga_IE = 'Irish (Ireland)';
    public const gd = 'Scottish Gaelic';
    public const gd_GB = 'Scottish Gaelic (United Kingdom)';
    public const gl = 'Galician';
    public const gl_ES = 'Galician (Spain)';
    public const gu = 'Gujarati';
    public const gu_IN = 'Gujarati (India)';
    public const gv = 'Manx';
    public const gv_IM = 'Manx (Isle of Man)';
    public const ha = 'Hausa';
    public const ha_GH = 'Hausa (Ghana)';
    public const ha_NE = 'Hausa (Niger)';
    public const ha_NG = 'Hausa (Nigeria)';
    public const he = 'Hebrew';
    public const he_IL = 'Hebrew (Israel)';
    public const hi = 'Hindi';
    public const hi_IN = 'Hindi (India)';
    public const hi_Latn = 'Hindi (Latin)';
    public const hi_Latn_IN = 'Hindi (Latin, India)';
    public const hr = 'Croatian';
    public const hr_BA = 'Croatian (Bosnia & Herzegovina)';
    public const hr_HR = 'Croatian (Croatia)';
    public const hu = 'Hungarian';
    public const hu_HU = 'Hungarian (Hungary)';
    public const hy = 'Armenian';
    public const hy_AM = 'Armenian (Armenia)';
    public const ia = 'Interlingua';
    public const ia_001 = 'Interlingua (world)';
    public const id = 'Indonesian';
    public const id_ID = 'Indonesian (Indonesia)';
    public const ig = 'Igbo';
    public const ig_NG = 'Igbo (Nigeria)';
    public const ii = 'Sichuan Yi';
    public const ii_CN = 'Sichuan Yi (China)';
    public const is = 'Icelandic';
    public const is_IS = 'Icelandic (Iceland)';
    public const it = 'Italian';
    public const it_CH = 'Italian (Switzerland)';
    public const it_IT = 'Italian (Italy)';
    public const it_SM = 'Italian (San Marino)';
    public const it_VA = 'Italian (Vatican City)';
    public const ja = 'Japanese';
    public const ja_JP = 'Japanese (Japan)';
    public const jv = 'Javanese';
    public const jv_ID = 'Javanese (Indonesia)';
    public const ka = 'Georgian';
    public const ka_GE = 'Georgian (Georgia)';
    public const ki = 'Kikuyu';
    public const ki_KE = 'Kikuyu (Kenya)';
    public const kk = 'Kazakh';
    public const kk_KZ = 'Kazakh (Kazakhstan)';
    public const kl = 'Kalaallisut';
    public const kl_GL = 'Kalaallisut (Greenland)';
    public const km = 'Khmer';
    public const km_KH = 'Khmer (Cambodia)';
    public const kn = 'Kannada';
    public const kn_IN = 'Kannada (India)';
    public const ko = 'Korean';
    public const ko_KP = 'Korean (North Korea)';
    public const ko_KR = 'Korean (South Korea)';
    public const ks = 'Kashmiri';
    public const ks_Arab = 'Kashmiri (Arabic)';
    public const ks_Arab_IN = 'Kashmiri (Arabic, India)';
    public const ks_Deva = 'Kashmiri (Devanagari)';
    public const ks_Deva_IN = 'Kashmiri (Devanagari, India)';
    public const ks_IN = 'Kashmiri (India)';
    public const ku = 'Kurdish';
    public const ku_TR = 'Kurdish (Turkey)';
    public const kw = 'Cornish';
    public const kw_GB = 'Cornish (United Kingdom)';
    public const ky = 'Kyrgyz';
    public const ky_KG = 'Kyrgyz (Kyrgyzstan)';
    public const lb = 'Luxembourgish';
    public const lb_LU = 'Luxembourgish (Luxembourg)';
    public const lg = 'Ganda';
    public const lg_UG = 'Ganda (Uganda)';
    public const ln = 'Lingala';
    public const ln_AO = 'Lingala (Angola)';
    public const ln_CD = 'Lingala (Congo - Kinshasa)';
    public const ln_CF = 'Lingala (Central African Republic)';
    public const ln_CG = 'Lingala (Congo - Brazzaville)';
    public const lo = 'Lao';
    public const lo_LA = 'Lao (Laos)';
    public const lt = 'Lithuanian';
    public const lt_LT = 'Lithuanian (Lithuania)';
    public const lu = 'Luba-Katanga';
    public const lu_CD = 'Luba-Katanga (Congo - Kinshasa)';
    public const lv = 'Latvian';
    public const lv_LV = 'Latvian (Latvia)';
    public const mg = 'Malagasy';
    public const mg_MG = 'Malagasy (Madagascar)';
    public const mi = 'Māori';
    public const mi_NZ = 'Māori (New Zealand)';
    public const mk = 'Macedonian';
    public const mk_MK = 'Macedonian (North Macedonia)';
    public const ml = 'Malayalam';
    public const ml_IN = 'Malayalam (India)';
    public const mn = 'Mongolian';
    public const mn_MN = 'Mongolian (Mongolia)';
    public const mr = 'Marathi';
    public const mr_IN = 'Marathi (India)';
    public const ms = 'Malay';
    public const ms_BN = 'Malay (Brunei)';
    public const ms_ID = 'Malay (Indonesia)';
    public const ms_MY = 'Malay (Malaysia)';
    public const ms_SG = 'Malay (Singapore)';
    public const mt = 'Maltese';
    public const mt_MT = 'Maltese (Malta)';
    public const my = 'Burmese';
    public const my_MM = 'Burmese (Myanmar [Burma])';
    public const nb = 'Norwegian Bokmål';
    public const nb_NO = 'Norwegian Bokmål (Norway)';
    public const nb_SJ = 'Norwegian Bokmål (Svalbard & Jan Mayen)';
    public const nd = 'North Ndebele';
    public const nd_ZW = 'North Ndebele (Zimbabwe)';
    public const ne = 'Nepali';
    public const ne_IN = 'Nepali (India)';
    public const ne_NP = 'Nepali (Nepal)';
    public const nl = 'Dutch';
    public const nl_AW = 'Dutch (Aruba)';
    public const nl_BE = 'Dutch (Belgium)';
    public const nl_BQ = 'Dutch (Caribbean Netherlands)';
    public const nl_CW = 'Dutch (Curaçao)';
    public const nl_NL = 'Dutch (Netherlands)';
    public const nl_SR = 'Dutch (Suriname)';
    public const nl_SX = 'Dutch (Sint Maarten)';
    public const nn = 'Norwegian Nynorsk';
    public const nn_NO = 'Norwegian Nynorsk (Norway)';
    public const no = 'Norwegian';
    public const no_NO = 'Norwegian (Norway)';
    public const om = 'Oromo';
    public const om_ET = 'Oromo (Ethiopia)';
    public const om_KE = 'Oromo (Kenya)';
    public const or = 'Odia';
    public const or_IN = 'Odia (India)';
    public const os = 'Ossetic';
    public const os_GE = 'Ossetic (Georgia)';
    public const os_RU = 'Ossetic (russia)';
    public const pa = 'Punjabi';
    public const pa_Arab = 'Punjabi (Arabic)';
    public const pa_Arab_PK = 'Punjabi (Arabic, Pakistan)';
    public const pa_Guru = 'Punjabi (Gurmukhi)';
    public const pa_Guru_IN = 'Punjabi (Gurmukhi, India)';
    public const pa_IN = 'Punjabi (India)';
    public const pa_PK = 'Punjabi (Pakistan)';
    public const pl = 'Polish';
    public const pl_PL = 'Polish (Poland)';
    public const ps = 'Pashto';
    public const ps_AF = 'Pashto (Afghanistan)';
    public const ps_PK = 'Pashto (Pakistan)';
    public const pt = 'Portuguese';
    public const pt_AO = 'Portuguese (Angola)';
    public const pt_BR = 'Portuguese (Brazil)';
    public const pt_CH = 'Portuguese (Switzerland)';
    public const pt_CV = 'Portuguese (Cape Verde)';
    public const pt_GQ = 'Portuguese (Equatorial Guinea)';
    public const pt_GW = 'Portuguese (Guinea-Bissau)';
    public const pt_LU = 'Portuguese (Luxembourg)';
    public const pt_MO = 'Portuguese (Macao SAR China)';
    public const pt_MZ = 'Portuguese (Mozambique)';
    public const pt_PT = 'Portuguese (Portugal)';
    public const pt_ST = 'Portuguese (São Tomé & Príncipe)';
    public const pt_TL = 'Portuguese (Timor-Leste)';
    public const qu = 'Quechua';
    public const qu_BO = 'Quechua (Bolivia)';
    public const qu_EC = 'Quechua (Ecuador)';
    public const qu_PE = 'Quechua (Peru)';
    public const rm = 'Romansh';
    public const rm_CH = 'Romansh (Switzerland)';
    public const rn = 'Rundi';
    public const rn_BI = 'Rundi (Burundi)';
    public const ro = 'Romanian';
    public const ro_MD = 'Romanian (Moldova)';
    public const ro_RO = 'Romanian (Romania)';
    public const ru = 'russian';
    public const ru_BY = 'russian (Belarus)';
    public const ru_KG = 'russian (Kyrgyzstan)';
    public const ru_KZ = 'russian (Kazakhstan)';
    public const ru_MD = 'russian (Moldova)';
    public const ru_RU = 'russian (russia)';
    public const ru_UA = 'russian (Ukraine)';
    public const rw = 'Kinyarwanda';
    public const rw_RW = 'Kinyarwanda (Rwanda)';
    public const sa = 'Sanskrit';
    public const sa_IN = 'Sanskrit (India)';
    public const sc = 'Sardinian';
    public const sc_IT = 'Sardinian (Italy)';
    public const sd = 'Sindhi';
    public const sd_Arab = 'Sindhi (Arabic)';
    public const sd_Arab_PK = 'Sindhi (Arabic, Pakistan)';
    public const sd_Deva = 'Sindhi (Devanagari)';
    public const sd_Deva_IN = 'Sindhi (Devanagari, India)';
    public const sd_IN = 'Sindhi (India)';
    public const sd_PK = 'Sindhi (Pakistan)';
    public const se = 'Northern Sami';
    public const se_FI = 'Northern Sami (Finland)';
    public const se_NO = 'Northern Sami (Norway)';
    public const se_SE = 'Northern Sami (Sweden)';
    public const sg = 'Sango';
    public const sg_CF = 'Sango (Central African Republic)';
    public const sh = 'Serbo-Croatian';
    public const sh_BA = 'Serbo-Croatian (Bosnia & Herzegovina)';
    public const si = 'Sinhala';
    public const si_LK = 'Sinhala (Sri Lanka)';
    public const sk = 'Slovak';
    public const sk_SK = 'Slovak (Slovakia)';
    public const sl = 'Slovenian';
    public const sl_SI = 'Slovenian (Slovenia)';
    public const sn = 'Shona';
    public const sn_ZW = 'Shona (Zimbabwe)';
    public const so = 'Somali';
    public const so_DJ = 'Somali (Djibouti)';
    public const so_ET = 'Somali (Ethiopia)';
    public const so_KE = 'Somali (Kenya)';
    public const so_SO = 'Somali (Somalia)';
    public const sq = 'Albanian';
    public const sq_AL = 'Albanian (Albania)';
    public const sq_MK = 'Albanian (North Macedonia)';
    public const sr = 'Serbian';
    public const sr_BA = 'Serbian (Bosnia & Herzegovina)';
    public const sr_Cyrl = 'Serbian (Cyrillic)';
    public const sr_Cyrl_BA = 'Serbian (Cyrillic, Bosnia & Herzegovina)';
    public const sr_Cyrl_ME = 'Serbian (Cyrillic, Montenegro)';
    public const sr_Cyrl_RS = 'Serbian (Cyrillic, Serbia)';
    public const sr_Latn = 'Serbian (Latin)';
    public const sr_Latn_BA = 'Serbian (Latin, Bosnia & Herzegovina)';
    public const sr_Latn_ME = 'Serbian (Latin, Montenegro)';
    public const sr_Latn_RS = 'Serbian (Latin, Serbia)';
    public const sr_ME = 'Serbian (Montenegro)';
    public const sr_RS = 'Serbian (Serbia)';
    public const su = 'Sundanese';
    public const su_ID = 'Sundanese (Indonesia)';
    public const su_Latn = 'Sundanese (Latin)';
    public const su_Latn_ID = 'Sundanese (Latin, Indonesia)';
    public const sv = 'Swedish';
    public const sv_AX = 'Swedish (Åland Islands)';
    public const sv_FI = 'Swedish (Finland)';
    public const sv_SE = 'Swedish (Sweden)';
    public const sw = 'Swahili';
    public const sw_CD = 'Swahili (Congo - Kinshasa)';
    public const sw_KE = 'Swahili (Kenya)';
    public const sw_TZ = 'Swahili (Tanzania)';
    public const sw_UG = 'Swahili (Uganda)';
    public const ta = 'Tamil';
    public const ta_IN = 'Tamil (India)';
    public const ta_LK = 'Tamil (Sri Lanka)';
    public const ta_MY = 'Tamil (Malaysia)';
    public const ta_SG = 'Tamil (Singapore)';
    public const te = 'Telugu';
    public const te_IN = 'Telugu (India)';
    public const tg = 'Tajik';
    public const tg_TJ = 'Tajik (Tajikistan)';
    public const th = 'Thai';
    public const th_TH = 'Thai (Thailand)';
    public const ti = 'Tigrinya';
    public const ti_ER = 'Tigrinya (Eritrea)';
    public const ti_ET = 'Tigrinya (Ethiopia)';
    public const tk = 'Turkmen';
    public const tk_TM = 'Turkmen (Turkmenistan)';
    public const tl = 'Tagalog';
    public const tl_PH = 'Tagalog (Philippines)';
    public const to = 'Tongan';
    public const to_TO = 'Tongan (Tonga)';
    public const tr = 'Turkish';
    public const tr_CY = 'Turkish (Cyprus)';
    public const tr_TR = 'Turkish (Turkey)';
    public const tt = 'Tatar';
    public const tt_RU = 'Tatar (russia)';
    public const ug = 'Uyghur';
    public const ug_CN = 'Uyghur (China)';
    public const uk = 'Ukrainian';
    public const uk_UA = 'Ukrainian (Ukraine)';
    public const ur = 'Urdu';
    public const ur_IN = 'Urdu (India)';
    public const ur_PK = 'Urdu (Pakistan)';
    public const uz = 'Uzbek';
    public const uz_AF = 'Uzbek (Afghanistan)';
    public const uz_Arab = 'Uzbek (Arabic)';
    public const uz_Arab_AF = 'Uzbek (Arabic, Afghanistan)';
    public const uz_Cyrl = 'Uzbek (Cyrillic)';
    public const uz_Cyrl_UZ = 'Uzbek (Cyrillic, Uzbekistan)';
    public const uz_Latn = 'Uzbek (Latin)';
    public const uz_Latn_UZ = 'Uzbek (Latin, Uzbekistan)';
    public const uz_UZ = 'Uzbek (Uzbekistan)';
    public const vi = 'Vietnamese';
    public const vi_VN = 'Vietnamese (Vietnam)';
    public const wo = 'Wolof';
    public const wo_SN = 'Wolof (Senegal)';
    public const xh = 'Xhosa';
    public const xh_ZA = 'Xhosa (South Africa)';
    public const yi = 'Yiddish';
    public const yi_001 = 'Yiddish (world)';
    public const yo = 'Yoruba';
    public const yo_BJ = 'Yoruba (Benin)';
    public const yo_NG = 'Yoruba (Nigeria)';
    public const zh = 'Chinese';
    public const zh_CN = 'Chinese (China)';
    public const zh_HK = 'Chinese (Hong Kong SAR China)';
    public const zh_Hans = 'Chinese (Simplified)';
    public const zh_Hans_CN = 'Chinese (Simplified, China)';
    public const zh_Hans_HK = 'Chinese (Simplified, Hong Kong SAR China)';
    public const zh_Hans_MO = 'Chinese (Simplified, Macao SAR China)';
    public const zh_Hans_SG = 'Chinese (Simplified, Singapore)';
    public const zh_Hant = 'Chinese (Traditional)';
    public const zh_Hant_HK = 'Chinese (Traditional, Hong Kong SAR China)';
    public const zh_Hant_MO = 'Chinese (Traditional, Macao SAR China)';
    public const zh_Hant_TW = 'Chinese (Traditional, Taiwan)';
    public const zh_MO = 'Chinese (Macao SAR China)';
    public const zh_SG = 'Chinese (Singapore)';
    public const zh_TW = 'Chinese (Taiwan)';
    public const zu = 'Zulu';
    public const zu_ZA = 'Zulu (South Africa)';
    # endregion


    /**
     * The list of all locales.
     *
     * This is a cache for the {@see self::getLocaleList()} method.
     */
    private static array $localesList;

    /**
     * The list of all locale keys.
     *
     * This is a cache for the {@see self::getLocaleKeysList()} method.
     */
    private static array $localeKeysList;


    /**
     * Returns the locale key for the given locale name.
     *
     * Example getLocaleKey( 'English (United States)' ) will return 'en_US' {@see self::en_US}
     *
     * @param string $localeName The name of the locale.
     *
     * @throws UndefinedLocaleKeyException If the given locale name is not defined.
     *
     * @return string The key for the given locale name.
     */
    public static function getLocaleKey( string $localeName ): string
    {
        if ( self::checkLocaleName( $localeName ) === false )
            throw new UndefinedLocaleKeyException( $localeName );

        return self::getLocaleKeysList()[ $localeName ];
    }

    /**
     * Checks if the given locale name is defined.
     *
     * Example checkLocaleName( 'English (United States)' ) will return true {@see self::en_US}
     *
     * @param string $localeName The name of the locale to check.
     *
     * @return bool true if the locale name is defined, false otherwise.
     */
    public static function checkLocaleName( string $localeName ): bool
    {
        return array_key_exists( $localeName, self::getLocaleKeysList() );
    }

    /**
     * Returns the list of locale keys, where each key corresponds to a locale name.
     *
     * Example getLocaleNamesList() will return [ 'English (United States)' => 'en_US', ... ] {@see self::en_US}
     *
     * @return array The list of locale keys.
     */
    public static function getLocaleKeysList(): array
    {
        if ( isset( self::$localeKeysList ) === false ) {
            if ( ca()->get( 'locale_keys_list') === null )
                self::setLocaleKeysList();
            else
                self::$localeKeysList = j_decode( ca()->get( 'locale_keys_list' ), true );
        }

        return self::$localeKeysList;
    }

    /**
     * Populates the cache with the locale keys, obtained by inverting the list of locale names.
     *
     * @internal Must only be used once per request to populate the cache.
     */
    private static function setLocaleKeysList(): void
    {
        self::$localeKeysList = array_flip( self::getLocaleNamesList() );

        ca()->set( 'locale_keys_list', j_encode( self::$localeKeysList ) );
    }

    /**
     * Returns the list of locale names.
     *
     * Example getLocaleNamesList() will return [ 'en_US' => 'English (United States)', ... ] {@see self::en_US}
     *
     * @return array The list of locale names.
     */
    public static function getLocaleNamesList(): array
    {
        if ( isset( self::$localesList ) === false ) {
            if ( ca()->get( 'locale_names_list') === null )
                self::setLocaleNamesList();
            else
                self::$localesList = j_decode( ca()->get( 'locale_names_list' ), true );
        }

        return self::$localesList;
    }

    /**
     * Populates the cache with the locale names, obtained using reflection.
     *
     * @internal Must only be used once per request to populate the cache.
     */
    private static function setLocaleNamesList(): void
    {
        self::$localesList = ( new ReflectionClass( self::class ) )
            ->getConstants( ReflectionProperty::IS_PUBLIC );

        ca()->set( 'locale_names_list', j_encode( self::$localesList ) );
    }

    /**
     * Returns the locale name for the given locale key.
     *
     * Example getLocaleName( 'en_US' ) will return 'English (United States)' {@link self::en_US}
     *
     * @param string $localeKey The key of the locale.
     *
     * @throws UndefinedLocaleNameException If the given locale key is not defined.
     *
     * @return string The name of the locale with the given key.
     */
    public static function getLocaleName( string $localeKey ): string
    {
        if ( self::checkLocaleKey( $localeKey ) === false )
            throw new UndefinedLocaleNameException( $localeKey );

        return self::getLocaleNamesList()[ $localeKey ];
    }

    /**
     * Checks if the given locale key is defined.
     *
     * Example checkLocaleKey( 'en_US' ) will return true {@link self::en_US}
     *
     * @param string $localeKey The key of the locale to check.
     *
     * @return bool true if the locale key is defined, false otherwise.
     */
    public static function checkLocaleKey( string $localeKey ): bool
    {
        return array_key_exists( $localeKey, self::getLocaleNamesList() );
    }

}
