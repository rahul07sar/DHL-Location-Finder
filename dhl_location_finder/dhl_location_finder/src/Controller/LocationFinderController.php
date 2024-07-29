<?php

namespace Drupal\dhl_location_finder\Controller;

use Symfony\Component\HttpFoundation\Request;
use Drupal\Core\Controller\ControllerBase;

class LocationFinderController extends ControllerBase {

    public function locations(Request $request) {
        $output = $request->query->get('output');
        return [
            '#theme' => 'location_finder',
            '#output' => $output,
        ];
    }

}