<?php

namespace App\Http\Cv;

use Twig\Environment;

final class CvRenderer
{
    private Environment $twig;

    public function __construct(Environment $twig)
    {
        $this->twig = $twig;
    }

    public function renderFragment(string $template, array $data): string
    {
        return $this->twig->render($template, $data);
    }

    public function renderPrivate(array $data, array $labels, string $lang = 'de'): string
    {
        return $this->twig->render('cv_private.html.twig', [
            'cv' => $data,
            'etiketten' => $labels,
            'lang' => $this->normalizeLang($lang),
            'title' => $labels['_'] ?? 'Lebenslauf',
        ]);
    }

    public function renderPublic(array $data, array $labels, string $lang = 'de'): string
    {
        return $this->twig->render('cv_public.html.twig', [
            'cv' => $data,
            'etiketten' => $labels,
            'lang' => $this->normalizeLang($lang),
            'title' => $labels['_'] ?? 'Lebenslauf',
        ]);
    }

    private function normalizeLang(string $lang): string
    {
        $lang = strtolower(trim($lang));
        return $lang === '' ? 'de' : $lang;
    }
}
