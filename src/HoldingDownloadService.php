<?php

namespace Drupal\atom;

use Drupal\Core\Datetime\DrupalDateTime;
use GuzzleHttp\Exception\RequestException;
use Masterminds\HTML5\Exception;
use Drupal\node\Entity\Node;
use Drupal\taxonomy\Entity\Term;

/**
 * Class HoldingDownloadService.
 */
class HoldingDownloadService implements HoldingDownloadServiceInterface
{

    /**
     * Constructs a new HoldingDownloadService object.
     */
    public function __construct()
    {

    }

    

    public function callATOMServer($repo_id, $param = null)

    {

        $config = \Drupal::config('atom.atomapiconfig');

        try {
          if (empty($param)) {
                if ($repo_id != -1) {
                    $request_url = $config->get('host')."/index.php/api/informationobjects?sortDir=asc&sort=alphabetic&sq0=&sf0=&repos=".$repo_id."&findingAidStatus=&topLod=1&rangeType=inclusive";
                } else {
                    $request_url = $config->get('host')."/index.php/api/informationobjects?sortDir=asc&sort=alphabetic&sq0=&sf0=&findingAidStatus=&topLod=1&rangeType=inclusive";
                }
                $response = \Drupal::httpClient()->request('GET', $request_url, 
                [ 
                    'headers' => [ 
                    'REST-API-Key' => $config->get("atom-api-key")]
                ]
                );

                $initial_call = json_decode((string)$response->getBody());
                $total_pages = intdiv($initial_call->total, 50);

                $result_array = array();

                for ($i = 0; $i <= $total_pages; $i++) {
                    $skip_val = $i * 50;
                    if ($repo_id != -1) {
                        $request_url = $config->get('host')."/index.php/api/informationobjects?sortDir=asc&sort=alphabetic&sq0=&sf0=&repos=".$repo_id."&findingAidStatus=&topLod=1&rangeType=inclusive&skip=".$skip_val;
                    } else {
                        $request_url = $config->get('host')."/index.php/api/informationobjects?sortDir=asc&sort=alphabetic&sq0=&sf0=&findingAidStatus=&topLod=1&rangeType=inclusive&skip=".$skip_val;
                    }
                    $response = \Drupal::httpClient()->request('GET', $request_url, 
                    [ 
                        'headers' => [ 
                        'REST-API-Key' => $config->get("atom-api-key")]
                    ]
                    );

                    $current_page = json_decode((string)$response->getBody());
                    $result_array = array_merge($result_array, $current_page->results);
                }
                return $result_array;
                
        } else {
            $response = \Drupal::httpClient()->request('GET', $config->get('host')."/index.php/api/informationobjects/".$param, 
            [ 
                'headers' => [ 
                'REST-API-Key' => $config->get("atom-api-key")]
            ]
            );
        }
        return json_decode((string)$response->getBody());
        } catch (RequestException $e) {
            print_r($e->getMessage());
            return null;
        }
    }

    public function get($repo_id = null, $params = null)
    {
        $config = \Drupal::config('atom.atomapiconfig');

        try {
            $contents = $this->callATOMServer($repo_id, $params);
        } catch (RequestException $e) {
            print_log($e->getMessage());
            return null;
        }
        return $contents;
    }

    /**
     * @param array $holdings
     */
    public function atomHoldingToNode($holdings)
    {
        foreach ($holdings as $holding) {
            $holding_id = $holding->slug;
            $nids = $this->queryHoldingNode($holding_id);
            if (count($nids) <= 0) {
                $this->createNewHoldingNode($holding_id);
            } else {
                $this->updateHoldingNode($nids, $holding_id);
            }
        
        }   

    }

  

    /**
     * @param $holding_id
     * @return mixed
     */
    public function queryHoldingNode($holding_id)
    {
        $query = \Drupal::entityQuery('node');
        $query->condition('status', 1);
        $query->condition('type', "holding");
        $query->condition('field_atom_id', $holding_id);
        return $query->execute();

    }

