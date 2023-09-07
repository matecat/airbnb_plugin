<?php

namespace Features\Airbnb\Decorator;


use AbstractCatDecorator;
use CookieManager;
use Features\Airbnb;
use Features\Airbnb\Utils\Routes;
use Features\Airbnb\Model\SegmentDelivery\SegmentDeliveryDao;
use INIT;

class CatDecorator extends AbstractCatDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template;

    public function decorate() {
        $this->_checkSessionCookie();
        $path = realpath( Airbnb::getPluginBasePath() . '/../static/build/' );
        $files = scandir($path);
        foreach ($files as $file) {
            $path_parts = pathinfo($file);
            if ($path_parts['extension'] === 'css') {
                $this->template->append( 'css_resources', Routes::staticBuild( $file ) );
            } else if ($path_parts['extension'] === 'js'){
                $this->template->append( 'footer_js', Routes::staticBuild( $file ) );
            }
        }
//        $this->template->append( 'footer_js', Routes::staticBuild( '/airbnb.28ad8166d78e0986cf33.js' ) );
//        $this->template->append( 'css_resources', Routes::staticBuild( '/runtime.28ad8166d78e0986cf33.js' ) );

        $this->assignCatDecorator();
    }

    protected function _checkSessionCookie() {
        $chunk = $this->controller->getChunk();

        if ( !isset( $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ] ) ) {
            return;
        }

        $cookie  = $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ];
        $payload = \SimpleJWT::getValidPayload( $cookie );

        if ( $payload[ 'id_job' ] == $chunk->id ) {
            $isAJobDeliverable = SegmentDeliveryDao::isAJobDeliverable($payload[ 'id_job' ]);
            $this->template->append( 'config_js', [
                    'airbnb_ontool'     => $payload[ 'ontool' ],
                    'airbnb_auth_token' => $cookie,
                    'delivery_available' => $isAJobDeliverable
            ] );
        }

        unset( $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ] );
        CookieManager::setCookie( Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id,
                null,
                [
                        'expires'  => strtotime( '-20 minutes' ),
                        'path'     => '/',
                        'domain'   => INIT::$COOKIE_DOMAIN,
                        'secure'   => true,
                        'httponly' => true,
                        'samesite' => 'None',
                ]
        );
    }

    protected function decorateForTranslate(){
        $this->template->footer_show_revise_link = false;
    }

    /**
     * Empty method because it's not necessery to do again what is written into the parent
     */
    protected function decorateForRevision() {
    }

}