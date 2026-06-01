<?php
/**
 * Created by PhpStorm.
 * User: vincenzoruffa
 * Date: 09/04/2018
 * Time: 14:57
 */

namespace Features;

use Exception;
use Features\Airbnb\Utils\SmartCount\Pluralization;
use Klein\Klein;
use Matecat\SubFiltering\Events\FromLayer0ToLayer1Event;
use Matecat\SubFiltering\Filters\RubyOnRailsI18n;
use Matecat\SubFiltering\Filters\SmartCounts;
use Model\FeaturesBase\FeatureCodes;
use Model\FeaturesBase\Hook\Event\Filter\AnalysisBeforeMTGetContributionEvent;
use Model\FeaturesBase\Hook\Event\Filter\AppendFieldToAnalysisObjectEvent;
use Model\FeaturesBase\Hook\Event\Filter\CharacterLengthCountEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagMismatchEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagPositionsEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnMTSetEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterMyMemoryGetParametersEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterRevisionChangeNotificationListEvent;
use Model\FeaturesBase\Hook\Event\Filter\ProjectUrlsEvent;
use Model\FeaturesBase\Hook\Event\Filter\RewriteContributionContextsEvent;
use Model\Jobs\JobStruct;
use Model\Segments\SegmentStruct;
use Model\Users\UserStruct;
use Plugins\Features\BaseFeature;
use TypeError;
use Utils\Contribution\SetContributionRequest;
use Utils\Engines\MMT;
use Utils\LQA\QA;


class Airbnb extends BaseFeature
{

    const string FEATURE_CODE = "airbnb";
    const string DELIVERY_COOKIE_PREFIX = 'airbnb_session_';

    /**
     * @var array<int, string>
     */
    public static array $dependencies = [
        FeatureCodes::TRANSLATION_VERSIONS,
        FeatureCodes::REVIEW_EXTENDED
    ];

    public static function loadRoutes(Klein $klein): void
    {
    }

    public function appendFieldToAnalysisObject(AppendFieldToAnalysisObjectEvent $event): void
    {
        $_segment_metadata = $event->getMetadata();
        $projectStructure = $event->getProjectStructure();

        if (isset($projectStructure->notes[$_segment_metadata['internal_id']])) {
            foreach ($projectStructure->notes[$_segment_metadata['internal_id']]['entries'] as $entry) {
                if (str_contains($entry, 'phrase_key|¶|')) {
                    $_segment_metadata['additional_params']['spice'] = md5(str_replace('phrase_key|¶|', '', $entry) . $_segment_metadata['segment']);
                } elseif (str_contains($entry, 'translation_context|¶|')) {
                    $_segment_metadata['additional_params']['spice'] = md5(str_replace('translation_context|¶|', '', $entry) . $_segment_metadata['segment']);
                }
            }
        }

        $event->setMetadata($_segment_metadata);
    }

    public function filterMyMemoryGetParameters(FilterMyMemoryGetParametersEvent $event): void
    {
        $parameters = $event->getParameters();
        $original_config = $event->getConfig();

        /*
         * From analysis we will have additional params and spice field
         */
        if (isset($original_config['additional_params']['spice'])) {
            $parameters['context_before'] = $original_config['additional_params']['spice'];
            $parameters['context_after'] = null;
        }

        $parameters['cid'] = Airbnb::FEATURE_CODE;

        $event->setParameters($parameters);
    }

    /**
     * @throws Exception
     */
    public function filterRevisionChangeNotificationList(FilterRevisionChangeNotificationListEvent $event): void
    {
        $emails = $event->getEmails();

        // TODO: add custom email recipients here
        $config = self::getConfig();

        if (isset($config['revision_change_notification_recipients'])) {
            foreach ($config['revision_change_notification_recipients'] as $recipient) {
                [$firstName, $lastName, $email] = explode(',', $recipient);
                $emails[] = [
                    'recipient' => new UserStruct([
                        'email' => $email,
                        'first_name' => $firstName,
                        'last_name' => $lastName
                    ]),
                    'isPreviousChangeAuthor' => false
                ];
            }
        }

        $event->setEmails($emails);
    }

