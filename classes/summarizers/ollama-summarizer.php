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

    // Check if the model is actually available on the server
    $this->log->info("Checking if $this->provider_name summarizer is available with URL: " . $this->api_url . " and model: " . $this->model_name);
    return $this->isModelAvailable();
  }


  // Check if the model is available
  private function isModelAvailable(): bool {
    if ($this->is_model_available !== null) {
      return $this->is_model_available;
    }

    $tags_url = $this->api_url . '/api/tags';
    $curl_options = array(
      CURLOPT_CUSTOMREQUEST => 'GET',
      CURLOPT_HTTPHEADER => array(
        'Content-Type: application/json'
      ),
      CURLOPT_TIMEOUT => 2
    );

    $curl_response = curlURL($tags_url, $curl_options) ?? '';

    if (!$curl_response) {
      $this->log->warning("$this->provider_name URL " . $this->api_url . " is not reachable");
      return false;
    }

    $response = json_decode($curl_response, true);
    if (empty($response['models'])) {
      $this->log->warning("No models returned from $this->provider_name URL $tags_url. The $this->provider_name summarizer will be disabled for this session.");
      $this->is_model_available = false;
      return false;
    }

    $model_name = $this->model_name;
    foreach ($response['models'] as $model) {
      if (in_array($model['name'], [$model_name, $model_name . ':latest'])) {
        $this->log->info("$this->provider_name model $model_name is available at " . $this->api_url);
        $this->is_model_available = true;
        return true;
      }
    }

    $this->log->warning("Model $model_name is not available at $this->provider_name URL " . $this->api_url . ". The $this->provider_name summarizer will be disabled for this session.");
    $this->is_model_available = false;
    return false;
  }


  // Get the summary from the provider
  protected function getProviderSummary(string $content, string $url): array {

    $api_url = $this->api_url . '/api/generate';
    $curl_options = array(
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => json_encode(array(
        'model'       => $this->model_name,
        'stream'      => false,
        'temperature' => $this->temperature,
        'system'      => $this->system_prompt,
        'prompt'      => $content
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

    if (!empty($response['response'])) {
      return ['summary' => $response['response']];
    }

    $this->log->error("$this->provider_name API response does not contain valid summary content");
    return [];
  }
}
