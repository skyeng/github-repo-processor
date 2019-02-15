# Инструмент для обрабатки одинаковых файлов в нескольких репозиториях для "организации" на Github

### Настройка

Подставляем свои значения в файл `.env.sample`
    
    $ cp .env.sample .env
    $ vi .env
    
необходимо вписать организацию на Github, токен (получить можно здесь https://github.com/settings/tokens) и коммитера.

Далее, можно скачать файл из всех репозиториев, внести правку во все экземпляры, закоммитить правки в отдельную ветки и создать PRы.

### Как использовать

Скачивание файла

    main.php get-contents <path_of_file_in_repo> <branch(default=master)>

Пример:

    $ docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine \
        ./main.php get-contents deploy/capistrano/config/deploy.rb

Создание бранчей

    main.php create-branch <repo> <branch>
    
Пример:

    docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine \
        sh -c "ls data/deploy.rb/INFRA-1921 | xargs -I % ./main.php create-branch % INFRA-1921"
    
Закоммитить файлы

    main.php commit-files <path_of_file_in_repo> <branch> <message>
    
Пример:

    docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine \
        ./main.php commit-files deploy/capistrano/config/deploy.rb INFRA-1921 "[INFRA-1921] own COMPOSER_HOME for each project"
    
Отправить pull-реквест

    main.php pull-request <repo> <branch> <message>
    
Пример:

    docker run --rm -ti \
        -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
        --env-file .env \
        -w="/opt/app" \
        php:7.1-cli-alpine \
        sh -c "ls data/deploy.rb/INFRA-1921 | xargs -I % ./main.php pull-request % INFRA-1921 'own COMPOSER_HOME for each project'"
