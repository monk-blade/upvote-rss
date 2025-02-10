<?php

namespace Community;

class HackerNews extends Community
{

	// Properties
	public $platform = 'hacker-news';
	public $instance = 'news.ycombinator.com';
	public $is_instance_valid = true;
	public $platform_icon = 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Y_Combinator_logo.svg/256px-Y_Combinator_logo.svg.png';
	public $slug_title;
	public $max_items_per_request = 50;

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
		$community = new Community\HackerNews();
		foreach ($array as $key => $value) {
			$community->{$key} = $value;
		}
		return $community;
	}


	protected function getInstanceInfo() {}


	protected function getCommunityInfo() {
		if (!in_array($this->slug, ['beststories', 'topstories', 'newstories', 'askstories', 'showstories']))
			die("This Hacker News category is not valid.");
		$api_slug_map = [
			'beststories' => ['Best', 'https://news.ycombinator.com/best'],
			'topstories'  => ['Top', 'https://news.ycombinator.com/'],
			'newstories'  => ['New', 'https://news.ycombinator.com/newest'],
			'askstories'  => ['Ask', 'https://news.ycombinator.com/ask'],
			'showstories' => ['Show', 'https://news.ycombinator.com/show'],
		];
		$this->platform             = "hackernews";
		$this->name                 = "Hacker News";
		$this->title                = $this->name . " - " . $api_slug_map[$this->slug][0];
		$this->slug_title           = $api_slug_map[$this->slug][0];
		$this->description          = "A social news website focusing on computer science and entrepreneurship.";
		$this->url                  = $api_slug_map[$this->slug][1];
		$this->icon                 = 'https://upload.wikimedia.org/wikipedia/commons/thumb/b/b2/Y_Combinator_logo.svg/480px-Y_Combinator_logo.svg.png';
		$this->created              = '2007-10-09T18:21:51.000Z';
		$this->nsfw                 = false;
		$this->feed_title           = $this->title;
		$this->feed_description     = "\"" . $this->slug_title . "\" posts at Hacker News";
		$this->needs_authentication = false;
		$this->is_community_valid   = true;
	}


	public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW) {
		$limit = $limit ?? $this->max_items_per_request;
		$cache_object_key = $this->slug . '_limit_' . $limit;
		$cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/hacker_news/top_posts/";
		if (cacheGet($cache_object_key, $cache_directory))
			return cacheGet($cache_object_key, $cache_directory);
		$progress_cache_object_key = "progress_" . $this->platform . "_" . $this->slug;
		$progress_cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/progress/";
		$top_post_ids = $this->getTopCategoryPostIDs($this->slug);
		$top_post_ids = array_slice($top_post_ids, 0, $limit);
		$posts = [];
		if (INCLUDE_PROGRESS)
			cacheDelete($progress_cache_object_key, $progress_cache_directory);
		foreach ($top_post_ids as $index => $post_id) {
			$progress = [
				'current' => $index + 1,
				'total' => count($top_post_ids) + 1
			];
			if (INCLUDE_PROGRESS)
				cacheSet($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
			$individual_post_cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/hacker_news/individual_posts/";
			if (cacheGet($post_id, $individual_post_cache_directory)) {
				$posts[] = cacheGet($post_id, $individual_post_cache_directory);
			} else {
				$url = "https://hacker-news.firebaseio.com/v0/item/$post_id.json";
				$curl_response = curlURL($url);
				$post = json_decode($curl_response, true);
				if (empty($post) || empty($post['id'])) {
					continue;
				}
				$post = new \Post\HackerNews($post, $this);
				$posts[] = $post;
				cacheSet($post->id, $post, $individual_post_cache_directory, HOT_POSTS_EXPIRATION);
			}
		}
		cacheSet($cache_object_key, $posts, $cache_directory, HOT_POSTS_EXPIRATION);
		if (INCLUDE_PROGRESS)
			cacheSet($progress_cache_object_key, ['current' => 99, 'total' => 100], $progress_cache_directory, 1);
		return $posts;
	}


	// Get top posts
	public function getTopPosts($limit, $period = null) {
		$limit = $limit ?? $this->max_items_per_request;
		return $this->getHotPosts($limit);
	}


	private function getTopCategoryPostIDs() {
		$cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/hacker_news/category_post_ids/";
		if (cacheGet($this->slug, $cache_directory))
			return cacheGet($this->slug, $cache_directory);
		$url = "https://hacker-news.firebaseio.com/v0/$this->slug.json";
		$curl_response = curlURL($url);
		$curl_data = json_decode($curl_response, true);
		if (empty($curl_data) || !empty($curl_data['error']))
			return ['error' => 'There was an error communicating with Hacker News.'];
		cacheSet($this->slug, $curl_data, $cache_directory, TOP_POSTS_EXPIRATION);
		return $curl_data;
	}


	// Get monthly average top score
	public function getMonthlyAverageTopScore() {}


	public function getFilteredPostsByValue(
		$filter_type = FILTER_TYPE,
		$filter_value = FILTER_VALUE,
		$filter_nsfw = FILTER_NSFW,
		$blur_nsfw = BLUR_NSFW,
		$filter_old_posts = FILTER_OLD_POSTS,
		$post_cutoff_days = POST_CUTOFF_DAYS
	) {

		if (!$this->is_community_valid)
		die("This community is not valid.");

		// Filter by score
		if ($filter_type == 'score') :
			return $this->getHotPostsByScore($filter_type, $filter_value, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);

		// Filter by threshold
		elseif (
			$filter_type == 'threshold'
		) :
			return ['error' => 'This filter type is not supported for Hacker News.'];

		// Filter by average posts per day
		elseif (
			$filter_type == 'averagePostsPerDay'
		) :
			$threshold_score                         = 0;
			$top_posts                               = $this->getHotPosts(100);
			usort($top_posts, function ($a, $b) {
				return $b->time <=> $a->time;
			});
			$returned_number_of_posts                = count($top_posts);
			$oldest_post_time                        = $top_posts[$returned_number_of_posts - 1]->time;
			$newest_post_time                        = $top_posts[0]->time;
			$time_range                              = $newest_post_time - $oldest_post_time;
			$days_between_oldest_and_newest_posts    = $time_range / 86400;
			$requested_posts_per_day                 = $filter_value;
			usort($top_posts, function ($a, $b) {
				return $b->score <=> $a->score;
			});
			$number_of_posts_that_meet_the_cutoff    = floor($days_between_oldest_and_newest_posts * $requested_posts_per_day);
			$ratio_of_cutoff_posts_to_returned_posts = $number_of_posts_that_meet_the_cutoff / $returned_number_of_posts;
			if ($number_of_posts_that_meet_the_cutoff == 0) return [];
			if ($number_of_posts_that_meet_the_cutoff <= $returned_number_of_posts) {
				$threshold_score = $top_posts[$number_of_posts_that_meet_the_cutoff - 1]->score;
			} else {
				$lowest_score = $top_posts[$returned_number_of_posts - 1]->score;
				$threshold_score = $lowest_score / $ratio_of_cutoff_posts_to_returned_posts;
			}
			return $this->getHotPostsByScore($filter_type, $threshold_score, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);

		endif;
	}
}
