<?php
namespace Drupal\alt_text_review\Service;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
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
    $api_provider = $config->get('api_provider') ?? 'openai';
    $debug_mode = $config->get('debug_mode') ?? FALSE;
    if (!is_file($image_uri)) {
      $this->logger->error('Image file not found at URI: @uri', ['@uri' => $image_uri]);
      return 'Error: Image file not found.';
    }
    $image_data = file_get_contents($image_uri);
    $base64 = base64_encode($image_data);
    $max_length = $config->get('alt_text_max_length') ?? 128;
    $prompt_template = $config->get('ai_prompt') ?? 'Generate a concise alt text for this image, within [max_length] characters maximum.';
    $prompt_text = str_replace('[max_length]', $max_length, $prompt_template);
    if ($api_provider === 'ollama') {
      return $this->generateWithOllama($base64, $prompt_text, $config, $debug_mode);
    }
    // Default to OpenAI.
    $mime_type = finfo_buffer(finfo_open(), $image_data, FILEINFO_MIME_TYPE);
    return $this->generateWithOpenAI($base64, $mime_type, $prompt_text, $config, $debug_mode);
  }

  private function generateWithOllama(string $base64_image, string $prompt_text, ImmutableConfig $config, bool $debug_mode): string
  {
    $hostname = trim($config->get('ollama_hostname'));
    if (empty($hostname)) {
      return 'Error: Ollama hostname is not configured.';
    }

    $model_name = $config->get('ollama_model') ?? 'llava';
    $max_length = $config->get('alt_text_max_length') ?? 128;
    $max_tokens = (int) ceil($max_length / 4);
    $api_url = rtrim($hostname, '/') . '/api/generate';

    $request_options = [
      'headers' => [
        'Content-Type' => 'application/json',
        'Connection' => 'close',
      ],
      'json' => [
        'model' => $model_name,
        'prompt' => $prompt_text,
        'images' => [$base64_image],
        'stream' => FALSE,
        'options' => [
          'num_predict' => $max_tokens,
          'temperature' => 0.2, // Lowered temperature for more factual responses
        ],
      ],
      'timeout' => 60,
    ];

    if ($debug_mode) {
      $this->logger->debug('Ollama Request Body: @json', ['@json' => json_encode($request_options['json'], JSON_PRETTY_PRINT)]);
    }

    try {
      $response = $this->httpClient->post($api_url, $request_options);
      $body = $response->getBody()->getContents();
      if ($debug_mode) {
        $this->logger->debug('Ollama Response: @body', ['@body' => $body]);
      }
      $data = json_decode($body, TRUE);
      return trim($data['response'] ?? 'Error: Unexpected response format from Ollama.');
    }
    catch (RequestException $e) {
      $this->logger->error('Ollama API request failed: @message', ['@message' => $e->getMessage()]);
      if ($debug_mode && $e->hasResponse()) {
        $this->logger->debug("--- DEBUG: FAILED REQUEST ---\n@request\n\n--- DEBUG: FAILED RESPONSE ---\n@response", [
          '@request' => Message::toString($e->getRequest()),
          '@response' => Message::toString($e->getResponse()),
        ]);
      }
      return 'Error: The AI suggestion could not be generated. Check if the Ollama service is running.';
    }
  }

  private function generateWithOpenAI(string $base64, string $mime_type, string $prompt_text, ImmutableConfig $config, bool $debug_mode): string
  {
    $api_key = trim($config->get('openai_api_key'));
    if (empty($api_key)) {
      return 'Error: OpenAI API key is not configured.';
    }
    $data_uri = 'data:' . $mime_type . ';base64,' . $base64;
    $max_length = $config->get('alt_text_max_length') ?? 128;
    $max_tokens = (int) ceil($max_length / 4);
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
      return trim($data['choices'][0]['message']['content'] ?? 'Error: Unexpected response format from OpenAI.');
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
