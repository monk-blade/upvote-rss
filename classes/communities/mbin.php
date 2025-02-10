<?php

class MbinMagazine extends Community
{
  // https://docs.joinmbin.org/api#tag/magazine

  // Properties
  public $platform = 'mbin';
  public $instance;
  public $needs_authentication = false;


  // Constructor
  function __construct(
    $slug = null,
    $instance = null
  ) {
    if (!empty($slug)) {
      $this->slug = $slug;
    } elseif (!empty($_GET['community'])) {
      $this->slug = $_GET['community'];
    } else {
      $this->slug = 'selfhosted';
    }
    if (!empty($instance)) {
      $this->instance = $instance;
    } elseif (!empty($_GET['instance'])) {
      $this->instance = $_GET['instance'];
    } else {
      $this->instance = 'fedia.io';
    }
    $this->platform_icon = "https://$this->instance/favicon.svg";
    $this->getCommunityInfo();
  }


  protected function getInstanceInfo()
  {
  }


  protected function getCommunityInfo()
  {
    // Check cache directory first
    $info_directory = "cache/communities/mbin/$this->slug/about/";
    $info = cacheGet($this->slug, $info_directory);
    if (empty($info)) {
      // Get community info
      $this->instance = rtrim(preg_replace('/^https?:\/\//', '', $this->instance), '/');
      $url = "https://$this->instance/api/magazines/$this->slug";
      $curl_response = curlURL($url);
      $curl_data = json_decode($curl_response, true);
      dd($curl_data);
      if (!empty($curl_data['community_view']) && empty($curl_data['error'])) {
        $this->is_community_valid = true;
        cacheSet($this->slug, $curl_data, $info_directory, ABOUT_EXPIRATION);
      } else {
        $this->is_community_valid = false;
      }
    }
    if (!empty($curl_data['community_view']) && empty($curl_data['error'])) {
      $community = $curl_data['community_view']['community'];
      $counts = $curl_data['community_view']['counts'];
      $this->name               = !empty($community['name']) ? $community['name'] : $this->slug;
      $this->title              = !empty($community['title']) ? $community['title'] : $this->slug;
      $this->description        = !empty($community['description']) ? $community['description'] : '';
      $this->url                = !empty($community['actor_id']) ? $community['actor_id'] : '';
      $this->icon               = !empty($community['icon']) ? $community['icon'] : '';
      $this->banner_image       = !empty($community['banner']) ? $community['banner'] : '';
      $this->created            = !empty($community['published']) ? $community['published'] : '';
      $this->nsfw               = !empty($community['nsfw']) ? $community['nsfw'] : false;
      $this->subscribers        = !empty($counts['subscribers']) ? $counts['subscribers'] : 0;
      $this->is_community_valid = true;
    }
  }


  // Get hot posts
  // TODO: get hot posts for previews and RSS feeds
  public function getHotPosts($limit = 100)
  {
  }


  // Get top posts
  public function getTopPosts($limit = 100, $period = 'day')
  {
    if (!$this->is_community_valid)
      return "This community is not valid.";
    // Sort: https://docs.joinmbin.org/api/#tag/magazine/operation/post_api_magazine_entry_create_image # top / hot / newest / active / newest
    // Time: https://docs.joinmbin.org/api/#tag/magazine/operation/post_api_magazine_entry_create_image # all / 6hours / 12hours / day / week / month / year
    if ($limit == 0) $limit = 100;
    $cacheObjectKey = "$this->slug-top-$period-limit-$limit-min";
    $cacheDir = "cache/communities/mbin/$this->slug/top_posts_$period/";
    $url = "$this->instance/api/posts?magazine=$this->slug&sort=top&time=$period";
    if (cacheGet($cacheObjectKey, $cacheDir))
      return cacheGet($cacheObjectKey, $cacheDir);
    $curl_response = curlURL($url);
    $curl_data = json_decode($curl_response, true);
    if (empty($curl_data))
      return false;
    // TODO: Finish this junk
    dd($curl_data);
    $top_posts_min = array();
    foreach ($curl_data['posts'] as $post) {
      $top_posts_min[] = [
        'id'            => !empty($post['post']['id']) ? $post['post']['id'] : null,
        'title'         => !empty($post['post']['name']) ? $post['post']['name'] : null,
        'url'           => !empty($post['post']['url']) ? $post['post']['url'] : null,
        'permalink'     => !empty($post['post']['ap_id']) ? $post['post']['ap_id'] : null,
        'created_utc'   => !empty($post['post']['published']) ? $post['post']['published'] : null,
        'score'         => !empty($post['counts']['score']) ? $post['counts']['score'] : null,
        'domain'        => !empty($post['post']['url']) ? parse_url($post['post']['url'], PHP_URL_HOST) : null,
        'thumbnail'     => !empty($post['post']['thumbnail_url']) ? $post['post']['thumbnail_url'] : null,
        'selftext_html' => !empty($post['body']) ? $post['body'] : null,
        'nsfw'          => !empty($post['nsfw']) ? $post['nsfw'] : null,
        'local'         => !empty($post['local']) ? $post['local'] : false,
      ];
    }
    cacheSet($cacheObjectKey, $top_posts_min, $cacheDir, TOP_POSTS_EXPIRATION);
    return $top_posts_min;
  }


  // Get monthly average top score
  public function getMonthlyAverageTopScore()
  {
  }


  public function getFilteredPostsByValue(
    $filterType = 'score',
    $filterValue = 1000,
    $returnType = 'preview'
  ) {
    if (!$this->is_community_valid)
    die("This community is not valid.");
    if ($filterType == 'score') {
      return $this->getHotPostsByScore($filterValue, $returnType);
    }
  }


  // Get comments
  public function getComments($post_id = null)
  {
    return;
  }
}
