<?php

namespace Summarizer;

abstract class Summarizer {

  // Properties
  protected $log;
  protected ?bool   $is_summarizer_available;
  protected string  $provider_name;
  protected ?string $model_name;
  protected float   $temperature;
  protected int     $max_tokens;
  protected string  $system_prompt;


  // Constructor
  public function __construct() {
    $this->is_summarizer_available = null;
    $this->temperature             = SUMMARY_TEMPERATURE;
    $this->max_tokens              = SUMMARY_MAX_TOKENS;
    $this->system_prompt           = SUMMARY_SYSTEM_PROMPT;
  }


  /**
   * Generate a summary from the given content
   *
   * @param string $content The content to summarize
   * @param string $url The URL of the content (for logging)
   * @return array ['summary' => string, 'provider' => string, 'model' => string]
   */
  public function generateSummary(string $content, string $url): array {
    // Validate content length
    if (str_word_count($content) < 200) {
      logger()->info("Content for URL $url is too short to generate a summary (" . str_word_count($content) . " words)");
      return ['summary' => '', 'provider' => '', 'model' => ''];
    }

    // Prepare content - strip tags and limit to 1000 words
    $prepared_content = strip_tags($content);
    $prepared_content = implode(' ', array_slice(explode(' ', $prepared_content), 0, 1000));

    // Try to generate summary
    $summary_data = $this->getProviderSummary($prepared_content, $url);

    // Check if provided summary is too short
    if (!empty($summary_data['summary']) && strlen($summary_data['summary']) <= 100) {
      logger()->warning("Summary for URL $url from " . $this->provider_name . " is too short (" . strlen($summary_data['summary']) . " characters)");
      return ['summary' => '', 'provider' => '', 'model' => ''];
    }

    if (!empty($summary_data['summary'])) {

      // Process summary through Parsedown for safety
      $Parsedown = new \Parsedown();
      $Parsedown->setSafeMode(true);
      $processed_summary = $Parsedown->text($summary_data['summary']);
      $processed_summary = str_replace(["\r", "\n"], '', $processed_summary);
      $processed_summary = trim($processed_summary);

      return [
        'summary'  => $processed_summary,
        'provider' => $this->provider_name,
        'model'    => $this->model_name ?? '',
      ];
    }

    return ['summary' => '', 'provider' => '', 'model' => ''];
  }


  /**
   * Check if this summarizer is available/configured
   *
   * @return bool
   */
  abstract public function isAvailable(): bool;

  /**
   * Get the summary from the provider
   *
   * @param string $content The prepared content to summarize
   * @param string $url The URL of the content (for logging)
   * @return array ['summary' => string] or empty array on failure
   */
  abstract protected function getProviderSummary(string $content, string $url): array;
}
