<?php

namespace Community;

class HackerNews extends Community
{

	// Properties
	public  $platform              = 'hacker-news';
	public  $instance              = 'news.ycombinator.com';
	public  $is_instance_valid     = true;
	public  $platform_icon         = UPVOTE_RSS_URI . 'img/platforms/hacker-news.png';
	public  $slug_title;
	public  $max_items_per_request = 1000;
	private $top_posts_timeframe   = 60 * 60 * 24 * 30; // 30 days
	private $hot_posts_timeframe   = 60 * 60 * 24 * 7;  // 7 days
	private $new_stories_timeframe = 60 * 60 * 3;       // 3 hours


	// Constructor
	function __construct(
		$slug = null
	) {
		if (!empty($slug)) $this->slug = $slug;
		else $this->slug = DEFAULT_HACKER_NEWS_COMMUNITY;
		$this->getCommunityInfo();
	}


	// Enable loading from cache
	static function __set_state($array) {
		$community = new self();
		foreach ($array as $key => $value) {
			$community->{$key} = $value;
		}
		return $community;
	}


	protected function getInstanceInfo() {}


	protected function getCommunityInfo() {
		if (!$this->validateSlug()) {
			$message = "The requested Hacker News category $this->slug does not exist";
			logger()->error($message);
			return ['error' => $message];
		}
		$api_slug_map = [
			'beststories' => ['Best', 'https://news.ycombinator.com/best'],
			'frontpage'   => ['Front page', 'https://news.ycombinator.com/'],
			'newstories'  => ['New', 'https://news.ycombinator.com/newest'],
			'askstories'  => ['Ask', 'https://news.ycombinator.com/ask'],
			'showstories' => ['Show', 'https://news.ycombinator.com/show'],
		];
		$this->platform             = "hacker-news";
		$this->name                 = "Hacker News";
		$this->title                = $this->name . " - " . $api_slug_map[$this->slug][0];
		$this->slug_title           = $api_slug_map[$this->slug][0];
		$this->description          = "A social news website focusing on computer science and entrepreneurship.";
		$this->url                  = $api_slug_map[$this->slug][1];
		$this->icon                 = $this->platform_icon;
		$this->created              = '2007-10-09T18:21:51.000Z';
		$this->nsfw                 = false;
		$this->feed_title           = $this->title;
		$this->feed_description     = "\"" . $this->slug_title . "\" posts at Hacker News";
		$this->needs_authentication = false;
		$this->is_community_valid   = true;
	}


	public static function getCommunityTypes(): array {
		return [
			'frontpage'   => 'Front page',
			'beststories' => 'Best',
			'newstories'  => 'New',
			'askstories'  => 'Ask',
			'showstories' => 'Show'
		];
	}


	// Validate slug
	private function validateSlug(): bool {
		$this->slug = $this->slug == 'topstories' ? 'frontpage' : $this->slug;
		if (!in_array($this->slug, ['beststories', 'frontpage', 'newstories', 'askstories', 'showstories'])) {
			$message = "The requested Hacker News category $this->slug does not exist";
			logger()->error($message);
			return false;
		}
		return true;
	}


