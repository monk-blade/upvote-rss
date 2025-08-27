<?php

namespace Post;

class Lobsters extends Post {

  // Properties
	public $post_data         = null;
	public $instance          = null;
  public $slug              = null;
  public $community_type    = null;

  // Constructor
  public function __construct($post_data, $instance, $slug, $community_type) {
    $this->post_data = $post_data;
    $this->instance = $instance;
    $this->slug = $slug;
    $this->community_type = $community_type;
    $this->setID();
    $this->setURL();
    $this->setDomain();
    $this->setTitle();
    $this->setPermalink();
    $this->setCreatedUTC();
    $this->setTime();
    $this->setScore();
    $this->setSelftextHTML();
    $this->setNSFW();
    $this->setFeedLink();
  }


  // Enable loading from cache
  static function __set_state($array) {
    $post = new \Post\Lobsters([], null, null, null);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }

  // Set ID
  private function setID() {
    $this->id = $this->post_data['short_id'] ?? null;
  }

  // Set URL
  private function setURL() {
    $post_url = $this->post_data['url'] ?? null;
    $this->url = !empty($post_url) ? cleanURL($post_url) : null;
  }

  // Set Domain
  private function setDomain() {
    $this->domain = $this->post_data['domain'] ?? null;
    if (empty($this->domain)) {
      $this->domain = $this->url ? parse_url($this->url, PHP_URL_HOST) : null;
    }
  }

  // Set Title
  private function setTitle() {
    $title = $this->post_data['title'] ?? null;
    $add_domain = false;
    if (!empty($this->domain) && strpos($this->instance, $this->domain) === false) {
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
    $permalink = $this->post_data['comments_url'] ?? $this->post_data['short_id_url'] ?? ($this->post_data['short_id'] ? 'https://' . $this->instance . '/s/' . $this->post_data['short_id'] : null);
    if (!empty($permalink)) {
      $permalink = cleanURL($permalink);
    }
    $this->permalink = $permalink;
  }

  // Set Created UTC
  private function setCreatedUTC() {
    $created_utc = $this->post_data['created_at'] ?? null;
    if (!is_numeric($created_utc)) {
      $created_utc = strtotime($created_utc);
    }
    $this->created_utc = $created_utc;
  }

  // Set Time
  private function setTime() {
    $time = $this->created_utc ?? null;
    if (!is_numeric($time)) {
      $time = strtotime($time);
    }
    $this->time = $time;
  }

  // Set Score
  private function setScore() {
    $this->score = $this->post_data['score'] ?? 0;
  }

  // Set Selftext HTML
  private function setSelftextHTML() {
    $selftext_html = $this->post_data['description_plain'] ?? $this->post_data['description'] ?? '';
    if(!empty($selftext_html)) {
      $Parsedown = new \Parsedown();
      $Parsedown->setSafeMode(true);
      $selftext_html = $Parsedown->text($selftext_html);
    }
    $this->selftext_html = $selftext_html;
  }

  private function setNSFW() {
    $this->nsfw = false;
  }

  // Get comments
	public function getComments() {
    $log = \CustomLogger::getLogger();
    $cache_object_key = $this->id . "_limit_" . COMMENTS;
    $comments = cacheGet($cache_object_key, $cache_directory);
    if ($comments) {
      return array_slice($comments, 0, COMMENTS);
    $cache_directory = "communities/lobsters/comments";
    }
    $url = $this->permalink . '.json';
    $curl_response = curlURL($url);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data) || !empty($curl_data['status'])) {
      $message = "Failed to get comments for Lobsters post $this->id";
      $log->error($message);
      return [];
    }
    if (empty($curl_data["comments"])) {
      return [];
    }
    $comments = $curl_data["comments"];
    $comments_min = [];
    $Parsedown = new \Parsedown();
    $Parsedown->setSafeMode(true);
    foreach ($comments as $comment) {
      if (!empty($comment['parent_comment'])) {
        continue;
      }
      $body = !empty($comment['comment_plain']) ? $Parsedown->text($comment['comment_plain']) : '';
      if (empty($body) && !empty($comment['comment'])) {
        $body = $Parsedown->text($comment['comment']);
      }
      $body = trim($body);
      $date = $comment['created_at'] ? strtotime($comment['created_at']) : null;
      $comments_min[] = [
        'id'          => $comment['short_id'],
        'author'      => $comment['commenting_user'] ?? 'Unknown',
        'body'        => $body,
        'created_utc' => normalizeTimestamp($date),
        'permalink'   => $comment['short_id_url'],
      ];
    }
    cacheSet($cache_object_key, $comments_min, $cache_directory, COMMENTS_EXPIRATION);
    return array_slice($comments_min, 0, COMMENTS);
  }
}