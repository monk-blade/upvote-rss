<?php

class WebpageAnalyzer {

  // Properties
  public  string  $url                          = '';
  private string  $cache_object_key             = '';
  private string  $cache_directory              = 'cache/webpages/';
  public  string  $domain                       = '';
  private int     $status                       = 0;
  public  string  $title                        = '';
  public  string  $content                      = '';
  public  string  $content_text                 = '';
  public  string  $excerpt                      = '';
  public  string  $lead_image_url               = '';
  public  array   $all_images                   = [];
  public  string  $og_image_url                 = '';
  public  bool    $large_intro_image_in_content = false;
  public  string  $author                       = '';
  public  string  $byline                       = '';
  public  string  $date_published               = '';
  public  string  $dek                          = '';
  public  string  $total_pages                  = '';
  public  string  $next_page_url                = '';
  public  string  $rendered_pages               = '';
  public  string  $direction                    = '';
  public  int     $length                       = 0;
  public  int     $word_count                   = 0;
  private string  $content_source               = '';
  private string  $content_extractor            = '';
  private bool    $parser_error                 = false;
  private int     $parser_unix_time             = 0;
  private float   $parser_total_time            = 0;
  private bool    $summary_error                = false;
  private int     $summary_tries                = 0;
  public  string  $summary                      = '';
  public  string  $summary_provider             = '';
  public  string  $summary_model                = '';
  private int     $summary_unix_time            = 0;
  private float   $summary_total_time           = 0;
  private $log;

  // Constructor
  public function __construct($url) {
    $this->log = new \CustomLogger;
    $this->url = cleanURL($url) ?? '';
    $this->getCacheObjectKey();
    $this->getPropertiesFromCache();
    $this->getDomain();
  }

  // Get cache object key
  private function getCacheObjectKey() {
    $url = str_replace(['http://', 'https://', 'www.'], '', $this->url);
    $url = str_replace(['/', '.'], '-', $url);
    $url = filter_var($url, FILTER_SANITIZE_ENCODED);
    $this->cache_object_key = substr($url, 0, 100);
  }

  // Get properties from cache
  public function getPropertiesFromCache() {
    if (cacheGet($this->cache_object_key, $this->cache_directory)) {
      $properties = cacheGet($this->cache_object_key, $this->cache_directory);
      $this->domain                       = $properties['domain'] ?? '';
      $this->status                       = $properties['status'] ?? 0;
      $this->title                        = $properties['title'] ?? '';
      $this->content                      = $properties['content'] ?? '';
      $this->content_text                 = $properties['content_text'] ?? '';
      $this->excerpt                      = $properties['excerpt'] ?? '';
      $this->lead_image_url               = $properties['lead_image_url'] ?? '';
      $this->all_images                   = $properties['all_images'] ?? [];
      $this->og_image_url                 = $properties['og_image_url'] ?? '';
      $this->large_intro_image_in_content = $properties['large_intro_image_in_content'] ?? false;
      $this->author                       = $properties['author'] ?? '';
      $this->byline                       = $properties['byline'] ?? '';
      $this->date_published               = $properties['date_published'] ?? '';
      $this->dek                          = $properties['dek'] ?? '';
      $this->total_pages                  = $properties['total_pages'] ?? '';
      $this->next_page_url                = $properties['next_page_url'] ?? '';
      $this->rendered_pages               = $properties['rendered_pages'] ?? '';
      $this->direction                    = $properties['direction'] ?? '';
      $this->length                       = $properties['length'] ?? 0;
      $this->word_count                   = $properties['word_count'] ?? 0;
      $this->content_source               = $properties['content_source'] ?? '';
      $this->content_extractor            = $properties['content_extractor'] ?? '';
      $this->parser_error                 = $properties['parser_error'] ?? false;
      $this->parser_unix_time             = $properties['parser_unix_time'] ?? 0;
      $this->parser_total_time            = $properties['parser_total_time'] ?? 0;
      $this->summary_error                = $properties['summary_error'] ?? false;
      $this->summary_tries                = $properties['summary_tries'] ?? 0;
      $this->summary                      = $properties['summary'] ?? '';
      $this->summary_provider             = $properties['summary_provider'] ?? '';
      $this->summary_model                = $properties['summary_model'] ?? '';
      $this->summary_unix_time            = $properties['summary_unix_time'] ?? 0;
      $this->summary_total_time           = $properties['summary_total_time'] ?? 0;
    }
  }