    public function updatePastFieldHoldingNode($flag)
    {
        $query = \Drupal::entityQuery('node');
        $query->condition('type', "holding");
        //$query->condition('field_past_event', false);
        $nids  = $query->execute();
        foreach ($nids as $nid) {
            $holdingnode = \Drupal\node\Entity\Node::load($nid);
            //$eventnode->set('field_past_event', $flag);

            // check if current timestamp with event timestamp
            //\Drupal::messenger()->addMessage($eventnode->id() . ") ". $eventnode->getTitle() . " " . time(). " " . $eventnode->get("field_start_date")->getValue()[0]['value']. " = " . (time() > strtotime($eventnode->get("field_start_date")->getValue()[0]['value'])) , "warning");
	    
            $holdingnode->save();
        }
    }

    /**
     * @param $holding
     */
    public function createNewHoldingNode($holding)
    {   
        $detailedHoldingInfo = $this->get(null, $holding);

        $scope_and_content = !empty($detailedHoldingInfo->scope_and_content) ?  $detailedHoldingInfo->scope_and_content: '';


        if ($tid_repository = $this->getTidByName($detailedHoldingInfo->repository, 'holding_repository')) {
            $repository_term = Term::load($tid_repository);
        } else {
            $term_create = Term::create(array('name' => $detailedHoldingInfo->repository, 'vid' => 'holding_repository' ))->save();
            if ($tid_repository = $this->getTidByName($detailedHoldingInfo->repository, 'holding_repository')) {
                $repository_term = Term::load($tid_repository);
            }
        }

        if ($tid_level_of_description = $this->getTidByName($detailedHoldingInfo->level_of_description, 'holding_level_of_description')) {
            $level_of_description_term = Term::load($tid_level_of_description);
        } else {
            $term_create = Term::create(array('name' => $detailedHoldingInfo->level_of_description, 'vid' => 'holding_level_of_description' ))->save();
            if ($tid_level_of_description = $this->getTidByName($detailedHoldingInfo->level_of_description, 'holding_level_of_description')) {
                $level_of_description_term = Term::load($tid_level_of_description);
            }
        }

        $creators = $detailedHoldingInfo->creators;

        $creators_array = array();

        foreach ($creators as $creator) {
            $creator_name = $creator->authotized_form_of_name;
            $history = $creator->history;
            if ($tid_creator = $this->getTidByName($creator_name, 'holding_creators')) {
                $creator_term = Term::load($tid_creator);
                //update description (history), and dates of existence
                $creator_term->field_date_of_existence->setValue($creator->dates_of_existence);
                $creator_term->description->setValue($history);
                $creator_term->save();
            } else {
                $term_create = Term::create(array('name' => $creator_name, 'vid' => 'holding_creators', 'field_date_of_existence' => $creator->dates_of_existence, 'description'  => array('value' => $history,'format' => 'full_html') ))->save();
                if ($tid_creator = $this->getTidByName($creator_name, 'holding_creators')) {
                    $creator_term = Term::load($tid_creator);
                }
            }
            array_push($creators_array, $creator_term);
        }
        

        $params = [
            // The node entity bundle.
            'type' => 'holding',
            'langcode' => 'en',
            'created' => time(),
            'changed' => time(),
            // The user ID.
            'uid' => 1,
            'moderation_state' => 'published',

            // holding fields
            'title' => $detailedHoldingInfo->title,
            'body' => [
                'summary' => substr(strip_tags($scope_and_content), 0, 100),
                'value' => str_replace("<p>&nbsp;</p>", "", $scope_and_content),
                'format' => 'full_html'
            ],
            'field_date_range' => $detailedHoldingInfo->dates[0]->date,
            'field_creator' => $creators_array,
            'field_level_of_description' => $level_of_description_term,
            'field_repository' => $repository_term,
            'field_atom_id' => $holding,
            'field_reference_code' => !empty($detailedHoldingInfo->reference_code) ? $detailedHoldingInfo->reference_code: '', // need to make sure it's unique
            
        ];
        $node = Node::create($params);
        $node->save();
    }

