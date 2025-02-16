<?php

namespace LiturgicalCalendar\Api\Paths;

use Swaggest\JsonSchema\Schema;
use Swaggest\JsonSchema\InvalidValue;
use LiturgicalCalendar\Api\Core;
use LiturgicalCalendar\Api\Enum\JsonData;
use LiturgicalCalendar\Api\Enum\Route;
use LiturgicalCalendar\Api\Enum\RequestMethod;
use LiturgicalCalendar\Api\Enum\AcceptHeader;
use LiturgicalCalendar\Api\Enum\StatusCode;
use LiturgicalCalendar\Api\Enum\LitSchema;
use LiturgicalCalendar\Api\Enum\RequestContentType;
use LiturgicalCalendar\Api\Params\RegionalDataParams;
use PHP_CodeSniffer\Tokenizers\JS;
use stdClass;

/**
 * RegionalData
 * PHP version 8.3
 *
 * @package  LitCal
 * @author   John Romano D'Orazio <priest@johnromanodorazio.com>
 * @license  https://www.apache.org/licenses/LICENSE-2.0.txt Apache License 2.0
 * @version  GIT: 3.9
 * @link     https://litcal.johnromanodorazio.com
 */
class RegionalData
{
    private readonly ?object $CalendarsMetadata;
    private RegionalDataParams $params;
    public static Core $Core;

    /**
     * Constructor
     *
     * Initializes the RegionalData class.
     *
     * @return void
     */
    public function __construct()
    {
        self::$Core = new Core();
        $this->params  = new RegionalDataParams();
        $this->CalendarsMetadata = json_decode(file_get_contents(API_BASE_PATH . Route::CALENDARS->value))->litcal_metadata;
    }

    /**
     * Handle the request method.
     *
     * Depending on the request method, it will call the appropriate class method to handle the request.
     *
     * @return void
     */
    private function handleRequestMethod()
    {
        switch (self::$Core->getRequestMethod()) {
            case RequestMethod::GET:
            case RequestMethod::POST:
                if (property_exists($this->params, 'i18nRequest') && null !== $this->params->i18nRequest) {
                    // If a simple i18n data request was made, retrieve the i18n data
                    $this->getI18nData();
                } else {
                    // Else retrieve the calendar data
                    $this->getRegionalCalendarData();
                }
                break;
            case RequestMethod::PUT:
                $this->createRegionalCalendar();
                break;
            case RequestMethod::PATCH:
                $this->updateRegionalCalendar();
                break;
            case RequestMethod::DELETE:
                $this->deleteRegionalCalendar();
                break;
            default:
                self::produceErrorResponse(StatusCode::METHOD_NOT_ALLOWED, "The method " . $_SERVER['REQUEST_METHOD'] . " cannot be handled by this endpoint");
        }
    }

    /**
     * Handle GET and POST requests for i18n data.
     *
     * The request params should include the following values:
     * - `category`: the category of regional data to retrieve (DIOCESANCALENDAR, WIDERREGIONCALENDAR or NATIONALCALENDAR)
     * - `key`: the ID of the regional calendar to retrieve i18n data for
     * - `i18nRequest`: the locale to retrieve the i18n data for
     *
     * The method will return the i18n data for the requested calendar in the requested locale.
     * If the requested resource exists, it will be returned as JSON.
     * If the resource does not exist, a 404 error will be returned.
     *
     * @return void
     */
    private function getI18nData(): void
    {
        $i18nDataFile = null;
        switch ($this->params->category) {
            case "DIOCESANCALENDAR":
                $dioceseEntry = array_values(array_filter($this->CalendarsMetadata->diocesan_calendars, function ($el) {
                    return $el->calendar_id === $this->params->key;
                }));
                if (empty($dioceseEntry)) {
                    self::produceErrorResponse(StatusCode::NOT_FOUND, "The requested resource {$this->params->key} was not found in the index");
                }
                $i18nDataFile = strtr(JsonData::DIOCESAN_CALENDARS_I18N_FILE, [
                    '{nation}' => $dioceseEntry[0]->nation,
                    '{diocese}' => $this->params->key,
                    '{locale}' => $this->params->i18nRequest
                ]);
                break;
            case "WIDERREGIONCALENDAR":
                $i18nDataFile = strtr(JsonData::WIDER_REGIONS_I18N_FILE, [
                    '{wider_region}' => $this->params->key,
                    '{locale}' => $this->params->i18nRequest
                ]);
                break;
            case "NATIONALCALENDAR":
                $i18nDataFile = strtr(JsonData::NATIONAL_CALENDARS_I18N_FILE, [
                    '{nation}' => $this->params->key,
                    '{locale}' => $this->params->i18nRequest
                ]);
                break;
            default:
                self::produceErrorResponse(
                    StatusCode::BAD_REQUEST,
                    "RegionalData::getI18nData: invalid value <{$this->params->category}> for param `category`: valid values are: "
                        . implode(', ', array_values(RegionalDataParams::EXPECTED_CATEGORIES))
                );
        }
        if (null !== $i18nDataFile && file_exists($i18nDataFile)) {
            self::produceResponse(file_get_contents($i18nDataFile));
        } else {
            self::produceErrorResponse(StatusCode::NOT_FOUND, "RegionalData::getI18nData: file $i18nDataFile does not exist");
        }
    }