  // Save properties to cache
  public function savePropertiesToCache() {
    $properties = [
      'url'                          => $this->url,
      'domain'                       => $this->domain,
      'status'                       => $this->status,
      'title'                        => $this->title,
      'content'                      => $this->content,
      'content_text'                 => $this->content_text,
      'excerpt'                      => $this->excerpt,
      'lead_image_url'               => $this->lead_image_url,
      'all_images'                   => $this->all_images,
      'og_image_url'                 => $this->og_image_url,
      'large_intro_image_in_content' => $this->large_intro_image_in_content,
      'author'                       => $this->author,
      'byline'                       => $this->byline,
      'date_published'               => $this->date_published,
      'dek'                          => $this->dek,
      'total_pages'                  => $this->total_pages,
      'next_page_url'                => $this->next_page_url,
      'rendered_pages'               => $this->rendered_pages,
      'direction'                    => $this->direction,
      'length'                       => $this->length,
      'word_count'                   => $this->word_count,
      'content_source'               => $this->content_source,
      'content_extractor'            => $this->content_extractor,
      'parser_error'                 => $this->parser_error,
      'parser_unix_time'             => $this->parser_unix_time,
      'parser_total_time'            => $this->parser_total_time,
      'summary_error'                => $this->summary_error,
      'summary_tries'                => $this->summary_tries,
      'summary'                      => $this->summary,
      'summary_provider'             => $this->summary_provider,
      'summary_model'                => $this->summary_model,
      'summary_unix_time'            => $this->summary_unix_time,
      'summary_total_time'           => $this->summary_total_time
    ];
    cacheSet($this->cache_object_key, $properties, $this->cache_directory, WEBPAGE_EXPIRATION);
    $this->log->info('Webpage cache updated for ' . $this->url);
  }

  // Get domain from URL
  private function getDomain() {
    $domain = $this->domain;
    if (empty($domain)) {
      $domain = parse_url($this->url, PHP_URL_HOST);
    }
    if($domain != $this->domain) {
      $this->domain = $domain;
      $this->savePropertiesToCache();
    }
  }

  // Get HTTP status
  private function getStatus() {
    $status = $this->status;
    if (empty($status)) {
      $status = getHttpStatus($this->url) ?? 0;
      if ($status < 200 || $status >= 300) {
        $this->log->log('WebpageAnalyzer: ' . $this->url . ' returned status ' . $status);
      }
    }
    if($status != $this->status) {
      $this->status = $status;
      $this->savePropertiesToCache();
    }
  }

