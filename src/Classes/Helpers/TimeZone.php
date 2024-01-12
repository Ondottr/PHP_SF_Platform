<?php /** @noinspection PhpUnused @noinspection SpellCheckingInspection @noinspection PhpConstantNamingConventionInspection */
declare( strict_types=1 );
/*
 * Copyright © 2018-2024, Nations Original Sp. z o.o. <contact@nations-original.com>
 *
 * Permission to use, copy, modify, and/or distribute this software for any purpose with or without fee is hereby
 * granted, provided that the above copyright notice and this permission notice appear in all copies.
 *
 * THE SOFTWARE IS PROVIDED \"AS IS\" AND THE AUTHOR DISCLAIMS ALL WARRANTIES WITH REGARD TO THIS SOFTWARE
 * INCLUDING ALL IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS. IN NO EVENT SHALL THE AUTHOR BE
 * LIABLE FOR ANY SPECIAL, DIRECT, INDIRECT, OR CONSEQUENTIAL DAMAGES OR ANY DAMAGES WHATSOEVER
 * RESULTING FROM LOSS OF USE, DATA OR PROFITS, WHETHER IN AN ACTION OF CONTRACT, NEGLIGENCE OR OTHER
 * TORTIOUS ACTION, ARISING OUT OF OR IN CONNECTION WITH THE USE OR PERFORMANCE OF THIS SOFTWARE.
 */

namespace PHP_SF\System\Classes\Helpers;

class TimeZone
{

