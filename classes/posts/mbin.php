<?php

namespace Post;

class Mbin extends Post {

  // Properties
	public $post_data         = null;
	public $instance          = null;
  public $slug              = null;
  public $entry_slug        = null;

  // Constructor
  public function __construct($post_data, $instance, $slug) {
    $this->post_data = $post_data;
    $this->instance = $instance;
    $this->slug = $slug;
    $this->setID();
    $this->setURL();
    $this->setDomain();
    $this->setEntrySlug();
    $this->setTitle();
    $this->setPermalink();
    $this->setCreatedUTC();
    $this->setTime();
    $this->setScore();
    $this->setThumbnail();
    $this->setSelftextHTML();
    $this->setNSFW();
    $this->setFeedLink();
  }

  // Enable loading from cache
  static function __set_state($array) {
    $post = new \Post\Mbin([], null, null);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }

  // Set ID
  private function setID() {
    $this->id = $this->post_data['entryId'] ?? null;
  }

  // Set URL
  private function setURL() {
    $post_url = $this->post_data['url'] ?? $this->post_data['apId'] ?? null;
    $this->url = !empty($post_url) ? cleanURL($post_url) : null;
  }

  // Set Domain
  private function setDomain() {
    $this->domain = $this->post_data['domain']['name'] ?? null;
    if (empty($this->domain)) {
      $this->domain = $this->url ? parse_url($this->url, PHP_URL_HOST) : null;
    }
    if (empty($this->domain)) {
      $this->domain = $this->post_data['apId'] ?? null;
    }
    $this->domain = trim($this->domain ?? '');
  }

  // Set Entry Slug
  private function setEntrySlug() {
    $this->entry_slug = $this->post_data['slug'] ?? null;
  }

  // Set Title
  private function setTitle() {
    $title = $this->post_data['title'] ?? null;
    $add_domain = false;
    $ap_id_domain = $this->post_data['apId'] ? parse_url($this->post_data['apId'], PHP_URL_HOST) : null;
    if (
      !empty($title) &&
      !empty($this->domain) &&
      $this->instance !== $this->domain &&
      $this->domain !== $ap_id_domain
     ) {
      $add_domain = true;
    }
    if($add_domain) {
      $title = $title . ' (' . $this->domain . ')';
    }
    $title = htmlspecialchars($title);
    $title = str_replace(["\r", "\n"], "", $title);
    $title = trim($title);
    $this->title = $title ?? '';
  }

  // Set Permalink
  private function setPermalink() {
    $permalink = $this->post_data['magazine']['name'] ? 'https://' . $this->instance . '/m/' . $this->post_data['magazine']['name'] . '/t/' . $this->id . '/' . $this->entry_slug . '/' : null;
    if (!empty($permalink)) {
      $permalink = cleanURL($permalink);
    }
    $this->permalink = $permalink;
  }

  // Set Created UTC
  private function setCreatedUTC() {
    $this->created_utc = $this->post_data['createdAt'] ?? null;
  }

  // Set Time
  private function setTime() {
    $this->time = !empty($this->created_utc) ? normalizeTimestamp($this->created_utc) : null;
  }

  // Set Score
  private function setScore() {
    $this->score = $this->post_data['favourites'] ?? null;
  }

  // Set Thumbnail
  private function setThumbnail() {
    $thumbnail = $this->post_data['image']['storageUrl'] ?? $this->post_data['image']['sourceUrl'] ?? null;
    if (empty($thumbnail) && !empty($this->url)) {
      $url_parts = parse_url($this->url);
      $path = $url_parts['path'] ?? null;
      $extension = pathinfo($path, PATHINFO_EXTENSION);
      if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
        $thumbnail = $this->url;
      }
    }
    $this->thumbnail_url = $thumbnail;
  }

  // Set Selftext HTML
  private function setSelftextHTML() {
    $selftext_html = $this->post_data['body'] ?? null;
    if(!empty($selftext_html)) {
      $Parsedown = new \Parsedown();
      $Parsedown->setSafeMode(true);
      $selftext_html = $Parsedown->text($selftext_html);
    }
    $this->selftext_html = $selftext_html;
  }

  // Set NSFW
  private function setNSFW() {
    $post_is_nsfw = false;
    if (!empty($this->post_data['isAdult'])) {
      $post_is_nsfw = true;
    }
    $this->nsfw = $post_is_nsfw;
  }

  // Get comments
	public function getComments() {
    $log = \CustomLogger::getLogger();
    $cache_object_key = $this->id . "_limit_" . COMMENTS;
    $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/mbin/comments/";
    if (cacheGet($cache_object_key, $cache_directory)) {
      return cacheGet($cache_object_key, $cache_directory);
    }
    $url = "https://$this->instance/api/entry/$this->id/comments?sortBy=top&perPage=" . COMMENTS . "&d=0";
    $curl_response = curlURL($url);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data) || !empty($curl_data['status'])) {
      $message = "Failed to get comments for Mbin post $this->id";
      $log->error($message);
      return ['error' => $message];
    }
    if (empty($curl_data["items"])) {
      return false;
    }
    $comments = $curl_data["items"];
    $comments = array_slice($comments, 0, COMMENTS);
    $comments_min = [];
    $Parsedown = new \Parsedown();
    $Parsedown->setSafeMode(true);
    foreach ($comments as $comment) {
      $body = $Parsedown->text($comment['body']);
      $body = str_replace('href="/m/', 'href="https://' . $this->instance . '/m/', $body);
      $comments_min[] = [
        'id' => $comment['commentId'],
        'author' => $comment['user']['userId'] ?? 'Unknown',
        'body' => $body,
        'created_utc' => normalizeTimestamp($comment['createdAt']),
        'permalink' => $this->permalink . 'comment/' . $comment['commentId'] . '#entry-comment-' . $comment['commentId'],
      ];
    }
    cacheSet($cache_object_key, $comments_min, $cache_directory, COMMENTS_EXPIRATION);
    return $comments_min;
  }
}