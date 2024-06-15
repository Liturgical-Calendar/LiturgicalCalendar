<?php

namespace Johnrdorazio\LitCal\Params;

use Johnrdorazio\LitCal\Paths\RegionalData;
use Johnrdorazio\LitCal\Enum\Route;
use Johnrdorazio\LitCal\Enum\StatusCode;
use Johnrdorazio\LitCal\Enum\LitLocale;
use Johnrdorazio\LitCal\Enum\RequestMethod;

class RegionalDataParams
{
    private ?object $calendars = null;
    public const array EXPECTED_CATEGORIES = [
        "nation"      => "NATIONALCALENDAR",
        "diocese"     => "DIOCESANCALENDAR",
        "widerregion" => "WIDERREGIONCALENDAR"
    ];
    public ?string $category = null;
    public ?string $key = null;
    public ?string $locale = null;
    public ?object $payload = null;

    public function __construct()
    {
        $metadataRaw = file_get_contents(API_BASE_PATH .  Route::CALENDARS->value);
        if ($metadataRaw) {
            $metadata = json_decode($metadataRaw);
            if (JSON_ERROR_NONE === json_last_error() && property_exists($metadata, 'LitCalMetadata')) {
                $this->calendars = $metadata->LitCalMetadata;
            }
        }
    }

    public function setData(object $data): bool
    {
        if (false === property_exists($data, 'category') || false === property_exists($data, 'key')) {
            RegionalData::produceErrorResponse(
                StatusCode::BAD_REQUEST,
                "Expected params `category` and `key` but either one or both not present"
            );
        }
        if (false === in_array($data->category, self::EXPECTED_CATEGORIES)) {
            RegionalData::produceErrorResponse(
                StatusCode::BAD_REQUEST,
                "Unexpected value '{$data->category}' for param `category`, acceptable values are: " . implode(', ', array_values(self::EXPECTED_CATEGORIES))
            );
        } else {
            switch ($data->category) {
                case 'NATIONALCALENDAR':
                    if (false === property_exists($this->calendars->NationalCalendars, $data->key)) {
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid value {$data->key} for param `key`");
                    }
                    // Check the request method: cannot DELETE National calendar data if it is still in use by a Diocesan calendar
                    if (RegionalData::$APICore->getRequestMethod() === RequestMethod::DELETE) {
                        foreach ($this->calendars->DiocesanCalendars as $key => $value) {
                            if ($value->nation === $data->key) {
                                RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Cannot DELETE National Calendar data is a Diocesan Calendar depends on it. Currently, {$data->key} is in use by {$key}");
                            }
                        }
                    }
                    break;
                case 'DIOCESANCALENDAR':
                    if (false === property_exists($this->calendars->DiocesanCalendars, $data->key)) {
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid value {$data->key} for param `key`");
                    }
                    break;
                case 'WIDERREGIONCALENDAR':
                    if (false === property_exists($this->calendars->WiderRegions, $data->key)) {
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid value {$data->key} for param `key`");
                    }
                    // A locale parameter is required for WiderRegion data, whether supplied by the Accept-Language header or by a `locale` parameter
                    if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                        $value = \Locale::acceptFromHttp($_SERVER['HTTP_ACCEPT_LANGUAGE']);
                        //$mainLang = explode("_", $value )[0];
                        if (LitLocale::isValid($value)) {
                            $this->locale = $value;
                        } else {
                            RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid value {$value} for Accept-Language header");
                        }
                    } else {
                        // if the locale is not set in the Accept-Language header, let's see if it was set in a `locale` parameter
                        if (property_exists($data, 'locale')) {
                            if (LitLocale::isValid($data->locale)) {
                                $this->locale = $data->locale;
                            } else {
                                RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid value {$data->locale} for param `locale`");
                            }
                        } else {
                            RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "`locale` param or `Accept-Language` header required for Wider Region calendar data");
                        }
                    }
                    // Check the request method: cannot DELETE Wider Region calendar data if their are national calendars that depend on it
                    if (RegionalData::$APICore->getRequestMethod() === RequestMethod::DELETE) {
                        foreach ($this->calendars->NationalCalendarsMetadata as $key => $value) {
                            if (in_array($data->key, $value->widerRegions)) {
                                RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Cannot DELETE Wider Region calendar data as long as it is in use by a National Calendar. Currently {$data->key} is in use by {$key}");
                            }
                        }
                    }
                    break;
                default:
                    //nothing to do
            }
        }
        if (RegionalData::$APICore->getRequestMethod() === RequestMethod::PUT || RegionalData::$APICore->getRequestMethod() === RequestMethod::PATCH) {
            if (false === property_exists($data, 'payload') || false === $data->payload instanceof object) {
                RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, "Cannot create or update Calendar data without a payload");
            }
            switch ($this->category) {
                case 'NATIONALCALENDAR':
                    if (
                        false === property_exists($data->payload, 'LitCal')
                        || false === property_exists($data->paylod, 'Metadata')
                        || false === property_exists($data->payload, 'Settings')
                    ) {
                        $message = "Cannot create or update National calendar data when the payload does not have required properties `LitCal`, `Metadata` or `Settings`";
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, $message);
                    }
                    break;
                case 'DIOCESANCALENDAR':
                    if (
                        false === property_exists($data->payload, 'LitCal')
                        || false === property_exists($data->paylod, 'Diocese')
                        || false === property_exists($data->payload, 'Nation')
                    ) {
                        $message = "Cannot create or update Diocesan calendar data when the payload does not have required properties `LitCal`, `Diocese` or `Nation`";
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, $message);
                    }
                    break;
                case 'WIDERREGIONCALENDAR':
                    if (
                        false === property_exists($data->payload, 'LitCal')
                        || false === property_exists($data->paylod, 'Metadata')
                        || false === property_exists($data->payload, 'NationalCalendars')
                    ) {
                        $message = "Cannot create or update Wider Region calendar data when the payload does not have required properties `LitCal`, `Metadata` or `NationalCalendars`";
                        RegionalData::produceErrorResponse(StatusCode::BAD_REQUEST, $message);
                    }
                    break;
            }
        }
        return true;
    }
}
