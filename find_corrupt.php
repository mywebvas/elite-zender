<?php
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator('resources/views'));
foreach ($files as $file) {
    if ($file->isFile() && strpos($file->getFilename(), '.blade.php') !== false) {
        $content = file_get_contents($file->getPathname());
        if (strpos($content, chr(239).chr(191).chr(189)) !== false) {
            echo $file->getPathname() . PHP_EOL;
        }
    }
}

