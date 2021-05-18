<?php

namespace Violinist\ProviderFactory;

use Github\Client;
use Gitlab\Client as GitlabClient;
use Violinist\ProviderFactory\Provider\Github;
use Violinist\Slug\Slug;

class ProviderFactory {

  public static function createFromSlugAndUrl(Slug $slug, $url)
  {
      $host = $slug->getProvider();
      $provider = null;
      switch ($host) {
          case 'github.com':
              $client = new Client();
              $provider = new Github($client);
              break;

          case 'gitlab.com':
              $client = new GitlabClient();
              $provider = new Gitlab($client);
              break;

          case 'bitbucket.org':
              $client = new \Bitbucket\Client();
              $provider = new Bitbucket($client);
              break;

          default:
              // @todo: Support more self-hosted at some point.
              $client = new GitlabClient();
              $provider = new SelfHostedGitlab($client, $url);
              break;
      }
      return $provider;
  }

}
