<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 14/05/2018
 * Time: 15:10
 */

namespace Features\Airbnb\Model\Analysis;


class CustomPayableRates extends \Analysis_PayableRates {

    public static $DEFAULT_PAYABLE_RATES = [
        'NO_MATCH'    => 100,
        '50%-74%'     => 100,
        '75%-84%'     => 60,
        '85%-94%'     => 60,
        '95%-99%'     => 60,
        '100%'        => 30,
        '100%_PUBLIC' => 30,
        'REPETITIONS' => 30,
        'INTERNAL'    => 60,
        'ICE'         => 0,
        'MT'          => 77
    ];

    protected static $langPair2MTpayableRates = [
        "en" => [
            "it" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 72
            ],
            "fr" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 72
            ],
            "pt" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 72
            ],
            "es" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 72
            ],
            "nl" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 72
            ],
            "pl" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "uk" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "hi" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "fi" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "tr" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "ru" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "zh" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "ar" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "ko" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "lt" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "ja" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "he" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ],
            "sr" => [
                'NO_MATCH'    => 100,
                '50%-74%'     => 100,
                '75%-84%'     => 60,
                '85%-94%'     => 60,
                '95%-99%'     => 60,
                '100%'        => 30,
                '100%_PUBLIC' => 30,
                'REPETITIONS' => 30,
                'INTERNAL'    => 60,
                'ICE'         => 0,
                'MT'          => 80
            ]
        ]
    ];

    public static function getPayableRates( $source, $target ) {
        self::$langPair2MTpayableRates[ 'en-GB' ] = self::$langPair2MTpayableRates[ 'en-US' ];

        return parent::getPayableRates( $source, $target );
    }

}