  // Get parsed data
  private function getParsedData() {
    if (
      $this->parser_error ||
      $this->parser_unix_time ||
      !INCLUDE_CONTENT
    ) {
      return;
    }
    $display_errors = ini_get('display_errors');
    if ($display_errors) {
      ini_set('display_errors', 0);
    }
    $start_time = microtime(true);
    $content_extractor = '';
    $parsed_webpage = [];
    // Try Mercury first
    if (!empty(MERCURY_URL)) {
      if (!remote_file_exists(MERCURY_URL)) {
        $this->log->error('Mercury URL ' . MERCURY_URL . ' is not reachable');
      } else {
        $parser = new \Parser\Mercury($this->url);
        $parsed_webpage = $parser->getParsedWebpage() ?? [];
        $content_extractor = 'mercury';
      }
    }
    // Try ReadabilityJS next
    if (
      empty($parsed_webpage['content']) &&
      !empty(READABILITY_JS_URL)
    ) {
      if (remote_file_exists(READABILITY_JS_URL) || getHttpStatus(READABILITY_JS_URL) == 400) {
        $parser = new \Parser\ReadabilityJS($this->url);
        $parsed_webpage = $parser->getParsedWebpage();
        $content_extractor = 'readability_js';
      } else {
        $this->log->error('ReadabilityJS URL ' . READABILITY_JS_URL . ' is not reachable');
      }
    }
    // Try ReadabilityPHP last
    if (empty($parsed_webpage['content'])) {
      $parser = new \Parser\ReadabilityPHP($this->url);
      $parsed_webpage = $parser->getParsedWebpage();
      $content_extractor = 'readability_php';
    }
    // If no content is found, set parser error to true and save to cache
    if (empty($parsed_webpage['content'])) {
      $this->parser_error = true;
      $this->savePropertiesToCache();
      $this->log->warning('Content for the URL ' . $this->url . ' could not be parsed');
      if ($display_errors) {
        ini_set('display_errors', 1);
      }
      return;
    }
    $this->content_extractor = $content_extractor;
    foreach ($parsed_webpage as $key => $value) {
      $this->{$key} = $value;
    }
    $this->cleanContent();
    $end_time = microtime(true);
    $total_time = $end_time - $start_time;
    $total_time = ceil($total_time * 10) / 10;
    $this->parser_total_time = $total_time;
    if ($display_errors) {
      ini_set('display_errors', 1);
    }
    $this->savePropertiesToCache();
  }

