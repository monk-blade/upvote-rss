<?php

namespace Parser;

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

class ReadabilityPHP extends Parser {

  // Properties
  public $content_source = '';

  // Constructor
  function __construct(
    $url = null
  ) {
    if (!empty($url)) $this->url = $url;
  }

  public function getParsedWebpage() {
    if (empty($this->url)) {
      return [];
    }
    $curled_content = '';
    // Try Browserless first
    $curled_content = getBrowserlessPage($this->url);
    $this->content_source = 'browserless';
    // If Browserless fails, try cURL
    if (empty($curled_content)) {
      $curled_content = curlURL($this->url);
      if (!$curled_content) {
        sleep(1);
        $curled_content = curlURL($this->url);
      }
      if (!$curled_content) {
        return [
          'parser_error' => true
        ];
      }
      $this->content_source = 'curl';
    }
		$readability = new Readability(new Configuration());
		$readability_object = new \stdClass();
		try {
      $readability->parse($curled_content);
			$readability_object->title          = $readability->getTitle() ?? '';
			$readability_object->content        = $readability->getContent() ?? '';
			$readability_object->excerpt        = $readability->getExcerpt() ?? '';
			$readability_object->lead_image_url = $readability->getImage() ?? '';
			$readability_object->all_images     = $readability->getImages() ?? '';
			$readability_object->author         = $readability->getAuthor() ?? '';
			$readability_object->direction      = $readability->getDirection() ?? '';
			$readability_object->word_count     = $readability->getContent() ? str_word_count($readability->getContent()) : 0;
		} catch (\Throwable $e) {
      return [
        'parser_error' => true
      ];
		}
    if (!$readability_object) {
      return [];
    }
    $this->readability = $readability_object;
    if(!empty($readability_object->content)) {
      $readability_object->content = preg_replace('/\n/', ' ', $readability_object->content);
    }
    return [
      'parser_error'     => false,
      'parser_unix_time' => time(),
      'title'            => $readability_object->title ?? '',
      'content'          => $readability_object->content ?? '',
      'excerpt'          => $readability_object->excerpt ?? '',
      'lead_image_url'   => $readability_object->lead_image_url ?? '',
      'all_images'       => $readability_object->all_images ?? [],
      'author'           => $readability_object->author ?? '',
      'direction'        => $readability_object->direction ?? '',
      'word_count'       => $readability_object->word_count ?? 0,
      'content_source'   => $this->content_source
    ];

  }
}
