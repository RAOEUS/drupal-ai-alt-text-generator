<?php

namespace Drupal\alt_text_review\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\alt_text_review\Service\AltTextGenerator;

class AltTextReviewController extends ControllerBase
{

  protected $generator;

  public function __construct(AltTextGenerator $generator)
  {
    $this->generator = $generator;
  }

  public static function create(ContainerInterface $container)
  {
    return new static(
      $container->get('alt_text_review.alt_text_generator')
    );
  }

  public function review()
  {
    $query = \Drupal::entityQuery('media')
      ->accessCheck(TRUE)
      ->condition('bundle', 'image');
    $or = $query->orConditionGroup()
      ->condition('field_media_image.alt', '')
      ->condition('field_media_image.alt', NULL, 'IS NULL');
    $query->condition($or)
      ->range(0, 1);
    $mids = $query->execute();

    if (empty($mids)) {
      return ['#markup' => $this->t('All images have alt text.')];
    }

    $mid = reset($mids);
    $media = $this->entityTypeManager()->getStorage('media')->load($mid);
    $file = $media->get('field_media_image')->entity;
    $uri = $file->getFileUri();

    return $this->formBuilder()
      ->getForm(\Drupal\alt_text_review\Form\AltTextReviewForm::class, $media, $uri);
  }
}
