<?php
namespace Drupal\alt_text_review\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
class AltTextReviewSettingsForm extends ConfigFormBase
{
  /**
   * {@inheritdoc}
   */
  public function getFormId()
  {
    return 'alt_text_review_settings';
  }
  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames()
  {
    return ['alt_text_review.settings'];
  }
  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state)
  {
    $config = $this->config('alt_text_review.settings');
    $form['api_provider'] = [
      '#type' => 'radios',
      '#title' => $this->t('API Provider'),
      '#description' => $this->t('Select the service to use for generating alt text.'),
      '#options' => [
        'openai' => $this->t('OpenAI'),
        'ollama' => $this->t('Ollama (local)'),
      ],
      '#default_value' => $config->get('api_provider') ?? 'openai',
      '#required' => TRUE,
    ];
    $form['openai_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('OpenAI Settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="api_provider"]' => ['value' => 'openai'],
        ],
      ],
    ];
    $form['openai_settings']['openai_api_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('OpenAI API key'),
      '#default_value' => $config->get('openai_api_key'),
      '#rows' => 2,
      '#states' => [
        'required' => [
          ':input[name="api_provider"]' => ['value' => 'openai'],
        ],
      ],
    ];
    $form['ollama_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Ollama Settings'),
      '#open' => TRUE,
      '#states' => [
        'visible' => [
          ':input[name="api_provider"]' => ['value' => 'ollama'],
        ],
      ],
    ];
    $form['ollama_settings']['ollama_hostname'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama Hostname'),
      '#description' => $this->t('The hostname and port for your local Ollama instance (e.g., <code>http://localhost:11434</code>).'),
      '#default_value' => $config->get('ollama_hostname') ?? 'http://localhost:11434',
      '#states' => [
        'required' => [
          ':input[name="api_provider"]' => ['value' => 'ollama'],
        ],
      ],
    ];
    $form['ollama_settings']['ollama_model'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Ollama Model'),
      '#description' => $this->t('Specify the name of the vision model to use (e.g., <code>llava</code>, <code>moondream</code>). The model must be pulled in your Ollama instance.'),
      '#default_value' => $config->get('ollama_model') ?? 'llava',
      '#states' => [
        'required' => [
          ':input[name="api_provider"]' => ['value' => 'ollama'],
        ],
      ],
    ];
    $form['common_settings'] = [
      '#type' => 'details',
      '#title' => $this->t('Common Settings'),
      '#open' => TRUE,
    ];
    $form['common_settings']['ai_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Prompt'),
      '#description' => $this->t('The prompt sent to the AI. Use the token <code>[max_length]</code> to dynamically include the max character length setting below.'),
      '#default_value' => $config->get('ai_prompt') ?? 'Provide a literal description of what is visible in this image. Do not add any interpretation or names.',
      '#rows' => 3,
      '#required' => TRUE,
    ];
    $form['common_settings']['alt_text_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Alt text maximum character length'),
      '#description' => $this->t('The value that will replace the [max_length] token in the prompt.'),
      '#default_value' => $config->get('alt_text_max_length') ?? 128,
      '#min' => 50,
      '#required' => TRUE,
    ];
    $form['common_settings']['debug_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable debug logging'),
      '#description' => $this->t('Log detailed API requests and responses. This should be disabled on a production site.'),
      '#default_value' => $config->get('debug_mode'),
    ];
    return parent::buildForm($form, $form_state);
  }
  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $this->config('alt_text_review.settings')
      ->set('api_provider', $form_state->getValue('api_provider'))
      ->set('openai_api_key', $form_state->getValue('openai_api_key'))
      ->set('ollama_hostname', $form_state->getValue('ollama_hostname'))
      ->set('ollama_model', $form_state->getValue('ollama_model'))
      ->set('ai_prompt', $form_state->getValue('ai_prompt'))
      ->set('alt_text_max_length', $form_state->getValue('alt_text_max_length'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
