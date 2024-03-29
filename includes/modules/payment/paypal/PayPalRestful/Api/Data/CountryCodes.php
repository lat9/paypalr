<?php
/**
 * An API-data class for Countrie used by the PayPalRestful (paypalr) Payment Module
 *
 * @copyright Copyright 2023 Zen Cart Development Team
 * @license https://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 * @version $Id: lat9 2023 Nov 16 Modified in v2.0.0 $
 */
namespace PayPalRestful\Api\Data;

class CountryCodes
{
    protected static array $countryCodes = [
        'AL',  //- ALBANIA
        'DZ',  //- ALGERIA
        'AD',  //- ANDORRA
        'AO',  //- ANGOLA
        'AI',  //- ANGUILLA
        'AG',  //- ANTIGUA & BARBUDA
        'AR',  //- ARGENTINA
        'AM',  //- ARMENIA
        'AW',  //- ARUBA
        'AU',  //- AUSTRALIA
        'AT',  //- AUSTRIA
        'AZ',  //- AZERBAIJAN
        'BS',  //- BAHAMAS
        'BH',  //- BAHRAIN
        'BB',  //- BARBADOS
        'BY',  //- BELARUS
        'BE',  //- BELGIUM
        'BZ',  //- BELIZE
        'BJ',  //- BENIN
        'BM',  //- BERMUDA
        'BT',  //- BHUTAN
        'BO',  //- BOLIVIA
        'BA',  //- BOSNIA & HERZEGOVINA
        'BW',  //- BOTSWANA
        'BR',  //- BRAZIL
        'VG',  //- BRITISH VIRGIN ISLANDS
        'BN',  //- BRUNEI
        'BG',  //- BULGARIA
        'BF',  //- BURKINA FASO
        'BI',  //- BURUNDI
        'KH',  //- CAMBODIA
        'CM',  //- CAMEROON
        'CA',  //- CANADA
        'CV',  //- CAPE VERDE
        'KY',  //- CAYMAN ISLANDS
        'TD',  //- CHAD
        'CL',  //- CHILE
        'C2',  //- CHINA ... Zen Cart code is 'CH'
        'CO',  //- COLOMBIA
        'KM',  //- COMOROS
        'CG',  //- CONGO - BRAZZAVILLE
//        'CD',  //- CONGO - KINSHASA ... not a Zen Cart country!
        'CK',  //- COOK ISLANDS
        'CR',  //- COSTA RICA
        'CI',  //- CÔTE D’IVOIRE
        'HR',  //- CROATIA
        'CY',  //- CYPRUS
        'CZ',  //- CZECH REPUBLIC
        'DK',  //- DENMARK
        'DJ',  //- DJIBOUTI
        'DM',  //- DOMINICA
        'DO',  //- DOMINICAN REPUBLIC
        'EC',  //- ECUADOR
        'EG',  //- EGYPT
        'SV',  //- EL SALVADOR
        'ER',  //- ERITREA
        'EE',  //- ESTONIA
        'ET',  //- ETHIOPIA
        'FK',  //- FALKLAND ISLANDS
        'FO',  //- FAROE ISLANDS
        'FJ',  //- FIJI
        'FI',  //- FINLAND
        'FR',  //- FRANCE
        'GF',  //- FRENCH GUIANA
        'PF',  //- FRENCH POLYNESIA
        'GA',  //- GABON
        'GM',  //- GAMBIA
        'GE',  //- GEORGIA
        'DE',  //- GERMANY
        'GI',  //- GIBRALTAR
        'GR',  //- GREECE
        'GL',  //- GREENLAND
        'GD',  //- GRENADA
        'GP',  //- GUADELOUPE
        'GT',  //- GUATEMALA
        'GN',  //- GUINEA
        'GW',  //- GUINEA-BISSAU
        'GY',  //- GUYANA
        'HN',  //- HONDURAS
        'HK',  //- HONG KONG SAR CHINA
        'HU',  //- HUNGARY
        'IS',  //- ICELAND
        'IN',  //- INDIA
        'ID',  //- INDONESIA
        'IE',  //- IRELAND
        'IL',  //- ISRAEL
        'IT',  //- ITALY
        'JM',  //- JAMAICA
        'JP',  //- JAPAN
        'JO',  //- JORDAN
        'KZ',  //- KAZAKHSTAN
        'KE',  //- KENYA
        'KI',  //- KIRIBATI
        'KW',  //- KUWAIT
        'KG',  //- KYRGYZSTAN
        'LA',  //- LAOS
        'LV',  //- LATVIA
        'LS',  //- LESOTHO
        'LI',  //- LIECHTENSTEIN
        'LT',  //- LITHUANIA
        'LU',  //- LUXEMBOURG
        'MK',  //- MACEDONIA
        'MG',  //- MADAGASCAR
        'MW',  //- MALAWI
        'MY',  //- MALAYSIA
        'MV',  //- MALDIVES
        'ML',  //- MALI
        'MT',  //- MALTA
        'MH',  //- MARSHALL ISLANDS
        'MQ',  //- MARTINIQUE
        'MR',  //- MAURITANIA
        'MU',  //- MAURITIUS
        'YT',  //- MAYOTTE
        'MX',  //- MEXICO
        'FM',  //- MICRONESIA
        'MD',  //- MOLDOVA
        'MC',  //- MONACO
        'MN',  //- MONGOLIA
        'ME',  //- MONTENEGRO
        'MS',  //- MONTSERRAT
        'MA',  //- MOROCCO
        'MZ',  //- MOZAMBIQUE
        'NA',  //- NAMIBIA
        'NR',  //- NAURU
        'NP',  //- NEPAL
        'NL',  //- NETHERLANDS
        'NC',  //- NEW CALEDONIA
        'NZ',  //- NEW ZEALAND
        'NI',  //- NICARAGUA
        'NE',  //- NIGER
        'NG',  //- NIGERIA
        'NU',  //- NIUE
        'NF',  //- NORFOLK ISLAND
        'NO',  //- NORWAY
        'OM',  //- OMAN
        'PW',  //- PALAU
        'PA',  //- PANAMA
        'PG',  //- PAPUA NEW GUINEA
        'PY',  //- PARAGUAY
        'PE',  //- PERU
        'PH',  //- PHILIPPINES
        'PN',  //- PITCAIRN ISLANDS
        'PL',  //- POLAND
        'PT',  //- PORTUGAL
        'QA',  //- QATAR
        'RE',  //- RÉUNION
        'RO',  //- ROMANIA
        'RU',  //- RUSSIA
        'RW',  //- RWANDA
        'WS',  //- SAMOA
        'SM',  //- SAN MARINO
        'ST',  //- SÃO TOMÉ & PRÍNCIPE
        'SA',  //- SAUDI ARABIA
        'SN',  //- SENEGAL
        'RS',  //- SERBIA
        'SC',  //- SEYCHELLES
        'SL',  //- SIERRA LEONE
        'SG',  //- SINGAPORE
        'SK',  //- SLOVAKIA
        'SI',  //- SLOVENIA
        'SB',  //- SOLOMON ISLANDS
        'SO',  //- SOMALIA
        'ZA',  //- SOUTH AFRICA
        'KR',  //- SOUTH KOREA
        'ES',  //- SPAIN
        'LK',  //- SRI LANKA
        'SH',  //- ST. HELENA
        'KN',  //- ST. KITTS & NEVIS
        'LC',  //- ST. LUCIA
        'PM',  //- ST. PIERRE & MIQUELON
        'VC',  //- ST. VINCENT & GRENADINES
        'SR',  //- SURINAME
        'SJ',  //- SVALBARD & JAN MAYEN
        'SZ',  //- SWAZILAND
        'SE',  //- SWEDEN
        'CH',  //- SWITZERLAND
        'TW',  //- TAIWAN
        'TJ',  //- TAJIKISTAN
        'TZ',  //- TANZANIA
        'TH',  //- THAILAND
        'TG',  //- TOGO
        'TO',  //- TONGA
        'TT',  //- TRINIDAD & TOBAGO
        'TN',  //- TUNISIA
        'TM',  //- TURKMENISTAN
        'TC',  //- TURKS & CAICOS ISLANDS
        'TV',  //- TUVALU
        'UG',  //- UGANDA
        'UA',  //- UKRAINE
        'AE',  //- UNITED ARAB EMIRATES
        'GB',  //- UNITED KINGDOM
        'US',  //- UNITED STATES
        'UY',  //- URUGUAY
        'VU',  //- VANUATU
        'VA',  //- VATICAN
        'VE',  //- VENEZUELA
        'VN',  //- VIETNAM
        'WF',  //- WALLIS & FUTUNA
        'YE',  //- YEMEN
        'ZM',  //- ZAMBIA
        'ZW',  //- ZIMBABWE
    ];

    public static function convertCountryCode(string $country_code): string
    {
        if (in_array($country_code, self::$countryCodes)) {
            return $country_code;
        }
        return ($country_code === 'CH') ? 'C2' : '';
    }
}
