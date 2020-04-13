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
    /**
     * Secret key used for signing request.
     *
     * @return string
     */
    abstract public function getSecretKey(): string ;

    /**
     * Hashing algorithm used for signing request.
     *
     * @return string
     */
    abstract public function getHashingAlgorithm(): string ;

    /**
     * List of headers to include in signature.
     *
     * @return array
     */
    abstract public function getHeadersList(): array ;

    /**
     * Get GuzzleHttp Client object with handler that will sign all the requests.
     *
     * @return Client
     * @throws \HttpSignatures\Exception
     */
    public function getClientWithSignHandler(): Client
    {
        $handlerStack = GuzzleHttpSignatures::defaultHandlerFromContext($this->buildContext());

        return new Client(['handler' => $handlerStack]);
    }

    /**
     * Get GuzzleHttp Client object without any handler. I'm sure you have pretty secure setup.
     *
     * @return Client
     */
    public function getClient(): Client
    {
        return new Client();
    }

    /**
     * Prepare the context required to sign requests.
     *
     * @return Context
     * @throws \HttpSignatures\Exception
     */
    protected function buildContext(): Context
    {
        return new Context([
            'keys' => ['ps-key' => $this->getSecretKey()],
            'algorithm' => $this->getHashingAlgorithm(),
            'headers' => $this->getHeadersList()
        ]);
    }
}