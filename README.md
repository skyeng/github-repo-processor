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

Перед использованием берем переменные окружения и экспортируем их:

    $ source .env
    
Получаем в папку `jenkinsfile/master` все jenkinsfile'ы по именам репозиториев:

    $ ./main.php download-originals
    
Далее, копируем файлы в `jenkinsfile/branch` и изменяем, как нам нужно.
Создаём ветку ISSUE-123 для всех реп, файлы, относящиеся к которым
есть в папке `jenkinsfile/branch`:

    $ ./main.php create-branch all ISSUE-123
    
Коммитим файлы из папки `jenkinsfile/branch` в соответствующие репы в ветки ISSUE-123:

    $ ./main.php upload-files all ISSUE-123
    
Создаем pull request для данных веток:

    $ ./main.php pull-request all ISSUE-123