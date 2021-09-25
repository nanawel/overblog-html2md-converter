<?php
/**
 * @param SimpleXMLElement|array $in
 * @return array|string
 */
function xmlToArray($in) {
    if (is_array($in)) {
        $out = $in;
    } else {
        $out = (array) $in;
        if (empty($out)) {
            $str = (string) $in;
            if (!empty($str)) {
                return $str;
            }
            $out = [];
        }
    }

    foreach ($out as $k => $node) {
        if (is_array($node) || $node instanceof SimpleXMLElement) {
            $out[$k] = xmlToArray($node);
        }
    }

    return $out;
}

/**
 * @param string $html
 * @return DOMDocument
 */
function htmlToDOMDocument($html) {
    static $doc;
    if (!isset($doc)) {
        $doc = new DOMDocument();
    }
    if (!$doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'))) {
        throw new RuntimeException('Could not load HTML.');
    }

    return $doc;
}

/**
 * @param string $html
 * @return string
 */
function cleanupHtmlContent(string $html): string {
    global $climate;

    // Convert titles (we don't want any H1, so start with H2)
    $html = preg_replace_callback('#<(/?)h(\d)#i', function($m) {
        $h = $m[2] + 1;
        return "<{$m[1]}h{$h}";
    }, $html);

    // Remove comments
    $html = preg_replace('/<!--.*?-->/ims', '', $html);

    // Replace <span> with <p> when direct children of <div>
    $doc = htmlToDOMDocument($html);
    /** @var DOMElement $span */
    foreach ($doc->getElementsByTagName('span') as $span) {
        if ($span->parentNode->tagName === 'div') {
            $p = $doc->createElement('p');
            $spanChildren = [];
            for ($i = 0; $i < $span->childNodes->length; $i++) {
                $spanChildren[] = $span->childNodes->item($i);
            }
            $p->append(...$spanChildren);
            if (!$span->parentNode->replaceChild($p, $span)) {
                throw new RuntimeException('Could not replace <span>.');
            }
        }
    }

    // Remove classes on <pre> and trim content
    /** @var DOMElement $pre */
    foreach ($doc->getElementsByTagName('pre') as $pre) {
        $newPre = $doc->createElement('pre');
        $newPre->appendChild($doc->createTextNode(trim($pre->textContent)));
        $pre->parentNode->replaceChild($newPre, $pre);
    }

    // Remove hardcoded dimensions on <iframe>
    /** @var DOMElement $iframe */
    foreach ($doc->getElementsByTagName('iframe') as $iframe) {
        $iframe->removeAttribute('width');
        $iframe->removeAttribute('height');
    }

    // Remove <script>
    /** @var DOMElement $script */
    foreach ($doc->getElementsByTagName('script') as $script) {
        $script->remove();
    }

    // Make sure all necessary elements are wrapped in <p>
    $foundObSections = false;
    /** @var DOMElement $node */
    foreach ($doc->getElementsByTagName('div') as $node) {
        if ($node->getAttribute('class') === 'ob-sections') {
            $foundObSections = true;

            // Iterate over <div class="ob-sections"> children (which should be <div class="ob-section">)
            for ($obSectionsChildIdx = 0; $obSectionsChildIdx < $node->childNodes->length; $obSectionsChildIdx++) {
                /** @var DOMNode $obSection */
                $obSection = $node->childNodes->item($obSectionsChildIdx);
                if ($obSection instanceof DOMText) {
                    if (wrapParagraph($doc, $node, $obSection)) {
                        $climate->whisper(sprintf('Text content wrapped successfully: %s[...]', substr($obSection->textContent, 0, 30)));
                    }
                } elseif ($obSection instanceof DOMElement) {
                    if (strpos($obSection->getAttribute('class'), 'ob-section') === false) {
                        throw new RuntimeException('Unexpected node class under ob-sections.');
                    }

                    // Iterate over <div class="ob-section..."> children
                    for ($obSectionChildIdx = 0; $obSectionChildIdx < $obSection->childNodes->length; $obSectionChildIdx++) {
                        $obSectionContentChild = $obSection->childNodes->item($obSectionChildIdx);

                        if ($obSectionContentChild instanceof DOMText) {
                            if (wrapParagraph($doc, $obSection, $obSectionContentChild)) {
                                $climate->whisper(sprintf('Text content wrapped successfully: %s[...]', substr($obSectionContentChild->textContent, 0, 30)));
                            }
                        } elseif (in_array($obSectionContentChild->tagName, ['blockquote', 'div', 'hr', 'iframe','ol', 'p', 'pre', 'table', 'ul'])) {
                            // OK, already valid
                            continue;
                        } elseif (in_array($obSectionContentChild->tagName, ['h2', 'h3', 'h4'])) {
                            $headerNode = $obSectionContentChild;
                            $newParagraph = null;
                            foreach ($headerNode->childNodes as $headerChild) {
                                // Those nodes are misplaced so move them up
                                if ($headerChild instanceof DOMElement
                                    && in_array($headerChild->tagName, ['img', 'a'])
                                ) {
                                    if (!$newParagraph) {
                                        $newParagraph = $doc->createElement('p');
                                    }
                                    $newParagraph->appendChild($headerChild);
                                    $climate->whisper(sprintf(
                                        '<%s> node moved successfully from <%s>.',
                                        $headerChild->tagName,
                                        $headerNode->tagName
                                    ));
                                }
                            }
                            if ($newParagraph) {
                                $obSection->insertBefore($newParagraph, $headerNode);
                            }
                        } else {
                            throw new RuntimeException('Unexpected ob-section child node with tag: ' . $obSectionContentChild->tagName);
                        }
                    }
                } else {
                    throw new RuntimeException('Unexpected ob-sections child node with tag: ' . $obSection->tagName);
                }
            }
        }
    }
    if (!$foundObSections) {
        throw new RuntimeException('Cannot find ob-sections node.');
    }

    // Extract <img> if needed
    /** @var DOMElement $img */
    foreach ($doc->getElementsByTagName('img') as $img) {
        $actualImgNode = $img;
        if ($img->parentNode->tagName === 'a') {
            $actualImgNode = $img->parentNode;
        }
        if (!in_array($actualImgNode->parentNode->tagName, ['p'])) {
            $newParagraph = $doc->createElement('p');
            $actualImgNode->parentNode->appendChild($newParagraph);
            $newParagraph->appendChild($actualImgNode);
        }
    }

    if (!$html = $doc->saveHTML()) {
        throw new RuntimeException('Could not save HTML.');
    }

    // Remove unwanted tags
    $html = trim(strip_tags(
        $html,
        [
            'a', 'b', 'blockquote', 'br', 'em',
            'i', 'iframe', 'img',
            'h1', 'h2', 'h3', 'h4',
            'li', 'p', 'pre', 's',
            'table', 'tbody', 'td', 'tr',
            'strong', 'ul'
        ]
    ));

    // Remove unnecessary leading spaces before tags on each line
    $html = preg_replace('/^\s+(<.*)$/m', '\1', $html);

    return $html;
}

