<?php

namespace Johnrdorazio\LitCal\Enum;

class RomanMissal
{
    public const EDITIO_TYPICA_1970                    = "EDITIO_TYPICA_1970";
    public const REIMPRESSIO_EMENDATA_1971             = "EDITIO_TYPICA_1971";
    public const EDITIO_TYPICA_SECUNDA_1975            = "EDITIO_TYPICA_1975";
    public const EDITIO_TYPICA_TERTIA_2002             = "EDITIO_TYPICA_2002";
    public const EDITIO_TYPICA_TERTIA_EMENDATA_2008    = "EDITIO_TYPICA_2008";

    public const USA_EDITION_2011                      = "USA_2011";
    public const ITALY_EDITION_1983                    = "ITALY_1983";
    public const ITALY_EDITION_2020                    = "ITALY_2020";
    public const NETHERLANDS_EDITION_1978              = "NETHERLANDS_1978";

    public static array $values = [
        "EDITIO_TYPICA_1970",
        "EDITIO_TYPICA_1971",
        "EDITIO_TYPICA_1975",
        "EDITIO_TYPICA_2002",
        "EDITIO_TYPICA_2008",
        "USA_2011",
        "ITALY_1983",
        "ITALY_2020",
        "NETHERLANDS_1978"
    ];

    public static array $names = [
        self::EDITIO_TYPICA_1970                    => "Editio Typica 1970",
        self::REIMPRESSIO_EMENDATA_1971             => "Reimpressio Emendata 1971",
        self::EDITIO_TYPICA_SECUNDA_1975            => "Editio Typica Secunda 1975",
        self::EDITIO_TYPICA_TERTIA_2002             => "Editio Typica Tertia 2002",
        self::EDITIO_TYPICA_TERTIA_EMENDATA_2008    => "Editio Typica Tertia Emendata 2008",
        self::USA_EDITION_2011                      => "2011 Roman Missal issued by the USCCB",
        self::ITALY_EDITION_1983                    => "Messale Romano ed. 1983 pubblicata dalla CEI",
        self::ITALY_EDITION_2020                    => "Messale Romano ed. 2020 pubblicata dalla CEI",
        self::NETHERLANDS_EDITION_1978              => "Romeins Missaal ed. 1978 goedgekeurd door de Nederlandse bisschoppenconferentie"
    ];

    public static array $jsonFiles = [
        self::EDITIO_TYPICA_1970                    => "data/propriumdesanctis_1970/propriumdesanctis_1970.json",
        self::REIMPRESSIO_EMENDATA_1971             => false,
        self::EDITIO_TYPICA_SECUNDA_1975            => false,
        self::EDITIO_TYPICA_TERTIA_2002             => "data/propriumdesanctis_2002/propriumdesanctis_2002.json",
        self::EDITIO_TYPICA_TERTIA_EMENDATA_2008    => "data/propriumdesanctis_2008/propriumdesanctis_2008.json",
        self::USA_EDITION_2011                      => "data/propriumdesanctis_USA_2011/propriumdesanctis_USA_2011.json",
        self::ITALY_EDITION_1983                    => "data/propriumdesanctis_ITALY_1983/propriumdesanctis_ITALY_1983.json",
        self::ITALY_EDITION_2020                    => false,
        self::NETHERLANDS_EDITION_1978              => false
    ];

    public static array $i18nPath = [
        self::EDITIO_TYPICA_1970                    => "data/propriumdesanctis_1970/i18n/",
        self::REIMPRESSIO_EMENDATA_1971             => false,
        self::EDITIO_TYPICA_SECUNDA_1975            => false,
        self::EDITIO_TYPICA_TERTIA_2002             => "data/propriumdesanctis_2002/i18n/",
        self::EDITIO_TYPICA_TERTIA_EMENDATA_2008    => "data/propriumdesanctis_2008/i18n/",
        self::USA_EDITION_2011                      => false,
        self::ITALY_EDITION_1983                    => false,
        self::ITALY_EDITION_2020                    => false,
        self::NETHERLANDS_EDITION_1978              => false
    ];

    public static array $yearLimits = [
        self::EDITIO_TYPICA_1970                    => [ "since_year" => 1970 ],
        self::REIMPRESSIO_EMENDATA_1971             => [ "since_year" => 1971 ],
        self::EDITIO_TYPICA_SECUNDA_1975            => [ "since_year" => 1975 ],
        self::EDITIO_TYPICA_TERTIA_2002             => [ "since_year" => 2002 ],
        self::EDITIO_TYPICA_TERTIA_EMENDATA_2008    => [ "since_year" => 2008 ],
        self::USA_EDITION_2011                      => [ "since_year" => 2011 ],
        //the festivities applied in the '83 edition were incorporated into the Latin 2002 edition,
        //therefore we no longer need to apply them after the year 2002 since the Latin edition takes precedence
        self::ITALY_EDITION_1983                    => [ "since_year" => 1983, "until_year" => 2002 ],
        self::ITALY_EDITION_2020                    => [ "since_year" => 2020 ],
        self::NETHERLANDS_EDITION_1978              => [ "since_year" => 1979 ]
    ];


    public static function isValid($value): bool
    {
        return in_array($value, self::$values);
    }

    public static function isLatinMissal($value): bool
    {
        return in_array($value, self::$values) && strpos($value, "VATICAN_");
    }

    public static function getName($value): string
    {
        return self::$names[ $value ];
    }

    public static function getSanctoraleFileName($value): string|false
    {
        return self::$jsonFiles[ $value ];
    }

    public static function getSanctoraleI18nFilePath($value): string|false
    {
        return self::$i18nPath[ $value ];
    }

    public static function getYearLimits($value): object
    {
        return (object) self::$yearLimits[ $value ];
    }

    public static function produceMetadata(): array
    {
        $reflectionClass = new \ReflectionClass(static::class);
        $metadata = $reflectionClass->getConstants();
        array_walk($metadata, function (string &$v) {
            $v = [
                "missal_id" => $v,
                "name" => self::getName($v),
                "data_path" => self::getSanctoraleFileName($v),
                "i18n_path" => self::getSanctoraleI18nFilePath($v),
                "year_limits" => self::$yearLimits[ $v ],
                "year_published" => self::$yearLimits[ $v ][ "since_year" ]
            ];
        });
        return $metadata;
    }
}
