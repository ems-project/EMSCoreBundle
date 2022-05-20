<?php

declare(strict_types=1);

namespace EMS\CoreBundle\Core\Security;

final class Canonicalizer
{
    public static function canonicalize(string $string): string
    {
        $encoding = \mb_detect_encoding($string);
        $result = $encoding
            ? \mb_convert_case($string, MB_CASE_LOWER, $encoding)
            : \mb_convert_case($string, MB_CASE_LOWER);

        return $result;
    }
}