    /**
     * Handle GET and POST requests to retrieve a Regional Calendar data resource.
     *
     * This is a private method and should only be called from {@see handleRequestMethod}.
     *
     * The `category` parameter is required and must be one of the following values:
     * - DIOCESANCALENDAR
     * - WIDERREGIONCALENDAR
     * - NATIONALCALENDAR
     *
     * The `key` parameter is required and must be a valid key for the requested category.
     *
     * The `locale` parameter is optional.
     * If present, it must be a valid locale listed in the metadata of the requested calendar.
     * If not present, the first valid locale for the requested category will be used.
     *
     * If the requested resource exists, it will be returned as JSON.
     * If the resource does not exist, a 404 error will be returned.
     * If the `category` or `locale` parameters are invalid, a 400 error will be returned.
     */
    private function getRegionalCalendarData(): void
    {
        $calendarDataFile = null;
        switch ($this->params->category) {
            case "DIOCESANCALENDAR":
                $dioceseEntry = array_values(array_filter($this->CalendarsMetadata->diocesan_calendars, function ($el) {
                    return $el->calendar_id === $this->params->key;
                }));
                if (empty($dioceseEntry)) {
                    self::produceErrorResponse(StatusCode::NOT_FOUND, "The requested resource {$this->params->key} was not found in the index");
                }

                $calendarDataFile = strtr(JsonData::DIOCESAN_CALENDARS_FILE, [
                    '{nation}' => $dioceseEntry[0]->nation,
                    '{diocese}' => $this->params->key,
                    '{diocese_name}' => $dioceseEntry[0]->diocese
                ]);
                break;
            case "WIDERREGIONCALENDAR":
                $calendarDataFile = strtr(JsonData::WIDER_REGIONS_FILE, [
                    '{wider_region}' => $this->params->key
                ]);
                break;
            case "NATIONALCALENDAR":
                $calendarDataFile = strtr(JsonData::NATIONAL_CALENDARS_FILE, [
                    '{nation}' => $this->params->key
                ]);
                break;
            default:
                self::produceErrorResponse(
                    StatusCode::BAD_REQUEST,
                    "Invalid value <{$this->params->category}> for param `category`: valid values are: "
                        . implode(', ', array_values(RegionalDataParams::EXPECTED_CATEGORIES))
                );
        }

        if (null !== $calendarDataFile && file_exists($calendarDataFile)) {
            $CalendarData = json_decode(file_get_contents($calendarDataFile));

            // If a locale was not requested, use the first valid locale for the current requested calendar data
            // Else if a locale was requested, make sure it is a valid locale for the current requested calendar data
            if (null === $this->params->locale) {
                $this->params->locale = $CalendarData->metadata->locales[0];
            } elseif (false === in_array($this->params->locale, $CalendarData->metadata->locales, true)) {
                self::produceErrorResponse(
                    StatusCode::BAD_REQUEST,
                    "Invalid value `{$this->params->locale}` for param `locale`. Valid values for current requested Wider region calendar data `{$this->params->key}` are: "
                        . implode(', ', $CalendarData->metadata->locales)
                );
            }

            // Based on the locale requested, retrieve the appropriate locale data
            switch ($this->params->category) {
                case 'DIOCESANCALENDAR':
                    $CalendarDataI18nFile = strtr(JsonData::DIOCESAN_CALENDARS_I18N_FILE, [
                        '{nation}' => $dioceseEntry[0]->nation,
                        '{diocese}' => $this->params->key,
                        '{locale}' => $this->params->locale
                    ]);
                    break;
                case 'WIDERREGIONCALENDAR':
                    $CalendarDataI18nFile = strtr(JsonData::WIDER_REGIONS_I18N_FILE, [
                        '{wider_region}' => $this->params->key,
                        '{locale}' => $this->params->locale
                    ]);
                    break;
                case 'NATIONALCALENDAR':
                    $CalendarDataI18nFile = strtr(JsonData::NATIONAL_CALENDARS_I18N_FILE, [
                        '{nation}' => $this->params->key,
                        '{locale}' => $this->params->locale
                    ]);
                    break;
            }
            if (file_exists($CalendarDataI18nFile)) {
                $localeData = json_decode(file_get_contents($CalendarDataI18nFile));
                foreach ($CalendarData->litcal as $idx => $el) {
                    if (property_exists($localeData, $CalendarData->litcal[$idx]->festivity->event_key)) {
                        $CalendarData->litcal[$idx]->festivity->name = $localeData->{$CalendarData->litcal[$idx]->festivity->event_key};
                    }
                }
            } else {
                self::produceErrorResponse(StatusCode::NOT_FOUND, "RegionalData::getRegionalCalendarData: file $CalendarDataI18nFile does not exist");
            }
            self::produceResponse(json_encode($CalendarData));
        } else {
            self::produceErrorResponse(StatusCode::NOT_FOUND, "RegionalData::getRegionalCalendarData: file $calendarDataFile does not exist");
        }
    }

