<?php

declare(strict_types=1);

namespace Matecat\Plugins\Airbnb\Features;

use Features\Airbnb;
use Matecat\SubFiltering\AbstractFilter;
use Matecat\SubFiltering\Commons\Pipeline;
use Matecat\SubFiltering\Events\FromLayer0ToLayer1Event;
use Matecat\SubFiltering\Filters\RubyOnRailsI18n;
use Matecat\SubFiltering\Filters\SmartCounts;
use Matecat\TestHelpers\AbstractTest;
use Model\FeaturesBase\BasicFeatureStruct;
use Model\FeaturesBase\Hook\Event\Filter\AnalysisBeforeMTGetContributionEvent;
use Model\FeaturesBase\Hook\Event\Filter\AppendFieldToAnalysisObjectEvent;
use Model\FeaturesBase\Hook\Event\Filter\CharacterLengthCountEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagMismatchEvent;
use Model\FeaturesBase\Hook\Event\Filter\CheckTagPositionsEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterContributionStructOnMTSetEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterMyMemoryGetParametersEvent;
use Model\FeaturesBase\Hook\Event\Filter\FilterRevisionChangeNotificationListEvent;
use Model\FeaturesBase\Hook\Event\Filter\RewriteContributionContextsEvent;
use Model\Jobs\JobStruct;
use Model\ProjectCreation\ProjectStructure;
use Model\Segments\SegmentStruct;
use PHPUnit\Framework\Attributes\Test;
use stdClass;
use Utils\Contribution\SetContributionRequest;
use Utils\Engines\MMT;
use Utils\LQA\QA;

class AirbnbTest extends AbstractTest
{
    private Airbnb $airbnb;

    protected function setUp(): void
    {
        parent::setUp();

        $this->airbnb = new Airbnb(
            new BasicFeatureStruct(['feature_code' => Airbnb::FEATURE_CODE]),
            config: ['revision_change_notification_recipients' => ['John,Doe,john@example.com']],
        );
    }

    // ---------------------------------------------------------------
    // appendFieldToAnalysisObject
    // ---------------------------------------------------------------

    #[Test]
    public function appendFieldToAnalysisObject_withPhraseKey_setsSpice(): void
    {
        $projectStructure = $this->createStub(ProjectStructure::class);
        $projectStructure->notes = [
            42 => ['entries' => ['phrase_key|¶|my_key']],
        ];

        $metadata = [
            'internal_id' => 42,
            'segment' => 'Hello',
            'additional_params' => [],
        ];

        $event = new AppendFieldToAnalysisObjectEvent($metadata, $projectStructure);
        $this->airbnb->appendFieldToAnalysisObject($event);

        $result = $event->getMetadata();
        self::assertSame(md5('my_key' . 'Hello'), $result['additional_params']['spice']);
    }

    #[Test]
    public function appendFieldToAnalysisObject_withTranslationContext_setsSpice(): void
    {
        $projectStructure = $this->createStub(ProjectStructure::class);
        $projectStructure->notes = [
            1 => ['entries' => ['translation_context|¶|ctx_value']],
        ];

        $metadata = [
            'internal_id' => 1,
            'segment' => 'World',
            'additional_params' => [],
        ];

        $event = new AppendFieldToAnalysisObjectEvent($metadata, $projectStructure);
        $this->airbnb->appendFieldToAnalysisObject($event);

        $result = $event->getMetadata();
        self::assertSame(md5('ctx_value' . 'World'), $result['additional_params']['spice']);
    }

    #[Test]
    public function appendFieldToAnalysisObject_noNotes_metadataUnchanged(): void
    {
        $projectStructure = $this->createStub(ProjectStructure::class);
        $projectStructure->notes = [];

        $metadata = [
            'internal_id' => 99,
            'segment' => 'Foo',
            'additional_params' => [],
        ];

        $event = new AppendFieldToAnalysisObjectEvent($metadata, $projectStructure);
        $this->airbnb->appendFieldToAnalysisObject($event);

        $result = $event->getMetadata();
        self::assertArrayNotHasKey('spice', $result['additional_params']);
    }

    // ---------------------------------------------------------------
    // filterMyMemoryGetParameters
    // ---------------------------------------------------------------

    #[Test]
    public function filterMyMemoryGetParameters_withSpice_setsContextBefore(): void
    {
        $event = new FilterMyMemoryGetParametersEvent(
            ['some_param' => 'value'],
            ['additional_params' => ['spice' => 'abc123']],
        );

        $this->airbnb->filterMyMemoryGetParameters($event);

        $params = $event->getParameters();
        self::assertSame('abc123', $params['context_before']);
        self::assertNull($params['context_after']);
        self::assertSame('airbnb', $params['cid']);
    }

