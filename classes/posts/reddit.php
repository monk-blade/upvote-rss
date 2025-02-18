<?php

namespace Post;

class Reddit extends Post {

  // Properties
	public $post_data = null;
  public $subreddit = null;

  // Constructor
  public function __construct($post_data, $subreddit = null) {
    $this->post_data                = $post_data;
    $this->thumbnail_url            = null;
    $this->thumbnail_obfuscated_url = null;
    $this->image_obfuscated_url     = null;
    $this->setID();
    $this->setDomain();
    $this->setSubreddit($subreddit);
    $this->setURL();
    $this->setPermalink();
    $this->setOriginalPermalink();
    $this->setCreatedUTC();
    $this->setTime();
    $this->setScore();
    $this->setMediaEmbed();
    $this->setSecureMediaEmbed();
    $this->setSecureMedia();
    $this->setPreview();
    $this->setSelftextHTML();
    $this->setNSFW();
    $this->setThumbnail();
    $this->setThumbnailObfuscatedUrl();
    $this->setImageObfuscatedUrl();
    $this->setFeedLink();
    $this->setTitle();
    $this->post_data = null;
  }

  // Enable loading from cache
  static function __set_state($array) {
    $post = new \Post\Reddit([]);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }

  // Set ID
  private function setID() {
    $this->id = $this->post_data['id'] ?? null;
  }

  // Set Domain
  private function setDomain() {
    $this->domain = $this->post_data['domain'] ?? null;
  }

  // Set Subreddit
  private function setSubreddit($subreddit) {
    $this->subreddit = $subreddit ?? $this->post_data['subreddit'] ?? null;
  }

  // Set URL
  private function setURL() {
    $url = $this->post_data['url'] ?? null;
    if (
      strpos($url, '/r/') !== false &&
      strpos($url, 'https') === false
    ) {
      $url = 'https://www.reddit.com' . $url;
    }
    $this->url = $url;
  }

  // Set Permalink
  private function setPermalink() {
    if (!empty($this->post_data['permalink'])) {
      $this->permalink = 'https://www.reddit.com' . $this->post_data['permalink'];
    }
  }

  // Set Original Permalink
  private function setOriginalPermalink() {
    if (
      !empty($this->post_data['is_self']) &&
      $this->post_data['is_self'] == false &&
      !empty($this->post_data['crosspost_parent_list'][0]['permalink'])
    ) {
      $this->original_permalink = 'https://www.reddit.com' . $this->post_data['crosspost_parent_list'][0]['permalink'];
    }
  }

  // Set Created UTC
  private function setCreatedUTC() {
    $this->created_utc = $this->post_data['created_utc'] ?? null;
  }

  // Set Time
  private function setTime() {
    if (!empty($this->post_data['created_utc'])) {
      $this->time = normalizeTimestamp($this->post_data['created_utc']);
    }
  }

  // Set Score
  private function setScore() {
    $this->score = $this->post_data['score'] ?? null;
  }

  // Set Media Embed
  private function setMediaEmbed() {
    $this->media_embed = $this->post_data['media_embed'] ?? null;
  }

  // Set Secure Media Embed
  private function setSecureMediaEmbed() {
    $this->secure_media_embed = $this->post_data['secure_media_embed'] ?? null;
  }

  // Set Secure Media
  private function setSecureMedia() {
    $this->secure_media = $this->post_data['secure_media'] ?? null;
  }

  // Set Preview
  private function setPreview() {
    $this->preview = $this->post_data['preview'] ?? null;
    if (!empty($this->preview['images'])) {
      foreach ($this->preview['images'] as $key => $image) {
        $source_url = $image['source']['url'] ?? null;
        $resolutions_url = $image['resolutions'][0]['url'] ?? null;
        if (!empty($source_url)) {
          $source_url = str_replace('//preview.redd.it', '//i.redd.it', $source_url);
          $source_url = str_replace('&amp;', '&', $source_url);
          $this->preview['images'][$key]['source']['url'] = $source_url;
        }
        if (!empty($resolutions_url)) {
          $resolutions_url = str_replace('//preview.redd.it', '//i.redd.it', $resolutions_url);
          $resolutions_url = str_replace('&amp;', '&', $resolutions_url);
          $this->preview['images'][$key]['resolutions'][0]['url'] = $resolutions_url;
        }
      }
    }
  }

  // Set Selftext HTML
  private function setSelftextHTML() {
    $selftext_html = $this->post_data['selftext_html'] ?? $this->post_data['crosspost_parent_list'][0]['selftext_html'] ?? '';
    $selftext_html = str_replace('href="/r/', 'href="https://www.reddit.com/r/', $selftext_html);
    $selftext_html = str_replace('href="/u/', 'href="https://www.reddit.com/u/', $selftext_html);
    $selftext_html = str_replace('href="/message/', 'href="https://www.reddit.com/message/', $selftext_html);
    $this->selftext_html = $selftext_html;
  }

  // Set NSFW
  private function setNSFW() {
    $nsfw = false;
    $subreddit_nsfw = false;
    if($this->subreddit) {
      $community = new \Community\Reddit($this->subreddit);
      $subreddit_nsfw = $community->nsfw;
    }
    if (
      $subreddit_nsfw ||
      !empty($this->post_data['over_18']) ||
      !empty($this->post_data['nsfw']) ||
      $this->post_data['thumbnail'] == 'nsfw'
    ) {
      $nsfw = true;
    }
    $this->nsfw = $nsfw;
  }

