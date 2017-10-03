<?php

namespace Aptoma\Security\Http\Firewall;

use Symfony\Component\Security\Http\Firewall\ListenerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\AuthenticationManagerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Aptoma\Security\Authentication\Token\ApiKeyToken;

class ApiKeyAuthenticationListener implements ListenerInterface
{
    private $tokenStorage;
    private $authenticationManager;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        AuthenticationManagerInterface $authenticationManager
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->authenticationManager = $authenticationManager;
    }

    /**
     * Handles API key authentication.
     *
     * @param GetResponseEvent $event A GetResponseEvent instance
     */
    public function handle(GetResponseEvent $event)
    {
        $apiKey = $event->getRequest()->get('apikey', false);

        if (false === $apiKey) {
            return;
        }

        try {
            $token = $this->authenticationManager->authenticate(new ApiKeyToken($apiKey));
            $this->tokenStorage->setToken($token);
        } catch (AuthenticationException $failed) {
            $this->tokenStorage->setToken(null);
            $this->doFailureResponse($event);
        }
    }

    /**
     * Failure response
     *
     * Can be overridden if a different response is needed
     *
     * @param GetResponseEvent $event
     */
    protected function doFailureResponse(GetResponseEvent $event)
    {
        $headers = array();
        $content = 'Forbidden';
        if (in_array('application/json', $event->getRequest()->getAcceptableContentTypes())) {
            $headers['Content-Type'] = 'application/json';
            $content = json_encode(array('message' => $content));
        }

        $event->setResponse(new Response($content, 403, $headers));
    }
}