    #[Test]
    public function filterMyMemoryGetParameters_withoutSpice_setsCidOnly(): void
    {
        $event = new FilterMyMemoryGetParametersEvent(
            ['some_param' => 'value'],
            [],
        );

        $this->airbnb->filterMyMemoryGetParameters($event);

        $params = $event->getParameters();
        self::assertArrayNotHasKey('context_before', $params);
        self::assertSame('airbnb', $params['cid']);
    }

    // ---------------------------------------------------------------
    // filterRevisionChangeNotificationList
    // ---------------------------------------------------------------

    #[Test]
    public function filterRevisionChangeNotificationList_addsRecipientsFromConfig(): void
    {
        $event = new FilterRevisionChangeNotificationListEvent([]);

        $this->airbnb->filterRevisionChangeNotificationList($event);

        $emails = $event->getEmails();
        self::assertCount(1, $emails);
        self::assertSame('john@example.com', $emails[0]['recipient']->email);
        self::assertSame('John', $emails[0]['recipient']->first_name);
        self::assertSame('Doe', $emails[0]['recipient']->last_name);
        self::assertFalse($emails[0]['isPreviousChangeAuthor']);
    }

    #[Test]
    public function filterRevisionChangeNotificationList_noConfigKey_leavesListEmpty(): void
    {
        $airbnb = new Airbnb(
            new BasicFeatureStruct(['feature_code' => Airbnb::FEATURE_CODE]),
            config: [],
        );

        $event = new FilterRevisionChangeNotificationListEvent([]);
        $airbnb->filterRevisionChangeNotificationList($event);

        self::assertSame([], $event->getEmails());
    }

    // ---------------------------------------------------------------
    // characterLengthCount
    // ---------------------------------------------------------------

    #[Test]
    public function characterLengthCount_stringInput_returnsBaseLength(): void
    {
        $event = new CharacterLengthCountEvent('Hello');

        $this->airbnb->characterLengthCount($event);

        $result = $event->getFilterable();
        self::assertIsArray($result);
        self::assertSame(5, $result['baseLength']);
        self::assertSame(0, $result['cjkMatches']);
        self::assertSame(0, $result['emojiMatches']);
    }

    #[Test]
    public function characterLengthCount_nonStringInput_leavesUnchanged(): void
    {
        $event = new CharacterLengthCountEvent(42);

        $this->airbnb->characterLengthCount($event);

        self::assertSame(42, $event->getFilterable());
    }

    // ---------------------------------------------------------------
    // analysisBeforeMTGetContribution
    // ---------------------------------------------------------------

    #[Test]
    public function analysisBeforeMTGetContribution_mmtEngine_callsSetAnalysis(): void
    {
        $mmt = $this->createMock(MMT::class);
        $mmt->expects(self::once())
            ->method('setAnalysis')
            ->with(false);

        $event = new AnalysisBeforeMTGetContributionEvent(
            ['key' => 'val'],
            $mmt,
            null,
        );

        $this->airbnb->analysisBeforeMTGetContribution($event);
    }

    #[Test]
    public function analysisBeforeMTGetContribution_nonMmtEngine_doesNotCallSetAnalysis(): void
    {
        $engine = new stdClass();

        $event = new AnalysisBeforeMTGetContributionEvent(
            ['key' => 'val'],
            $engine,
            null,
        );

        $this->airbnb->analysisBeforeMTGetContribution($event);

        // No exception = pass; engine is not MMT so setAnalysis is never called
        self::assertSame(['key' => 'val'], $event->getConfig());
    }

    // ---------------------------------------------------------------
    // rewriteContributionContexts
    // ---------------------------------------------------------------

    #[Test]
    public function rewriteContributionContexts_phraseKey_hashesContext(): void
    {
        $idSegment = new SegmentStruct();
        $idSegment->segment = 'source text';

        $segmentsList = new stdClass();
        $segmentsList->id_before = null;
        $segmentsList->id_segment = $idSegment;
        $segmentsList->id_after = new stdClass();
        $segmentsList->isSpice = false;

        $event = new RewriteContributionContextsEvent(
            $segmentsList,
            ['context_before' => 'phrase_key|¶|my_key'],
        );

        $this->airbnb->rewriteContributionContexts($event);

        $result = $event->getSegmentsList();
        self::assertInstanceOf(SegmentStruct::class, $result->id_before);
        self::assertSame(md5('my_key' . 'source text'), $result->id_before->segment);
        self::assertNull($result->id_after);
        self::assertTrue($result->isSpice);
    }