    protected function getTidByName($name = NULL, $vocabulary = NULL) {
        $properties = [];
        if (!empty($name)) {
          $properties['name'] = $name;
        }
        if (!empty($vocabulary)) {
          $properties['vid'] = $vocabulary;
        }
        $terms = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties($properties);
        $term = reset($terms);
        return !empty($term) ? $term->id() : 0;

    }

    /**
     * @param $nids
     * @param $holding
     */
    public function updateHoldingNode($nids, $holding)
    {
        $detailedHoldingInfo = $this->get(null, $holding);

        $scope_and_content = !empty($detailedHoldingInfo->scope_and_content) ?  $detailedHoldingInfo->scope_and_content: '';


        if ($tid_repository = $this->getTidByName($detailedHoldingInfo->repository, 'holding_repository')) {
            $repository_term = Term::load($tid_repository);
        } else {
            $term_create = Term::create(array('name' => $detailedHoldingInfo->repository, 'vid' => 'holding_repository' ))->save();
            if ($tid_repository = $this->getTidByName($detailedHoldingInfo->repository, 'holding_repository')) {
                $repository_term = Term::load($tid_repository);
            }
        }

        if ($tid_level_of_description = $this->getTidByName($detailedHoldingInfo->level_of_description, 'holding_level_of_description')) {
            $level_of_description_term = Term::load($tid_level_of_description);
        } else {
            $term_create = Term::create(array('name' => $detailedHoldingInfo->level_of_description, 'vid' => 'holding_level_of_description' ))->save();
            if ($tid_level_of_description = $this->getTidByName($detailedHoldingInfo->level_of_description, 'holding_level_of_description')) {
                $level_of_description_term = Term::load($tid_level_of_description);
            }
        }

        $creators = $detailedHoldingInfo->creators;

        $creators_array = array();

        foreach ($creators as $creator) {
            $creator_name = $creator->authotized_form_of_name;
            $history = $creator->history;
            if ($tid_creator = $this->getTidByName($creator_name, 'holding_creators')) {
                $creator_term = Term::load($tid_creator);
                //update description (history), and dates of existence
                $creator_term->field_date_of_existence->setValue($creator->dates_of_existence);
                $creator_term->description->setValue($history);
                $creator_term->save();

            } else {
                $term_create = Term::create(array('name' => $creator_name, 'vid' => 'holding_creators','field_date_of_existence' => $creator->dates_of_existence, 'description'  => array('value' => $history,'format' => 'full_html')))->save();
                if ($tid_creator = $this->getTidByName($creator_name, 'holding_creators')) {
                    $creator_term = Term::load($tid_creator);
                }
            }
            array_push($creators_array, $creator_term);
        }
        

        // update existing Holding node
        $holdingNode = Node::load(array_values($nids)[0]);
        if (isset($holdingNode)) {
            $holdingNode->set('changed', time());
            // The user ID.
            $holdingNode->set('title', $detailedHoldingInfo->title);
            $holdingNode->set('body', [
                'summary' => substr(strip_tags($scope_and_content), 0, 100),
                'value' => str_replace("<p>&nbsp;</p>", "", $scope_and_content),
                'format' => 'full_html'
            ]);
            $holdingNode->set('field_date_range', $daterange);
            $holdingNode->set('field_creator', $creators_array);
            $holdingNode->set('field_reference_code', !empty($detailedHoldingInfo->reference_code) ? $detailedHoldingInfo->reference_code: ''); // need to make sure it's unique
            $holdingNode->set('field_repository', $repository_term );
            $holdingNode->set('field_level_of_description', $level_of_description_term);
            $holdingNode->set('field_atom_id', $holding);

            $holdingNode->save();
        }
    }


}
