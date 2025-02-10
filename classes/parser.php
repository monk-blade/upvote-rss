<?php

namespace Parser;

abstract class Parser {

  // Properties
  private string  $url                          = '';
  private string  $content_source               = '';
  private int     $parser_unix_time             = 0;
  private string  $title                        = '';
  private string  $content                      = '';
  private string  $content_text                 = '';
  private int     $length                       = 0;
  private string  $excerpt                      = '';
  private string  $lead_image_url               = '';
  private array   $all_images                   = [];
  private string  $author                       = '';
  private string  $byline                       = '';
  private string  $date_published               = '';
  private string  $direction                    = '';
  private int     $word_count                   = 0;
  private string  $dek                          = '';
  private string  $total_pages                  = '';
  private string  $next_page_url                = '';
  private string  $rendered_pages               = '';
  private bool    $parser_error                 = false;

  // Constructor
  function __construct(
    $url = null
  ) {
    if (!empty($url)) $this->url = $url;
  }

  abstract function getParsedWebpage();

}

include_once 'parsers/mercury.php';
include_once 'parsers/readability-php.php';
include_once 'parsers/readability-js.php';