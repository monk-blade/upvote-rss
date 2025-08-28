<?php

namespace Post;

class HackerNews extends Post {

  // Properties
	public $post_data = null;
  public $max_items_per_request = 500;

  // Constructor
  public function __construct($post_data) {
    $this->post_data = $post_data;
    $this->nsfw = false;
    $this->setID();
    $this->setKids();
    $this->setPermalink();
    $this->setURL();
    $this->setDomain();
    $this->setTitle();
    $this->setTime();
    $this->setScore();
    $this->setSelftextHTML();
    $this->setFeedLink();
    $this->post_data = null;
  }

  // Enable loading from cache
  static function __set_state($array) {
    $post = new \Post\HackerNews([]);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }

  // Set ID
  private function setID() {
    $this->id = $this->post_data['id'] ?? null;
  }

  // Set Kids
  private function setKids() {
    $this->kids = $this->post_data['kids'] ?? null;
  }

  // Set Permalink
  private function setPermalink() {
    $this->permalink = 'https://news.ycombinator.com/item?id=' . $this->id;
  }

  // Set URL
  private function setURL() {
    $this->url = !empty($this->post_data['url']) ? $this->post_data['url'] : $this->permalink;
  }

  // Set Domain
  private function setDomain() {
    $this->domain = !empty($this->post_data['domain']) ? $this->post_data['domain'] : parse_url($this->url, PHP_URL_HOST);
    if (strpos($this->domain, 'news.ycombinator.com') !== false) {
      $this->domain = 'self.';
    }
  }

  // Set Title
  private function setTitle() {
    $title = $this->post_data['title'] ?? '';
    if ($this->domain && strpos($this->domain, 'self.') === false) {
      $title = $title . ' (' . $this->domain . ')';
    }
    $title = htmlspecialchars($title);
    $title = str_replace(["\r", "\n"], "", $title);
    $title = trim($title);
    $this->title = $title ?? '';
  }

  // Set Time
  private function setTime() {
    $this->time = $this->post_data['time'] ?? null;
  }

  // Set Score
  private function setScore() {
    $this->score = $this->post_data['score'] ?? null;
  }

  // Set Selftext HTML
  private function setSelftextHTML() {
    $selftext_html = !empty($this->post_data['text']) ? $this->post_data['text'] : null;
    if (!empty($selftext_html)) {
      $Parsedown = new \Parsedown();
      $Parsedown->setSafeMode(true);
      $selftext_html = $Parsedown->text($selftext_html);
    }
    $this->selftext_html = $selftext_html;
  }

  // Get single comment
  private function getComment($comment_id = null) {
		$cache_directory = "communities/hacker_news/comments";
		if (cacheGet($comment_id, $cache_directory)) {
			return cacheGet($comment_id, $cache_directory);
    }
		$url = "https://hacker-news.firebaseio.com/v0/item/$comment_id.json";
		$curl_response = curlURL($url);
		$curl_data = json_decode($curl_response, true);
		cacheSet($comment_id, $curl_data, $cache_directory, COMMENTS_EXPIRATION);
		return $curl_data;
	}

  // Check if comment should be filtered out
  protected function shouldFilterComment($comment): bool {
    return match (true) {
      empty($comment['text']) => true,
      !empty($comment['deleted']) => true,
      !empty($comment['dead']) => true,
      default => false
    };
  }

  // Get comments
	public function getComments(): array {
    $log = \CustomLogger::getLogger();
    $buffer_comments = max(5, (int)(COMMENTS * 1.5)); // Add some wiggle room
    $number_of_comments_to_fetch = min(COMMENTS + $buffer_comments, $this->max_items_per_request);
		$cache_object_key = $this->id . "_limit_" . $number_of_comments_to_fetch;
		$cache_directory = "communities/hacker_news/comments";
    $comments = $this->getCachedComments($cache_directory, $cache_object_key, $number_of_comments_to_fetch) ?? [];

    if (empty($comments)) {
      $comment_ids = [];
      $individual_post_cache_directory = "communities/hacker_news/individual_posts";
      if (cacheGet($this->id, $individual_post_cache_directory)) {
        $post = cacheGet($this->id, $individual_post_cache_directory) ?? [];
        if (is_object($post)) {
          $post = (array)$post;
        }
        if (!empty($post['kids'])) {
          foreach ($post['kids'] as $comment_id) {
            $comment_ids[] = $comment_id;
          }
        }
      } else {
        $url = "https://hacker-news.firebaseio.com/v0/item/$this->id.json";
        $curl_response = curlURL($url);
        if (empty($curl_response)) {
          $log->error("Failed to get comments for Hacker News post $this->id");
          return [];
        }
        $curl_data = json_decode($curl_response, true);
        cacheSet($this->id, $curl_data, $individual_post_cache_directory, HOT_POSTS_EXPIRATION);
        $comment_ids = $curl_data['kids'];
      }
      if (empty($comment_ids)) {
        return [];
      }
      $comment_id_count = 0;
      foreach ($comment_ids as $comment_id) {
        if ($comment_id_count >= $number_of_comments_to_fetch) {
          break;
        }
        $comment = $this->getComment($comment_id);
        if (!empty($comment['error'])) {
          $log->error("Comment $comment_id for Hacker News post $this->id returned an error: " . $comment['error']);
          continue;
        }
        $comments[] = $comment;
        $comment_id_count++;
      }
      if (empty($comments)) {
        return [];
      }
      cacheSet($cache_object_key, $comments, $cache_directory, COMMENTS_EXPIRATION);
    }

		$comments_min = [];
    $comment_count = 0;

		foreach ($comments as $comment) {
      if ($comment_count >= COMMENTS) {
        break;
      }
      if ($this->shouldFilterComment($comment)) {
        continue;
      }
      $comment_id          = $comment['id'] ?? '';
      $comment_author      = $comment['by'] ?? '';
      $comment_body        = $comment['text'] ?? '';
      $comment_created_utc = $comment['time'] ?? 0;
      $comment_permalink   = 'https://news.ycombinator.com/item?id=' . $comment_id;
			$comments_min[] = [
				'id'          => $comment_id,
				'author'      => $comment_author,
				'body'        => $comment_body,
				'created_utc' => $comment_created_utc,
				'permalink'   => $comment_permalink,
			];
      $comment_count++;
		}
		return $comments_min;
	}

}