  // Set Title
  private function setTitle() {
    $title = $this->post_data['title'] ?? null;
    if (
      (strpos($this->feed_Link, "imgur") !== false && strpos($this->feed_Link, "gallery") !== false) ||
      strpos($this->feed_Link, "www.reddit.com/gallery/")
    ) {
      $title .= " (Gallery)";
    } elseif (strpos($this->feed_Link, "v.redd.it") !== false) {
      $title .= " (Video)";
    } elseif (
      !empty($this->domain) &&
      !empty($this->subreddit) &&
      $this->domain === 'reddit.com'
      ) {
      $title .= ' (/r/' . $this->subreddit . ')';
    } elseif (
      !empty($this->domain) &&
      strpos($this->domain, 'self.') === false
    ) {
      $title .= ' (' . $this->domain . ')';
    }
    if (
      $this->nsfw &&
      strpos(strtolower($title), 'nsfw') === false
    ) {
      $title .= ' [NSFW]';
    }
    $title = htmlspecialchars($title);
    $title = str_replace(["\r", "\n"], "", $title);
    $title = trim($title);
    $this->title = $title ?? '';
  }

  // Set Thumbnail
  private function setThumbnail() {
    $thumbnail_url = $this->post_data['thumbnail'] ?? null;
    if (!empty($thumbnail_url)) {
      if ($this->nsfw || $thumbnail_url == 'nsfw') {
        $thumbnail_url = $this->preview['images'][0]['source']['url'] ?? $thumbnail_url;
        $thumbnail_url = $this->preview['images'][0]['resolutions'][0]['url'] ?? $thumbnail_url;
      }
      $thumbnail_url = str_replace('//preview.redd.it', '//i.redd.it', $thumbnail_url);
      $thumbnail_url = str_replace('&amp;', '&', $thumbnail_url);
      if ($thumbnail_url == 'nsfw') {
        $community = $this->post_data['subreddit'] ?? '';
        $community = new \Community\Reddit($community);
        $thumbnail_url = $community->icon ?? null;
      }
      $this->thumbnail_url = $thumbnail_url;
    }
  }

  // Obfuscate Thumbnail URL
  private function setThumbnailObfuscatedUrl() {
    if ($this->nsfw || $this->thumbnail_url == 'nsfw') {
      $thumbnail_obfuscated_url = null;
      $thumbnail_obfuscated_url = $this->preview['images'][0]['variants']['obfuscated']['source']['url'] ?? null;
      $thumbnail_obfuscated_url = $this->preview['images'][0]['variants']['obfuscated']['resolutions'][0]['url'] ?? null;
      if (!empty($thumbnail_obfuscated_url)) {
        $this->thumbnail_obfuscated_url = str_replace('&amp;', '&', $thumbnail_obfuscated_url);
      }
    }
  }

  // Obfuscate Image URL
  private function setImageObfuscatedUrl() {
    if ($this->nsfw || $this->thumbnail_url == 'nsfw') {
      $image_obfuscated_url = null;
      $image_obfuscated_url = $this->preview['images'][0]['variants']['obfuscated']['source']['url'] ?? null;
      if (!empty($this->preview['images'][0]['variants']['obfuscated']['resolutions'])) {
        $image_obfuscated_url = $this->preview['images'][0]['variants']['obfuscated']['resolutions'][count($this->preview['images'][0]['variants']['obfuscated']['resolutions']) - 1]['url'] ?? $image_obfuscated_url;
      }
      if (!empty($image_obfuscated_url)) {
        $this->image_obfuscated_url = str_replace('&amp;', '&', $image_obfuscated_url);
      }
    }
  }

  // Get comments
	public function getComments() {
    $log = new \CustomLogger;
    $reddit_auth = new \Auth\Reddit();
    if (!$reddit_auth->getToken()) {
      $message = "Reddit auth token not found.";
      $log->error($message);
      return ['error' => $message];
    }
    $cache_object_key = $this->id . "_limit_" . COMMENTS;
    $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/reddit/comments/";
    $url = "https://oauth.reddit.com/comments/$this->id.json?depth=1&showmore=0&limit=" . COMMENTS;
    if (cacheGet($cache_object_key, $cache_directory)) {
      return cacheGet($cache_object_key, $cache_directory);
    }
    $curl_response = curlURL($url, [
      CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $reddit_auth->getToken()),
      CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')'
    ]);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data) || !empty($curl_data['error'])) {
      $message = "Error in Reddit comments response: " . json_encode($curl_data['error'] ?? 'Unknown error');
      $log->error($message);
      return ['error' => $message];
    }
    if (empty($curl_data[1]["data"]["children"])) {
      return false;
    }
    $comments = $curl_data[1]["data"]["children"];
    $comments_min = [];
    foreach ($comments as $comment) {
      $body = $comment['data']['body_html'];
      $body = str_replace('href="/r/', 'href="https://www.reddit.com/r/', $body);
      $body = str_replace('href="/u/', 'href="https://www.reddit.com/u/', $body);
      $body = str_replace('href="/message/', 'href="https://www.reddit.com/message/', $body);
      $comments_min[] = [
        'id' => $comment['data']['id'],
        'author' => $comment['data']['author'],
        'body' => $body,
        'score' => $comment['data']['score'],
        'created_utc' => $comment['data']['created_utc'],
        'permalink' => 'https://www.reddit.com' . $comment['data']['permalink'],
      ];
    }
    cacheSet($cache_object_key, $comments_min, $cache_directory, COMMENTS_EXPIRATION);
    return $comments_min;
  }

}