<?php

namespace Violinist\ProviderFactory\Provider;

use Violinist\Slug\Slug;

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

    /**
     * {@inheritdoc}
     */
    public function getDefaultBase(Slug $slug, $default_branch)
    {
        return $this->getShaFromBranchAndSlug($default_branch, $slug);
    }

}
