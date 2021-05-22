<?php

namespace Violinist\ProviderFactory\Provider;

use Gitlab\Client;
use Violinist\Slug\Slug;

class Gitlab extends ProviderBase {

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function authenticate($token)
    {
        $this->client->authenticate($token, Client::AUTH_OAUTH_TOKEN);
    }

    public function repoIsPrivate(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        if (!isset($this->cache['repo'])) {
            $this->cache['repo'] = $this->client->api('repo')->show($user, $repo);
        }
        return (bool) $this->cache['repo']['private'];
    }

    public function getDefaultBranch(Slug $slug)
    {
        if (!isset($this->cache['repo'])) {
            $project_id = $this->getProjectId($slug->getUrl());
            $this->cache['repo'] = $this->client->projects()->show($project_id);
        }
        return $this->cache['repo']['default_branch'];
    }

    protected function getBranches($user, $repo)
    {
        if (!isset($this->cache['branches'])) {
            $pager = new ResultPager($this->client);
            $api = $this->client->api('repo');
            $method = 'branches';
            $this->cache['branches'] = $pager->fetchAll($api, $method, [$user, $repo]);
        }
        return $this->cache['branches'];
    }

    public function getBranchesFlattened(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $branches = $this->getBranches($user, $repo);

        $branches_flattened = [];
        foreach ($branches as $branch) {
            $branches_flattened[] = $branch['name'];
        }
        return $branches_flattened;
    }

    public function getPrsNamed(Slug $slug)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $pager = new ResultPager($this->client);
        $api = $this->client->api('pr');
        $method = 'all';
        $prs = $pager->fetchAll($api, $method, [$user, $repo]);
        $prs_named = [];
        foreach ($prs as $pr) {
            $prs_named[$pr['head']['ref']] = $pr;
        }
        return $prs_named;
    }

    public function getShaFromBranchAndSlug($branch, Slug $slug)
    {
        $url = $slug->getUrl();
        $branches = $this->client->repositories()->branches($this->getProjectId($url));
        foreach ($branches as $repo_branch) {
            if ($repo_branch['name'] == $branch) {
                return $repo_branch['commit']['id'];
            }
        }
        return FALSE;
    }

    public function getFileFromSlug(Slug $slug, $file)
    {
        $default_branch = $this->getDefaultBranch($slug);
        $url = $slug->getUrl();
        return $this->client->repositoryFiles()->getRawFile($this->getProjectId($url), $file, $default_branch);
    }

    public function createFork($user, $repo, $fork_user)
    {
        return $this->client->api('repo')->forks()->create($user, $repo, [
            'organization' => $fork_user,
        ]);
    }

    public function createPullRequest(Slug $slug, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        /** @var PullRequest $prs */
        $prs = $this->client->api('pull_request');
        $data = $prs->create($user_name, $user_repo, $params);
        if (!empty($params['assignees'])) {
            // Now try to update it with assignees.
            try {
                /** @var Issue $issues */
                $issues = $this->client->api('issues');
                $issues->update($user_name, $user_repo, $data['number'], [
                    'assignees' => $params['assignees'],
                ]);
            } catch (\Exception $e) {
                // Too bad.
                //  @todo: Should be possible to inject a logger and log this.
            }
        }
        return $data;
    }

    public function updatePullRequest(Slug $slug, $id, $params)
    {
        $user_name = $slug->getUserName();
        $user_repo = $slug->getUserRepo();
        return $this->client->api('pull_request')->update($user_name, $user_repo, $id, $params);
    }

    /**
     * The project id in gitlab.
     */
    protected function getProjectId($url)
    {
        $url = parse_url($url);
        return ltrim($url['path'], '/');
    }
}
