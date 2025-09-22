<?php

namespace Community;

class GitHub extends Community
{

	// Properties
	public  $platform              = 'github';
	public  $instance              = 'github.com';
	public  $is_instance_valid     = true;
	public  $platform_icon         = UPVOTE_RSS_URI . 'img/platforms/github.png';
	public  $language              = null;
	public  $topic                 = null;
	public  $max_items_per_request = 100;


	// Constructor
	function __construct(
		$slug     = null,
		$language = null,
		$topic    = null
	) {
		$this->language  = $language ? trim($language) : LANGUAGE;
		$this->topic     = $topic ? trim($topic) : TOPIC;
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


	protected function getCommunityInfo(): void {
		// Name
		$name = 'GitHub';
		// Title
		$title = $name;
		if ($this->language || $this->topic) {
			$title .= ' - ';
			if ($this->language) {
				$title .= $this->language;
			}
			if ($this->language && $this->topic) {
				$title .= ' / ';
			}
			if ($this->topic) {
				$title .= $this->topic;
			}
		} else {
			$title .= ' - Trending new repositories';
		}
		$language_text = (strpbrk($this->language, ',+') !== false) ? 'languages' : 'language';
		$topic_text = (strpbrk($this->topic, ',+') !== false) ? 'topics' : 'topic';
		// Slug
		$slug = 'github';
		if ($this->language) {
			$slug .= '/language/' . strtolower(str_replace([' ', '+'], '-', $this->language));
		}
		if ($this->topic) {
			$slug .= '/topic/' . strtolower(str_replace([' ', '+'], '-', $this->topic));
		}
		$this->slug = $slug;
		// Description
		$description = "New repositories";
		if ($this->language || $this->topic) {
			if ($this->language) {
				$description .= " in $language_text \"" . $this->language . "\"";
			}
			if ($this->language && $this->topic) {
				$description .= " and in $topic_text \"" . $this->topic . "\"";
			} else if ($this->topic) {
				$description .= " with $topic_text \"" . $this->topic . "\"";
			}
		}
		$description .= " on " . $name . ", a platform for code hosting, version control, and collaboration among developers.";
		// Feed description
		$feed_description = "New repositories on " . $name;
		if ($this->language) {
			$feed_description .= " with $language_text \"" . $this->language . "\"";
		}
		if ($this->language && $this->topic) {
			$feed_description .= " and";
			$feed_description .= " $topic_text \"" . $this->topic . "\"";
		} else if ($this->topic) {
			$feed_description .= " with $topic_text \"" . $this->topic . "\"";
		}
		$this->platform             = "github";
		$this->name                 = $name;
		$this->title                = $title;
		$this->description          = $description;
		$this->url                  = 'https://github.com/';
		$this->icon                 = $this->platform_icon;
		$this->created              = '2008-02-08T00:00:00.000Z';
		$this->nsfw                 = false;
		$this->feed_title           = $this->title;
		$this->feed_description     = $feed_description;
		$this->needs_authentication = false;
		$this->is_community_valid   = true;
	}


	// Get top posts
	public function getTopPosts($limit, $period = null): array {
		$limit = $limit ?? $this->max_items_per_request;
		$cache_object_key = "top_repositories_";
		if ($this->language || $this->topic) {
			if ($this->language) {
				$cache_object_key .= "lang_" . strtolower($this->language) . "_";
			}
			if ($this->topic) {
				$cache_object_key .= "topic_" . strtolower($this->topic) . "_";
			}
		} else {
			$cache_object_key .= "all_";
		}
		$cache_object_key .= "limit_" . $limit;
		$cache_directory = "communities/github/hot_posts";
		if ($cache = cache()->get($cache_object_key, $cache_directory)) {
			return $cache;
		}
		$cache_expiration = HOT_POSTS_EXPIRATION;

		$top_posts = [];

		$created_since = date('Y-m-d', strtotime('-30 days'));
		$language = $this->language;
		$language_query = $language ? "%20language:$language" : '';
		$topic = $this->topic;
		$topic_query = $topic ? "%20topic:$topic" : '';
		$url = "https://api.github.com/search/repositories?sort=stars&order=desc&per_page=$limit&q=created:>$created_since$language_query$topic_query";
		$message = 'Fetching GitHub top repositories';
		if ($this->language) {
			$message .= ' with language "' . $this->language . '"';
		}
		if ($this->language && $this->topic) {
			$message .= ' and';
		} else if ($this->topic) {
			$message .= ' with';
		}
		if ($this->topic) {
			$message .= ' topic "' . $this->topic . '"';
		}
		$message .= ' from URL: ' . $url;
		logger()->info($message);

		$curl_response = curlURL($url);
		if (empty($curl_response)) {
			$message = 'Empty response when trying to get repositories for GitHub at ' . $url;
			logger()->error($message);
			return ['error' => $message];
		}

		$curl_data = json_decode($curl_response, true);
		if (empty($curl_data) || !empty($curl_data['error'])) {
			$message = 'There was an error communicating with GitHub: ' . ($curl_data['error'] ?? 'Unknown error');
			logger()->error($message);
			return ['error' => $message];
		}

		if (empty($curl_data['items'])) {
			$message = 'No repositories found in response when trying to get repositories for GitHub at ' . $url;
			logger()->error($message);
			return [];
		}

		foreach ($curl_data['items'] as $post_data) {
			$post = [
				'id'          => $post_data['id'] ?? 0,
				'title'       => $post_data['full_name'] ?? '',
				'score'       => $post_data['stargazers_count'] ?? 0,
				'url'         => $post_data['html_url'] ?? '',
				'created_at'  => $post_data['created_at'] ?? 0,
				'description' => $post_data['description'] ?? '',
				'avatar_url'  => $post_data['owner']['avatar_url'] ?? '',
			];
			$top_posts[] = $post;
		}

		cache()->set($cache_object_key, $top_posts, $cache_directory, $cache_expiration);
		return $top_posts;
	}


	// Get hot posts
	public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW): array {
		$limit = $limit ?? $this->max_items_per_request;
		$cache_object_key = "hot_repositories_";
		if ($this->language || $this->topic) {
			if ($this->language) {
				$cache_object_key .= "lang_" . strtolower($this->language) . "_";
			}
			if ($this->topic) {
				$cache_object_key .= "topic_" . strtolower($this->topic) . "_";
			}
		} else {
			$cache_object_key .= "all_";
		}
		$cache_object_key .= "limit_" . $limit;
		$cache_directory = "communities/github/hot_posts";
		if ($cache = cache()->get($cache_object_key, $cache_directory)) {
			return $cache;
		}
		$cache_expiration = HOT_POSTS_EXPIRATION;

		$hot_posts = $this->getTopPosts($limit);

		$hot_posts_min = [];
		foreach ($hot_posts as $post) {
			$post = new \Post\GitHub($post);
			$hot_posts_min[] = $post;
		}

		cache()->set($cache_object_key, $hot_posts_min, $cache_directory, $cache_expiration);
		return $hot_posts_min;
	}


	// Get monthly average top score
	public function getMonthlyAverageTopScore(): float {
		$top_posts = $this->getTopPosts($this->max_items_per_request);
		if (empty($top_posts)) {
			logger()->info('No top posts found for monthly average score calculation');
			return 0;
		}
		$total_score = array_sum(array_column($top_posts, 'score'));
		$average_score = $total_score / count($top_posts);
		$average_score = round($average_score, 1);
		return $average_score;
	}
}
