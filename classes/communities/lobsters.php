<?php

namespace Community;

class Lobsters extends Community
{

	// Properties
	public $platform = 'lobsters';
	public $instance = DEFAULT_LOBSTERS_INSTANCE;
	public $is_instance_valid = true;
	public $platform_icon = UPVOTE_RSS_URI . 'img/platforms/lobsters.png';
  public $community_type = '';
	public $slug_title;
	public $max_items_per_request = 25;

	// Constructor
	function __construct(
		$slug = null,
    $community_type = null
	) {
    $this->slug = $slug ?? DEFAULT_LOBSTERS_COMMUNITY;
    $this->community_type = $community_type ?? DEFAULT_LOBSTERS_COMMUNITY;
		$this->getCommunityInfo();
	}


	// Enable loading from cache
	static function __set_state($array) {
		$community = new Community\Lobsters();
		foreach ($array as $key => $value) {
			$community->{$key} = $value;
		}
		return $community;
	}


	protected function getInstanceInfo() {}


	protected function getCommunityInfo() {
		if (!in_array($this->community_type, ['all', 'category', 'tag'])) {
			$message = "The requested Lobsters community type $this->community_type does not exist";
			logger()->error($message);
			return ['error' => $message];
		}
    // Check cache directory first
    $info_directory = "communities/lobsters/$this->slug/about";
    if ($this->community_type === 'category') {
      $info_directory = "communities/lobsters/category/$this->slug/about";
    } elseif ($this->community_type === 'tag') {
      $info_directory = "communities/lobsters/tag/$this->slug/about";
    }
    $info = cache()->get($this->slug, $info_directory);
    if (empty($info)) {
      // Set community info for "all" type
      $title = "Lobsters - Hottest";
      $slug_title = "Hottest";
      $description = "A computing-focused community centered around link aggregation and discussion.";
      $url = "https://$this->instance";
      $feed_description = "\"Hottest\" posts at Lobsters";
      // Set community info for "category" type
      if ($this->community_type === 'category') {
        $title = "Lobsters category: " . $this->slug;
        $slug_title = "category/" . $this->slug;
        $description = "Stories with tags in the \"" . $this->slug . "\" category at Lobsters.";
        $url = "https://$this->instance/categories/" . $this->slug;
        $feed_description = "\"category/" . $this->slug . "\" posts at Lobsters";
      }
      // Set community info for "tag" type
      if ($this->community_type === 'tag') {
        $title = "Lobsters tag: " . $this->slug;
        $slug_title = "tag/" . $this->slug;
        $description = "Stories tagged as \"" . $this->slug . "\" at Lobsters.";
        $url = "https://$this->instance/t/" . $this->slug;
        $feed_description = "\"tag/" . $this->slug . "\" posts at Lobsters";
      }
      // Check if the community exists
      if (!remote_file_exists($url)) {
        logger()->error("The requested Lobsters community type $this->slug does not exist");
        return;
      }
      $info = [
        'title' => $title,
        'slug_title' => $slug_title,
        'description' => $description,
        'url' => $url,
        'feed_description' => $feed_description,
      ];
      cache()->set($this->slug, $info, $info_directory, ABOUT_EXPIRATION);
    }
		$this->platform             = "lobsters";
		$this->name                 = "Lobsters";
		$this->title                = $info['title'];
		$this->slug_title           = $info['slug_title'];
		$this->description          = $info['description'];
		$this->url                  = $info['url'];
		$this->icon                 = $this->platform_icon;
		$this->created              = '2012-07-03T00:00:00.000Z';
		$this->nsfw                 = false;
		$this->feed_title           = $info['title'];
		$this->feed_description     = $info['feed_description'];
		$this->needs_authentication = false;
		$this->is_community_valid   = true;
	}


  public static function getCommunityTypes(): array {
    return [
			'all'      => 'All posts',
			'category' => 'Category',
			'tag'      => 'Tag'
		];
	}


