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

function sendPullRequest(string $repo, string $branch, String $message){
    global $client;
    global $org;

    echo "Sending pull request of $branch in $repo ... ";
    $client->pullRequests()->create($org, $repo, [
        'base'  => 'master',
        'head'  => $branch,
        'title' => $branch,
        'body'  => $message
    ]);
    echo "Done.\n";
}