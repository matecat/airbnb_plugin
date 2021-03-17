<?php

namespace Features\Airbnb\Decorator;


use AbstractCatDecorator;
use Features\Airbnb;
use Features\Airbnb\Utils\Routes;
use Features\Airbnb\Model\SegmentDelivery\SegmentDeliveryDao;

class CatDecorator extends AbstractCatDecorator {
    /**
     * @var \PHPTALWithAppend
     */
    protected $template;

    public function decorate() {
        $this->_checkSessionCookie();

        $this->template->append( 'footer_js', Routes::staticBuild( '/airbnb-core-build.js' ) );
        $this->template->append( 'css_resources', Routes::staticBuild( '/airbnb-build.css' ) );

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
        setcookie( Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id, null, strtotime( '-20 minutes' ), '/', \INIT::$COOKIE_DOMAIN );
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