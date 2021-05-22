<?php

namespace Violinist\ProviderFactory\Provider;

use Bitbucket\Client;
use Violinist\Slug\Slug;

class BitBucket extends ProviderBase {

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function authenticate($token)
    {
        $this->client->authenticate(Client::AUTH_OAUTH_TOKEN, $token);
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
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $repo = $this->client->repositories()->users($user)->show($repo);
        return $repo["mainbranch"]["name"];
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
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $repo_users = $this->client->repositories()->users($user);
        $repo_users->setPerPage(1000);
        $branches = $repo_users->refs($repo)->branches()->list();
        foreach ($branches["values"] as $remote_branch) {
            if ($remote_branch['name'] == $branch) {
                return $remote_branch['commit']['id'];
            }
        }
        return FALSE;
    }

    public function getFileFromSlug(Slug $slug, $file)
    {
        $user = $slug->getUserName();
        $repo = $slug->getUserRepo();
        $default_branch = $this->getDefaultBranch($slug);
        $file_contents = $this->client->repositories()->users($user)->src($repo)->download($default_branch, $file);
        return (string) $file_contents->getContents();
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
}
