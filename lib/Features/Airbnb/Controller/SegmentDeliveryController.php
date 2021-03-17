<?php
/**
 * Created by PhpStorm.
 * User: fregini
 * Date: 12/03/2019
 * Time: 13:03
 */

namespace Features\Airbnb\Controller;

use ActivityLog\Activity;
use ActivityLog\ActivityLogStruct;
use API\V2\Exceptions\AuthenticationError;
use API\V2\Exceptions\ExternalServiceException;
use API\V2\KleinController;
use API\V2\Validators\ChunkPasswordValidator;
use Chunks_ChunkStruct;
use Constants_JobStatus;
use DateTime;
use DomainException;
use Features\Airbnb;
use Features\Airbnb\Controller\Validators\AirbnbTOSAuthLoginValidator;
use InvalidArgumentException;
use Jobs_JobDao;
use LQA\ChunkReviewStruct;
use Routes;
use Segments_SegmentDao;
use Segments_SegmentNoteDao;
use SimpleJWT;
use Translations_SegmentTranslationDao;
use Utils;

class SegmentDeliveryController extends KleinController {

    /** @var  Chunks_ChunkStruct */
    protected $chunk;

    /** @var ChunkReviewStruct */
    protected $chunkReview;

    public function auth() {

        $tos_jwt = $this->request->param( "tos_jwt" );

        if ( empty( $tos_jwt ) ) {
            throw new InvalidArgumentException( "Bad request", 400 );
        }

        $jwt = new SimpleJWT( [
                'id_job'             => $this->chunk->id,
                'tos_authentication' => "1",
                'ontool'             => $this->request->param( 'ontool' )
        ] );

        $jwt->setTimeToLive( 20 ); //set 20 seconds

        $this->response->json( [
                'tos_jwt'     => $tos_jwt,
                'matecat_jwt' => $jwt->jsonSerialize()
        ] );

        return $this;

    }

    /**
     * @throws \Exception
     */
    public function startSession() {

        $payload = \SimpleJWT::getValidPayload( $this->request->param( 'matecat_jwt' ) );
        if ( $payload[ 'id_job' ] != $this->chunk->id || $payload[ 'tos_authentication' ] != "1" ) {
            throw new DomainException( "Forbidden. Invalid Token Context." );
        }

        $jwt = new SimpleJWT( [
                'id_job'        => $this->chunk->id,
                'session_valid' => "1",
                'uid'           => $this->getUser()->uid,
                'ontool'        => $this->request->param( 'ontool' )
        ] );

        $jwt->setTimeToLive( 60 * 60 ); //set 60 minutes

        //by setting the cookie this endpoint is not stateless and MUST be used by clients
        setcookie( Airbnb::DELIVERY_COOKIE_PREFIX . $this->request->param( 'id_job' ), $jwt->jsonSerialize(), strtotime( '+2 minutes' ), '/', \INIT::$COOKIE_DOMAIN );

        if ( $this->chunk->isArchiveable() || $this->chunk->status_owner == Constants_JobStatus::STATUS_ARCHIVED ) {

            Jobs_JobDao::updateJobStatus( $this->chunk, Constants_JobStatus::STATUS_ACTIVE );
            $lastSegmentsList = Translations_SegmentTranslationDao::getMaxSegmentIdsFromJob( $this->chunk );
            Translations_SegmentTranslationDao::updateLastTranslationDateByIdList( $lastSegmentsList, Utils::mysqlTimestamp( time() ) );

            $this->_saveActivity( ActivityLogStruct::JOB_UNARCHIVED );

        }

        $project      = $this->chunk->getProject();

        if( !$this->chunk->getIsReview() ){
            $redirect_url = Routes::translate(  $project->name, $this->chunk->id, $this->chunk->password, $this->chunk->source, $this->chunk->target );
        } else {
            $options = [
                    'revision_number' => ($this->chunk->isSecondPassReview()) ? 2 : 1
            ];

            $redirect_url = Routes::revise( $project->name, $this->chunk->id, $this->chunkReview->review_password, $this->chunk->source, $this->chunk->target, $options );
        }

        $this->response->header( 'Cache-Control', 'max-age=0' );
        $this->response->redirect( $redirect_url . '#' . $this->request->param( 'id_segment' ) );
    }

