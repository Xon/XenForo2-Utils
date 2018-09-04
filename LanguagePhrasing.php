<?php

namespace SV\Utils;

class LanguagePhrasing extends \XF\Language
{
    public static function forceSetPhrase(\XF\Language $language, $name, $text)
    {
        $language->phraseCache[$name] = $text;
    }
}