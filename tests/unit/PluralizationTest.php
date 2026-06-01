<?php

namespace unit;

use Features\Airbnb\Utils\SmartCount\Pluralization;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TestHelpers\AbstractTest;

class PluralizationTest extends AbstractTest
{
    /**
     * @return array<string, array{string, int}>
     */
    public static function languageProvider(): array
    {
        return [
            // 1 plural form
            'Chinese Simplified' => ['zh-CN', 1],
            'Chinese Hong Kong' => ['zh-HK', 1],
            'Chinese Traditional' => ['zh-TW', 1],
            'Malay' => ['ms-MY', 1],
            'Japanese' => ['ja-JP', 1],
            'Korean' => ['ko-KR', 1],
            'Vietnamese' => ['vi-VN', 1],

            // 2 plural forms
            'Azerbaijani' => ['az-AZ', 2],
            'Indonesian' => ['id-ID', 2],
            'Thai' => ['th-TH', 2],
            'Turkish' => ['tr-TR', 2],
            'Bulgarian' => ['bg-BG', 2],
            'Catalan' => ['ca-ES', 2],
            'Danish' => ['da-DK', 2],
            'German' => ['de-DE', 2],
            'Greek' => ['el-GR', 2],
            'English AU' => ['en-AU', 2],
            'English CA' => ['en-CA', 2],
            'English GB' => ['en-GB', 2],
            'Spanish' => ['es-ES', 2],
            'Spanish AR' => ['es-AR', 2],
            'Spanish CO' => ['es-CO', 2],
            'Spanish 419' => ['es-419', 2],
            'Spanish MX' => ['es-MX', 2],
            'Spanish US' => ['es-US', 2],
            'Estonian' => ['et-EE', 2],
            'Finnish' => ['fi-FI', 2],
            'French' => ['fr-FR', 2],
            'French CA' => ['fr-CA', 2],
            'Hebrew' => ['he-IL', 2],
            'Hindi' => ['hi-IN', 2],
            'Hungarian' => ['hu-HU', 2],
            'Armenian' => ['hy-AM', 2],
            'Georgian' => ['ka-GE', 2],
            'Kannada' => ['kn-IN', 2],
            'Icelandic' => ['is-IS', 2],
            'Italian' => ['it-IT', 2],
            'Macedonian' => ['mk-MK', 2],
            'Marathi' => ['mr-IN', 2],
            'Norwegian Bokmal' => ['nb-NO', 2],
            'Dutch' => ['nl-NL', 2],
            'Dutch Belgium' => ['nl-BE', 2],
            'Norwegian Nynorsk' => ['nn-NO', 2],
            'Portuguese BR' => ['pt-BR', 2],
            'Portuguese PT' => ['pt-PT', 2],
            'Albanian' => ['sq-AL', 2],
            'Swedish' => ['sv-SE', 2],
            'Swahili' => ['sw-KE', 2],
            'Filipino' => ['tl-PH', 2],
            'Xhosa' => ['xh-ZA', 2],
            'Zulu' => ['zu-ZA', 2],

            // 3 plural forms
            'Slovak' => ['sk-SK', 3],
            'Czech' => ['cs-CZ', 3],
            'Bosnian' => ['bs-BA', 3],
            'Croatian' => ['hr-HR', 3],
            'Lithuanian' => ['lt-LT', 3],
            'Latvian' => ['lv-LV', 3],
            'Romanian' => ['ro-RO', 3],
            'Russian' => ['ru-RU', 3],
            'Serbian Latin' => ['sr-Latn-RS', 3],
            'Serbian Montenegro' => ['sr-ME', 3],
            'Ukrainian' => ['uk-UA', 3],

            // 4 plural forms
            'Maltese' => ['mt-MT', 4],
            'Polish' => ['pl-PL', 4],
            'Slovenian' => ['sl-SI', 4],

            // 5 plural forms
            'Irish' => ['ga-IE', 5],

            // 6 plural forms
            'Arabic' => ['ar-SA', 6],

            // Unknown language → 0
            'Unknown' => ['xx-XX', 0],
            'Empty string' => ['', 0],
        ];
    }

    #[Test]
    #[DataProvider('languageProvider')]
    public function returnsCorrectPluralFormCount(string $lang, int $expected): void
    {
        $this->assertSame($expected, Pluralization::getCountFromLang($lang));
    }
}
