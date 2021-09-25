#!/bin/env php
<?php
declare(strict_types=1);
require 'vendor/autoload.php';

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$climate = new League\CLImate\CLImate;

$postsArray = json_decode(file_get_contents(IMAGES_INPUT_JSON_FILE), true)['posts']['post'];
$outputArray = [];

/** @var \League\CLImate\TerminalObject\Dynamic\Progress $progress */
$progress = $climate->progress()->total(count($postsArray));
$dom = new \PHPHtmlParser\Dom(new \PHPHtmlParser\Dom\Parser());

$err = [];
foreach ($postsArray as &$post) {
    $progress->advance(1, $post['title']);

    if (!$post['slug']) {
        continue;
    }

    $dom->loadStr($post['content_html']);
    foreach ($dom->find('img') as $imgElement) {
        $imgSrc = $imgElement->src;
        try {
            $imgPath = imgUrlToPath($imgSrc);

            $climate->out("$imgSrc => $imgPath");

            if (!isset($outputArray[$imgSrc])) {
                $outputArray[$imgSrc] = [
                    'path' => $imgPath,
                    'references' => []
                ];
            }
            $outputArray[$imgSrc]['references'][] = $post['new_slug'];

            $imgFullPath = IMAGES_DIR . "/$imgPath";
            if (!is_file($imgFullPath) || filesize($imgFullPath) === 0) {
                $imgDir = dirname($imgFullPath);
                if (!is_dir($imgDir)) {
                    mkdir($imgDir, 0777, true);
                }
                file_put_contents($imgFullPath, file_get_contents($imgSrc));

                $delay = rand((int) IMAGES_DOWNLOAD_DELAY_MIN, (int) IMAGES_DOWNLOAD_DELAY_MAX);
                usleep($delay);
            }
        }
        catch (\Throwable $e) {
            $err[] = [
                'image_url' => $imgSrc,
                'message' => $e->getMessage()
            ];
            $climate->error($e->getMessage());
        }
    }
}
$climate->br();

file_put_contents(IMAGES_MAPPING_FILE, json_encode($outputArray, JSON_PRETTY_PRINT));

if ($err) {
    $climate->error(sprintf("%d errors found. You might want to run the script again.", count($err)));
    $climate->red()->table($err);
} else {
    $climate->green("Finished. No error found :)");
}
