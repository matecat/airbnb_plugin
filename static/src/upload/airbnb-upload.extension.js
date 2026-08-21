import { CHARS_SIZE_COUNTER_TYPES } from "../../../../../public/js/utils/charsSizeCounterUtil"
import { useProjectTemplateInterface } from "../../../../../public/js/hooks/useProjectTemplates"

const AIRBNB_FEATURE = 'airbnb'

function init(){
    useProjectTemplateInterface.getCharacterCounterMode = () => CHARS_SIZE_COUNTER_TYPES.EXCLUDE_CJK
}

document.addEventListener('DOMContentLoaded', function () {
    if (config.user_plugins.indexOf(AIRBNB_FEATURE) > -1 ){
        init()
      }
  })