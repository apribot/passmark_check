#!/usr/bin/env php
<?php

/**
 * CLI tool that searches passmark.com for a CPU designation, then fetches
 * and displays key benchmark specs from the first matching result page.
 *
 * Usage: php passmarkcheck.php <search query>
 */

function fetchUrl(string $url): string
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 15,
        // passmark.com/cpubenchmark.net return 403 without a browser-like User-Agent.
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
            'Accept: text/html,application/xhtml+xml',
            'Accept-Language: en-US,en;q=0.9',
        ],
    ]);

    $html = curl_exec($ch);
    if ($html === false) {
        $error = curl_error($ch);
        throw new RuntimeException("Failed to fetch {$url}: {$error}");
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($statusCode !== 200) {
        throw new RuntimeException("Request to {$url} failed with HTTP status {$statusCode}");
    }

    return $html;
}

function fetchSearchHtml(string $query): string
{
    // zoom_cat=5 restricts results to the "Benchmark results" category so the
    // first hit is always a cpubenchmark.net CPU spec page.
    $url = 'https://www.passmark.com/search/zoomsearch.php?zoom_query=' . urlencode($query)
        . '&zoom_cat=5&zoom_per_page=10&zoom_xml=0&zoom_and=1&zoom_sort=0';

    return fetchUrl($url);
}

function findFirstResultUrl(string $html): ?string
{
    $doc = new DOMDocument();
    libxml_use_internal_errors(true);
    $doc->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($doc);
    $links = $xpath->query('//div[contains(concat(" ", normalize-space(@class), " "), " result_title ")]//a');

    if ($links->length === 0) {
        return null;
    }

    // Prefer the first result that actually links to a cpubenchmark.net CPU
    // spec page, since other results (listing pages, etc.) don't have specs.
    foreach ($links as $link) {
        if (preg_match('/cpubenchmark\.net\/cpu\.php\?/', $link->getAttribute('href'))) {
            return $link->getAttribute('href');
        }
    }

    return $links->item(0)->getAttribute('href');
}

/**
 * Extracts benchmark specs from a cpubenchmark.net CPU detail page.
 *
 * @return array{multithread_rating: ?string, single_thread_rating: ?string, clockspeed: ?string, cores: ?string, threads: ?string}
 */
function extractCpuSpecs(string $html): array
{
    $specs = [
        'multithread_rating' => null,
        'single_thread_rating' => null,
        'clockspeed' => null,
        'cores' => null,
        'threads' => null,
    ];

    if (preg_match('/Multithread Rating<\/div>\s*<div[^>]*>([\d,]+)<\/div>/', $html, $m)) {
        $specs['multithread_rating'] = $m[1];
    }

    if (preg_match('/Single Thread Rating<\/div>\s*<div[^>]*>([\d,]+)<\/div>/', $html, $m)) {
        $specs['single_thread_rating'] = $m[1];
    }

    if (preg_match('/<strong>Clockspeed:<\/strong>\s*([^<]+)/', $html, $m)) {
        $specs['clockspeed'] = trim($m[1]);
    }

    if (preg_match('/<strong>Cores:<\/strong>\s*(\d+)/', $html, $m)) {
        $specs['cores'] = $m[1];
    }

    if (preg_match('/<strong>Threads:<\/strong>\s*(\d+)/', $html, $m)) {
        $specs['threads'] = $m[1];
    }

    // Hybrid (big.LITTLE) CPUs, e.g. Apple M-series, use "Total/Primary Cores"
    // instead of the plain Clockspeed/Cores/Threads labels above.
    if ($specs['cores'] === null && preg_match('/<strong>Total Cores:<\/strong>\s*(\d+)\s*Cores,\s*(\d+)\s*Threads/', $html, $m)) {
        $specs['cores'] = $m[1];
        $specs['threads'] = $m[2];
    }

    if ($specs['clockspeed'] === null && preg_match('/<strong>Primary Cores:<\/strong>\s*\d+\s*Cores,\s*\d+\s*Threads,\s*([\d.]+\s*[A-Za-z]+)\s*Base/', $html, $m)) {
        $specs['clockspeed'] = trim($m[1]);
    }

    return $specs;
}

function main(array $argv): int
{
    if (count($argv) < 2 || trim($argv[1]) === '') {
        fwrite(STDERR, "Usage: php passmarkcheck.php <cpu designation>\n");
        return 1;
    }

    $query = $argv[1];

    try {
        $searchHtml = fetchSearchHtml($query);
        $url = findFirstResultUrl($searchHtml);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "Error: {$e->getMessage()}\n");
        return 1;
    }

    if ($url === null) {
        fwrite(STDERR, "No results found for \"{$query}\"\n");
        return 1;
    }

    echo $url . "\n";

    try {
        $cpuHtml = fetchUrl($url);
    } catch (RuntimeException $e) {
        fwrite(STDERR, "Error: {$e->getMessage()}\n");
        return 1;
    }

    $specs = extractCpuSpecs($cpuHtml);

    echo "Multithread Rating: " . ($specs['multithread_rating'] ?? 'N/A') . "\n";
    echo "Single Thread Rating: " . ($specs['single_thread_rating'] ?? 'N/A') . "\n";
    echo "Clockspeed: " . ($specs['clockspeed'] ?? 'N/A') . "\n";
    echo "Cores: " . ($specs['cores'] ?? 'N/A') . "\n";
    echo "Threads: " . ($specs['threads'] ?? 'N/A') . "\n";

    return 0;
}

exit(main($argv));

