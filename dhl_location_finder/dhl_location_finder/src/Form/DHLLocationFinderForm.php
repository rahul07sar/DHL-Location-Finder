<?php

/**
 * @file
 * Contains Drupal\dhl_location_finder\Form\DHLLocationFinderForm.
 */

namespace Drupal\dhl_location_finder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use GuzzleHttp\Client;
use Drupal\Core\Cache\Cache;

class DHLLocationFinderForm extends FormBase {

    public function getFormId() {
        return 'dhl_location_finder_form';
    }

    public function buildForm(array $form, FormStateInterface $form_state) {
        $form['#cache'] = ['max-age' => 0];

        $form['country'] = [
            '#type' => 'textfield',
            '#title' => t('Country'),
            '#required' => TRUE,
            '#attributes' => [
                'placeholder' => 'Enter Country name here',
            ],
						'#description' => t('Enter the country where the location is situated.'),
        ];
        
        $form['city'] = [
            '#type' => 'textfield',
            '#title' => t('City'),
            '#required' => TRUE,
            '#attributes' => [
                'placeholder' => 'Enter City name here',
            ],
					  '#description' => t('Enter the city where the location is situated.'),
        ];
        $form['postal_code'] = [
            '#type' => 'textfield',
            '#title' => t('Postal Code'),
            '#maxlength' => 6,
            '#size' => 20,
            '#required' => TRUE,
           // '#pattern' => '[0-9]{1,6}',
            '#attributes' => [
                'placeholder' => 'Enter the postal code for the location.e',
            ],
						'#description' => t('Enter the postal code for the location.'),
        ];

        $form['submit'] = [
            '#type' => 'submit',
            '#value' => t('Submit'),
        ];

        return $form;
    }

    public function submitForm(array &$form, FormStateInterface $form_state) {

        $country = $form_state->getValue('country');
        $city = $form_state->getValue('city');
        $postal_code = $form_state->getValue('postal_code');

        $country = $form_state->getValue('country');
        $country_manager = \Drupal::service('country_manager');
        $countries = $country_manager->getList();
        
        // Looping through the countries and finding the one that matches the user's input
        foreach ($countries as $country_code => $country_name) {
          if (strtolower($country_name) == strtolower($country)) {
            $country_code = $country_code;
            break;
          }
        }

        $client = new Client();
        $response = $client->get('https://api.dhl.com/location-finder/v1/find-by-address', [
            'headers' => [
                'DHL-API-Key' => 'demo-key',
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'query' => [
                'countryCode' => $country_code,
                'addressLocality' => $city,
                'PostalCode' => $postal_code,
            ],
        ]);

        $locations = json_decode($response->getBody()->getContents());
        $filtered_locations = [];
        foreach ($locations->locations as $location) {
					if ($location->place->address->postalCode == $postal_code && $this->isOpenOnWeekends($location) && $this->hasOddNumberInAddress($location)) {
                $filtered_locations[] = $location;
            }
        }
        $output = '';
        if (empty($filtered_locations)) {
            $output = 'No locations found. Please check your country and city name.';
        } else {
            foreach ($filtered_locations as $location) {
                if ($output != '') {
                    $output .= "---\n";
                }
                $output .= "locationName: " . $location->name . "\n";
                $output .= "address:\n";
                $output .= "  countryCode: " . $location->place->address->countryCode . "\n";
                $output .= "  postalCode: " . $location->place->address->postalCode . "\n";
                $output .= "  addressLocality: " . $location->place->address->addressLocality . "\n";
                $output .= "  streetAddress: " . $location->place->address->streetAddress . "\n";
                $output .= "openingHours:\n";
                $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
								foreach ($location->openingHours as $hours) {
									$dayOfWeek = str_replace('http://schema.org/', '', $hours->dayOfWeek);
									$output .= "  " . $dayOfWeek . ": " . $hours->opens . " - " . $hours->closes . "\n";
							}
            }
        }
        $form_state->setRedirect('dhl_location_finder.locations', ['output' => $output]);
        \Drupal::service('cache.render')->invalidateAll();
        \Drupal::service('cache.dynamic_page_cache')->invalidateAll();
        \Drupal::service('cache.page')->invalidateAll();
        \Drupal::service('cache.menu')->invalidateAll();
    }

    private function isOpenOnWeekends($location) {
        $openOnWeekends = false;
        foreach ($location->openingHours as $hours) {
            $day = $this->getDayOfWeekFromUrl($hours->dayOfWeek);
            if (in_array($day, ['Saturday', 'Sunday']) && $hours->opens != '00:00:00' && $hours->closes != '00:00:00') {
                $openOnWeekends = true;
                break;
            }
        }
        return $openOnWeekends;
    }

    private function getDayOfWeekFromUrl($url) {
        $parts = parse_url($url);
        $path = $parts['path'];
        $day = ltrim(str_replace('http://schema.org/', '', $path), '/');
        $days = [
          'Monday' => 'Monday',
          'Tuesday' => 'Tuesday',
          'Wednesday' => 'Wednesday',
          'Thursday' => 'Thursday',
          'Friday' => 'Friday',
          'Saturday' => 'Saturday',
          'Sunday' => 'Sunday',
        ];
        return $days[$day];
      }

			private function hasOddNumberInAddress($location) {
				if (isset($location->place->address->streetAddress)) {
						$streetAddress = $location->place->address->streetAddress;
						// dd($streetAddress);
						preg_match_all('/\d+/', $streetAddress, $matches);
						foreach ($matches[0] as $number) {
                            // If odd number is found, return true
										return true; 
						}
                        // If no odd numbers found
						return false; 
				}
                // If no street address found
				return false; 
		}
}
