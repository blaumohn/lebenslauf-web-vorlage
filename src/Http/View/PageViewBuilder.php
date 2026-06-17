<?php

namespace App\Http\View;

final class PageViewBuilder
{
    public static function base(?string $siteHeader): array
    {
        return ['site_header' => $siteHeader];
    }
}
