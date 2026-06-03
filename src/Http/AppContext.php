<?php

namespace App\Http;

use App\Http\Task\TaskRunner;
use App\Http\Task\Deploy\DeploySwitcher;
use App\Http\Task\Deploy\DeploySwitchTaskHandler;
use App\Http\Task\Cv\CvPublishTaskHandler;
use App\Http\Task\Token\CvTokenRotationTaskHandler;
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
use Psr\Log\LoggerInterface;
use Twig\Environment;

final class AppContext
{
    public ConfigCompiled $config;
    public LoggerInterface $logger;
    public Environment $twig;
    public CvStorage $cvStorage;
    public TokenService $tokenService;
    public CaptchaService $captchaService;
    public RateLimiter $rateLimiter;
    public IpHashService $ipHashService;
    public MailService $mailService;
    public IpResolver $ipResolver;
    public TaskRunner $taskRunner;

    public static function fromConfig(
        ConfigCompiled $config,
        string $appRoot,
        string $deployRoot,
        string $basePath,
    ): self {
        $storage = new FileStorage();
        $lockRunner = new RuntimeLockRunner($appRoot . '/var/state/locks');
        $writer = new RuntimeAtomicWriter();
        $logDir = $config->pipeline() === 'dev'
            ? $appRoot . '/var/log'
            : $deployRoot . '/log';

        $context = new self();
        $context->config = $config;
        $context->logger = AppLogger::create($logDir, $config->get('APP_LOG_CHANNEL'));
        $context->twig = TwigFactory::create($appRoot . '/src/resources/templates');
        TwigFactory::configure($context->twig, $basePath);
        $context->cvStorage = new CvStorage($storage, $appRoot . '/var/cache/html');
        $context->tokenService = new TokenService($storage, $lockRunner, $writer, $appRoot . '/var/state/tokens');
        $context->captchaService = new CaptchaService(
            $storage,
            $lockRunner,
            $writer,
            $appRoot . '/var/tmp/captcha',
            (int) $config->get('CAPTCHA_TTL_SECONDS')
        );
        $context->rateLimiter = new RateLimiter($storage, $lockRunner, $writer, $appRoot . '/var/tmp/ratelimit');
        $ipSaltService = self::buildIpSaltService($storage, $lockRunner, $writer, $appRoot);
        $context->ipHashService = new IpHashService($ipSaltService->resolveSalt());
        $context->mailService = new MailService($config);
        $context->ipResolver = new IpResolver();
        $context->taskRunner = self::buildTaskRunner(
            $writer, $lockRunner, $appRoot, $deployRoot, $config, $context->mailService, $context->cvStorage, $context->tokenService, $context->logger
        );

        return $context;
    }

    private static function buildTaskRunner(
        RuntimeAtomicWriter $writer,
        RuntimeLockRunner $lockRunner,
        string $appRoot,
        string $deployRoot,
        ConfigCompiled $config,
        MailService $mailService,
        CvStorage $cvStorage,
        TokenService $tokenService,
        LoggerInterface $logger,
    ): TaskRunner {
        $switcher = new DeploySwitcher($writer, $lockRunner, $deployRoot);
        $rotateHandler = new TokenRotationService($cvStorage, $tokenService);
        $handlers = [
            new DeploySwitchTaskHandler($switcher, $deployRoot),
            new CvTokenRotationTaskHandler($rotateHandler),
            new CvPublishTaskHandler($writer),
        ];
        return new TaskRunner($handlers, $appRoot, $mailService, $logger, $writer);
    }

    private static function buildIpSaltService(
        FileStorage $storage,
        RuntimeLockRunner $lockRunner,
        RuntimeAtomicWriter $writer,
        string $appRoot
    ): IpSaltService {
        return new IpSaltService(
            $storage,
            $lockRunner,
            $writer,
            $appRoot . '/var/state',
            $appRoot . '/var/tmp/captcha',
            $appRoot . '/var/tmp/ratelimit'
        );
    }
}
