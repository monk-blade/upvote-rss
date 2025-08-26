<?php

namespace Parser;

class ReadabilityJS extends Parser {

  // Properties
  private $log;

  // Constructor
  function __construct(
    $url = null
  ) {
    if (!empty($url)) $this->url = $url;
    $this->log = \CustomLogger::getLogger();
  }

  public function getParsedWebpage() {
    if (empty($this->url)) {
      return [];
    }
    $curled_content = '';
    $curl_response = curlURL(READABILITY_JS_URL, [
			CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
      CURLOPT_POSTFIELDS => json_encode([
        'url' => $this->url
      ])
		]);
    if (!$curl_response) {
      $this->log->error("There was an error communicating with the Readability.js parser at " . READABILITY_JS_URL);
      return [
        'parser_error' => true
      ];
    }
    $readability_object = json_decode($curl_response);
    if (empty($readability_object)) {
      $this->log->error("The response from Readability.js was empty or invalid for URL " . $this->url);
      return [
        'parser_error' => true
      ];
    }
    if(!empty($readability_object->content)) {
      $readability_object->content = preg_replace('/\n/', ' ', $readability_object->content);
    }
    if (!empty($readability_object->content) && !empty($readability_object->length)) {
      $content = strip_tags($readability_object->content);
      $readability_object->word_count = str_word_count($content);
    }
    // Instantiate ReadabilityPHP parser to grab images
    $readability_php = new \Parser\ReadabilityPHP($this->url);
    $readability_php_object = $readability_php->getParsedWebpage() ?? null;
    if (!empty($readability_php_object)) {
      $readability_object->all_images = $readability_php_object['all_images'] ?? [];
      $readability_object->lead_image_url = $readability_php_object['lead_image_url'] ?? '';
    }
    $this->log->info("Readability.js successfully parsed the webpage for URL " . $this->url);
    $this->log->debug("Parsed data: ", [
      'title'          => $readability_object->title ?? '',
      'word_count'     => $readability_object->word_count ?? 0
    ]);
    return [
      'parser_error'     => false,
      'parser_unix_time' => time(),
      'title'            => $readability_object->title ?? '',
      'content'          => $readability_object->content ?? '',
      'excerpt'          => $readability_object->excerpt ?? '',
      'length'           => $readability_object->length ?? 0,
      'byline'           => $readability_object->byline ?? '',
      'direction'        => $readability_object->dir ?? '',
      'word_count'       => $readability_object->word_count ?? 0,
      'all_images'       => $readability_object->all_images ?? [],
      'lead_image_url'   => $readability_object->lead_image_url ?? '',
      'content_source'   => 'readability_js'
    ];

  }
}
