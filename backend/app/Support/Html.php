<?php

namespace App\Support;

/** Sanitize rich-text fields before storing them. */
class Html
{
    protected static ?\HTMLPurifier $purifier = null;

    public static function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        if (self::$purifier === null) {
            $config = \HTMLPurifier_Config::createDefault();
            $config->set('HTML.Allowed', 'p,br,b,strong,i,em,u,ul,ol,li,h3,h4,blockquote,a[href|title],div,span');
            $config->set('URI.AllowedSchemes', [
                'http' => true,
                'https' => true,
                'mailto' => true,
            ]);
            self::$purifier = new \HTMLPurifier($config);
        }

        return trim(self::$purifier->purify($html));
    }
}
