<?php

namespace Summarizer;

class Ollama extends Summarizer {

  // Properties
  private ?string $api_url;
  private ?bool   $is_model_available;


  // Constructor
  public function __construct() {
    parent::__construct();
    $this->provider_name      = 'Ollama';
    $this->api_url            = OLLAMA_URL;
    $this->model_name         = OLLAMA_MODEL;
    $this->is_model_available = null;
  }


  // Check if the provider is available
  public function isAvailable(): bool {

    if (empty($this->api_url) && empty($this->model_name)) {
      return false;
    }

    if (!empty($this->api_url) && empty($this->model_name)) {
      $this->log->warning("$this->provider_name URL is set but model is not. The $this->provider_name summarizer will be disabled for this session.");
      return false;
    }

    if (empty($this->api_url) && !empty($this->model_name)) {
      $this->log->warning("$this->provider_name model is set but URL is not. The $this->provider_name summarizer will be disabled for this session.");
      return false;
    }

    return true;
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

    // Test if the API is reachable
    if (getHttpStatus($this->api_url) !== 200) {
      $this->log->error("$this->provider_name API URL is not reachable");
      $this->is_summarizer_available = false;
      return [];
    }

    $api_url = $this->api_url . '/api/generate';
    $curl_options = array(
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode(array(
        'model'   => $this->model_name,
        'stream'  => false,
        'system'  => $this->system_prompt,
        'prompt'  => $content,
        'options' => [
          'num_predict' => $this->max_tokens,
          'temperature' => $this->temperature,
        ]
      )),
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
      CURLOPT_TIMEOUT => MAX_EXECUTION_TIME
    );

    $curl_response = curlURL($api_url, $curl_options) ?? '';

    if (!$curl_response) {
      $this->log->error("$this->provider_name API response is empty or invalid");
      return [];
    }

    $response = json_decode($curl_response, true);

    if (!$response) {
      $this->log->error("$this->provider_name API response is not valid JSON");
      return [];
    }

    if (!empty($response['error']) &&
      is_string($response['error']) &&
      strpos($response['error'], 'model') !== false &&
      strpos($response['error'], 'not found') !== false) {
      $this->log->error("$this->provider_name model '{$this->model_name}' not found. Please check if the model is installed and the name is correct. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_model_available = false;
      return [];
    }

    if (!empty($response['error']) && is_string($response['error'])) {
      $this->log->error("$this->provider_name API error: " . $response['error']);
      return [];
    }

    if (!empty($response['eval_count']) && $response['eval_count'] === $this->max_tokens) {
      $this->log->warning("$this->provider_name API response is most likely truncated due to reaching the maximum token limit of " . $this->max_tokens);
    }

    if (!empty($response['response'])) {
      return ['summary' => $response['response']];
    }

    $this->log->error("$this->provider_name API response does not contain valid summary content");
    return [];
  }
}
