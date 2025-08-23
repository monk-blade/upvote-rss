<?php

namespace Summarizer;

class GoogleGemini extends Summarizer {

  // Properties
  private ?string $api_key;
  private string  $api_url = 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions';
  private ?bool   $is_model_available;


  // Constructor
  public function __construct() {
    parent::__construct();
    $this->provider_name      = 'Google Gemini';
    $this->api_key            = GOOGLE_GEMINI_API_KEY;
    $this->model_name         = GOOGLE_GEMINI_API_MODEL;
    $this->is_model_available = null;
  }


  // Check if the provider is available
  public function isAvailable(): bool {
    return !empty($this->api_key);
  }


  // Get the summary from the provider
  protected function getProviderSummary(string $content, string $url): array {
    if ($this->is_summarizer_available === false) {
      return [];
    }

    if ($this->is_model_available === false) {
      return [];
    }

    $this->log->info("Trying to get summary from $this->provider_name API for $url");

    $curl_options = array(
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode(array(
        'model'                 => $this->model_name,
        'max_completion_tokens' => $this->max_tokens,
        'temperature'           => $this->temperature,
        'messages'              => [
          array(
            'role'    => 'system',
            'content' => $this->system_prompt
          ),
          array(
            'role'    => 'user',
            'content' => $content
          )
        ]
      )),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json',
        'Authorization: Bearer ' . ($this->api_key ?? '')
      ),
      CURLOPT_TIMEOUT => MAX_EXECUTION_TIME
    );

    $curl_response = curlURL($this->api_url, $curl_options);

    if (!$curl_response) {
      $this->log->error("$this->provider_name API response is empty or invalid");
      return [];
    }

    $response = json_decode($curl_response, true);

    if (!$response) {
      $this->log->error("$this->provider_name API response is not valid JSON");
      return [];
    }

    if(!empty($response[0]['error']['details'][0]['reason']) && $response[0]['error']['details'][0]['reason'] === 'API_KEY_INVALID') {
      $this->log->error("$this->provider_name API key is invalid. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_summarizer_available = false;
      return [];
    }

    if(!empty($response[0]['error']['status']) && $response[0]['error']['status'] === 'NOT_FOUND') {
      $this->log->error("$this->provider_name API model $this->model_name not found. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_model_available = false;
      return [];
    }

    if (!empty($response['error']['message'])) {
      $this->log->error("$this->provider_name API response contains an error: " . $response['error']['message']);
      return [];
    }

    if (!empty($response[0]['error']['message'])) {
      $this->log->error("$this->provider_name API response contains an error: " . $response[0]['error']['message']);
      return [];
    }

    if (!empty($response['choices'][0]['finish_reason']) && $response['choices'][0]['finish_reason'] == 'length') {
      $total_tokens = $response['usage']['total_tokens'] ?? 'unknown';
      $this->log->error(
        "$this->provider_name API response was cut off due to length limit. " .
        "Try increasing SUMMARY_MAX_TOKENS. " .
        "total_tokens: $total_tokens, SUMMARY_MAX_TOKENS: " . $this->max_tokens
      );
      return [];
    }

    if (!empty($response['choices'][0]['message']['content'])) {
      return ['summary' => $response['choices'][0]['message']['content']];
    }

    $this->log->error("$this->provider_name API response does not contain valid summary content");
    return [];
  }
}