  // Clean content
  private function cleanContent() {
    if (!$this->content) {
      return;
    }
    // Remove most attributes from parsed content
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding(html_entity_decode($this->content), 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//@*');
    foreach ($nodes as $node) {
      if (!in_array($node->nodeName, ['src', 'href', 'width', 'height', 'alt', 'title', 'srcset'])) {
        $node->parentNode->removeAttribute($node->nodeName);
      }
      // If href or src attributes are relative, make them absolute
      if (in_array($node->nodeName, ['src', 'href'])) {
        if (strpos($node->nodeValue, 'http') === false) {
          $node->nodeValue = 'https://' . $this->domain . $node->nodeValue;
        }
      }
    }
    // Remove empty paragraphs
    $paragraphs = $dom->getElementsByTagName('p');
    foreach ($paragraphs as $paragraph) {
      if (empty(trim($paragraph->textContent))) {
        $paragraph->parentNode->removeChild($paragraph);
      }
    }
    // Remove empty divs
    $divs = $dom->getElementsByTagName('div');
    foreach ($divs as $div) {
      if (empty(trim($div->textContent))) {
        $div->parentNode->removeChild($div);
      }
    }
    // Remove empty spans
    $spans = $dom->getElementsByTagName('span');
    foreach ($spans as $span) {
      if (empty(trim($span->textContent))) {
        $span->parentNode->removeChild($span);
      }
    }
    // Remove empty images
    $images = $dom->getElementsByTagName('img');
    foreach ($images as $image) {
      $image_src = $image->getAttribute('src') ?? '';
      $image_data_src = $image->getAttribute('data-src') ?? '';
      if (!$image_src && $image_data_src) {
        $image->setAttribute('src', $image_data_src);
      }
      $image_srcset = $image->getAttribute('srcset') ?? '';
      $image_data_srcset = $image->getAttribute('data-srcset') ?? '';
      if (!$image_srcset && $image_data_srcset) {
        $image->setAttribute('srcset', $image_data_srcset);
      }
      if (!$image_src && !$image_srcset) {
        $image->parentNode->removeChild($image);
      }
    }
    // Remove any <bsp-timestamp> elements
    $timestamp_elements = $dom->getElementsByTagName('bsp-timestamp');
    foreach ($timestamp_elements as $timestamp_element) {
      $timestamp_element->parentNode->removeChild($timestamp_element);
    }
    // Remove meta tags
    $meta_tags = $dom->getElementsByTagName('meta');
    foreach ($meta_tags as $meta_tag) {
      $meta_tag->parentNode->removeChild($meta_tag);
    }
    // Remove svg elements
    $svg_elements = $dom->getElementsByTagName('svg');
    foreach ($svg_elements as $svg_element) {
      $svg_element->parentNode->removeChild($svg_element);
    }
    // Remove any empty elements
    // $elements = $dom->getElementsByTagName('*');
    // foreach ($elements as $element) {
    //   if (empty(trim($element->textContent))) {
    //     $element->parentNode->removeChild($element);
    //   }
    // }
    // Add a gallery of images that are not in the parsed content
    $parsed_content_pictures = $dom->getElementsByTagName('picture');
    $parsed_content_picture_source_urls = [];
    $parsed_content_picture_image_urls = [];
    foreach ($parsed_content_pictures as $parsed_content_picture) {
      $parsed_content_picture_sources = $parsed_content_picture->getElementsByTagName('source');
      foreach ($parsed_content_picture_sources as $parsed_content_picture_source) {
        $parsed_content_picture_source_urls[] = $parsed_content_picture_source->getAttribute('srcset');
      }
      $parsed_content_picture_images = $parsed_content_picture->getElementsByTagName('img');
      foreach ($parsed_content_picture_images as $parsed_content_picture_image) {
        $parsed_content_picture_image_urls[] = $parsed_content_picture_image->getAttribute('src');
      }
    }
    $parsed_content_images = $dom->getElementsByTagName('img');
    $gallery_image_urls = [];
    $parsed_content_image_urls = [];
    if (!empty($this->all_images)) {
      foreach($parsed_content_images as $parsed_content_image) {
        if(!empty($parsed_content_image->getAttribute('src'))) {
          $parsed_content_image_urls[] = $parsed_content_image->getAttribute('src');
        }
      }
      foreach($this->all_images as $image_url) {
        if (
          !in_array($image_url, $parsed_content_image_urls) &&
          filter_var($image_url, FILTER_VALIDATE_URL) &&
          strpos($this->lead_image_url, $image_url) === false &&
          !in_array($image_url, $parsed_content_picture_source_urls) &&
          !in_array($image_url, $parsed_content_picture_image_urls) &&
          strpos($image_url, 'missing-image') === false &&
          remote_file_exists($image_url)
        ) {
          $gallery_image_urls[] = $image_url;
        }
      }
      if (!empty($gallery_image_urls)) {
        $gallery = $dom->createElement('div');
        $gallery->setAttribute('class', 'gallery');
        foreach($gallery_image_urls as $gallery_image_url) {
          $gallery_image_paragraph = $dom->createElement('p');
          $gallery_image = $dom->createElement('img');
          $gallery_image->setAttribute('src', $gallery_image_url);
          $gallery_image_paragraph->appendChild($gallery_image);
          $gallery->appendChild($gallery_image_paragraph);
        }
        $dom->appendChild($gallery);
      }
    }
    $this->content = $dom->saveHTML();
    // Remove any spaces that are more than 1 in a row
    $this->content = preg_replace('/\s+/', ' ', $this->content);
    // Remove any spaces that immediately precede or follow <div> or <p> tags
    $this->content = preg_replace('/\s+<div/', '<div', $this->content);
    $this->content = preg_replace('/div>\s+/', 'div>', $this->content);
    $this->content = preg_replace('/\s+<p/', '<p', $this->content);
    $this->content = preg_replace('/p>\s+/', 'p>', $this->content);
    // Remove newlines
    $this->content = preg_replace('/\n/', ' ', $this->content);
    // Remove return characters
    $this->content = preg_replace('/\r/', ' ', $this->content);
    // Tidy up the content
    $this->content = tidy($this->content);
    // If a good image is found in the parsed content intro, set the parsed image URL to blank
    foreach ($parsed_content_images as $index => $image) {
      if ($index > 1) {
        break;
      }
      $img_src = $image->getAttribute('src');
      if (
        empty($img_src) ||
        strpos($img_src, 'data:image') !== false ||
        strpos($img_src, 'base64') !== false ||
        strpos($img_src, 'svg') !== false ||
        !filter_var($img_src, FILTER_VALIDATE_URL) ||
        strpos($this->content, '<img') === false ||
        strpos($this->content, '<img') > 2000 ||
        !remote_file_exists($img_src)
      ) {
        continue;
      }
      if (
        ($image->getAttribute('width') &&
        $image->getAttribute('width') >= 640) ||
        getRemoteFileSize($img_src) > 30 * 1024 ||
        getRemoteFileSize(str_replace('&', '&amp;', $img_src)) > 30 * 1024
      ) {
        $this->large_intro_image_in_content = true;
        break;
      }
    }
  }

  // Get parsed content
  public function getParsedContent() {
    $this->getParsedData();
    return $this->content;
  }

  // Get lead image URL
  public function getLeadImageURL() {
    $this->getParsedData();
    return $this->lead_image_url;
  }

  // Is there a large intro image in the content?
  public function isLargeIntroImageInContent() {
    $this->getParsedData();
    return $this->large_intro_image_in_content;
  }

  // Get word count
  public function getWordCount() {
    $this->getParsedData();
    return $this->word_count;
  }

  public function getOGImage() {
    if ($this->og_image_url) {
      return $this->og_image_url;
    }
    $this->log->info('Trying to get OG image from ' . $this->url);
    $og_image_url = '';
    $parser = new \Parser\ReadabilityPHP($this->url);
    $webpage_contents = $parser->getBrowserlessPage($this->url) ?? curlURL($this->url) ?? '';
    if (!$webpage_contents) {
      $this->log->info('Curl response is empty or invalid when trying to get OG image from ' . $this->url);
      return '';
    }
    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding(html_entity_decode($webpage_contents), 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);
    $meta_tags = $xpath->query('//meta[@property="og:image"]');
    foreach ($meta_tags as $meta_tag) {
      $og_image_url = $meta_tag->getAttribute('content');
    }
    if (empty($og_image_url)) {
      $this->log->info('No OG image found for ' . $this->url);
      return '';
    }
    $this->og_image_url = $og_image_url;
    $this->log->info("OG image found for $this->url: $og_image_url");
    $this->savePropertiesToCache();
    return $og_image_url;
  }

  // Get summary
  public function getSummary() {
    if ($this->summary) {
      return $this->summary;
    }
    if (
      $this->parser_error ||
      ($this->summary_error && $this->summary_tries > 2)
    ) {
      return '';
    }
    $this->summary_tries++;
    $this->savePropertiesToCache();
    $this->getParsedData();
    $fetch_summary = true;
    if(!INCLUDE_SUMMARY) {
      $fetch_summary = false;
    }
    if(strpos($_SERVER['REQUEST_URI'], 'view=test') !== false) {
      $fetch_summary = true;
    }
    if (
      !$fetch_summary ||
      !$this->content
    ) {
      return '';
    }
    $start_time       = microtime(true);
    $summary          = '';
    $summary_provider = '';
    $summary_model    = '';
    $parsed_content   = $this->content;
    $parsed_content   = strip_tags($parsed_content);
    $parsed_content   = implode(' ', array_slice(explode(' ', $parsed_content), 0, 1000));
    if (str_word_count($parsed_content) < 200) {
      $this->log->info('Content for the URL ' . $this->url . ' is too short to generate a summary (' . str_word_count($parsed_content) . ' words)');
      return '';
    }
    if(
      !empty(OLLAMA_URL) &&
      !empty(OLLAMA_MODEL)
    ) {
      $this->log->info('Trying to get summary from Ollama API for ' . $this->url);
      $model_is_available = false;
      $ollama_tags_url = OLLAMA_URL . '/api/tags';
      $curl_options = array(
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json'
        )
      );
      $curl_options[CURLOPT_TIMEOUT] = 2;
      $curl_response = curlURL($ollama_tags_url, $curl_options) ?? '';
      if (!$curl_response) {
        $this->log->error('Ollama URL ' . OLLAMA_URL . ' is not reachable');
      } else {
        $response = json_decode($curl_response, true);
        if (empty($response['models'])) {
          $this->log->error('No models returned from Ollama URL ' . $ollama_tags_url);
        }
        foreach ($response['models'] as $model) {
          if (in_array($model['name'], [OLLAMA_MODEL, OLLAMA_MODEL . ':latest'])) {
            $model_is_available = true;
            break;
          }
        }
        if (!$model_is_available) {
          $this->log->error('Model ' . OLLAMA_MODEL . ' is not available at Ollama URL ' . OLLAMA_URL);
        } else {
          $ollama_api_url = OLLAMA_URL . '/api/generate';
          $curl_options = array(
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode(array(
              'model'       => OLLAMA_MODEL,
              'stream'      => false,
              'temperature' => SUMMARY_TEMPERATURE,
              'system'      => SUMMARY_SYSTEM_PROMPT,
              'prompt'      => $parsed_content
            )),
            CURLOPT_HTTPHEADER => array(
              'Content-Type: application/json'
            )
          );
          $curl_options[CURLOPT_TIMEOUT] = MAX_EXECUTION_TIME;
          $curl_response = curlURL($ollama_api_url, $curl_options) ?? '';
          if (!$curl_response) {
            $this->log->error('Ollama API response is empty or invalid');
          } else {
            $response = json_decode($curl_response, true);
            if (!$response) {
              $this->log->error('Ollama API response is not valid JSON');
            } elseif (!empty($response['response']) && strlen($response['response']) > 100) {
              $summary          = $response['response'];
              $summary_provider = 'ollama';
              $summary_model    = OLLAMA_MODEL;
              $this->log->info("Summary for URL $this->url generated by Ollama model $summary_model");
            } else {
              $this->log->error('Ollama API response does not contain valid summary content');
            }
          }
        }
      }
    }
    if(!$summary && !empty(OPENAI_API_KEY)) {
      $this->log->info('Trying to get summary from OpenAI API for ' . $this->url);
      $openai_url = 'https://api.openai.com/v1/chat/completions';
      $openai_model = OPENAI_API_MODEL ?? 'gpt-4o-mini';
      $curl_options = array(
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode(array(
          'max_tokens' => 150,
          'temperature' => SUMMARY_TEMPERATURE,
          'model' => $openai_model,
          'messages' => [
            array(
              'role'    => 'system',
              'content' => SUMMARY_SYSTEM_PROMPT
            ),
            array(
              'role'    => 'user',
              'content' => $parsed_content
            )
          ]
        )),
        CURLOPT_HTTPHEADER => array(
          'Content-Type: application/json',
          'Authorization: Bearer ' . OPENAI_API_KEY
        )
      );
      $curl_response = curlURL($openai_url, $curl_options);
      if (!$curl_response) {
        $this->log->error('OpenAI API response is empty or invalid');
        $summary = '';
      }
      $response = json_decode($curl_response, true);
      if (!empty($response['choices'][0]['message']['content']) && strlen($response['choices'][0]['message']['content']) > 100){
        $summary          = $response['choices'][0]['message']['content'];
        $summary_provider = 'openai';
        $summary_model    = $openai_model;
        $this->log->info("Summary for URL $this->url generated by OpenAI model $summary_model");
      } else {
        if(!empty($response['error']['message'])) {
          $this->log->error('OpenAI API response contains an error: ' . $response['error']['message']);
        } else {
          $this->log->error('OpenAI API response does not contain valid summary content');
        }
      }
    }
    if (!$summary) {
      $this->summary_error = true;
      $this->log->warning('Summary for the URL ' . $this->url . ' could not be generated after ' . $this->summary_tries . ' tries');
      $this->savePropertiesToCache();
      return '';
    }
    $this->summary_error      = false;
    $Parsedown                = new \Parsedown();
    $Parsedown->setSafeMode(true);
    $summary                  = $Parsedown->text($summary);
    $summary                  = str_replace(["\r", "\n"], '', $summary);
    $this->summary            = trim($summary);
    $this->summary_provider   = $summary_provider;
    $this->summary_model      = $summary_model;
    $this->summary_unix_time  = time();
    $end_time                 = microtime(true);
    $total_time               = $end_time - $start_time;
    $total_time               = ceil($total_time * 10) / 10;
    $this->summary_total_time = $total_time;
    $this->savePropertiesToCache();
    return $this->summary;
  }

}
