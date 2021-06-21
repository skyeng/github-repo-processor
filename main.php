#!/usr/bin/env php
<?php

use Http\Client\Exception;

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
    case 'get-repos':
        $repos = [];
        $page = 0;
        while (true) {
            $buf = $client->repositories()->org($org, ['page' => ++$page]);
            if (count($buf) === 0) {
                break;
            }
            $repos = array_merge($repos, $buf);
        }

        $minRepos = [];
        foreach ($repos as $repo) {
            if (!$repo['archived']) {
//                $minRepos [] = [
//                    'html_url' => $repo['html_url'],
//                    'full_name' => $repo['full_name'],
//                    'archived' => $repo['archived'],
//                ];
                $minRepos [] = "${repo['full_name']}\t${repo['html_url']}\n";
            }
        }

        file_put_contents(
            'repos.txt',
            $minRepos
        );

        break;
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
     * args: <repo> <branch>
     */
    case 'delete-branch':
        $repo = $argv[2];
        $branch = $argv[3];
        deleteBranch($repo, $branch);
        break;

    case 'add-files':
        $path = $argv[2];
        $repoPath = $argv[3];
        $branch = $argv[4];
        $message = $argv[5];
        $forcePullRequest = $argv[6] ?? null;

        $isDirectory = false;

        $files = [];
        if (is_dir($path)) {
            $files = getDirectoryFiles($path);
            $isDirectory = true;
        } else {
            $files[] = $path;
        }

        $repos = [];
        $page = 0;
        $continue = true;
        do {
            $batch = $client->repositories()->org($org, ['page' => ++$page]);
            if (!empty($batch)) {
                $repos = array_merge($repos, $batch);
            } else {
                $continue = false;
            }
        } while ($continue);

        foreach ($repos as $repo) {
            $repoName = $repo['name'] ?? null;
            foreach ($files as $filePath) {
                try {
                    $repoBranch = $client->repositories()->branches($org, $repoName, $branch);
                } catch (Exception $exception) {
                    try {
                        createBranch($repoName, $branch);
                    } catch (\Github\Exception\RuntimeException $e) {
                        echo "Repository {$repoName} was archived " . PHP_EOL;
                    }
                }

                if ($isDirectory) {
                    $repoPath = str_replace($path, '', $filePath); // crop data/untracked
                }

                try {
                    commitFile($filePath, $repoPath, $repoName, $branch, $message, true);
                } catch (\Exception $exception) {
                    echo "Skipped {$repoPath} in {$repoName}. Maybe {$repoPath} already exists? " . PHP_EOL;
                }
            }
        }

        if ($forcePullRequest !== null) {
            foreach ($repos as $repo) {
                $repoName = $repo['name'] ?? null;
                try {
                    sendPullRequest($repoName, $branch, $message);
                } catch (\Github\Exception\ValidationFailedException $e) {
                    echo "Skipped {$repoName}. Maybe PR already exists? " . PHP_EOL;
                } catch (\Github\Exception\RuntimeException $e) {
                    echo "Skipped {$repoName}. Repository was archived " . PHP_EOL;
                }
            }
        }

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
//    /**
//     * args: <query>
//     */
//    case 'search':
////        $matches = [];
////        $page = 0;
////        while (true) {
////            ++$page;
////
////            if (file_exists("matches{$page}.json")) {
////
////                echo "page: {$page} skipping" . PHP_EOL;
////
////            } else {
////                echo "page: {$page} processing ... ";
////                sleep(60);
////
////                $buf = $client->search()->code($argv[2], 'updated', 'desc', $page)['items'];
////                if (count($buf) === 0) {
////                    break;
////                }
////
////                file_put_contents(
////                    "matches{$page}.json",
////                    json_encode($buf, JSON_PRETTY_PRINT)
////                );
////
////                echo "complete!" . PHP_EOL;
////            }
////        }
//
//        $matches = [];
//        for ($i=1; $i<=10; $i++) {
//            $matches = array_merge($matches, json_decode(file_get_contents("matches$i.json"), true));
//        }
//
//        $dir = "/opt/app/data/search_result";
//        mkdir($dir, 0777, true);
//
////        $matches = json_decode(file_get_contents('matches.json'), true);
//        foreach ($matches as $match) {
//            $downloadToFile = "$dir/{$match['repository']['name']}.{$match['name']}";
//            if (!file_exists($downloadToFile))
//            {
//                $content = $client->repository()->contents()->download($org, $match['repository']['name'], $match['path'], "refs/heads/master");
//                file_put_contents($downloadToFile, $content);
//                echo "+{$match['repository']['name']}" . PHP_EOL;
//            }
//        }
//
//        break;
//
    default:
        echo "ERROR: Unknown command\n";
}

