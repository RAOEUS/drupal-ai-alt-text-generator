<?php

namespace Drupal\alt_text_review\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media\Entity\Media;

class AltTextReviewForm extends FormBase
{

  public function getFormId()
  {
    return 'alt_text_review_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, Media $media = NULL, string $uri = NULL)
  {
    $suggestion = \Drupal::service('alt_text_review.alt_text_generator')->generateAltText($uri);

    $form['image'] = [
      '#theme' => 'image',
      '#uri' => $media->get('field_media_image')->entity->getFileUri(),
    ];
    $form['suggestion'] = [
      '#type' => 'textarea',
      '#title' => $this->t('AI suggestion'),
      '#default_value' => $suggestion,
      '#disabled' => TRUE,
    ];
    $form['alt'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Alt text'),
      '#default_value' => $suggestion,
      '#required' => TRUE,
    ];
    $form['media_id'] = [
      '#type' => 'hidden',
      '#value' => $media->id(),
    ];
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save alt text'),
    ];
    return $form;
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
