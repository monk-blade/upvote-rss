<?php

namespace Auth;

class Reddit extends Auth {
  private $log;

  public function __construct() {
    $this->log = \CustomLogger::getLogger();
  }

  public function getToken() {
    if (empty(REDDIT_USER) || empty(REDDIT_CLIENT_ID_ENCRYPTED) || empty(REDDIT_CLIENT_SECRET)) {
      $message = "Reddit credentials are not fully set";
      $this->log->error($message);
      throw new \Exception($message);
    }
    $auth_directory = "auth/reddit";
    $token = cache()->get(REDDIT_CLIENT_ID_ENCRYPTED, $auth_directory);
    if ($token) {
      return openssl_decrypt($token, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
    }
    $this->log->info("Requesting new token from Reddit for user " . REDDIT_USER);
    $auth_string = base64_encode(REDDIT_CLIENT_ID . ':' . REDDIT_CLIENT_SECRET);
    $curl_response = curlURL('https://www.reddit.com/api/v1/access_token', [
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
      CURLOPT_USERAGENT => 'web:Upvote RSS:' . UPVOTE_RSS_VERSION . ' (by /u/' . REDDIT_USER . ')',
      CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $auth_string)
    ]);
    $curl_data = json_decode($curl_response, true);
    if (!empty($curl_data['error'])) {
      $message = "Reddit authentication failed for user " . REDDIT_USER . ". Response: " . $curl_response;
      $this->log->error($message);
      throw new \Exception($message);
    }
    $this->log->info("New token received from Reddit for user " . REDDIT_USER);
    $access_token = $curl_data['access_token'];
    $access_token_encrypted = openssl_encrypt($access_token, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
    cache()->set(REDDIT_CLIENT_ID_ENCRYPTED, $access_token_encrypted, $auth_directory, AUTH_EXPIRATION);
    return $access_token;
  }
}
