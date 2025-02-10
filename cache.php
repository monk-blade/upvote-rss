<?php


// Remove expired cache files and empty directories
$directory_expirations = [
  "/progress/"                => PROGRESS_EXPIRATION,
  "/auth/"                    => AUTH_EXPIRATION,
  "/about/"                   => ABOUT_EXPIRATION,
  "/hot_posts/"               => HOT_POSTS_EXPIRATION,
  "/top_posts/"               => TOP_POSTS_EXPIRATION,
  "/top_posts_day/"           => TOP_DAILY_POSTS_EXPIRATION,
  "/top_posts_month/"         => TOP_MONTHLY_POSTS_EXPIRATION,
  "/comments/"                => COMMENTS_EXPIRATION,
  "/communities/hacker_news/" => TOP_POSTS_EXPIRATION,
  "/communities/lemmy/"       => TOP_POSTS_EXPIRATION,
  "/galleries/"               => GALLERY_EXPIRATION,
  "/rss/"                     => RSS_EXPIRATION,
  "/webpages/"                => WEBPAGE_EXPIRATION,
  "/images/"                  => IMAGE_EXPIRATION
];
if (!is_dir("cache")) mkdir("cache", 0755, true);
for ($i = 0; $i < 10; $i++) {
  if (is_dir("cache")) break;
  usleep(100000);
}
$cache_directory_iterator = new RecursiveDirectoryIterator("cache");
foreach (new RecursiveIteratorIterator($cache_directory_iterator) as $item) {
  if (is_file($item)) {
    foreach ($directory_expirations as $search => $expiration) {
      if (
        is_file($item) &&
        strpos($item->getPathname(), $search) !== false &&
        time() - filemtime($item) >= $expiration
      )
      unlink($item->getPathname());
    }
  } elseif (
    !empty($item) &&
    is_dir($item) &&
    is_array(scandir($item)) &&
    count(scandir($item)) == 2
  ) {
    $parent = $item->getPath();
    while (count(scandir($parent)) == 2) {
      if (!@rmdir($parent)) {
        // Directory is busy, skip it
        break;
      }
      $parent = dirname($parent);
    }
  }
}


/**
 * Get cache directory size
 * @return int Cache directory size in bytes
 */
function getCacheDirectorySize()
{
  $cache_size = 0;
  // Get size of cache directory if it exists
  if (!is_dir("cache")) return $cache_size;
  $cache_directory_iterator = new RecursiveDirectoryIterator("cache");
  foreach (new RecursiveIteratorIterator($cache_directory_iterator) as $item) {
    if (is_file($item)) $cache_size += $item->getSize();
  }
  return $cache_size;
}


/**
 * Get total cache size
 * @return string Cache size in human readable format
 */
function getCacheSize()
{
  $cache_size = 0;
  // Get Redis cache size if available
  if (REDIS) {
    $client = new Predis\Client(array(
      'scheme'   => 'tcp',
      'host'     => REDIS_HOST,
      'port'     => REDIS_PORT,
      'timeout' => 0.5
    ));
    $keys = $client->keys('upvote_rss*');
    foreach ($keys as $key) {
      if (!empty($key) && $client->exists($key)) {
        $cache_size += strlen($key) + strlen($client->get($key));
      }
    }
  }
  // Get size of cache directory if it exists
  $cache_size += getCacheDirectorySize();
  return formatByteSize($cache_size);
}
define('CACHE_SIZE', getCacheSize());