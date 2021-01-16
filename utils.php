<?php

function commitFile(string $localpath, string $repopath, string $repo, string $branch, String $message, $new = false){
    global $client;
    global $org;

    echo "Commiting $repopath to $repo ... ";
    if ($new) {
        $client->repository()->contents()->create(
            $org,
            $repo,
            $repopath,
            file_get_contents($localpath),
            $message,
            $branch
        );
    } else {
        $client->repository()->contents()->update(
            $org,
            $repo,
            $repopath,
            file_get_contents($localpath),
            $message,
            $client->repository()->contents()->show($org, $repo, $repopath, "refs/heads/$branch")['sha'],
            $branch
        );
    }
    echo "Done.\n";
}

function createBranch(string $repo, string $branch){
    global $client;
    global $org;

    echo "Creating branch $branch in $repo ... ";
    try {
        $master = $client->repositories()->branches($org, $repo, 'master');
        $client->git()->references()->create($org, $repo, [
            'ref' => "refs/heads/$branch",
            'sha' => $master['commit']['sha'],
        ]);
        echo "Done\n";
    } catch (\Github\Exception\RuntimeException $e) {
        if ($e->getMessage() === 'Reference already exists') {
            echo "Branch already exists, skipping\n";
        } elseif ($e->getMessage() === 'Repository was archived so is read-only.') {
            echo "Repository was archived so is read-only, skipping\n";
        } elseif ($e->getMessage() === 'Not Found') {
            echo "Repository was not found (lack of permissions?), skipping\n";
        } else {
            echo "Unknown error, message was: '{$e->getMessage()}'\n";
            throw $e;
        }
    }
}

function deleteBranch(string $repo, string $branch){
    global $client;
    global $org;

    echo "Deleting branch $branch in $repo ... ";
    $client->git()->references()->remove($org, $repo, "heads/$branch");
    echo "Done\n";
}

function sendPullRequest(string $repo, string $branch, string $title){
    global $client;
    global $org;

    echo "Sending pull request of $branch in $repo ... ";
    $client->pullRequests()->create($org, $repo, [
        'base'  => 'master',
        'head'  => $branch,
        'title' => $title,
        'body'  => file_get_contents('body.txt'),
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

function getDirectoryFiles(string $path): array
{
    if (!is_dir($path)) {
        return [];
    }

    $files = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
    /** @var SplFileInfo $index */
    foreach ($iterator as $index) {
        if ($index->isDir()) {
            continue;
        }

        $files[] = $index->getPathname();
    }

    return $files;
}
