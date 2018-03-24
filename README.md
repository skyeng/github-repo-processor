### Обрабатываем jenkinsfile для кучи проектов сразу

##### Настройка

Подставляем свои значения в файл `.env.sample`
    
    $ cp .env.sample .env
    $ vi .env

##### Как использовать

docker run --rm -ti \
    -v /home/akovytin/IdeaProjects/github-repo-processor:/opt/app \
    --env-file .env \
    php:7.1-cli-alpine /opt/app/main.php get-contents Jenkinsfile
