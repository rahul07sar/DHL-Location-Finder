<?php
namespace Drupal\dhl_location_finder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Cache\Cache;

class DHLLocationFinderController extends ControllerBase {

  public function form() {
    $form = \Drupal::formBuilder()->getForm('Drupal\dhl_location_finder\Form\DHLLocationFinderForm');
    $this->cache->invalidateTags(['dhl_location_finder']);
    return $form;
  }

}