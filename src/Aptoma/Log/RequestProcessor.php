<?php
namespace Aptoma\Log;

use Pimple\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * RequestProcessor adds extra information about the request.
 *
 * It will add clientIp and a unique request token, as well as the username
 * of the currently logged in user if the Symfony Security Component is in use.
 *
 * @author Gunnar Lium <gunnar@aptoma.com>
 */
class RequestProcessor
{
    /**
     * @var Application
     */
    private $app;
    private $token;
    private $clientIp;
    private $remoteRequestToken;

    public function __construct(Container $app, $token = null)
    {
        $this->app = $app;
        $this->token = $token ?: uniqid();
    }

    public function __invoke(array $record)
    {
        try {
            $record['extra']['clientIp'] = $this->getClientIp($this->app['request_stack']);
        } catch (\Exception $e) {
            $record['extra']['clientIp'] = '';
        }
        if ($this->app->offsetExists('security.token_storage')) {
            $record['extra']['user'] = $this->getUsername($this->app['security.token_storage']);
        }
        $record['extra']['token'] = $this->token;

        $record = $this->addRemoteRequestToken($record, $this->app['request_stack']);

        return $record;
    }

    private function getClientIp(RequestStack $requestStack)
    {
        if (!$this->clientIp) {
            if ($requestStack->getCurrentRequest()) {
                $this->clientIp = $requestStack->getCurrentRequest()->getClientIp();
            }
        }

        return $this->clientIp;
    }

    private function getUsername(TokenStorageInterface $tokenStorage)
    {
        try {
            $token = $tokenStorage->getToken();

            if ($token) {
                return $token->getUsername();
            }
        } catch (\InvalidArgumentException $e) {
        }

        return '';
    }

    private function addRemoteRequestToken($record, RequestStack $requestStack)
    {
        if (!$this->remoteRequestToken) {
            $request = $requestStack->getCurrentRequest();
            if ($request && null !== $remoteRequestToken = $request->headers->get('X-Remote-Request-Token')) {
                $this->remoteRequestToken = $remoteRequestToken;
            }
        }

        if ($this->remoteRequestToken) {
            $record['extra']['remoteRequestToken'] = $this->remoteRequestToken;
            return $record;
        }

        return $record;
    }
}
