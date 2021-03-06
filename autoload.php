<?php

spl_autoload_register(function($class) {
    if (0 === strpos($class, 'Artax\\')) {
        $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
        $file = __DIR__ . "/src/$class.php";
        if (file_exists($file)) {
            require $file;
        }
    }
});

require __DIR__ . '/vendor/Alert/src/bootstrap.php';
require __DIR__ . '/vendor/Addr/src/bootstrap.php';
