<?php
/**
 * Created by PhpStorm.
 * User: digital14
 * Date: 3/18/20
 * Time: 11:48 AM
 */

namespace Ipedis\Rabbit\Signer;


use GuzzleHttp\Client;
use HttpSignatures\Context;
use HttpSignatures\GuzzleHttpSignatures;

trait RequestSigner
{
    abstract public function getSecretKey(): string ;
    abstract public function getHashingAlgorithm(): string ;
    abstract public function getHeadersList(): array ;

    /**
     * @return Client
     */
    public function getClientWithSignHandler(): Client
    {
        $handlerStack = GuzzleHttpSignatures::defaultHandlerFromContext($this->buildContext());

        return new Client(['handler' => $handlerStack]);
    }

    /**
     * @return Client
     */
    public function getClient(): Client
    {
        return new Client();
    }

    protected function buildContext(): Context
    {
        return new Context([
            'keys' => ['ps-key' => $this->getSecretKey()],
            'algorithm' => $this->getHashingAlgorithm(),
            'headers' => $this->getHeadersList()
        ]);
    }
}