<?php

namespace Drupal\atom;

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
            $request_url = $config->get('host')."/index.php/api/informationobjects";
            if (empty($param)) {
                $request_url .= '?sortDir=asc&sort=alphabetic&topLod=1';
                if (!empty($repo_id)) {
                    $request_url .= "&repos=$repo_id";
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
                    $response = \Drupal::httpClient()->request('GET', $request_url."&skip=$skip_val",
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
                $response = \Drupal::httpClient()->request('GET', $request_url."/$param",
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
        try {
            $contents = $this->callATOMServer($repo_id, $params);
        } catch (RequestException $e) {
            print_log($e->getMessage());
            return null;
        }
        return $contents;
    }

    // Some public variables for reporting
    public $created = 0;
    public $updated = 0;
    public $deleted = 0;

    /**
     * @param array $holdings
     */
    public function atomHoldingToNode($holdings)
    {
        $nids = [];

        foreach ($holdings as $holding) {
            $nid = $this->createNewHoldingNode($holding);
            $nids[] = $nid;
        }

        return $nids;
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

    /**
     * @param $holding
     * @return $nid
     */
    public function createNewHoldingNode($holding)
    {
        $detailedHoldingInfo = $this->get(null, $holding->slug);
        $scope_and_content = !empty($detailedHoldingInfo->scope_and_content) ? $detailedHoldingInfo->scope_and_content : '';

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
                $term_create = Term::create(array('name' => $creator_name, 'vid' => 'holding_creators', 'field_date_of_existence' => $creator->dates_of_existence, 'description' => array('value' => $history,'format' => 'full_html')))->save();
                if ($tid_creator = $this->getTidByName($creator_name, 'holding_creators')) {
                    $creator_term = Term::load($tid_creator);
                }
            }
            array_push($creators_array, $creator_term);
        }

        $nodeParams = [
            // The node entity bundle.
            'type' => 'holding',
            'langcode' => 'en',
            // The user ID.
            'uid' => 1,
            'moderation_state' => 'published'
        ];

        $holdingParams = [
            // holding fields
            'title' => $detailedHoldingInfo->title,
            'body' => [
                'summary' => mb_substr(strip_tags($scope_and_content), 0, 100),
                'value' => str_replace("<p>&nbsp;</p>", "", $scope_and_content),
                'format' => 'full_html'
            ],
            'field_date_range' => $detailedHoldingInfo->dates[0]->date,
            'field_creator' => $creators_array,
            'field_level_of_description' => $level_of_description_term,
            'field_repository' => $repository_term,
            'field_atom_id' => $detailedHoldingInfo->id,
            'field_slug'=> $holding->slug,
            'field_finding_aid_status'=> isset($detailedHoldingInfo->finding_aids_status) ? $detailedHoldingInfo->finding_aids_status : 0,
            'field_extent_and_medium' => $detailedHoldingInfo->extent_and_medium,
            'field_conditions_governing_acces' => $detailedHoldingInfo->conditions_governing_access,
            'field_reference_code' => !empty($detailedHoldingInfo->reference_code) ? $detailedHoldingInfo->reference_code : ''
        ];

        $newNode = Node::create(array_merge($nodeParams, $holdingParams));

        // Determine if we should create a new node or update an existing one
        $nids = $this->queryHoldingNode($detailedHoldingInfo->id);

        if (count($nids) <= 0) {
            // New node, so just save it and we're done.
            $newNode->set('created', time());
            $newNode->set('changed', time());
            $newNode->save();
            // Node receives a nid after it has been saved
            $nid = $newNode->nid->value;
            $this->created++;
        } else {
            $nid = array_values($nids)[0];
            $currentNode = Node::load($nid);
            $updateRequired = false;

            foreach (array_keys($holdingParams) as $field) {
                // Get the currently set and expected values
                $cv = $currentNode->get($field)->value;
                $nv = $newNode->get($field)->value;

                // If they do not match
                if ($cv != $nv) {
                    $updateRequired = true;
                    // body field requires special treatment
                    $b = 'body';
                    if ($field == $b) {
                        $currentNode->set(
                            $b,
                            [
                                'summary' => $newNode->get($b)->summary,
                                'value' => $newNode->get($b)->value,
                                'format' => 'full_html'
                            ]
                        );
                    } else {
                        $currentNode->set($field, $nv);
                    }
                }
            }

            // Only update the node if there were changes
            if ($updateRequired) {
                $currentNode->set('changed', time());
                $currentNode->save();
                $this->updated++;
            }
        }

        return $nid;
    }

    /**
     * Deletes event nodes that have been deleted from AtoM
     */
    public function deleteStaleHoldings($nids) {
        // Get all holdings
        $query = \Drupal::entityQuery('node');
        $query->condition('status', 1);
        $query->condition('type', 'holding');
        $dbnids = $query->execute();

        $todelete = array_diff(array_values($dbnids), $nids);

        // Seem to be facing issues with long execution time on SQL query when
        // deleting nodes. This increases it to 5 minutes, but only for this method.
        set_time_limit(300);

        foreach ($todelete as $nid) {
            Node::load($nid)->delete();
            $this->deleted++;
        }
    }
}