/**
 * @param string $html
 * @return string
 */
function htmlToMarkdown(string $html):string {
    static $converter = null;
    if (!$converter) {
        $converter = new \League\HTMLToMarkdown\HtmlConverter();
        $converter->getEnvironment()->addConverter(new \League\HTMLToMarkdown\Converter\TableConverter());
    }

    return $converter->convert($html);
}

/**
 * @param string $html
 * @return string
 */
function wrapHtml(string $html): string {
    return <<<"EOHTML"
<html>
    <head>
        <meta charset="utf-8"/>
    </head>
    <body>
        $html
    </body>
</html>
EOHTML;

}

/**
 * @param DOMDocument $doc
 * @param DOMElement $parent
 * @param DOMNode $child
 * @return bool
 */
function wrapParagraph(DOMDocument $doc, DOMElement $parent, DOMNode $child) {
    if ($child instanceof DOMText && !trim($child->textContent)) {
        // Whitespaces only text node, ignore
        return false;
    }
    // Text section is not in <p>, so add a wrapper
    $p = $doc->createElement('p');
    if (!$parent->replaceChild($p, $child)) {
        throw new RuntimeException('Could not replace DOMText node.');
    }
    $p->appendChild($child);

    return true;
}

/**
 * @param array $post
 * @return string
 * @throws Exception
 */
function generateSlugWithDate(array $post): string {
    if (preg_match('#\d{4}/\d{2}/.*#i', $post['slug'])) {
        return $post['slug'];
    }
    $publishedAt = new \DateTime($post['published_at']);

    return "{$publishedAt->format('Y/m')}/{$post['slug']}";
}

/**
 * @param string $basePath
 * @param array $items
 * @throws Exception
 */
function exportBlogItems($basePath, &$items, $useDateInSlug = true) {
    global $climate;

    @mkdir($basePath, 0777, true);
    foreach ($items as &$item) {
        if (!$item['slug']) {
            continue;
        }
        $climate->info("** {$item['slug']}");

        if ($useDateInSlug) {
            $item['new_slug'] = generateSlugWithDate($item);
        } else {
            $item['new_slug'] = basename($item['slug']);
        }
        $item['content_html'] = cleanupHtmlContent($item['content']);
        $item['content_markdown'] = trim(htmlToMarkdown($item['content_html']));

        $itemDir = $item['post_dir'] = $basePath . '/' . trim($item['new_slug'], ' -/');
        @mkdir($itemDir, 0777, true);

        file_put_contents("$itemDir/index.json", json_encode($item, JSON_PRETTY_PRINT));
        file_put_contents("$itemDir/index.orig.html", wrapHtml($item['content']));
        file_put_contents("$itemDir/index.html", wrapHtml($item['content_html']));
        file_put_contents("$itemDir/index.md", $item['content_markdown']);
    }
}

/**
 * @param string $url
 * @return string
 */
function imgUrlToPath($url) {
    return preg_replace_callback('#^(.+?)/([^/]+)$#i', function($matches) {
        return sprintf(
            '%s/%s',
            preg_replace('/[^0-9a-z._-]/i', '_', $matches[1]),
            $matches[2]
        );
    }, $url);
}
