<?php
if (! defined('BASEPATH'))
    exit('No direct script access allowed');
// ==============================================================================================
// Created by : Daniel
// File description : Invoice Credit Card Processor Page Controller
// Special - notes : none
// Tables used : 
// Stored procedures : none
// Triggers used : none
// ----------------------------------------------------------------------------------------------
class Invoice_CC_Pay extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        $this->load->model('invoice_model');
        $this->load->library('qsapi');

        require $this->config->item('base_path') . 'plugins/quickbooks_config.php';

        // Set up the IPP instance
        $this->IPP = $this->IPP1 = new QuickBooks_IPP($dsn, $encryption_key);
        // Get our OAuth credentials from the database
        $creds = $IntuitAnywhere->load($the_tenant);
        // Tell the framework to load some data from the OAuth store
        $this->IPP->authMode(QuickBooks_IPP::AUTHMODE_OAUTHV2, $creds);
        log_message('debug', print_r($creds, true));
        // This is our current realm
        $this->realm = $creds['qb_realm'];
        $this->quickbooks_is_connected = $quickbooks_is_connected;
        if (!$quickbooks_is_connected) {
            $this->session->set_flashdata('qberror', '<p>Quickbooks is not connected, To connect <a href="' . base_url() . 'qbconnect?do=' . $_SERVER['REQUEST_URI'] . '">click here</a></p>');
        }
    }

    public function index($invoice_id = null)
    {
        if ($invoice_id > 0) {
            try {
                $paid = $this->input->get('paid');
                $load_invoice = $this->load_invoice($invoice_id, $paid);
                $data['invoice_html'] = $load_invoice['html'];
                $data['invoice_id'] = $invoice_id;
                $data['customer_id'] = $this->invoice_model->getCustomerIDFromInvoice($invoice_id);
                $data['email'] = $load_invoice['billing_email'];
                if ($data['invoice_html']) {
                    $this->load->view('invoice_cc_pay', $data);
                }
            }
            catch (Exception $e) {
                die(print_r($e, true));
            }
        }
    }

    private function load_invoice($invoice_id = null, $paid = 0) {
        if ($invoice_id) {
            ini_set("max_execution_time", 0);
            set_time_limit(0);

            $data['attachWashlog'] = true;

            $this->load->model('sendinvoices_model');
            $unitTypesText = $this->sendinvoices_model->getUnitNames();
            $data['unitTypesText'] = $unitTypesText;

            $customer_id = $this->invoice_model->getCustomerIDFromInvoice($invoice_id);
            if ($customer_id) {
                $invoiceDetail=$this->invoice_model->approved_invoice_detail(array($customer_id));
                $data['invoices'] = $invoiceDetail['data'];
                $invoiceDetail=$invoiceDetail['data'];
                $invoiceDetail[$invoice_id]['paid'] += $paid;
                $invoiceDetail[$invoice_id]['payment_page'] = true;
                $data['invoiceDetail']= $invoiceDetail[$invoice_id];

                $terms=$this->customers_model->getAllTerms();
                $data['terms']=array_combine(array_column($terms, 'id'), array_column($terms, 'title'));

                $locationDetails = $this->locations_model->getLocationDetailsByLocationID($data['invoiceDetail']['location_id']);
                $data['invoiceDetail']['location']=$locationDetails[0]['st_city'];

                if($invoice_id && $invoice_id<=$this->config->item('old_invoice'))
                    $html	= $this->load->view('invoice_pdf_view',$data,true);
                else
                    $html	= $this->load->view('invoice_pdf_viewNew',$data,true);

                $html 	= mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');

                $return['html'] = $html;
                $return['billing_email'] = $this->invoice_model->getBillingAndBillingCCEmail($invoiceDetail[$invoice_id], $customer_id);

                return $return;
            }
            else {
                return false;
            }
        }
        else {
            return false;
        }
    }

    public function pay_invoice() {
        try {
            $qs = new QSAPI;

            $account_id = '';
            $api_accesskey = '';
            $amount = $this->input->post('amount');
            $card_number = $this->input->post('cardnumber');
            //die($this->input->post('ccyear').'-'.$this->input->post('ccmonth').'-01');
            $card_expire = new DateTime($this->input->post('ccyear').'-'.$this->input->post('ccmonth').'-01');
            $card_expire = $card_expire->format('my');
            $cvc = $this->input->post('cvc');
            $data['ccname'] = $this->input->post('ccname');
            $email = $this->input->post('email');

            $qs->setParam('api_accesskey',$api_accesskey);
            $qs->setParam('request_format', 'FORM');
            $qs->setParam('account_id',$account_id);

            $qs->setParam('ip_address',$_SERVER['REMOTE_ADDR']);
            $qs->setParam('transaction_type','SALE');
            $qs->setParam('transaction_amount',$amount);

            $qs->setParam('tender_type','CARD');

            $qs->setParam('card_number',$card_number);
            $qs->setParam('card_expiration',$card_expire);
            $qs->setParam('card_cvv2_code',$cvc);

            $invoice_id = $this->input->post('invoice_id');
            $qs->setParam('transaction_description','MyFC Invoice #FC'.($invoice_id + 1000));

            $qs->setParam('disable_avs','1');
            $qs->setParam('disable_cvv2','0');
            $qs->setParam('disable_fraudfirewall','1');

            //die(print_r($qs, true));

            $result = $qs->process();
            $rinfo = $qs->getResponse();

            $results = array();
            parse_str($rinfo[0], $results);

            if ($results['transaction_approved'] === '1') {

                $customer_id = $this->input->post('customer_id');

                $invoiceDetail=$this->invoice_model->approved_invoice_detail(array($customer_id),$invoice_id);
                $invoiceDetail=$invoiceDetail['data'];

                $data['invoiceDetail']= $invoiceDetail[$invoice_id];

                $terms=$this->customers_model->getAllTerms();
                $data['terms']=array_combine(array_column($terms, 'id'), array_column($terms, 'title'));

                $locationDetails = $this->locations_model->getLocationDetailsByLocationID($data['invoiceDetail']['location_id']);
                $data['invoiceDetail']['location']=$locationDetails[0]['st_city'];

                $data['cc_info'] = '<p><strong>Card Number:</strong> ****'.substr($card_number, -4).'</p>';
                $data['cc_info'] .= '<p><strong>Card Expiration:</strong> '.$card_expire.'</p>';
                $data['cc_info'] .= '<p><strong>Card CVV2:</strong> '.$cvc.'</p>';
                $data['invoice_id'] = $invoice_id;


                $email_from_addr	=	'';
                $email_from_name	=	'';
                $member_email 	= $email;
                $email_config = array(
                    'protocol' 	=> 'smtp',
                    'smtp_host' 	=> 'smtp.sendgrid.net',
                    'smtp_user' 	=> '',
                    'smtp_pass' 	=> '',
                    'mailtype'	=> 'html',
                );

                $this->load->library('email', $email_config);
                $this->email->set_newline("\r\n");
                $this->email->from($email_from_addr,$email_from_name);
                $this->email->to($member_email);
                //$this->email->bcc('');
                $this->email->subject('Transaction Receipt For #FC'.($invoice_id + 1000));


                $data['email']['name'] = $data['ccname'];
                $data['email']['email'] = $email;
                $data['email']['customer_id'] = $customer_id;
                $data['email']['description'] = 'FC'.($invoice_id + 1000);

                $data['email']['transaction_type'] = 'SALE';
                $data['email']['card_brand'] = $results['card_brand'];
                $data['email']['card_num'] = '************'.substr($card_number, -4);
                $data['email']['amount'] = $amount;
                $data['email']['trans_date'] = date('Y-m-d h:i:s T', strtotime('now'));
                $data['email']['trans_id'] = $results['transaction_id'];
                $data['email']['invoice_id'] = $invoice_id;
                $data['email']['invoice_num'] = 'FC'.($invoice_id + 1000);

                $strMail = $this->load->view('./emailtemplate/email_invoice_receipt', $data , true);

                $this->email->message($strMail);
                $this->email->send();

                echo $this->invoice_model->addInvoiceCCPayment($data);
            }
            else {
                die(print_r($results, true));
            }
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function pay_multiple_invoices() {
        try {


            //die(print_r($this->input->post(), true));

            $account_id = '';
            $api_accesskey = '';
            $amounts = $this->input->post('m_amount');
            $card_number = $this->input->post('cardnumber');
            //die($this->input->post('ccyear').'-'.$this->input->post('ccmonth').'-01');
            $card_expire = new DateTime($this->input->post('ccyear').'-'.$this->input->post('ccmonth').'-01');
            $card_expire = $card_expire->format('my');
            $cvc = $this->input->post('cvc');
            $email = $this->input->post('email');

            $confirmation_ids = array();

            foreach ($amounts as $invoice_id => $amount) {
                if ($amount > 0 && $invoice_id > 0) {
                    $qs = new QSAPI;

                    $qs->setParam('api_accesskey',$api_accesskey);
                    $qs->setParam('request_format', 'FORM');
                    $qs->setParam('account_id',$account_id);

                    $qs->setParam('ip_address',$_SERVER['REMOTE_ADDR']);
                    $qs->setParam('transaction_type','SALE');
                    $qs->setParam('transaction_amount',$amount);

                    $qs->setParam('tender_type','CARD');

                    $qs->setParam('card_number',$card_number);
                    $qs->setParam('card_expiration',$card_expire);
                    $qs->setParam('card_cvv2_code',$cvc);

                    $qs->setParam('transaction_description','MyFC Invoice #FC'.($invoice_id + 1000));

                    $qs->setParam('disable_avs','1');
                    $qs->setParam('disable_cvv2','0');
                    $qs->setParam('disable_fraudfirewall','1');

                    //die(print_r($qs, true));

                    $result = $qs->process();
                    $rinfo = $qs->getResponse();

                    $results = array();
                    parse_str($rinfo[0], $results);

                    $terms=$this->customers_model->getAllTerms();

                    if ($results['transaction_approved'] === '1') {

                        $customer_id = $this->input->post('customer_id');

                        $invoiceDetail=$this->invoice_model->approved_invoice_detail(array($customer_id),$invoice_id);
                        $invoiceDetail=$invoiceDetail['data'];

                        $data = array();

                        $data['invoiceDetail']= $invoiceDetail[$invoice_id];

                        $data['terms']=array_combine(array_column($terms, 'id'), array_column($terms, 'title'));

                        $locationDetails = $this->locations_model->getLocationDetailsByLocationID($data['invoiceDetail']['location_id']);
                        $data['invoiceDetail']['location']=$locationDetails[0]['st_city'];

                        $data['cc_info'] = '<p><strong>Card Number:</strong> ****'.substr($card_number, -4).'</p>';
                        $data['cc_info'] .= '<p><strong>Card Expiration:</strong> '.$card_expire.'</p>';
                        $data['cc_info'] .= '<p><strong>Card CVV2:</strong> '.$cvc.'</p>';
                        $data['invoice_id'] = $invoice_id;


                        $email_from_addr	=	'';
                        $email_from_name	=	'';
                        $member_email 	= $email;
                        $email_config = array(
                            'protocol' 	=> 'smtp',
                            'smtp_host' 	=> 'smtp.sendgrid.net',
                            'smtp_user' 	=> '',
                            'smtp_pass' 	=> '',
                            'mailtype'	=> 'html',
                        );

                        $this->load->library('email', $email_config);
                        $this->email->set_newline("\r\n");
                        $this->email->from($email_from_addr,$email_from_name);
                        $this->email->to($member_email);
                        //$this->email->bcc('');
                        $this->email->subject('Transaction Receipt For #FC'.($invoice_id + 1000));


                        $data['email']['name'] = $this->input->post('ccname');
                        $data['email']['email'] = $email;
                        $data['email']['customer_id'] = $customer_id;
                        $data['email']['description'] = 'FC'.($invoice_id + 1000);

                        $data['email']['transaction_type'] = 'SALE';
                        $data['email']['card_brand'] = $results['card_brand'];
                        $data['email']['card_num'] = '************'.substr($card_number, -4);
                        $data['email']['amount'] = $amount;
                        $data['email']['trans_date'] = date('Y-m-d h:i:s T', strtotime('now'));
                        $data['email']['trans_id'] = $results['transaction_id'];
                        $data['email']['invoice_id'] = $invoice_id;
                        $data['email']['invoice_num'] = 'FC'.($invoice_id + 1000);

                        $strMail = $this->load->view('./emailtemplate/email_invoice_receipt', $data , true);

                        $this->email->message($strMail);
                        $this->email->send();

                        $confirmation_ids[] = $this->invoice_model->addInvoiceCCPayment($data);
                    }
                    else {
                        $errors[] = 'Payment for invoice FC'.($invoice_id + 1000).' declined';
                    }
                }
            }
            if ($errors > 0 ){

            }
            echo implode(',', $confirmation_ids);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    public function send_paid_invoices_batch_email() {
        $data['payments'] = $this->invoice_model->getCCInvoicePayments();

        $file = $this->create_batch_report_excel_file($data['payments']);

        //die(print_r($data['payments'], true));
        $html = $this->load->view('./emailtemplate/email_invoice_batch', $data, true);

        $email_from_addr	=	'';
        $email_from_name	=	'';
        $member_email 	= '';
        $email_config = array(
            'protocol' 	=> 'smtp',
            'smtp_host' 	=> 'smtp.sendgrid.net',
            'smtp_user' 	=> '',
            'smtp_pass' 	=> '',
            'mailtype'	=> 'html',
        );

        $this->load->library('email', $email_config);
        $this->email->set_newline("\r\n");
        $this->email->from($email_from_addr,$email_from_name);
        $this->email->to($member_email);
        if ($file) {
            $this->email->attach($file['file_path']);
        }
        $this->email->subject('Paid Invoices for '.date('Y-m-d', strtotime('now')));
        $this->email->message($html);
        $this->email->send();
    }

    private function create_batch_report_excel_file($payments) {
        $this->load->library('PHPExcel');
        $this->load->library('phpexceldefault');

        $s = 0;

        $insertdata = $this->phpexcel->createSheet($s);

        $insertdata->getDefaultRowDimension()->setCollapsed(FALSE);
        $insertdata->getDefaultStyle()->getAlignment()->setWrapText(true);
        $insertdata->getDefaultStyle()->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);

        $c = 1;

        $insertdata->getColumnDimension("A")->setWidth(30);
        $insertdata->getColumnDimension("B")->setWidth(30);
        $insertdata->getColumnDimension("C")->setWidth(30);
        $insertdata->getColumnDimension("D")->setWidth(30);

        $insertdata->SetCellValue("A$c", "AUTHORIZATION_DATE");
        $insertdata->SetCellValue("B$c", "TRANSACTION_AMOUNT");
        $insertdata->SetCellValue("C$c", "DESCRIPTION");
        $insertdata->SetCellValue("D$c", "COMPANY");

        foreach ($payments as $payment) {
            $c++;
            $insertdata->SetCellValue("A$c", date('n/j/y G:i', strtotime($payment['trans_date'])));
            $insertdata->SetCellValue("B$c", $payment['amount']);
            $insertdata->SetCellValue("C$c", $payment['description']);
            $insertdata->SetCellValue("D$c", $payment['customer_name']);
        }

        $this->phpexcel->setActiveSheetIndex(0);

        $invoiceFileName = 'MyFC_Invoice_Payments_'.date('n-j-y', strtotime('now'));

        $path = $_SERVER['DOCUMENT_ROOT'] . '/webservices_images/invoice_payments_xlsx/';
        $objWriter = PHPExcel_IOFactory::createWriter($this->phpexcel, 'Excel2007');
        $objWriter->save($path . $invoiceFileName . '.xlsx');

        $this->phpexcel->disconnectWorksheets();
        unset($objWriter);

        $file['file_path'] = $_SERVER['DOCUMENT_ROOT'] . '/webservices_images/invoice_payments_xlsx' . '/' . $invoiceFileName . '.xlsx';
        $file['file_url'] = base_url() . 'webservices_images/invoice_payments_xlsx' . '/' . $invoiceFileName . '.xlsx';
        return $file;
    }
}