<?php

namespace Post;

class GitHub extends Post {

	// Properties
	public  ?array $post_data             = null;
	public  int    $max_items_per_request = 500;
	public  ?int   $num_kids              = null;
	private array  $kids_ids              = [];
	private array  $all_comments          = [];

	// Constructor
	public function __construct($post_data) {
		if (!is_array($post_data)) {
			$this->post_data = null;
		} else {
			$this->post_data = $post_data;
		}
		$this->nsfw = false;
		$this->setID();
		$this->setPermalink();
		$this->setURL();
		$this->setDomain();
		$this->setTitle();
		$this->setTime();
		$this->setScore();
		$this->setSelftextHTML($this->post_data['description'] ?? '');
		$this->setThumbnail();
		$this->setFeedLink();
		unset($this->post_data);
	}

	// Enable loading from cache
	static function __set_state($array) {
		$post = new \Post\GitHub([]);
		foreach ($array as $key => $value) {
			$post->{$key} = $value;
		}
		return $post;
	}

	// Set ID
	private function setID(): void {
		$this->id = $this->post_data['id'] ?? null;
	}

	// Set Permalink
	private function setPermalink(): void {
		$this->permalink = $this->post_data['url'] ?? null;
	}

	// Set URL
	private function setURL(): void {
		$this->url = $this->permalink;
	}

	// Set Domain
	private function setDomain(): void {
		$this->domain = $this->url ? parse_url($this->url, PHP_URL_HOST) : '';
	}

	// Set Title
	private function setTitle(): void {
		$title = $this->post_data['title'] ?? '';
		$title = $this->normalize_title($title);
		$this->title = $title;
	}

	// Set Time
	private function setTime(): void {
		$this->created_utc = $this->post_data['created_at'] ?? '';
		$this->time = \normalizeTimestamp($this->created_utc);
	}

	// Set Score
	private function setScore(): void {
		$score = $this->post_data['score'] ?? null;
		if (is_numeric($score)) {
			$score = (int)$score;
		} else {
			$score = 0;
		}
		$this->score = $score;
		$this->score_formatted = $this->formatScore($this->score);
	}

	// Get detailed post data
	public function getDetailedPostData(): void {}

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

	// Set Thumbnail
	private function setThumbnail(): void {
		$this->thumbnail_url = $this->post_data['avatar_url'] ?? null;
	}

	// Check if comment should be filtered out
	protected function shouldFilterComment($comment): bool {}

	// Get comments
	public function getComments(): array {}

}