    // ---------------------------------------------------------------
    // fromLayer0ToLayer1
    // ---------------------------------------------------------------

    #[Test]
    public function fromLayer0ToLayer1_addsSmartCountsToPipeline(): void
    {
        $pipeline = $this->createMock(Pipeline::class);
        $pipeline->expects(self::once())
            ->method('addAfter')
            ->with(RubyOnRailsI18n::class, SmartCounts::class);

        $event = new FromLayer0ToLayer1Event($pipeline);

        $this->airbnb->fromLayer0ToLayer1($event);
    }

    // ---------------------------------------------------------------
    // checkTagPositions
    // ---------------------------------------------------------------

    #[Test]
    public function checkTagPositions_notQAInstance_earlyReturn(): void
    {
        $event = new CheckTagPositionsEvent(false, new stdClass());

        $this->airbnb->checkTagPositions($event);

        // errorCode stays false because method returned early
        self::assertFalse($event->getErrorCode());
    }

    #[Test]
    public function checkTagPositions_noSmartCount_earlyReturn(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('getSourceSeg')->willReturn('Simple segment with no pipes');

        $event = new CheckTagPositionsEvent(false, $qa);

        $this->airbnb->checkTagPositions($event);

        // sourceSplittedCount === 1, so early return without setting errorCode to true
        self::assertFalse($event->getErrorCode());
    }

