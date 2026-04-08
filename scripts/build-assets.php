<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$cssDir = $root . '/styles';
$jsDir = $root . '/scripts';
$adminAssetsDir = $root . '/admin/assets';
$minCssDir = $root . '/min_css';
$minJsDir = $root . '/min_js';

if (!is_dir($minCssDir)) {
    mkdir($minCssDir, 0755, true);
}
if (!is_dir($minJsDir)) {
    mkdir($minJsDir, 0755, true);
}

function app_collect_files(string $dir, string $ext): array
{
    if (!is_dir($dir)) {
        return [];
    }

    $files = glob($dir . '/*.' . $ext);
    if ($files === false) {
        return [];
    }

    $result = [];
    foreach ($files as $file) {
        $name = strtolower(basename($file));
        if (str_contains($name, '.min.' . $ext)) {
            continue;
        }
        if (preg_match('/(test|mock|fixture|sample|demo)/i', $name)) {
            continue;
        }
        $result[] = $file;
    }

    sort($result, SORT_NATURAL | SORT_FLAG_CASE);
    return $result;
}

function app_read_file(string $file): string
{
    $content = file_get_contents($file);
    return $content === false ? '' : $content;
}

function app_minify_css(string $css): string
{
    $css = preg_replace('#/\*.*?\*/#s', '', $css) ?? $css;
    $css = preg_replace('/\s+/', ' ', $css) ?? $css;
    $css = preg_replace('/\s*([{}:;,])\s*/', '$1', $css) ?? $css;
    $css = str_replace(';}', '}', $css);
    return trim($css);
}

function app_minify_js(string $js): string
{
    $js = preg_replace('#/\*.*?\*/#s', '', $js) ?? $js;
    $lines = preg_split('/\R/u', $js) ?: [];
    $clean = [];
    foreach ($lines as $line) {
        $trim = trim((string)$line);
        if ($trim === '') {
            continue;
        }
        if (str_starts_with($trim, '//')) {
            continue;
        }
        $clean[] = rtrim((string)$line);
    }
    return trim(implode("\n", $clean));
}

$cssFiles = array_merge(
    app_collect_files($cssDir, 'css'),
    app_collect_files($adminAssetsDir, 'css')
);
$jsFiles = array_merge(
    app_collect_files($jsDir, 'js'),
    app_collect_files($adminAssetsDir, 'js')
);

$cssOutput = '';
foreach ($cssFiles as $file) {
    $cssOutput .= "/* " . str_replace($root . '/', '', $file) . " */\n";
    $cssOutput .= app_minify_css(app_read_file($file)) . "\n";
}

$jsOutput = '';
foreach ($jsFiles as $file) {
    $jsOutput .= "/* " . str_replace($root . '/', '', $file) . " */\n";
    $jsOutput .= app_minify_js(app_read_file($file)) . "\n";
}

file_put_contents($minCssDir . '/app.min.css', $cssOutput);
file_put_contents($minJsDir . '/app.min.js', $jsOutput);

echo "Built min_css/app.min.css and min_js/app.min.js\n";
