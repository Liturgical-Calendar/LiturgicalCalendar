<?php

/**
 * Liturgical Calendar PHP engine script
 * Author: John Romano D'Orazio
 * Email: priest@johnromanodorazio.com
 * Licensed under the Apache 2.0 License
 * Version 3.0
 * Date Created: 27 December 2017
 * Note: it is necessary to set up the MySQL liturgy tables prior to using this script
 */


/**********************************************************************************
 *                          ABBREVIATIONS                                         *
 * CB     Cerimonial of Bishops                                                   *
 * CCL    Code of Canon Law                                                       *
 * IM     General Instruction of the Roman Missal                                 *
 * IH     General Instruction of the Liturgy of the Hours                         *
 * LH     Liturgy of the Hours                                                    *
 * LY     Universal Norms for the Liturgical Year and the Calendar ( Roman Missal ) *
 * OM     Order of Matrimony                                                      *
 * PC     Instruction regarding Proper Calendars                                  *
 * RM     Roman Missal                                                            *
 * SC     Sacrosanctum Concilium, Conciliar Constitution on the Sacred Liturgy    *
 *                                                                                *
 *********************************************************************************/


/**********************************************************************************
 *         EDITIONS OF THE ROMAN MISSAL AND OF THE GENERAL ROMAN CALENDAR         *
 *                                                                                *
 * Editio typica, 1970                                                            *
 * Reimpressio emendata, 1971                                                     *
 * Editio typica secunda, 1975                                                    *
 * Editio typica tertia, 2002                                                     *
 * Editio typica tertia emendata, 2008                                            *
 * -----------------------------------                                            *
 * Roman Missal [ USA ], 2011                                                       *
 * -----------------------------------                                            *
 * Messale Romano [ ITALIA ], 1983                                                  *
 * Messale Romano [ ITALIA ], 2020                                                  *
 *                                                                                *
 *********************************************************************************/

error_reporting( E_ALL );
ini_set( 'display_errors', 1 );
ini_set( 'date.timezone', 'Europe/Vatican' );

include_once( 'includes/enums/AcceptHeader.php' );
include_once( 'includes/enums/CacheDuration.php' );
include_once( 'includes/enums/RequestMethod.php' );
include_once( 'includes/enums/RequestContentType.php' );
include_once( 'includes/enums/ReturnType.php' );

include_once( "includes/LitCalAPI.php" );

$LitCalEngine = new LitCalAPI();
$LitCalEngine->APICore->setAllowedOrigins( [
    "https://johnromanodorazio.com",
    "https://www.johnromanodorazio.com",
    "https://litcal.johnromanodorazio.com",
    "https://litcal-staging.johnromanodorazio.com"
] );
$LitCalEngine->APICore->setAllowedRequestMethods( [ REQUEST_METHOD::GET, REQUEST_METHOD::POST ] );
$LitCalEngine->APICore->setAllowedRequestContentTypes( [ REQUEST_CONTENT_TYPE::JSON, REQUEST_CONTENT_TYPE::FORMDATA ] );
$LitCalEngine->APICore->setAllowedAcceptHeaders( [ ACCEPT_HEADER::JSON, ACCEPT_HEADER::XML, ACCEPT_HEADER::ICS ] );
$LitCalEngine->setAllowedReturnTypes( [ RETURN_TYPE::JSON, RETURN_TYPE::XML, RETURN_TYPE::ICS ] );
$LitCalEngine->setCacheDuration( CACHEDURATION::MONTH );
$LitCalEngine->Init();