    public function rewriteContributionContexts(RewriteContributionContextsEvent $event): void
    {
        $segmentsList = $event->getSegmentsList();
        $postInput = $event->getRequestData();

        if (!$segmentsList->id_before instanceof SegmentStruct) {
            $segmentsList->id_before = new SegmentStruct();
        }

        $idSegment = $segmentsList->id_segment;
        $sourceSegment = $idSegment instanceof SegmentStruct ? $idSegment->segment : '';

        if (str_contains($postInput['context_before'], 'phrase_key|¶|')) {
            // old school ( backward compatibility )
            $segmentsList->id_before->segment = md5(str_replace('phrase_key|¶|', '', $postInput['context_before']) . $sourceSegment);
        } else {
            $segmentsList->id_before->segment = md5(str_replace('translation_context|¶|', '', $postInput['context_before']) . $sourceSegment);
        }

        $segmentsList->id_after = null;

        $segmentsList->isSpice = true;

        $event->setSegmentsList($segmentsList);
    }

    public function projectUrls(ProjectUrlsEvent $event): void
    {
        $event->setFormatted($event->getFormatted());
    }

    public function fromLayer0ToLayer1(FromLayer0ToLayer1Event $event): void
    {
        $event->getPipeline()->addAfter(RubyOnRailsI18n::class, SmartCounts::class);
    }

