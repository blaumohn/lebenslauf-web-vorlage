<?php

namespace App\Cli\Site;

use Twig\Environment;

final class CvRenderer
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function renderPrivate(array $data, array $labels, string $lang, string $cvFooter): string
    {
        return $this->twig->render('cv_private.html.twig', [
            'cv' => $data,
            'etiketten' => $labels['cv']['childLabels'],
            'lang' => $this->normalizeLang($lang),
            'title' => $labels['cv']['value'],
            'cv_footer' => $cvFooter,
        ]);
    }

    public function renderPublic(array $data, array $labels, string $lang, string $siteHeader, string $cvFooter): string
    {
        return $this->twig->render('cv_public.html.twig', [
            'cv' => $data,
            'etiketten' => $labels['cv']['childLabels'],
            'lang' => $this->normalizeLang($lang),
            'title' => $labels['cv']['value'],
            'site_header' => $siteHeader,
            'cv_footer' => $cvFooter,
        ]);
    }

    private function normalizeLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return $lang === '' ? 'de' : $lang;
    }
}
