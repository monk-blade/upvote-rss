<?php

namespace Parser;

class Mercury extends Parser {

  // Properties
  private $log;

  // Constructor
  function __construct(
    $url = null
  ) {
    if (!empty($url)) $this->url = $url;
    $this->log = new \CustomLogger;
  }

  public function getParsedWebpage() {
    if (empty($this->url)) {
      return [];
    }
    $curled_content = curlURL(MERCURY_URL . '/parser?url=' . $this->url, [
			CURLOPT_HTTPHEADER => ['Content-Type: application/json']
		]);
    if (!$curled_content) {
      $this->log->error("There was an error communicating with the Mercury parser at " . MERCURY_URL);
      return [
        'parser_error' => true
      ];
    }
    $mercury_object = json_decode($curled_content);
    if (empty($mercury_object)) {
      $this->log->error("The response from Mercury was empty or invalid for URL " . $this->url);
      return [
        'parser_error' => true
      ];
    }
    if (!empty($mercury_object->content)) {
      $mercury_object->content = preg_replace('/\n/', ' ', $mercury_object->content);
    }
    if (!empty($mercury_object->content) && empty($mercury_object->word_count)) {
      $content = strip_tags($mercury_object->content);
      $mercury_object->word_count = str_word_count($content);
    }
    // Instantiate ReadabilityPHP parser to grab images
    $readability_php = new \Parser\ReadabilityPHP($this->url);
    $readability_php_object = $readability_php->getParsedWebpage() ?? null;
    if (!empty($readability_php_object)) {
      $mercury_object->all_images = $readability_php_object['all_images'] ?? [];
      $mercury_object->lead_image_url = $mercury_object->lead_image_url ?? $readability_php_object['lead_image_url'] ?? '';
    }
    $this->log->info("Mercury successfully parsed the webpage for URL " . $this->url);
    $this->log->debug("Parsed data: ", [
      'title'          => $mercury_object->title ?? '',
      'word_count'     => $mercury_object->word_count ?? 0,
      'date_published' => $mercury_object->date_published ?? 0
    ]);
    return [
      'parser_error'     => false,
      'parser_unix_time' => time(),
      'title'            => $mercury_object->title ?? '',
      'content'          => $mercury_object->content ?? '',
      'excerpt'          => $mercury_object->excerpt ?? '',
      'date_published'   => $mercury_object->date_published ?? 0,
      'author'           => $mercury_object->author ?? '',
      'direction'        => $mercury_object->dir ?? '',
      'dek'              => $mercury_object->dek ?? '',
      'word_count'       => $mercury_object->word_count ?? 0,
      'total_pages'      => $mercury_object->total_pages ?? 0,
      'next_page_url'    => $mercury_object->next_page_url ?? 0,
      'rendered_pages'   => $mercury_object->rendered_pages ?? 0,
      'all_images'       => $mercury_object->all_images ?? [],
      'lead_image_url'   => $mercury_object->lead_image_url ??'',
      'content_source'   => 'mercury'
    ];

  }
}
