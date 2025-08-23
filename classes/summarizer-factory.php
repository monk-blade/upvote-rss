<?php

class SummarizerFactory {

  // Properties
  private static array $summarizers = [];


  /**
   * Get all available summarizers in order of preference
   *
   * @return array Array of Summarizer instances
   */
  public static function getAvailableSummarizers(): array {
    if (empty(self::$summarizers)) {
      $potential_summarizers = [
        new \Summarizer\Ollama(),
        new \Summarizer\GoogleGemini(),
        new \Summarizer\OpenAI(),
        new \Summarizer\Anthropic(),
        new \Summarizer\OpenAICompatible(),
      ];

      foreach ($potential_summarizers as $summarizer) {
        if ($summarizer->isAvailable()) {
          self::$summarizers[] = $summarizer;
        }
      }
    }

    return self::$summarizers;
  }


  /**
   * Try to generate a summary using available summarizers
   * Falls back to next available summarizer if one fails
   *
   * @param string $content The content to summarize
   * @param string $url The URL of the content (for logging)
   * @return array ['summary' => string, 'provider' => string, 'model' => string]
   */
  public static function generateSummary(string $content, string $url): array {
    $summarizers = self::getAvailableSummarizers();

    if (empty($summarizers)) {
      return ['summary' => '', 'provider' => '', 'model' => ''];
    }

    foreach ($summarizers as $summarizer) {
      $result = $summarizer->generateSummary($content, $url);
      if (!empty($result['summary'])) {
        return $result;
      }
    }

    return ['summary' => '', 'provider' => '', 'model' => ''];
  }
}
