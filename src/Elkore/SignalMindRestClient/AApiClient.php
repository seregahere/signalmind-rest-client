<?php

namespace Elkore\SignalMindRestClient;

abstract class AApiClient
{
    protected $logger = null;

    protected function warning($obj)
    {
        if (is_object($this->logger) && method_exists($this->logger, 'warning')) {
            $this->logger->warning($obj);
        }
    }

    protected function error($obj)
    {
        if (is_object($this->logger) && method_exists($this->logger, 'error')) {
            $this->logger->error($obj);
        }
    }

    protected function info($obj)
    {
        if (is_object($this->logger) && method_exists($this->logger, 'info')) {
            $this->logger->info($obj);
        }
    }
}