    # region Timezone constants
    public const CI            = [ 'name' => 'Africa/Abidjan', 'offset' => '+00:00' ];
    public const GH            = [ 'name' => 'Africa/Accra', 'offset' => '+00:00' ];
    public const GM            = [ 'name' => 'Africa/Banjul', 'offset' => '+00:00' ];
    public const GN            = [ 'name' => 'Africa/Conakry', 'offset' => '+00:00' ];
    public const ML            = [
        'bamako'   => [ 'name' => 'Africa/Bamako', 'offset' => '+00:00' ],
        'timbuktu' => [ 'name' => 'Africa/Timbuktu', 'offset' => '+00:00' ],
    ];
    public const ET            = [ 'name' => 'Africa/Addis_Ababa', 'offset' => '+03:00' ];
    public const DZ            = [ 'name' => 'Africa/Algiers', 'offset' => '+01:00' ];
    public const ER            = [
        'asmara'  => [ 'name' => 'Africa/Asmara', 'offset' => '+03:00' ],
        'asmera'  => [ 'name' => 'Africa/Asmera', 'offset' => '+03:00' ],
        'nairobi' => [ 'name' => 'Africa/Nairobi', 'offset' => '+03:00' ],
    ];
    public const CF            = [ 'name' => 'Africa/Bangui', 'offset' => '+01:00' ];
    public const GW            = [ 'name' => 'Africa/Bissau', 'offset' => '+00:00' ];
    public const MW            = [ 'name' => 'Africa/Blantyre', 'offset' => '+02:00' ];
    public const CG            = [ 'name' => 'Africa/Brazzaville', 'offset' => '+01:00' ];
    public const BI            = [ 'name' => 'Africa/Bujumbura', 'offset' => '+02:00' ];
    public const EG            = [
        'cairo' => [ 'name' => 'Africa/Cairo', 'offset' => '+02:00' ],
        'egypt' => [ 'name' => 'Egypt', 'offset' => '+02:00' ],
    ];
    public const MA            = [ 'name' => 'Africa/Casablanca', 'offset' => '+01:00' ];
    public const ES            = [
        'ceuta'  => [ 'name' => 'Africa/Ceuta', 'offset' => '+01:00' ],
        'canary' => [ 'name' => 'Atlantic/Canary', 'offset' => '+00:00' ],
        'madrid' => [ 'name' => 'Europe/Madrid', 'offset' => '+01:00' ],
    ];
    public const SN            = [ 'name' => 'Africa/Dakar', 'offset' => '+00:00' ];
    public const TZ            = [ 'name' => 'Africa/Dar_es_Salaam', 'offset' => '+03:00' ];
    public const DJ            = [ 'name' => 'Africa/Djibouti', 'offset' => '+03:00' ];
    public const CM            = [ 'name' => 'Africa/Douala', 'offset' => '+01:00' ];
    public const EH            = [ 'name' => 'Africa/El_Aaiun', 'offset' => '+01:00' ];
    public const SL            = [ 'name' => 'Africa/Freetown', 'offset' => '+00:00' ];
    public const BW            = [ 'name' => 'Africa/Gaborone', 'offset' => '+02:00' ];
    public const ZW            = [ 'name' => 'Africa/Harare', 'offset' => '+02:00' ];
    public const ZA            = [ 'name' => 'Africa/Johannesburg', 'offset' => '+02:00' ];
    public const LS            = [ 'name' => 'Africa/Maseru', 'offset' => '+02:00' ];
    public const SZ            = [ 'name' => 'Africa/Mbabane', 'offset' => '+02:00' ];
    public const SS            = [ 'name' => 'Africa/Juba', 'offset' => '+02:00' ];
    public const UG            = [ 'name' => 'Africa/Kampala', 'offset' => '+03:00' ];
    public const SD            = [ 'name' => 'Africa/Khartoum', 'offset' => '+02:00' ];
    public const RW            = [ 'name' => 'Africa/Kigali', 'offset' => '+02:00' ];
    public const CD            = [
        'kinshasa'   => [ 'name' => 'Africa/Kinshasa', 'offset' => '+01:00' ],
        'lubumbashi' => [ 'name' => 'Africa/Lubumbashi', 'offset' => '+02:00' ],
    ];
    public const NG            = [ 'name' => 'Africa/Lagos', 'offset' => '+01:00' ];
    public const GA            = [ 'name' => 'Africa/Libreville', 'offset' => '+01:00' ];
    public const TG            = [ 'name' => 'Africa/Lome', 'offset' => '+00:00' ];
    public const AO            = [ 'name' => 'Africa/Luanda', 'offset' => '+01:00' ];
    public const ZM            = [ 'name' => 'Africa/Lusaka', 'offset' => '+02:00' ];
    public const GQ            = [ 'name' => 'Africa/Malabo', 'offset' => '+01:00' ];
    public const MZ            = [ 'name' => 'Africa/Maputo', 'offset' => '+02:00' ];
    public const SO            = [ 'name' => 'Africa/Mogadishu', 'offset' => '+03:00' ];
    public const LR            = [ 'name' => 'Africa/Monrovia', 'offset' => '+00:00' ];
    public const KE            = [ 'name' => 'Africa/Nairobi', 'offset' => '+03:00' ];
    public const TD            = [ 'name' => 'Africa/Ndjamena', 'offset' => '+01:00' ];
    public const NE            = [ 'name' => 'Africa/Niamey', 'offset' => '+01:00' ];
    public const MR            = [ 'name' => 'Africa/Nouakchott', 'offset' => '+00:00' ];
    public const BF            = [ 'name' => 'Africa/Ouagadougou', 'offset' => '+00:00' ];
    public const BJ            = [ 'name' => 'Africa/Porto-Novo', 'offset' => '+01:00' ];
    public const ST            = [ 'name' => 'Africa/Sao_Tome', 'offset' => '+00:00' ];
    public const LY            = [
        'tripoli' => [ 'name' => 'Africa/Tripoli', 'offset' => '+02:00' ],
        'libia'   => [ 'name' => 'Libya', 'offset' => '+02:00' ],
    ];
    public const TN            = [ 'name' => 'Africa/Tunis', 'offset' => '+01:00' ];
    public const NA            = [ 'name' => 'Africa/Windhoek', 'offset' => '+02:00' ];
    public const US            = [
        'adak'                   => [ 'name' => 'America/Adak', 'offset' => '-10:00' ],
        'anchorage'              => [ 'name' => 'America/Anchorage', 'offset' => '-09:00' ],
        'atka'                   => [ 'name' => 'America/Atka', 'offset' => '-10:00' ],
        'boise'                  => [ 'name' => 'America/Boise', 'offset' => '-07:00' ],
        'chicago'                => [ 'name' => 'America/Chicago', 'offset' => '-06:00' ],
        'denver'                 => [ 'name' => 'America/Denver', 'offset' => '-07:00' ],
        'detroit'                => [ 'name' => 'America/Detroit', 'offset' => '-05:00' ],
        'fort_wayne'             => [ 'name' => 'America/Fort_Wayne', 'offset' => '-05:00' ],
        'indiana_indianapolis'   => [ 'name' => 'America/Indiana/Indianapolis', 'offset' => '-05:00' ],
        'indiana_knox'           => [ 'name' => 'America/Indiana/Knox', 'offset' => '-06:00' ],
        'indiana_marengo'        => [ 'name' => 'America/Indiana/Marengo', 'offset' => '-05:00' ],
        'indiana_petersburg'     => [ 'name' => 'America/Indiana/Petersburg', 'offset' => '-05:00' ],
        'indiana_tell_city'      => [ 'name' => 'America/Indiana/Tell_City', 'offset' => '-06:00' ],
        'indiana_vevay'          => [ 'name' => 'America/Indiana/Vevay', 'offset' => '-05:00' ],
        'indiana_vincennes'      => [ 'name' => 'America/Indiana/Vincennes', 'offset' => '-05:00' ],
        'indiana_winamac'        => [ 'name' => 'America/Indiana/Winamac', 'offset' => '-05:00' ],
        'indianapolis'           => [ 'name' => 'America/Indianapolis', 'offset' => '-05:00' ],
        'juneau'                 => [ 'name' => 'America/Juneau', 'offset' => '-09:00' ],
        'kentucky_louisville'    => [ 'name' => 'America/Kentucky/Louisville', 'offset' => '-05:00' ],
        'kentucky_monticello'    => [ 'name' => 'America/Kentucky/Monticello', 'offset' => '-05:00' ],
        'knox_in'                => [ 'name' => 'America/Knox_IN', 'offset' => '-06:00' ],
        'los_angeles'            => [ 'name' => 'America/Los_Angeles', 'offset' => '-08:00' ],
        'louisville'             => [ 'name' => 'America/Louisville', 'offset' => '-05:00' ],
        'menominee'              => [ 'name' => 'America/Menominee', 'offset' => '-06:00' ],
        'metlakatla'             => [ 'name' => 'America/Metlakatla', 'offset' => '-08:00' ],
        'new_york'               => [ 'name' => 'America/New_York', 'offset' => '-05:00' ],
        'nome'                   => [ 'name' => 'America/Nome', 'offset' => '-09:00' ],
        'north_dakota_beulah'    => [ 'name' => 'America/North_Dakota/Beulah', 'offset' => '-06:00' ],
        'north_dakota_center'    => [ 'name' => 'America/North_Dakota/Center', 'offset' => '-06:00' ],
        'north_dakota_new_salem' => [ 'name' => 'America/North_Dakota/New_Salem', 'offset' => '-06:00' ],
        'phoenix'                => [ 'name' => 'America/Phoenix', 'offset' => '-07:00' ],
        'shiprock'               => [ 'name' => 'America/Shiprock', 'offset' => '-07:00' ],
        'sitka'                  => [ 'name' => 'America/Sitka', 'offset' => '-09:00' ],
        'yakutat'                => [ 'name' => 'America/Yakutat', 'offset' => '-09:00' ],
        'navajo'                 => [ 'name' => 'Navajo', 'offset' => '-07:00' ],
        'honolulu'               => [ 'name' => 'Pacific/Honolulu', 'offset' => '-10:00' ],
        'alaska'                 => [ 'name' => 'US/Alaska', 'offset' => '-09:00' ],
        'aleutian'               => [ 'name' => 'US/Aleutian', 'offset' => '-10:00' ],
        'arizona'                => [ 'name' => 'US/Arizona', 'offset' => '-07:00' ],
        'central'                => [ 'name' => 'US/Central', 'offset' => '-06:00' ],
        'east_indiana'           => [ 'name' => 'US/East-Indiana', 'offset' => '-05:00' ],
        'eastern'                => [ 'name' => 'US/Eastern', 'offset' => '-05:00' ],
        'hawaii'                 => [ 'name' => 'US/Hawaii', 'offset' => '-10:00' ],
        'indiana_starke'         => [ 'name' => 'US/Indiana-Starke', 'offset' => '-05:00' ],
        'michigan'               => [ 'name' => 'US/Michigan', 'offset' => '-05:00' ],
        'mountain'               => [ 'name' => 'US/Mountain', 'offset' => '-07:00' ],
        'pacific'                => [ 'name' => 'US/Pacific', 'offset' => '-08:00' ],
    ];
    public const AI            = [ 'name' => 'America/Anguilla', 'offset' => '-04:00' ];
    public const AG            = [ 'name' => 'America/Antigua', 'offset' => '-04:00' ];
    public const AR            = [
        'argentina_buenos_aires'    => [ 'name' => 'America/Argentina/Buenos_Aires', 'offset' => '-03:00' ],
        'argentina_catamarca'       => [ 'name' => 'America/Argentina/Catamarca', 'offset' => '-03:00' ],
        'argentina_comod_rivadavia' => [ 'name' => 'America/Argentina/ComodRivadavia', 'offset' => '-03:00' ],
        'argentina_cordoba'         => [ 'name' => 'America/Argentina/Cordoba', 'offset' => '-03:00' ],
        'argentina_jujuy'           => [ 'name' => 'America/Argentina/Jujuy', 'offset' => '-03:00' ],
        'argentina_ja_rioja'        => [ 'name' => 'America/Argentina/La_Rioja', 'offset' => '-03:00' ],
        'argentina_mendoza'         => [ 'name' => 'America/Argentina/Mendoza', 'offset' => '-03:00' ],
        'argentina_rio_gallegos'    => [ 'name' => 'America/Argentina/Rio_Gallegos', 'offset' => '-03:00' ],
        'argentina_salta'           => [ 'name' => 'America/Argentina/Salta', 'offset' => '-03:00' ],
        'argentina_san_juan'        => [ 'name' => 'America/Argentina/San_Juan', 'offset' => '-03:00' ],
        'argentina_san_luis'        => [ 'name' => 'America/Argentina/San_Luis', 'offset' => '-03:00' ],
        'argentina_tucuman'         => [ 'name' => 'America/Argentina/Tucuman', 'offset' => '-03:00' ],
        'argentina_usuaia'          => [ 'name' => 'America/Argentina/Ushuaia', 'offset' => '-03:00' ],
        'buenos_aires'              => [ 'name' => 'America/Buenos_Aires', 'offset' => '-03:00' ],
        'catamarca'                 => [ 'name' => 'America/Catamarca', 'offset' => '-03:00' ],
        'cordoba'                   => [ 'name' => 'America/Cordoba', 'offset' => '-03:00' ],
        'jujuy'                     => [ 'name' => 'America/Jujuy', 'offset' => '-03:00' ],
        'mendoza'                   => [ 'name' => 'America/Mendoza', 'offset' => '-03:00' ],
        'rosario'                   => [ 'name' => 'America/Rosario', 'offset' => '-03:00' ],
    ];
    public const AW            = [ 'name' => 'America/Aruba', 'offset' => '-04:00' ];
    public const PY            = [ 'name' => 'America/Asuncion', 'offset' => '-04:00' ];
    public const CA            = [
        'atikokan'      => [ 'name' => 'America/Atikokan', 'offset' => '-05:00' ],
        'blanc_sablon'  => [ 'name' => 'America/Blanc-Sablon', 'offset' => '-04:00' ],
        'cambridge_bay' => [ 'name' => 'America/Cambridge_Bay', 'offset' => '-05:00' ],
        'coral_harbour' => [ 'name' => 'America/Coral_Harbour', 'offset' => '-05:00' ],
        'creston'       => [ 'name' => 'America/Creston', 'offset' => '-05:00' ],
        'dawson'        => [ 'name' => 'America/Dawson', 'offset' => '-06:00' ],
        'dawson_creek'  => [ 'name' => 'America/Dawson_Creek', 'offset' => '-07:00' ],
        'edmonton'      => [ 'name' => 'America/Edmonton', 'offset' => '-07:00' ],
        'fort_nelson'   => [ 'name' => 'America/Fort_Nelson', 'offset' => '-07:00' ],
        'glace_bay'     => [ 'name' => 'America/Glace_Bay', 'offset' => '-04:00' ],
        'goose_bay'     => [ 'name' => 'America/Goose_Bay', 'offset' => '-04:00' ],
        'halifax'       => [ 'name' => 'America/Halifax', 'offset' => '-04:00' ],
        'inuvik'        => [ 'name' => 'America/Inuvik', 'offset' => '-07:00' ],
        'iqaluit'       => [ 'name' => 'America/Iqaluit', 'offset' => '-05:00' ],
        'monctonn'      => [ 'name' => 'America/Moncton', 'offset' => '-04:00' ],
        'montreal'      => [ 'name' => 'America/Montreal', 'offset' => '-05:00' ],
        'nipigon'       => [ 'name' => 'America/Nipigon', 'offset' => '-05:00' ],
        'panama'        => [ 'name' => 'America/Panama', 'offset' => '-05:00' ],
        'pangnirtung'   => [ 'name' => 'America/Pangnirtung', 'offset' => '-05:00' ],
        'rainy_river'   => [ 'name' => 'America/Rainy_River', 'offset' => '-06:00' ],
        'rankin_inlet'  => [ 'name' => 'America/Rankin_Inlet', 'offset' => '-06:00' ],
        'regina'        => [ 'name' => 'America/Regina', 'offset' => '-06:00' ],
        'resolute'      => [ 'name' => 'America/Resolute', 'offset' => '-05:00' ],
        'st_johns'      => [ 'name' => 'America/St_Johns', 'offset' => '-03:30' ],
        'swift_current' => [ 'name' => 'America/Swift_Current', 'offset' => '-06:00' ],
        'thunder_bay'   => [ 'name' => 'America/Thunder_Bay', 'offset' => '-04:00' ],
        'vancouver'     => [ 'name' => 'America/Vancouver', 'offset' => '-07:00' ],
        'whitehorse'    => [ 'name' => 'America/Whitehorse', 'offset' => '-07:00' ],
        'winipeg'       => [ 'name' => 'America/Winnipeg', 'offset' => '-06:00' ],
        'yellowknife'   => [ 'name' => 'America/Yellowknife', 'offset' => '-07:00' ],
        'atlantic'      => [ 'name' => 'Canada/Atlantic', 'offset' => '-04:00' ],
        'central'       => [ 'name' => 'Canada/Central', 'offset' => '-06:00' ],
        'eastern'       => [ 'name' => 'Canada/Eastern', 'offset' => '-05:00' ],
        'mountain'      => [ 'name' => 'Canada/Mountain', 'offset' => '-07:00' ],
        'newfoundland'  => [ 'name' => 'Canada/Newfoundland', 'offset' => '-03:30' ],
        'pacific'       => [ 'name' => 'Canada/Pacific', 'offset' => '-08:00' ],
        'saskatchewan'  => [ 'name' => 'Canada/Saskatchewan', 'offset' => '-06:00' ],
        'yukon'         => [ 'name' => 'Canada/Yukon', 'offset' => '-08:00' ],
    ];
    public const BR            = [
        'araguaina'    => [ 'name' => 'America/Araguaina', 'offset' => '-03:00' ],
        'bahia'        => [ 'name' => 'America/Bahia', 'offset' => '-03:00' ],
        'belem'        => [ 'name' => 'America/Belem', 'offset' => '-03:00' ],
        'boa_vista'    => [ 'name' => 'America/Boa_Vista', 'offset' => '-04:00' ],
        'campo_grande' => [ 'name' => 'America/Campo_Grande', 'offset' => '-04:00' ],
        'cuiaba'       => [ 'name' => 'America/Cuiaba', 'offset' => '-04:00' ],
        'eirunepe'     => [ 'name' => 'America/Eirunepe', 'offset' => '-05:00' ],
        'fortaleza'    => [ 'name' => 'America/Fortaleza', 'offset' => '-03:00' ],
        'maceio'       => [ 'name' => 'America/Maceio', 'offset' => '-03:00' ],
        'manaus'       => [ 'name' => 'America/Manaus', 'offset' => '-04:00' ],
        'noronha'      => [ 'name' => 'America/Noronha', 'offset' => '-02:00' ],
        'porto_acre'   => [ 'name' => 'America/Porto_Acre', 'offset' => '-05:00' ],
        'porto_velho'  => [ 'name' => 'America/Porto_Velho', 'offset' => '-04:00' ],
        'recife'       => [ 'name' => 'America/Recife', 'offset' => '-03:00' ],
        'rio_branco'   => [ 'name' => 'America/Rio_Branco', 'offset' => '-05:00' ],
        'santarem'     => [ 'name' => 'America/Santarem', 'offset' => '-03:00' ],
        'san_paulo'    => [ 'name' => 'America/Sao_Paulo', 'offset' => '-03:00' ],
        'acre'         => [ 'name' => 'Brazil/Acre', 'offset' => '-05:00' ],
        'de_noronha'   => [ 'name' => 'Brazil/DeNoronha', 'offset' => '-02:00' ],
        'east'         => [ 'name' => 'Brazil/East', 'offset' => '-04:00' ],
        'west'         => [ 'name' => 'Brazil/West', 'offset' => '-05:00' ],
    ];
    public const MX            = [
        'bahia_banderas' => [ 'name' => 'America/Bahia_Banderas', 'offset' => '-06:00' ],
        'cancun'         => [ 'name' => 'America/Cancun', 'offset' => '-05:00' ],
        'chihuahua'      => [ 'name' => 'America/Chihuahua', 'offset' => '-07:00' ],
        'ensenada'       => [ 'name' => 'America/Ensenada', 'offset' => '-08:00' ],
        'hermosillo'     => [ 'name' => 'America/Hermosillo', 'offset' => '-07:00' ],
        'matamoros'      => [ 'name' => 'America/Matamoros', 'offset' => '-06:00' ],
        'mazatlan'       => [ 'name' => 'America/Mazatlan', 'offset' => '-07:00' ],
        'merida'         => [ 'name' => 'America/Merida', 'offset' => '-06:00' ],
        'mexico_city'    => [ 'name' => 'America/Mexico_City', 'offset' => '-05:00' ],
        'monterrey'      => [ 'name' => 'America/Monterrey', 'offset' => '-06:00' ],
        'ojinaga'        => [ 'name' => 'America/Ojinaga', 'offset' => '-07:00' ],
        'santa_isabel'   => [ 'name' => 'America/Santa_Isabel', 'offset' => '-08:00' ],
        'tijuana'        => [ 'name' => 'America/Tijuana', 'offset' => '-08:00' ],
        'baja_norte'     => [ 'name' => 'Mexico/BajaNorte', 'offset' => '-08:00' ],
        'baja_sur'       => [ 'name' => 'Mexico/BajaSur', 'offset' => '-07:00' ],
        'general'        => [ 'name' => 'Mexico/General', 'offset' => '-06:00' ],
    ];
    public const BB            = [ 'name' => 'America/Barbados', 'offset' => '-04:00' ];
    public const BZ            = [ 'name' => 'America/Belize', 'offset' => '-06:00' ];
    public const CO            = [ 'name' => 'America/Bogota', 'offset' => '-05:00' ];
    public const VE            = [ 'name' => 'America/Caracas', 'offset' => '-04:00' ];
    public const GF            = [ 'name' => 'America/Cayenne', 'offset' => '-03:00' ];
    public const KY            = [ 'name' => 'America/Cayman', 'offset' => '-05:00' ];
    public const CR            = [ 'name' => 'America/Costa_Rica', 'offset' => '-06:00' ];
    public const CW            = [ 'name' => 'America/Curacao', 'offset' => '-04:00' ];
    public const GL            = [
        'danmarkshavn' => [ 'name' => 'America/Danmarkshavn', 'offset' => '+00:00' ],
        'godthab'      => [ 'name' => 'America/Godthab', 'offset' => '-03:00' ],
        'nuuk'         => [ 'name' => 'America/Nuuk', 'offset' => '-03:00' ],
        'thule'        => [ 'name' => 'America/Thule', 'offset' => '-04:00' ],
        'scoresbysund' => [ 'name' => 'America/Scoresbysund', 'offset' => '-01:00' ],
    ];
    public const DM            = [ 'name' => 'America/Dominica', 'offset' => '-04:00' ];
    public const SV            = [ 'name' => 'America/El_Salvador', 'offset' => '-06:00' ];
    public const TC            = [ 'name' => 'America/Grand_Turk', 'offset' => '-05:00' ];
    public const GD            = [ 'name' => 'America/Grenada', 'offset' => '-04:00' ];
    public const GP            = [ 'name' => 'America/Guadeloupe', 'offset' => '-04:00' ];
    public const GT            = [ 'name' => 'America/Guatemala', 'offset' => '-06:00' ];
    public const EC            = [
        'guayaquil' => [ 'name' => 'America/Guayaquil', 'offset' => '-05:00' ],
        'galapagos' => [ 'name' => 'Pacific/Galapagos', 'offset' => '-06:00' ],
    ];
    public const GY            = [ 'name' => 'America/Guyana', 'offset' => '-04:00' ];
    public const CU            = [
        'havana' => [ 'name' => 'America/Havana', 'offset' => '-05:00' ],
        'cuba'   => [ 'name' => 'Cuba', 'offset' => '-05:00' ],
    ];
    public const JM            = [ 'name' => 'America/Jamaica', 'offset' => '-05:00' ];
    public const BQ            = [ 'name' => 'America/Kralendijk', 'offset' => '-04:00' ];
    public const BO            = [ 'name' => 'America/La_Paz', 'offset' => '-04:00' ];
    public const PE            = [ 'name' => 'America/Lima', 'offset' => '-05:00' ];
    public const SX            = [ 'name' => 'America/Lower_Princes', 'offset' => '-04:00' ];
    public const NI            = [ 'name' => 'America/Managua', 'offset' => '-06:00' ];
    public const MF            = [ 'name' => 'America/Marigot', 'offset' => '-04:00' ];
    public const MQ            = [ 'name' => 'America/Martinique', 'offset' => '-04:00' ];
    public const PM            = [ 'name' => 'America/Miquelon', 'offset' => '-03:00' ];
    public const UY            = [ 'name' => 'America/Montevideo', 'offset' => '-03:00' ];
    public const MS            = [ 'name' => 'America/Montserrat', 'offset' => '-04:00' ];
    public const BS            = [
        'nassau'  => [ 'name' => 'America/Nassau', 'offset' => '-05:00' ],
        'toronto' => [ 'name' => 'America/Toronto', 'offset' => '-05:00' ],
    ];
    public const PA            = [ 'name' => 'America/Panama', 'offset' => '-05:00' ];
    public const SR            = [ 'name' => 'America/Paramaribo', 'offset' => '-03:00' ];
    public const HT            = [ 'name' => 'America/Port-au-Prince', 'offset' => '-05:00' ];
    public const TT            = [ 'name' => 'America/Port_of_Spain', 'offset' => '-04:00' ];
    public const PR            = [ 'name' => 'America/Puerto_Rico', 'offset' => '-04:00' ];
    public const CL            = [
        'punta_arenas'  => [ 'name' => 'America/Punta_Arenas', 'offset' => '-03:00' ],
        'santiago'      => [ 'name' => 'America/Santiago', 'offset' => '-04:00' ],
        'continental'   => [ 'name' => 'Chile/Continental', 'offset' => '-04:00' ],
        'easter_island' => [ 'name' => 'Chile/EasterIsland', 'offset' => '-05:00' ],
        'easter'        => [ 'name' => 'Pacific/Easter', 'offset' => '-06:00' ],
    ];
    public const DO            = [ 'name' => 'America/Santo_Domingo', 'offset' => '-04:00' ];
    public const BL            = [ 'name' => 'America/St_Barthelemy', 'offset' => '-04:00' ];
    public const KN            = [ 'name' => 'America/St_Kitts', 'offset' => '-04:00' ];
    public const LC            = [ 'name' => 'America/St_Lucia', 'offset' => '-04:00' ];
    public const VI            = [
        'st_thomas' => [ 'name' => 'America/St_Thomas', 'offset' => '-04:00' ],
        'virgin'    => [ 'name' => 'America/Virgin', 'offset' => '-04:00' ],
    ];
    public const VC            = [ 'name' => 'America/St_Vincent', 'offset' => '-04:00' ];
    public const HN            = [ 'name' => 'America/Tegucigalpa', 'offset' => '-06:00' ];
    public const VG            = [ 'name' => 'America/Tortola', 'offset' => '-04:00' ];
    public const AQ            = [
        'casey'            => [ 'name' => 'Antarctica/Casey', 'offset' => '+11:00' ],
        'davis'            => [ 'name' => 'Antarctica/Davis', 'offset' => '+07:00' ],
        'dumont_D_Urville' => [ 'name' => 'Antarctica/DumontDUrville', 'offset' => '+10:00' ],
        'mawson'           => [ 'name' => 'Antarctica/Mawson', 'offset' => '+05:00' ],
        'mc_murdo'         => [ 'name' => 'Antarctica/McMurdo', 'offset' => '+12:00' ],
        'palmer'           => [ 'name' => 'Antarctica/Palmer', 'offset' => '-03:00' ],
        'rothera'          => [ 'name' => 'Antarctica/Rothera', 'offset' => '-03:00' ],
        'south_pole'       => [ 'name' => 'Antarctica/South_Pole', 'offset' => '+12:00' ],
        'syowa'            => [ 'name' => 'Antarctica/Syowa', 'offset' => '+03:00' ],
        'vostok'           => [ 'name' => 'Antarctica/Vostok', 'offset' => '+06:00' ],
    ];
    public const AU            = [
        'macquarie'   => [ 'name' => 'Antarctica/Macquarie', 'offset' => '+10:00' ],
        'act'         => [ 'name' => 'Australia/ACT', 'offset' => '+10:00' ],
        'adelaide'    => [ 'name' => 'Australia/Adelaide', 'offset' => '+09:30' ],
        'brisbane'    => [ 'name' => 'Australia/Brisbane', 'offset' => '+10:00' ],
        'broken_hill' => [ 'name' => 'Australia/Broken_Hill', 'offset' => '+09:30' ],
        'canberra'    => [ 'name' => 'Australia/Canberra', 'offset' => '+10:00' ],
        'currie'      => [ 'name' => 'Australia/Currie', 'offset' => '+10:00' ],
        'darwin'      => [ 'name' => 'Australia/Darwin', 'offset' => '+09:30' ],
        'eucla'       => [ 'name' => 'Australia/Eucla', 'offset' => '+08:45' ],
        'hobla'       => [ 'name' => 'Australia/Hobart', 'offset' => '+10:00' ],
        'lhi'         => [ 'name' => 'Australia/LHI', 'offset' => '+10:30' ],
        'lindeman'    => [ 'name' => 'Australia/Lindeman', 'offset' => '+10:00' ],
        'lord_howe'   => [ 'name' => 'Australia/Lord_Howe', 'offset' => '+10:30' ],
        'melbourne'   => [ 'name' => 'Australia/Melbourne', 'offset' => '+10:00' ],
        'north'       => [ 'name' => 'Australia/North', 'offset' => '+09:30' ],
        'nsw'         => [ 'name' => 'Australia/NSW', 'offset' => '+10:00' ],
        'perth'       => [ 'name' => 'Australia/Perth', 'offset' => '+08:00' ],
        'queensland'  => [ 'name' => 'Australia/Queensland', 'offset' => '+10:00' ],
        'south'       => [ 'name' => 'Australia/South', 'offset' => '+09:30' ],
        'sydney'      => [ 'name' => 'Australia/Sydney', 'offset' => '+10:00' ],
        'tasmania'    => [ 'name' => 'Australia/Tasmania', 'offset' => '+10:00' ],
        'victoria'    => [ 'name' => 'Australia/Victoria', 'offset' => '+10:00' ],
        'west'        => [ 'name' => 'Australia/West', 'offset' => '+08:00' ],
        'yancowinna'  => [ 'name' => 'Australia/Yancowinna', 'offset' => '+09:30' ],
    ];
    public const SJ            = [
        'longyearbyen' => [ 'name' => 'Arctic/Longyearbyen', 'offset' => '+01:00' ],
        'jan_mayen'    => [ 'name' => 'Atlantic/Jan_Mayen', 'offset' => '+01:00' ],
    ];
    public const YE            = [ 'name' => 'Asia/Aden', 'offset' => '+03:00' ];
    public const KZ            = [
        'almaty'    => [ 'name' => 'Asia/Almaty', 'offset' => '+06:00' ],
        'aqtau'     => [ 'name' => 'Asia/Aqtau', 'offset' => '+05:00' ],
        'aqtobe'    => [ 'name' => 'Asia/Aqtobe', 'offset' => '+05:00' ],
        'atyrau'    => [ 'name' => 'Asia/Atyrau', 'offset' => '+05:00' ],
        'oral'      => [ 'name' => 'Asia/Oral', 'offset' => '+05:00' ],
        'qostanay'  => [ 'name' => 'Asia/Qostanay', 'offset' => '+06:00' ],
        'qyzylorda' => [ 'name' => 'Asia/Qyzylorda', 'offset' => '+06:00' ],
    ];
    public const JO            = [ 'name' => 'Asia/Amman', 'offset' => '+02:00' ];
    public const RU            = [
        'anadyr'        => [ 'name' => 'Asia/anadyr', 'offset' => '+12:00' ],
        'barnaul'       => [ 'name' => 'Asia/barnaul', 'offset' => '+07:00' ],
        'chita'         => [ 'name' => 'Asia/chita', 'offset' => '+09:00' ],
        'irkutsk'       => [ 'name' => 'Asia/irkutsk', 'offset' => '+08:00' ],
        'kamchatka'     => [ 'name' => 'Asia/kamchatka', 'offset' => '+12:00' ],
        'khanyga'       => [ 'name' => 'Asia/khandyga', 'offset' => '+09:00' ],
        'krasnoyarsk'   => [ 'name' => 'Asia/krasnoyarsk', 'offset' => '+07:00' ],
        'magadan'       => [ 'name' => 'Asia/magadan', 'offset' => '+11:00' ],
        'novokuznetsk'  => [ 'name' => 'Asia/novokuznetsk', 'offset' => '+07:00' ],
        'novosibirsk'   => [ 'name' => 'Asia/novosibirsk', 'offset' => '+07:00' ],
        'omsk'          => [ 'name' => 'Asia/omsk', 'offset' => '+06:00' ],
        'sakhalin'      => [ 'name' => 'Asia/sakhalin', 'offset' => '+11:00' ],
        'srednekolymsk' => [ 'name' => 'Asia/srednekolymsk', 'offset' => '+11:00' ],
        'tomsk'         => [ 'name' => 'Asia/tomsk', 'offset' => '+07:00' ],
        'ust-nera'      => [ 'name' => 'Asia/ust-Nera', 'offset' => '+10:00' ],
        'vladivostok'   => [ 'name' => 'Asia/vladivostok', 'offset' => '+10:00' ],
        'yakutsk'       => [ 'name' => 'Asia/yakutsk', 'offset' => '+09:00' ],
        'yekaterinburd' => [ 'name' => 'Asia/yekaterinburg', 'offset' => '+05:00' ],
        'astrakhan'     => [ 'name' => 'Europe/astrakhan', 'offset' => '+04:00' ],
        'kaliningrad'   => [ 'name' => 'Europe/kaliningrad', 'offset' => '+03:00' ],
        'kirov'         => [ 'name' => 'Europe/kirov', 'offset' => '+03:00' ],
        'moscow'        => [ 'name' => 'Europe/moscow', 'offset' => '+03:00' ],
        'samara'        => [ 'name' => 'Europe/samara', 'offset' => '+04:00' ],
        'saratov'       => [ 'name' => 'Europe/saratov', 'offset' => '+03:00' ],
        'ulyanovsk'     => [ 'name' => 'Europe/ulyanovsk', 'offset' => '+04:00' ],
        'volgograd'     => [ 'name' => 'Europe/volgograd', 'offset' => '+03:00' ],
    ];
    public const TM            = [ 'name' => 'Asia/Ashgabat', 'offset' => '+05:00' ];
    public const IQ            = [ 'name' => 'Asia/Baghdad', 'offset' => '+03:00' ];
    public const BH            = [ 'name' => 'Asia/Bahrain', 'offset' => '+03:00' ];
    public const AZ            = [ 'name' => 'Asia/Baku', 'offset' => '+04:00' ];
    public const TH            = [ 'name' => 'Asia/Bangkok', 'offset' => '+07:00' ];
    public const LB            = [ 'name' => 'Asia/Beirut', 'offset' => '+02:00' ];
    public const KG            = [ 'name' => 'Asia/Bishkek', 'offset' => '+06:00' ];
    public const BN            = [ 'name' => 'Asia/Brunei', 'offset' => '+08:00' ];
    public const IN            = [
        'calcutta' => [ 'name' => 'Asia/Calcutta', 'offset' => '+05:30' ],
        'kolkata'  => [ 'name' => 'Asia/Kolkata', 'offset' => '+05:30' ],
    ];
    public const MN            = [
        'choibalsan'  => [ 'name' => 'Asia/Choibalsan', 'offset' => '+08:00' ],
        'hovd'        => [ 'name' => 'Asia/Hovd', 'offset' => '+07:00' ],
        'ulaanbaatar' => [ 'name' => 'Asia/Ulaanbaatar', 'offset' => '+08:00' ],
        'ulan_batorl' => [ 'name' => 'Asia/Ulan_Bator', 'offset' => '+08:00' ],
    ];
    public const CN            = [
        'chongqing' => [ 'name' => 'Asia/Chongqing', 'offset' => '+08:00' ],
        'harbin'    => [ 'name' => 'Asia/Harbin', 'offset' => '+08:00' ],
        'kashgar'   => [ 'name' => 'Asia/Kashgar', 'offset' => '+06:00' ],
        'shanghai'  => [ 'name' => 'Asia/Shanghai', 'offset' => '+08:00' ],
        'urumqi'    => [ 'name' => 'Asia/Urumqi', 'offset' => '+06:00' ],
    ];
    public const LK            = [ 'name' => 'Asia/Colombo', 'offset' => '+05:30' ];
    public const BD            = [
        'dacca' => [ 'name' => 'Asia/Dacca', 'offset' => '+06:00' ],
        'dhaka' => [ 'name' => 'Asia/Dhaka', 'offset' => '+06:00' ],
    ];
    public const SY            = [ 'name' => 'Asia/Damascus', 'offset' => '+02:00' ];
    public const TL            = [ 'name' => 'Asia/Dili', 'offset' => '+09:00' ];
    public const AE            = [ 'name' => 'Asia/Dubai', 'offset' => '+04:00' ];
    public const TJ            = [ 'name' => 'Asia/Dushanbe', 'offset' => '+05:00' ];
    public const CY            = [
        'famagusta' => [ 'name' => 'Asia/Famagusta', 'offset' => '+02:00' ],
        'nicosia'   => [ 'name' => 'Asia/Nicosia', 'offset' => '+02:00' ],
    ];
    public const PS            = [
        'gaza'   => [ 'name' => 'Asia/Gaza', 'offset' => '+02:00' ],
        'hebron' => [ 'name' => 'Asia/Hebron', 'offset' => '+02:00' ],
    ];
    public const VN            = [
        'ho_chi_minh' => [ 'name' => 'Asia/Ho_Chi_Minh', 'offset' => '+07:00' ],
        'saigon'      => [ 'name' => 'Asia/Saigon', 'offset' => '+07:00' ],
    ];
    public const HK            = [
        'hong_kong' => [ 'name' => 'Asia/Hong_Kong', 'offset' => '+08:00' ],
        'hongkong'  => [ 'name' => 'Hongkong', 'offset' => '+08:00' ],
    ];
    public const TR            = [
        'asia_istanbul'   => [ 'name' => 'Asia/Istanbul', 'offset' => '+03:00' ],
        'europe_istanbul' => [ 'name' => 'Europe/Istanbul', 'offset' => '+03:00' ],
        'turkey'          => [ 'name' => 'Turkey', 'offset' => '+03:00' ],
    ];
    public const ID            = [
        'jakarta'       => [ 'name' => 'Asia/Jakarta', 'offset' => '+07:00' ],
        'jayapura'      => [ 'name' => 'Asia/Jayapura', 'offset' => '+09:00' ],
        'makassar'      => [ 'name' => 'Asia/Makassar', 'offset' => '+08:00' ],
        'pontianak'     => [ 'name' => 'Asia/Pontianak', 'offset' => '+07:00' ],
        'ujung_pandang' => [ 'name' => 'Asia/Ujung_Pandang', 'offset' => '+08:00' ],
    ];
    public const IL            = [
        'jerusalem' => [ 'name' => 'Asia/Jerusalem', 'offset' => '+02:00' ],
        'tel_aviv'  => [ 'name' => 'Asia/Tel_Aviv', 'offset' => '+02:00' ],
        'israel'    => [ 'name' => 'Israel', 'offset' => '+02:00' ],
    ];
    public const AF            = [ 'name' => 'Asia/Kabul', 'offset' => '+04:30' ];
    public const PK            = [ 'name' => 'Asia/Karachi', 'offset' => '+05:00' ];
    public const NP            = [ 'name' => 'Asia/Kathmandu', 'offset' => '+05:45' ];
    public const MY            = [
        'kuala_lumpur' => [ 'name' => 'Asia/Kuala_Lumpur', 'offset' => '+08:00' ],
        'kuching'      => [ 'name' => 'Asia/Kuching', 'offset' => '+08:00' ],
    ];
    public const KW            = [ 'name' => 'Asia/Kuwait', 'offset' => '+03:00' ];
    public const MO            = [ 'name' => 'Asia/Macao', 'offset' => '+08:00' ];
    public const PH            = [ 'name' => 'Asia/Manila', 'offset' => '+08:00' ];
    public const OM            = [ 'name' => 'Asia/Muscat', 'offset' => '+04:00' ];
    public const KH            = [ 'name' => 'Asia/Phnom_Penh', 'offset' => '+07:00' ];
    public const KP            = [ 'name' => 'Asia/Pyongyang', 'offset' => '+09:00' ];
    public const QA            = [ 'name' => 'Asia/Qatar', 'offset' => '+03:00' ];
    public const MM            = [
        'rangoon' => [ 'name' => 'Asia/Rangoon', 'offset' => '+06:30' ],
        'yangon'  => [ 'name' => 'Asia/Yangon', 'offset' => '+06:30' ],
    ];
    public const SA            = [ 'name' => 'Asia/Riyadh', 'offset' => '+03:00' ];
    public const UZ            = [
        'samarkand' => [ 'name' => 'Asia/Samarkand', 'offset' => '+05:00' ],
        'tashkent'  => [ 'name' => 'Asia/Tashkent', 'offset' => '+05:00' ],
    ];
    public const KR            = [
        'seoul' => [ 'name' => 'Asia/Seoul', 'offset' => '+09:00' ],
        'rok'   => [ 'name' => 'ROK', 'offset' => '+09:00' ],
    ];
    public const SG            = [
        'asia_singapore' => [ 'name' => 'Asia/Singapore', 'offset' => '+08:00' ],
        'singapore'      => [ 'name' => 'Singapore', 'offset' => '+08:00' ],
    ];
    public const TW            = [
        'taipei' => [ 'name' => 'Asia/Taipei', 'offset' => '+08:00' ],
        'roc'    => [ 'name' => 'ROC', 'offset' => '+08:00' ],
    ];
    public const GE            = [ 'name' => 'Asia/Tbilisi', 'offset' => '+04:00' ];
    public const IR            = [
        'tehran' => [ 'name' => 'Asia/Tehran', 'offset' => '+03:30' ],
        'iran'   => [ 'name' => 'Iran', 'offset' => '+03:30' ],
    ];
    public const BT            = [
        'thimbu'  => [ 'name' => 'Asia/Thimbu', 'offset' => '+06:00' ],
        'thimphu' => [ 'name' => 'Asia/Thimphu', 'offset' => '+06:00' ],
    ];
    public const JP            = [
        'tokyo' => [ 'name' => 'Asia/Tokyo', 'offset' => '+09:00' ],
        'japan' => [ 'name' => 'Japan', 'offset' => '+09:00' ],
    ];
    public const LA            = [ 'name' => 'Asia/Vientiane', 'offset' => '+07:00' ];
    public const AM            = [ 'name' => 'Asia/Yerevan', 'offset' => '+04:00' ];
    public const PT            = [
        'azores'   => [ 'name' => 'Atlantic/Azores', 'offset' => '-01:00' ],
        'madeira'  => [ 'name' => 'Atlantic/Madeira', 'offset' => '+00:00' ],
        'lisbon'   => [ 'name' => 'Europe/Lisbon', 'offset' => '+00:00' ],
        'portugal' => [ 'name' => 'Portugal', 'offset' => '+00:00' ],
    ];
    public const BM            = [ 'name' => 'Atlantic/Bermuda', 'offset' => '-04:00' ];
    public const CV            = [ 'name' => 'Atlantic/Cape_Verde', 'offset' => '-01:00' ];
    public const FO            = [
        'faeroe' => [ 'name' => 'Atlantic/Faeroe', 'offset' => '+00:00' ],
        'faroe'  => [ 'name' => 'Atlantic/Faroe', 'offset' => '+00:00' ],
    ];
    public const IS            = [
        'reykjavik' => [ 'name' => 'Atlantic/Reykjavik', 'offset' => '+00:00' ],
        'iceland'   => [ 'name' => 'Iceland', 'offset' => '+00:00' ],
    ];
    public const GS            = [ 'name' => 'Atlantic/South_Georgia', 'offset' => '-02:00' ];
    public const SH            = [ 'name' => 'Atlantic/St_Helena', 'offset' => '+00:00' ];
    public const FK            = [ 'name' => 'Atlantic/Stanley', 'offset' => '-03:00' ];
    public const CET           = [ 'name' => 'CET', 'offset' => '+01:00' ];
    public const CST6CDT       = [ 'name' => 'CST6CDT', 'offset' => '-06:00' ];
    public const EET           = [ 'name' => 'EET', 'offset' => '+02:00' ];
    public const IE            = [
        'eire'   => [ 'name' => 'Eire', 'offset' => '+01:00' ],
        'dublin' => [ 'name' => 'Europe/Dublin', 'offset' => '+01:00' ],
    ];
    public const EST           = [ 'name' => 'EST', 'offset' => '-05:00' ];
    public const EST5EDT       = [ 'name' => 'EST5EDT', 'offset' => '-05:00' ];
    public const ETC_GMT       = [ 'name' => 'Etc/GMT', 'offset' => '+00:00' ];
    public const ETC_GMT0      = [ 'name' => 'Etc/GMT+0', 'offset' => '+00:00' ];
    public const ETC_GMT1      = [ 'name' => 'Etc/GMT+1', 'offset' => '-01:00' ];
    public const ETC_GMT10     = [ 'name' => 'Etc/GMT+10', 'offset' => '-10:00' ];
    public const ETC_GMT11     = [ 'name' => 'Etc/GMT+11', 'offset' => '-11:00' ];
    public const ETC_GMT12     = [ 'name' => 'Etc/GMT+12', 'offset' => '-12:00' ];
    public const ETC_GMT2      = [ 'name' => 'Etc/GMT+2', 'offset' => '-02:00' ];
    public const ETC_GMT3      = [ 'name' => 'Etc/GMT+3', 'offset' => '-03:00' ];
    public const ETC_GMT4      = [ 'name' => 'Etc/GMT+4', 'offset' => '-04:00' ];
    public const ETC_GMT5      = [ 'name' => 'Etc/GMT+5', 'offset' => '-05:00' ];
    public const ETC_GMT6      = [ 'name' => 'Etc/GMT+6', 'offset' => '-06:00' ];
    public const ETC_GMT7      = [ 'name' => 'Etc/GMT+7', 'offset' => '-07:00' ];
    public const ETC_GMT8      = [ 'name' => 'Etc/GMT+8', 'offset' => '-08:00' ];
    public const ETC_GMT9      = [ 'name' => 'Etc/GMT+9', 'offset' => '-09:00' ];
    public const ETC_GMT_0     = [ 'name' => 'Etc/GMT-0', 'offset' => '+00:00' ];
    public const ETC_GMT_1     = [ 'name' => 'Etc/GMT-1', 'offset' => '+01:00' ];
    public const ETC_GMT_10    = [ 'name' => 'Etc/GMT-10', 'offset' => '+10:00' ];
    public const ETC_GMT_11    = [ 'name' => 'Etc/GMT-11', 'offset' => '+11:00' ];
    public const ETC_GMT_12    = [ 'name' => 'Etc/GMT-12', 'offset' => '+12:00' ];
    public const ETC_GMT_13    = [ 'name' => 'Etc/GMT-13', 'offset' => '+13:00' ];
    public const ETC_GMT_14    = [ 'name' => 'Etc/GMT-14', 'offset' => '+14:00' ];
    public const ETC_GMT_2     = [ 'name' => 'Etc/GMT-2', 'offset' => '+02:00' ];
    public const ETC_GMT_3     = [ 'name' => 'Etc/GMT-3', 'offset' => '+03:00' ];
    public const ETC_GMT_4     = [ 'name' => 'Etc/GMT-4', 'offset' => '+04:00' ];
    public const ETC_GMT_5     = [ 'name' => 'Etc/GMT-5', 'offset' => '+05:00' ];
    public const ETC_GMT_6     = [ 'name' => 'Etc/GMT-6', 'offset' => '+06:00' ];
    public const ETC_GMT_7     = [ 'name' => 'Etc/GMT-7', 'offset' => '+07:00' ];
    public const ETC_GMT_8     = [ 'name' => 'Etc/GMT-8', 'offset' => '+08:00' ];
    public const ETC_GMT_9     = [ 'name' => 'Etc/GMT-9', 'offset' => '+09:00' ];
    public const ETC_GREENWICH = [ 'name' => 'Etc/Greenwich', 'offset' => '+00:00' ];
    public const ETC_UTC       = [ 'name' => 'Etc/UCT', 'offset' => '+00:00' ];
    public const ETC_UNIVERSAL = [ 'name' => 'Etc/Universal', 'offset' => '+00:00' ];
    public const ETC_ZULU      = [ 'name' => 'Etc/Zulu', 'offset' => '+00:00' ];
    public const NL            = [ 'name' => 'Europe/Amsterdam', 'offset' => '+01:00' ];
    public const AD            = [ 'name' => 'Europe/Andorra', 'offset' => '+01:00' ];
    public const GR            = [ 'name' => 'Europe/Athens', 'offset' => '+02:00' ];
    public const GB            = [
        'belfast' => [ 'name' => 'Europe/Belfast', 'offset' => '+00:00' ],
        'london'  => [ 'name' => 'Europe/London', 'offset' => '+00:00' ],
        'gb'      => [ 'name' => 'GB', 'offset' => '+00:00' ],
        'gb_eire' => [ 'name' => 'GB-Eire', 'offset' => '+00:00' ],
    ];
    public const RS            = [ 'name' => 'Europe/Belgrade', 'offset' => '+01:00' ];
    public const DE            = [
        'berlin'   => [ 'name' => 'Europe/Berlin', 'offset' => '+01:00' ],
        'busingen' => [ 'name' => 'Europe/Busingen', 'offset' => '+01:00' ],
    ];
    public const SK            = [ 'name' => 'Europe/Bratislava', 'offset' => '+01:00' ];
    public const BE            = [ 'name' => 'Europe/Brussels', 'offset' => '+01:00' ];
    public const RO            = [ 'name' => 'Europe/Bucharest', 'offset' => '+02:00' ];
    public const HU            = [ 'name' => 'Europe/Budapest', 'offset' => '+01:00' ];
    public const MD            = [
        'chisinau' => [ 'name' => 'Europe/Chisinau', 'offset' => '+02:00' ],
        'tiraspol' => [ 'name' => 'Europe/Tiraspol', 'offset' => '+02:00' ],
    ];
    public const DK            = [ 'name' => 'Europe/Copenhagen', 'offset' => '+01:00' ];
    public const GI            = [ 'name' => 'Europe/Gibraltar', 'offset' => '+01:00' ];
    public const GG            = [ 'name' => 'Europe/Guernsey', 'offset' => '+00:00' ];
    public const FI            = [ 'name' => 'Europe/Helsinki', 'offset' => '+02:00' ];
    public const IM            = [ 'name' => 'Europe/Isle_of_Man', 'offset' => '+00:00' ];
    public const JE            = [ 'name' => 'Europe/Jersey', 'offset' => '+00:00' ];
    public const UA            = [
        'kiev'       => [ 'name' => 'Europe/Kiev', 'offset' => '+02:00' ],
        'simferopol' => [ 'name' => 'Europe/Simferopol', 'offset' => '+02:00' ],
        'uzhgorod'   => [ 'name' => 'Europe/Uzhgorod', 'offset' => '+02:00' ],
        'zaporozhye' => [ 'name' => 'Europe/Zaporozhye', 'offset' => '+02:00' ],
    ];
    public const SI            = [ 'name' => 'Europe/Ljubljana', 'offset' => '+01:00' ];
    public const LU            = [ 'name' => 'Europe/Luxembourg', 'offset' => '+01:00' ];
    public const MT            = [ 'name' => 'Europe/Malta', 'offset' => '+01:00' ];
    public const AX            = [ 'name' => 'Europe/Mariehamn', 'offset' => '+02:00' ];
    public const BY            = [ 'name' => 'Europe/Minsk', 'offset' => '+03:00' ];
    public const MC            = [ 'name' => 'Europe/Monaco', 'offset' => '+01:00' ];
    public const NO            = [ 'name' => 'Europe/Oslo', 'offset' => '+01:00' ];
    public const FR            = [ 'name' => 'Europe/Paris', 'offset' => '+01:00' ];
    public const ME            = [ 'name' => 'Europe/Podgorica', 'offset' => '+01:00' ];
    public const CZ            = [ 'name' => 'Europe/Prague', 'offset' => '+01:00' ];
    public const LV            = [ 'name' => 'Europe/Riga', 'offset' => '+02:00' ];
    public const IT            = [ 'name' => 'Europe/Rome', 'offset' => '+01:00' ];
    public const SM            = [ 'name' => 'Europe/San_Marino', 'offset' => '+01:00' ];
    public const BA            = [ 'name' => 'Europe/Sarajevo', 'offset' => '+01:00' ];
    public const MK            = [ 'name' => 'Europe/Skopje', 'offset' => '+01:00' ];
    public const BG            = [ 'name' => 'Europe/Sofia', 'offset' => '+02:00' ];
    public const SE            = [ 'name' => 'Europe/Stockholm', 'offset' => '+01:00' ];
    public const EE            = [ 'name' => 'Europe/Tallinn', 'offset' => '+02:00' ];
    public const AL            = [ 'name' => 'Europe/Tirane', 'offset' => '+01:00' ];
    public const LI            = [ 'name' => 'Europe/Vaduz', 'offset' => '+01:00' ];
    public const VA            = [ 'name' => 'Europe/Vatican', 'offset' => '+01:00' ];
    public const AT            = [ 'name' => 'Europe/Vienna', 'offset' => '+01:00' ];
    public const LT            = [ 'name' => 'Europe/Vilnius', 'offset' => '+02:00' ];
    public const PL            = [
        'warsaw' => [ 'name' => 'Europe/Warsaw', 'offset' => '+01:00' ],
        'poland' => [ 'name' => 'Poland', 'offset' => '+01:00' ],
    ];
    public const HR            = [ 'name' => 'Europe/Zagreb', 'offset' => '+01:00' ];
    public const CH            = [ 'name' => 'Europe/Zurich', 'offset' => '+01:00' ];
    public const FACTORY       = [ 'name' => 'Factory', 'offset' => '+00:00' ];
    public const GMT           = [ 'name' => 'GMT', 'offset' => '+00:00' ];
    public const GMT0          = [ 'name' => 'GMT+0', 'offset' => '+00:00' ];
    public const GMT_0         = [ 'name' => 'GMT-0', 'offset' => '+00:00' ];
    public const GREENWICH     = [ 'name' => 'Greenwich', 'offset' => '+00:00' ];
    public const HST           = [ 'name' => 'HST', 'offset' => '-10:00' ];
    public const MG            = [ 'name' => 'Indian/Antananarivo', 'offset' => '+03:00' ];
    public const IO            = [ 'name' => 'Indian/Chagos', 'offset' => '+06:00' ];
    public const CX            = [ 'name' => 'Indian/Christmas', 'offset' => '+07:00' ];
    public const CC            = [ 'name' => 'Indian/Cocos', 'offset' => '+06:30' ];
    public const KM            = [ 'name' => 'Indian/Comoro', 'offset' => '+03:00' ];
    public const TF            = [ 'name' => 'Indian/Kerguelen', 'offset' => '+05:00' ];
    public const SC            = [ 'name' => 'Indian/Mahe', 'offset' => '+04:00' ];
    public const MV            = [ 'name' => 'Indian/Maldives', 'offset' => '+05:00' ];
    public const MU            = [ 'name' => 'Indian/Mauritius', 'offset' => '+04:00' ];
    public const YT            = [ 'name' => 'Indian/Mayotte', 'offset' => '+03:00' ];
    public const RE            = [ 'name' => 'Indian/Reunion', 'offset' => '+04:00' ];
    public const MH            = [
        'kwajalein'         => [ 'name' => 'Kwajalein', 'offset' => '+12:00' ],
        'pacific_kwajalein' => [ 'name' => 'Pacific/Kwajalein', 'offset' => '+12:00' ],
        'majuro'            => [ 'name' => 'Pacific/Majuro', 'offset' => '+12:00' ],
    ];
    public const MET           = [ 'name' => 'MET', 'offset' => '+01:00' ];
    public const MST           = [ 'name' => 'MST', 'offset' => '-07:00' ];
    public const MST7MDT       = [ 'name' => 'MST7MDT', 'offset' => '-07:00' ];
    public const NZ            = [
        'nz'       => [ 'name' => 'NZ', 'offset' => '+12:00' ],
        'nz_chat'  => [ 'name' => 'NZ-CHAT', 'offset' => '+12:45' ],
        'auckland' => [ 'name' => 'Pacific/Auckland', 'offset' => '+12:00' ],
        'chatman'  => [ 'name' => 'Pacific/Chatham', 'offset' => '+12:45' ],
    ];
    public const WS            = [
        'apia'          => [ 'name' => 'Pacific/Apia', 'offset' => '+13:00' ],
        'pacific_samoa' => [ 'name' => 'Pacific/Samoa', 'offset' => '-11:00' ],
        'us_samoa'      => [ 'name' => 'US/Samoa', 'offset' => '-11:00' ],
    ];
    public const PG            = [
        'bougainville' => [ 'name' => 'Pacific/Bougainville', 'offset' => '+11:00' ],
        'port_moresby' => [ 'name' => 'Pacific/Port_Moresby', 'offset' => '+10:00' ],
    ];
    public const FM            = [
        'chuuk'   => [ 'name' => 'Pacific/Chuuk', 'offset' => '+10:00' ],
        'kosrae'  => [ 'name' => 'Pacific/Kosrae', 'offset' => '+11:00' ],
        'pohnpei' => [ 'name' => 'Pacific/Pohnpei', 'offset' => '+11:00' ],
        'ponape'  => [ 'name' => 'Pacific/Ponape', 'offset' => '+11:00' ],
        'truk'    => [ 'name' => 'Pacific/Truk', 'offset' => '+10:00' ],
        'yap'     => [ 'name' => 'Pacific/Yap', 'offset' => '+10:00' ],
    ];
    public const VU            = [ 'name' => 'Pacific/Efate', 'offset' => '+11:00' ];
    public const KI            = [
        'enderbury'  => [ 'name' => 'Pacific/Enderbury', 'offset' => '+13:00' ],
        'kanton'     => [ 'name' => 'Pacific/Kanton', 'offset' => '+13:00' ],
        'kiritimati' => [ 'name' => 'Pacific/Kiritimati', 'offset' => '+14:00' ],
        'tarawa'     => [ 'name' => 'Pacific/Tarawa', 'offset' => '+12:00' ],
    ];
    public const TK            = [ 'name' => 'Pacific/Fakaofo', 'offset' => '+13:00' ];
    public const FJ            = [ 'name' => 'Pacific/Fiji', 'offset' => '+12:00' ];
    public const TV            = [ 'name' => 'Pacific/Funafuti', 'offset' => '+12:00' ];
    public const PF            = [
        'gambier'   => [ 'name' => 'Pacific/Gambier', 'offset' => '-09:00' ],
        'marquesas' => [ 'name' => 'Pacific/Marquesas', 'offset' => '-09:30' ],
        'tahiti'    => [ 'name' => 'Pacific/Tahiti', 'offset' => '-10:00' ],
    ];
    public const SB            = [ 'name' => 'Pacific/Guadalcanal', 'offset' => '+11:00' ];
    public const GU            = [ 'name' => 'Pacific/Guam', 'offset' => '+10:00' ];
    public const UM            = [
        'honolulu' => [ 'name' => 'Pacific/Honolulu', 'offset' => '-10:00' ],
        'johnston' => [ 'name' => 'Pacific/Johnston', 'offset' => '-10:00' ],
        'midway'   => [ 'name' => 'Pacific/Midway', 'offset' => '-11:00' ],
        'wake'     => [ 'name' => 'Pacific/Wake', 'offset' => '+12:00' ],
    ];
    public const NR            = [ 'name' => 'Pacific/Nauru', 'offset' => '+12:00' ];
    public const NU            = [ 'name' => 'Pacific/Niue', 'offset' => '-11:00' ];
    public const NF            = [ 'name' => 'Pacific/Norfolk', 'offset' => '+11:00' ];
    public const NC            = [ 'name' => 'Pacific/Noumea', 'offset' => '+11:00' ];
    public const AS            = [ 'name' => 'Pacific/Pago_Pago', 'offset' => '-11:00' ];
    public const PW            = [ 'name' => 'Pacific/Palau', 'offset' => '+09:00' ];
    public const PN            = [ 'name' => 'Pacific/Pitcairn', 'offset' => '-08:00' ];
    public const CK            = [ 'name' => 'Pacific/Rarotonga', 'offset' => '-10:00' ];
    public const MP            = [ 'name' => 'Pacific/Saipan', 'offset' => '+10:00' ];
    public const TO            = [ 'name' => 'Pacific/Tongatapu', 'offset' => '+13:00' ];
    public const WF            = [ 'name' => 'Pacific/Wallis', 'offset' => '+12:00' ];
    public const PST8PDT       = [ 'name' => 'PST8PDT', 'offset' => '-08:00' ];
    public const UCT           = [ 'name' => 'UCT', 'offset' => '+00:00' ];
    public const UNIVERSAL     = [ 'name' => 'Universal', 'offset' => '+00:00' ];
    public const WET           = [ 'name' => 'WET', 'offset' => '+00:00' ];
    public const ZULU          = [ 'name' => 'Zulu', 'offset' => '+00:00' ];
    # endregion

}
