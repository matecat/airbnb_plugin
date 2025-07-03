<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/21/16
 * Time: 12:05 PM
 */
namespace Features\Airbnb\Utils;

use Features\Airbnb;

class Routes {


    public static function staticSrc( $file, $options=array() ) {
        $path = \Model\FeaturesBase\FeaturesFactory::getPluginDirectoryName(Airbnb::FEATURE_CODE);
        $host = \Routes::pluginsBase( $options );
        return $host . "/$path/static/src/$file" ;
    }

    public static function staticBuild( $file, $options=array() ) {
        $path = \Model\FeaturesBase\FeaturesFactory::getPluginDirectoryName(Airbnb::FEATURE_CODE);
        $host = \Routes::pluginsBase( $options );
        return $host . "/$path/static/build/$file" ;
    }
}