<?php
function fixDir($dir) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($files as $file) {
        if ($file->isFile()) {
            $content = file_get_contents($file->getPathname());
            if (!mb_check_encoding($content, 'UTF-8')) {
                echo 'Fixing: ' . $file->getPathname() . PHP_EOL;
                $utf8 = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
                file_put_contents($file->getPathname(), $utf8);
            }
        }
    }
}
fixDir('resources');
fixDir('routes');

