<?php

namespace Features\Airbnb\Decorator;


use CookieManager;
use Features\Airbnb;
use Features\Airbnb\Model\SegmentDelivery\SegmentDeliveryDao;
use INIT;
use SimpleJWT;
use Views\TemplateDecorator\AbstractDecorator;
use Views\TemplateDecorator\Arguments\ArgumentInterface;

class CatDecorator extends AbstractDecorator {

    private ?ArgumentInterface $arguments;

    public function decorate( ?ArgumentInterface $arguments = null ) {
        $this->arguments = $arguments;
        $this->_checkSessionCookie();
        $this->assignCatDecorator();
    }

    protected function _checkSessionCookie() {

        $chunk = $this->arguments->getJob();

        if ( !isset( $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ] ) ) {
            return;
        }

        $cookie  = $_COOKIE[ Airbnb::DELIVERY_COOKIE_PREFIX . $chunk->id ];
        $payload = SimpleJWT::getValidPayload( $cookie );

        if ( $payload[ 'id_job' ] == $chunk->id ) {
            $isAJobDeliverable = SegmentDeliveryDao::isAJobDeliverable( $payload[ 'id_job' ] );
            $this->template->append( 'config_js', [
                    'airbnb_ontool'      => $payload[ 'ontool' ],
                    'airbnb_auth_token'  => $cookie,
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


    /**
     * Empty method because it's not necessary to do again what is written into the parent
     */
    protected function decorateForRevision() {
    }

    protected function assignCatDecorator() {
        if ( $this->arguments->isRevision() ) {
            $this->decorateForRevision();
        } else {
            $this->decorateForTranslate();
        }
    }

    protected function decorateForTranslate() {
        $this->template->{'footer_show_revise_link'} = false;
    }

}