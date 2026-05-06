<?php

namespace App\Http;

use App\Http\Admin\AdminTaskRunner;
use App\Http\Admin\Deploy\DeploySwitcher;
use App\Http\Admin\Deploy\DeploySwitchTaskHandler;
use App\Http\Admin\Token\CvTokenRotationTaskHandler;
use App\Http\Captcha\CaptchaService;
use App\Http\Mail\MailService;
use App\Http\Cv\CvStorage;
use App\Http\Security\IpHashService;
use App\Http\Security\IpSaltService;
use App\Http\Security\RateLimiter;
use App\Http\Runtime\RuntimeAtomicWriter;
use App\Http\Runtime\RuntimeLockRunner;
use App\Http\Security\TokenRotationService;
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
    public AdminTaskRunner $adminTaskRunner;

    public static function fromConfig(ConfigCompiled $config): self
    {
        $rootPath = $config->rootPath();
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($rootPath . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();

        $context = new self();
        $context->config = $config;
        $context->twig = TwigFactory::create($rootPath . '/src/resources/templates');
        TwigFactory::configure($context->twig, $config->basePath());
        $context->cvStorage = new CvStorage($storage, $rootPath . '/var/cache/html');
        $context->tokenService = new TokenService($storage, $lockRunner, $writer, $rootPath . '/var/state/tokens');
        $context->captchaService = new CaptchaService(
            $storage,
            $lockRunner,
            $writer,
            $rootPath . '/var/tmp/captcha',
            $config->getInt('CAPTCHA_TTL_SECONDS', 600)
        );
        $context->rateLimiter = new RateLimiter($storage, $lockRunner, $writer, $rootPath . '/var/tmp/ratelimit');
        $ipSaltService = self::buildIpSaltService($storage, $lockRunner, $writer, $rootPath);
        $context->ipHashService = new IpHashService($ipSaltService->resolveSalt());
        $context->mailService = new MailService($config);
        $context->ipResolver = new IpResolver();
        $context->adminTaskRunner = self::buildAdminTaskRunner(
            $writer, $lockRunner, $config, $context->mailService, $context->cvStorage, $context->tokenService
        );

        return $context;
    }

    private static function buildAdminTaskRunner(
        RuntimeAtomicWriter $writer,
        RuntimeLockRunner $lockRunner,
        ConfigCompiled $config,
        MailService $mailService,
        CvStorage $cvStorage,
        TokenService $tokenService,
    ): AdminTaskRunner {
        $entryPath = $config->entryPath();
        $switcher = new DeploySwitcher($writer, $lockRunner, $entryPath);
        $rotateHandler = new TokenRotationService($cvStorage, $tokenService);
        $handlers = [
            new DeploySwitchTaskHandler($switcher),
            new CvTokenRotationTaskHandler($rotateHandler, $mailService),
        ];
        return new AdminTaskRunner($handlers, $entryPath);
    }

    private static function buildIpSaltService(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $rootPath
    ): IpSaltService {
        return new IpSaltService(
            $storage,
            $lockRunner,
            $writer,
            $rootPath . '/var/state',
            $rootPath . '/var/tmp/captcha',
            $rootPath . '/var/tmp/ratelimit'
        );
    }
}
