<?php
// ==============================================================================================
// Created by : Daniel
// File description : Lead Generator Manager Pages
// Special - notes : none
// Tables used : tbl_max_google_api_requests
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------

ini_set('memory_limit', '512M');

if (! defined('BASEPATH'))
    exit('No direct script access allowed');

class leadgen_manager extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();

        $this->load->model('leadgen_manager_model');
        $this->load->model('locations_model');
        $this->load->model('user_model');
        $this->load->model('crons_model');
    }

    private $max_leads_per_month = 8000;

    private function getTotalAPIRequestsPerMonth() {
        $this->db->select("max_api_requests");
        $this->db->from("tbl_max_google_api_requests");
        $query = $this->db->get();
        return $query->row()->max_api_requests;
    }

    public function index() {
        $post = $this->input->post();
        if ($post) {
            $assignees = array();
            foreach($post['assign_to'] as $assignee) {
                $assignees[] = $this->user_model->getFullName($assignee);
            }

            $locations = array();

            foreach($post['location'] as $location) {
                $locations[] = $this->locations_model->get_city($location);
            }

            $keywords = array();
            foreach($post['keywords'] as $keyword) {
                $keywords[] = $keyword;
                $keywords_encoded[] = urlencode($keyword);
            }

            $post['assign_to'] = implode(',', $post['assign_to']);
            $post['location'] = implode(',', $post['location']);
            $post['keywords'] = implode(',', $post['keywords']);

            if ($post['add']) {
                $current_id = $this->leadgen_manager_model->getNextScheduleID();
            }
            else {
                $current_id = $post['schedule_id'];
            }
            unset($post['schedule_id']);

            if ($post['run_time'] && $post['run_date']) {
                $post['run_time'] = date('Y-m-d H:i:s', strtotime($post['run_date'].' '.$post['run_time']));
                unset($post['run_date']);
            }

            $post['url'] = site_url('get-google-leads?location='.$post['location']).'&targetLocation='.urlencode($post['cityState']).'&assign_to='.$post['assign_to'].'&miles='.$post['miles'].'&keywords='.implode(',', $keywords_encoded).'&lead_amount='.$post['lead_amount'].'&schedule_id='.$current_id;

            $post['hours'] = $post['hours'] > 0 ? $post['hours'] : '*';

            if ($post['hours'] || $post['min']) {
                $post['cron'] = ($post['hours'] > 0 ? $post['min'] : '*/'.$post['min']).($post['hours'] > 0 ? ' */'.$post['hours'] : ' *').' * * * curl "'.$post['url'].'" >/dev/null 2>&1';
                $post['friendly_desc'] = ucwords('<strong>Frequency:</strong> Every '.$post['hours'].' hours and '.$post['min'].' minutes.'."<br><strong>Locations:</strong> ".implode(', ', $locations)."<br><strong>Target Location:</strong> ".$post['cityState']."<br><strong>Assign to:</strong> ".implode(', ', $assignees)."<br><strong>Keywords:</strong> ".implode(', ', $keywords)."<br><strong>Lead Amount:</strong> ".$post['lead_amount']);
            }
            else {
                $post['cron'] = 'curl "'.$post['url'].'" >/dev/null 2>&1';
                $post['friendly_desc'] = ucwords("<strong>Locations:</strong> ".implode(', ', $locations)."<br><strong>Target Location:</strong> ".$post['cityState']."<br><strong>Assign to:</strong> ".implode(', ', $assignees)."<br><strong>Keywords:</strong> ".implode(', ', $keywords)."<br><strong>Lead Amount:</strong> ".$post['lead_amount']);
            }


            if ($post['edit']) {
                unset($post['edit']);
                if (!$this->leadgen_manager_model->updateSchedule($post)) {
                    $data['error'] = 'Error updating schedule';
                }
            }
            if ($post['add']) {
                unset($post['add']);
                $post['active'] = '1';
                if (!$this->leadgen_manager_model->addSchedule($post)) {
                    $data['error'] = 'Error adding schedule';
                }
            }
            //echo "{$post['cron']} | at ".date('g:i A Y-m-d', strtotime($post['run_time']));
            //exec("{$post['cron']} | at ".date('g:i A Y-m-d', strtotime($post['run_time'])));
            //$r = popen('at '.date('g:i A Y-m-d', strtotime($post['run_time'])),'w');
            //fwrite($r,$post['cron']);
            //pclose($r);
            //file_put_contents("lead_gen_exec.sh", "{$post['cron']} | at ".date('g:i A Y-m-d', strtotime($post['run_time'])));
            //exec("sh lead_gen_exec.sh");
            //exec("rm lead_gen_exec.sh -rf");
            //mail("dharris@fleetcleanusa.com", "Lead Gen Execute Results", print_r($result, true), "");
            $this->crons_model->updateCrons();
        }
        $data['max_requests'] = $this->getTotalAPIRequestsPerMonth();
        $data['schedules'] = $this->leadgen_manager_model->get_all_schedules();
        $this->load->view('leadgen_manager', $data);
    }

    public function add() {
        $data['lead_amt_left'] = $this->max_leads_per_month - $this->leadgen_manager_model->get_total_lead_amts();
        $data['keywords'] = $this->leadgen_manager_model->get_keywords();
        $this->load->view('leadgen_manager_add', $data);
    }

    public function edit($id) {
        $data['lead_amt_left'] = $this->max_leads_per_month - $this->leadgen_manager_model->get_total_lead_amts();
        $data['schedule'] = $this->leadgen_manager_model->get_schedule($id);
        $data['keywords'] = $this->leadgen_manager_model->get_keywords();
        $this->load->view('leadgen_manager_edit', $data);
    }

    public function delete($id) {
        if ($this->leadgen_manager_model->deleteSchedule($id)) {
            $this->crons_model->updateCrons();
        }
        else {
            echo "0";
        }
    }

    public function updateStatus($id, $active)
    {
        $post['id'] = $id;
        $post['active'] = $active;
        if ($this->leadgen_manager_model->updateSchedule($post)) {
            $this->crons_model->updateCrons();
        } else {
            echo "0";
        }
    }

    public function updateMaxAPIRequests()
    {
        $post['max_api_requests'] = $this->input->post('requests');
        if ($this->leadgen_manager_model->updateMaxAPIRequests($post)) {
            echo "1";
        } else {
            echo "0";
        }
    }
}