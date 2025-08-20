<?php

/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 3/21/16
 * Time: 12:05 PM
 */

namespace Features\Airbnb\Utils;

use Exception;
use Features\Airbnb;
use Model\FeaturesBase\PluginsLoader;
use Utils\Url\CanonicalRoutes;

class Routes {


    /**
     * @throws Exception
     */
    public static function staticSrc( $file, $options = [] ): string {
        $path = PluginsLoader::getPluginDirectoryName( Airbnb::FEATURE_CODE );
        $host = CanonicalRoutes::pluginsBase( $options );

        return $host . "/$path/static/src/$file";
    }

    /**
     * @throws Exception
     */
    public static function staticBuild( $file, $options = [] ): string {
        $path = PluginsLoader::getPluginDirectoryName( Airbnb::FEATURE_CODE );
        $host = CanonicalRoutes::pluginsBase( $options );

        return $host . "/$path/static/build/$file";
    }
}