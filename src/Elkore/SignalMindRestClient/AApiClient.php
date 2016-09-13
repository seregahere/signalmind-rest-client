<?php

namespace Elkore\SignalMindRestClient;

abstract class AApiClient
{
    protected $logger = null;

    protected function warning($obj)
    {
        if (is_object($this->logger) && method_exists('warning', $this->logger)) {
            $log->warning($obj);
        }
    }

    protected function error($obj)
    {
        if (is_object($this->logger) && method_exists('error', $this->logger)) {
            $log->error($obj);
        }
    }

    protected function info($obj)
    {
        if (is_object($this->logger) && method_exists('info', $this->logger)) {
            $log->info($obj);
        }
    }
}
