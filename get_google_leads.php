<?php
// ==============================================================================================
// Created by : Daniel
// File description : Lead Generator Google Maps Lead Scraper Controller
// Special - notes : none
// Tables used : tbl_customers, tbl_customer_address, tbl_unqualified_leads, tbl_unqualified_contacts, tbl_customer_contacts, tbl_leadgen_schedule, merlin_results, leadgen_results, tbl_max_google_api_requests, tbl_lead_gen_jobs, 
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------
ini_set('memory_limit', '512M');

if (! defined('BASEPATH'))
    exit('No direct script access allowed');

class get_google_leads extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('locations_model');
        $this->load->model('user_model');
    }

    private function customer_exists($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_customers");
        $this->db->where("st_company_name LIKE '%".addslashes($lead['st_company_name'])."%'");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function customer_address_exists ($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_customer_address");
        $this->db->where("st_service_address_name LIKE '%".addslashes($lead['st_company_name'])."%' AND st_service_street1 LIKE '%".addslashes($lead['st_customer_street1'])."%' AND st_service_city = '".addslashes($lead['st_customer_city'])."'");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function lead_exists ($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_unqualified_leads");
        $this->db->where("st_company_name = '".addslashes($lead['st_company_name'])."'");
        $this->db->where("in_location_id = '".$lead['in_location_id']."'");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }



    private function contact_exists ($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_unqualified_contacts");
        $this->db->where("replace(replace(replace(replace(replace(st_phone_number,'+',''),'-',''),'(',''),')',''),' ','') = replace(replace(replace(replace(replace('{$lead['st_phone']}','+',''),'-',''),'(',''),')',''),' ','')");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function customer_contact_exists ($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_customer_contacts");
        $this->db->where("replace(replace(replace(replace(replace(st_phone_number,'+',''),'-',''),'(',''),')',''),' ','') = replace(replace(replace(replace(replace('{$lead['st_phone']}','+',''),'-',''),'(',''),')',''),' ','')");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function address_exists ($lead) {
        $this->db->select("in_customer_id");
        $this->db->from("tbl_unqualified_address");
        $this->db->where("st_service_address_name LIKE '%".addslashes($lead['st_company_name'])."%' AND st_service_street1 LIKE '%".addslashes($lead['st_customer_street1'])."%' AND st_service_city = '".addslashes($lead['st_customer_city'])."'");
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function getScheduleLeadCount($schedule_id) {
        $this->db->select("leads_count");
        $this->db->from("tbl_leadgen_schedule");
        $this->db->where("id", $schedule_id);
        $query = $this->db->get();
        return $query->row()->leads_count;
    }

    private function increaseLeadCount($schedule_id, $amt) {
        $this->db->set('leads_count', "leads_count + $amt", false);
        $this->db->where('id', $schedule_id);
        $this->db->update('tbl_leadgen_schedule');
    }

    private function getTotalRequestsCount() {
        $date = date('Y-m', strtotime('now'));
        $query = $this->db->query("
            SELECT SUM(api_requests) AS total
            FROM (
            (SELECT api_requests FROM merlin_results WHERE DATE_FORMAT(dt_acquired, '%Y-%m') LIKE '$date%')
            UNION ALL
            (SELECT api_requests FROM leadgen_results WHERE DATE_FORMAT(dt_acquired, '%Y-%m') LIKE '$date%')
            ) t
        ");
        $requests = $query->row()->total;

        return $requests;
    }

    private function addHistory($data)
    {
        $this->db->insert('leadgen_results', $data);
    }

    private function getTotalAPIRequestsPerMonth() {
        $this->db->select("max_api_requests");
        $this->db->from("tbl_max_google_api_requests");
        $query = $this->db->get();
        return $query->row()->max_api_requests;
    }

    private function alreadyRan($location, $keyword, $miles) {
        $this->db->select("id");
        $this->db->from("tbl_lead_gen_jobs");
        $this->db->where("location", $location);
        $this->db->where("keyword", $keyword);
        $this->db->where("miles", $miles);
        $query = $this->db->get();
        if ($query->num_rows() > 0) {
            return true;
        }
        else {
            return false;
        }
    }

    private function saveJob($save) {
        $this->db->insert("tbl_lead_gen_jobs", $save);
    }

    private $max_requests_per_month = 0;
    private $total_requests = 0;
    private $lead_count = 0;

    public function index() {
        $max_requests = $this->getTotalAPIRequestsPerMonth();
        $this->max_requests_per_month = $max_requests ? $max_requests : 8000;
        $schedule_id = $this->input->get('schedule_id');
        $lead_amount = $this->input->get('lead_amount');
        $targetLocation = urldecode($this->input->get('targetLocation'));
        $lead_count = $this->getScheduleLeadCount($schedule_id);
        $total_month_requests = $this->getTotalRequestsCount();

        if ($lead_count < $lead_amount && $total_month_requests < $this->max_requests_per_month) {
            ob_start();

            require_once(dirname(__FILE__).'/magic/Google-Places---PHP--master/googlePlaces.php');

            require_once(dirname(__FILE__).'/magic/merlin.php');

            $total_requests = 0;

            $merlin = new Merlin();

            $merlinResults = array();

            $googlePlaces = new googlePlaces($merlin->GOOGLE_API_KEY);

            if ($this->input->get('location')) {
                $locations_get = explode(',', $this->input->get('location'));
                foreach($locations_get as $location) {
                    $location_details = $this->locations_model->getLocationDetailsByLocationID($location);
                    $locations[] = $location_details[0];
                }
                //$locations = $locations[0];
            }
            else {
                $locations = $this->locations_model->getAllActiveLocations();
            }
            //$locations = array_values($locations);

            //die("<pre>".print_r($locations , true)."</pre>");

            $miles = $this->input->get('miles');
            $miles = $miles ? $miles : 40;

            $meters = round($miles / 0.00062137);



            //$location_get = $this->input->get('location') ? $this->locations_model->getLocationDetailsByLocationID($this->input->get('location')) : '';

            //die("<pre>".print_r($location , true)."</pre>");




            if ($this->input->get('keywords')) {
                $keywords_get = explode(',', $this->input->get('keywords'));
                foreach ($keywords_get as $keyword) {
                    $keywords[] = array('keyword' => urldecode($keyword));
                }
            }
            else {
                $keywords = $merlin->SEARCH_TERMS;
            }





            $keyword = '';
            $location = array();
            $save = array();

            foreach ($keywords as $key => $k) {
                if (count($save) < 1) {
                    if ($targetLocation) {
                        if (!$this->alreadyRan($targetLocation, $k['keyword'], $miles)) {
                            $save['keyword'] = $k['keyword'];
                            $keyword = $k['keyword'];
                            $save['location'] = $targetLocation;
                            $save['miles'] = $miles;

                            $location = $locations[0];
                            $location['st_city'] = $location['st_city'] == 'Training' ? 'Melbourne' : $location['st_city'];
                        }
                    }
                    else {
                        foreach ($locations as $loc) {
                            $l = $loc['st_city'].", ".$loc['st_state_area'];
                            if (!$this->alreadyRan($l, $k['keyword'], $miles)) {
                                $save['keyword'] = $k['keyword'];
                                $keyword = $k['keyword'];
                                $save['location'] = $l;
                                $save['miles'] = $miles;

                                $location = $loc;
                                $location['st_city'] = $loc['st_city'] == 'Training' ? 'Melbourne' : $loc['st_city'];
                            }
                        }
                    }
                }
            }

            if (count($save) < 1) {
                die('All location/keyword/radius combinations have been exhausted');
            }
            else {
                $this->saveJob($save);
            }




            if ($targetLocation) {
                $json = json_decode(file_get_contents("https://".$_SERVER["SERVER_NAME"]."/magic/getLatLongFromCity?val=".urlencode($targetLocation)));
            }
            else {
                $json = json_decode(file_get_contents("https://".$_SERVER["SERVER_NAME"]."/magic/getLatLongFromCity?val=".urlencode($location['st_city'].", ".$location['st_state_area'])));
            }

            $latitude = $json->lat;
            $longitude = $json->lng;

            $googlePlaces->setLocation($latitude . ',' . $longitude);
            $googlePlaces->setRadius($meters);


            $googlePlaces->setKeyword($keyword);
            $googlePlaces->setRankBy('distance');

            $placesResults = $googlePlaces->nearbySearch();
            $this->total_requests += 1;
            $placesResults2 = array();
            $placesResults3 = array();

            if (!empty($placesResults['next_page_token'])) {
                sleep(2);
                $placesResults2 = $googlePlaces->repeat($placesResults['next_page_token']);
                $this->total_requests += 1;
            }

            if (!empty($placesResults2['next_page_token'])) {
                sleep(2);
                $placesResults3 = $googlePlaces->repeat($placesResults2['next_page_token']);
                $this->total_requests += 1;
            }

            /*if (!empty($placesResults['next_page_token'])) {
                $placesResults2 = $googlePlaces->repeat($placesResults['next_page_token']);
            }

            if (!empty($placesResults2['next_page_token'])) {
                $placesResults3 = $googlePlaces->repeat($placesResults2['next_page_token']);
            }*/

            $results1 = $merlin->processPlacesResults($placesResults);
            $results2 = $merlin->processPlacesResults($placesResults2);
            $results3 = $merlin->processPlacesResults($placesResults3);


            $merlinResults = array_merge($results1, $results2, $results3);

            print_r(array_column($merlinResults, "result_name"));

            $results = $merlinResults;
            //$data['results'] = $results;

            $leads = array();
            $inserted_count = 0;
            $inserted_addresses_count = 0;
            $dups_count = 0;
            $inserted_contact_count = 0;

            echo "location: ".$location['st_city']."\n";
            echo "target location: ".$targetLocation."\n";
            echo "radius: ".$miles." miles\n";
            echo "keyword: ".$keyword."\n";
            echo "results1: ".count($results1)."\t";
            echo "results2: ".count($results2)."\t";
            echo "results3: ".count($results3)."\t";
            echo "\r\n".count($results)." leads were found using Google Maps API\r\n";

            $this->total_requests += count($results);

            $this->increaseLeadCount($schedule_id, $this->total_requests);

            if ($_GET['assign_to']) {
                $agents = explode(',', $_GET['assign_to']);
            }
            else {
                $agents = array('944', '968');
            }

            foreach ($results as $result) {
                $addr = array();
                foreach ($result['details']['result']["address_components"] as $address) {
                    if (in_array("locality", $address["types"])) {
                        $addr['city'] = $address["long_name"];
                    }
                    if (in_array("postal_code", $address["types"])) {
                        $addr['zip'] = $address["long_name"];
                    }
                    if (in_array("administrative_area_level_1", $address["types"])) {
                        $addr['state'] = $address["long_name"];
                    }
                    if (in_array("street_number", $address["types"])) {
                        $addr['st_num'] = $address["long_name"];
                    }
                    if (in_array("route", $address["types"])) {
                        $addr['route'] = $address["long_name"];
                    }
                    $addr['street'] = $addr['st_num'] . " " . $addr['route'];
                }

                $lead = array(
                    'status' => 'lead',
                    'st_company_name' => $result['details']['result']['name'],
                    'st_phone' => $result['details']['result']['formatted_phone_number'] ? $result['details']['result']['formatted_phone_number'] : '',
                    'st_customer_street1' => $addr['street'],
                    'st_customer_city' => $addr['city'],
                    'st_customer_zip' => $addr['zip'],
                    'st_customer_state' => $addr['state'],
                    'st_website' => $result['details']['result']['website'] ? $result['details']['result']['website'] : '',
                    'dt_acquired' => date("Y-m-d", strtotime('now')),
                    'dt_created' => date("Y-m-d H:i:s", strtotime('now')),
                    'flg_is_active' => '0',
                    'in_location_id' => $location['in_location_id'] == '83' ? '14' : $location['in_location_id'],
                    //'assign_to' => $this->user_model->get_assigned_user_by_location($location['in_location_id']),
                    //'assign_to' => $this->user_model->get_random_sales_agent(),
                    'assign_to' => count($agents) > 1 ? $agents[rand(0 ,count($agents) - 1)] : $agents[0],
                    'assign_date' => date("Y-m-d H:i:s", strtotime('now')),
                    'show_today' => '1',
                    'st_tags' => '{state:lead}|{state:lead(unqualified)}',
                    'latLng' => "{\"lat\":{$result['details']['result']['geometry']['location']['lat']},\"lng\":{$result['details']['result']['geometry']['location']['lng']}}",
                    'from_google' => '1',
                    'place_id' => $result['result_place_id']
                );
                $leads[] = $lead;

                if ($targetLocation) {
                    if (!$this->lead_exists($lead) && !$this->customer_exists($lead) && !$this->customer_address_exists($lead)) {

                        $this->db->insert("tbl_unqualified_leads", $lead);
                        $inserted_count++;

                        $this->db->query("
                        INSERT INTO tbl_unqualified_address
                        (
                            in_customer_id,
                            in_location_id,
                            st_service_address_name,
                            st_service_street1,
                            st_service_city,
                            st_service_zip,
                            st_service_state,
                            flg_is_active,
                            dt_created,
                            dt_modified,
                            latLng,
                            latlng_found,
                            st_phone
                        )
                        
                        VALUES(
                            '{$this->db->insert_id()}',
                            '{$lead['in_location_id']}',
                            '" . addslashes($lead['st_company_name']) . "',
                            '" . addslashes($lead['st_customer_street1']) . "',
                            '" . addslashes($lead['st_customer_city']) . "',
                            '{$lead['st_customer_zip']}',
                            '{$lead['st_customer_state']}',
                            '0',
                            '{$lead['dt_created']}',
                            '{$lead['dt_created']}',
                            '{$lead['latLng']}',
                            '1',
                            '{$lead['st_phone']}'
                        )             
                    ");
                        $inserted_addresses_count++;
                        $this->lead_count += 1;
                    }
                }
                else {
                    if (!$this->lead_exists($lead) && !$this->customer_exists($lead) && !$this->customer_address_exists($lead)) {
                        //if ($this->)
                        $inserted_count++;

                        $this->db->insert("tbl_unqualified_leads", $lead);

                        if (!$this->address_exists($lead) && !$this->customer_address_exists($lead)) {
                            $this->db->query("
                        INSERT INTO tbl_unqualified_address
                        (
                            in_customer_id,
                            in_location_id,
                            st_service_address_name,
                            st_service_street1,
                            st_service_city,
                            st_service_zip,
                            st_service_state,
                            flg_is_active,
                            dt_created,
                            dt_modified,
                            latLng,
                            latlng_found,
                            st_phone
                        )
                        
                        VALUES(
                            '{$this->db->insert_id()}',
                            '{$lead['in_location_id']}',
                            '" . addslashes($lead['st_company_name']) . "',
                            '" . addslashes($lead['st_customer_street1']) . "',
                            '" . addslashes($lead['st_customer_city']) . "',
                            '{$lead['st_customer_zip']}',
                            '{$lead['st_customer_state']}',
                            '0',
                            '{$lead['dt_created']}',
                            '{$lead['dt_created']}',
                            '{$lead['latLng']}',
                            '1',
                            '{$lead['st_phone']}'
                        )             
                    ");
                        }
                        $inserted_addresses_count++;
                        $this->lead_count += 1;
                    }
                    else {
                        $dup_found = true;
                        $lead['in_location_id'] = $lead['in_location_id'] == '83' ? '14' : $lead['in_location_id'];
                        if (!$this->address_exists($lead) && !$this->customer_address_exists($lead)) {
                            $this->db->query("
                        INSERT INTO tbl_unqualified_address
                        (
                            in_customer_id,
                            in_location_id,
                            st_service_address_name,
                            st_service_street1,
                            st_service_city,
                            st_service_zip,
                            st_service_state,
                            flg_is_active,
                            dt_created,
                            dt_modified,
                            latLng,
                            latlng_found,
                            st_phone
                        )
                        
                        VALUES(
                            IFNULL((SELECT MIN(in_customer_id) FROM tbl_unqualified_leads d WHERE d.st_company_name LIKE '%".addslashes($lead['st_company_name'])."%'), ''),
                            '{$lead['in_location_id']}',
                            '".addslashes($lead['st_company_name'])."',
                            '".addslashes($lead['st_customer_street1'])."',
                            '".addslashes($lead['st_customer_city'])."',
                            '{$lead['st_customer_zip']}',
                            '{$lead['st_customer_state']}',
                            '0',
                            '{$lead['dt_created']}',
                            '{$lead['dt_created']}',
                            '{$lead['latLng']}',
                            '1',
                            '{$lead['st_phone']}'
                        )             
                    ");
                            $inserted_addresses_count++;
                            $dup_found = false;
                        }
                        if (!$this->contact_exists($lead) && !$this->customer_contact_exists($lead)) {
                            $this->db->query("
                        INSERT INTO tbl_unqualified_contacts
                        (
                            in_customer_id,
                            in_location_id,
                            st_phone_number,
                            st_street1,
                            st_city,
                            st_zip,
                            st_state,
                            flg_is_active,
                            dt_created,
                            dt_modified,
                            in_customer_address_id
                        )
                                                
                        VALUES(
                            IFNULL((SELECT MIN(in_customer_id) FROM tbl_unqualified_leads d WHERE d.st_company_name LIKE '%".addslashes($lead['st_company_name'])."%'), ''),
                            '{$lead['in_location_id']}',
                            '{$lead['st_phone']}',
                            '".addslashes($lead['st_customer_street1'])."',
                            '".addslashes($lead['st_customer_city'])."',
                            '{$lead['st_customer_zip']}',
                            '{$lead['st_customer_state']}',
                            '0',
                            '{$lead['dt_created']}',
                            '{$lead['dt_created']}',
                            (SELECT in_customer_address_id FROM tbl_unqualified_address e WHERE e.st_service_street1 LIKE '%".addslashes($lead['st_customer_street1'])."%' AND in_customer_id = IFNULL((SELECT MIN(in_customer_id) FROM tbl_unqualified_leads f WHERE f.st_company_name LIKE '%".addslashes($lead['st_company_name'])."%'), '') LIMIT 1)
                        )             
                    ");
                            $inserted_contact_count++;
                            $dup_found = false;
                        }

                        if ($dup_found) {
                            $dups_count++;
                        }
                        else {
                            $this->lead_count += 1;
                        }

                    }
                }


            }

            $history = array('location' => $location['st_city'], 'miles' => $miles, 'keyword' => $keyword, 'api_requests' => $this->total_requests, 'leads' => $this->lead_count);
            $this->addHistory($history);

            $this->db->_protect_identifiers=false;
            $this->db->select("SUM(c) AS dup_addresses");
            $this->db->from("(SELECT COUNT(*) as c FROM tbl_unqualified_address group by st_service_street1, in_customer_id HAVING c > 1 ORDER BY c DESC) a");
            $query = $this->db->get();
            $dup_addresses = (int) $query->row()->dup_addresses;

            $this->db->select("SUM(c) AS dup_contacts");
            $this->db->from("(SELECT COUNT(*) as c FROM tbl_unqualified_contacts group by replace(replace(replace(replace(replace(st_phone_number,'+',''),'-',''),'(',''),')',''),' ','') HAVING c > 1 ORDER BY c DESC) a");
            $query = $this->db->get();
            $dup_contacts = (int) $query->row()->dup_contacts;

            echo $inserted_count." new leads were inserted into the db\r\n";
            echo $inserted_addresses_count." new addresses were inserted into the db\r\n";
            echo $inserted_contact_count." new contacts were inserted into the db\r\n";
            echo $dups_count." duplicate leads were found\r\n";

            $lead_count = $this->getScheduleLeadCount($schedule_id);

            echo "\r\nLead Amount: ".$lead_amount."\r\n";
            echo "Lead Count: ".$lead_count."\r\n\r\n";

            echo "There are still $dup_addresses duplicate addresses in the db.\r\n";
            echo "There are still $dup_contacts duplicate contacts in the db.\r\n";

            $output = ob_get_clean();
            echo $output;
            if ($this->input->get('manual') == 1) {
                if (count($save) >= 1) {
                    header("Refresh:0");
                }
            }
        }
        else {
            $output = "Lead count: ".$lead_count;
            $output .= "\nTotal Month Requests: ".$total_month_requests;
            $output .= "\nMax Requests per Month: ".$this->max_requests_per_month;
            mail("", "Lead Generator Failed", "Lead generator did not run.\n\n$output", "");
            //file_put_contents("lead_gen.log", $output);
        }


        //$this->load->view('get_google_leads', $data);
    }
}