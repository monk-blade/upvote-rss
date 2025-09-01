<?php

namespace Community;

class Reddit extends Community
{
  private $auth;

  // Properties
  public  $platform              = 'reddit';
  public  $platform_icon         = 'https://www.redditstatic.com/mweb2x/favicon/76x76.png';
  public  $needs_authentication  = true;
  public  $instance              = 'reddit.com';
  public  $is_instance_valid     = true;
  public  $is_private            = false;
  public  $max_items_per_request = 100;
  private $auth_token            = null;


  // Constructor
  function __construct($slug = null) {
    $this->auth = new \Auth\Reddit();
    $this->slug = !empty($slug) ? $slug : SUBREDDIT;
    try {
      $this->auth_token = $this->auth->getToken();
    } catch (\Exception $e) {
      return;
    }
    $this->getCommunityInfo();
  }


  // Enable loading from cache
  static function __set_state($array) {
    $post = new Reddit([]);
    foreach ($array as $key => $value) {
      $post->{$key} = $value;
    }
    return $post;
  }


  // Get instance info
  protected function getInstanceInfo() {}


  // Get subreddit info
  protected function getCommunityInfo() {
    $log = \CustomLogger::getLogger();
    // Check cache directory first
    $info_directory = "communities/reddit/$this->slug/about";
    $info = cache()->get($this->slug, $info_directory);
    if (empty($info)) {
      if ($this->slug === 'all') {
        $url = "https://oauth.reddit.com/r/all.json";
        $info = [
          'data' => [
            'display_name'       => 'r/all',
            'title'              => 'r/all',
            'public_description' => 'The front page of the internet',
            'banner_img'         => '',
            'created_utc'        => 0,
            'over18'             => false,
            'subscribers'        => 0,
            'is_private'         => false,
            'icon_img'           => $this->platform_icon
          ]
        ];
        cache()->set($this->slug, $info, $info_directory, ABOUT_EXPIRATION);
      }
      // Get authenticated
      elseif ($this->auth_token) {
        // Get subreddit info
        $url = "https://oauth.reddit.com/r/$this->slug/about.json";
        $curl_response = curlURL(
          $url,
          [
            CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->auth_token),
            CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')'
          ]
        );
        $info = json_decode($curl_response, true);
        if (isset($info['data'])) {
          if (!empty($info['kind']) && $info['kind'] == 'Listing') {
            $log->error("The requested subreddit $this->slug does not exist");
            return;
          }
          $this->is_community_valid = true;
          cache()->set($this->slug, $info, $info_directory, ABOUT_EXPIRATION);
        }
      }
    }
    if (!empty($info['reason']) && $info['reason'] == "private") {
      $this->is_private = true;
      $log->error("The requested subreddit $this->slug is private");
    }
    if (empty($info['data'])) {
      $log->error("The requested subreddit $this->slug does not exist");
      return;
    }
    $this->is_community_valid = true;
    $community_icon = $this->platform_icon;
    if (!empty($info['data']['icon_img'])) {
      $community_icon = $info['data']['icon_img'];
    }
    if (!empty($info['data']['community_icon'])) {
      $community_icon = strtok($info['data']['community_icon'], '?');
    }
    $description = $info['data']['public_description'];
    if($description) {
      $description = preg_replace('/\[(.*?)\]\((.*?)\)/', "$1", $description);
      $description = strip_tags($description);
      $description = str_replace(["\n", "\r"], '', $description);
      $description = preg_replace('/\s+/', ' ', $description);
      $description = trim($description);
      $description = preg_replace('/((\w+\W+){80}(\w+))(.*)/', '${1}' . '…', $description);
    }
    $this->name               = $info['data']['display_name'];
    $this->title              = $info['data']['title'];
    $this->description        = $description;
    $this->url                = "https://www.reddit.com/r/$this->slug/";
    $this->icon               = $community_icon;
    $this->banner_image       = $info['data']['banner_img'];
    $this->created            = $info['data']['created_utc'];
    $this->nsfw               = $info['data']['over18'];
    $this->subscribers        = $info['data']['subscribers'];
    $this->feed_title         = "&#x2F;r&#x2F;" . SUBREDDIT;
    $this->feed_description   = "Hot posts in &#x2F;r&#x2F;" . SUBREDDIT;
  }


  // Get top posts
  public function getTopPosts($limit, $period = '') {
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested subreddit $this->slug does not exist");
      return [];
    }
    if (empty($limit) || $limit < $this->max_items_per_request) {
      $limit = $this->max_items_per_request;
    }
    $number_of_requests = ceil($limit / $this->max_items_per_request);
    $limit = $number_of_requests * $this->max_items_per_request;
    $cache_object_key = "$this->slug-top";
    $cache_directory = "communities/reddit/$this->slug/top_posts";
    $cache_expiration = TOP_DAILY_POSTS_EXPIRATION;
    if ($period && $period == 'month') {
      $cache_expiration = TOP_MONTHLY_POSTS_EXPIRATION;
    }
    $base_url = "https://oauth.reddit.com/r/$this->slug/top/.json?limit=$limit";
    if ($period) {
      $cache_object_key = "$this->slug-top-$period";
      $cache_directory = "communities/reddit/$this->slug/top_posts_$period";
      $base_url = "https://oauth.reddit.com/r/$this->slug/top/.json?t=$period&limit=$limit";
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
      $url = $base_url;
      if ($i > 1) {
        $url .= "&after=" . end($top_posts)['name'];
      }
      $page_cache_object_key = "$this->slug-top-$period-limit-$this->max_items_per_request-page-$i";
      if (cache()->get($page_cache_object_key, $cache_directory)) {
        $top_posts = array_merge($top_posts, cache()->get($page_cache_object_key, $cache_directory));
      } else {
        $curl_response = curlURL($url, [
          CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->auth_token),
          CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')'
        ]);
        $curl_data = json_decode($curl_response, true);
        if (!empty($curl_data['data']['children'])) {
          $paged_top_posts = [];
          foreach ($curl_data['data']['children'] as $post) {
            $post = $post['data'];
            if (empty($post['id']) || empty($post['score'])) continue;
            $post_min = [
              'id' => $post['id'],
              'score' => $post['score'],
              'name' => $post['name']
            ];
            $paged_top_posts[] = $post_min;
            $top_posts[] = $post_min;
          }
          cache()->set($page_cache_object_key, $paged_top_posts, $cache_directory, TOP_POSTS_EXPIRATION);
        }
      }
      // if ($i < $number_of_requests) sleep(1);
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
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested subreddit $this->slug does not exist");
      return [];
    }
    if(
      ($filter_nsfw || FILTER_NSFW) &&
      $this->nsfw
    ) {
      return [];
    }
    $limit = $limit ?? $this->max_items_per_request;
    $cache_object_key = "$this->slug-hot-limit-$limit-min";
    $cache_directory = "communities/reddit/$this->slug/hot_posts";
    if (cache()->get($cache_object_key, $cache_directory))
      return cache()->get($cache_object_key, $cache_directory);
    $url = "https://oauth.reddit.com/r/$this->slug/hot/.json?limit=$limit";
    $curl_response = curlURL($url, [
      CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $this->auth_token),
      CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')'
    ]);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data) || !empty($curl_data['error']))
      return ['error' => "There was an error communicating with Reddit."];
    cache()->set($cache_object_key, $curl_data, $cache_directory, HOT_POSTS_EXPIRATION);
    if (empty($curl_data['data']['children']))
      return false;
    $hot_posts = $curl_data['data']['children'];
    $hot_posts_min = array();
    foreach ($hot_posts as $post) {
      $post = new \Post\Reddit($post['data'], $this->slug);
      if (
        ($filter_nsfw || FILTER_NSFW) &&
        ($post->nsfw || $post->thumbnail == 'nsfw')
      ) {
        continue;
      }
      $hot_posts_min[] = $post;
    }
    cache()->set($cache_object_key, $hot_posts_min, $cache_directory, HOT_POSTS_EXPIRATION);
    return $hot_posts_min;
  }


  // Get monthly average top score
  public function getMonthlyAverageTopScore() {
    $log = \CustomLogger::getLogger();
    if (!$this->is_community_valid) {
      $log->error("The requested subreddit $this->slug does not exist");
      return 0;
    }
    $cache_object_key = "$this->slug-month-average-top-score";
    $cache_directory = "communities/reddit/$this->slug/top_posts_month";
    // Use cached score if present
    if ($cached_score = cache()->get($cache_object_key, $cache_directory)) {
      return $cached_score;
    }
    $top_posts = $this->getTopPosts($this->max_items_per_request, 'month');
    $total_score = array_reduce($top_posts, function($sum, $post) {
      return $sum + $post['score'];
    }, 0);
    if (count($top_posts) == 0) {
      return 0;
    }
    $average_score = floor($total_score / count($top_posts));
    $log->info("Monthly average top score calculated for subreddit $this->slug: $average_score");
    cache()->set($cache_object_key, $average_score, $cache_directory, TOP_MONTHLY_POSTS_EXPIRATION);
    return $average_score;
  }


}
