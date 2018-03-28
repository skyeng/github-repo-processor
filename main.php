#!/usr/bin/env php
<?php
require 'vendor/autoload.php';
require 'prepare.php';

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
        // debug
        file_put_contents(
            'repos.json',
            json_encode($repos, JSON_PRETTY_PRINT)
        );

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
                upload("$dir/$file", $path, $repo, $branch, $message);
            }
        }
        break;
    /**
     * args: <repo> <branch> <message>
     */
    case 'pull-request':
        $repo = $argv[2];
        $branch = $argv[3];
        $message = $argv[4];
        createPullRequest($repo, $branch, $message);
        break;
//    default:
//        echo "Утилита для обработки Jenkinsfile`ов во всех проектах\n";
//        echo "./main.php [download-originals|upload-files|create-branch]\n";
//        echo "\tdownload-originals\t\t\tЗакачивает Jenkinsfile`ы всех доступных репозиториев организации в папку ./jenkinsfiles/master\n";
//        echo "\tupload-files [all|<repo>] <branch>\tЗагружает конкретный файл или все файлы в соответствующие репозитории в указанную ветку\n";
//        echo "\tcreate-branch [all|<repo>] <branch>\tСоздаем новую ветку от мастера\n";
//        echo "\tpull-request [all|<repo>] <branch>\tСоздаем pull-request\n";
//        echo "*all - соответствует всем репозиториям, перечисленным в папке branch\n";
//        break;
}

function upload(string $localpath, string $repopath, string $repo, string $branch, String $message){
    global $client;
    global $org;

    echo 'Updating '.$repo.PHP_EOL;
    $client->repository()->contents()->update(
        $org,
        $repo,
        $repopath,
        file_get_contents($localpath),
        "[$branch] $message",
        $client->repository()->contents()->show($org, $repo, $repopath, "refs/heads/$branch")['sha'],
        $branch
    );
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

function createPullRequest(string $repo, string $branch, String $message){
    global $client;
    global $org;

    echo "Creating pull request of $branch in $repo ... ";
    $client->pullRequests()->create($org, $repo, [
        'base'  => 'master',
        'head'  => $branch,
        'title' => "$branch",
        'body'  => "$message"
    ]);
    echo "Done.\n";
}