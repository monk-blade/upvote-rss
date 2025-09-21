<?php

namespace Community;

abstract class Community
{
  // Properties
  public  $platform;
  public  $instance;
  public  $slug;
  public  $name;
  public  $title;
  public  $description;
  public  $url;
  public  $platform_icon;
  public  $icon;
  public  $banner_image;
  public  $created;
  public  $nsfw;
  public  $subscribers;
  public  $max_items_per_request;
  public  $needs_authentication;
  public  $feed_title;
  public  $feed_description;
  public  $is_instance_valid  = false;
  public  $is_community_valid = false;

  // Abstract methods
  abstract protected function getInstanceInfo();
  abstract protected function getCommunityInfo();
  abstract function getHotPosts(int $limit, bool $filter_nsfw, bool $blur_nsfw);
  abstract function getTopPosts(int $limit, string $period);
  abstract function getMonthlyAverageTopScore();


  // Get filtered posts by value
  public function getFilteredPostsByValue(
    $filter_type      = FILTER_TYPE,
    $filter_value     = FILTER_VALUE,
    $filter_nsfw      = FILTER_NSFW,
    $blur_nsfw        = BLUR_NSFW,
    $filter_old_posts = FILTER_OLD_POSTS,
    $post_cutoff_days = POST_CUTOFF_DAYS
  ) {
    if (!$this->is_community_valid) {
      $log_message = $this->slug . " is not a valid community";
      if (PLATFORM === 'lemmy') {
        $log_message .= " or the instance '" . $this->instance . "' is not reachable.";
      }
      if (PLATFORM === 'lobsters') {
        $log_message .= ". Please check if the Lobsters community exists and is public.";
      }
      if (PLATFORM === 'mbin') {
        $log_message = $this->slug . " is not a valid community or the instance '" . $this->instance . "' is not reachable.";
      }
      if (PLATFORM === 'reddit') {
        $log_message .= ". Please check if the subreddit exists and is public.";
      }
      logger()->error($log_message);
      throw new \Exception($log_message);
    }

    // Filter by score
    switch ($filter_type) {
      case 'score':
        if (!in_array(PLATFORM, SCORE_FILTER_AVAILABLE_PLATFORMS)) {
          logger()->error("Score filter is not available for " . PLATFORM . " community " . $this->slug);
          return [];
        }
        return $this->getHotPostsByScore($filter_type, $filter_value, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);
        break;

      case 'threshold':
        if (!in_array(PLATFORM, THRESHOLD_FILTER_AVAILABLE_PLATFORMS)) {
          logger()->error("Threshold filter is not available for " . PLATFORM . " community " . $this->slug);
          return [];
        }
        $monthly_average_top_score = $this->getMonthlyAverageTopScore(0);
        logger()->info("Monthly average top score for " . $this->slug . " is " . $monthly_average_top_score);
        $threshold_score = $monthly_average_top_score * $filter_value / 100;
        return $this->getHotPostsByScore($filter_type, $threshold_score, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);
        break;

      case 'averagePostsPerDay':
        if (!in_array(PLATFORM, AVERAGE_POSTS_PER_DAY_FILTER_AVAILABLE_PLATFORMS)) {
          logger()->error("Average posts per day filter is not available for " . PLATFORM . " community " . $this->slug);
          return [];
        }
        $threshold_score = 0;
        $average_posts_per_day = $filter_value;
        $average_posts_per_month = $average_posts_per_day * 30;
        $top_posts = $this->getTopPosts($average_posts_per_month, 'month');
        $top_posts = array_slice($top_posts, 0, $average_posts_per_month);
        $number_of_returned_posts = count($top_posts);
        if ($number_of_returned_posts == 0) return [];
        $ratio_of_wanted_posts_to_returned_posts = $average_posts_per_month / $number_of_returned_posts;
        if ($ratio_of_wanted_posts_to_returned_posts <= 1) {
          $lowest_score = isset($top_posts[$average_posts_per_month - 1]) && $top_posts[$average_posts_per_month - 1] !== null ? $top_posts[$average_posts_per_month - 1]['score'] : 0;
          $second_lowest_score = isset($top_posts[$average_posts_per_month - 2]) && $top_posts[$average_posts_per_month - 2] !== null ? $top_posts[$average_posts_per_month - 2]['score'] : $lowest_score;
          $threshold_score = $lowest_score < $second_lowest_score / 2 ? $second_lowest_score : $lowest_score;
        } else {
          $lowest_score = isset($top_posts[$number_of_returned_posts - 1]) && $top_posts[$number_of_returned_posts - 1] !== null ? $top_posts[$number_of_returned_posts - 1]['score'] : 0;
          $second_lowest_score = count($top_posts) > 1 && isset($top_posts[$number_of_returned_posts - 2]) && $top_posts[$number_of_returned_posts - 2] !== null ? $top_posts[$number_of_returned_posts - 2]['score'] : $lowest_score;
          if ($lowest_score < $second_lowest_score / 2) $lowest_score = $second_lowest_score;
          $threshold_score = floor($lowest_score / $ratio_of_wanted_posts_to_returned_posts);
        }
        return $this->getHotPostsByScore($filter_type, $threshold_score, $filter_nsfw, $blur_nsfw, $filter_old_posts, $post_cutoff_days);
        break;
    }
  }


  // Get posts by score
  public function getHotPostsByScore(
    $filter_type      = 'score',
    $score            = 1000,
    $filter_nsfw      = FILTER_NSFW,
    $blur_nsfw        = BLUR_NSFW,
    $filter_old_posts = FILTER_OLD_POSTS,
    $post_cutoff_days = POST_CUTOFF_DAYS
  ) {
    $hot_posts = $this->getHotPosts($this->max_items_per_request, $filter_nsfw, $blur_nsfw);
    if (empty($hot_posts)) {
      return [];
    }
    if (!empty($hot_posts['error'])) {
      logger()->error($hot_posts['error']);
      return $hot_posts;
    }
    $hot_posts_filtered = array();
    foreach ($hot_posts as $post) {
      $return_post = true;
      if (!isset($post->score) || is_numeric($post->score) && $post->score < $score) {
        $return_post = false;
      }
      if ($filter_nsfw && $post->nsfw) {
        $return_post = false;
      }
      if ($filter_old_posts && $post_cutoff_days && $post->time < strtotime("-" . $post_cutoff_days . " days")) {
        $return_post = false;
      }
      if ($return_post) {
        if (strpos($_SERVER['REQUEST_URI'], 'view=rss') !== false && method_exists($post, 'getDetailedPostData')) {
          $post->getDetailedPostData();
        }
        $hot_posts_filtered[] = $post;
      }
    }
    if($filter_type == 'averagePostsPerDay') {
      usort($hot_posts_filtered, function ($a, $b) {
        return $b->time <=> $a->time;
      });
    } else {
      usort($hot_posts_filtered, function ($a, $b) {
        return $b->score <=> $a->score;
      });
    }
    return $hot_posts_filtered;
  }

}
