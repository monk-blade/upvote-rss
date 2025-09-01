<?php

namespace Post;

abstract class Post {

	// Properties
	public  ?string $id;
	public  ?string $title;
	public  ?string $url = null;
	public  ?string $permalink = null;
	public  ?string $original_permalink;
	public  ?string $created_utc;
	public  ?int    $time;
	public  ?string $relative_date;
	public  ?string $time_rfc_822;
	public  ?int    $score;
	public  ?string $score_formatted = '0';
	public  ?string $domain;
	public  ?array  $kids;
	public  ?array  $media_embed;
	public  ?array  $secure_media_embed;
	public  ?array  $secure_media;
	public  ?string $thumbnail = '';
	public  ?string $thumbnail_url = '';
	public  ?string $thumbnail_obfuscated_url;
	public  ?string $image_obfuscated_url;
	public  ?array  $preview;
	public  ?string $selftext_html = '';
	public  ?bool   $nsfw;
	public  ?string $feed_Link = null;
	public  ?bool   $url_is_image = false;
  private ?string $post_link_image_url = '';
  private ?string $preview_image_url = '';
  private ?string $preview_image_html = '';
  private ?string $reddit_preview_image_url  = '';
  private ?string $reddit_preview_image_html = '';
  private ?string $image_obfuscated_html = '';
  private ?string $embedded_media_html = '';
  private ?string $imgur_image_url = '';
  private ?string $livememe_image_url = '';
	private bool    $get_parsed_content = true;
	private bool    $is_large_image_in_content = false;
  private ?string $parsed_content = '';
  private ?int    $parsed_content_word_count = 0;
  private ?string $parsed_image_url = '';
  private ?string $summary = '';
  private ?string $separator_after_permalink = '';
  private ?string $separator_after_summary = '';
  private ?string $separator_after_selftext = '';
  private ?string $separator_before_parsed_content = '';
  private ?string $separator_before_comments = '';
  private ?array  $comments = [];


	// Constructor
	public function __construct($params = array()) {
		foreach ($params as $key => $value) {
			$this->{$key} = $value;
		}
	}


	// Set Feed Link
  protected function setFeedLink() {
    $link = '';
    if(!empty($this->url)) {
      $link = cleanURL($this->url);
    }
    if (!$link && !empty($this->permalink)) {
      $link = cleanURL($this->permalink) ?? '';
    }
    $this->feed_Link = $link;
  }


  /**
 * Format score
 * @param int $score The score to format
 * @return string The formatted score
 */
  protected function formatScore($score): string {
    if (empty($score) || !is_numeric($score) || $score < 0) {
      return '0';
    }
    if ($score >= 1000) {
      return round($score / 1000, 1) . 'k';
    }
    return (string)$score;
  }


  // Get feed item description
  public function getFeedItemDescription() {
    $description = '';
    $this->getPostMedia();
    $this->shouldGetParsedContent();
    $this->getParsedContentAndSummary();
    $this->getPreviewImage();
    $this->cleanSelftext();
    $this->getSeparators();
    $description .= $this->getFeedItemMeta();
    $description .= $this->separator_after_permalink;
    if ($this->summary) {
      if (strpos($this->summary, "<p>") === 0) {
        $this->summary = "<p>Summary: " . substr($this->summary, 3);
      } else {
        $this->summary = "<p>Summary</p>" . $this->summary;
      }
      $description .= "<section class='summary'>$this->summary</section>";
    }
    $description .= $this->separator_after_summary;
    if ($this->selftext_html) {
      $description .= "<section class='selftext'>$this->selftext_html</section>";
    }
    $description .= $this->separator_after_selftext;
    if ($this->preview_image_html) {
      $preview_image_spacing = $this->selftext_html ? "<p>&nbsp;</p>" : "";
      $description .= "<section class='preview-image'>$preview_image_spacing$this->preview_image_html</section>";
    }
    if ($this->embedded_media_html) {
      $description .= "<section class='embedded-media'>$this->embedded_media_html</section>";
    }
    $description .= $this->separator_before_parsed_content;
    if ($this->parsed_content) {
      $description .= "<section class='parsed-content'>$this->parsed_content</section>";
    }
    $description .= $this->separator_before_comments;
    $description .= $this->getCommentsHTML();
    // Description text cleanup
    $description = str_replace("<p>​</p>", "", $description);
    $description = str_replace('src="//', 'src="https://', $description);
    return $description;
  }


