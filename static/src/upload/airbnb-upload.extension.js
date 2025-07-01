import { UseProjectTemplateInterface } from "../../../../../public/js/hooks/useProjectTemplates"
import { CHARS_SIZE_COUNTER_TYPES } from "../../../../../public/js/utils/charsSizeCounterUtil"

const AIRBNB_FEATURE = 'airbnb'

function init(){
    UseProjectTemplateInterface.prototype.getCharacterCounterMode = () => CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK
}

document.addEventListener('DOMContentLoaded', function () {
    if (config.user_plugins.indexOf(AIRBNB_FEATURE) > -1 ){
        init()
      }
  })