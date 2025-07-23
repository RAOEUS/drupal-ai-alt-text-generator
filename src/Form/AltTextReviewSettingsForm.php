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

    $form['openai_api_key'] = [
      '#type' => 'textarea',
      '#title' => $this->t('OpenAI API key'),
      '#default_value' => $config->get('openai_api_key'),
      '#required' => TRUE,
      '#rows' => 2,
    ];

    $form['ai_prompt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI Prompt'),
      '#description' => $this->t('The prompt sent to the AI. Use the token <code>[max_length]</code> to dynamically include the max character length setting below.'),
      '#default_value' => $config->get('ai_prompt') ?? 'Generate a concise alt text for this image, within [max_length] characters maximum.',
      '#rows' => 3,
      '#required' => TRUE,
    ];

    $form['alt_text_max_length'] = [
      '#type' => 'number',
      '#title' => $this->t('Alt text maximum character length'),
      '#description' => $this->t('The value that will replace the [max_length] token in the prompt.'),
      '#default_value' => $config->get('alt_text_max_length') ?? 128,
      '#min' => 50,
      '#required' => TRUE,
    ];

    $form['debug_mode'] = [
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
      ->set('openai_api_key', $form_state->getValue('openai_api_key'))
      ->set('ai_prompt', $form_state->getValue('ai_prompt'))
      ->set('alt_text_max_length', $form_state->getValue('alt_text_max_length'))
      ->set('debug_mode', $form_state->getValue('debug_mode'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
