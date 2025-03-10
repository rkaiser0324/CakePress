<?php

namespace App\Http;

use Cake\Http\Server;
use Laminas\Diactoros\Response\EmitterInterface;
use Psr\Http\Message\ResponseInterface;

class CakepressServer extends Server
{
    /**
     * Emit the response using the PHP SAPI.
     *
     * @param \Psr\Http\Message\ResponseInterface $response The response to emit
     * @param \Laminas\Diactoros\Response\EmitterInterface|null $emitter The emitter to use.
     *   When null, a SAPI Stream Emitter will be used.
     * @return void
     */
    public function emit(ResponseInterface $response, EmitterInterface $emitter = null)
    {
        $response->withHeader('Content-Type', '');

        if (!$emitter) {
            $emitter = new CakepressResponseEmitter();
        }
        $emitter->emit($response);
    }
}
