<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use API\V2\Json\ProjectUrls;
use Engines_AbstractEngine;
use Engines_MMT;
use Features;
use Features\Airbnb\Model\Analysis\CustomPayableRates;
use Features\Airbnb\Utils\SmartCount\Pluralization;
use Klein\Klein;
use LQA\QA;
use Matecat\SubFiltering\Commons\Pipeline;
use Matecat\SubFiltering\Filters\HtmlToPh;
use Matecat\SubFiltering\Filters\LtGtDoubleDecode;
use Matecat\SubFiltering\Filters\PlaceHoldXliffTags;
use Matecat\SubFiltering\Filters\SmartCounts;
use Matecat\SubFiltering\Filters\Variables;
use Predis\Connection\ConnectionException;
use ReflectionException;
use Segments_SegmentStruct;
use TaskRunner\Commons\QueueElement;
use Users_UserStruct;

class Airbnb extends BaseFeature {

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const DELIVERY_COOKIE_PREFIX = 'airbnb_session_';

    public static $dependencies = [
            Features::TRANSLATION_VERSIONS,
            Features::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
        route( '/job/[:id_job]/[:password]/segment_delivery/[:id_segment]/session', 'POST', 'Features\Airbnb\Controller\SegmentDeliveryController', 'auth' );
        route( '/job/[:id_job]/[:password]/segment_delivery/[:id_segment]/session', 'GET', 'Features\Airbnb\Controller\SegmentDeliveryController', 'startSession' );
        route( '/job/[:id_job]/[:password]/segment_delivery', 'POST', 'Features\Airbnb\Controller\SegmentDeliveryController', 'send' );
    }

    /**
     * @param $_segment_metadata array
     * @param $projectStructure
     *
     * @return array
     * @see \ProjectManager::_storeSegments()
     *
     */
    public function appendFieldToAnalysisObject( $_segment_metadata, \ArrayObject $projectStructure ) {

        if ( $projectStructure[ 'notes' ]->offsetExists( $_segment_metadata[ 'internal_id' ] ) ) {

            foreach ( $projectStructure[ 'notes' ][ $_segment_metadata[ 'internal_id' ] ][ 'entries' ] as $k => $entry ) {

                if ( strpos( $entry, 'phrase_key|¶|' ) !== false ) {
                    $_segment_metadata[ 'additional_params' ][ 'spice' ] = md5( str_replace( 'phrase_key|¶|', '', $entry ) . $_segment_metadata[ 'segment' ] );
                } elseif ( strpos( $entry, 'translation_context|¶|' ) !== false ) {
                    $_segment_metadata[ 'additional_params' ][ 'spice' ] = md5( str_replace( 'translation_context|¶|', '', $entry ) . $_segment_metadata[ 'segment' ] );
                }

            }

        }

        return $_segment_metadata;

    }

    /**
     * @param $parameters
     *
     * @param $original_config
     *
     * @return mixed
     * @see Airbnb::appendFieldToAnalysisObject()
     *
     * @see \Engines_MyMemory::get()
     */
    public function filterMyMemoryGetParameters( $parameters, $original_config ) {

        /*
         * From analysis we will have additional params and spice field
         */
        if ( isset( $original_config[ 'additional_params' ][ 'spice' ] ) ) {
            $parameters[ 'context_before' ] = $original_config[ 'additional_params' ][ 'spice' ];
            $parameters[ 'context_after' ]  = null;
        }

        $parameters[ 'cid' ] = Airbnb::FEATURE_CODE;

        return $parameters;

    }

    public function filterRevisionChangeNotificationList( $emails ) {
        // TODO: add custom email recipients here
        $config = self::getConfig();

        if ( isset( $config[ 'revision_change_notification_recipients' ] ) ) {
            foreach ( $config[ 'revision_change_notification_recipients' ] as $recipient ) {
                list( $firstName, $lastName, $email ) = explode( ',', $recipient );
                $emails[] = [
                        'recipient'              => new Users_UserStruct( [
                                'email'      => $email,
                                'first_name' => $firstName,
                                'last_name'  => $lastName
                        ] ),
                        'isPreviousChangeAuthor' => false
                ];

            }
        }

        return $emails;
    }

