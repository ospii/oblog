<?php
require_once (__DIR__ . '/../vendor/autoload.php');
ini_set('display_errors', 1);

echo PHP_EOL . 'Generating posts to ' . __DIR__ . '/document_root';

if (php_sapi_name() === 'cli') {
    $blog = new \Oblog\Blog;
    $blog->setSourcePath(__DIR__ .'/source')
        ->setOutputPath(__DIR__ .'/document_root')
        ->setTemplatePath(__DIR__ . '/template')
        ->setBaseUrl('http://example.com')
        ->setName('Example Blog')
        ->setAuthor('John Example', 'john@example.org')
        ->generateStaticPosts();
}

echo PHP_EOL . "Done" . PHP_EOL;