    /**
     * Handle PUT requests to create or update a regional calendar data resource.
     *
     * This is a private method and should only be called from {@see handleRequestMethod}.
     *
     * The resource is created or updated in the `jsondata/sourcedata/` directory.
     *
     * If the payload is valid according to the associated schema, the response will be a JSON object
     * containing a success message.
     *
     * If the payload is invalid, the response will be a JSON error response with a 422 status code.
     *
     * TODO: we are currently only handling creation of diocesan calendar data, this should be expanded
     *       to handle creation of wider region and national calendar data
     */
    private function createRegionalCalendar(): void
    {
        $response = new \stdClass();
        if (false === $this->params->payload instanceof \stdClass) {
            $payloadType = gettype($this->params->payload);
            self::produceErrorResponse(StatusCode::BAD_REQUEST, "`payload` param expected to be serialized object, instead it was of type `{$payloadType}` after unserialization");
        }

        $test = $this->validateDataAgainstSchema($this->params->payload, LitSchema::DIOCESAN);
        if ($test === true) {
            // Ensure we have all the necessary folders in place
            // Since we are passing `true` to the `i18n` mkdir, all missing parent folders will also be created,
            // so we don't have to worry about manually checking and creating each one individually
            $diocesanCalendarI18nFolder = strtr(JsonData::DIOCESAN_CALENDARS_I18N_FOLDER, [
                '{nation}' => $this->params->payload->metadata->nation,
                '{diocese}' => $this->params->payload->metadata->diocese_id
            ]);
            if (!file_exists($diocesanCalendarI18nFolder)) {
                mkdir($diocesanCalendarI18nFolder, 0755, true);
            }

            foreach ($this->params->payload->i18n as $locale => $litCalEventsI18n) {
                $diocesanCalendarI18nFile = strtr(
                    JsonData::DIOCESAN_CALENDARS_I18N_FILE,
                    [
                        '{nation}' => $this->params->payload->metadata->nation,
                        '{diocese}' => $this->params->payload->metadata->diocese_id,
                        '{locale}' => $locale
                    ]
                );
                file_put_contents($diocesanCalendarI18nFile, json_encode($litCalEventsI18n, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
            }

            // We no longer need the i18n data, we can now remove it
            unset($this->params->payload->i18n);

            $diocesanCalendarFile = strtr(
                JsonData::DIOCESAN_CALENDARS_FILE,
                [
                    '{nation}' => $this->params->payload->metadata->nation,
                    '{diocese}' => $this->params->payload->metadata->diocese_id,
                    '{diocese_name}' => $this->params->payload->metadata->diocese_name
                ]
            );

            $calendarData = json_encode($this->params->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            file_put_contents(
                $diocesanCalendarFile,
                $calendarData . PHP_EOL
            );

            $response->success = "Calendar data created or updated for Diocese \"{$this->params->payload->metadata->diocese_name}\" (Nation: \"{$this->params->payload->metadata->nation}\")";
            $response->data = $this->params->payload;
            self::produceResponse(json_encode($response));
        } else {
            self::produceErrorResponse(StatusCode::UNPROCESSABLE_CONTENT, $test);
        }
    }

    /**
     * Handle PATCH requests to create or update a national calendar data resource.
     *
     * It is private as it is called from {@see updateRegionalCalendar}.
     *
     * The resource is created or updated in the `jsondata/sourcedata/nations/` directory.
     *
     * If the payload is valid according to {@see LitSchema::NATIONAL}, the response will be a JSON object
     * containing a success message.
     *
     * If the payload is invalid, the response will be a JSON error response with a 422 status code.
     */
    private function handleNationalCalendarUpdate()
    {
        $response     = new \stdClass();
        $calendarFile = strtr(
            JsonData::NATIONAL_CALENDARS_FILE,
            [
                '{nation}' => $this->params->key
            ]
        );

        $calendarI18nFile = strtr(
            JsonData::NATIONAL_CALENDARS_I18N_FILE,
            [
                '{nation}' => $this->params->key,
                '{locale}' => $this->params->locale
            ]
        );

        $test = $this->validateDataAgainstSchema($this->params->payload, LitSchema::NATIONAL);
        if ($test === true) {
            // We need to extract localized name properties from the payload,
            // and write them to the corresponding locale file,
            // but only for createNew or setProperty::name actions,
            // or for the makePatron action.
            // Make sure &$value is a reference, not a copy, so that the `name` property will be deleted on the original object.
            $i18nPayload = [];
            foreach ($this->params->payload->litcal as &$value) {
                if (
                    $value->metadata->action === 'createNew'
                    || ($value->metadata->action === 'setProperty' && $value->metadata->property === 'name')
                    || $value->metadata->action === 'makePatron'
                ) {
                    $i18nPayload[$value->festivity->event_key] = $value->festivity->name;
                }
                unset($value->festivity->name);
            }
            $data = json_encode(
                $this->params->payload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
            $i18nData = json_encode(
                $i18nPayload,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
            );
            file_put_contents(
                $calendarFile,
                $data . PHP_EOL
            );
            file_put_contents(
                $calendarI18nFile,
                $i18nData . PHP_EOL
            );
            $response->success = "Calendar data created or updated for Nation \"{$this->params->key}\"";
            self::produceResponse(json_encode($response));
        } else {
            self::produceErrorResponse(StatusCode::UNPROCESSABLE_CONTENT, $test);
        }
    }

    /**
     * Handle PATCH requests to create or update a wider region calendar data resource.
     *
     * It is private as it is called from {@see updateRegionalCalendar}.
     *
     * The resource is created or updated in the `jsondata/sourcedata/wider_regions/` directory.
     *
     * If the payload is valid according to {@see LitSchema::WIDERREGION}, the response will be a JSON object
     * containing a success message.
     *
     * If the payload is invalid, the response will be a JSON error response with a 422 status code.
     */
    private function handleWiderRegionCalendarUpdate()
    {
        $response = new \stdClass();
        $this->params->payload->metadata->wider_region = ucfirst(strtolower($this->params->payload->metadata->wider_region));
        $widerRegion = $this->params->payload->metadata->wider_region;
        $widerRegionPath = JsonData::WIDER_REGIONS_FOLDER . '/' . $widerRegion;
        $i18npath = strtr(JsonData::WIDER_REGIONS_I18N_FOLDER, [
            '{wider_region}' => $widerRegion
        ]);
        if (!file_exists($widerRegionPath)) {
            mkdir($widerRegionPath, 0755, true);
        }
        if (!file_exists($i18npath)) {
            mkdir($i18npath, 0755, true);
        }
        $translationJSON = new \stdClass();
        foreach ($this->params->payload->litcal as $CalEvent) {
            $translationJSON->{ $CalEvent->festivity->event_key } = '';
        }
        if (count($this->params->payload->metadata->locales) > 0) {
            foreach ($this->params->payload->metadata->locales as $iso) {
                $widerRegionI18nFile = strtr(JsonData::WIDER_REGIONS_I18N_FILE, [
                    '{wider_region}' => $widerRegion,
                    '{locale}' => $iso
                ]);
                if (!file_exists($widerRegionI18nFile)) {
                    file_put_contents(
                        $widerRegionI18nFile,
                        json_encode($translationJSON, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
                    );
                }
            }
        }

        $test = $this->validateDataAgainstSchema($this->params->payload, LitSchema::WIDERREGION);
        if ($test === true) {
            $data = json_encode($this->params->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $widerRegionFile = strtr(
                JsonData::WIDER_REGIONS_FILE,
                [
                    '{wider_region}' => $widerRegion
                ]
            );
            file_put_contents($widerRegionFile, $data . PHP_EOL);
            $response->success = "Calendar data created or updated for Wider Region \"{$widerRegion}\"";
            self::produceResponse(json_encode($response));
        } else {
            self::produceErrorResponse(StatusCode::UNPROCESSABLE_CONTENT, $test);
        }
    }

    /**
     * Handle PATCH requests to create or update a diocesan calendar data resource.
     *
     * It is private as it is called from {@see updateRegionalCalendar}.
     *
     * The resource is created or updated in the `jsondata/sourcedata/nations/` directory.
     *
     * If the payload is valid according to {@see LitSchema::DIOCESAN}, the response will be a JSON object
     * containing a success message.
     *
     * If the resource to update is not found in the diocesan calendars index or in the `jsondata/sourcedata/nations/{nation}/` directory, the response will be a JSON error response with a status code of 404 Not Found.
     * If the payload is not an object, the response will be a JSON error response with a status code of 400 Bad Request.
     * If the resource to update is not writable or the write was not successful, the response will be a JSON error response with a status code of 503 Service Unavailable.
     * If the payload is not valid according to {@see LitSchema::DIOCESAN}, the response will be a JSON error response with a status code of 422 Unprocessable Content.
     *
     */
    private function handleDiocesanCalendarUpdate()
    {
        $dioceseEntry = array_values(array_filter($this->CalendarsMetadata->diocesan_calendars, function ($item) {
            return $item->calendar_id === $this->params->key;
        }));

        if ( empty($dioceseEntry) ) {
            self::produceErrorResponse(StatusCode::NOT_FOUND, "Cannot update diocesan calendar resource {$this->params->key}.");
        }

        // Validate payload against schema
        $test = $this->validateDataAgainstSchema($this->params->payload, LitSchema::DIOCESAN);
        if (false === $test) {
            self::produceErrorResponse(StatusCode::UNPROCESSABLE_CONTENT, $test);
        }

        foreach ($this->params->payload->i18n as $locale => $i18nData) {
            $DiocesanCalendarI18nFile = strtr(
                JsonData::DIOCESAN_CALENDARS_I18N_FILE,
                [
                    '{nation}' => $dioceseEntry[0]->nation,
                    '{diocese}' => $dioceseEntry[0]->calendar_id,
                    '{locale}' => $locale
                ]
            );

            if (false === is_writable($DiocesanCalendarI18nFile)) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "Cannot update diocesan calendar i18n resource for {$this->params->key} at {$DiocesanCalendarI18nFile}, check file and folder permissions.");
            }

            // Update diocesan calendar i18n data for locale
            $calendarI18nData = json_encode($i18nData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if (
                false === file_put_contents(
                    $DiocesanCalendarI18nFile,
                    $calendarI18nData . PHP_EOL
                )
            ) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "Could not update diocesan calendar i18n resource {$this->params->key} in path {$DiocesanCalendarI18nFile}.");
            }
        }

        $DiocesanCalendarFile = strtr(
            JsonData::DIOCESAN_CALENDARS_FILE,
            [
                '{nation}' => $dioceseEntry[0]->nation,
                '{diocese}' => $dioceseEntry[0]->calendar_id,
                '{diocese_name}' => $dioceseEntry[0]->diocese
            ]
        );

        if (false === file_exists($DiocesanCalendarFile)) {
            self::produceErrorResponse(StatusCode::NOT_FOUND, "Cannot update diocesan calendar resource at {$DiocesanCalendarFile}, file not found.");
        }

        if (false === is_writable($DiocesanCalendarFile)) {
            self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "Cannot update diocesan calendar resource for {$this->params->key} at {$DiocesanCalendarFile}, check file and folder permissions.");
        }

        // Update diocesan calendar data
        // We no longer need the `i18n` property, so delete it
        unset($this->params->payload->i18n);
        $calendarData = json_encode($this->params->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (
            false === file_put_contents(
                $DiocesanCalendarFile,
                $calendarData . PHP_EOL
            )
        ) {
            self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "Could not update diocesan calendar resource {$this->params->key} in path {$DiocesanCalendarFile}.");
        }

        $response = new \stdClass();
        $response->success = "Calendar data created or updated for Diocese \"{$dioceseEntry[0]->diocese}\" (Nation: \"{$dioceseEntry[0]->nation}\")";
        $response->data = $this->params->payload;
        self::produceResponse(json_encode($response));
    }

    /**
     * Handle PATCH requests to create or update a regional calendar data resource.
     *
     * This is a private method and should only be called from {@see handleRequestMethod}.
     *
     * The resource is created or updated in the `jsondata/sourcedata/` directory.
     *
     * If the payload is valid according to the associated schema, the response will be a JSON object
     * containing a success message.
     *
     * If the payload is invalid, the response will be a JSON error response with a 422 status code.
     */
    private function updateRegionalCalendar()
    {
        switch ($this->params->category) {
            case 'NATIONALCALENDAR':
                $this->handleNationalCalendarUpdate();
                break;
            case 'WIDERREGIONCALENDAR':
                $this->handleWiderRegionCalendarUpdate();
                break;
            case 'DIOCESANCALENDAR':
                $this->handleDiocesanCalendarUpdate();
        }
    }

    /**
     * Get the paths for deleting a regional calendar data resource.
     *
     * The return value is an array with two elements:
     * - The first element is the path to the JSON file containing the calendar data.
     * - The second element is the path to the folder containing the i18n data for the calendar.
     *
     * This is a private method and should only be called from {@see deleteRegionalCalendar}.
     *
     * If the calendar for which deletion is requested is a diocesan calendar,
     * but a correponding entry is not found in the `/calendars` metadata index,
     * a 404 Not Found error response will be produced.
     *
     * @return array The paths for deleting a regional calendar data resource.
     */
    private function getPathsForCalendarDelete(): array
    {
        switch ($this->params->category) {
            case "DIOCESANCALENDAR":
                $dioceseEntry = array_values(array_filter($this->CalendarsMetadata->diocesan_calendars, function ($el) {
                    return $el->calendar_id === $this->params->key;
                }));
                if (empty($dioceseEntry)) {
                    self::produceErrorResponse(StatusCode::NOT_FOUND, "The resource requested for deletion {$this->params->key} is not known.");
                }
                $calendarDataFile = strtr(
                    JsonData::DIOCESAN_CALENDARS_FILE,
                    [
                        '{nation}' => $dioceseEntry[0]->nation,
                        '{diocese}' => $dioceseEntry[0]->calendar_id,
                        '{diocese_name}' => $dioceseEntry[0]->diocese
                    ]
                );
                $calendarI18nFolder = strtr(
                    JsonData::DIOCESAN_CALENDARS_I18N_FOLDER,
                    [
                        '{nation}' => $dioceseEntry[0]->nation,
                        '{diocese}' => $dioceseEntry[0]->calendar_id
                    ]
                );
                break;
            case "WIDERREGIONCALENDAR":
                $calendarDataFile = strtr(
                    JsonData::WIDER_REGIONS_FILE,
                    [
                        '{wider_region}' => $this->params->key
                    ]
                );
                $calendarI18nFolder = strtr(
                    JsonData::WIDER_REGIONS_I18N_FOLDER,
                    [
                        '{wider_region}' => $this->params->key
                    ]
                );
                break;
            case "NATIONALCALENDAR":
                $calendarDataFile = strtr(
                    JsonData::NATIONAL_CALENDARS_FILE,
                    [
                        '{nation}' => $this->params->key
                    ]
                );
                $calendarI18nFolder = strtr(
                    JsonData::NATIONAL_CALENDARS_I18N_FOLDER,
                    [
                        '{nation}' => $this->params->key
                    ]
                );
                break;
        }

        return [$calendarDataFile, $calendarI18nFolder];
    }

    /**
     * Handle DELETE requests to delete a regional calendar data resource.
     *
     * This is a private method and should only be called from {@see handleRequestMethod}.
     *
     * The resource is deleted from the `jsondata/sourcedata/` directory.
     *
     * If the resource is successfully deleted, the response will be a JSON object
     * containing a success message.
     *
     * If the resource does not exist, a 404 error will be returned.
     */
    private function deleteRegionalCalendar()
    {
        $response = new \stdClass();

        [$calendarDataFile, $calendarI18nFolder] = $this->getPathsForCalendarDelete();

        if (file_exists($calendarDataFile) && file_exists($calendarI18nFolder)) {
            if (false === is_writable($calendarDataFile)) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, check file and folder permissions.");
            }

            // We want to make sure to also remove the containing folder, let's get the parent folder for later removal
            $calendarDataFolder = dirname($calendarDataFile);

            // And in the case of a diocesan calendar, if the parent `nation_id` folder is empty, remove it as well
            // so let's get a reference to the parent folder to check later
            if ($this->params->category === "DIOCESANCALENDAR") {
                $dioceseNationFolder = dirname($calendarDataFolder);
            }

            if (false === unlink($calendarDataFile)) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully.");
            };
            foreach (glob($calendarI18nFolder . "/*.json") as $file) {
                if (false === is_writable($file)) {
                    self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, check i18n file and folder permissions.");
                }
                if (false === unlink($file)) {
                    self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, i18n file could not be removed.");
                };
            }
            if (false === rmdir($calendarI18nFolder)) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, i18n folder could not be removed.");
            }
            if (false === rmdir($calendarDataFolder)) {
                self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, data folder could not be removed.");
            }
            if ($this->params->category === "DIOCESANCALENDAR") {
                // Check if the parent `nation_id` folder is empty, if it is, remove it too
                if (count(scandir($dioceseNationFolder)) === 2) { // only . and ..
                    if (false === rmdir($dioceseNationFolder)) {
                        self::produceErrorResponse(StatusCode::SERVICE_UNAVAILABLE, "The resource '{$this->params->key}' requested for deletion was not removed successfully, diocese nation folder could not be removed.");
                    }
                }
            }
        } else {
            self::produceErrorResponse(StatusCode::NOT_FOUND, "The resource '{$this->params->key}' requested for deletion (or the relative i18n folder) was not found on this server.");
        }
        $response->success = "Calendar data \"{$this->params->category}/{$this->params->key}\" deletion successful.";
        self::produceResponse(json_encode($response));
    }



    /**
     * Function validateDataAgainstSchema
     *
     * @param array|object $data Data to validate
     * @param string $schemaUrl  Schema to validate against
     *
     * @return boolean
     */
    private function validateDataAgainstSchema(array|object $data, string $schemaUrl): bool
    {
        $schema = Schema::import($schemaUrl);
        try {
            $schema->in($data);
            return true;
        } catch (InvalidValue | \Exception $e) {
            self::produceErrorResponse(StatusCode::UNPROCESSABLE_CONTENT, LitSchema::ERROR_MESSAGES[ $schemaUrl ] . PHP_EOL . $e->getMessage());
        }
    }

    /**
     * Retrieves the payload from the request body, either JSON or YAML encoded, for PUT, PATCH, and POST requests.
     *
     * If the request method is POST, it will also retrieve the locale from the payload, if present, and set it on the
     * `$data` object passed as argument.
     *
     * If the request method is PUT or PATCH, and the payload is not either JSON or YAML encoded, it will produce a
     * 400 Bad Request error.
     *
     * @param object $data the object to set the locale and payload on
     *
     * @return object the object with the locale and payload set
     */
    private static function retrievePayloadFromPostPutPatchRequest(object $data): ?object
    {
        $payload = null;
        $required = self::$Core->getRequestMethod() !== RequestMethod::POST;
        switch (self::$Core->getRequestContentType()) {
            case RequestContentType::JSON:
                $payload = self::$Core->readJsonBody($required);
                break;
            case RequestContentType::YAML:
                $payload = self::$Core->readYamlBody($required);
                break;
            case RequestContentType::FORMDATA:
                if ($required && count($_REQUEST) === 0) {
                    self::produceErrorResponse(
                        StatusCode::BAD_REQUEST,
                        "Expected non empty payload in body of request, either JSON encoded or YAML encoded or form encoded."
                    );
                }
                $payload = (object)$_REQUEST;
                break;
            default:
                if ($required) {
                    // the payload MUST be in the body of the request, either JSON encoded or YAML encoded or form encoded
                    self::produceErrorResponse(
                        StatusCode::BAD_REQUEST,
                        "Expected non empty payload in body of request, either JSON encoded or YAML encoded or form encoded."
                    );
                }
        }

        if (self::$Core->getRequestMethod() === RequestMethod::POST && $payload !== null) {
            if (property_exists($payload, 'locale')) {
                $data->locale = $payload->locale;
            }
        }

        if ($payload !== null) {
            $data->payload = $payload;
        }

        return $data;
    }

    /**
     * Set the category, key (if applicable), and locale (if applicable) based on the request path parts and method.
     *
     * @param array $requestPathParts the parts of the request path
     *
     * @return object the object with the category, key, and locale set
     */
    private static function setDataFromPath(array $requestPathParts): object
    {
        $data = new \stdClass();

        $data->category = RegionalDataParams::EXPECTED_CATEGORIES[$requestPathParts[0]];

        if (self::$Core->getRequestMethod() !== RequestMethod::PUT) {
            $data->key = $requestPathParts[1];
        }

        if (in_array(self::$Core->getRequestMethod(), [RequestMethod::GET, RequestMethod::POST], true)) {
            // For GET and POST request, there may be an optional third path parameter (= locale),
            // which will determine whether we are requesting calendar data or i18n data
            if (isset($requestPathParts[2])) {
                $data->i18n = $requestPathParts[2];
            }
            // For GET requests, we attempt to retrieve the locale from the query string if present
            if (self::$Core->getRequestMethod() === RequestMethod::GET && isset($_GET['locale'])) {
                $data->locale = \Locale::canonicalize($_GET['locale']);
            }
        }

        // If the request method is PATCH, or PUT, we expect a payload
        // For POST requests, there might be a payload
        // So in all these cases, we attempt to retrieve the payload from the request body if present
        if (in_array(self::$Core->getRequestMethod(), [RequestMethod::POST, RequestMethod::PUT, RequestMethod::PATCH], true)) {
            $data = RegionalData::retrievePayloadFromPostPutPatchRequest($data);
        }
        return $data;
    }

    /**
     * Validate the request path parts for the RegionalData resource.
     *
     * Will produce a 400 error response if the request path parts are invalid.
     *
     * @param array $requestPathParts the parts of the request path
     */
    private static function validateRequestPath(array $requestPathParts): void
    {
        // For GET and POST requests, we expect at least two path params (= category and key) for Calendar Data requests,
        // and at most three path params (= category, key, and locale) for I18n Data requests
        if (in_array(self::$Core->getRequestMethod(), [RequestMethod::GET, RequestMethod::POST], true)) {
            if (count($requestPathParts) < 2 || count($requestPathParts) > 3) {
                self::produceErrorResponse(
                    StatusCode::BAD_REQUEST,
                    "Expected at least two and at most three path params for GET and POST requests, received " . count($requestPathParts)
                );
            }
        }
        // For PUT requests, we expect exactly one path param (= category)
        elseif (self::$Core->getRequestMethod() === RequestMethod::PUT) {
            if (count($requestPathParts) !== 1) {
                self::produceErrorResponse(
                    StatusCode::BAD_REQUEST,
                    "Expected one path param for PUT requests, received " . count($requestPathParts)
                );
            }
        }
        // For PATCH and DELETE requests, we expect exactly two path params
        elseif (count($requestPathParts) !== 2) {
            self::produceErrorResponse(
                StatusCode::BAD_REQUEST,
                "Expected two and exactly two path params for PATCH and DELETE requests, received " . count($requestPathParts)
            );
        }

        // In all cases, we check if the category param is valid
        if (false === array_key_exists($requestPathParts[0], RegionalDataParams::EXPECTED_CATEGORIES)) {
            self::produceErrorResponse(
                StatusCode::BAD_REQUEST,
                "Unexpected path param {$requestPathParts[0]}, acceptable values are: "
                    . implode(', ', array_keys(RegionalDataParams::EXPECTED_CATEGORIES))
            );
        }
    }

    /**
     * Handle the request parameters for the RegionalData resource.
     *
     * The request parameters are expected to be in the request path (or possibly in the request body when the request content type is JSON or YAML).
     * The request body is expected to be a JSON or YAML encoded object, with the following properties:
     * - category: a string indicating the category of the Regional Calendar data, one of the values in RegionalDataParams::EXPECTED_CATEGORIES
     * - key: a string indicating the key of the Regional Calendar data
     * - locale: a string indicating the locale of the Regional Calendar data, only applicable for the WIDERREGIONCALENDAR category
     *
     * If the request parameters are invalid, it will produce an error response with a status code of 400.
     *
     * @param array $requestPathParts the parts of the request path
     */
    private function handleRequestParams(array $requestPathParts = []): void
    {
        RegionalData::validateRequestPath($requestPathParts);
        $data = RegionalData::setDataFromPath($requestPathParts);

        // In the case of a PUT request, we expect only one PATH parameter, we only retrieve the payload from the request body
        if (self::$Core->getRequestMethod() === RequestMethod::PUT) {
            if (
                !property_exists($data, 'payload')
                || $data->payload === null
                || !property_exists($data->payload, 'litcal')
                || !property_exists($data->payload, 'i18n')
                || !property_exists($data->payload, 'metadata')
                || !property_exists($data->payload->metadata, 'diocese_id')
            ) {
                self::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid payload in request. Must receive non empty payload in body of request, in JSON or YAML or form encoded format, with properties `payload`, `payload.litcal`, `payload.i18n`, `payload.metadata`, and `payload.metadata.diocese_id`");
            } else {
                $data->key = $data->payload->metadata->diocese_id;
            }
        }

        if (self::$Core->getRequestMethod() === RequestMethod::PATCH) {
            if (
                !property_exists($data, 'payload')
                || $data->payload === null
                || !property_exists($data->payload, 'litcal')
                || !property_exists($data->payload, 'i18n')
                || !property_exists($data->payload, 'metadata')
            ) {
                self::produceErrorResponse(StatusCode::BAD_REQUEST, "Invalid payload in request. Must receive non empty payload in body of request, in JSON or YAML or form encoded format, with properties `payload`, `payload.litcal`, `payload.i18n`, and `payload.metadata`");
            }
        }

        if (false === $this->params->setData($data)) {
            self::produceErrorResponse(StatusCode::BAD_REQUEST, "The params do not seem to be correct, must have params `category` and `key` with acceptable values");
        }
    }

    /**
     * Produce an error response with the given HTTP status code and description.
     *
     * The description is a short string that should be used to give more context to the error.
     *
     * The function will output the error in the response format specified by the Accept header
     * of the request (JSON or YAML) and terminate the script execution with a call to die().
     *
     * @param int $statusCode the HTTP status code to return
     * @param string $description a short description of the error
     */
    public static function produceErrorResponse(int $statusCode, string $description): void
    {
        header($_SERVER[ "SERVER_PROTOCOL" ] . StatusCode::toString($statusCode), true, $statusCode);
        $message = new \stdClass();
        $message->status = "ERROR";
        $statusMessage = "";
        switch (self::$Core->getRequestMethod()) {
            case RequestMethod::PUT:
                $statusMessage = "Resource not Created";
                break;
            case RequestMethod::PATCH:
                $statusMessage = "Resource not Updated";
                break;
            case RequestMethod::DELETE:
                $statusMessage = "Resource not Deleted";
                break;
            default:
                $statusMessage = "Sorry what was it you wanted to do with this resource?";
        }
        $message->response = $statusCode === 404 ? "Resource not Found" : $statusMessage;
        $message->description = $description;
        $response = json_encode($message);
        switch (self::$Core->getResponseContentType()) {
            case AcceptHeader::YAML:
                $responseObj = json_decode($response, true);
                echo yaml_emit($responseObj, YAML_UTF8_ENCODING);
                break;
            case AcceptHeader::JSON:
            default:
                echo $response;
        }
        die();
    }

    /**
     * Outputs the response for the /data endpoint.
     *
     * Outputs the response as either JSON or YAML, depending on the value of
     * self::$Core->getResponseContentType(). If the request method was PUT or
     * PATCH, it also sets a 201 Created status code.
     *
     * @param string $jsonEncodedResponse the response as a JSON encoded string
     */
    private static function produceResponse(string $jsonEncodedResponse): void
    {
        if (in_array(self::$Core->getRequestMethod(), [RequestMethod::PUT, RequestMethod::PATCH], true)) {
            header($_SERVER[ "SERVER_PROTOCOL" ] . " 201 Created", true, 201);
        }
        switch (self::$Core->getResponseContentType()) {
            case AcceptHeader::YAML:
                $responseObj = json_decode($jsonEncodedResponse, true);
                echo yaml_emit($responseObj, YAML_UTF8_ENCODING);
                break;
            case AcceptHeader::JSON:
            default:
                echo $jsonEncodedResponse;
        }
        die();
    }

    /**
     * Initializes the RegionalData class.
     *
     * @param array $requestPathParts the path parameters from the request
     *
     * This method will:
     * - Initialize the instance of the Core class
     * - If the $requestPathParts argument is not empty, it will set the request path parts
     * - It will validate the request content type
     * - It will set the request headers
     * - It will load the Diocesan Calendars index
     * - It will handle the request method
     */
    public function init(array $requestPathParts = [])
    {
        self::$Core->init();
        if (in_array(self::$Core->getRequestMethod(), [RequestMethod::GET, RequestMethod::OPTIONS], true)) {
            self::$Core->validateAcceptHeader(true);
        } else {
            self::$Core->validateAcceptHeader(false);
        }
        if (self::$Core->getRequestMethod() === RequestMethod::OPTIONS) {
            die();
        }
        self::$Core->setResponseContentTypeHeader();
        $this->handleRequestParams($requestPathParts);
        $this->handleRequestMethod();
    }
}
