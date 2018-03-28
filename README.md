### Обрабатываем jenkinsfile для кучи проектов сразу

##### Настройка

Подставляем свои значения в файл `.env.sample`
    
    $ cp .env.sample .env
    $ vi .env

##### Как использовать

Скачивание файла

    main.php get-contents <path_of_file_in_repo> <branch(default=master)>

Пример:

    $ docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine ./main.php get-contents deploy/capistrano/config/deploy.rb

Создание бранчей

    main.php create-branch <repo> <branch>
    
Пример:

    docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine sh -c "ls data/deploy.rb/INFRA-1921 | xargs -I % ./main.php create-branch % INFRA-1921"
    
Закоммитить файл

    main.php commit-files <path_of_file_in_repo> <branch>
    
Пример:

    docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        -e "COMMIT_MESSAGE=own COMPOSER_HOME for each project" \
        php:7.1-cli-alpine ./main.php commit-files deploy/capistrano/config/deploy.rb INFRA-1921
