#!/usr/bin/env php
<?php
require 'vendor/autoload.php';
require 'utils.php';

global $org;
$org = getenv('GITHUB_ORGANIZATION');

global $client;
$client = new \Github\Client();
$token = getenv('GITHUB_TOKEN');
$client->authenticate($token, null, Github\Client::AUTH_HTTP_TOKEN);

@$cmd = $argv[1];
switch ($cmd) {
    /**
     * args: <path_of_file_in_repo> <branch>
     */
    case 'get-contents':
        $repos = [];
        $page = 0;
        while (true) {
            $buf = $client->repositories()->org($org, ['page' => ++$page]);
            if (count($buf) === 0) {
                break;
            }
            $repos = array_merge($repos, $buf);
        }
//        // debug
//        file_put_contents(
//            'repos.json',
//            json_encode($repos, JSON_PRETTY_PRINT)
//        );

        $path = $argv[2];
        $branch = $argv[3] ?? 'master';

        $segs = explode('/', $path);
        $name = array_pop($segs);

        $dir = "/opt/app/data/$name/$branch";
        mkdir($dir, 0777, true);
        // get path's $content from $branch
        foreach ($repos as $repo) {
            if ($client->repository()->contents()->exists($org, $repo['name'], $path, "refs/heads/$branch")) {
                $content = $client->repository()->contents()->download($org, $repo['name'], $path, "refs/heads/$branch");
                file_put_contents("$dir/{$repo['name']}", $content);
                echo "+{$repo['name']}".PHP_EOL;
            } else {
                echo "No path in {$repo['name']}, $branch branch".PHP_EOL;
            }
        }

        break;
    /**
     * args: <repo> <branch>
     */
    case 'create-branch':
        $repo = $argv[2];
        $branch = $argv[3];
        createBranch($repo, $branch);
        break;
    /**
     * <path_of_file_in_repo> <branch> <message>
     */
    case 'commit-files':
        $path = $argv[2];
        $branch = $argv[3];
        $message = $argv[4];

        $segs = explode('/', $path);
        $name = array_pop($segs);

        $dir = "/opt/app/data/$name/$branch";
        foreach (scandir($dir) as $file) {
            if ($file !== '.' && $file !== '..') {
                $repo = $file;
                commitFile("$dir/$file", $path, $repo, $branch, $message);
            }
        }
        break;
    /**
     * args: <repo> <branch> <title>
     */
    case 'pull-request':
        $repo = $argv[2];
        $branch = $argv[3];
        $title = $argv[4];
        sendPullRequest($repo, $branch, $title);
        break;
    /**
     * args: <repo> <branch> <title>
     */
    case 'rename-pull-request':
        $repo = $argv[2];
        $branch = $argv[3];
        $title = $argv[4];
        renamePullRequest($repo, $branch, $title);
        break;
    /**
     * args: <repo> <branch>
     */
    case 'merge-pull-request':
        $repo = $argv[2];
        $branch = $argv[3];
        mergePullRequest($repo, $branch);
        break;
    default:
        echo "ERROR: Unknown command\n";
}

