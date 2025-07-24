<?php

namespace Drupal\alt_text_review\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\ReplaceCommand;

class AltTextReviewForm extends FormBase
{

  public function getFormId()
  {
    return 'alt_text_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, ?Media $media = NULL, ?string $uri = NULL)
  {
    if (!$media || !$uri) {
      $this->messenger()->addWarning($this->t('No image found that requires alt text.'));
      return [];
    }

    // Get the initial suggestion on first load.
    if (!$form_state->isRebuilding()) {
      $suggestion = \Drupal::service('alt_text_review.alt_text_generator')->generateAltText($uri);
      $form_state->set('suggestion', $suggestion);
    } else {
      $suggestion = $form_state->get('suggestion');
    }

    $form['image'] = [
      '#theme' => 'image',
      '#uri' => $media->get('field_media_image')->entity->getFileUri(),
    ];

    // Add a wrapper around the textareas for AJAX replacement.
    $form['suggestions_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'suggestions-wrapper'],
    ];

    $form['suggestions_wrapper']['suggestion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI suggestion'),
      '#default_value' => $suggestion,
      '#disabled' => TRUE,
    ];

    $form['suggestions_wrapper']['alt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Alt text'),
      '#default_value' => $suggestion,
      '#required' => TRUE,
    ];

    $form['media_id'] = [
      '#type' => 'hidden',
      '#value' => $media->id(),
    ];

    $form['uri'] = [
      '#type' => 'hidden',
      '#value' => $uri,
    ];

    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save alt text'),
    ];

    // Add the new AJAX button.
    $form['actions']['new_suggestion'] = [
      '#type' => 'button',
      '#value' => $this->t('🔄 Generate New Suggestion'),
      '#ajax' => [
        'callback' => '::ajaxGetNewSuggestion',
        'wrapper' => 'suggestions-wrapper',
        'event' => 'click',
      ],
    ];

    return $form;
  }

  /**
   * AJAX callback to get a new suggestion.
   */
  public function ajaxGetNewSuggestion(array &$form, FormStateInterface $form_state) {
    $uri = $form_state->getValue('uri');
    $new_suggestion = \Drupal::service('alt_text_review.alt_text_generator')->generateAltText($uri);

    // Store the new suggestion in the form state so it persists.
    $form_state->set('suggestion', $new_suggestion);

    // Replace the content of the textareas.
    $form['suggestions_wrapper']['suggestion']['#value'] = $new_suggestion;
    $form['suggestions_wrapper']['alt']['#value'] = $new_suggestion;

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#suggestions-wrapper', $form['suggestions_wrapper']));

    return $response;
  }


  public function submitForm(array &$form, FormStateInterface $form_state)
  {
    $media = Media::load($form_state->getValue('media_id'));
    $media->get('field_media_image')->alt = $form_state->getValue('alt');
    $media->save();
    $this->messenger()->addStatus($this->t('Alt text saved.'));
    $form_state->setRedirect('alt_text_review.review');
  }
}
