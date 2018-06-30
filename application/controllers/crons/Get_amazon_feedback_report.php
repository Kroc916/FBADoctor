<?php

/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Get_amazon_feedback_report extends CI_Controller {

    function __construct() {
        parent::__construct();
        if (!$this->input->is_cli_request()) {
            show_404();
        }
        $this->load->library('amazon');
        $this->load->model('amazon_feedback_report_model');
        $this->load->model('feedback_setting_model');
        $this->load->model('amazon_report_request_log_model');
        $this->load->model('amazon_api_log_model');
        $this->load->model('account_model');
        $this->load->model('feedback_asin_model');
        $this->load->model('feedback_history_model');
        $this->load->model('feedback_order_model');
        $this->load->helper('email');
        $this->load->model('User_model');
        $this->load->model("home_model");
        $this->load->library('rat');
        $this->logging = new Rat();
        $this->cronName = "Get Feedback report Cron";
        $this->template->loadData("activeLink", array("get_amazon_feedback_report" => array("general" => 1)));
    }

    function index() {
        $content = date("d-m-Y H:i:s") . " " . "Get Feedback report Cron Start";
        $this->logging->write_log($this->cronName, $content, 1, 0);
        $result = $this->amazon_feedback_report_model->get_feedback_report_setting();
        $this->getorderfromamazon($result);
    }
    function getorderfromamazon($result) {
        global $store, $AMAZON_SERVICE_URL;
        foreach ($result->result() as $row) {
            $feedStatus = 1;
            $ReportRequestId = "";
            $GeneratedReportId = '';
            $customer = $this->User_model->get_user_by_Account_id($row->ID_ACCOUNT);
            $store[$row->amazonstorename]['ID_ACCOUNT'] = $row->ID_ACCOUNT;
            $store[$row->amazonstorename]['merchantId'] = $row->mws_sellerid; //Merchant ID for this store
            $store[$row->amazonstorename]['marketplaceId'] = $row->marketplace_id; //Marketplace ID for this store
            $store[$row->amazonstorename]['keyId'] = $row->access_key; //Access Key ID
            $store[$row->amazonstorename]['secretKey'] = $row->secret_key; //Secret Access Key for this store
            $store[$row->amazonstorename]['MWSAuthToken'] = $row->mws_authtoken; //Secret Access Key for this store
            $AMAZON_SERVICE_URL = $row->host;
            $searchArrayLogRow = array('status !=' => 2, 'reporttype =' => '_GET_SELLER_FEEDBACK_DATA_', 'ID_ACCOUNT =' => $row->ID_ACCOUNT);
            $logRow = $this->amazon_report_request_log_model->get_log_result($searchArrayLogRow);
            if (!empty($logRow->requestid))
                $ReportRequestId = $logRow->requestid;
            if (empty($ReportRequestId)) {
                try {
                    echo 'Getting feedback data.....';
                    // Request the seller feedback data report
                    $objAmazonFeedbackReportRequest = new AmazonReportRequest($row->amazonstorename);
                    $fulldataload = 0;
                    if ($row->loadedfirstfeedbackdata == 1) {
                        echo 'Last 48 hours';
                        $objAmazonFeedbackReportRequest->setTimeLimits('- 48 hours');
                    } else {
                       /* echo 'Last 90 days';
                        $t = time();
                        $currentDate = date("M", $t) . " " . date("j", $t) . ", " . date("Y", $t);
                        $last90days = $t - (89 * 24 * 60 * 60);
                        $startDate = date("M", $last90days) . " " . date("j", $last90days) . ", " . date("Y", $last90days);*/

                        $date = date('Y-m-d H:i:s');
                        $prevousDate =date('Y-m-d',strtotime($date.' -4days '));
                        $startDate = $prevousDate.'00:00:00';
                        $objAmazonFeedbackReportRequest->setTimeLimits($startDate,$date);
                        $fulldataload = 1;
                    }
                    //$objAmazonFeedbackReportRequest->setTimeLimits('Feb 10, 2015',"Feb 18, 2015");
                    $objAmazonFeedbackReportRequest->setReportType("_GET_SELLER_FEEDBACK_DATA_");
                    $objAmazonFeedbackReportRequest->requestReport();
                    $response = $objAmazonFeedbackReportRequest->getResponse();
                    $feedStatus = 0;
                    if (!empty($response['ReportRequestId'])) {
                        $ReportRequestId = $response['ReportRequestId'];
                        $feedStatus = 1;
                        if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_CANCELLED_') {
                            echo 'No Data!!!!!!';
                            $feedStatus = 2;
                        }
                        if ($response['ReportProcessingStatus'] == '_DONE_NO_DATA_' || $response['ReportProcessingStatus'] == '_DONE_' && $row->loadedfirstfeedbackdata = 0) {
                            $fulldataload = 1;
                            $feedStatus = 2;
                            $t = time();
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'api_feedbackreportdate' => $t
                            ));
                        }

                        $t = time();
                        // Insert the report
                        $addReportRequestLogParams = array(
                            'ID_ACCOUNT' => $row->ID_ACCOUNT,
                            'logdate' => $t,
                            'reporttype' => '_GET_SELLER_FEEDBACK_DATA_',
                            'requestid' => $ReportRequestId,
                            'fulldataload' => $fulldataload,
                            'status' => $feedStatus
                        );
                        $this->amazon_report_request_log_model->add_amazon_report_request_log($addReportRequestLogParams);
                        if ($fulldataload == 1) {
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'loadedfirstfeedbackdata' => 1,
                                'hourlimit_feedbackdata' => 'hourlimit_feedbackdata+1'
                            ));
                        }
                    }
                    $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getorderdata2');
                } catch (Exception $ex) {
                    $feedStatus = 0;
                    $content = date("d-m-Y H:i:s") . " " . "Get Feedback report Cron Start". ":RequestReportList " . $ex->getMessage();
                    $this->logging->write_log($this->cronName, $content, 1, 0);
                }
            }
            // Get
            if ($feedStatus == 1 && !empty($ReportRequestId) && empty($GeneratedReportId)) {
//                sleep(2);
                // Get GeneratedReportId
                try {
                    echo 'Getting generated report id';
                    $objAmazonFeedbackReport = new AmazonReportRequestList($row->amazonstorename);
                    //$objAmazonFeedbackReport->setTimeLimits('Feb 10, 2015',"Feb 21, 2015");
                    $objAmazonFeedbackReport->setRequestIds($ReportRequestId);
                    $works = $objAmazonFeedbackReport->fetchRequestList();
                    $response = $objAmazonFeedbackReport->getList();
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
                            // Mark Completed
                            $t = time();
                            $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array(
                                'api_feedbackreportdate' => $t
                            ));
                        }
                    }

                    $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getfeedback3');
                    $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array('hourlimit_feedbackdata' => "hourlimit_feedbackdata + 1"));
                } catch (Exception $ex) {
                    $feedStatus = 0;
                    $content = date("d-m-Y H:i:s") . " " . "Get Feedback report Cron Start". ":RequestReportList " . $ex->getMessage();
                    $this->logging->write_log($this->cronName, $content, 1, 0);
                }
            }
            // Last Step Get the Report
            if ($feedStatus == 1 && !empty($GeneratedReportId)) {
                sleep(2);
                try {
                    echo 'Getting report';
                    $this->amazon_api_log_model->add_amazon_api_log($row->ID_ACCOUNT, 'getfeedback4');
                    $objAmazonFeedbackReport = new AmazonReport($row->amazonstorename);
                    $objAmazonFeedbackReport->setReportId($GeneratedReportId);
                    $objAmazonFeedbackReport->fetchReport();
                    $data = $objAmazonFeedbackReport->returnReport();
                    if (!empty($data)) {
                        $dateLogFull = time();
                        //	print_r($data);
                        $lineCount = 0;
                        $arrfeedId = array();
                        $tmp = explode("\n", $data);
                        foreach ($tmp as $line) {
                            if ($lineCount > 0) {
                                $fieldData = explode("\t", $line);
                                if (empty($fieldData[0]))
                                    continue;

                                $orginalfeedbackdate = addslashes($fieldData[0]);
                                $feedbackdate = strtotime($orginalfeedbackdate);
                                $rating = (int) $fieldData[1];
                                $comments = addslashes($fieldData[2]);
                                $yourresponse = addslashes($fieldData[3]);
                                $arrivedontime = $fieldData[4];
                                if ($arrivedontime == 'Yes')
                                    $arrivedontime = 1;
                                else if ($arrivedontime == 'No')
                                    $arrivedontime = 2;
                                else
                                    $arrivedontime = 0;

                                $itemnotasdescribed = $fieldData[5];
                                if ($itemnotasdescribed == 'Yes')
                                    $itemnotasdescribed = 1;
                                else if ($itemnotasdescribed == 'No')
                                    $itemnotasdescribed = 2;
                                else
                                    $itemnotasdescribed = 0;

                                $customerservice = $fieldData[6];
                                if ($customerservice == 'Yes')
                                    $customerservice = 1;
                                else if ($customerservice == 'No')
                                    $customerservice = 2;
                                else
                                    $customerservice = 0;

                                $orderid = addslashes($fieldData[7]);
                                $rateremail = addslashes($fieldData[8]);
                                $raterrole = addslashes($fieldData[9]);

                                $queryArray['ID_ACCOUNT'] = $row->ID_ACCOUNT;
                                $queryArray['orderid'] = $orderid;
                                $totalRow = $this->feedback_history_model->custom_count_feedback_history($queryArray);
                                if ($totalRow == 0 && !empty($orderid)) {
                                    $t = time();
                                    $notified = 0;
                                    // Negative feedback
                                    if ($rating == 1 || $rating == 2) {
                                        if ($row->set_negative_fb_emailalerts == 1 && !empty($row->set_notification_fbalerts_email) && $row->set_negativenetural_sendtype == 2) {
                                            // Send negative feedback
                                            $subject = 'You have received a Negative feedback on Amazon';
                                            $notified = 1;
                                        }
                                    }
                                    // Netural feedback
                                    if ($rating == 3) {
                                        if ($row->set_netural_fb_emailalerts == 1 && !empty($row->set_notification_fbalerts_email) && $row->set_negativenetural_sendtype == 2) {
                                            $subject = 'You have received a Neutral feedback on Amazon';
                                            $notified = 1;
                                        }
                                    }
                                    if ($notified == 1) {
                                        $this->load->model("home_model");
                                        $detail=array('subject'=>$subject,
                                                      'first_name'=>$customer['first_name'],
                                                      'last_name'=>$customer['last_name'],
                                                      'feedbackdate'=>$orginalfeedbackdate,
                                                      'rating'=>$rating,
                                                      'comments'=>$comments
                                        );
                                        $body = $this->load->view('email_template/feedback_email.php',array('detail'=>$detail),true);
                                        $result = $this->common->send_email($subject, $body,$customer['email']);

                                    }
                                    // Insert into database if it is unique
                                    $feedbackParam = array(
                                        'ID_ACCOUNT' => $row->ID_ACCOUNT,
                                        'rating' => $rating,
                                        'feedbackdate' => $feedbackdate,
                                        'orginalfeedbackdate' => $orginalfeedbackdate,
                                        'comments' => $comments,
                                        'yourresponse' => $yourresponse,
                                        'arrivedontime' => $arrivedontime,
                                        'arrivedontime' => $arrivedontime,
                                        'itemasdescribed' => $itemnotasdescribed,
                                        'customerservice' => $customerservice,
                                        'orderid' => $orderid,
                                        'rateremail' => $rateremail,
                                        'raterrole' => $raterrole,
                                        'lastcheckeddate' => $t,
                                        'notified' => $notified
                                    );
                                    // Update order as having bad feedback
                                    $arrfeedId[]=$this->feedback_history_model->insert_feedback_data($feedbackParam);
                                    $queryArray['ID_ACCOUNT'] = $row->ID_ACCOUNT;
                                    $queryArray['orderid'] = $orderid;
                                    $this->feedback_order_model->custom_where_update_amazon_order($queryArray, array('hasbadfeedback' => "1"));
                                } else {
                                    $t = time();
                                    // Last ratings processed /already
                                    if (!empty($orderid)) {
                                        $queryArray['ID_ACCOUNT'] = $row->ID_ACCOUNT;
                                        $queryArray['orderid'] = $orderid;
                                        $this->feedback_history_model->custom_where_update_amazon_feedback_history($queryArray, array('lastcheckeddate' => $t));
                                    }
                                }
                            } // end iflinecount
                            $lineCount++;
                        } // end foreach
                        // Send Digest
                        // Send each one
                        if (!empty($arrfeedId)) {
                            $strCases = implode(",", $arrfeedId);
                            $content = "Feedback Id's Added :" . $strCases;
                            $this->logging->write_log($this->cronName, $content, 2, $row->ID_ACCOUNT);
                        }
                        if ($logRow->fulldataload == 1) {
                            $t = time();
                            // Give it an extra wiggle room
                            $last90days = $t - (88 * 24 * 60 * 60);
                            $queryArraydata = array(
                                'ID_ACCOUNT = ' => $row->ID_ACCOUNT,
                                'rating <= ' => "3",
                                'feedbackdate >= ' => $last90days,
                                'lastcheckeddate = ' => $dateLogFull,
                            );
                            $this->feedback_history_model->custom_where_update_amazon_feedback_history($queryArraydata, array('hasremoved' => "1"));
                            // Count Negative feedback removed
                            $querytotalResultArray = array(
                                'ID_ACCOUNT = ' => $row->ID_ACCOUNT,
                                'hasremoved = ' => "1"
                            );
                            $totalRow = $this->feedback_history_model->custom_count_feedback_history($querytotalResultArray);
                            if ($totalRow > 0) {
                                $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array('total_negativefeedbackremoved' => $totalRow['total']));
                            }
                        }
                    }
                    $t = time();
                    // Feedback settings date
                    $this->feedback_setting_model->update_feedback_setting($row->ID_ACCOUNT, array('api_feedbackreportdate' => $t, 'hourlimit_feedbackdata' => "5"));
                    // Update the request id
                    $this->amazon_report_request_log_model->custom_where_update_amazon_report_request_log(array('ID_ACCOUNT =' => $row->ID_ACCOUNT, 'requestid = ' => $ReportRequestId), array(
                        'status' => '2'
                    ));
                } catch (Exception $ex) {
                    $feedStatus = 0;
                    $content = date("d-m-Y H:i:s") . " " . "Get Feedback report Cron Start". ":RequestReportList " . $ex->getMessage();
                    $this->logging->write_log($this->cronName, $content, 1, 0);
                }
            }
        }
        $content = date("d-m-Y H:i:s") . " " . "Get Feedback report Cron End.";
        $this->logging->write_log($this->cronName, $content, 3, 0);
    }
    function update_forward_email($orderid) {
        $this->feedback_history_model->updatependingemail($orderid);
    }
}