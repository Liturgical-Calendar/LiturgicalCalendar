<?php

namespace Johnrdorazio\LitCal\Params;

use Johnrdorazio\LitCal\Enum\CalendarType;
use Johnrdorazio\LitCal\Enum\LitLocale;

class EventsParams
{
    public int $Year;
    public bool $EternalHighPriest            = false;
    public ?string $Locale                    = null;
    public ?string $ReturnType                = null;
    public ?string $NationalCalendar          = null;
    public ?string $DiocesanCalendar          = null;
    private array $SupportedNationalCalendars = [ "VATICAN" ];
    private static string $lastError          = '';

    public const ALLOWED_PARAMS  = [
        "ETERNALHIGHPRIEST",
        "LOCALE",
        "NATIONALCALENDAR",
        "DIOCESANCALENDAR"
    ];

    // If we can get more data from 1582 (year of the Gregorian reform) to 1969
    //  perhaps we can lower the limit to the year of the Gregorian reform
    //  public const YEAR_LOWER_LIMIT          = 1583;
    // For now we'll just deal with the Liturgical Calendar from the Editio Typica 1970
    public const YEAR_LOWER_LIMIT          = 1970;

    //The upper limit is determined by the limit of PHP in dealing with DateTime objects
    public const YEAR_UPPER_LIMIT          = 9999;

    /*private static function debugWrite(string $string)
    {
        file_put_contents("debug.log", $string . PHP_EOL, FILE_APPEND);
    }*/

    public function __construct(array $DATA = [])
    {
        //we need at least a default value for the current year and for the locale
        $this->Year = (int)date("Y");
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $value = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
            //$mainLang = explode("_", $value )[0];
            $this->Locale = LitLocale::isValid($value) ? $value : LitLocale::LATIN;
        } else {
            $this->Locale = LitLocale::LATIN;
        }

        $directories = array_map('basename', glob('nations/*', GLOB_ONLYDIR));
        //self::debugWrite(json_encode($directories));
        foreach ($directories as $directory) {
            //self::debugWrite($directory);
            if (file_exists("nations/$directory/$directory.json")) {
                $this->SupportedNationalCalendars[] = $directory;
            }
        }
        if (count($DATA)) {
            $this->setData($DATA);
        }
    }

    public function setData(array $DATA): bool
    {
        foreach ($DATA as $key => $value) {
            $key = strtoupper($key);
            if (in_array($key, self::ALLOWED_PARAMS)) {
                switch ($key) {
                    case "LOCALE":
                        $this->Locale           = LitLocale::isValid($value) ? $value : LitLocale::LATIN;
                        break;
                    case "NATIONALCALENDAR":
                        if (false === in_array(strtoupper($value), $this->SupportedNationalCalendars)) {
                            self::$lastError = "uknown value `$value` for nation parameter, supported national calendars are: ["
                                . implode(',', $this->SupportedNationalCalendars) . "]";
                            return false;
                        }
                        $this->NationalCalendar =  strtoupper($value);
                        break;
                    case "DIOCESANCALENDAR":
                        $this->DiocesanCalendar = strtoupper($value);
                        break;
                    case "ETERNALHIGHPRIEST":
                        $this->EternalHighPriest = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                        break;
                }
            }
        }
        return true;
    }

    public static function getLastErrorMessage(): string
    {
        return self::$lastError;
    }
}