  // Get top posts
  public function getTopPosts($limit, $period = null) {
    if (!$this->is_community_valid) {
      logger()->error("The requested Lobsters community $this->slug does not exist");
      return [];
    }
    if (empty($limit) || $limit < $this->max_items_per_request) {
      $limit = $this->max_items_per_request;
    }
    $number_of_requests = ceil($limit / $this->max_items_per_request);
    $limit = $number_of_requests * $this->max_items_per_request;
    // Use cached top posts if present
    $cache_object_key = "$this->slug-top";
    $cache_directory = "communities/lobsters/$this->slug";
    $base_url = "https://$this->instance/top/page/";
    if ($this->community_type === 'category') {
      $cache_directory = "communities/lobsters/category/$this->slug/top_posts_month";
      $base_url = "https://$this->instance/categories/$this->slug.json";
    }
    if ($this->community_type === 'tag') {
      $cache_directory = "communities/lobsters/tag/$this->slug/top_posts_month";
      $base_url = "https://$this->instance/t/$this->slug.json";
    }
    $cache_expiration = TOP_DAILY_POSTS_EXPIRATION;
    $top_posts = cache()->get($cache_object_key, $cache_directory) ?: [];
    if (count($top_posts)) {
      return array_slice($top_posts, 0, $limit);
    }
    $progress_cache_object_key = "progress_" . $this->platform . "_" . $this->slug;
    $progress_cache_directory = "progress";
    if (INCLUDE_PROGRESS) {
      cache()->delete($progress_cache_object_key, $progress_cache_directory);
    }
    $seen_ids = [];
    $cutoff_days = 30;
    $cutoff_date = new \DateTime();
    $cutoff_date->modify("-$cutoff_days days");
    // Loop through each page of top posts
    for ($i = 1; $i <= $number_of_requests; $i++) {
      $progress = [
        'current' => $i,
        'total' => $number_of_requests + 1
      ];
      if (INCLUDE_PROGRESS) {
        cache()->set($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
      }
      $page_cache_object_key = "$this->slug-top-limit-$this->max_items_per_request-page-$i";
      if (cache()->get($page_cache_object_key, $cache_directory)) {
        $top_posts = array_merge($top_posts, cache()->get($page_cache_object_key, $cache_directory));
      } else {
        $url = $base_url . "$i";
        // All posts
        if ($this->slug === 'all') {
          $curl_response = curlURL($url);
          $dom = new \DOMDocument();
          libxml_use_internal_errors(true);
          $dom->loadHTML($curl_response);
          libxml_clear_errors();
          $xpath = new \DOMXPath($dom);
          $stories = $xpath->query("//li[contains(@class, 'story')]");
          if (!empty($stories)) {
            $paged_top_posts = [];
            foreach ($stories as $story) {
              $id = $story->getAttribute('data-shortid');
              $scoreNode = $xpath->query(".//div[contains(@class, 'score')]", $story)->item(0);
              $score = $scoreNode ? $scoreNode->nodeValue : null;
              if (empty($id) || empty($score)) {
                continue;
              }
              if (in_array($id, $seen_ids)) {
                logger()->info("Duplicate story ID $id detected, breaking the loop.");
                break 2; // Break out of both foreach and for loops
              }
              $seen_ids[] = $id;
              $titleNode = $xpath->query(".//span[@role='heading']//a[contains(@class, 'u-url')]", $story)->item(0);
              $title = $titleNode ? $titleNode->nodeValue : null;
              $url = $titleNode ? $titleNode->getAttribute('href') : null;
              $domainNode = $xpath->query(".//a[contains(@class, 'domain')]", $story)->item(0);
              $domain = $domainNode ? $domainNode->nodeValue : null;
              $dateNode = $xpath->query(".//span[@title]", $story)->item(0);
              $date = $dateNode ? $dateNode->getAttribute('title') : null;
              $date = strtotime($date);
              if ($date && $date < $cutoff_date) {
                logger()->info("Story ID $id is older than $cutoff_days days, breaking the loop.");
                break 2; // Break out of both foreach and for loops
              }
              $post_min = [
                'short_id'   => $id,
                'score'      => $score,
                'title'      => $title,
                'url'        => $url,
                'domain'     => $domain,
                'created_at' => $date,
              ];
              if ($score !== null) {
                $paged_top_posts[] = $post_min;
                $top_posts[] = $post_min;
              }
            }
          }
        } elseif (in_array($this->community_type, ['category', 'tag'])) {
          // Category or tag posts
          $url = $base_url . "?page=$i";
          $curl_response = curlURL($url);
          $curl_data = json_decode($curl_response, true);
          if (empty($curl_data)) {
            $message = "Error communicating with Lobsters: " . ($curl_data['error'] ?? 'Unknown error');
            logger()->error($message);
            return ['error' => $message];
          }
          $paged_top_posts = [];
          foreach ($curl_data as $post) {
            $domain = $post['url'] ? parse_url($post['url'], PHP_URL_HOST) : null;
            $date = $post['created_at'] ? strtotime($post['created_at']) : null;
            if ($date && $date < $cutoff_date) {
              logger()->info("Story ID " . $post['short_id'] . " is older than 30 days, breaking the loop.");
              break 2; // Break out of both foreach and for loops
            }
            $post_min = [
              'short_id'          => $post['short_id'],
              'short_id_url'      => $post['short_id_url'],
              'score'             => $post['score'],
              'title'             => $post['title'],
              'url'               => $post['url'],
              'domain'            => $domain,
              'date'              => $date,
              'created_at'        => $date,
              'description'       => $post['description'],
              'description_plain' => $post['description_plain'],
            ];
            $paged_top_posts[] = $post_min;
            $top_posts[] = $post_min;
          }
        }
        cache()->set($page_cache_object_key, $paged_top_posts, $cache_directory, TOP_POSTS_EXPIRATION);
      }
    }
    $top_posts = array_filter($top_posts, function ($post) use ($cutoff_date) {
      return $post['created_at'] >= $cutoff_date;
    });
    $top_posts = array_slice($top_posts, 0, $limit);
    usort($top_posts, function ($a, $b) {
      return $b['score'] <=> $a['score'];
    });
    if (!empty($top_posts)) {
      cache()->set($cache_object_key, $top_posts, $cache_directory, $cache_expiration);
    }
    if (INCLUDE_PROGRESS) {
      cache()->set($progress_cache_object_key, ['current' => 99, 'total' => 100], $progress_cache_directory, 1);
    }
    return $top_posts;
  }


  // Get hot posts
  public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW) {
    if (!$this->is_community_valid) {
      logger()->error("The requested Lobsters community $this->slug does not exist");
      return [];
    }
    $limit = $limit ?? $this->max_items_per_request;
    // All posts
    if ($this->community_type === 'all') {
      $cache_object_key = "$this->slug-hot-limit-$limit-min";
      $cache_directory = "communities/lobsters/$this->slug/hot_posts";
      if (cache()->get($cache_object_key, $cache_directory)) {
        return cache()->get($cache_object_key, $cache_directory);
      }
      $url = "https://$this->instance/active.json";
      $curl_response = curlURL($url);
      $curl_data = json_decode($curl_response, true);
      if (empty($curl_data)) {
        $message = "Error communicating with Lobsters: " . ($curl_data['error'] ?? 'Unknown error');
        logger()->error($message);
        return ['error' => $message];
      }
      $hot_posts_min = array();
      foreach ($curl_data as $post) {
        $post = new \Post\Lobsters($post, $this->instance, $this->slug, $this->community_type);
        $hot_posts_min[] = $post;
      }
      cache()->set($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
      return $hot_posts_min;
    }
    // Category posts
    if ($this->community_type === 'category') {
      $cache_object_key = "$this->slug-hot-limit-$limit-min";
      $cache_directory = "communities/lobsters/category/$this->slug/hot_posts";
      if (cache()->get($cache_object_key, $cache_directory)) {
        return cache()->get($cache_object_key, $cache_directory);
      }
      $top_posts = $this->getTopPosts($limit);
      $hot_posts_min = array();
      foreach ($top_posts as $post) {
        $post = new \Post\Lobsters($post, $this->instance, $this->slug, $this->community_type);
        $hot_posts_min[] = $post;
      }
      cache()->set($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
      return $hot_posts_min;
    }
    // Tag posts
    if ($this->community_type === 'tag') {
      $cache_object_key = "$this->slug-hot-limit-$limit-min";
      $cache_directory = "communities/lobsters/tag/$this->slug/hot_posts";
      if (cache()->get($cache_object_key, $cache_directory)) {
        return cache()->get($cache_object_key, $cache_directory);
      }
      $top_posts = $this->getTopPosts($limit);
      $hot_posts_min = array();
      foreach ($top_posts as $post) {
        $post = new \Post\Lobsters($post, $this->instance, $this->slug, $this->community_type);
        $hot_posts_min[] = $post;
      }
      cache()->set($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
      return $hot_posts_min;
    }
  }


  // Get monthly average top score
  public function getMonthlyAverageTopScore() {
    $cache_object_key = "$this->slug-month-average-top-score";
    $cache_directory = "communities/lobsters/$this->slug";
    if ($this->community_type === 'category') {
      $cache_directory = "communities/lobsters/category/$this->slug/top_posts_month";
    }
    if ($this->community_type === 'tag') {
      $cache_directory = "communities/lobsters/tag/$this->slug/top_posts_month";
    }
    // Use cached score if present
    if (cache()->get($cache_object_key, $cache_directory)) {
      return cache()->get($cache_object_key, $cache_directory);
    }
    $top_posts = $this->getTopPosts(600, 'month');
    $total_score = 0;
    foreach ($top_posts as $post) {
      $total_score += $post['score'];
    }
    if (count($top_posts) == 0) {
      return 0;
    }
    $average_score = floor($total_score / count($top_posts));
    $message = "Monthly average top score calculated for $this->instance community $this->slug: $average_score";
    if ($this->community_type === 'category') {
      $message = "Monthly average top score calculated for $this->instance community category $this->slug: $average_score";
    }
    if ($this->community_type === 'tag') {
      $message = "Monthly average top score calculated for $this->instance community tag $this->slug: $average_score";
    }
    logger()->info($message);
    cache()->set($cache_object_key, $average_score, $cache_directory, TOP_MONTHLY_POSTS_EXPIRATION);
    return $average_score;
  }

}
