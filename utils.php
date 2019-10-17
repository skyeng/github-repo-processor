<?php

function commitFile(string $localpath, string $repopath, string $repo, string $branch, String $message){
    global $client;
    global $org;

    echo "Commiting $repopath to $repo ... ";
    $client->repository()->contents()->update(
        $org,
        $repo,
        $repopath,
        file_get_contents($localpath),
        $message,
        $client->repository()->contents()->show($org, $repo, $repopath, "refs/heads/$branch")['sha'],
        $branch
    );
    echo "Done.\n";
}

function createBranch(string $repo, string $branch){
    global $client;
    global $org;

    echo "Creating branch $branch in $repo ... ";
    $master = $client->repositories()->branches($org, $repo, 'master');
    try {
        $client->git()->references()->create($org, $repo, [
            'ref' => "refs/heads/$branch",
            'sha' => $master['commit']['sha'],
        ]);
        echo "Done\n";
    } catch (\Github\Exception\RuntimeException $e) {
        if ($e->getMessage() === 'Reference already exists') {
            echo "Branch already exists, skipping\n";
        } else {
            throw $e;
        }
    }
}

function sendPullRequest(string $repo, string $branch, string $title){
    global $client;
    global $org;

    echo "Sending pull request of $branch in $repo ... ";
    $client->pullRequests()->create($org, $repo, [
        'base'  => 'master',
        'head'  => $branch,
        'title' => $title,
        'body'  => ''
    ]);
    echo "Done.\n";
}

function getPullRequestID(string $repo, string $branch){
    global $client;
    global $org;

    $prs = $client->pullRequests()->all($org, $repo, [
        'head' => "$org:$branch"
    ]);
    if (count($prs) > 1) {
        throw new LogicException("There's several PR for this branch: " . join(', ',
                array_map(function($pr){return $pr['url'];}, $prs)
            )
        );
    } else {
        return $prs[0]['number'];
    }
}

function renamePullRequest(string $repo, string $branch, string $title){
    global $client;
    global $org;

    echo "Renaming pull request of $branch in $repo ... ";
    $client->pullRequests()->update($org, $repo, getPullRequestID($repo, $branch), [
        'title' => $title,
    ]);
    echo "Done.\n";
}

function mergePullRequest(string $repo, string $branch){
    global $client;
    global $org;

    echo "Merging pull request of $branch in $repo ... ";
    $client->pullRequests()->merge(
        $org,
        $repo,
        getPullRequestID($repo, $branch),
        "Merge $branch",
        $client->repositories()->branches($org, $repo, $branch)['commit']['sha']
    );
    echo "Done.\n";
}