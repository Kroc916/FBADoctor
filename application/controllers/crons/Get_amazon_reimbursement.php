<?php /* Generated by Webdimensions  www.webdimensions.co.in */
class Get_amazon_reimbursement extends CI_Controller {
    public function __construct() {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
        }
        $this->load->library('rat');
        $this->load->library('amazon');
        $this->load->model("Ipn_model");
        $this->load->model('user_model');
        $this->load->model('account_model');
        $this->load->model('reimbursement_model');
        $this->load->model('feedback_fba_order_model');
        $this->load->model('feedback_setting_model');
        $this->load->model('amazon_report_request_log_model');
        $this->load->model("Inventory_salvager_model");
        $this->load->model('amazon_api_log_model');
        $this->load->model('feedback_asin_model');
        $this->load->model('Refund_rescuer_model');
        $this->load->model('custom_case_model');
        $this->logging = new Rat();
        $this->common->stripe_setting();
        $this->cronName = "Amazon Reimbursement Fetch Cron";
    }
    function index() {
        $content = date("d-m-Y H:i:s") . " " . "Amazon Reimbursement Fetch Cron Start";
        $this->logging->write_log($this->cronName, $content, 1, 0);
        $result = $this->feedback_setting_model->get_reimburse_feedback_setting();
        $this->getfbaorderfromamazon($result);
    }
    function getfbaorderfromamazon($result) {
        global $store, $AMAZON_SERVICE_URL;
        foreach ($result->result() as $row) {
            $count= 0;
            $result = $this->reimbursement_model->getAmzDateIterationByAccountId($row->ID_ACCOUNT);
            if($result){
                foreach ($result as $key=>$record){
                    if($record['ReimburseStatus']==1){
                        $count++;
                    }
                }
                $totalCount = count($result);
                if($totalCount==$count){
                    echo "<pre/>Account Id : =>".$row->ID_ACCOUNT."<br>";
                    $feedStatus = 1;
                    $ReportRequestId = "";
                    $GeneratedReportId = '';
                    $store[$row->amazonstorename]['ID_ACCOUNT'] = $row->ID_ACCOUNT;
                    $store[$row->amazonstorename]['merchantId'] = $row->mws_sellerid; //Merchant ID for this store
                    $store[$row->amazonstorename]['marketplaceId'] = $row->marketplace_id; //Marketplace ID for this store
                    $store[$row->amazonstorename]['keyId'] = $row->access_key; //Access Key ID
                    $store[$row->amazonstorename]['secretKey'] = $row->secret_key; //Secret Access Key for this store
                    $store[$row->amazonstorename]['MWSAuthToken'] = $row->mws_authtoken; //Secret Access Key for this store
                    $AMAZON_SERVICE_URL = $row->host;
                    $searchArrayLogRow = array('status !=' => 2, 'reporttype =' => '_GET_FBA_REIMBURSEMENTS_DATA_', 'ID_ACCOUNT =' => $row->ID_ACCOUNT);
                    $logRow = $this->amazon_report_request_log_model->get_log_result($searchArrayLogRow);
                    if (!empty($logRow->requestid))
                        $ReportRequestId = $logRow->requestid;
                    if (empty($ReportRequestId)) {
                        try {
                            echo 'Getting fba Reimbursement data<br>';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, '_GET_FBA_REIMBURSEMENTS_DATA_');
                            $objAmazonReportRequest = new AmazonReportRequest($row->amazonstorename);
                            echo '<br>Previous Day Data.<br>';
                            $date= date('Y-m-d H:i:s');
                            $previousDate = date('Y-m-d', strtotime($date .' -4 day'));
                            $startDate = $previousDate." 00:00:00";
                            $objAmazonReportRequest->setTimeLimits($startDate,$date);
                            $objAmazonReportRequest->setReportType("_GET_FBA_REIMBURSEMENTS_DATA_");
                            $objAmazonReportRequest->requestReport();
                            $response = $objAmazonReportRequest->getResponse();
                            $feedStatus = 0;
                            if (!empty($response['ReportRequestId'])) {
                                $ReportRequestId = $response['ReportRequestId'];
                                $feedStatus = 1;$fulldataload =0;
                                if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_CANCELLED_') {
                                    echo 'No Data!!!!!!';
                                    $feedStatus = 2;
                                    $fulldataload =2;
                                }
                                if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_DONE_' && $row->loadfirstreimburse = 0) {
                                    $fulldataload = 1;
                                    $feedStatus = 2;
                                }
                                $addReportRequestLogParams = array(
                                    'ID_ACCOUNT' => $row->ID_ACCOUNT,
                                    'logdate' => time(),
                                    'reporttype' => '_GET_FBA_REIMBURSEMENTS_DATA_',
                                    'requestid' => $ReportRequestId,
                                    'status' => $feedStatus,
                                    'fulldataload' => $fulldataload
                                );
                                $this->amazon_report_request_log_model->add_amazon_report_request_log($addReportRequestLogParams);
                                if ($fulldataload == 1) {
                                    $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                        'loadfirstreimburse' => '1',
                                        'hourlimit_reimburse' => "hourlimit_reimburse+1",
                                    ));
                                }
                            }

                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Reimbursement Fetch Cron Start". ":RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }

                    if ($feedStatus == 1 && !empty($ReportRequestId) && empty($GeneratedReportId)) {
                        try {
                            echo '<br>Getting generated report id<br>';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata2');
                            $objAmazonReportRequestList = new AmazonReportRequestList($row->amazonstorename);
                            $objAmazonReportRequestList->setRequestIds($ReportRequestId);
                            $works = $objAmazonReportRequestList->fetchRequestList();
                            $response = $objAmazonReportRequestList->getList();
                            echo "<br><pre />";print_r($objAmazonReportRequestList);print_r($response)."<br>";
                            $feedStatus = 0;
                            if (!empty($response[0]['GeneratedReportId'])) {
                                $GeneratedReportId = $response[0]['GeneratedReportId'];
                                $feedStatus = 1;
                            }
                            if ($response[0]['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response[0]['ReportProcessingStatus'] == '_CANCELLED_') {
                                echo 'No Data!!!!!!';
                                $feedStatus = 2;
                                if (!empty($logRow->ID_LOG)) {
                                    $this->amazon_report_request_log_model->custom_where_update_amazon_report_request_log(array('ID_LOG =' => $logRow->ID_LOG), array(
                                        'status' => '2'
                                    ));
                                }
                            }
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'hourlimit_reimburse' => "hourlimit_reimburse + 1",
                            ));
                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Reimbursement Fetch Cron Start". ":RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }

                    // Last Step Get the Report
                    if ($feedStatus == 1 && !empty($GeneratedReportId)) {
//                sleep(2);
                        try {
                            echo 'Getting report';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata3');
                            $objAmazonReportRequestList = new AmazonReport($row->amazonstorename);
                            $objAmazonReportRequestList->setReportId($GeneratedReportId);
                            $objAmazonReportRequestList->fetchReport();
                            $data = $objAmazonReportRequestList->returnReport();
                            if (!empty($data)) {
                                $lineCount = 0;
                                $tmp = explode("\n", $data);
                                $arrInventoryId = array();
                                print_r($tmp);
                                foreach ($tmp as $line) {
                                    echo "<pre />"; print_r($line);
                                    if ($lineCount > 0) {
                                        $saleschannel = '';
                                        $fieldData = explode("\t", $line);
                                        $approvalDate = addslashes($fieldData[0]);
                                        $reimbursementID = addslashes($fieldData[1]); // new
                                        $caseId = addslashes($fieldData[2]); // new
                                        $orderID = addslashes($fieldData[3]); // new
                                        $reason = addslashes($fieldData[4]);
                                        $sku = addslashes($fieldData[5]); // new
                                        $fnsku = addslashes($fieldData[6]);
                                        $asin = addslashes($fieldData[7]);
                                        $productName = addslashes($fieldData[8]);
                                        $condition = addslashes($fieldData[9]);
                                        $currencyUnit = addslashes($fieldData[10]); // new
                                        $amountPerUnit = addslashes($fieldData[11]); // new
                                        $amountTotal = addslashes($fieldData[12]); // new
                                        $qtyReimbursedCash = addslashes($fieldData[13]); // new
                                        $qtyReimbursedInv = addslashes($fieldData[14]); // new
                                        $qtyReimbursedTotal = addslashes($fieldData[15]); // new
                                        $qtyReimbursedID = addslashes($fieldData[16]); // new
                                        $originalReimbursedType = addslashes($fieldData[17]); // new
                                        echo "<pre />"; print_r($fieldData);
                                        if (!empty($saleschannel)) {
                                            $marketplaceidforSalesChannel = $this->common->GetMarketPlaceIDFromSalesChannel($saleschannel);
                                        }
                                        if (empty($marketplaceidforSalesChannel))
                                            $marketplaceidforSalesChannel = $row->marketplace_id;
                                        $queryArray['ID_ACCOUNT'] = $row->ID_ACCOUNT;
                                        $queryArray['reimburse_id'] = $reimbursementID;
                                        $queryArray['case_id']      = $caseId;
                                        $queryArray['fnsku']        = $fnsku;
                                        $totalRow = $this->Refund_rescuer_model->custom_count_reimbursement_case($queryArray);
                                        $this->Refund_rescuer_model->updateCaseDetailbyOrderIdandAccountID($orderID, $row->ID_ACCOUNT);
                                        $isCaseIDExist = $this->custom_case_model->checkCustomCaseIdExist( $caseId );
                                        if ( ! empty( $isCaseIDExist ) ) {
                                            $customData = array(
                                                'reimbursedId'     => $reimbursementID,
                                                'reimbursedAmount' => $amountPerUnit * $qtyReimbursedTotal,
                                            );
                                            $this->custom_case_model->updateCaseByCaseId( $isCaseIDExist['id'], $customData );
                                        }
                                        if ( $caseId != '' ) {
                                            $this->Refund_rescuer_model->updateCaseInventoryDetail( $caseId, $row->ID_ACCOUNT, $fnsku );
                                        }
                                        if (empty($totalRow)) {
                                            // Insert into database if it is unique
                                            $orderParam = array(
                                                'ID_ACCOUNT' => $row->ID_ACCOUNT,
                                                'order_id' => $orderID,
                                                'reimburse_id' => $reimbursementID,
                                                'case_id' => $caseId,
                                                'reason' => $reason,
                                                'sku' => $sku,
                                                'fnsku' => $fnsku,
                                                'asin' => $asin,
                                                'product_name' => $productName,
                                                'condition' => $condition,
                                                'currency_unit' => $currencyUnit,
                                                'amount_per_unit' => $amountPerUnit,
                                                'amount_total' => $amountTotal,
                                                'quantity_reimbursed_cash' => $qtyReimbursedCash,
                                                'quantity_reimbursed_inventory' => $qtyReimbursedInv,
                                                'quantity_reimbursed_total' => $qtyReimbursedTotal,
                                                'original_reimburment_id' => $qtyReimbursedID,
                                                'original_reimburment_type' => $originalReimbursedType,
                                                'approval_date' => $approvalDate,
                                                'actual_reimbursed' => $amountPerUnit * $qtyReimbursedTotal
                                            );
                                            echo "<pre/ >"; print_r($orderParam);
                                            $arrInventoryId[]=$this->Refund_rescuer_model->add_reimbursement_detail($orderParam);
                                        }
                                        else
                                        {
                                            $orderParam = array(
                                                'ID_ACCOUNT' => $row->ID_ACCOUNT,
                                                'order_id' => $orderID,
                                                'reimburse_id' => $reimbursementID,
                                                'case_id' => $caseId,
                                                'reason' => $reason,
                                                'sku' => $sku,
                                                'fnsku' => $fnsku,
                                                'asin' => $asin,
                                                'product_name' => $productName,
                                                'condition' => $condition,
                                                'currency_unit' => $currencyUnit,
                                                'amount_per_unit' => $amountPerUnit,
                                                'amount_total' => $amountTotal,
                                                'quantity_reimbursed_cash' => $qtyReimbursedCash,
                                                'quantity_reimbursed_inventory' => $qtyReimbursedInv,
                                                'quantity_reimbursed_total' => $qtyReimbursedTotal,
                                                'original_reimburment_id' => $qtyReimbursedID,
                                                'original_reimburment_type' => $originalReimbursedType,
                                                'approval_date' => $approvalDate,
                                                'actual_reimbursed' => $amountPerUnit * $qtyReimbursedTotal
                                            );
                                            $this->Refund_rescure_model->update_reimbursement_detail($orderParam,$totalRow['id']);
                                        }
                                    }
                                    $lineCount++;
                                }
                                if (!empty($arrInventoryId)) {
                                    $strCases = implode(",", $arrInventoryId);
                                    $content = "Inventory Id's Added :" . $strCases;
                                    $this->logging->write_log($this->cronName, $content, 2, $row->ID_ACCOUNT);
                                }
                            }
                            // Feedback settings date
                            $t = time();
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'api_reimbursedate ' => $t,
                                'hourlimit_reimburse' => "5"
                            ));
                            // Update the request id
                            $this->amazon_report_request_log_model->custom_where_update_amazon_report_request_log(array('ID_ACCOUNT =' => $row->ID_ACCOUNT, 'requestid =' => $ReportRequestId), array(
                                'status' => '2'
                            ));
                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Reimbursement Fetch Cron Start". ":RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }
                }
                else{
                    echo "<br>Historical Data is Remaining to Fetch.<br>";
                }
            }
            else{
                $content = date("d-m-Y H:i:s") . " AmzDateIteration Data is not Available. " . "Amazon Reimbursement Fetch Cron.";
                $this->logging->write_log($this->cronName, $content, 1, 0);
            }
        }
        $content = date("d-m-Y H:i:s") . " " . "Amazon Reimbursement Fetch Cron End.";
        $this->logging->write_log($this->cronName, $content, 3, 0);
        $this->generatecharge();
    }
    // stripe charge generation
        public function generatecharge(){
        $cronName = "Generate charges";
        // Logging in database.
        $content = date("d-m-Y H:i:s") . " " . "Generate  charges cron Start";
        $this->logging->write_log($cronName, $content, 1, 0);
        $cronName = "Generate charges";
        $result = $this->user_model->getAllActiveUsers();
        foreach($result as $new_result){
            $customer = $this->reimbursement_model->getcustomer($new_result['ID_ACCOUNT']);
            if(!empty($customer)) {
                $charges = $this->account_model->get_account_charge( $new_result['ID_ACCOUNT'] );
                $arrCaseID = array();
                $date =date('Y-m-d H:i:s');
                $orderpaycases = $this->reimbursement_model->getOrderPaymentCases($new_result['ID_ACCOUNT']);
                if(!empty($orderpaycases)) {
                    foreach ($orderpaycases as $orderpaycase) {
                        if ( empty( $orderpaycase['actual_reimbursed'] ) ||  $orderpaycase['actual_reimbursed'] == 0) {
                            $total_amount = abs( $orderpaycase['amount_total'] );
                        } else {
                            $total_amount = abs( $orderpaycase['actual_reimbursed'] );
                        }
                        $charge_amount = ($total_amount * $charges['charges'] )/100;
                        $data = array('ID_ACCOUNT'=>$new_result['ID_ACCOUNT'],
                            'caseid'=>$orderpaycase['case_id'],
                            'amount'=>$charge_amount,
                            'reimburseid'=>$orderpaycase['reimburse_id'],
                            'currency_unit'=>$orderpaycase['currency_unit'],
                            'fnsku'            => $orderpaycase['fnsku'],
                            'amount_recovered' => $total_amount,
                            'date'=>$date,
                            'approval_date'    => $orderpaycase['approval_date']
                        );
                        $last_id=$this->reimbursement_model->addpaymenthistory($data);
                        $status = array('status' => '2');
                        $this->reimbursement_model->updatecasedetail($status, $orderpaycase['id']);
                    }
                }
                $inventorypaycases = $this->Inventory_salvager_model->getInventoryPaymentCases( $new_result['ID_ACCOUNT'] );
                if ( ! empty( $inventorypaycases ) ) {
                    foreach ( $inventorypaycases as $inventorypaycase ) {
                        $paymentHistoryCheck = $this->Inventory_salvager_model->paymenthistorycheck( $new_result['ID_ACCOUNT'], $inventorypaycase['reimburse_id'], $inventorypaycase['caseId'], $inventorypaycase['fnsku'] );
                        if ( ! empty( $paymentHistoryCheck ) ) {
                            $status = array( 'status' => '2' );
                            $this->reimbursement_model->updateinventorycasedetail( $status, $inventorypaycase['id'] );
                            $status = array( 'status' => '1' );
                            $this->Inventory_salvager_model->updatereimbursementdata( $new_result['ID_ACCOUNT'], $inventorypaycase['reimburse_id'], $inventorypaycase['caseId'], $status );
                        } else {
                            $reimbursementCheck = $this->Inventory_salvager_model->reimbursementRecordFetch( $inventorypaycase['fnsku'], $inventorypaycase['caseId'] );
                            if ( ! empty( $reimbursementCheck ) ) {
                                if ( empty( $reimbursementCheck['actual_reimbursed'] ) ||  $reimbursementCheck['actual_reimbursed'] == 0) {
                                    $reimb_total_amount = ( $reimbursementCheck['amount_per_unit'] * $reimbursementCheck['quantity_reimbursed_total'] == 0 ? abs( $reimbursementCheck['amount_total'] ) : abs( $reimbursementCheck['amount_per_unit'] * $reimbursementCheck['quantity_reimbursed_total'] ) );
                                } else {
                                    $reimb_total_amount = abs( $reimbursementCheck['actual_reimbursed'] );
                                }
                                $charge_amount       = ( $reimb_total_amount * $charges['charges'] ) / 100;
                                $parameter           = array(
                                    'amount'   => round( $charge_amount ),
                                    'customer' => $customer[0]['customerid'],
                                    'currency' => $inventorypaycase['currency_unit']
                                );
                                $data                = array(
                                    'ID_ACCOUNT'       => $new_result['ID_ACCOUNT'],
                                    'caseid'           => $inventorypaycase['caseId'],
                                    'amount'           => $charge_amount,
                                    'reimburseid'      => $reimbursementCheck['reimburse_id'],
                                    'currency_unit'    => $reimbursementCheck['currency_unit'],
                                    'fnsku'            => $reimbursementCheck['fnsku'],
                                    'amount_recovered' => $reimb_total_amount,
                                    'date'             => $date,
                                    'approval_date'    => $reimbursementCheck['approval_date']
                                );
                                $last_id             = $this->reimbursement_model->addpaymenthistory( $data );
                                $reimbursementstatus = array( 'status' => '1' );
                                $this->reimbursement_model->updateReimbursementStatus( $reimbursementCheck['id'], $reimbursementstatus );
                            }
                            $status = array( 'status' => '2' );
                            $this->reimbursement_model->updateinventorycasedetail( $status, $inventorypaycase['id'] );
                        }
                    }
                }

                $customCases = $this->Custom_case_model->getCustomCaseGenerated( $new_result['ID_ACCOUNT'] );
                if ( ! empty( $customCases ) ) {
                    foreach ( $customCases as $customCase ) {
                        $customReimbursement = $this->Custom_case_model->getCustomCaseReimbursementDetail( $new_result['ID_ACCOUNT'], $customCase['caseId'] );
                        if ( ! empty( $customReimbursement ) ) {
                            $final_amount        = $reimbursementId = $key = '';
                            $custom_total_amount = '';
                            foreach ( $customReimbursement as $key => $reimbursementArr ) {
                                $paymentHistoryCheck = $this->Inventory_salvager_model->paymenthistorycheck( $new_result['ID_ACCOUNT'], $reimbursementArr['reimburse_id'], $customCase['caseId'] );
                                if ( ! empty( $paymentHistoryCheck ) ) {
                                    $status = array( 'status' => '1' );
                                    $this->Inventory_salvager_model->updatereimbursementdata( $new_result['ID_ACCOUNT'], $reimbursementArr['reimburse_id'], $customCase['caseId'], $status );
                                } else {
                                    if ( empty( $customCase['actual_reimbursed'] ) ||  $customCase['actual_reimbursed'] == 0) {
                                        $custom_total_amount = ( $reimbursementArr['amount_per_unit'] * $reimbursementArr['quantity_reimbursed_total'] == 0 ? abs( $reimbursementArr['amount_total'] ) : abs( $reimbursementArr['amount_per_unit'] * $reimbursementArr['quantity_reimbursed_total'] ) );
                                        if($custom_total_amount == 0){
                                            $custom_total_amount = $reimbursementArr['amount_per_unit'];
                                        }
                                    } else {
                                        $custom_total_amount = abs( $reimbursementArr['actual_reimbursed'] );
                                    }
                                    $charge_amount       = ( $custom_total_amount * $charges['charges'] ) / 100;
                                    $data                = array(
                                        'ID_ACCOUNT'       => $new_result['ID_ACCOUNT'],
                                        'caseid'           => $customCase['caseId'],
                                        'amount'           => $charge_amount,
                                        'reimburseid'      => $reimbursementArr['reimburse_id'],
                                        'fnsku'            => $reimbursementArr['fnsku'],
                                        'currency_unit'    => $reimbursementArr['currency_unit'],
                                        'amount_recovered' => $custom_total_amount,
                                        'date'             => $date,
                                        'approval_date'    => $reimbursementArr['approval_date']
                                    );
                                    $last_id             = $this->reimbursement_model->addpaymenthistory( $data );
                                    $reimbursementstatus = array( 'status' => '1' );
                                    $this->reimbursement_model->updateReimbursementStatus( $reimbursementArr['id'], $reimbursementstatus );
                                    $final_amount = $final_amount + $custom_total_amount;
                                    if ( $key == 0 ) {
                                        $reimbursementId = $reimbursementArr['reimburse_id'];
                                    } else {
                                        $reimbursementId .= ' - ' . $reimbursementArr['reimburse_id'];
                                    }
                                }
                            }
                            $status = array(
                                'status'           => '2',
                                'reimbursedId'     => $reimbursementId,
                                'reimbursedAmount' => $final_amount
                            );
                            $this->Custom_case_model->updateCustomCaseCaseDetail( $status, $customCase['id'] );
                        }
                    }
                }
                /*$customcasecharge = $this->custom_case_model->Customcasecharges($new_result['ID_ACCOUNT']);
                if(!empty($customcasecharge)) {
                    foreach ($customcasecharge as $customcasecharge) {
                        $total_amount = abs($customcasecharge['amount_total']);
                        $charge_amount = ($total_amount * 25)/100;
                        $data = array(
                            'ID_ACCOUNT'=>$new_result['ID_ACCOUNT'],
                            'caseid'=>$customcasecharge['case_id'],
                            'amount'=>$charge_amount,
                            'reimburseid'=>$customcasecharge['reimburse_id'],
                            'currency_unit'=>$customcasecharge['currency_unit'],
                            'date'=>$date,
                            'total_amount'=>$customcasecharge['amount_total']
                        );
                        $last_id=$this->reimbursement_model->addpaymenthistory($data);
                        $status = array('custom_status' => '2');
                        $this->custom_case_model->updatecustomcasedetail($status, $customcasecharge['CUSTOM_ID']);
                    }
                }*/


                /*$inventorypaycases = $this->Inventory_salvager_model->getInventoryPaymentCases($new_result['ID_ACCOUNT']);
                if(!empty($inventorypaycases)) {
                    foreach ($inventorypaycases as $inventorypaycase) {
                        $total_amount = abs($inventorypaycase['amount_total']);
                        $charge_amount = ($total_amount * 25)/100;
                        $parameter = array('amount' => round($charge_amount),
                            'customer' => $customer[0]['customerid'],
                            'currency' => $inventorypaycase['currency_unit']
                        );
                        $data = array('ID_ACCOUNT'=>$new_result['ID_ACCOUNT'],
                            'caseid'=>$inventorypaycase['caseId'],
                            'amount'=>$charge_amount,
                            'reimburseid'=>$inventorypaycase['reimburse_id'],
                            'currency_unit'=>$orderpaycase['currency_unit'],
                            'date'=>$date
                        );
                        $last_id=$this->reimbursement_model->addpaymenthistory($data);
                        $status = array('status' => '2');
                        $this->reimbursement_model->updateinventorycasedetail($status, $inventorypaycase['id']);
                    }
                }*/
                if (!empty($arrCaseID)) {
                    $strCases = implode(",", $arrCaseID);
                    $content = "Generated Charges :" . $strCases;
                    $this->logging->write_log($cronName, $content, 2, $new_result['ID_ACCOUNT']);
                }
            }
        }
        $content = date("d-m-Y H:i:s") . " " . "Generate charges cron End";
        $this->logging->write_log($cronName, $content, 3, 0);
    }
}
