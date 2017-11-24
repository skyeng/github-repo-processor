### Обрабатываем jenkinsfile для кучи проектов сразу

Чтобы развернуть:

    $ composer install

Примеры использования

    $ export GITHUB_ORGANIZATION=xxx
    $ export GITHUB_TOKEN=xxx
    $ ./main.php download-originals
    
Получаем в папку `jenkinsfile/master` все jenkinsfile'ы по именам репозиториев.
Далее, копируем файлы в `jenkinsfile/branch` и изменяем, как нам нужно.

    $ ./main.php create-branch all ISSUE-123
    
Создаётся ветка ISSUE-123 для всех реп, файлы, относящиеся к которым
есть в папке `jenkinsfile/branch`.

    $ ./main.php upload-files all ISSUE-123
    
Коммитит файлы из папки `jenkinsfile/branch` в соответствующие репы в ветки ISSUE-123.
