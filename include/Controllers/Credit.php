<?php

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * @property LoggerInterface $logger;
 */
class Credit
{
    /**
     * @var LoggerInterface
     */
    protected $logger

    /**
     * @param LoggerInterface $logger
     */
    public function __construct($logger)
    {
        $this->logger = $logger;
    }

    public function action(ServerRequestInterface $request, ResponseInterface $response, $args=[])
    {
        $this->logger->info((string)$request->getUri());
        /* some actions */
        return $response;
    }
}