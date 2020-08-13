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

    case 'rm-files':
        $path = $argv[2];
        $branch = $argv[3];
        $message = $argv[4];

        $files[] = $path;

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

                try {
                    $sha = $client->repository()->contents()->show($org, $repoName, $path)['sha'] ?? null;
                    if ($sha === null) {
                        echo "Skipped {$path} in {$repoName}. Sha does not exists " . PHP_EOL;
                        continue;
                    }

                    $client->repository()->contents()->rm(
                        $org,
                        $repoName,
                        $path,
                        $message,
                        $sha,
                        $branch
                    );
                } catch (\Exception $exception) {
                    echo "Cannot remove {$path} in {$repoName} " . PHP_EOL;
                }
            }
        }

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
                    try {
                        commitFile($filePath, $repoPath, $repoName, $branch, $message, false);
                    } catch (\Exception $exception) {
                        echo "Skipped {$repoPath} in {$repoName}. Maybe {$repoPath} already exists? " . PHP_EOL;
                    }
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

    case 'pull-request-all-repo':
        $branch = $argv[2];
        $message = $argv[3];

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
            try {
                $result = sendPullRequest($repoName, $branch, $message);
                echo 'URL: ' . $result['html_url'] . PHP_EOL;
                echo 'Diff: ' . $result['diff_url'] . PHP_EOL;
            } catch (\Github\Exception\ValidationFailedException $e) {
                echo "Skipped {$repoName}. Maybe PR already exists? " . PHP_EOL;
            } catch (\Github\Exception\RuntimeException $e) {
                echo "Skipped {$repoName}. Repository was archived " . PHP_EOL;
            }
        }

        break;

    case 'merge-all-pull-requests':
        $branch = $argv[2];

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

        $count = 0;
        foreach ($repos as $repo) {
            $repoName = $repo['name'] ?? null;
            try {
                mergePullRequest($repoName, $branch);
                $count++;
            } catch (\Exception $e) {
                echo "Skipped {$repoName}" . PHP_EOL;
            }
        }
        echo 'Total processed: ' . $count;
        break;

    case 'protect-branch':
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
            continue; // stop

            $repoName = $repo['name'] ?? null;
            try {
                $params = [
                    'Commit Message Lint',
                ];
                $protection = $client->api('repo')->protection()->addStatusChecksContexts($org, $repoName, 'master', $params);
                echo "Requiring checks for {$repoName}... Done " . PHP_EOL;
            } catch (\Exception $e) {
                try {
                    $params = [
                        'required_status_checks' => [
                            'strict' => true,
                            'contexts' => [
                                'Commit Message Lint',
                            ],
                        ],
                        'required_pull_request_reviews' => null,
                        'enforce_admins' => true,
                        'restrictions' => null,
                    ];
                    $protection = $client->api('repo')->protection()->update($org, $repoName, 'master', $params);
                    echo "Protecting master and requiring checks for {$repoName}... Done " . PHP_EOL;
                } catch (\Exception $e) {
                    echo "Skipped {$repoName}" . PHP_EOL;
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
    default:
        echo "ERROR: Unknown command\n";
}

