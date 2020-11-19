<?php
// ==============================================================================================
// Created by : Daniel
// File description : Lead, Prospect, & Customer System Migration Controller
// Special - notes : none
// Tables used : tbl_customers, tbl_users, tbl_customer_fleet_info, tbl_source_master, tbl_unqualified_leads, tbl_locations, tbl_tasks, tbl_customer_address, tbl_customer_contacts, tbl_unqualified_address, tbl_unqualified_contacts, tbl_unqualified_leads_history
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------
ini_set('memory_limit', '512M');

if (! defined('BASEPATH'))
    exit('No direct script access allowed');

class lpc_migration_tool extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        //ini_set('display_errors',1);
        //error_reporting(E_ALL|E_STRICT);

        $this->load->model('locations_model');
        $this->load->model('user_model');
        $this->load->model('unqualifiedleads_model');
        $this->load->model('customers_model');
    }

    public function index() {
        $data['userType'] = $this->customsession->getSessionVar('login_user_type');
        $this->load->view('lpc_migration_tool', $data);
    }

    private function str_replace_first($from, $to, $content)
    {
        $from = '/'.preg_quote($from, '/').'/';

        return preg_replace($from, $to, $content, 1);
    }

    public function update() {
        $data = $this->input->post();
        if ($data) {
            foreach ($data as $k => $d) {
                if ($d === "true") {
                    $data[$k] = true;
                }
                elseif ($d === "false") {
                    $data[$k] = false;
                }
                elseif ($d === "on") {
                    $data[$k] = "true";
                }
                elseif ($d === "off") {
                    $data[$k] = "false";
                }
            }

            //die(print_r($data, true));

            $outData = array();


            try {
                $this->db->save_queries = true;
                $tagsAutomatic = $data['status'];
                $locationId = $data['location'];
                $assign_from = $data['assign_from'];
                $from_date = $data['from_date'] ? date("Y-m-d", strtotime($data['from_date'])) : null;
                $to_date = $data['to_date'] ? date("Y-m-d", strtotime($data['to_date']." +1 day")) : null;
                $number_units = $data['units'];
                $is_google = $data['google_leads'];
                $sales_only = $data['sales_assigned'];
                $keywords = $data['keywords'];
                $search_keyword_filter = $data['keywords_type'];

                $date_filter_type = $data['date_type'];
                $assign_to = $data['assign_to'];
                $source_selector = $data['source'];
                $amount = $data['amount'];
                $selected_customers = $data['selected_customers'];
                $selected_leads = $data['selected_leads'];

                $loginUserId = $this->customsession->getSessionVar('login_user_id');

                // To get the user details like "Access Location" here.
                $userDetails = getUserDetails($loginUserId);
                $userType = $userDetails[0]['in_user_type'];
                $userFranchiseId = $userDetails[0]['in_franchise_id'];
                $locationIdsOfUser = $userDetails[0]['st_location_access'];

                $this->output->enable_profiler(TRUE);

                $query1 = '';
                $query2 = '';

                $keywordsSql = addslashes(trim($keywords));

                $phone = strstr($keywordsSql, ')') || strstr($keywordsSql, '(') || strstr($keywordsSql, '-') ? preg_replace('/\D+/', '', str_replace(' ', '', $keywordsSql)) : str_replace(' ', '', $keywordsSql);

                if (strstr($tagsAutomatic, 'customer') || strstr($tagsAutomatic, 'prospect') || !$tagsAutomatic) {
                    $this->db->_protect_identifiers=false;

                    $this->db->select("
						   C.in_customer_id,
                           C.in_location_id,
						   C.in_customer_id__parent,
						   C.st_company_name,
						   C.st_name,
						   C.st_email,
						   C.st_phone,
	    				   C.st_fax,
	    				   C.st_website,
						   C.flg_is_active,
						   C.in_added_by,
						   C.flg_is_sign,
                           C.st_tags,
                           C.dt_acquired,
                           L.st_city AS location_city,
                           L.st_state_area AS location_state_area,
                           L.st_zipcode AS location_zipcode,
                            IFNULL(IFNULL(IFNULL((SELECT dt_created FROM tbl_customer_history b WHERE b.in_customer_id = C.in_customer_id ORDER BY b.dt_created DESC LIMIT 1),(SELECT created_date FROM customer_history b WHERE b.customer_id = C.in_customer_id ORDER BY b.created_date DESC LIMIT 1)),(SELECT dt_modified FROM tbl_tasks b WHERE b.in_customer_id = C.in_customer_id ORDER BY b.dt_modified DESC LIMIT 1)), IF(C.dt_modified != '0000-00-00 00:00:00', C.dt_modified, C.dt_created)) AS last_updated,
                           IFNULL((SELECT sum(DISTINCT units) FROM quote_service_address_line_item where quote_service_address_id IN (select id from quote_service_address where quote_id IN (select id from quote where customer_id = C.in_customer_id))),F.total_vehical) AS number_units,
                           CONCAT(U.st_first_name, ' ', U.st_last_name) as assigned_to,
                           GROUP_CONCAT(CONCAT_WS('|',
                                C.st_company_name,
                                C.st_name,
                                C.st_email,
                                C.st_phone
                           )) AS searchable,
                           CONCAT(C.in_location_id, ',', (SELECT GROUP_CONCAT(DISTINCT in_location_id SEPARATOR ',') FROM tbl_customer_address uqa WHERE uqa.in_customer_id = C.in_customer_id)) AS SA_Locations,
                           false AS is_lead

						   ", false);
                    $this->db->from('tbl_customers C');
                    $this->db->join('tbl_locations L', 'L.in_location_id = C.in_location_id','LEFT');
                    $this->db->join('tbl_users U', 'U.in_user_id = C.assign_to','LEFT');
                    $this->db->join('tbl_customer_fleet_info F', 'F.in_customer_id = C.in_customer_id','LEFT');
                    $this->db->join('tbl_source_master S', 'S.in_source_id = C.in_source_id', 'left');

                    if(trim($keywords) != '') {

                        switch($search_keyword_filter) {
                            case 0:
                            case null:
                            case '':
                                if(is_numeric(trim($keywords)) || is_numeric($phone)) {
                                    if (!$phone) {
                                        $phone = $keywordsSql;
                                    }
                                    $this->db->where("(C.st_customer_zip LIKE '%{$keywordsSql}%' OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address ca2 WHERE ca2.st_service_zip LIKE '%{$keywordsSql}%') OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts cc2 WHERE cc2.st_zip LIKE '%{$keywordsSql}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_phone_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%') OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_phone, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR L.st_zipcode LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                } else {
                                    $this->db->where("(REPLACE(REPLACE(REPLACE(REPLACE(C.st_website, 'https://', ''), 'http://', ''), 'www.', ''), '/', '') LIKE '%{$keywordsSql}%' OR C.st_email LIKE '%{$keywordsSql}%' OR C.st_name LIKE '%{$keywordsSql}%' OR C.st_company_name LIKE '%{$keywordsSql}%' OR C.st_customer_zip LIKE '%{$keywordsSql}%' OR C.st_phone LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address ca2 WHERE ca2.st_service_address_name LIKE '%{$keywordsSql}%' OR ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%')) OR L.st_zipcode LIKE '%{$keywordsSql}%' OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts cc2 WHERE cc2.st_name = '{$keywordsSql}' OR cc2.st_first_name = '{$keywordsSql}' OR cc2.st_last_name = '{$keywordsSql}'))", NULL, FALSE);
                                }
                                break;
                            case 1:
                                $this->db->where("(C.st_company_name LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                break;
                            case 2:
                                $this->db->where("(C.st_customer_street1 LIKE '%{$keywordsSql}%' OR C.st_customer_street2 LIKE '%{$keywordsSql}%' OR C.st_customer_city LIKE '%{$keywordsSql}%' OR C.st_customer_state LIKE '%{$keywordsSql}%' OR C.st_customer_zip LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address ca2 WHERE ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%')) OR L.st_city LIKE '%{$keywordsSql}%' OR L.st_state_area LIKE '%{$keywordsSql}%' OR L.st_zipcode LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                break;
                            case 3:
                                $this->db->where("(C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address ca2 WHERE ca2.st_service_address_name LIKE '%{$keywordsSql}%' OR ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%'))", NULL, FALSE);
                                break;
                            case 4:
                                $this->db->where("(C.st_name = '{$keywordsSql}' OR C.st_first_name = '{$keywordsSql}' OR C.st_last_name = '{$keywordsSql}' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts ca2 WHERE ca2.st_name = '{$keywordsSql}' OR ca2.st_first_name = '{$keywordsSql}' OR ca2.st_last_name = '{$keywordsSql}')))", NULL, FALSE);
                                break;
                            case 5:
                                $this->db->where("(C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts cc2 WHERE REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_phone_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%') OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_phone, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%')", NULL, FALSE);
                                break;
                            case 6:
                                $this->db->where("(C.st_email LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts ca2 WHERE ca2.st_email LIKE '%{$keywordsSql}%')))", NULL, FALSE);
                                break;
                            case 7:
                                $this->db->where("(REPLACE(REPLACE(REPLACE(REPLACE(C.st_website, 'https://', ''), 'http://', ''), 'www.', ''), '/', '') LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                break;
                        }
                    }

                    if ($tagsAutomatic) {
                        $this->db->where("C.st_tags LIKE '%{$tagsAutomatic}%'", NULL, FALSE);
                    }

                    if($assign_from != ''){
                        $this->db->where("C.assign_to = '$assign_from'");
                    }

                    if($selected_customers || $selected_leads){
                        $this->db->where("FIND_IN_SET(C.in_customer_id, '$selected_customers')");
                    }

                    if($sales_only == 'true'){
                        $this->db->where("C.assign_to IN (SELECT in_user_id FROM tbl_users WHERE is_sales_agent = 1)");
                    }

                    if ($date_filter_type === "1" || $date_filter_type == NULL || $date_filter_type == '') {
                        if ($from_date) {
                            $this->db->having("last_updated >= '$from_date'");
                        }
                        if ($to_date) {
                            $this->db->having("last_updated <= '$to_date'");
                        }
                    }
                    else {
                        if (!strstr($tagsAutomatic, 'quote_sent_outstanding')) {
                            if ($from_date) {
                                $this->db->having("C.dt_acquired >= '$from_date'");
                            }
                            if ($to_date) {
                                $this->db->having("C.dt_acquired <= '$to_date'");
                            }
                        }
                        else {
                            if ($assign_from) {
                                $this->db->having("C.in_customer_id IN (SELECT DISTINCT customer_id FROM quote WHERE added_by_user_id = '$assign_from' AND id IN (SELECT quote_id FROM quote_service_address WHERE created_date >= '$from_date' AND created_date <= '$to_date'))");
                            }
                            else {
                                $this->db->having("C.in_customer_id IN (SELECT DISTINCT customer_id FROM quote WHERE id IN (SELECT quote_id FROM quote_service_address WHERE created_date >= '$from_date' AND created_date <= '$to_date'))");
                            }

                        }

                    }

                    if ((int)$number_units > 0) {
                        $this->db->having("number_units >= $number_units");
                    }

                    if ($source_selector !== '0' && $source_selector != NULL && $source_selector != '') {
                        $this->db->where("C.in_source_id", $source_selector);
                    }

                    if(!empty($locationIdsOfUser) && $userType != 9)
                    {
                        $this->db->having("FIND_IN_SET(C.in_location_id,'$locationIdsOfUser') OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_address ca2 WHERE FIND_IN_SET(ca2.in_location_id,'$locationIdsOfUser'))");
                    }

                    if($locationId != ''){
                        $this->db->having("(find_in_set('$locationId', SA_Locations) > 0 OR C.in_location_id = '$locationId')");
                    }
                    else {
                        $this->db->having("(NOT find_in_set('83', SA_Locations) OR C.in_location_id != '83')");
                    }


                    $this->db->where("C.flg_is_active = '0'");
                    $this->db->group_by("C.in_customer_id");

                    $this->db->limit('0');

                    $query1 = $this->db->get();
                    if ($this->db->_error_message()) {
                        echo $this->db->_error_message(); die;
                    }
                    $query1 =  $this->db->last_query();
                    log_message('query 1:',$query1);
                }


                if (strstr($tagsAutomatic, 'lead') || !$tagsAutomatic) {
                    $this->db->_protect_identifiers=false;
                    $this->db->select("
						   C.in_customer_id,
                           C.in_location_id,
						   NULL AS in_customer_id__parent,
						   C.st_company_name,
						   C.st_name,
						   C.st_email,
						   C.st_phone,
	    				   C.st_fax,
	    				   C.st_website,
						   C.flg_is_active,
						   C.in_added_by,
						   NULL AS flg_is_sign,
                           C.st_tags,
                           C.dt_acquired,
                           L.st_city AS location_city,
                           L.st_state_area AS location_state_area,
                           L.st_zipcode AS location_zipcode,
                           IFNULL((SELECT dt_created FROM tbl_unqualified_leads_history b WHERE b.unqualified_leads_id = C.in_customer_id ORDER BY b.dt_created DESC LIMIT 1), IF(C.dt_modified != '0000-00-00 00:00:00', C.dt_modified, C.dt_created)) AS last_updated,
                           C.number_units,
                           CONCAT(U.st_first_name, ' ', U.st_last_name) as assigned_to,
                           GROUP_CONCAT(CONCAT_WS('|',
                                C.st_company_name,
                                C.st_name,
                                C.st_email,
                                C.st_phone
                                )) AS searchable,
                           CONCAT(C.in_location_id, ',', (SELECT GROUP_CONCAT(DISTINCT in_location_id SEPARATOR ',') FROM tbl_unqualified_address uqa WHERE uqa.in_customer_id = C.in_customer_id)) AS SA_Locations,
                           true AS is_lead

						   ", false);
                    $this->db->from('tbl_unqualified_leads C');
                    $this->db->join('tbl_locations L', 'L.in_location_id = C.in_location_id','LEFT');
                    $this->db->join('tbl_users U', 'U.in_user_id = C.assign_to','LEFT');
                    $this->db->join('tbl_source_master S', 'S.in_source_id = C.in_source_id', 'left');

                    if(trim($keywords) != '') {
                        switch($search_keyword_filter) {
                            case 0:
                            case null:
                            case '':
                                if(is_numeric(trim($keywords)) || is_numeric($phone)) {
                                    if (!$phone) {
                                        $phone = $keywordsSql;
                                    }
                                    $this->db->where("(C.st_customer_zip LIKE '%{$keywordsSql}%' OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE ca2.st_service_zip LIKE '%{$keywordsSql}%' OR REPLACE(REPLACE(REPLACE(REPLACE(ca2.st_phone, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%') OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_contacts cc2 WHERE cc2.st_zip LIKE '%{$keywordsSql}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_phone_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%') OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_phone, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR L.st_zipcode LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                } else {
                                    $this->db->where("(REPLACE(REPLACE(REPLACE(REPLACE(C.st_website, 'https://', ''), 'http://', ''), 'www.', ''), '/', '') LIKE '%{$keywordsSql}%' OR C.st_email LIKE '%{$keywordsSql}%' OR C.st_name LIKE '%{$keywordsSql}%' OR C.st_company_name LIKE '%{$keywordsSql}%' OR C.st_customer_zip LIKE '%{$keywordsSql}%' OR C.st_phone LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE ca2.st_service_address_name LIKE '%{$keywordsSql}%' OR ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%')) OR L.st_zipcode LIKE '%{$keywordsSql}%' OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_contacts cc2 WHERE cc2.st_name = '{$keywordsSql}' OR cc2.st_first_name = '{$keywordsSql}' OR cc2.st_last_name = '{$keywordsSql}'))", NULL, FALSE);
                                }
                                break;
                            case 1:
                                $this->db->where("(C.st_company_name LIKE '%{$keywordsSql}%' OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE ca2.st_service_address_name LIKE '%{$keywordsSql}%'))", NULL, FALSE);
                                break;
                            case 2:
                                $this->db->where("(C.st_customer_street1 LIKE '%{$keywordsSql}%' OR C.st_customer_street2 LIKE '%{$keywordsSql}%' OR C.st_customer_city LIKE '%{$keywordsSql}%' OR C.st_customer_state LIKE '%{$keywordsSql}%' OR C.st_customer_zip LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%')) OR L.st_city LIKE '%{$keywordsSql}%' OR L.st_state_area LIKE '%{$keywordsSql}%' OR L.st_zipcode LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                break;
                            case 3:
                                $this->db->where("(C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE ca2.st_service_address_name LIKE '%{$keywordsSql}%' OR ca2.st_service_street1 LIKE '%{$keywordsSql}%' OR ca2.st_service_street2 LIKE '%{$keywordsSql}%' OR ca2.st_service_city LIKE '%{$keywordsSql}%' OR ca2.st_service_zip LIKE '%{$keywordsSql}%' OR ca2.st_service_state LIKE '%{$keywordsSql}%'))", NULL, FALSE);
                                break;
                            case 4:
                                $this->db->where("(C.st_name = '{$keywordsSql}' OR C.st_first_name = '{$keywordsSql}' OR C.st_last_name = '{$keywordsSql}' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_contacts ca2 WHERE ca2.st_name = '{$keywordsSql}' OR ca2.st_first_name = '{$keywordsSql}' OR ca2.st_last_name = '{$keywordsSql}')))", NULL, FALSE);
                                break;
                            case 5:
                                $this->db->where("(C.in_customer_id IN (SELECT in_customer_id FROM tbl_customer_contacts cc2 WHERE REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_phone_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(cc2.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%') OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_phone, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_mobile_number, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%' OR REPLACE(REPLACE(REPLACE(REPLACE(C.st_fax, '-', ''), ')', ''), '(', ''), ' ', '') LIKE '%{$phone}%')", NULL, FALSE);
                                break;
                            case 6:
                                $this->db->where("(C.st_email LIKE '%{$keywordsSql}%' OR (C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_contacts ca2 WHERE ca2.st_email LIKE '%{$keywordsSql}%')))", NULL, FALSE);
                                break;
                            case 7:
                                $this->db->where("(REPLACE(REPLACE(REPLACE(REPLACE(C.st_website, 'https://', ''), 'http://', ''), 'www.', ''), '/', '') LIKE '%{$keywordsSql}%')", NULL, FALSE);
                                break;
                        }
                    }

                    if ($tagsAutomatic) {
                        if ($tagsAutomatic == "state:lead(unqualified)") {
                            $this->db->where("(C.st_tags LIKE '%{$tagsAutomatic}%' OR C.st_tags LIKE '%state:lead(unqualified_%')", NULL, FALSE);}
                        else {
                            $this->db->where("C.st_tags LIKE '%$tagsAutomatic%'", NULL, FALSE);
                        }
                    }

                    if($selected_leads || $selected_customers){
                        $this->db->where("FIND_IN_SET(C.in_customer_id, '$selected_leads')");
                    }

                    if($assign_from != ''){
                        $this->db->where("C.assign_to = '$assign_from'");
                    }

                    if($sales_only == 'true'){
                        $this->db->where("C.assign_to IN (SELECT in_user_id FROM tbl_users WHERE is_sales_agent = 1)");
                    }


                    if ($date_filter_type === "1" || $date_filter_type == NULL || $date_filter_type == '') {
                        if ($from_date) {
                            $this->db->having("last_updated >= '$from_date'");
                        }
                        if ($to_date) {
                            $this->db->having("last_updated <= '$to_date'");
                        }
                    }
                    else {
                        if ($from_date) {
                            $this->db->having("C.dt_acquired >= '$from_date'");
                        }
                        if ($to_date) {
                            $this->db->having("C.dt_acquired <= '$to_date'");
                        }
                    }



                    if ($is_google == 'true') {
                        $this->db->where("C.from_google = '1'");
                    }



                    if ((int)$number_units > 0) {
                        $this->db->where("number_units >= $number_units");
                    }

                    if ($source_selector !== '0' && $source_selector != NULL && $source_selector != '') {
                        $this->db->where("C.in_source_id", $source_selector);
                    }

                    if(!empty($locationIdsOfUser) && $userType != 9)
                    {
                        $this->db->having("FIND_IN_SET(C.in_location_id,'$locationIdsOfUser') OR C.in_customer_id IN (SELECT in_customer_id FROM tbl_unqualified_address ca2 WHERE FIND_IN_SET(ca2.in_location_id,'$locationIdsOfUser'))");
                    }

                    if($locationId != ''){
                        $this->db->having("(find_in_set('$locationId', SA_Locations) > 0 OR C.in_location_id = '$locationId')");
                    }
                    else {
                        $this->db->having("(NOT find_in_set('83', SA_Locations) OR C.in_location_id != '83')");
                    }

                    if ($tagsAutomatic != 'state:lead(dead)') {
                        $this->db->where("C.flg_is_active = '0'");
                    }

                    $this->db->where("C.status != 'customer'");

                    $this->db->group_by("C.in_customer_id");

                    $this->db->limit('0');

                    $query2 = $this->db->get();
                    if ($this->db->_error_message()) {
                        echo $this->db->_error_message(); die;
                    }
                    $query2 =  $this->db->last_query();
                }

                $query1 = str_replace('LIMIT 0', '', $query1);
                $query2 = str_replace('LIMIT 0', '', $query2);

                if ($query1) {
                    $this->db->set("st_account_manager", "(SELECT CONCAT(st_first_name, ' ', st_last_name) FROM tbl_users WHERE in_user_id = '$assign_to')", false);
                    $this->db->where("in_customer_id IN (SELECT in_customer_id FROM ($query1) A)");
                    $this->db->update('tbl_customers');

                    $this->db->set('in_user_id', $assign_to);
                    $this->db->where("in_customer_id IN (SELECT in_customer_id FROM ($query1) A)");
                    $this->db->update('tbl_tasks');

                    $this->db->set('assign_to', $assign_to);
                    $this->db->set('in_added_by', $assign_to);
                    $this->db->set('dt_acquired', date('Y-m-d', strtotime('now')));
                    $this->db->where("in_customer_id IN (SELECT in_customer_id FROM ($query1) A)");
                    $this->db->update("tbl_customers");

                    log_message('error', 'query1: '.$this->db->last_query());
                }
                if ($query2) {
                    $this->db->set('assign_to', $assign_to);
                    $this->db->set('in_added_by', $assign_to);
                    $this->db->set('dt_acquired', date('Y-m-d', strtotime('now')));
                    $this->db->where("in_customer_id IN (SELECT in_customer_id FROM ($query2) B)");
                    $this->db->update("tbl_unqualified_leads");

                    log_message('error', 'query2: '.$this->db->last_query());
                }


                if ($this->db->_error_message()) {
                    echo $this->db->_error_message(); die;
                }
                else {
                    echo 'yes';
                }
            }
            catch (Exception $e) {
                die(print_r($e, true));
            }
        }
    }

    public function updateSalesTracker() {
        $assign_to = $this->input->post('assign_to');
        $customer_ids = $this->input->post('customer_ids');
        $lead_ids = $this->input->post('lead_ids');
        $task_ids = $this->input->post('task_ids');

        if ($customer_ids) {
            $this->db->set('assign_to', $assign_to);
            $this->db->set('in_added_by', $assign_to);
            $this->db->set('dt_acquired', date('Y-m-d', strtotime('now')));
            $this->db->where("in_customer_id IN ($customer_ids)");
            $this->db->update('tbl_customers');

            $this->db->set("st_account_manager", "(SELECT CONCAT(st_first_name, ' ', st_last_name) FROM tbl_users WHERE in_user_id = '$assign_to')", false);
            $this->db->where("in_customer_id IN ($customer_ids)");
            $this->db->update('tbl_customers');
        }

        if ($lead_ids) {
            $this->db->set('assign_to', $assign_to);
            $this->db->set('in_added_by', $assign_to);
            $this->db->set('dt_acquired', date('Y-m-d', strtotime('now')));
            $this->db->where("in_customer_id IN ($lead_ids)");
            $this->db->update('tbl_unqualified_leads');
        }

        if ($task_ids) {
            $this->db->set('in_user_id', $assign_to);
            $this->db->where("in_task_id IN ($task_ids)");
            $this->db->update('tbl_tasks');
        }

        if ($this->db->_error_message()) {
            echo $this->db->_error_message(); die;
        }
        else {
            echo 'yes';
        }
    }
}