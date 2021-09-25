#!/bin/env php
<?php
declare(strict_types=1);
require 'vendor/autoload.php';

$climate = new League\CLImate\CLImate;

$inputXml = simplexml_load_file(INPUT_FILE);
$inputArray = xmlToArray($inputXml);
$inputJson = json_encode($inputArray, JSON_PRETTY_PRINT);

file_put_contents(OUTPUT_JSON_FILE, $inputJson);
//$climate->json($inputArray);

$climate->cyan('POSTS');
exportBlogItems(POSTS_DIR, $inputArray['posts']['post']);
$climate->br();

$climate->cyan('PAGES');
exportBlogItems(PAGES_DIR, $inputArray['pages']['page'], false);
$climate->br();

$inputJson = json_encode($inputArray, JSON_PRETTY_PRINT);
file_put_contents(OUTPUT_CLEAN_JSON_FILE, $inputJson);
//$climate->json($inputArray);

