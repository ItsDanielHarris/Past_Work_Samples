<?php
// ==============================================================================================
// Created by : Daniel
// File description : Scheduler Restful API Importer tool
// Special - notes : none
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------
class Scheduler extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->common_function->check_logged_in($this);
        $this->load->model('customers_model');
        $this->load->model('quote_model');
        $this->load->model('scheduler_model');
    }

    private $token = '';

    public function index() {
        $this->load->view('scheduler');
    }

    private function callAPI($method, $url, $data, $headers){
        $curl = curl_init();
        switch ($method){
            case "POST":
                curl_setopt($curl, CURLOPT_POST, 1);
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            case "PUT":
                curl_setopt($curl, CURLOPT_CUSTOMREQUEST, "PUT");
                if ($data)
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                break;
            default:
                if ($data)
                    $url = sprintf("%s?%s", $url, http_build_query($data));
        }
        // OPTIONS:
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        // EXECUTE:
        curl_setopt($curl, CURLOPT_VERBOSE, true);
        $verbose = fopen('php://temp', 'w+');

        $result = curl_exec($curl);
        if ($result === FALSE) {
            printf("cUrl error (#%d): %s<br>\n", curl_errno($curl),
                htmlspecialchars(curl_error($curl)));
        }

        rewind($verbose);
        $verboseLog = stream_get_contents($verbose);

        //echo "Verbose information:\n<pre>", htmlspecialchars($verboseLog), "</pre>\n";
        if(!$result){die("Connection Failure");}
        curl_close($curl);
        return $result;
    }

    public function get_customer_from_quote_id() {
        $quote_id = $this->input->post('quote_id');
        $customer_id = $this->quote_model->getCustomerIdfromQuoteId($quote_id);
        $customer = $this->customers_model->getCustomerById($customer_id);

        if ($customer['st_company_name']) {

            // POST Customer
            $data =  (object) array(
                "crmCustomerId"=>$customer['in_customer_id'],
                "name"=>$customer['st_company_name'],
                "street1"=>$customer['st_customer_street1'],
                "street2"=>$customer['st_customer_street2'],
                "city"=>$customer['st_customer_city'],
                "state"=>$customer['st_customer_state'],
                "zipCode"=>$customer['st_customer_zip']
            );

            $headers = array(
                'Content-Type: application/json',
                'Authorization: Bearer '.$this->token,
                'Host: example.com'
            );

            $response = $this->callAPI("POST", "https://example.com/customers", json_encode($data), $headers);
            $response = json_decode($response);
            $response = $response->data;

            if ($response) {
                $scheduler_id = (string) $response->id;
                // Update Scheduler ID in MyFC

                if ($this->update_customer_scheduler_id($customer['in_customer_id'], $scheduler_id)) {
                    $quote = $this->get_quote($quote_id);
                    $quote_units = $this->scheduler_model->get_quote_units($quote_id);
                    foreach ($quote_units as $key => $unit) {
                        $quote_units[$key] = (object) $unit;
                    }

                    // POST Quote
                    $data =  (object) array(
                        'customerId'=>$scheduler_id,
                        'branchCode'=>$customer['in_franchise_id'],
                        'street1'=>$quote['street1'],
                        'street2'=>$quote['street2'],
                        'city'=>$quote['city'],
                        'state'=>$quote['state'],
                        'zipCode'=>$quote['zipCode'],
                        'crmQuoteId'=>$quote_id,
                        'units'=>$quote_units,
                    );
                    $response_quote = $this->callAPI("POST", "https://example.com/quotes", json_encode($data), $headers);
                    if ($response_quote) {
                        $response_quote = json_decode($response_quote);
                        $response_quote = $response_quote->data;
                        $this->update_quote_scheduler_id($quote_id, $response_quote->id);
                    }
                    else {
                        echo "Error updating quote";
                    }
                }
                else {
                    echo "Error updating customer scheduler id.";
                }
            }
            else {
                echo "Error posting customer to scheduler.";
            }
        }
        else {
            echo "Error getting customer from quote id.";
        }
    }

    private function update_customer_scheduler_id($customer_id, $scheduler_id) {
        return $this->scheduler_model->update_customer_scheduler_id($customer_id, $scheduler_id);
    }
    private function update_quote_scheduler_id($quote_id, $scheduler_id) {
        return $this->scheduler_model->update_quote_scheduler_id($quote_id, $scheduler_id);
    }

    private function get_quote($quote_id) {
        $quote_addr = $this->scheduler_model->get_quote_address($quote_id);
        $quote['street1'] = $quote_addr['street1'];
        $quote['street2'] = $quote_addr['street2'];
        $quote['city'] = $quote_addr['city'];
        $quote['state'] = $quote_addr['state'];
        $quote['zipCode'] = $quote_addr['zipCode'];
        return $quote;
    }
}