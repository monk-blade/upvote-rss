<?php

namespace Auth;

class Reddit extends Auth {

  public function getToken() {
    if (empty(REDDIT_USER) || empty(REDDIT_CLIENT_ID_ENCRYPTED) || empty(REDDIT_CLIENT_SECRET))
      die("Please ensure that you have set all Reddit credentials.");
    $auth_directory = $_SERVER['DOCUMENT_ROOT'] . "/cache/auth/reddit/";
    $token = cacheGet(REDDIT_CLIENT_ID_ENCRYPTED, $auth_directory);
    if ($token)
      return openssl_decrypt($token, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
    $auth_string = base64_encode(REDDIT_CLIENT_ID . ':' . REDDIT_CLIENT_SECRET);
    $curl_response = curlURL('https://www.reddit.com/api/v1/access_token', [
      CURLOPT_POST => 1,
      CURLOPT_POSTFIELDS => 'grant_type=client_credentials',
      CURLOPT_USERAGENT => 'web:voterss:1.0 (by /u/' . REDDIT_USER . ')',
      CURLOPT_HTTPHEADER => array('Authorization: Basic ' . $auth_string)
    ]);
    $curl_data = json_decode($curl_response, true);
    if (!empty($curl_data['error']))
      die("There was an error authenticating with Reddit. Please check your username, client ID, and client secret.");
    $access_token = $curl_data['access_token'];
    $access_token_encrypted = openssl_encrypt($access_token, CIPHERING, ENCRYPTION_KEY, ENCRYPTION_OPTIONS, ENCRYPTION_IV);
    cacheSet(REDDIT_CLIENT_ID_ENCRYPTED, $access_token_encrypted, $auth_directory, AUTH_EXPIRATION);
    return $access_token;
  }
}
