#!/bin/env php
<?php
declare(strict_types=1);
require 'vendor/autoload.php';

$climate = new League\CLImate\CLImate;

set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // error was suppressed with the @-operator
    if (0 === error_reporting()) {
        return false;
    }

    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

$climate = new League\CLImate\CLImate;

$data = json_decode(file_get_contents(HTMLY_INPUT_JSON_FILE), true);
$outputArray = [];

$allTags = [];
$imagesToCopy = [];
$linksMapping = [];

/**
 * @param array $post
 * @throws Exception
 */
function htmlyExportPost(array $post) {
    global $allTags;

    if ($post['status'] != OB_STATUS_PUBLISHED) {
        return;
    }

    $mdContent = htmlyGetMarkdownContent($post);

    // Update $allTags for later
    $allTags = array_merge($allTags, explode(',', $post['tags']));

    $mdPath = str_replace('{username}', strtolower($post['author']), HTMLY_POSTS_DIR_PATTERN)
        . '/' . htmlyGetPostFilename($post);
    if (!is_dir(dirname($mdPath))) {
        mkdir(dirname($mdPath), 0777, true);
    }
    file_put_contents($mdPath, $mdContent);
}

/**
 * @param array $post
 * @return string
 * @throws Exception
 */
function htmlyGetPostFilename(array $post) {
    $publishedAt = (new DateTime($post['published_at']))->format('Y-m-d-H-i-s');
    $tags = $post['tags'];
    $slug = fixInternalUrlSuffix(basename($post['slug']));

    return sprintf('%s_%s_%s.md', $publishedAt, $tags, $slug);
}

/**
 * @param array $page
 */
function htmlyExportPage(array $page) {
    global $allTags;

    if ($page['status'] != OB_STATUS_PUBLISHED) {
        return;
    }

    $mdContent = htmlyGetMarkdownContent($page);

    // Update $allTags for later
    $allTags = array_merge($allTags, explode(',', $page['tags']));

    $mdPath = HTMLY_PAGES_DIR . '/' . htmlyGetPageFilename($page);
    if (!is_dir(dirname($mdPath))) {
        mkdir(dirname($mdPath), 0777, true);
    }
    file_put_contents($mdPath, $mdContent);
}

/**
 * @param array $page
 * @return string
 * @throws Exception
 */
function htmlyGetPageFilename(array $page) {
    $slug = fixInternalUrlSuffix(basename($page['slug']));

    return sprintf('%s.md', $slug);
}

/**
 * @param array $item
 * @return string
 */
function htmlyGetMarkdownContent(array $item) {
    $mdContentTemplate = <<<EOMD
<!--t %s t-->
<!--tag %s tag-->
%s

%s
EOMD;

    $mdMetadata = [];
    foreach (HTMLY_EXPORTED_METADATA as $metadata) {
        if (isset($item[$metadata])) {
            $mdMetadata[] = "<!-- $metadata: {$item[$metadata]} -->";
        }
    }

    return sprintf(
        $mdContentTemplate,
        $item['title'],
        $item['tags'],
        implode("\n", $mdMetadata),
        $item['content_markdown_htmly'],
    );
}

/**
 * @return void
 */
function htmlyUpdateTags() {
    global $allTags;

    $tags = [];
    if (is_file(HTMLY_TAGS_FILE)) {
        $tags = unserialize(file_get_contents(HTMLY_TAGS_FILE));
    }

    foreach ($allTags as $t) {
        $tags[$t] = $t;
    }

    if (!is_dir(dirname(HTMLY_TAGS_FILE))) {
        mkdir(dirname(HTMLY_TAGS_FILE), 0777, true);
    }
    file_put_contents(HTMLY_TAGS_FILE, serialize($tags));
}

function htmlyCleanupContent(array &$item) {
    global $climate, $imagesToCopy, $linksMapping;

    if (!isset($item['content_html'])) {
        return;
    }

    try {
        $doc = htmlToDOMDocument($item['content_html']);
    } catch (\Throwable $e) {
        $climate->to('error')->red("{$item['title']}: {$e->getMessage()}");
        throw $e;
    }

    // IMAGES
    /** @var DOMElement $img */
    foreach ($doc->getElementsByTagName('img') as $img) {
        $parentLink = null;
        if ($img->parentNode->tagName === 'a'
            && $img->parentNode->getAttribute('href') == $img->getAttribute('src')
        ) {
            $parentLink = $img->parentNode;
        }

        $originalUrl = $img->getAttribute('src');
        $newImageFilename = uniqid() . '_' . str_replace('%2F', '', basename($originalUrl));
        $newImagePath = $newImageFilename;
        $newUrl = HTMLY_IMAGES_BASE_URL . $newImageFilename;

        $img->setAttribute('src', $newUrl);
        if ($parentLink) {
            $parentLink->setAttribute('href', $newUrl);
        }

        $imagesToCopy[$originalUrl] = $newImagePath;
        $climate->whisper("IMAGE: $originalUrl => $newUrl");
    }

    // LINKS
    /** @var DOMElement $a */
    foreach ($doc->getElementsByTagName('a') as $a) {
        if (preg_match('#^https?://(www\.)?lanterne-rouge(\.over-blog\.org|\.info)/(?P<path>.*)$#i', $a->getAttribute('href'), $m)) {
            $originalUrl = $a->getAttribute('href');
            $originalSlug = $slug = $m['path'];

            if (isset($linksMapping[$originalSlug])) {
                $slug = $linksMapping[$originalSlug];
            }

            $newUrl = HTMLY_BASE_URL . fixInternalUrlSuffix($slug);
            $a->setAttribute('href', $newUrl);
            $climate->whisper("LINK: $originalUrl => $newUrl");
        }
    }

    $item['content_markdown_htmly'] = trim(htmlToMarkdown($doc->saveHTML()));
}

/**
 * @return void
 */
function htmlyCopyImages() {
    global $imagesToCopy;

    $imagesData = json_decode(file_get_contents(IMAGES_MAPPING_FILE), true);

    if (!is_dir(HTMLY_IMAGES_DIR)) {
        mkdir(HTMLY_IMAGES_DIR, 0777, true);
    }
    foreach ($imagesToCopy as $sourceUrl => $targetPath) {
        if (!isset($imagesData[$sourceUrl])) {
            throw new RuntimeException('Cannot find image mapping data for ' . $sourceUrl);
        }
        copy(IMAGES_DIR . '/' . $imagesData[$sourceUrl]['path'], HTMLY_IMAGES_DIR . '/' . $targetPath);
    }
}

/**
 * @param string $url
 * @return string
 */
function fixInternalUrlSuffix($url) {
    // Remove suffix that HTMLy cannot handle
    if (str_ends_with($url, '.html')) {
        $url = substr($url, 0, -5);
    }

    return $url;
}

// ########################################################################

$climate->cyan('POSTS');

// Normalize posts links (=> {Y}/{m}/{slug})
foreach ($data['posts']['post'] as $post) {
    if (!isset($post['new_slug'])) {
        continue;
    }
    $linksMapping[$post['slug']] = $post['new_slug'];
}

foreach ($data['posts']['post'] as $post) {
    $climate->bold()->info($post['title']);
    htmlyCleanupContent($post);
    htmlyExportPost($post);
}
$climate->br();

$climate->cyan('PAGES');

foreach ($data['pages']['page'] as $page) {
    $climate->bold()->info($page['title']);
    htmlyCleanupContent($page);
    htmlyExportPage($page);
}
$climate->br();

htmlyUpdateTags();
htmlyCopyImages();

$climate->green('Finished.');
