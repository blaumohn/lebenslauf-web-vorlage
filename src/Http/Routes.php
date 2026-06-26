<?php

namespace App\Http;

use App\Http\Actions\BlogAction;
use App\Http\Actions\TaskAction;
use App\Http\Actions\CaptchaPngAction;
use App\Http\Actions\ContactFormAction;
use App\Http\Actions\ContactSubmitAction;
use App\Http\Actions\CvAction;
use App\Http\Actions\HomeAction;
use App\Http\Middleware\LangMiddleware;
use Slim\App;

final class Routes
{
    public static function register(App $app, AppContext $context): void
    {
        $lang = new LangMiddleware($context);

        $app->get('/', new HomeAction($context))->setName('home')->add($lang);
        $app->get('/cv', new CvAction($context))->setName('cv')->add($lang);

        $blog = new BlogAction($context);
        $app->get('/blog', [$blog, 'invokeIndex'])->setName('blog.index')->add($lang);
        $app->get('/blog/{slug}', [$blog, 'invokePost'])->setName('blog.post')->add($lang);

        $app->group('/contact', function ($group) use ($context, $lang) {
            $group->get('', new ContactFormAction($context))->setName('contact.form')->add($lang);
            $group->post('', new ContactSubmitAction($context))->setName('contact.submit')->add($lang);
        });

        $app->get('/captcha.png', new CaptchaPngAction($context))
            ->setName('captcha.png');

        $app->get('/tasks/dispatch', new TaskAction($context))
            ->setName('task.dispatch');
    }
}
