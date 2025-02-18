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

  public function getBrowserlessPage($url = '') {
    $log = new \CustomLogger;
    if (empty($url)) {
      return;
    }
    if (empty(BROWSERLESS_URL)) {
      return;
    }
    if (empty(BROWSERLESS_TOKEN)) {
      $log->error("Browserless token is not set.");
      return;
    }
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL            => BROWSERLESS_URL . "/content?token=" . BROWSERLESS_TOKEN,
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_ENCODING       => "",
      CURLOPT_MAXREDIRS      => 10,
      CURLOPT_TIMEOUT        => 10,
      CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
      CURLOPT_CUSTOMREQUEST  => "POST",
      CURLOPT_POSTFIELDS     => "{
        \"url\": \"" . $url . "\"
      }",
      CURLOPT_HTTPHEADER     => [
        "Content-Type: application/json"
      ],
    ]);
    $response = curl_exec($curl);
    $err = curl_error($curl);
    curl_close($curl);
    if ($err) {
      $log->error("Curl error encountered in Browserless for URL " . $url . ": " . $err);
      return;
    }
    if (strpos($response, 'HTTP ERROR 404') !== false) {
      $log->error("404 error encountered in Browserless for URL " . $url);
      return;
    }
    $log->info("Browserless successfully fetched the content for URL " . $url);
    return $response;
  }

}

include_once 'parsers/mercury.php';
include_once 'parsers/readability-php.php';
include_once 'parsers/readability-js.php';