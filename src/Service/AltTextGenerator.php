<?php

namespace Drupal\alt_text_review\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Image\ImageFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Message;

class AltTextGenerator
{

  protected $httpClient;
  protected $configFactory;
  protected $imageFactory;
  protected $fileSystem;
  protected $logger;

  public function __construct(ClientInterface $http_client, ConfigFactoryInterface $config_factory, ImageFactory $image_factory, FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_factory)
  {
    $this->httpClient = $http_client;
    $this->configFactory = $config_factory;
    $this->imageFactory = $image_factory;
    $this->fileSystem = $file_system;
    $this->logger = $logger_factory->get('alt_text_review');
  }

  public function generateAltText(string $image_uri): string
  {
    $config = $this->configFactory->get('alt_text_review.settings');
    $api_key = trim($config->get('openai_api_key'));
    $debug_mode = $config->get('debug_mode') ?? FALSE;

    if (empty($api_key)) {
      return 'Error: OpenAI API key is not configured.';
    }

    if (!is_file($image_uri)) {
      $this->logger->error('Image file not found at URI: @uri', ['@uri' => $image_uri]);
      return 'Error: Image file not found.';
    }

    $image_data = file_get_contents($image_uri);
    $mime_type = finfo_buffer(finfo_open(), $image_data, FILEINFO_MIME_TYPE);
    $base64 = base64_encode($image_data);
    $data_uri = 'data:' . $mime_type . ';base64,' . $base64;

    $max_length = $config->get('alt_text_max_length') ?? 128;
    $max_tokens = (int) ceil($max_length / 4); #calculate a token budget (approx. 4 chars/token)
    $prompt_template = $config->get('ai_prompt') ?? 'Generate a concise alt text for this image, within [max_length] characters maximum.';
    $prompt_text = str_replace('[max_length]', $max_length, $prompt_template);

    $request_options = [
      'headers' => [
        'Authorization' => 'Bearer ' . $api_key,
        'Content-Type' => 'application/json',
      ],
      'json' => [
        'model' => 'gpt-4o-mini',
        'messages' => [
          [
            'role' => 'user',
            'content' => [
              ['type' => 'text', 'text' => $prompt_text],
              ['type' => 'image_url', 'image_url' => ['url' => $data_uri]],
            ],
          ],
        ],
        'max_tokens' => $max_tokens,
      ],
    ];

    if ($debug_mode) {
      $this->logger->debug('OpenAI Request Body: @json', ['@json' => json_encode($request_options['json'], JSON_PRETTY_PRINT)]);
    }

    try {
      $response = $this->httpClient->post('https://api.openai.com/v1/chat/completions', $request_options);
      $body = $response->getBody()->getContents();
      if ($debug_mode) {
        $this->logger->debug('OpenAI Response: @body', ['@body' => $body]);
      }
      $data = json_decode($body, TRUE);
      return trim($data['choices'][0]['message']['content'] ?? '');
    }
    catch (RequestException $e) {
      $this->logger->error('OpenAI API request failed: @message', ['@message' => $e->getMessage()]);
      if ($debug_mode && $e->hasResponse()) {
        $this->logger->debug("--- DEBUG: FAILED REQUEST ---\n@request\n\n--- DEBUG: FAILED RESPONSE ---\n@response", [
          '@request' => Message::toString($e->getRequest()),
          '@response' => Message::toString($e->getResponse()),
        ]);
      }
      return 'Error: The AI suggestion could not be generated.';
    }
  }
}
