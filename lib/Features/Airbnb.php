<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use ArrayObject;
use Exception;
use Features\Airbnb\Utils\SmartCount\Pluralization;
use Klein\Klein;
use Matecat\SubFiltering\Commons\Pipeline;
use Matecat\SubFiltering\Filters\PercentDoubleCurlyBrackets;
use Matecat\SubFiltering\Filters\SmartCounts;
use Model\FeaturesBase\FeatureCodes;
use Model\Segments\SegmentStruct;
use Model\Users\UserStruct;
use Plugins\Features\BaseFeature;
use Utils\Engines\AbstractEngine;
use Utils\Engines\MMT;
use Utils\LQA\QA;
use Utils\TaskRunner\Commons\QueueElement;
use View\API\V2\Json\ProjectUrls;


class Airbnb extends BaseFeature {

    const FEATURE_CODE = "airbnb";

    protected static $service_types = [ 'standard', 'priority' ];

    const DELIVERY_COOKIE_PREFIX = 'airbnb_session_';

    public static array $dependencies = [
            FeatureCodes::TRANSLATION_VERSIONS,
            FeatureCodes::REVIEW_EXTENDED
    ];

    public static function loadRoutes( Klein $klein ) {
    }

    /**
     * @param             $_segment_metadata array
     * @param ArrayObject $projectStructure
     *
     * @return array
     * @see \Model\ProjectManager\ProjectManager::_storeSegments()
     */
    public function appendFieldToAnalysisObject( $_segment_metadata, ArrayObject $projectStructure ) {

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
     * @see \Utils\Engines\MyMemory::get()
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

    /**
     * @throws Exception
     */
    public function filterRevisionChangeNotificationList( $emails ) {
        // TODO: add custom email recipients here
        $config = self::getConfig();

        if ( isset( $config[ 'revision_change_notification_recipients' ] ) ) {
            foreach ( $config[ 'revision_change_notification_recipients' ] as $recipient ) {
                [ $firstName, $lastName, $email ] = explode( ',', $recipient );
                $emails[] = [
                        'recipient'              => new UserStruct( [
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
     * @param $segmentsList SegmentStruct[]
     * @param $postInput
     *
     * @see \getContributionController::doAction()
     * @see \setTranslationController
     *
     */
    public function rewriteContributionContexts( $segmentsList, $postInput ) {

        if ( !is_object( $segmentsList->id_before ) ) {
            $segmentsList->id_before = new SegmentStruct();
        }

        if ( strpos( $postInput[ 'context_before' ], 'phrase_key|¶|' ) !== false ) {
            // old school ( backward compatibility )
            $segmentsList->id_before->segment = md5( str_replace( 'phrase_key|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        } else {
            $segmentsList->id_before->segment = md5( str_replace( 'translation_context|¶|', '', $postInput[ 'context_before' ] ) . $segmentsList->id_segment->segment );
        }

        $segmentsList->id_after = null;

        $segmentsList->isSpice = true;

    }

    /**
     * @param ProjectUrls $formatted
     *
     * @return ProjectUrls
     */
    public static function projectUrls( ProjectUrls $formatted ) {
        return $formatted;
    }

    public function fromLayer0ToLayer1( Pipeline $channel ) {
        $channel->addAfter( PercentDoubleCurlyBrackets::class, SmartCounts::class );

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
     * @param               $errorType
     * @param QA            $QA
     *
     * @return bool
     */
    public function checkTagPositions( $errorType, QA $QA ) {
        $sourceSplittedByPipeSep      = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getSourceSeg() );
        $sourceSplittedByPipeSepCount = count( $sourceSplittedByPipeSep );

        // No smart count pipes, continue with _checkTagPositions()
        if ( $sourceSplittedByPipeSepCount === 1 ) {
            return false;
        }

        // Smart count check tag position
        $targetSplittedByPipeSep      = preg_split( '/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getTargetSeg() );
        $targetSplittedByPipeSepCount = count( $targetSplittedByPipeSep );
        $targetPluralFormsCount       = Pluralization::getCountFromLang( $QA->getTargetSegLang() );

        // if $targetSplittedByPipeSepCount !== $targetPluralFormsCount an error will be thrown
        // by the checkTagMismatch() function, so we don't care about it
        if ( $targetSplittedByPipeSepCount === $targetPluralFormsCount ) {

            // perform strict checks only with language with 2 plural forms
            $performIdCheck           = $targetPluralFormsCount === 2;
            $performTagPositionsCheck = $targetPluralFormsCount === 2;

            $QA->performTagPositionCheck( $sourceSplittedByPipeSep[ 0 ], $targetSplittedByPipeSep[ 0 ], $performIdCheck, $performTagPositionsCheck );

            unset( $targetSplittedByPipeSep[ 0 ] );

            foreach ( $targetSplittedByPipeSep as $targetSplitted ) {
                $QA->performTagPositionCheck( $sourceSplittedByPipeSep[ 1 ], $targetSplitted, $performIdCheck, $performTagPositionsCheck );
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
     * @param               $errorType
     * @param QA            $QA
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
            // From this, $expectedTargetTagMap is obtained by analysing the split source segment taking into account the number of plural forms of target.
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

            $smartCountErrors = 0;
            $tagOrderErrors   = 0;

            foreach ( $expectedTargetTagMap as $index => $expectedTargetTags ) {

                $currentTargetTagMap = $targetTagMap[ $index ];

                $check  = array_diff( $currentTargetTagMap, $expectedTargetTags );
                $check2 = array_diff( $expectedTargetTags, $currentTargetTagMap );

                if ( !empty( $check ) or !empty( $check2 ) ) {
                    $smartCountErrors++;

                }

                if ( $currentTargetTagMap !== $expectedTargetTags ) {
                    $tagOrderErrors++;
                }
            }

            // If there is at least ONE smart count mismatch, return an error
            if ( $smartCountErrors > 0 ) {
                $QA->addCustomError( [
                        'code'  => QA::SMART_COUNT_MISMATCH,
                        'debug' => '%{smart_count} tag count mismatch',
                        'tip'   => 'Check the count of %{smart_count} tags in the source.'
                ] );

                return QA::SMART_COUNT_MISMATCH;
            }

            // Otherwise, consider if there is at least tag order mismatch, return a warning
            if ( $tagOrderErrors > 0 ) {
                $QA->addError( QA::ERR_TAG_ORDER );

                return QA::ERR_TAG_ORDER;
            }

            $QA->addCustomError( [
                    'code' => 0,
            ] );

            return 0;
        }

        return $errorType;
    }

    public static function analysisBeforeMTGetContribution( $config, AbstractEngine $engine, QueueElement $queueElement ) {
        if ( $engine instanceof MMT ) {
            //tell to the MMT that this is the analysis phase ( override default configuration )
            $engine->setAnalysis( false );
        }

        return $config;
    }

    /**
     * Count CJK and emoji as 1 character, so mb_strlen is enough. ( baseLength )
     *
     * @param $string
     *
     * @return array
     */
    public function characterLengthCount( $string ) {

        return [
                "baseLength"   => mb_strlen( $string ),
                "cjkMatches"   => 0,
                "emojiMatches" => 0,
        ];

    }

}
