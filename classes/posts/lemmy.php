<?php

namespace Post;

class Lemmy extends Post {

  // Properties
  public  $post_data             = null;
  public  $instance              = null;
  public  $slug                  = null;
  public  $local                 = false;
  public  $embed_description     = null;
  private $max_items_per_request = 50;

  // Constructor
  public function __construct($post_data, $instance, $slug) {
    $this->post_data = $post_data;
    $this->instance = $instance;
    $this->slug = $slug;
    $this->setID();
    $this->setURL();
    $this->setDomain();
    $this->setTitle();
    $this->setPermalink();
    $this->setCreatedUTC();
    $this->setTime();
    $this->setScore();
    $this->setThumbnail();
    $this->setSelftextHTML();
    $this->setNSFW();
    $this->setLocal();
    $this->setEmbedDescription();
    $this->setFeedLink();
  }

  // Enable loading from cache
  static function __set_state($array) {
    $post = new \Post\Lemmy([], null, null);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }

  // Set ID
  private function setID() {
    $this->id = $this->post_data['post']['id'] ?? null;
  }

  // Set URL
  private function setURL() {
    $post_url = $this->post_data['post']['url'] ?? null;
    if (!empty($post_url)) {
      $post_url = cleanURL($post_url);
    } else {
      $post_ap_id = $this->post_data['post']['ap_id'] ?? null;
      if (!empty($post_ap_id)) {
        $post_url = cleanURL($post_ap_id);
      }
    }
    $this->url = $post_url;
  }

  // Set Local
  private function setLocal() {
    $this->local = $this->post_data['post']['local'] ?? false;
  }

  // Set Domain
  private function setDomain() {
    $domain = null;
    if(!empty($this->url)) {
      $domain = parse_url($this->url, PHP_URL_HOST);
    } elseif (!empty($this->post_data['community']['name'])) {
      $domain = 'self.' . $this->post_data['community']['name'];
    }
    $ap_id_domain = $this->post_data['post']['ap_id'] ? parse_url($this->post_data['post']['ap_id'], PHP_URL_HOST) : null;
    if (
      !empty($domain) &&
      !empty($ap_id_domain) &&
      $domain === $ap_id_domain &&
      !empty($this->post_data['community']['name'])
    ) {
      $domain = 'self.' . $this->post_data['community']['name'];
    }
    $this->domain = $domain;
  }

  // Set Title
  private function setTitle() {
    $title = $this->post_data['post']['name'] ?? null;
    $add_domain = false;
    $ap_id_domain = $this->post_data['post']['ap_id'] ? parse_url($this->post_data['post']['ap_id'], PHP_URL_HOST) : null;
    if (
      !empty($title) &&
      !empty($this->domain) &&
      $this->instance !== $this->domain &&
      !$this->local
     ) {
      if (
        $this->slug !== $this->post_data['community']['name'] &&
        $this->slug !== $this->post_data['community']['title']
      ) {
        $title = $title . ' (' . $this->post_data['community']['title'] . ')';
      }
      if(
        empty($ap_id_domain) ||
        (
          $ap_id_domain !== $this->domain &&
          strpos($this->domain, 'self.') === false
        )
      ) {
        $add_domain = true;
      }
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
    $this->permalink = $this->post_data['post']['ap_id'] ?? $this->url;
  }

  // Set Created UTC
  private function setCreatedUTC() {
    $this->created_utc = $this->post_data['post']['published'] ?? null;
  }

  // Set Time
  private function setTime() {
    $this->time = !empty($this->created_utc) ? normalizeTimestamp($this->created_utc) : null;
  }

  // Set Score
  private function setScore() {
    $this->score = $this->post_data['counts']['score'] ?? null;
    $this->score_formatted = $this->formatScore($this->score);
  }

  // Set Thumbnail
  private function setThumbnail() {
    $thumbnail = $this->post_data['post']['thumbnail_url'] ?? null;
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
    $selftext_html = $this->post_data['post']['body'] ?? null;
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
    if (!empty($this->post_data['nsfw']) || $this->thumbnail_url == 'nsfw') {
      $post_is_nsfw = true;
    }
    $this->nsfw = $post_is_nsfw;
  }

  // Set Embed Description
  private function setEmbedDescription() {
    $this->embed_description = $this->post_data['post']['embed_description'] ?? null;
  }

  // Check if comment should be filtered out
  protected function shouldFilterComment($comment): bool {
    return match (true) {
      empty($comment['comment']['content']) => true,
      !empty($comment['comment']['removed']) => true,
      !empty($comment['comment']['deleted']) => true,
      str_contains($comment['comment']['content'], '[removed]') => true,
      str_contains($comment['comment']['content'], '[deleted]') => true,
      FILTER_PINNED_COMMENTS && !empty($comment['comment']['distinguished']) => true,
      default => false
    };
  }

  // Get comments
	public function getComments(): array {
    $log = \CustomLogger::getLogger();
    $buffer_comments = max(5, (int)(COMMENTS * 1.5)); // Add some wiggle room
    $number_of_comments_to_fetch = min(COMMENTS + $buffer_comments, $this->max_items_per_request);
    $cache_object_key = $this->id . "_limit_" . $number_of_comments_to_fetch;
    $cache_directory = "communities/lemmy/comments";
    $comments = $this->getCachedComments($cache_directory, $cache_object_key, $number_of_comments_to_fetch) ?? [];

    if (empty($comments)) {
      $url = "https://$this->instance/api/v3/comment/list?post_id=$this->id&max_depth=1&sort=Top&type_=All&limit=" . $number_of_comments_to_fetch;
      $curl_response = curlURL($url);
      if (empty($curl_response)) {
        $message = "Failed to get comments for Lemmy post $this->id";
        $log->error($message);
        return ['error' => $message];
      }
      $curl_data = json_decode($curl_response, true);
      if (empty($curl_data) || !empty($curl_data['error'])) {
        $message = "Failed to get comments for Lemmy post $this->id";
        $log->error($message);
        return ['error' => $message];
      }
      if (empty($curl_data["comments"])) {
        $log->info("No comments found for Lemmy post $this->id");
        return [];
      }
      $comments = $curl_data["comments"];
      cache()->set($cache_object_key, $comments, $cache_directory, COMMENTS_EXPIRATION);
    }

    $comments_min = [];
    $comment_count = 0;
    $Parsedown = new \Parsedown();
    $Parsedown->setSafeMode(true);
    foreach ($comments as $comment) {
      if ($comment_count >= COMMENTS) {
        break;
      }
      if ($this->shouldFilterComment($comment)) {
        continue;
      }
      $body = $Parsedown->text(($comment['comment']['content']) ?? '');
      $body = str_replace('href="/c/', 'href="https://' . $this->instance . '/c/', $body);
      $comments_min[] = [
        'id'          => $comment['comment']['id'] ?? 0,
        'author'      => $comment['comment']['creator_id'] ?? 0,
        'body'        => $body,
        'created_utc' => normalizeTimestamp(($comment['comment']['published']) ?? 0),
        'permalink'   => "https://$this->instance/comment/" . ($comment['comment']['id'] ?? 0),
      ];
      $comment_count++;
    }
    return $comments_min;
  }
}