    /**
     * Check tag positions
     * -------------------------------------------------
     * This function stops the _checkTagPositions() and let our code to check positions
     * -------------------------------------------------
     * returning true  or false means that _checkTagPositions() function should NOT check for tags inside || or || separator
     * returning null means that _checkTagPositions() function should continue or not
     *
     */
    public function checkTagPositions(CheckTagPositionsEvent $event): void
    {
        $QA = $event->getQaInstance();
        if (!$QA instanceof QA) {
            return;
        }

        $sourceSplittedByPipeSep = preg_split('/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getSourceSeg());
        if ($sourceSplittedByPipeSep === false) {
            return;
        }
        $sourceSplittedByPipeSepCount = count($sourceSplittedByPipeSep);

        // No smart count pipes, continue with _checkTagPositions()
        if ($sourceSplittedByPipeSepCount === 1) {
            return;
        }

        // Smart count check tag position
        $targetSplittedByPipeSep = preg_split('/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getTargetSeg());
        if ($targetSplittedByPipeSep === false) {
            return;
        }
        $targetSplittedByPipeSepCount = count($targetSplittedByPipeSep);
        $targetPluralFormsCount = Pluralization::getCountFromLang($QA->getTargetSegLang() ?? '');

        // if $targetSplittedByPipeSepCount !== $targetPluralFormsCount an error will be thrown
        // by the checkTagMismatch() function, so we don't care about it
        if ($targetSplittedByPipeSepCount === $targetPluralFormsCount) {
            // perform strict checks only with language with 2 plural forms
            $performIdCheck = $targetPluralFormsCount === 2;
            $performTagPositionsCheck = $targetPluralFormsCount === 2;

            $QA->performTagPositionCheck($sourceSplittedByPipeSep[0], $targetSplittedByPipeSep[0], $performIdCheck, $performTagPositionsCheck);

            unset($targetSplittedByPipeSep[0]);

            foreach ($targetSplittedByPipeSep as $targetSplitted) {
                $QA->performTagPositionCheck($sourceSplittedByPipeSep[1], $targetSplitted, $performIdCheck, $performTagPositionsCheck);
            }
        }

        $event->setErrorCode(true);
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
     */
    public function checkTagMismatch(CheckTagMismatchEvent $event): void
    {
        $errorCode = $event->getErrorCode();
        $QA = $event->getQaInstance();
        if (!$QA instanceof QA) {
            return;
        }

        //check for smart count separator |||| in source segment ( base64 encoded "||||" === "fHx8fA==" )
        if (str_contains($QA->getSourceSeg(), "equiv-text=\"base64:fHx8fA==\"")) {
            //
            // ----------------------------------------------------------------
            // 1. check for |||| count correspondence
            // ----------------------------------------------------------------
            //
            $targetSeparatorCount = substr_count($QA->getTargetSeg(), "equiv-text=\"base64:fHx8fA==\"");
            $targetPluralFormsCount = Pluralization::getCountFromLang($QA->getTargetSegLang() ?? '');

            if ((1 + $targetSeparatorCount) !== $targetPluralFormsCount) {
                $QA->addCustomError([
                    'code' => QA::SMART_COUNT_PLURAL_MISMATCH,
                    'debug' => 'Smart Count rules not compliant with target language',
                    'tip' => 'Check your language specific configuration.'
                ]);

                $event->setErrorCode(QA::SMART_COUNT_PLURAL_MISMATCH);
                return;
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
            $sourceTagMap = [];
            $sourceSplittedByPipeSep = preg_split('/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getSourceSeg());
            if ($sourceSplittedByPipeSep === false) {
                return;
            }

            foreach ($sourceSplittedByPipeSep as $item) {
                preg_match_all('/equiv-text="base64:[a-zA-Z0-9=]{1,}/', $item, $itemSegMatch);
                //preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $item, $itemSegMatch );
                $sourceTagMap[] = $itemSegMatch[0];
            }

            $expectedTargetTagMap[] = $sourceTagMap[0];

            for ($i = 1; $i < $targetPluralFormsCount; $i++) {
                $expectedTargetTagMap[] = $sourceTagMap[1];
            }

            $targetTagMap = [];
            $targetSplittedByPipeSep = preg_split('/<ph id="mtc_[0-9]{0,10}" ctype="x-smart-count" equiv-text="base64:fHx8fA=="\/>/', $QA->getTargetSeg());
            if ($targetSplittedByPipeSep === false) {
                return;
            }

            foreach ($targetSplittedByPipeSep as $item) {
                //preg_match_all( '/<ph id ?= ?[\'"]mtc_[0-9]{1,9}?[\'"] equiv-text="base64:[a-zA-Z0-9=]{1,}"\/>/', $item, $itemSegMatch );
                preg_match_all('/equiv-text="base64:[a-zA-Z0-9=]{1,}/', $item, $itemSegMatch);
                $targetTagMap[] = $itemSegMatch[0];
            }

            sort($expectedTargetTagMap[0]);
            sort($expectedTargetTagMap[1]);
            sort($targetTagMap[0]);
            sort($targetTagMap[1]);

            $smartCountErrors = 0;
            $tagOrderErrors = 0;

            foreach ($expectedTargetTagMap as $index => $expectedTargetTags) {
                $currentTargetTagMap = $targetTagMap[$index];

                $check = array_diff($currentTargetTagMap, $expectedTargetTags);
                $check2 = array_diff($expectedTargetTags, $currentTargetTagMap);

                if (!empty($check) or !empty($check2)) {
                    $smartCountErrors++;
                }

                if ($currentTargetTagMap !== $expectedTargetTags) {
                    $tagOrderErrors++;
                }
            }

            // If there is at least ONE smart count mismatch, return an error
            if ($smartCountErrors > 0) {
                $QA->addCustomError([
                    'code' => QA::SMART_COUNT_MISMATCH,
                    'debug' => '%{smart_count} tag count mismatch',
                    'tip' => 'Check the count of %{smart_count} tags in the source.'
                ]);

                $event->setErrorCode(QA::SMART_COUNT_MISMATCH);
                return;
            }

            // Otherwise, consider if there is at least tag order mismatch, return a warning
            if ($tagOrderErrors > 0) {
                $QA->addError(QA::ERR_TAG_ORDER);

                $event->setErrorCode(QA::ERR_TAG_ORDER);
                return;
            }

            $QA->addCustomError([
                'code' => 0,
            ]);

            $event->setErrorCode(0);
            return;
        }

        $event->setErrorCode($errorCode);
    }

    public function analysisBeforeMTGetContribution(AnalysisBeforeMTGetContributionEvent $event): void
    {
        $engine = $event->getMtEngine();
        $config = $event->getConfig();

        if ($engine instanceof MMT) {
            //tell to the MMT that this is the analysis phase ( override default configuration )
            $engine->setAnalysis(false);
        }

        $event->setConfig($config);
    }

    /**
     * Count CJK and emoji as 1 character, so mb_strlen is enough. ( baseLength )
     *
     */
    public function characterLengthCount(CharacterLengthCountEvent $event): void
    {
        $string = $event->getFilterable();
        if (!is_string($string)) {
            return;
        }

        $event->setFilterable([
            "baseLength" => mb_strlen($string),
            "cjkMatches" => 0,
            "emojiMatches" => 0,
        ]);
    }

    /**
     * @throws Exception
     * @throws TypeError
     */
    public function filterContributionStructOnMTSet(FilterContributionStructOnMTSetEvent $event): void
    {
        $contributionRequest = $event->getContributionStruct();
        $segmentTranslationStruct = $event->getTranslation();
        $originalSourceSegment = $event->getSegment();
        $filter = $event->getFilter();

        $array = $contributionRequest->toArray();
        $array['jobStruct'] = new JobStruct($array['jobStruct']);
        $newContribution = new SetContributionRequest($array);
        $newContribution->segment = $originalSourceSegment->segment;
        $newContribution->translation = $segmentTranslationStruct->translation;
        $newContribution->context_after = $filter->fromLayer1ToLayer0($contributionRequest->context_after);
        $newContribution->context_before = $filter->fromLayer1ToLayer0($contributionRequest->context_before);

        $event->setContributionStruct($newContribution);
    }

}
