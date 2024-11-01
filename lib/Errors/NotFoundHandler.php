<?php

namespace Meetings\Errors;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use StudipPlugin;
use Throwable;

class NotFoundHandler
{
    use PreparesJsonapiResponse;

    public function __construct(private StudipPlugin $plugin)
    {
    }

    /**
     * Diese Methode wird aufgerufen, sobald es zu einer Exception
     * kam, und generiert eine entsprechende JSON-API-spezifische Response.
     */
    public function __invoke(ServerRequestInterface $request, Throwable $exception, bool $displayErrorDetails)
    {
        $message = $this->plugin->getPluginName() . ' - Slim Application Error: Request not found!';
        $details = 'The Action or Page you are looking for could not be found!';

        return $this->prepareResponseMessage(
            $request,
            app(ResponseFactoryInterface::class)->createResponse(404),
            new Error($message, 404, $details)
        );
    }
}
