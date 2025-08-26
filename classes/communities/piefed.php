<?php

namespace Community;

class PieFed extends Community
{
  // https://piefed.social/api/alpha

  // Properties
  public $platform              = 'piefed';
  public $needs_authentication  = false;
  public $instance              = '';
  public $slug                  = '';
  public $platform_icon         = 'https://piefed.social/static/media/logo_8p7en.svg';
  public $max_items_per_request = 50;


  // Constructor
  function __construct(
    $slug = null,
    $instance = null
  ) {
    if (!empty($slug)) $this->slug = $slug;
    else $this->slug = DEFAULT_PIEFED_COMMUNITY;
    if (!empty($instance)) $this->instance = $instance;
    else $this->instance = DEFAULT_PIEFED_INSTANCE;
    $this->getCommunityInfo();
  }


  // Enable loading from cache
  static function __set_state($array) {
    $community = new Community\PieFed();
    foreach ($array as $key => $value) {
      $community->{$key} = $value;
    }
    return $community;
  }


  protected function getInstanceInfo() {
    $log = \CustomLogger::getLogger();
    $url = "https://$this->instance/api/alpha/site";
    $curl_response = curlURL($url);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data)) {
      $message = "The PieFed instance $this->instance is not reachable";
      $log->error($message);
      return ['error' => $message];
    }
    if (!empty($curl_data['site']) && empty($curl_data['error'])) {
      $this->is_instance_valid = true;
    } elseif (!empty($curl_data['error'])) {
      $message = "Error retrieving data for the PieFed instance $this->instance: " . ($curl_data['error'] ?? 'Unknown error');
      $log->error($message);
      return ['error' => $message];
    }
  }


  protected function getCommunityInfo() {
    $log = \CustomLogger::getLogger();
    // Check cache directory first
    $info_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/piefed/$this->instance/$this->slug/about/";
    $info = cacheGet($this->slug, $info_directory);
    if (!empty($info)) {
      $this->is_instance_valid = true;
      $this->is_community_valid = true;
    } else {
      // Check if instance is valid
      $this->getInstanceInfo();
      if ($this->is_instance_valid) {
        // Get community info
        $this->instance = rtrim(preg_replace('/^https?:\/\//', '', $this->instance), '/');
        $url = "https://$this->instance/api/alpha/community?name=$this->slug";
        $curl_response = curlURL($url);
        $info = json_decode($curl_response, true);
        if (!empty($info['community_view']) && empty($info['error'])) {
          $this->is_community_valid = true;
          cacheSet($this->slug, $info, $info_directory, ABOUT_EXPIRATION);
        } else {
          $this->is_community_valid = false;
          if (!empty($info['error']) && $info['error'] == 'couldnt_find_community') {
            $log->error("The requested PieFed community $this->slug does not exist at the $this->instance instance");
          } else {
            $log->error("Error retrieving data for the requested PieFed community $this->slug at the $this->instance instance: " . ($info['error'] ?? 'Unknown error'));
          }
          return;
        }
      }
    }
    if ($this->is_instance_valid && !empty($info['community_view']) && empty($info['error'])) {
      $community = $info['community_view']['community'];
      $counts = $info['community_view']['counts'];
      $community_icon = $community['icon'] ?? $this->platform_icon ?? '';
      $description = $community['description'] ?? '';
      if($description) {
        $description = preg_replace('/\[(.*?)\]\((.*?)\)/', "$1", $description);
        $description = strip_tags($description);
        $description = str_replace(["\n", "\r"], '', $description);
        $description = preg_replace('/\s+/', ' ', $description);
        $description = trim($description);
        $description = preg_replace('/((\w+\W+){80}(\w+))(.*)/', '${1}' . '…', $description);
      }
      $this->name               = !empty($community['name']) ? $community['name'] : $this->slug;
      $this->title              = !empty($community['title']) ? $community['title'] : $this->slug;
      $this->description        = $description;
      $this->url                = !empty($community['actor_id']) ? $community['actor_id'] : '';
      $this->icon               = $community_icon;
      $this->banner_image       = !empty($community['banner']) ? $community['banner'] : '';
      $this->created            = !empty($community['published']) ? $community['published'] : '';
      $this->nsfw               = !empty($community['nsfw']) ? $community['nsfw'] : false;
      $this->subscribers        = !empty($counts['subscribers']) ? $counts['subscribers'] : 0;
      $this->feed_title         = "PieFed - " . $this->title;
      $this->feed_description   = "Hot posts at " . $this->url;
      $this->is_community_valid = true;
    }
  }


  // Get top posts
  public function getTopPosts($limit, $period = null) {
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested PieFed community $this->slug does not exist at the $this->instance instance");
      return [];
    }
    if (empty($limit) || $limit < $this->max_items_per_request) {
      $limit = $this->max_items_per_request;
    }
    $number_of_requests = ceil($limit / $this->max_items_per_request);
    $limit = $number_of_requests * $this->max_items_per_request;
    $cache_object_key = "$this->slug-top";
    $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/piefed/$this->instance/$this->slug/top_posts/";
    $cache_expiration = TOP_DAILY_POSTS_EXPIRATION;
    if ($period && $period == 'month') {
      $cache_expiration = TOP_MONTHLY_POSTS_EXPIRATION;
    }
    // Listing types: https://github.com/LemmyNet/lemmy-js-client/blob/main/src/types/ListingType.ts
    // Sort types: https://github.com/LemmyNet/lemmy-js-client/blob/main/src/types/SortType.ts
    $base_url = "https://$this->instance/api/alpha/post/list?community_name=$this->slug&limit=$this->max_items_per_request&sort=TopDay&type_=All";
    if ($period) {
      $cache_object_key = "$this->slug-top-$period";
      $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/piefed/$this->instance/$this->slug/top_posts_$period/";
      $period = ucfirst($period);
      $base_url = "https://$this->instance/api/alpha/post/list?community_name=$this->slug&limit=$this->max_items_per_request&sort=Top$period&type_=All";
    }
    if ($top_posts = cacheGet($cache_object_key, $cache_directory)) {
      if (count($top_posts) >= $limit) {
        return array_slice($top_posts, 0, $limit);
      }
    }
    $top_posts = [];
    $progress_cache_object_key = "progress_" . $this->platform . "_" . $this->slug;
    $progress_cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/progress/";
    if (INCLUDE_PROGRESS) {
      cacheDelete($progress_cache_object_key, $progress_cache_directory);
    }
    for ($i = 1; $i <= $number_of_requests; $i++) {
      $progress = [
        'current' => $i,
        'total' => $number_of_requests + 1
      ];
      if (INCLUDE_PROGRESS) {
        cacheSet($progress_cache_object_key, $progress, $progress_cache_directory, PROGRESS_EXPIRATION);
      }
      $url = $base_url . "&page=$i";
      $page_cache_object_key = "$this->slug-top-$period-limit-$this->max_items_per_request-page-$i";
      if (cacheGet($page_cache_object_key, $cache_directory)) {
        $top_posts = array_merge($top_posts, cacheGet($page_cache_object_key, $cache_directory));
      } else {
        $curl_response = curlURL($url);
        $curl_data = json_decode($curl_response, true);
        if (!empty($curl_data['posts'])) {
          $paged_top_posts = [];
          foreach ($curl_data['posts'] as $post) {
            if (empty($post['post']['id']) || empty($post['counts']['score'])) {
              continue;
            }
            $post_min = [
              'id' => $post['post']['id'],
              'score' => $post['counts']['score']
            ];
            $paged_top_posts[] = $post_min;
            $top_posts[] = $post_min;
          }
          cacheSet($page_cache_object_key, $paged_top_posts, $cache_directory, TOP_POSTS_EXPIRATION);
        }
      }
      // if ($i < $number_of_requests) sleep(1);
    }
    $top_posts = array_slice($top_posts, 0, $limit);
    usort($top_posts, function ($a, $b) {
      return $b['score'] <=> $a['score'];
    });
    cacheSet($cache_object_key, $top_posts, $cache_directory, $cache_expiration);
    if (INCLUDE_PROGRESS)
      cacheSet($progress_cache_object_key, ['current' => 99, 'total' => 100], $progress_cache_directory, 1);
    return $top_posts;
  }


  // Get hot posts
  public function getHotPosts($limit, $filter_nsfw = FILTER_NSFW, $blur_nsfw = BLUR_NSFW) {
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested PieFed community $this->slug does not exist at the $this->instance instance");
      return [];
    }
    $limit = $limit ?? $this->max_items_per_request;
    $cache_object_key = "$this->slug-hot-limit-$limit-min";
    $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/piefed/$this->instance/$this->slug/hot_posts/";
    // Listing types: https://github.com/LemmyNet/lemmy-js-client/blob/main/src/types/ListingType.ts
    // Sort types: https://github.com/LemmyNet/lemmy-js-client/blob/main/src/types/SortType.ts
    $url = "https://$this->instance/api/alpha/post/list?community_name=$this->slug&limit=$limit&sort=Hot&type_=All";
    if (cacheGet($cache_object_key, $cache_directory)) {
      return cacheGet($cache_object_key, $cache_directory);
    }
    $curl_response = curlURL($url);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data) || !empty($curl_data['error'])) {
      $message = "Error communicating with the $this->instance instance: " . ($curl_data['error'] ?? 'Unknown error');
      $log->error($message);
      return ['error' => $message];
    }
    cacheSet($cache_object_key, $curl_data, $cache_directory, HOT_POSTS_EXPIRATION);
    $hot_posts_min = array();
    foreach ($curl_data['posts'] as $post) {
      $post = new \Post\PieFed($post, $this->instance, $this->slug);
      $hot_posts_min[] = $post;
    }
    cacheSet($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
    return $hot_posts_min;
  }


  // Get monthly average top score
  public function getMonthlyAverageTopScore() {
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested PieFed community $this->slug does not exist at the $this->instance instance");
      return 0;
    }
    $cache_object_key = "$this->slug-month-average-top-score";
    $cache_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/communities/piefed/$this->instance/$this->slug/top_posts_month/";
    // Use cached score if present
    if (cacheGet($cache_object_key, $cache_directory)) {
      return cacheGet($cache_object_key, $cache_directory);
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
    $log->info("Monthly average top score calculated for $this->instance community $this->slug: $average_score");
    cacheSet($cache_object_key, $average_score, $cache_directory, TOP_MONTHLY_POSTS_EXPIRATION);
    return $average_score;
  }

}
