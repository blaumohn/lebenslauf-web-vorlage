<?php

namespace App\Http;

use App\Http\Actions\TaskAction;
use App\Http\Actions\CaptchaPngAction;
use App\Http\Actions\ContactFormAction;
use App\Http\Actions\ContactSubmitAction;
use App\Http\Actions\CvAction;
use App\Http\Actions\HomeAction;
use Slim\App;

final class Routes
{
    public static function register(App $app, AppContext $context): void
    {
        $app->get('/', new HomeAction($context))->setName('home');
        $app->get('/cv', new CvAction($context))->setName('cv');

        $app->group('/contact', function ($group) use ($context) {
            $group->get('', new ContactFormAction($context))->setName('contact.form');
            $group->post('', new ContactSubmitAction($context))->setName('contact.submit');
        });

        $app->get('/captcha.png', new CaptchaPngAction($context))
            ->setName('captcha.png');

        $app->get('/tasks/dispatch', new TaskAction($context))
            ->setName('task.dispatch');
    }
}