	// Get top posts
	public function getTopPosts($limit, $period = null) {
		$limit = $limit ?? $this->max_items_per_request;
		$slug_tags_map = [
			'beststories' => 'story',
			'frontpage'   => 'story',
			'newstories'  => 'story',
			'askstories'  => 'ask_hn',
			'showstories' => 'show_hn',
		];
		$cache_object_key = $slug_tags_map[$this->slug] . '_limit_' . $limit;
		$cache_directory = "communities/hacker_news/{$slug_tags_map[$this->slug]}/top_posts";
		if (cache()->get($cache_object_key, $cache_directory)) {
			return cache()->get($cache_object_key, $cache_directory);
		}
		$cache_expiration = TOP_MONTHLY_POSTS_EXPIRATION;

		$top_posts = [];

		$url = "http://hn.algolia.com/api/v1/search?tags={$slug_tags_map[$this->slug]}&hitsPerPage=$limit&numericFilters=created_at_i>" . (time() - $this->top_posts_timeframe);
		$curl_response = curlURL($url);
		if (empty($curl_response)) {
			$message = 'Empty response when trying to get top posts for Hacker News category ' . $this->slug . ' at ' . $url;
			logger()->error($message);
			return ['error' => $message];
		}

		$curl_data = json_decode($curl_response, true);
		if (empty($curl_data) || !empty($curl_data['error'])) {
			$message = 'There was an error communicating with Hacker News: ' . ($curl_data['error'] ?? 'Unknown error');
			logger()->error($message);
			return ['error' => $message];
		}

		// Set progress
		$progress_cache_object_key = "progress_" . str_replace('-', '', $this->platform) . "_" . $this->slug;
    $progress_cache_directory = "progress";
		$progress = [ 'current' => 50, 'total' => 100 ];
		if (INCLUDE_PROGRESS) {
			cache()->set($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
    }

		if (empty($curl_data['hits'])) {
			logger()->info('No top posts found for Hacker News category ' . $this->slug);
			return [];
		}

		usort($curl_data['hits'], function ($a, $b) {
			return $b['points'] <=> $a['points'];
		});

		foreach ($curl_data['hits'] as $post_data) {
			$top_posts[] = [
				'id'         => $post_data['story_id'] ?? 0,
				'title'      => $post_data['title'] ?? '',
				'score'      => $post_data['points'] ?? 0,
				'url'        => $post_data['url'] ?? '',
				'created_at' => $post_data['created_at_i'] ?? 0,
			];
		}

		cache()->set($cache_object_key, $top_posts, $cache_directory, $cache_expiration);
		return $top_posts;
	}


	// Get hot posts
	public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW) {
		$limit = $limit ?? $this->max_items_per_request;

		$slug_tags_map = [
			'beststories' => 'story',
			'frontpage'   => 'front_page',
			'newstories'  => 'story',
			'askstories'  => 'ask_hn',
			'showstories' => 'show_hn',
		];
		$cache_object_key = $this->slug . '_limit_' . $limit;
		$cache_directory = "communities/hacker_news/{$this->slug}/hot_posts";
		if (cache()->get($cache_object_key, $cache_directory)) {
			return cache()->get($cache_object_key, $cache_directory);
		}
		$cache_expiration = HOT_POSTS_EXPIRATION;

		$hot_posts = [];

		$url = "http://hn.algolia.com/api/v1/search_by_date?tags={$slug_tags_map[$this->slug]}&hitsPerPage=$limit&numericFilters=created_at_i>" . (time() - $this->hot_posts_timeframe);
		if ($this->slug == 'newstories') {
			$url = "http://hn.algolia.com/api/v1/search_by_date?tags={$slug_tags_map[$this->slug]}&hitsPerPage=$limit&numericFilters=created_at_i>" . (time() - $this->new_stories_timeframe);
		}
		$curl_response = curlURL($url);
		if (empty($curl_response)) {
			$message = 'Empty response when trying to get hot posts for Hacker News category ' . $this->slug . ' at ' . $url;
			logger()->error($message);
			return ['error' => $message];
		}

		$curl_data = json_decode($curl_response, true);
		if (empty($curl_data) || !empty($curl_data['error'])) {
			$message = 'There was an error communicating with Hacker News: ' . ($curl_data['error'] ?? 'Unknown error');
			logger()->error($message);
			return ['error' => $message];
		}

		// Set progress
		$progress_cache_object_key = "progress_" . str_replace('-', '', $this->platform) . "_" . $this->slug;
    $progress_cache_directory = "progress";
		$progress = [ 'current' => 99, 'total' => 100 ];
		if (INCLUDE_PROGRESS) {
			cache()->set($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
    }

		if (empty($curl_data['hits'])) {
			logger()->info('No hot posts found for Hacker News category ' . $this->slug);
			return [];
		}

		foreach ($curl_data['hits'] as $post_data) {
			$post = new \Post\HackerNews($post_data);
			$hot_posts[] = $post;
		}

		cache()->set($cache_object_key, $hot_posts, $cache_directory, $cache_expiration);
		return $hot_posts;
	}


	// Get monthly average top score
	public function getMonthlyAverageTopScore() {
		$top_posts = $this->getTopPosts($this->max_items_per_request);
		if (empty($top_posts)) {
			logger()->info('No top posts found for monthly average score calculation');
			return 0;
		}
		$total_score = array_sum(array_column($top_posts, 'score'));
		$average_score = $total_score / count($top_posts);
		logger()->info('Monthly average top score for Hacker News category ' . $this->slug . ': ' . $average_score);
		return $average_score;
	}
}
