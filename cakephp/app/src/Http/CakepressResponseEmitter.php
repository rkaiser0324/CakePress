<?php

namespace App\Http;

use Cake\Http\ResponseEmitter;
use Psr\Http\Message\ResponseInterface;

class CakepressResponseEmitter extends ResponseEmitter
{
    /**
     * {@inheritDoc}
     *
     * @param \Psr\Http\Message\ResponseInterface $response Response
     * @param int $maxBufferLength Max buffer length
     */
    public function emit(ResponseInterface $response, $maxBufferLength = 8192)
    {
        $file = $line = null;
        if (headers_sent($file, $line)) {
            $message = "Unable to emit headers. Headers sent in file=$file line=$line";
            if (Configure::read('debug')) {
                trigger_error($message, E_USER_WARNING);
            } else {
                Log::warning($message);
            }
        }
/*
        $this->emitStatusLine($response);
        $this->emitHeaders($response);
        $this->flush();
*/
        $range = $this->parseContentRange($response->getHeaderLine('Content-Range'));
        if (is_array($range)) {
            $this->emitBodyRange($range, $response, $maxBufferLength);
        } else {
            $this->emitBody($response, $maxBufferLength);
        }
/*
        if (function_exists('fastcgi_finish_request')) {
            session_write_close();
            fastcgi_finish_request();
        }
        */
    }
}