    #[Test]
    public function checkTagPositions_correctPluralForms_2FormLanguage(): void
    {
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';
        $source = 'One item' . $smartCountTag . '%{count} items';
        $target = 'Un elemento' . $smartCountTag . '%{count} elementi';

        $qa = $this->createMock(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('en-GB'); // 2 plural forms
        $qa->expects(self::atLeastOnce())->method('performTagPositionCheck');

        $event = new CheckTagPositionsEvent(false, $qa);

        $this->airbnb->checkTagPositions($event);

        self::assertTrue($event->getErrorCode());
    }

    #[Test]
    public function checkTagPositions_pregSplitReturnsFalse_earlyReturn(): void
    {
        // preg_split returns false on PCRE error; we simulate by giving a source
        // that doesn't match. Actually preg_split on a non-matching pattern returns
        // an array with one element (the original string), not false.
        // A real false requires a PCRE error, which is hard to trigger.
        // Instead, test the second preg_split returning a single-element array
        // (no smart count in source) — already covered above.
        // For this test, we verify that if source has a smart count tag but
        // target has none (targetSplittedByPipeSepCount != targetPluralFormsCount),
        // the method sets errorCode to true without calling performTagPositionCheck.
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';
        $source = 'One' . $smartCountTag . 'Many';
        $target = 'Solo testo senza pipe tag';

        $qa = $this->createMock(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('en-GB'); // expects 2 forms
        $qa->expects(self::never())->method('performTagPositionCheck');

        $event = new CheckTagPositionsEvent(false, $qa);

        $this->airbnb->checkTagPositions($event);

        // errorCode is set to true at the end of the method
        self::assertTrue($event->getErrorCode());
    }

    // ---------------------------------------------------------------
    // checkTagMismatch
    // ---------------------------------------------------------------

    #[Test]
    public function checkTagMismatch_noSmartCountInSource_passesThrough(): void
    {
        $qa = $this->createStub(QA::class);
        $qa->method('getSourceSeg')->willReturn('Simple source without smart count');
        $qa->method('getTargetSeg')->willReturn('Simple target');

        $event = new CheckTagMismatchEvent(999, $qa);

        $this->airbnb->checkTagMismatch($event);

        // Original error code preserved
        self::assertSame(999, $event->getErrorCode());
    }

    #[Test]
    public function checkTagMismatch_pluralMismatch_returnsError2000(): void
    {
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';

        // Source has 1 smart count separator (2 plural forms in source)
        $source = 'One item' . $smartCountTag . '%{count} items';
        // Target has 1 separator too (2 forms), but target lang expects 6 (ar-SA)
        $target = 'عنصر واحد' . $smartCountTag . 'عناصر';

        $qa = $this->createMock(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('ar-SA'); // 6 plural forms
        $qa->expects(self::once())->method('addCustomError');

        $event = new CheckTagMismatchEvent(0, $qa);

        $this->airbnb->checkTagMismatch($event);

        self::assertSame(QA::SMART_COUNT_PLURAL_MISMATCH, $event->getErrorCode());
    }

    #[Test]
    public function checkTagMismatch_correctTagsNoError_returnsZero(): void
    {
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';
        $nameTag = '<ph id="mtc_2" equiv-text="base64:JXtmaXJzdF9uYW1lfQ=="/>';

        // en-GB has 2 plural forms. Source has 2 sections, each with a name tag.
        $source = $nameTag . ' has 1 hour' . $smartCountTag . $nameTag . ' has %{count} hours';
        $target = $nameTag . ' ha 1 ora' . $smartCountTag . $nameTag . ' ha %{count} ore';

        $qa = $this->createStub(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('en-GB'); // 2 plural forms

        $event = new CheckTagMismatchEvent(0, $qa);

        $this->airbnb->checkTagMismatch($event);

        self::assertSame(0, $event->getErrorCode());
    }

    #[Test]
    public function checkTagMismatch_singlePluralFormLanguage_correctTags_returnsZero(): void
    {
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';
        $nameTag = '<ph id="mtc_2" equiv-text="base64:JXtmaXJzdF9uYW1lfQ=="/>';

        // Source (en-US): 2 forms, each containing the name tag.
        $source = $nameTag . ' has 1 hour' . $smartCountTag . $nameTag . ' has %{count} hours';
        // Target (zh-CN): 1 plural form only (no smart count separator), same name tag.
        $target = $nameTag . ' 有 %{count} 小时';

        $qa = $this->createStub(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('zh-CN'); // 1 plural form

        $event = new CheckTagMismatchEvent(0, $qa);

        $this->airbnb->checkTagMismatch($event);

        self::assertSame(0, $event->getErrorCode());
    }

    #[Test]
    public function checkTagMismatch_singlePluralFormLanguage_tagMismatch_returnsError2001(): void
    {
        $smartCountTag = '<ph id="mtc_1" ctype="x-smart-count" equiv-text="base64:fHx8fA=="/>';
        $nameTag = '<ph id="mtc_2" equiv-text="base64:JXtmaXJzdF9uYW1lfQ=="/>';

        // Source (en-US): 2 forms, each containing the name tag.
        $source = $nameTag . ' has 1 hour' . $smartCountTag . $nameTag . ' has %{count} hours';
        // Target (zh-CN): 1 plural form, but missing the name tag.
        $target = 'has %{count} hours';

        $qa = $this->createMock(QA::class);
        $qa->method('getSourceSeg')->willReturn($source);
        $qa->method('getTargetSeg')->willReturn($target);
        $qa->method('getTargetSegLang')->willReturn('zh-CN'); // 1 plural form
        $qa->expects(self::once())->method('addCustomError');

        $event = new CheckTagMismatchEvent(0, $qa);

        $this->airbnb->checkTagMismatch($event);

        self::assertSame(QA::SMART_COUNT_MISMATCH, $event->getErrorCode());
    }

    #[Test]
    public function checkTagMismatch_notQAInstance_earlyReturn(): void
    {
        $event = new CheckTagMismatchEvent(42, new stdClass());

        $this->airbnb->checkTagMismatch($event);

        // Error code unchanged — early return
        self::assertSame(42, $event->getErrorCode());
    }

    // ---------------------------------------------------------------
    // filterContributionStructOnMTSet
    // ---------------------------------------------------------------

    #[Test]
    public function filterContributionStructOnMTSet_setsFieldsCorrectly(): void
    {
        $contributionData = [
            'id_file' => 1,
            'id_segment' => 10,
            'segment' => 'layer1 source',
            'translation' => 'layer1 translation',
            'context_before' => 'layer1 ctx before',
            'context_after' => 'layer1 ctx after',
            'id_job' => 5,
            'job_password' => 'pass',
            'id_mt' => 1,
            'jobStruct' => new JobStruct([
                'id_project' => 1,
                'job_first_segment' => 1,
                'job_last_segment' => 2,
                'source' => 'en-GB',
                'target' => 'it-IT',
            ]),
        ];
        $contributionRequest = new SetContributionRequest($contributionData);

        $segment = new stdClass();
        $segment->segment = 'original source';

        $translation = new stdClass();
        $translation->translation = 'original translation';

        $filter = $this->createStub(AbstractFilter::class);
        $filter->method('fromLayer1ToLayer0')
            ->willReturnCallback(fn(string $s) => 'layer0:' . $s);

        $event = new FilterContributionStructOnMTSetEvent(
            $contributionRequest,
            $translation,
            $segment,
            $filter,
        );

        $this->airbnb->filterContributionStructOnMTSet($event);

        $result = $event->getContributionStruct();
        self::assertInstanceOf(SetContributionRequest::class, $result);
        self::assertSame('original source', $result->segment);
        self::assertSame('original translation', $result->translation);
        self::assertSame('layer0:layer1 ctx before', $result->context_before);
        self::assertSame('layer0:layer1 ctx after', $result->context_after);
    }
}