    /**
     * @param $segmentsList Segments_SegmentStruct[]
     * @param $postInput
     *
     * @see \getContributionController::doAction()
     * @see \setTranslationController
     *
     */
    public function rewriteContributionContexts( $segmentsList, $postInput ) {

        if ( !is_object( $segmentsList->id_before ) ) {
            $segmentsList->id_before = new Segments_SegmentStruct();
        }

        if ( strpos( $postInput[ 'context_before' ], 'phrase_key|¶|' ) !== false ) {
            // old school ( backward compatibility )
            $segmentsList->id_before->segment = md5( str_replace( 'phrase_key|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        } else {
            $segmentsList->id_before->segment = md5( str_replace( 'translation_context|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        }

        $segmentsList->id_after = null;
    }

    /**
     * @param ProjectUrls $formatted
     *
     * @return \API\V2\JSON\ProjectUrls
     */
    public static function projectUrls( ProjectUrls $formatted ) {
        return $formatted;
    }

    /**
     * Entry point for project data validation for this feature.
     *
     * @param $projectStructure
     *
     * @throws ConnectionException
     * @throws ReflectionException
     */
    public function validateProjectCreation( $projectStructure ) {
        //override Revise Improved qa Model
        $qa_mode_file = realpath( self::getPluginBasePath() . "/../qa_model.json" );
        ReviewExtended::loadAndValidateModelFromJsonFile( $projectStructure, $qa_mode_file );
    }

    public function fromLayer0ToLayer1( Pipeline $channel ) {
        $channel->addAfter( new HtmlToPh(), new Variables() );
        $channel->addAfter( new Variables(), new SmartCounts() );

        return $channel;
    }

    public function fromRawXliffToLayer0( Pipeline $channel ) {
        $channel->addAfter( new PlaceHoldXliffTags(), new LtGtDoubleDecode() ); // Fix source &amp;lt;&gt; // Hope and Pray

        return $channel;
    }

    /**
     * Check tag positions
     * -------------------------------------------------
     * NOTE 28/02/2023
     * -------------------------------------------------
     * This function returns a boolean to be used in the main QA class,
     * indicating that _checkTagPositions() function should continue or not
     *
     * @param $errorType
     * @param QA $QA
     *
     * @return bool
     */
    public function checkTagPositions($errorType, QA $QA)
    {
        $sourceSplittedByPipeSep = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getSourceSeg() );
        $sourceSplittedByPipeSepCount = count($sourceSplittedByPipeSep);

        // No smart count pipes, continue with _checkTagPositions()
        if($sourceSplittedByPipeSepCount === 1){
            return false;
        }

        // Smart count check tag position
        $targetSplittedByPipeSep = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getTargetSeg() );
        $targetSplittedByPipeSepCount = count($targetSplittedByPipeSep);
        $targetPluralFormsCount = Pluralization::getCountFromLang( $QA->getTargetSegLang() );

        // if $targetSplittedByPipeSepCount !== $targetPluralFormsCount an error will be thrown
        // by the checkTagMismatch() function, so we don't care about it
        if($targetSplittedByPipeSepCount === $targetPluralFormsCount){

            // perform strict checks only with language with 2 plural forms
            $performIdCheck = $targetPluralFormsCount === 2;
            $performTagPositionsCheck = $targetPluralFormsCount === 2;

            $QA->performTagPositionCheck($sourceSplittedByPipeSep[0], $targetSplittedByPipeSep[0], $performIdCheck, $performTagPositionsCheck);

            unset($targetSplittedByPipeSep[0]);

            foreach ($targetSplittedByPipeSep as $targetSplitted){
                $QA->performTagPositionCheck($sourceSplittedByPipeSep[1], $targetSplitted, $performIdCheck, $performTagPositionsCheck);
            }
        }

        return true;
    }

    /**
     * Check tag mismatch / smartcount consistency
     * -------------------------------------------------
     * NOTE 09/01/2020
     * -------------------------------------------------
     * This function does the following:
     *
     * - obtain the count of |||| separators in target raw text, and then sum 1 to calculate the expected tag count;
     * - obtain the number of possible plural forms of the target language (ex: "ar-SA --> 6");
     * - check for the equivalence of these two numbers, and in case return an error code 2000.
     *
     * Example:
     *
     * [source:en-US] - House |||| Houses
     * [target:ar-SA] - XXXX |||| XXXX |||| XXXX |||| XXXX |||| XXXX |||| XXXX
     *
     * $separatorCount = 5
     * $expectedTagCount = 6
     * $pluralizationCountForTargetLang = Pluralization::getCountFromLang('ar-SA') = 6
     *
     * No error will be produced.
     *
     * @param     $errorType
     * @param QA  $QA
     *
     * @return int
     */
    public function checkTagMismatch( $errorType, QA $QA ) {

        //check for smart count separator |||| in source segment ( base64 encoded "||||" === "fHx8fA==" )
        if ( strpos( $QA->getSourceSeg(), "equiv-text=\"base64:fHx8fA==\"" ) !== false ) {

            //
            // ----------------------------------------------------------------
            // 1. check for |||| count correspondence
            // ----------------------------------------------------------------
            //
            $targetSeparatorCount   = substr_count( $QA->getTargetSeg(), "equiv-text=\"base64:fHx8fA==\"" );
            $targetPluralFormsCount = Pluralization::getCountFromLang( $QA->getTargetSegLang() );

            if ( ( 1 + $targetSeparatorCount ) !== $targetPluralFormsCount ) {
                $QA->addCustomError( [
                        'code'  => QA::SMART_COUNT_PLURAL_MISMATCH,
                        'debug' => 'Smart Count rules not compliant with target language',
                        'tip'   => 'Check your language specific configuration.'
                ] );

                return QA::SMART_COUNT_PLURAL_MISMATCH;
            }

            //
            // ----------------------------------------------------------------
            // 2. Check the count of smart_count tags in the target
            // ----------------------------------------------------------------
            //
            // Example:
            //
            // $source = "<ph id="mtc_1" equiv-text="base64:JXtmaXJzdF9uYW1lfQ=="/> has 1 hour left to respond<ph id="mtc_2" equiv-text="base64:fHx8fA=="/><ph id="mtc_3"
            // equiv-text="base64:JXtmaXJzdF9uYW1lfQ=="/> has <ph id="mtc_4" equiv-text="base64:JXtzbWFydF9jb3VudH0="/> hours left to respond";
            //
            // From this, $expectedTargetTagMap is obtained by analysing the splitted source segment taking into account the number of plural forms of target.
            //
            // 1 Plural form:
            //
            // $expectedTargetTagMap =
            // array (
            //  0 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //  ),
            // )
            //
            // 2 Plural forms:
            //
            // $expectedTargetTagMap =
            // array (
            //  0 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //  ),
            //  1 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //    1 => 'equiv-text="base64:JXtzbWFydF9jb3VudH0=',
            //  ),
            // )
            //
            // 3 Plural forms:
            //
            // $expectedTargetTagMap =
            // array (
            //  0 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //  ),
            //  1 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //    1 => 'equiv-text="base64:JXtzbWFydF9jb3VudH0=',
            //  ),
            //  2 =>
            //  array (
            //    0 => 'equiv-text="base64:JXtmaXJzdF9uYW1lfQ==',
            //    1 => 'equiv-text="base64:JXtzbWFydF9jb3VudH0=',
            //  ),
            // )
            //
            // Finally, the target tag map is compared to $expectedTargetTagMap
            //
            $sourceTagMap            = [];
            $sourceSplittedByPipeSep = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getSourceSeg() );

            foreach ( $sourceSplittedByPipeSep as $item ) {
                preg_match_all( '/equiv-text="base64:[a-zA-Z0-9=]{1,}/', $item, $itemSegMatch );
                //preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $item, $itemSegMatch );
                $sourceTagMap[] = $itemSegMatch[ 0 ];
            }

            $expectedTargetTagMap[] = $sourceTagMap[ 0 ];

            for ( $i = 1; $i < $targetPluralFormsCount; $i++ ) {
                $expectedTargetTagMap[] = $sourceTagMap[ 1 ];
            }

            $targetTagMap            = [];
            $targetSplittedByPipeSep = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getTargetSeg() );

            foreach ( $targetSplittedByPipeSep as $item ) {
                //preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $item, $itemSegMatch );
                preg_match_all( '/equiv-text="base64:[a-zA-Z0-9=]{1,}/', $item, $itemSegMatch );
                $targetTagMap[] = $itemSegMatch[ 0 ];
            }

            sort( $expectedTargetTagMap[ 0 ] );
            sort( $expectedTargetTagMap[ 1 ] );
            sort( $targetTagMap[ 0 ] );
            sort( $targetTagMap[ 1 ] );

            foreach ($expectedTargetTagMap as $index => $expectedTargetTags){

                $currentTargetTagMap = $targetTagMap[$index];

                $check = array_diff($currentTargetTagMap, $expectedTargetTags);
                $check2 = array_diff($expectedTargetTags, $currentTargetTagMap);

                if(!empty($check) or !empty($check2)){
                    $QA->addCustomError( [
                        'code'  => QA::SMART_COUNT_MISMATCH,
                        'debug' => '%{smart_count} tag count mismatch',
                        'tip'   => 'Check the count of %{smart_count} tags in the source.'
                    ] );

                    return QA::SMART_COUNT_MISMATCH;
                }

                if($currentTargetTagMap !== $expectedTargetTags){
                    $QA->addError( QA::ERR_TAG_ORDER );

                    return QA::ERR_TAG_ORDER;
                }
            }

            $QA->addCustomError( [
                    'code' => 0,
            ] );

            return 0;
        }

        return $errorType;
    }

    public static function analysisBeforeMTGetContribution( $config, Engines_AbstractEngine $engine, QueueElement $queueElement ) {
        if ( $engine instanceof Engines_MMT ) {
            //tell to the MMT that this is the analysis phase ( override default configuration )
            $engine->setAnalysis( false );
        }

        return $config;
    }

    /**
     * Count CJK and emoji as 1 character, so mb_strlen is enough. ( baseLength )
     *
     * @param $string
     * @return array
     */
    public function characterLengthCount( $string ) {

        return [
            "baseLength"   => mb_strlen( $string ),
            "cjkMatches"   => 0,
            "emojiMatches" => 0,
        ];

    }

    /**
     * @param $payableRates
     * @param $SourceLang
     * @param $TargetLang
     *
     * @return array
     */
    public function filterPayableRates( $payableRates, $SourceLang, $TargetLang ) {
        return CustomPayableRates::getPayableRates( $SourceLang, $TargetLang );
    }
}
