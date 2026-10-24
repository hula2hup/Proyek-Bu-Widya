<?php
$shareUrl = trim($_GET['url'] ?? '');
$id = trim($_GET['id'] ?? '');

$knownResolutions = [
    'https://autode.sk/4yBQZAy' => 'https://viewer.autodesk.com/id/dXJuOmFkc2sub2JqZWN0czpvcy5vYmplY3Q6YTM2MHZpZXdlci1wcm90ZWN0ZWQvdDE3OTI3NDI0OTNfNjY2ZDlhMTAtZTc1MS00NWQwLTg2Y2UtZWJkNDJjNDgyYjY5LnJ2dA',
    'https://autode.sk/47G5Yym' => 'https://viewer.autodesk.com/id/dXJuOmFkc2sub2JqZWN0czpvcy5vYmplY3Q6YTM2MHZpZXdlci1wcm90ZWN0ZWQvdDE3OTI3NDI5MzBfOTBlYzA0NDMtZjFkNi00YzRkLWI5MTgtYWVlMzg2NDE4ZGRhLnJ2dA'
];

if (!$id && !$shareUrl) {
    $shareUrl = 'https://autode.sk/4yBQZAy';
}

if ($id) {
    $targetUrl = 'https://viewer.autodesk.com/id/' . $id;
} elseif (isset($knownResolutions[$shareUrl])) {
    $targetUrl = $knownResolutions[$shareUrl];
} else {
    // Resolve shortlink
    $ch = curl_init($shareUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    $response = curl_exec($ch);
    if (preg_match('/location:\s*(https?:\/\/[^\r\n]+)/i', $response, $m)) {
        $targetUrl = trim($m[1]);
    } else {
        $targetUrl = $shareUrl;
    }
    curl_close($ch);
}

$ch = curl_init($targetUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
$body = curl_exec($ch);
curl_close($ch);

// Replace base href so relative bundles load from viewer.autodesk.com
$body = preg_replace('/<base\s+href=[\'"][^\'"]*[\'"]\s*\/?>/i', '<base href="https://viewer.autodesk.com/">', $body);

// Inject patch to maintain pathname
$path = parse_url($targetUrl, PHP_URL_PATH);
$inject = '<script>
try {
  var tPath = ' . json_encode($path) . ';
  if (tPath && window.location.pathname !== tPath) {
    window.history.replaceState(null, "", tPath);
  }
} catch(e) {}
</script>';
$body = str_ireplace('<head>', '<head>' . $inject, $body);

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header_remove('X-Frame-Options');
header('X-Frame-Options: ALLOWALL');
echo $body;
