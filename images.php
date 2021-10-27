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

/**
 * @param string $imgUrl
 * @param array $items
 */
function processImage($imgUrl, $item) {
    global $outputArray, $climate, $err;

    try {
        $imgPath = imgUrlToPath($imgUrl);

        $climate->out("$imgUrl => $imgPath");

        if (!isset($outputArray[$imgUrl])) {
            $outputArray[$imgUrl] = [
                'path' => $imgPath,
                'references' => []
            ];
        }
        if (!in_array($item['new_slug'], $outputArray[$imgUrl]['references'])) {
            $outputArray[$imgUrl]['references'][] = $item['new_slug'];
        }

        $imgFullPath = IMAGES_DIR . "/$imgPath";
        if (!is_file($imgFullPath) || filesize($imgFullPath) === 0) {
            $imgDir = dirname($imgFullPath);
            if (!is_dir($imgDir)) {
                mkdir($imgDir, 0777, true);
            }
            file_put_contents($imgFullPath, file_get_contents($imgUrl));

            $delay = rand((int) IMAGES_DOWNLOAD_DELAY_MIN, (int) IMAGES_DOWNLOAD_DELAY_MAX);
            usleep($delay);
        }
    }
    catch (\Throwable $e) {
        $err[] = [
            'image_url' => $imgUrl,
            'message' => $e->getMessage()
        ];
        $climate->error($e->getMessage());
    }
}

$climate = new League\CLImate\CLImate;

$postsArray = json_decode(file_get_contents(IMAGES_INPUT_JSON_FILE), true)['posts']['post'];
$outputArray = [];
$err = [];

/** @var \League\CLImate\TerminalObject\Dynamic\Progress $progress */
$progress = $climate->progress()->total(count($postsArray));
$dom = new \PHPHtmlParser\Dom(new \PHPHtmlParser\Dom\Parser());

foreach ($postsArray as $post) {
    $progress->advance(1, $post['title']);

    if (!$post['slug']) {
        continue;
    }

    $dom->loadStr($post['content_html']);
    /** @var \PHPHtmlParser\Dom\Node\HtmlNode $imgElement */
    foreach ($dom->find('img') as $imgElement) {
        $imgSrc = $imgElement->src;
        processImage($imgSrc, $post);

        if ($imgElement->getParent()
            && $imgElement->getParent()->getTag()->name() === 'a'
            && in_array(strtolower(pathinfo($imgElement->getParent()->getAttribute('href'), PATHINFO_EXTENSION)), IMAGES_EXTENSIONS)
        ) {
            processImage($imgElement->getParent()->getAttribute('href'), $post);
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
