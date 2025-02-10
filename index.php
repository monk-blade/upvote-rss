<?php

if(strpos($_SERVER['REQUEST_URI'], '&amp;') !== false) {
  header('Location: ' . str_replace('&amp;', '&', $_SERVER['REQUEST_URI']));
}
$request = $_SERVER['REQUEST_URI'];
if (substr($request, -5) == '/feed') {
	// $request = substr($request, 0, -5);
  header('Location: ' . substr($request, 0, -5));
}
if (substr($request, -4) == '/rss') {
  $request = substr($request, 0, -4);
  header('Location: ' . substr($request, 0, -4));
}


// robots.txt
if(strpos($request, 'robots.txt') !== false) {
  header('Content-Type: text/plain');
  echo "User-agent: *\nDisallow: /rss";
  exit;
}

// Setup
include 'app.php';

// View format
if (strpos($request, '/rss') !== false || strpos($request, 'view=rss') !== false) {
  include 'views/rss.php';
} else {
  include 'views/html.php';
}
