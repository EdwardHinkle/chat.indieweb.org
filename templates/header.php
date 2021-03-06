<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html class="<?= $permalink ? '' : 'h-feed' ?>">
<head>
  <meta charset="utf-8"/>
  <title class="p-name"><?= $channelName ?> <?= $dateTitle ?></title>

  <meta name="keywords" content="<?= htmlspecialchars($channelName) ?>"/>
  <?php if($permalink): ?>
  <meta name="description" content="<?= htmlspecialchars($current->author->nickname . ': ' . $current->content) ?>">
  <meta name="author" content="<?= htmlspecialchars($current->author->nickname) ?>"/>
  <?php endif; ?>
  <?php if(isset($userUrl) && $userUrl): ?>
  <link rel="author" href="<?= htmlspecialchars($userUrl) ?>"/>
  <?php endif; ?>
  <?php if(isset($noindex) && $noindex): ?>
  <meta name="robots" content="noindex,follow"/>
  <?php endif; ?>

  <script src="/assets/jquery-3.1.0.min.js"></script>
  <script src="/assets/cookie.js"></script>

  <link rel="stylesheet" type="text/css" href="/materialize/css/materialize.min.css"/>
  <script src="/materialize/js/materialize.min.js"></script>
  <script src="/assets/moment.min.js"></script>

  <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet"/>

  <link rel="stylesheet" type="text/css" href="/assets/styles.css"/>

  <meta name="viewport" content="width=device-width,initial-scale=1"/>
  <meta name="generator" content="https://github.com/indieweb/chat.indieweb.org"/>
  <link rel="pingback" href="https://webmention.io/indiewebcamp/xmlrpc"/>
  <link href="https://webmention.io/indiewebcamp/webmention" rel="webmention"/>
  <script src="/assets/pushstream.js"></script>
  <script src="/assets/streaming.js"></script>

  <link rel="apple-touch-icon-precomposed" href="/assets/logo/<?= Config::logo_for_channel($channel); ?>">

  <script type="application/ld+json">
  {
    "@context": "http://schema.org",
    "@type": "WebSite",
    "url": "<?= Config::$base ?>",
    "potentialAction": {
      "@type": "SearchAction",
      "target": "https://indiechat.search.cweiske.de/?q={search_term_string}",
      "query-input": "required name=search_term_string"
    }
  }
  </script>
  <link rel="search" title="search chat.indieweb.org" type="application/opensearchdescription+xml" href="/opensearchdescription.php"/>
</head>
<body>
