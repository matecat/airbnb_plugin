import React from 'react'
import {isUndefined, forOwn} from 'lodash'
import TextUtils from '../../../../../public/js/cat_source/es6/utils/textUtils'
import SegmentActions from '../../../../../public/js/cat_source/es6/actions/SegmentActions'
import SegmentStore from '../../../../../public/js/cat_source/es6/stores/SegmentStore'
import SegmentFooterTabMessages from '../../../../../public/js/cat_source/es6/components/segments/SegmentFooterTabMessages'
import SegmentFooter from '../../../../../public/js/cat_source/es6/components/segments/SegmentFooter'
import SegmentUtils from '../../../../../public/js/cat_source/es6/utils/segmentUtils'
// Override characters size mapping

const AIRBNB_FEATURE = 'airbnb'

const init = () => {
  import('./airbnb-core.scss')

  TextUtils.charsSizeMapping = {
    default: (value) => TextUtils.getDefaultCharsSize(value),
    custom: [
      (value) => TextUtils.getCJKMatches(value, TextUtils.getUft16CharsSize),
      (value) =>
        TextUtils.getFullwidthVariantsMatches(
          value,
          TextUtils.getUft16CharsSize,
        ),
    ],
  }
  SegmentActions.addGlossaryItem = function () {
    return false
  }
  if (typeof UI !== 'undefined') {
    $.extend(UI, {
      originalRegisterFooterTabs: UI.registerFooterTabs,

      registerFooterTabs: function () {
        this.originalRegisterFooterTabs.apply(this)
        SegmentActions.registerTab('messages', true, true)
      },
      getContextBefore: function (segmentId) {
        let segmentObj
        let phraseKeyNote
        try {
          segmentObj = SegmentStore.getSegmentByIdToJS(segmentId)
          phraseKeyNote = segmentObj.notes.find((item) => {
            return (
              item.note.indexOf('phrase_key|¶|') >= 0 ||
              item.note.indexOf('translation_context|¶|') >= 0
            )
          })
        } catch (e) {
          return null
        }

        if (phraseKeyNote) {
          return phraseKeyNote.note
        } else {
          return null
        }
      },
      getContextAfter: function (segmentId) {
        return ''
      },
      getIdBefore: function (segmentId) {
        var segment = $('#segment-' + segmentId)
        var originalId = segment.attr('data-split-original-id')
        var segmentBefore = (function findBefore(segment) {
          var before = segment.prev()
          if (before.length === 0) {
            return undefined
          } else if (before.attr('data-split-original-id') !== originalId) {
            return before
          } else {
            return findBefore(before)
          }
        })(segment)
        // var segmentBefore = findSegmentBefore();
        if (isUndefined(segmentBefore)) {
          return null
        }
        var segmentBeforeId = UI.getSegmentId(segmentBefore)
        return segmentBeforeId
      },
      getIdAfter: function (segmentId) {
        var segment = $('#segment-' + segmentId)
        var originalId = segment.attr('data-split-original-id')
        var segmentAfter = (function findAfter(segment) {
          var after = segment.next()
          if (after.length === 0) {
            return undefined
          } else if (after.attr('data-split-original-id') !== originalId) {
            return after
          } else {
            return findAfter(after)
          }
        })(segment)
        if (isUndefined(segmentAfter)) {
          return null
        }
        return UI.getSegmentId(segmentAfter)
      },
    })
  }

  function overrideTabMessages(SegmentTabMessages) {
    SegmentTabMessages.prototype.getNotes = function () {
      let notesHtml = []
      let self = this
      if (this.props.notes) {
        const {metadata} = this.props
        this.props.notes.forEach(function (item, index) {
          if (item.note && item.note !== '') {
            if (item.note.indexOf('¶') === -1) {
              // jsont2
              if (
                metadata &&
                metadata.find(({meta_key}) => meta_key === 'id_content') &&
                metadata.find(({meta_key}) => meta_key === 'id_request')
              ) {
                let note = item.note
                note = TextUtils.replaceUrl(note.replace(/[ ]*\n/g, '<br>\n'))
                const html = (
                  <div className="note" key={'note-' + index}>
                    <span className="note-label">Note: </span>
                    <span dangerouslySetInnerHTML={self.allowHTML(note)} />
                  </div>
                )
                notesHtml.push(html)
              } else {
                let split = item.note.split(':')
                if (split.length > 1) {
                  let note = item.note.replace(split[0] + ':', '')
                  note = TextUtils.replaceUrl(note.replace(/[ ]*\n/g, '<br>\n'))
                  const html = (
                    <div className="note" key={'note-' + index}>
                      <span className="note-label">{split[0]}:</span>
                      <span dangerouslySetInnerHTML={self.allowHTML(note)} />
                    </div>
                  )
                  notesHtml.push(html)
                }
              }
            }
          }
        })
      }
      if (this.props.segmentSource.indexOf('"base64:fHx8fA=="') > -1) {
        //base64 of pipes "||||", now they are in tag form
        let targetPrefix = config.target_rfc.split('-')[0]
        let langLike
        //Search the dialect
        forOwn(PLURAL_TYPE_NAME_TO_LANGUAGES, function (value, key) {
          if (value.indexOf(config.target_rfc) !== -1) {
            langLike = key
            return false
          }
        })
        // In not search de prefix
        if (!langLike) {
          forOwn(PLURAL_TYPE_NAME_TO_LANGUAGES, function (value, key) {
            if (value.indexOf(targetPrefix) !== -1) {
              langLike = key
              return false
            }
          })
        }
        if (!isUndefined(langLike) && PLURAL_TYPES[langLike]) {
          let rules = PLURAL_TYPES[langLike]
          let html = (
            <div className="note" key="forms">
              <span className="note-label">Plural forms: </span>
              <span dangerouslySetInnerHTML={self.allowHTML(rules.num_forms)} />
            </div>
          )
          notesHtml.push(html)
          if (rules.doc && rules.doc.length) {
            html = (
              <div className="note" key="rules">
                <span className="note-label">Rules for smart count: </span>
                <span
                  dangerouslySetInnerHTML={self.allowHTML(
                    rules.doc.join(' |||| '),
                  )}
                />
              </div>
            )
            notesHtml.push(html)
          }
        }
      }
      // metadata notes
      if (this.props.metadata) {
        notesHtml.push(this.getMetadataNoteTemplate())
      }
      return notesHtml
    }

    var PLURAL_TYPES = {
      chinese_like: {
        num_forms: 1,

        doc: null,

        rule: 'lambda { |n| 0 }',
      },

      german_like: {
        num_forms: 2,

        doc: ['When count is 1', 'Everything else (0, 2, 3, ...)'],

        rule: 'lambda { |n| n != 1 ? 1 : 0 }',
      },
      thai_like: {
        num_forms: 2,

        doc: [
          'Main translation',
          'Duplicate form 1 or translate differently as needed (tags must be preserved)',
        ],

        rule: '',
      },

      french_like: {
        num_forms: 2,

        doc: ['When count is 0 or 1', 'Everything else (2, 3, 4, ...)'],

        rule: 'lambda { |n| n > 1 ? 1 : 0 }',
      },

      russian_like: {
        num_forms: 3,

        doc: [
          'When count ends in 1, excluding 11 (1, 21, 31, ...)',
          'When count ends in 2-4, excluding 12-14 (2, 3, 4, 22, ...)',
          'Everything else (0, 5, 6, ...)',
        ],

        rule: 'lambda { |n| n % 10 == 1 && n % 100 != 11 ? 0 : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 1 : 2 }',
      },

      czech_like: {
        num_forms: 3,

        doc: [
          'When count is 1',
          'When count is 2, 3, 4',
          'Everything else (0, 5, 6, ...)',
        ],

        rule: 'lambda { |n| (n == 1) ? 0 : (n >= 2 && n <= 4) ? 1 : 2 }',
      },

      polish_like: {
        num_forms: 4,

        doc: [
          'When count is 1',
          'When count ends in 2~4, excluding 12~14 (2, 3, 4, 22, ...)',
          'Other integers (0, 5, 6, ...)',
          'Fractions',
        ],

        rule: 'lambda { |n| (n == 1 ? 0 : n % 10 >= 2 && n % 10 <= 4 && (n % 100 < 10 || n % 100 >= 20) ? 1 : 2) }',
      },

      icelandic_like: {
        num_forms: 2,

        doc: [
          'When count ends in 1, excluding 11 (1, 21, 31, ...)',
          'Everything else (0, 2, 3, ...)',
        ],

        rule: 'lambda { |n| (n % 10 != 1 || n % 100 == 11) ? 1 : 0 }',
      },

      arabic_like: {
        num_forms: 6,

        doc: [
          'When count is 0',

          'When count is 1',

          'When count is 2',

          'When count is a number like 3~10, 103~110, 1003, …',

          'When count is a number like 11~26, 111, 1011, …',

          'When count is a number like 100~102, 200~202, 300~302, 400~402, 500~502, 600, 1000, 10000, 100000, 1000000, …',
        ],

        rule: 'lambda { |n| n == 0 ? 0 : n == 1 ? 1 : n == 2 ? 2 : n % 100 >= 3 && n % 100 <= 10 ? 3 : n % 100 >= 11 ? 4 : 5',
      },

      tagalog_like: {
        num_forms: 2,

        doc: [
          'When count ends in 0, 1, 2, 3, 5, 7, 8 (0~3, 5, 7, 8, 10~13, 15, ...)',

          'Everything else (4, 6, 9, 14, 16, 19, ...)',
        ],
      },

      latvian_like: {
        num_forms: 3,

        doc: [
          'When count ends in 0 or 11~19 (0, 10~20, 30, 40, ...)',

          'When count ends in 1, excluding ending in 11 (1, 21, 31, ...)',

          'Everything else (2~9, 22~29, ...)',
        ],
      },

      lithuanian_like: {
        num_forms: 3,

        doc: [
          'When count ends in 1, excluding ending in 11 (1, 21, 31, ...)',

          'When count ends in 2~9, excluding ending in 12~19 (2~9, 22~29, ...)',

          'Everything else (0, 10~20, 30, ...)',
        ],
      },

      romanian_like: {
        num_forms: 3,

        doc: [
          'When count is 1 ',

          'When count is 0 or ends in 01~19 excluding 1 (0, 2~19, 101~119, ...)',

          'Everything else (20~100, 120~200, ...)',
        ],
      },

      maltese_like: {
        num_forms: 4,

        doc: [
          'When count is 1 ',

          'When count is 0 or ends in 02~10 (0, 2~10, 102~110, ...)',

          'When count ends in 11~19 (11~19, 111~119, ...)',

          'Everything else (20~101, 120~201, ...)',
        ],
      },

      slovenian_like: {
        num_forms: 4,

        doc: [
          'When count ends in 01 (1, 101, 201, ...)',

          'When count ends in 02 (2, 102, 202, ...) ',

          'When count ends in 03 or 04 (3, 4, 103, 104, 203, ...)',

          'Everything else (0, 5~100, 105~200, ...) ',
        ],
      },

      irish_like: {
        num_forms: 5,

        doc: [
          'When count is 1',

          'When count is 2',

          'When count is 3, 4, 5, 6',

          'When count is 7, 8, 9, 10',

          'Everything else (0, 11, 12, ...)',
        ],
      },
    }

    var PLURAL_TYPE_NAME_TO_LANGUAGES = {
      chinese_like: ['ja', 'ms', 'zh', 'zh-TW', 'ko', 'vi'],

      german_like: [
        'da',
        'de',
        'en',
        'es',
        'ka',
        'fi',
        'el',
        'ca',
        'he',
        'hu',
        'it',
        'mr',
        'nl',
        'no',
        'nn',
        'nb',
        'pt',
        'sv',
        'sq',
        'bg',
        'et',
        'sw',
      ],

      french_like: ['fr', 'kn', 'hy', 'hi', 'pt-BR', 'xh', 'zu'],

      thai_like: ['az', 'id', 'th', 'tr'],

      russian_like: ['hr', 'ru', 'bs', 'me', 'sr', 'uk'],

      czech_like: ['cs', 'sk'],

      polish_like: ['pl'],

      latvian_like: ['lv'],

      lithuanian_like: ['lt'],

      icelandic_like: ['is', 'mk'],

      arabic_like: ['ar'],

      tagalog_like: ['tl'],

      romanian_like: ['ro'],

      maltese_like: ['mt'],

      slovenian_like: ['sl'],

      irish_like: ['ga'],
    }
  }

  function overrideSetDefaultTabOpen(SegmentFooter) {
    SegmentFooter.prototype.setDefaultTabOpen = function () {
      return false
    }
  }

  function ovverrideSegmentUtilFn(SegmentUtils) {
    const originalFn = SegmentUtils.segmentHasNote
    SegmentUtils.segmentHasNote = (segment) => {
      const hasOrginalNotes = originalFn(segment)
      return (
        hasOrginalNotes || segment.segment.indexOf('"base64:fHx8fA=="') > -1
      )
    }
  }

  overrideTabMessages(SegmentFooterTabMessages)
  overrideSetDefaultTabOpen(SegmentFooter)
  ovverrideSegmentUtilFn(SegmentUtils)
}
document.addEventListener('DOMContentLoaded', function (event) {
  if (config.project_plugins.indexOf(AIRBNB_FEATURE) > -1) init()
})
