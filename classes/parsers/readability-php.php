<?php

namespace Parser;

use fivefilters\Readability\Readability;
use fivefilters\Readability\Configuration;
use fivefilters\Readability\ParseException;

class ReadabilityPHP extends Parser {

  // Properties
  public $content_source = '';
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
    // Try Browserless first
    $curled_content = $this->getBrowserlessPage($this->url) ?? '';
    if (!empty($curled_content)) {
      $this->content_source = 'browserless';
    } else {
      // If Browserless fails, try cURL
      $curled_content = curlURL($this->url);
      if (!$curled_content) {
        sleep(1);
        $curled_content = curlURL($this->url);
      }
      if (!$curled_content) {
        $this->log->error("There was an error grabbing the webpage content for URL $this->url for the ReadabilityPHP parser.");
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
      $this->log->error("There was an error parsing the webpage content for URL $this->url with the ReadabilityPHP parser.");
      return [
        'parser_error' => true
      ];
		}
    if (!$readability_object) {
      $this->log->error("The response from ReadabilityPHP was empty or invalid for URL " . $this->url);
      return [];
    }
    $this->readability = $readability_object;
    if(!empty($readability_object->content)) {
      $readability_object->content = preg_replace('/\n/', ' ', $readability_object->content);
    }
    $this->log->info("ReadabilityPHP successfully parsed the webpage for URL " . $this->url);
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
      'lead_image_url'   => $readability_object->lead_image_url ?? '',
      'all_images'       => $readability_object->all_images ?? [],
      'author'           => $readability_object->author ?? '',
      'direction'        => $readability_object->direction ?? '',
      'word_count'       => $readability_object->word_count ?? 0,
      'content_source'   => $this->content_source
    ];

  }
}