  // Get post media
  private function getPostMedia() {
    // Determine if the URL is an image
    $url_extension = pathinfo($this->url, PATHINFO_EXTENSION);
    if (in_array($url_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
      // $this->preview_image_url = $this->url;
      // $this->preview_image_html .= "<img src='" . $this->url . "' alt='' />";
      $this->url_is_image = true;
      $this->post_link_image_url = $this->url;
    }
    // Reddit preview image
    if (!empty($this->preview['images'][0]['source']['url'])) {
      $this->reddit_preview_image_url = $this->preview['images'][0]['source']['url'];
      if (remote_file_exists($this->reddit_preview_image_url)) {
        $this->reddit_preview_image_html = "<img src='" . $this->reddit_preview_image_url . "' alt='' width='" . $this->preview['images'][0]['source']['width'] . "' height='" . $this->preview['images'][0]['source']['height'] . "' />";
        // $this->preview_image_html = $this->reddit_preview_image_html;
      }
    }
    // Thumbnail
    if (
      !empty($this->thumbnail_url) &&
      $this->thumbnail_url == "default"
    ) {
      $this->thumbnail_url = '';
    }
    // Obfuscated image
    if (!empty($this->image_obfuscated_url)) {
      $this->image_obfuscated_html = "<img src='" . $this->image_obfuscated_url . "' alt='' />";
    }
    // Secure media
    if (!empty($this->secure_media["oembed"]["html"])) {
      $this->embedded_media_html = html_entity_decode($this->secure_media["oembed"]["html"]);
    }
    // Imgur image
    if (
      strpos($this->url, "imgur.com") !== false &&
      remote_file_exists($this->url . '.jpg')
    ) {
      $imgur_image_url  = $this->url . '.jpg';
      // $this->preview_image_html = "<img src='" . $this->url . '.jpg' . "' alt='' />";
      // $this->get_parsed_content = false;
    }
    // Imgur gifv
    if (strpos($this->url, "imgur") && strpos($this->url, "gifv")) {
      $imgur_gifv = "";
      $preview_image_url = '';
      $preview_image_html = '';
      $preview_image_width = '';
      $preview_image_height = '';
      if (!empty($this->preview['images'][0]['source']['url'])) {
        $preview_image_url = $this->preview['images'][0]['source']['url'];
        $preview_image_height = $this->preview['images'][0]['source']['height'] ?? '';
        $preview_image_width = $this->preview['images'][0]['source']['width'] ?? '';
        $preview_image_html = "<img src='$preview_image_url' alt='' width='$preview_image_width' height='$preview_image_height' />";
      } elseif ($this->thumbnail_url) {
        $preview_image_url = $this->thumbnail_url;
        $preview_image_html = "<img src='$preview_image_url' alt='' />";
      }
      $imgur_gifv = "<video controls preload='auto' autoplay='false'";
      if ($preview_image_url) {
        $imgur_gifv .= " poster='" . $preview_image_url . "'";
      }
      if (!empty($preview_image_width) && !empty($preview_image_height)) {
        $imgur_gifv .= " width='" . $preview_image_width . "' height='" . $preview_image_height . "'";
      }
      $imgur_gifv .= "><source src='" . str_replace("gifv", "mp4", $this->url) . "' type='video/mp4'";
      if (!empty($this->media['oembed']['width']) && !empty($this->media['oembed']['height'])) {
        $imgur_gifv .= " width='" . $this->media['oembed']['width'] . "' height='" . $this->media['oembed']['height'] . "'";
      }
      $imgur_gifv .= ">$preview_image_html</video>";
      $this->embedded_media_html = $imgur_gifv;
    }
    // Reddit gallery
    if (strpos($this->url, "reddit.com/gallery/") !== false) {
      $gallery_id = explode('/', $this->url);
      $gallery_id = end($gallery_id);
      $cache_object_key = $gallery_id;
      $cache_directory = "galleries/reddit";
      $gallery = '';
      if (cache()->get($cache_object_key, $cache_directory)) {
        $gallery = cache()->get($cache_object_key, $cache_directory);
      } else {
        $url = "https://oauth.reddit.com/comments/$gallery_id.json";
        $reddit_auth = new \Auth\Reddit();
        $auth_token = $reddit_auth->getToken() ?? null;
        if(empty($auth_token)) {
          return;
        }
        $curl_response = curlURL($url, [
          CURLOPT_HTTPHEADER => array('Authorization: Bearer ' . $auth_token),
          CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')'
        ]);
        $curl_data = json_decode($curl_response, true);
        if (!empty($curl_data['error'])) {
          return;
        }
        $gallery = $curl_data[0]['data']['children'][0]['data'];
        cache()->set($cache_object_key, $gallery, $cache_directory, GALLERY_EXPIRATION);
      }
      if (empty($gallery)) {
        return;
      }
      $gallery_content = '';
      $media_ids = [];
      if (!empty($gallery['gallery_data']['items'])) {
        foreach ($gallery['gallery_data']['items'] as $item) {
          $media_ids[] = $item['media_id'];
        }
      }
      if (!empty($media_ids)) {
        foreach ($media_ids as $media_id) {
          if (BLUR_NSFW && $this->nsfw) {
            if (is_array($gallery['media_metadata'][$media_id]['o'][0])) {
              $gallery_content .= '<p><img src="' . $gallery['media_metadata'][$media_id]['o'][0]['u'] . '" height="' . $gallery['media_metadata'][$media_id]['o'][0]['y'] . '" width="' . $gallery['media_metadata'][$media_id]['o'][0]['x'] . '" /></p>';
            }
          } else {
            if (is_array($gallery['media_metadata'][$media_id]['s'])) {
              $gallery_content .= '<p><img src="' . $gallery['media_metadata'][$media_id]['s']['u'] . '" height="' . $gallery['media_metadata'][$media_id]['s']['y'] . '" width="' . $gallery['media_metadata'][$media_id]['s']['x'] . '" /></p>';
            }
          }
        }
      }
      $this->embedded_media_html = $gallery_content;
    }
    // YouTube video
    if (
      strpos($this->url, 'youtube.com') !== false &&
      strpos($this->url, 'v=') !== false
    ) {
      $youtube_video_id = explode('v=', $this->url);
      $youtube_video_id = end($youtube_video_id);
    } elseif (strpos($this->url, 'youtu.be/') !== false) {
      $youtube_video_id = explode('youtu.be/', $this->url);
      $youtube_video_id = end($youtube_video_id);
    } elseif (strpos($this->url, 'youtube.com/embed/') !== false) {
      $youtube_video_id = explode('embed/', $this->url);
      $youtube_video_id = end($youtube_video_id);
    }
    if (!empty($youtube_video_id)) {
      $youtube_video = "<iframe width='560' height='315' src='https://www.youtube.com/embed/$youtube_video_id' frameborder='0' allow='accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture' allowfullscreen></iframe>";
      if (!empty($youtube_video)) {
        $this->embedded_media_html = $youtube_video;
      }
    }
    // Reddit video
    if (
      !empty($this->secure_media['reddit_video']['hls_url']) ||
      strpos($this->url, "v.redd.it") !== false
      ) {
      $reddit_video = "";
      $poster_image_url = $this->thumbnail_url ?? '';
      $poster_image_url = !empty($this->preview['images'][0]['source']['url']) ? $this->preview['images'][0]['source']['url'] : $poster_image_url;
      $poster_image_url = str_replace("amp;", "", $poster_image_url);
      if (!remote_file_exists($poster_image_url)) {
        $poster_image_url = '';
      }
      if (BLUR_NSFW && $this->nsfw) {
        $poster_image_url = $this->image_obfuscated_url ?? '';
      }
      $video_url = !empty($this->secure_media['reddit_video']['hls_url']) ? $this->secure_media['reddit_video']['hls_url'] : '';
      if (empty($video_url) && remote_file_exists($this->url . '/HLSPlaylist.m3u8')) {
        $video_url = $this->url . '/HLSPlaylist.m3u8';
      }
      if (!empty($video_url)) {
        $reddit_video = "<video controls ";
        if ($poster_image_url) {
          $reddit_video .= "poster='$poster_image_url' ";
        }
        $reddit_video .= "preload='auto' autoplay='false'><source src='" . $video_url . "' type='video/mp4'";
        if (!empty($this->secure_media['reddit_video']['width']) && !empty($this->secure_media['reddit_video']['height'])) {
          $reddit_video .= " width='" . $this->secure_media['reddit_video']['width'] . "' height='" . $this->secure_media['reddit_video']['height'] . "'";
        }
        $reddit_video .= "><img src='$poster_image_url' alt='' /></video>";
      }
      if (!empty($reddit_video)) {
        $this->embedded_media_html = $reddit_video;
      }
    }
    // Twitter video
    if (
      strpos($this->url, "video.twimg.com") !== false &&
      strpos($this->url, ".mp4") !== false
    ) {
      $this->embedded_media_html = "<video controls preload='auto' autoplay='false'><source src='" . $this->url . "' type='video/mp4'></video>";
    }
    // Bluesky posts
    if (
      strpos($this->domain, "bsky.app") !== false &&
      strpos($this->url, "/post/") !== false
    ) {
      $bluesky_post = "";
      $oembed_url = "https://embed.bsky.app/oembed?url=" . $this->url;
      $curl_response = curlURL($oembed_url);
      $curl_data = json_decode($curl_response, true);
      if (!empty($curl_data['html'])) {
        $bluesky_post = $curl_data['html'];
      }
      if (!empty($bluesky_post)) {
        $this->embedded_media_html = $bluesky_post;
      }
    }
    // Livememe
    $livememe_image_to_try = str_replace("livememe.com", "i.lvme.me", $this->url) . '.jpg';
    if (
      strpos($this->url, "livememe") !== false &&
      remote_file_exists($livememe_image_to_try)
    ) {
      $this->livememe_image_url = $livememe_image_to_try;
      // $this->livememe_image_url = "<img src='" . $livememe_image_to_try . "' alt='' />";
      // $this->get_parsed_content = false;
    }
    // Secure media embed
    if (!empty($this->secure_media_embed["content"])) {
      $this->embedded_media_html = html_entity_decode($this->secure_media_embed["content"]);
    }
    // Media embed
    if (!empty($this->media_embed["content"])) {
      $this->embedded_media_html = html_entity_decode($this->media_embed["content"]);
    }

  }


  // Determine if we need to grab the webpage content
  private function shouldGetParsedContent() {
    $conditions = [
      !INCLUDE_CONTENT,
      strpos($this->domain, "self.") !== false,
      BLUR_NSFW && $this->nsfw && $this->image_obfuscated_url,
      $this->imgur_image_url,
      $this->livememe_image_url,
      $this->url_is_image,
      $this->embedded_media_html
    ];

    foreach ($conditions as $condition) {
      if ($condition) {
        $this->get_parsed_content = false;
        break;
      }
    }
  }


  // Get parsed content
  private function getParsedContentAndSummary() {
    if($this->get_parsed_content === true) {
      $webpage = new \WebpageAnalyzer($this->url);
      $this->parsed_content = $webpage->getParsedContent() ?? '';
      $this->parsed_content_word_count = $webpage->getWordCount();
      $this->parsed_image_url = $webpage->getLeadImageURL() ?? '';
      $this->summary = $webpage->getSummary() ?: '';
      $this->is_large_image_in_content = $webpage->isLargeIntroImageInContent();
      if($this->is_large_image_in_content) {
        $this->parsed_image_url = '';
      }
    }
  }


  // Get preview image
  private function getPreviewImage() {
    $preview_image_html = '';
    $preview_image_html = $this->thumbnail_url ? "<img src='$this->thumbnail_url' />" : $preview_image_html;
    $preview_image_html = $this->reddit_preview_image_html ?? $preview_image_html;
    $preview_image_html = $this->livememe_image_url ? "<img src='$this->livememe_image_url' />" : $preview_image_html;
    $preview_image_html = $this->imgur_image_url ? "<img src='$this->imgur_image_url' />" : $preview_image_html;
    if (
      $this->parsed_content &&
      $this->parsed_image_url &&
      remote_file_exists($this->parsed_image_url) &&
      (
        strpos($this->parsed_content, $this->parsed_image_url) === false ||
        strpos($this->parsed_content, "<img") > 2000
      )
    ) {
      $preview_image_html = "<img src='$this->parsed_image_url' />";
    }
    $preview_image_html = $this->post_link_image_url ? "<img src='$this->post_link_image_url' />" : $preview_image_html;
    if (
      !$preview_image_html &&
      $this->url &&
      $this->get_parsed_content
    ) {
      $webpage = new \WebpageAnalyzer($this->url);
      $og_image_url = $webpage->getOGImage();
      if ($og_image_url) {
        $this->preview_image_url = $og_image_url;
        $preview_image_html = "<img src='$og_image_url' />";
      }
    }
    if (
      BLUR_NSFW &&
      $this->nsfw &&
      $this->image_obfuscated_html
    ) {
      $preview_image_html = $this->image_obfuscated_html;
    }
    $preview_image_url = '';
    if (
      $preview_image_html &&
      preg_match('/<img.+src=[\'"](?P<src>.+?)[\'"].*>/i', $preview_image_html, $image_match)
    ) {
      $preview_image_url = $image_match['src'];
      $preview_image_html = "<img src='$preview_image_url' />";
    }
    if (
      $preview_image_html &&
      strpos($preview_image_html, 'http') === false
    ) {
      $preview_image_html = '';
    }
    if (
      $preview_image_html &&
      $this->selftext_html &&
      strpos($this->selftext_html, $preview_image_url) !== false
    ) {
      $preview_image_html = '';
    }
    if ($this->embedded_media_html) {
      $preview_image_html = '';
    }
    if (
      $this->parsed_content &&
      strpos($this->parsed_content, "<img") !== false &&
      strpos($this->parsed_content, "<img") < 2000
    ) {
      $preview_image_html = '';
    }
    $this->preview_image_html = $preview_image_html;
  }


  // Clean selftext
  private function cleanSelftext() {
    if (!empty($this->selftext_html)) {
      $selftext = html_entity_decode($this->selftext_html);
      $selftext = str_replace(["\r", "\n"], "", $selftext);
      $this->selftext_html = tidy($selftext);
    }
  }


  // Get separators
  private function getSeparators() {
    $separator = "<p>&nbsp;</p><hr><p>&nbsp;</p>";
    // Separator after permalink
    $separator_after_permalink = "<section class='separator separator-after-permalink'>$separator</section>";
    if (
      !$this->summary &&
      !$this->selftext_html
    ) {
      $separator_after_permalink = '';
    }
    $this->separator_after_permalink = $separator_after_permalink;
    // Separator after summary
    $separator_after_summary = '';
    if (
      $this->summary &&
      (
        !empty($this->selftext_html) ||
        (
          empty($this->selftext_html) &&
          !$this->preview_image_html &&
          !$this->embedded_media_html
        )
      )
    ) {
      $separator_after_summary = "<section class='separator separator-after-summary'>$separator</section>";
    }
    $this->separator_after_summary = $separator_after_summary;
    // Separator after selftext
    $separator_after_selftext = '';
    if (
      $this->selftext_html &&
      !$this->preview_image_html &&
      !$this->embedded_media_html &&
      $this->parsed_content
    ) {
      $separator_after_selftext = "<section class='separator separator-after-selftext'>$separator</section>";
    }
    $this->separator_after_selftext = $separator_after_selftext;
    // Separator before parsed content
    $separator_before_parsed_content = '';
    if (
      !$separator_after_permalink &&
      !$separator_after_summary &&
      !$this->selftext_html &&
      !$separator_after_selftext &&
      !$this->preview_image_html &&
      !$this->embedded_media_html &&
      $this->parsed_content &&
      strpos($this->parsed_content, "<img") !== 0 &&
      strpos($this->parsed_content, "<picture") !== 0 &&
      strpos($this->parsed_content, "<div><img") !== 0 &&
      strpos($this->parsed_content, "<div><picture") !== 0 &&
      strpos($this->parsed_content, "<div><div><img") !== 0 &&
      strpos($this->parsed_content, "<div><div><picture") !== 0
    ) {
      $separator_before_parsed_content = "<section class='separator separator-before-parsed-content'>$separator</section>";
    }
    $this->separator_before_parsed_content = $separator_before_parsed_content;
    // Separator before comments
    $separator_before_comments = '';
    if ($this->getCommentsHTML()) {
      $separator_before_comments = "<section class='separator separator-before-comments'>$separator</section>";
    }
    $this->separator_before_comments = $separator_before_comments;
  }


  // Get estimated reading time
  private function getEstimatedReadingTime() {
    $total_word_count       = 0;
    $estimated_reading_time = 0;
    $reading_minutes        = 0;
    if (!empty($this->selftext_html)) {
      $total_word_count = str_word_count(strip_tags($this->selftext_html));
    }
    if (
      $this->get_parsed_content &&
      $this->parsed_content_word_count
    ) {
      $total_word_count += $this->parsed_content_word_count;
    }
    if ($total_word_count) {
      $reading_minutes = $total_word_count / 200;
      if ($total_word_count < 20) {
        $reading_minutes = 0;
      } elseif (0.3 <= $reading_minutes && $reading_minutes < 1) {
        $reading_minutes = 1;
      } elseif ($reading_minutes < 2) {
        $reading_minutes = ceil($reading_minutes);
      } else {
        $reading_minutes = round($reading_minutes);
      }
    }
    return $reading_minutes;
  }


  // Get feed item meta section
  private function getFeedItemMeta() {
    $output = '';
    $score = '';
    $reading_time = '';
    if (SHOW_SCORE && !empty($this->score)) {
      $score = 'Score: ' . formatScore($this->score) . ' | ';
    }
    $permalink = !empty($this->permalink) ? "<a href='" . $this->permalink . "'>Post permalink</a>" : '';
    $original_permalink = !empty($this->original_permalink) ? "<a href='" . $this->original_permalink . "'>Original post</a>" : '';
    if ($this->getEstimatedReadingTime()) {
      $reading_time = 'Reading time: ' . $this->getEstimatedReadingTime() . ' min | ';
    }
    $output = "<section class='reading-time-and-permalink'><p>$score$reading_time<a href='" . $this->permalink . "'>Post permalink</a>$original_permalink</p></section>";
    return $output;
  }


  // Check if comment should be filtered out
  abstract protected function shouldFilterComment($comment): bool;


  // Get comments
	abstract function getComments(): array;


  // Get cached comments
  protected function getCachedComments($cache_directory, $cache_object_key, $number_of_comments_to_fetch): array {
    $comments = [];

    if (empty($cache_directory) || empty($cache_object_key || empty($number_of_comments_to_fetch))) {
      return $comments;
    }

    // Check for existing cache with exact limit
    if (cache()->get($cache_object_key, $cache_directory)) {
      $comments = cache()->get($cache_object_key, $cache_directory);
    }

    // Check for cached items with higher limits
    elseif (REDIS) {
      $cache_object_key = null;
      $client = new \Predis\Client('tcp://' . REDIS_HOST . ':' . REDIS_PORT);
      $keys_prefix = 'upvote_rss:' . str_replace('/', ':', $cache_directory);
      $keys_prefix = str_replace(['.'], '_', $keys_prefix);
      $keys_prefix = str_replace('::', ':', $keys_prefix);
      $keys = $client->keys($keys_prefix . ':*');
      foreach ($keys as $key) {
        if (preg_match('/^' . preg_quote($keys_prefix . ':' . $this->id . '_limit_', '/') . '(\d+)$/', $key, $matches)) {
          $cached_limit = (int)$matches[1];
          if ($cached_limit > $number_of_comments_to_fetch) {
            $cache_object_key = $this->id . "_limit_" . $cached_limit;
            break;
          }
        }
      }
    }
    elseif (is_dir(UPVOTE_RSS_CACHE_ROOT . $cache_directory)) {
      $files = scandir(UPVOTE_RSS_CACHE_ROOT . $cache_directory);
      $cache_object_key = null;
      foreach ($files as $file) {
        if (preg_match('/^' . preg_quote($this->id, '/') . '_limit_(\d+)$/', $file, $matches)) {
          $cached_limit = (int)$matches[1];
          if ($cached_limit > $number_of_comments_to_fetch) {
            $cache_object_key = $this->id . "_limit_" . $cached_limit;
            break;
          }
        }
      }
    }

    if ($cache_object_key && cache()->get($cache_object_key, $cache_directory)) {
      $comments = cache()->get($cache_object_key, $cache_directory);
    }

    return $comments;
  }


	// Get comments HTML
  public function getCommentsHTML(): string {
    if (!INCLUDE_COMMENTS || COMMENTS < 1) {
      return '';
    }
    $comments_html = '';
    // Use cached comments if available, otherwise fetch them
    if (empty($this->comments)) {
      $this->comments = $this->getComments();
    }
    if (!empty($this->comments) && count($this->comments)) {
      $comments_html .= "<section class='comments'>";
      if (count($this->comments) == 1) {
        $comments_html .= "<p>Top comment</p>";
      } elseif (count($this->comments) > 1) {
        $comments_html .= "<p>Top comments</p>";
      }
      $comments_html .= "<ol>";
      $iterator = 1;
      foreach ($this->comments as $comment) {
        $spacer = "";
        if ($iterator != count($this->comments)) {
          $spacer = "<p>&nbsp;</p>";
        }
        $comments_html .= "<li>" . str_replace(["\r", "\n"], '', html_entity_decode($comment["body"])) . "<p><a href='" . $comment["permalink"] . "'><small>Comment permalink</small></a></p>$spacer</li>";
        $iterator++;
      }
      $comments_html .= "</ol>";
      $comments_html .= "</section>";
      $comments_html = tidy($comments_html);
    }
    return $comments_html;
  }
}