    /**
     * @return $this
     * @throws AuthenticationError
     * @throws ExternalServiceException
     */
    public function send() {

        $segment_translation = Translations_SegmentTranslationDao::findBySegmentAndJob( $this->request->param( 'id_segment' ), $this->chunk->id );
        // rebuild the trans-unit and post to the external API

        try {
            $payload = SimpleJWT::getValidPayload( $this->request->param( 'jwt' ) );
        } catch ( \Exception $e ) {
            throw new AuthenticationError( $e->getMessage(), $e->getCode() );
        }

        if ( $payload[ 'uid' ] != $this->getUser()->uid || $payload[ 'session_valid' ] != "1" ) {
            throw new AuthenticationError( "Invalid Token." );
        }

        $segment = ( new Segments_SegmentDao() )->getById( $segment_translation->id_segment );

        $notes = [];
        foreach ( Segments_SegmentNoteDao::getBySegmentId( $segment->id ) as $note ) {
            if ( strpos( $note->note, '|¶|' ) !== false ) {
                $k_v                = explode( '|¶|', $note->note );
                $notes[ $k_v[ 0 ] ] = $k_v[ 1 ];
            }
        }

        $this->_makeDelivery( $segment, $segment_translation, $notes, $this->chunk );

        $this->response->json( [
                'translation' => $segment_translation->translation
        ] );

        return $this;
    }

    protected function _makeDelivery( $segment, $segment_translation, $notes, Chunks_ChunkStruct $chunk ) {

        $config = Airbnb::getConfig();

        $mh = new \MultiCurlHandler();

        $portParams = array_merge( [  // Redundant, the URL is enough
                'trans_unit_id' => $segment->internal_id,
                'source'        => $segment->segment,
                'target'        => $segment_translation->translation,
                'source_lang'   => $this->chunk->source,
                'target_lang'   => $this->chunk->target,
                'id_segment'    => $segment->id,
                'user_email'    => $this->user->email
        ], $notes );


        $curl_additional_params = [
                CURLOPT_HEADER         => false,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERAGENT      => \INIT::MATECAT_USER_AGENT . \INIT::$BUILD_NUMBER,
                CURLOPT_TIMEOUT        => 60,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
                CURLOPT_HTTPHEADER     => [
                        'Content-Type: application/json',
                ],
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => json_encode( $portParams /* Redundant, the URL is enough, add to pass more info to the service */ )
        ];

        if( new \DateTime( $chunk->create_date ) > new DateTime( "2020-08-25 12:00" ) ){
            return;
        }

        $resource = $mh->createResource( $config[ 'old_delivery_endpoint' ] . implode( "/", [ $this->chunk->id, $this->chunk->password, $segment->internal_id ] ), $curl_additional_params );


        $mh->multiExec();
        $mh->multiCurlCloseAll();

        $log                                   = $mh->getSingleLog( $resource );
        $log[ 'options' ][ 'post_parameters' ] = $portParams; //decoded parameters for logging
        \Log::doJsonLog( $log );

        if ( $mh->hasError( $resource ) ) {
            throw new ExternalServiceException( $mh->getError( $resource )[ 'error' ] );
        }

        $this->_saveActivity( ActivityLogStruct::TRANSLATION_DELIVERED );

    }

    public function setChunk( Chunks_ChunkStruct $chunk ) {
        $this->chunk = $chunk;
        return $this;
    }

    public function setChunkReview( ChunkReviewStruct $chunk_review ){
        $this->chunkReview = $chunk_review;
        return $this;
    }

    protected function afterConstruct() {

        $validator  = new ChunkPasswordValidator( $this );

        $validator->onSuccess( function () use ( $validator ) {
            $this->setChunk( $validator->getChunk() );
            if( $validator->getChunkReview() ){
                $this->setChunkReview( $validator->getChunkReview() );
            }
        } );

        $this->appendValidator( $validator );
        $this->appendValidator( new AirbnbTOSAuthLoginValidator( $this ) );

    }

    protected function _saveActivity( $action ) {
        $activity             = new ActivityLogStruct();
        $activity->id_job     = $this->chunk->id;
        $activity->id_project = $this->chunk->id_project;
        $activity->action     = $action;
        $activity->ip         = Utils::getRealIpAddr();
        $activity->uid        = $this->user->uid;
        $activity->event_date = date( 'Y-m-d H:i:s' );
        Activity::save( $activity );
    }

}

