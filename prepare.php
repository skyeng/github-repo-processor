<?php
if (!is_dir('jenkinsfiles'))
    mkdir('jenkinsfiles');
if (!is_dir('jenkinsfiles/master'))
    mkdir('jenkinsfiles/master');
if (!is_dir('jenkinsfiles/branch'))
    mkdir('jenkinsfiles/branch');