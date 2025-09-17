<?php

namespace Community;

class Mbin extends Community
{
  // https://docs.joinmbin.org/api#tag/magazine

  // Properties
  public $platform              = 'mbin';
  public $needs_authentication  = false;
  public $instance              = '';
  public $slug                  = '';
  public $magazine_id           = 0;
  public $platform_icon         = UPVOTE_RSS_URI . 'img/platforms/mbin.png';
  public $max_items_per_request = 50;


  // Constructor
  function __construct(
    $slug = null,
    $instance = null
  ) {
    if (!empty($slug)) {
      $this->slug = $slug;
    } else {
      $this->slug = DEFAULT_MBIN_COMMUNITY;
    }
    if (!empty($instance)) {
      $this->instance = $instance;
    } else {
      $this->instance = DEFAULT_MBIN_INSTANCE;
    }
    $this->getCommunityInfo();
  }


  // Enable loading from cache
  static function __set_state($array) {
    $community = new Community\Mbin();
    foreach ($array as $key => $value) {
      $community->{$key} = $value;
    }
    return $community;
  }


  protected function getInstanceInfo() {
    $url = "https://$this->instance/api/instance";
    $curl_response = curlURL($url, [CURLOPT_TIMEOUT => 20]);
    if (empty($curl_response)) {
      $message = "The Mbin instance $this->instance is not reachable";
      logger()->error($message);
      return ['error' => $message];
    }
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data)) {
      $message = "The Mbin instance $this->instance is not reachable";
      logger()->error($message);
      return ['error' => $message];
    }
    if (!empty($curl_data['error'])) {
      $message = "Error retrieving data for the Mbin instance $this->instance: " . ($curl_data['error'] ?? 'Unknown error');
      logger()->error($message);
      return ['error' => $message];
    }
    if (!empty($curl_data['about']) && empty($curl_data['error'])) {
      $this->is_instance_valid = true;
    }
  }


  protected function getCommunityInfo() {
    // Check cache directory first
    $info_directory = "communities/mbin/$this->instance/$this->slug/about";
    $info = cache()->get($this->slug, $info_directory);
    if (!empty($info)) {
      $this->is_instance_valid = true;
      $this->is_community_valid = true;
    } else {
      // Check if instance is valid
      $this->getInstanceInfo();
      if (!$this->is_instance_valid) {
        logger()->error("The Mbin instance $this->instance is not reachable");
        return;
      }
      // Get community info
      $this->instance = rtrim(preg_replace('/^https?:\/\//', '', $this->instance), '/');
      $url = "https://$this->instance/api/magazine/name/$this->slug";
      $curl_response = curlURL($url, [CURLOPT_TIMEOUT => 20]);
      if (empty($curl_response)) {
        logger()->error("Failed to get data for the requested Mbin magazine $this->slug at the $this->instance instance");
        return;
      }
      $info = json_decode($curl_response, true);
      if (empty($info)) {
        logger()->error("Failed to get data for the requested Mbin magazine $this->slug at the $this->instance instance");
        return;
      }
      if (empty($info['magazineId'])) {
        logger()->error("The requested Mbin magazine $this->slug does not exist at the $this->instance instance");
        return;
      }
      if (!empty($info['error']) && $info['error'] === 'couldnt_find_magazine') {
        logger()->warning("Mbin magazine $this->slug not found at the $this->instance instance");
        return;
      }
      if (!empty($info['error'])) {
        logger()->error("Error retrieving data for the requested Mbin magazine $this->slug at the $this->instance instance: " . ($info['error'] ?? 'Unknown error'));
        return;
      }
      $this->is_community_valid = true;
      $this->magazine_id = $info['magazineId'];
      logger()->info("Mbin magazine $this->slug found at the $this->instance instance with ID $this->magazine_id");
      if (!empty($info['icon']['storageUrl']) && !remote_file_exists($info['icon']['storageUrl'])) {
        logger()->warning("Mbin magazine $this->slug icon not found at the $this->instance instance. Falling back to source URL.");
        $info['icon']['storageUrl'] = $info['icon']['sourceUrl'];
      }
      if (!empty($info['icon']['storageUrl']) && !remote_file_exists($info['icon']['storageUrl'])) {
        logger()->warning("Mbin magazine $this->slug icon not found at the $this->instance instance");
        $info['icon']['storageUrl'] = '';
      }
      cache()->set($this->slug, $info, $info_directory, ABOUT_EXPIRATION);
    }
    // Set community properties
    $description = $info['description'] ?? '';
    if($description) {
      $description = preg_replace('/\[(.*?)\]\((.*?)\)/', "$1", $description);
      $description = strip_tags($description);
      $description = str_replace(["\n", "\r"], '', $description);
      $description = preg_replace('/\s+/', ' ', $description);
      $description = trim($description);
      $description = preg_replace('/((\w+\W+){80}(\w+))(.*)/', '${1}' . '…', $description);
    }
    $this->name             = $info['name'] ?? $this->slug;
    $this->title            = $info['title'] ?? $this->slug;
    $this->description      = $description;
    $this->url              = "https://$this->instance/m/$this->slug";
    $this->icon             = $info['icon']['storageUrl'] ?? '';
    $this->nsfw             = $info['isAdult'] ?? false;
    $this->subscribers      = $info['subscriptionsCount'] ?? 0;
    $this->created          = normalizeTimestamp($info['createdAt'] ?? 0);
    $this->feed_title       = "Mbin - " . $this->title;
    $this->feed_description = "Hot posts at " . $this->url;
  }


  // Get top posts
  public function getTopPosts($limit, $period = null) {
    if (!$this->is_community_valid) {
      logger()->error("The requested Mbin community $this->slug does not exist at the $this->instance instance");
      return [];
    }
    if (empty($limit) || $limit < $this->max_items_per_request) {
      $limit = $this->max_items_per_request;
    }
    $number_of_requests = ceil($limit / $this->max_items_per_request);
    $limit = $number_of_requests * $this->max_items_per_request;
    $cache_object_key = "$this->slug-top";
    $cache_directory = "communities/mbin/$this->instance/$this->slug/top_posts";
    $cache_expiration = TOP_DAILY_POSTS_EXPIRATION;
    if ($period && $period == 'month') {
      $cache_expiration = TOP_MONTHLY_POSTS_EXPIRATION;
    }
    // Get posts documentation: https://docs.joinmbin.org/api/#tag/magazine/operation/get_api_magazine_entries_retrieve
    $base_url = "https://$this->instance/api/magazine/$this->magazine_id/entries?perPage=$this->max_items_per_request&sort=top";
    if ($period) {
      $cache_object_key = "$this->slug-top-$period";
      $cache_directory = "communities/mbin/$this->instance/$this->slug/top_posts_$period";
      $period = $period === 'month' ? '1m' : '1d';
      $base_url .= "&time=$period";
    }
    if ($top_posts = cache()->get($cache_object_key, $cache_directory)) {
      if (count($top_posts) >= $limit) {
        return array_slice($top_posts, 0, $limit);
      }
    }
    $top_posts = [];
    $progress_cache_object_key = "progress_" . $this->platform . "_" . $this->slug;
    $progress_cache_directory = "progress";
    if (INCLUDE_PROGRESS) {
      cache()->delete($progress_cache_object_key, $progress_cache_directory);
    }
    for ($i = 1; $i <= $number_of_requests; $i++) {
      $progress = [
        'current' => $i,
        'total' => $number_of_requests + 1
      ];
      if (INCLUDE_PROGRESS) {
        cache()->set($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
      }
      $url = $base_url . "&p=$i";
      $page_cache_object_key = "$this->slug-top-$period-limit-$this->max_items_per_request-page-$i";
      if (cache()->get($page_cache_object_key, $cache_directory)) {
        $top_posts = array_merge($top_posts, cache()->get($page_cache_object_key, $cache_directory));
      } else {
        $curl_response = curlURL($url, [CURLOPT_TIMEOUT => 20]);
        if (empty($curl_response)) {
          $message = "Failed to get top posts for Mbin community $this->slug at the $this->instance instance";
          logger()->error($message);
          return ['error' => $message];
        }
        $curl_data = json_decode($curl_response, true);
        if (empty($curl_data['items'])) {
          logger()->info("No items found for $url");
          continue;
        }
        $paged_top_posts = [];
        foreach ($curl_data['items'] as $post) {
          if (empty($post['entryId']) || empty($post['favourites'])) {
            continue;
          }
          $post_min = [
            'id' => $post['entryId'],
            'score' => $post['favourites']
          ];
          $paged_top_posts[] = $post_min;
          $top_posts[] = $post_min;
        }
        cache()->set($page_cache_object_key, $paged_top_posts, $cache_directory, TOP_POSTS_EXPIRATION);
      }
    }
    $top_posts = array_slice($top_posts, 0, $limit);
    usort($top_posts, function ($a, $b) {
      return $b['score'] <=> $a['score'];
    });
    cache()->set($cache_object_key, $top_posts, $cache_directory, $cache_expiration);
    if (INCLUDE_PROGRESS)
      cache()->set($progress_cache_object_key, ['current' => 99, 'total' => 100], $progress_cache_directory, 1);
    return $top_posts;
  }


  // Get hot posts
  public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW) {
    if (!$this->is_community_valid) {
      logger()->error("The requested Mbin community $this->slug does not exist at the $this->instance instance");
      return [];
    }
    $limit = $limit ?? $this->max_items_per_request;
    $cache_object_key = "$this->slug-hot-limit-$limit-min";
    $cache_directory = "communities/mbin/$this->instance/$this->slug/hot_posts";
    // Get posts documentation: https://docs.joinmbin.org/api/#tag/magazine/operation/get_api_magazine_entries_retrieve
    $url = "https://" . $this->instance . "/api/magazine/" . $this->magazine_id . "/entries?perPage=" . $this->max_items_per_request . "&sort=hot";
    if (cache()->get($cache_object_key, $cache_directory)) {
      return cache()->get($cache_object_key, $cache_directory);
    }
    $curl_response = curlURL($url, [CURLOPT_TIMEOUT => 20]);
    $curl_data = json_decode($curl_response, true) ?? [];
    if (empty($curl_data) || !empty($curl_data['error'])) {
      $message = "Error communicating with the $this->instance instance: " . ($curl_data['error'] ?? 'Unknown error');
      logger()->error($message);
      return ['error' => $message];
    }
    cache()->set($cache_object_key, $curl_data, $cache_directory, HOT_POSTS_EXPIRATION);
    $hot_posts_min = array();
    if (empty($curl_data['items'])) {
      logger()->info("No items found for hot posts from $url");
      return $hot_posts_min;
    }
    foreach ($curl_data['items'] as $post) {
      $post = new \Post\Mbin($post, $this->instance, $this->slug);
      $hot_posts_min[] = $post;
    }
    cache()->set($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
    return $hot_posts_min;
  }


  // Get monthly average top score
  public function getMonthlyAverageTopScore() {
    if (!$this->is_community_valid) {
      logger()->error("The requested Mbin community $this->slug does not exist at the $this->instance instance");
      return 0;
    }
    $cache_object_key = "$this->slug-month-average-top-score";
    $cache_directory = "communities/mbin/$this->instance/$this->slug/top_posts_month";
    // Use cached score if present
    if (cache()->get($cache_object_key, $cache_directory)) {
      return cache()->get($cache_object_key, $cache_directory);
    }
    $top_posts = $this->getTopPosts($this->max_items_per_request, 'month');
    $total_score = 0;
    foreach ($top_posts as $post) {
      $total_score += $post['score'];
    }
    if (count($top_posts) == 0) {
      return 0;
    }
    $average_score = floor($total_score / count($top_posts));
    logger()->info("Monthly average top score calculated for $this->instance community $this->slug: $average_score");
    cache()->set($cache_object_key, $average_score, $cache_directory, TOP_MONTHLY_POSTS_EXPIRATION);
    return $average_score;
  }
}
