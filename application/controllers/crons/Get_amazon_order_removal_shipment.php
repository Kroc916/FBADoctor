<?php /* Generated by Webdimensions  www.webdimensions.co.in */

class Get_amazon_order_removal_shipment extends CI_Controller
{
    function __construct()
    {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
        }
        $this->load->library('amazon');
        $this->load->model('reimbursement_model');
        $this->load->model('feedback_fba_order_model');
        $this->load->model('feedback_setting_model');
        $this->load->model('amazon_report_request_log_model');
        $this->load->model('amazon_api_log_model');
        $this->load->model('account_model');
        $this->load->model('feedback_asin_model');
	    $this->load->model('Order_removal_model');
	    $this->load->library('rat');
        $this->logging = new Rat();
        $this->cronName = "Amazon Order Removal Shipment Cron";
    }

    function index()
    {
        $content = date("d-m-Y H:i:s") . " " . "Amazon Order Removal Shipment Start";
        $this->logging->write_log($this->cronName, $content, 1, 0);
        $result = $this->feedback_setting_model->get_order_removal_feedback_settings();
        $this->getfbaorderfromamazon($result);
    }

    function getfbaorderfromamazon($result)
    {
        global $store, $AMAZON_SERVICE_URL;
        foreach ($result->result() as $row)
        {
            $count= 0;
            $result = $this->reimbursement_model->getAmzDateIterationByAccountId($row->ID_ACCOUNT);
            if($result) {
                foreach ($result as $key => $record) {
                    if ($record['ShipmentOrderRemovalStatus'] == 1) {
                        $count++;
                    }
                }
	            $totalCount = count($result);
                if ($totalCount == $count) {
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
                    $searchArrayLogRow = array('status !=' => 2, 'reporttype =' => '_GET_FBA_FULFILLMENT_REMOVAL_SHIPMENT_DETAIL_DATA_', 'ID_ACCOUNT =' => $row->ID_ACCOUNT);
                    $logRow = $this->amazon_report_request_log_model->get_log_result($searchArrayLogRow);
                    if (!empty($logRow->requestid))
                        $ReportRequestId = $logRow->requestid;
                    if (empty($ReportRequestId)) {
                        try {
                            echo 'Getting Amazon Order Removal Shipment data';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata1');
                            $objAmazonReportRequest = new AmazonReportRequest($row->amazonstorename);
                            print_r($objAmazonReportRequest);

                            echo '<br>Previous Day Data.<br>';
                            $date= date('Y-m-d H:i:s');
                            $previousDate = date('Y-m-d', strtotime($date .' -4 days'));
                            $startDate = $previousDate." 00:00:00";
                            $objAmazonReportRequest->setTimeLimits($startDate,$date);
                            // $objAmazonReportRequest->setTimeLimits('Jan 01, 2016', 'Jan 31, 2016');
                            $objAmazonReportRequest->setReportType("_GET_FBA_FULFILLMENT_REMOVAL_SHIPMENT_DETAIL_DATA_");
                            $objAmazonReportRequest->requestReport();
                            $response = $objAmazonReportRequest->getResponse();
                            $feedStatus = 0;
                            $fulldataload = 0;
                            if (!empty($response['ReportRequestId'])) {
                                $ReportRequestId = $response['ReportRequestId'];
                                $feedStatus = 1;
                                if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_CANCELLED_') {
                                    echo 'No Data!!!!!!';
                                    $feedStatus = 2;
                                }
                                if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_DONE_' && $row->loadedfirstorderfba = 0) {
                                    $fulldataload = 1;
                                    $feedStatus = 2;
                                }
                                print_r($response);
                                $t = time();
                                 $addReportRequestLogParams = array(
                                    'ID_ACCOUNT' => $row->ID_ACCOUNT,
                                    'logdate' => $t,
                                    'reporttype' => '_GET_FBA_FULFILLMENT_REMOVAL_SHIPMENT_DETAIL_DATA_',
                                    'requestid' => $ReportRequestId,
                                    'status' => $feedStatus,
                                    'fulldataload' => $fulldataload
                                );
                                $this->amazon_report_request_log_model->add_amazon_report_request_log($addReportRequestLogParams);
                                if ($fulldataload == 1) {
                                    $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                        'loadedfirstorderfba' => '1',
                                        'hourlimit_removalorder' => "hourlimit_removalorder+1",
                                    ));
                                }
                            }
                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Order Removal Shipment Cron Start :RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }

                    if ($feedStatus == 1 && !empty($ReportRequestId) && empty($GeneratedReportId)) {
                       try {
                            echo 'Getting generated report id';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata2');
                            $objAmazonReportRequestList = new AmazonReportRequestList($row->amazonstorename);
                            $objAmazonReportRequestList->setRequestIds($ReportRequestId);
                            $works = $objAmazonReportRequestList->fetchRequestList();
                            $response = $objAmazonReportRequestList->getList();
                            echo "<pre />";
                            print_r($objAmazonReportRequestList) . "<br>";
                            print_r($response) . "<br>";
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
                                'hourlimit_removalorder ' => "hourlimit_removalorder + 1",
                            ));
                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Order Removal Shipment Cron Start :RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }

                    if ($feedStatus == 1 && !empty($GeneratedReportId)) {
                        try {
                            echo 'Getting report';
                            $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata3');
                            $objAmazonReportRequestList = new AmazonReport($row->amazonstorename);
                            $objAmazonReportRequestList->setReportId($GeneratedReportId);
                            $objAmazonReportRequestList->fetchReport();
                            $data = $objAmazonReportRequestList->returnReport();
                            /*$data = $objAmazonReportRequestList->getRawReport();*/
                            if (!empty($data)) {
                                // print_r($data);
                                $lineCount = 0;
                                $tmp = explode("\n", $data);
                                $arrorderId = array();
                                foreach ($tmp as $line) {
                                    print_r($line);
                                    if ($lineCount > 0) {
	                                    $fieldData = explode("\t", $line);
	                                    $request_date = addslashes($fieldData[0]);
	                                    $orderID = addslashes($fieldData[1]); // new
	                                    $shipment_date = addslashes($fieldData[2]); // new
	                                    $sku = addslashes($fieldData[3]); // new
	                                    $fnsku = addslashes($fieldData[4]);
	                                    $disposition = addslashes($fieldData[5]); // new
	                                    $shipped_qty = addslashes($fieldData[6]);
	                                    $carrier = addslashes($fieldData[7]);
	                                    $tracking_number = addslashes($fieldData[8]);
	                                    if (empty($marketplaceidforSalesChannel))
		                                    $marketplaceidforSalesChannel = $row->marketplace_id;
	                                    $queryArray['ID_ACCOUNT'] = $row->ID_ACCOUNT;
	                                    $queryArray['order_id'] = $orderID;
	                                    $queryArray['sku'] = $sku;
	                                    $queryArray['tracking_number'] = $tracking_number;
	                                    $totalRow = $this->Order_removal_model->custom_count_order_removal($queryArray);
	                                    if ($totalRow == 0 && !empty($orderID)) {
		                                     $orderParam = array(
			                                    'ID_ACCOUNT' => $row->ID_ACCOUNT,
			                                    'order_id' => $orderID,
			                                    'sku' => $sku,
			                                    'fnsku' => $fnsku,
			                                    'disposition' => $disposition,
			                                    'shipped_qty' => $shipped_qty,
			                                    'carrier' => $carrier,
			                                    'tracking_number' => $tracking_number,
			                                    'request_date' => $request_date,
			                                    'shipment_date' => $shipment_date
		                                    );
		                                    echo "<pre/ >";
		                                    print_r($orderParam);
		                                    $arrorderId[] = $this->Order_removal_model->add_order_removal($orderParam);
	                                    }
                                    }
	                                $lineCount++;
                                }
                                if (!empty($arrorderId)) {
                                    $strCases = implode(",", $arrorderId);
                                    $content = "Order Id's Added :" . $strCases;
                                    $this->logging->write_log($this->cronName, $content, 2, $row->ID_ACCOUNT);
                                }
                            }
                            $t = time();
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'api_removalorder ' => $t,
                                'hourlimit_removalorder ' => "5"
                            ));
                            $this->amazon_report_request_log_model->custom_where_update_amazon_report_request_log(array('ID_ACCOUNT =' => $row->ID_ACCOUNT, 'requestid =' => $ReportRequestId), array(
                                'status' => '2'
                            ));
                        } catch (Exception $ex) {
                            $feedStatus = 0;
                            $content = date("d-m-Y H:i:s") . " " . "Amazon Order Removal Shipment Cron Start :RequestReportList " . $ex->getMessage();
                            $this->logging->write_log($this->cronName, $content, 1, 0);
                        }
                    }
                }
                else{
                    echo "<br>Historical Data is Remaining to Fetch.<br>";
                }
            }
            else{
                $content = date("d-m-Y H:i:s") . " AmzDateIteration Data is not Available."  . "Amazon Order Removal Shipment Cron.";
                $this->logging->write_log($this->cronName, $content, 1, 0);
            }
        }
        $content = date("d-m-Y H:i:s") . " " . "Amazon Order Removal Shipment Cron End.";
        $this->logging->write_log($this->cronName, $content, 3, 0);
    }

}