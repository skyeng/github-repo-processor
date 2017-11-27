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
     *
     */
    case 'download-originals':
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

        // get Jenkinsfile from master
        foreach ($repos as $repo) {
            if ($client->repository()->contents()->exists($org, $repo['name'], 'Jenkinsfile')) {
                $jenkinsFileContents = $client->repository()->contents()->download($org, $repo['name'], 'Jenkinsfile');
                file_put_contents('jenkinsfiles/master/'.$repo['name'], $jenkinsFileContents);
                echo $repo['name'].PHP_EOL;
            }
        }

        break;
    /**
     *
     */
    case 'upload-files':
        $repo = $argv[2];
        $branch = $argv[3];

        if ($repo === 'all') {
            foreach (scandir("jenkinsfiles/branch") as $file) {
                if ($file !== '.' && $file !== '..') {
                    $repo = $file;
                    upload($file, $repo, $branch);
                }
            }
        } else {
            $file = $repo;
            upload($file, $repo, $branch);
        }
        break;
    /**
     *
     */
    case 'create-branch':
        $repo = $argv[2];
        $branch = $argv[3];

        if ($repo === 'all') {
            foreach (scandir("jenkinsfiles/branch") as $file) {
                if ($file !== '.' && $file !== '..') {
                    $repo = $file;
                    createBranch($repo, $branch);
                }
            }
        } else {
            createBranch($repo, $branch);
        }
        break;
    /**
     *
     */
    case 'pull-request':
        $repo = $argv[2];
        $branch = $argv[3];

        if ($repo === 'all') {
            foreach (scandir("jenkinsfiles/branch") as $file) {
                if ($file !== '.' && $file !== '..') {
                    $repo = $file;
                    createPullRequest($repo, $branch);
                }
            }
        } else {
            createPullRequest($repo, $branch);
        }
        break;
    default:
        echo "Утилита для обработки Jenkinsfile`ов во всех проектах\n";
        echo "./main.php [download-originals|upload-files|create-branch]\n";
        echo "\tdownload-originals\t\t\tЗакачивает Jenkinsfile`ы всех доступных репозиториев организации в папку ./jenkinsfiles/master\n";
        echo "\tupload-files [all|<repo>] <branch>\tЗагружает конкретный файл или все файлы в соответствующие репозитории в указанную ветку\n";
        echo "\tcreate-branch [all|<repo>] <branch>\tСоздаем новую ветку от мастера\n";
        echo "\tpull-request [all|<repo>] <branch>\tСоздаем pull-request\n";
        echo "*all - соответствует всем репозиториям, перечисленным в папке branch\n";
        break;
}

function upload(string $file, string $repo, string $branch){
    global $client;
    global $org;

    echo 'Updating '.$repo.PHP_EOL;
    $client->repository()->contents()->update(
        $org,
        $repo,
        'Jenkinsfile',
        file_get_contents("jenkinsfiles/branch/$file"),
        "[$branch] Update Jenkinsfile for new version of shared library",
        $client->repository()->contents()->show($org, $repo, 'Jenkinsfile', "refs/heads/$branch")['sha'],
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

function createPullRequest(string $repo, string $branch){
    global $client;
    global $org;

    echo "Creating pull request of $branch in $repo ... ";
    $client->pullRequests()->create($org, $repo, [
        'base'  => 'master',
        'head'  => $branch,
        'title' => "[$branch] Jenkinsfile change",
        'body'  => 'Jenkinsfile change'
    ]);
    echo "Done.\n";
}