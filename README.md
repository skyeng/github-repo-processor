### Обрабатываем jenkinsfile для кучи проектов сразу

##### Как установить

    $ composer install
    
Подставляем свои значения в этот файл
    
    $ cp .env.sample .env
    $ vi .env

##### Как использовать

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