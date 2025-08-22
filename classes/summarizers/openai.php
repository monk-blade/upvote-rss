<?php

namespace Summarizer;

class OpenAI extends Summarizer {

  // Properties
  private ?string $api_key;
  private string  $api_url = 'https://api.openai.com/v1/chat/completions';
  private ?bool   $is_model_available;


  // Constructor
  public function __construct() {
    parent::__construct();
    $this->provider_name      = 'OpenAI';
    $this->api_key            = OPENAI_API_KEY;
    $this->model_name         = OPENAI_API_MODEL;
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
      )
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

    if(!empty($response['error']['code']) && $response['error']['code'] === 'invalid_api_key') {
      $this->log->error("$this->provider_name API key is invalid. You can find your API key at https://platform.openai.com/account/api-keys. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_summarizer_available = false;
      return [];
    }

    if(!empty($response['error']['code']) && $response['error']['code'] === 'model_not_found') {
      $this->log->error("$this->provider_name API model $this->model_name not found. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_model_available = false;
      return [];
    }

    if (!empty($response['error']['message'])) {
      $this->log->error("$this->provider_name API response contains an error: " . $response['error']['message']);
      return [];
    }

    if (!empty($response['usage']['completion_tokens']) && $response['usage']['completion_tokens'] === $this->max_tokens) {
      $this->log->warning("$this->provider_name API response is most likely truncated due to reaching the maximum token limit of " . $this->max_tokens);
    }

    if (!empty($response['choices'][0]['message']['content'])) {
      return ['summary' => $response['choices'][0]['message']['content']];
    }

    $this->log->error("$this->provider_name API response does not contain valid summary content");
    return [];
  }
}
