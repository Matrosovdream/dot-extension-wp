<?php

function parseBinCodes() {

    $html = file_get_contents(__DIR__ . '/codes.php');

    if ($html === false) {
        die('Cannot read HTML file');
    }

    // 2. Prepare DOM
    $dom = new DOMDocument();

    // Suppress warnings due to broken HTML
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    // 3. Use XPath to get the first column of each row
    $xpath = new DOMXPath($dom);

    // All <tr> inside <tbody>
    $rows = $xpath->query('//table//tbody//tr');

    $bins = [];

    foreach ($rows as $row) {
        // First <td> in the row
        $firstTd = $xpath->query('td[1]', $row)->item(0);
        if (!$firstTd) {
            continue;
        }

        // Prefer <a> text inside <td>, fallback to full text
        $link = $xpath->query('.//a', $firstTd)->item(0);
        $text = $link ? $link->textContent : $firstTd->textContent;

        // Extract digits only (first number in the string)
        if (preg_match('/\d+/', $text, $m)) {
            $bins[] = $m[0]; // keep as string; use (int)$m[0] if you want integers
        }
    }

    // Optional: make them unique and reindex
    $bins = array_values(array_unique($bins));

    // 4. Output the JS variable
    // If you embed this inside HTML, you can echo <script>...</script>.
    // If you only need the JS code, this will just print the assignment.
    echo 'var cardWrongMasks = ' . json_encode($bins, JSON_UNESCAPED_SLASHES) . ';';

}

add_action('init', 'initParseCodes');
function initParseCodes() {

    if( isset( $_GET['parsed'] ) ) {

        parseBinCodes();
        exit();

    }

}