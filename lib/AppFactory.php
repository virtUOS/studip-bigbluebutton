<?php

namespace Meetings;

use DI\ContainerBuilder;
use Psr\Container\ContainerInterface;
use Slim\App;
use StudipPlugin;

use Slim\Factory\AppFactory as SlimAppFactory;
use  Meetings\Errors\ErrorMiddleware;

/**
 * Diese Klasse erstellt eine neue Slim-Applikation und konfiguriert
 * diese rudimentär vor.
 *
 * Dabei werden im `Dependency Container` der Slim-Applikation unter
 * dem Schlüssel `plugin` das Stud.IP-Plugin vermerkt und außerdem
 * eingestellt, dass Fehler des Slim-Frameworks detailliert angezeigt
 * werden sollen, wenn sich Stud.IP im Modus `development` befindet.
 *
 * Darüber hinaus wird eine Middleware installiert, die alle Requests umleitet,
 * die mit einem Schrägstrich enden (und zwar jeweils auf das Pendant
 * ohne Schrägstrich).
 *
 * @see http://www.slimframework.com/
 * @see \Studip\ENV
 */
class AppFactory
{
    /**
     * Diese Factory-Methode erstellt die Slim-Applikation und
     * konfiguriert diese wie oben angegeben.
     *
     * @param \StudipPlugin $plugin das Plugin, für die die
     *                              Slim-Applikation erstellt werden soll
     *
     * @return \Slim\App die erstellte Slim-Applikation
     */
    public function makeApp(StudipPlugin $plugin): App
    {
        SlimAppFactory::setContainer($this->getContainer($plugin));
        $app = SlimAppFactory::create();
        $app->setBasePath($GLOBALS['CANONICAL_RELATIVE_PATH_STUDIP'] . 'plugins.php');

        $app->addRoutingMiddleware();
        $app->add(\Middlewares\TrailingSlash::class);

        $this->setErrorMiddleware($plugin, $app);

        return $app;
    }

    private function getContainer(StudipPlugin $plugin): ContainerInterface
    {
        $builder = new ContainerBuilder();
        $builder->addDefinitions([
            StudipPlugin::class => $plugin,
            'roles' => ['admin' => 'Meetings_Admin'],
            'studip-current-user' => \DI\factory([\User::class, 'findCurrent']),
            Middlewares\Authentication::class => function (ContainerInterface $container) {
                return new Middlewares\Authentication(function ($username, $password) {
                    $check = \StudipAuthAbstract::CheckAuthentication($username, $password);

                    if ($check['uid'] && $check['uid'] != 'nobody') {
                        return \User::find($check['uid']);
                    }

                    return null;
                });
            },
        ]);

        return $builder->build();
    }

    private function setErrorMiddleware(StudipPlugin $plugin, App $app): void
    {
        $displayErrorDetails =
            (defined('\\Studip\\ENV') && \Studip\ENV === 'development') || $GLOBALS['perm']->have_perm('root');

        $container = $app->getContainer();

        $logger = $container->has(LoggerInterface::class) ? $container->get(LoggerInterface::class) : null;

        $errorMiddleware = new ErrorMiddleware(
            callableResolver: $app->getCallableResolver(),
            responseFactory: $app->getResponseFactory(),
            displayErrorDetails: $displayErrorDetails,
            logErrors: true,
            logErrorDetails: true,
            logger: $logger,
            plugin: $plugin
        );
        $app->addMiddleware($errorMiddleware);
    }
}
