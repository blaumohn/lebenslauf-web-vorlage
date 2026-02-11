<?php

namespace App\Http;

use App\Http\Captcha\CaptchaService;
use App\Http\Contact\MailService;
use App\Http\Cv\CvStorage;
use App\Http\Security\IpHashService;
use App\Http\Security\IpSaltRuntime;
use App\Http\Security\RateLimiter;
use App\Http\Security\TokenService;
use App\Http\Storage\FileStorage;
use App\Http\Templating\TwigFactory;
use Twig\Environment;

final class AppContext
{
    public ConfigCompiled $config;
    public Environment $twig;
    public CvStorage $cvStorage;
    public TokenService $tokenService;
    public CaptchaService $captchaService;
    public RateLimiter $rateLimiter;
    public IpHashService $ipHashService;
    public MailService $mailService;
    public IpResolver $ipResolver;

    public static function fromConfig(ConfigCompiled $config): self
    {
        $rootPath = $config->rootPath();
        $storage = new FileStorage();

        $context = new self();
        $context->config = $config;
        $context->twig = TwigFactory::create($rootPath . '/src/resources/templates');
        TwigFactory::configure($context->twig, $config->basePath());
        $context->cvStorage = new CvStorage($storage, $rootPath . '/var/cache/html');
        $context->tokenService = new TokenService($storage, $rootPath . '/var/state/tokens');
        $context->captchaService = new CaptchaService(
            $storage,
            $rootPath . '/var/tmp/captcha',
            $config->getInt('CAPTCHA_TTL_SECONDS', 600)
        );
        $context->rateLimiter = new RateLimiter($storage, $rootPath . '/var/tmp/ratelimit');
        $ipSaltRuntime = self::buildIpSaltRuntime($storage, $rootPath);
        $context->ipHashService = new IpHashService($ipSaltRuntime->resolveSalt());
        $context->mailService = new MailService($config);
        $context->ipResolver = new IpResolver();

        return $context;
    }

    private static function buildIpSaltRuntime(
        FileStorage $storage,
        string $rootPath
    ): IpSaltRuntime {
        return new IpSaltRuntime(
            $storage,
            $rootPath . '/var/state',
            $rootPath . '/var/tmp/captcha',
            $rootPath . '/var/tmp/ratelimit'
        );
    }
}
