<?php
define('BASE_PATH', '.');

define('INPUT_FILE', BASE_PATH . '/export.xml');
define('EXPORT_DIR', BASE_PATH . '/export');

define('OUTPUT_JSON_FILE', EXPORT_DIR . '/export.json');
define('OUTPUT_CLEAN_JSON_FILE', EXPORT_DIR . '/export.clean.json');
define('POSTS_DIR', EXPORT_DIR . '/posts');
define('PAGES_DIR', EXPORT_DIR . '/pages');

define('IMAGES_DIR', EXPORT_DIR . '/images');
define('IMAGES_INPUT_JSON_FILE', EXPORT_DIR . '/export.clean.json');
define('IMAGES_MAPPING_FILE', EXPORT_DIR . '/images.json');
define('IMAGES_DOWNLOAD_DELAY_MIN', 1 * 10e5);
define('IMAGES_DOWNLOAD_DELAY_MAX', 2 * 10e5);
define('IMAGES_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

define('HTMLY_INPUT_JSON_FILE', EXPORT_DIR . '/export.clean.json');
define('HTMLY_CONTENT_DIR', BASE_PATH . '/to-htmly');
define('HTMLY_TAGS_FILE', HTMLY_CONTENT_DIR . '/data/tags.lang');
define('HTMLY_IMAGES_DIR', HTMLY_CONTENT_DIR . '/images');
define('HTMLY_POSTS_DIR_PATTERN', HTMLY_CONTENT_DIR . '/{username}/blog/uncategorized/post');
define('HTMLY_PAGES_DIR', HTMLY_CONTENT_DIR . '/static');
define('HTMLY_EXPORTED_METADATA', [
    'created_at' => 'created_at',
    'published_at' => 'published_at',
    'modified_at' => 'modified_at',
    'slug' => 'slug'
]);
define('HTMLY_BASE_URL', '/'); // Must end with slash
define('HTMLY_IMAGES_BASE_URL', HTMLY_BASE_URL . 'content/images/'); // Must end with slash

define('OB_STATUS_DRAFT', 1);
define('OB_STATUS_PUBLISHED', 2);
