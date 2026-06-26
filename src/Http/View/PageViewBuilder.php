<?php

namespace App\Http\View;

final class PageViewBuilder
{
    public static function base(?string $siteHeader, ?string $siteFooter): array
    {
        return ['site_header' => $siteHeader, 'site_footer' => $siteFooter];
    }
}
