<?php

namespace Post;

class HackerNews extends Post {

	// Properties
	public  ?array $post_data             = null;
	public  int    $max_items_per_request = 500;
	public  ?int   $num_kids              = null;
	private array  $kids_ids              = [];
	private array  $all_comments          = [];

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
		$this->setFeedLink();
		unset($this->post_data);
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
		$this->id = $this->post_data['story_id'] ?? null;
	}

	// Set Kids
	private function setKids() {
		$this->num_kids = $this->post_data['num_comments'] ?? 0;
	}

	// Set Permalink
	private function setPermalink() {
		$this->permalink = 'https://news.ycombinator.com/item?id=' . $this->id;
	}

	// Set URL
	private function setURL() {
		$url = $this->post_data['url'] ?? '';
		$this->url = (!empty($url) && filter_var($url, FILTER_VALIDATE_URL))
			? $url
			: $this->permalink;
	}

	// Set Domain
	private function setDomain() {
		$this->domain = $this->url ? parse_url($this->url, PHP_URL_HOST) : '';
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
		$this->time = $this->post_data['created_at_i'] ?? null;
	}

	// Set Score
	private function setScore() {
		$this->score = $this->post_data['points'] ?? 0;
		$this->score_formatted = $this->formatScore($this->score);
	}

	// Get detailed post data
	public function getDetailedPostData(): void {

		if (empty($this->id)) {
			return;
		}

		$individual_post_cache_directory = "communities/hacker_news/individual_posts";
		if (cache()->get($this->id, $individual_post_cache_directory)) {
			$post = cache()->get($this->id, $individual_post_cache_directory) ?? [];
			if (is_object($post)) {
				$post = (array)$post;
			}
		} else {
			$url = "http://hn.algolia.com/api/v1/items/$this->id";
			$curl_response = curlURL($url);
			$curl_data = json_decode($curl_response, true);
			if (empty($curl_data) || !empty($curl_data['error'])) {
				$message = 'There was an error communicating with Hacker News';
				logger()->error($message);
				return;
			}
			// Remove extraneous data from the response
			foreach ($curl_data['children'] as &$child) {
				unset($child['children']);
			}

			$post = $curl_data;
			cache()->set($this->id, $curl_data, $individual_post_cache_directory, HOT_POSTS_EXPIRATION);
		}

		// Set Selftext HTML
		$this->setSelftextHTML($post['text'] ?? '');

		// Get comments
		if (empty($this->num_kids) || $this->num_kids === 0) {
			return;
		}
		if (isset($post['comments'])) {
			$this->all_comments = $post['comments'];
			return;
		}

		$url = "https://hacker-news.firebaseio.com/v0/item/{$this->id}.json";
		$curl_response = curlURL($url);
		$curl_data = json_decode($curl_response, true);
		if (empty($curl_data) || !empty($curl_data['error'])) {
			$message = 'There was an error communicating with Hacker News';
			logger()->error($message);
			return;
		}
		$this->kids_ids = $curl_data['kids'] ?? [];
		if (empty($this->kids_ids)) {
			logger()->info("No comments found for Hacker News post $this->id");
			return;
		}

		// Create a lookup array and build comments array
		$children_lookup = [];
		foreach ($post['children'] as $child) {
			$children_lookup[$child['id']] = $child;
		}
		$comments = [];
		foreach ($this->kids_ids as $id) {
			if (isset($children_lookup[$id])) {
				$comments[] = $children_lookup[$id];
			}
		}

		$post['comments'] = $comments;
		$this->all_comments = $comments;
		cache()->set($this->id, $post, $individual_post_cache_directory, HOT_POSTS_EXPIRATION);

		$this->getComments();
		return;
	}

	// Set Selftext HTML
	private function setSelftextHTML(string $selftext): void {
		if (empty($selftext)) {
			return;
		}

		$Parsedown = new \Parsedown();
		$Parsedown->setSafeMode(true);
		$selftext = $Parsedown->text($selftext);
		$this->selftext_html = $selftext;
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
		if ($this->num_kids === 0 || empty($this->all_comments)) {
			return [];
		}

		$comments_min = [];
		$comment_count = 0;

		foreach ($this->all_comments as $comment) {
			if ($comment_count >= COMMENTS) {
				break;
			}
			if ($this->shouldFilterComment($comment)) {
				continue;
			}
			$comment_id          = $comment['id'] ?? '';
			$comment_author      = $comment['author'] ?? '';
			$comment_body        = $comment['text'] ?? '';
			$comment_created_utc = $comment['created_at_i'] ?? 0;
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