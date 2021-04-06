<?php

namespace Violinist\ProviderFactory\Provider;

abstract class ProviderBase implements ProviderInterface
{
    /**
     * A client object, probably to interact with the API.
     *
     * @var object
     */
    protected $client;

    /**
     * Static cache to use between method calls.
     *
     * @var array
     */
    protected $cache = [];

}
