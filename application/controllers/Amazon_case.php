<?php /* Generated by Webdimensions  www.webdimensions.co.in */

class Amazon_case extends My_controller{
    function __construct(){
        parent::__construct();
        $this->load->library('amazon');
        $this->load->model("user_model");
        $this->load->model("reimbursement_model");
        $this->load->model("feedback_setting_model");
        $this->load->model("subscription_model");
        $this->load->model("Order_removal_model");
        $paymentinfo = $this->subscription_model->get_payment_info_by_accountid($this->user->info->ID_ACCOUNT);
        if (($this->user->info->feedbackfromemailaddress=='' || $this->user->info->mws_sellerid=='' ||  $this->user->info->mws_authtoken=='' || empty($paymentinfo['ID_PAYMENT'])) && !$this->user->info->admin)
            redirect(site_url("feedback_setting/setup"));

        if (!$this->user->loggedin) {
            redirect('login');
        }
    }
    public function index(){
        error_reporting(0);
        $this->template->loadData("activeLink", array("amazon_case" => array("amazon_case" => 1)));
        $this->template->loadContent("amazon_case/index.php");
    }


    public function orderTrackingInfo()
    {
        $this->template->loadData("activeLink", array("amazon_case" => array("order_tracking_info" => 1)));
        $userDetails = $this->user_model->getAllUsers();
        $data = array();
        if (isset($this->user->info->admin) && !empty($this->user->info->admin))
        {
            foreach ($userDetails as $key => $value)
            {
                $account_id = $value['ID_ACCOUNT'];
                $caseDetails = $this->reimbursement_model->getAllgeneratedCasesByUserId($value['ID_ACCOUNT']);

                if($caseDetails)
                {
                    $data['userInfo'][$key] = $value;
                }
            }
            $data['orderTrackingInfo'] = $this->reimbursement_model->orderTrackingInfoadmin($account_id);
        }
        $this->template->loadContent('amazon_case/orderTrackingInfo.php',$data);
    }

    public function AjaxTrackingInfo()
    {
        if (isset($this->user->info->admin) && !empty($this->user->info->admin))
        {
            $start_date = date('Y-m-d 00:00:01', strtotime($_POST['start_date']));
            $date = new DateTime($start_date);
            $start_date = $date->getTimestamp();

            $end_date = date('Y-m-d 23:59:59', strtotime($_POST['end_date']));
            $date = new DateTime($end_date);
            $end_date = $date->getTimestamp();

            $account_id  = $_POST['account_id'];

            $userDetails = $this->user_model->getAllUsers();
            foreach ($userDetails as $key => $value)
            {
                $account_id = $value['ID_ACCOUNT'];
            }
            $data['orderTrackingInfo'] = $this->reimbursement_model->orderTrackingInfo($account_id, $start_date, $end_date);
        }
        else
        {
            $start_date = date('Y-m-d 00:00:00',strtotime($_POST['start_date']));
            $end_date = date('Y-m-d 23:59:59',strtotime($_POST['end_date']));
            $data['start_date'] = $start_date;
            $data['end_date'] = $end_date;
        }

        $this->load->view("amazon_case/AjaxOrderTrackingInfo.php",$data);
    }

    public function show_detail()
    {
        error_reporting(0);
        $users  = $this->user_model->getAllActiveUsers();
        $user[] = $userCase = array();
        foreach ($users as $key=>$user)
        {
            $userCase['data'][$key]= $user;
            $userCase[$key]['details'] = $this->reimbursement_model->getUserwisegeneratePaymentDetails($user['ID_ACCOUNT']);
            $data = $this->Order_removal_model->getTotalOrderRemovalCaseCount( $user['ID_ACCOUNT'] );
            $userCase['data'][$key]['totalCases'] = count($userCase[$key]['details']) + $data;
            $userCase['data'][$key]['caseDetails'] = $this->getdata($user['ID_ACCOUNT']);
        }
        echo json_encode($userCase); exit;
    }

    public function AorGeneratecase($accountId , $case_type)
    {
        $userCases = array();
        $settings = $this->feedback_setting_model->get_feedback_setting($accountId);
        $result = $this->reimbursement_model->AorCaseGeneration($accountId, $case_type);

        foreach ($result as $key=>$record)
        {
            $userCases['AorcaseDetails'][$key] = $record;
        }

        $userCases['emailAddress'] = $settings['link_amazon_email'];
        $userCases['pass'] = $settings['link_amazon_password'];

        $this->template->loadContent("amazon_case/AmazonAorCaseGeneration.php",$userCases);
    }

    public function GetAorCaseDetail($order_id,$account_id,$case_type)
    {
        $Aorcase = $this->reimbursement_model->getAorDetailsIdType($order_id,$account_id,$case_type);
        foreach($Aorcase as $key => $value)
        {
            $Aorcase = $value;
        }
        echo json_encode($Aorcase); exit;
    }

    public function generateAorCase()
    {
        $flag = 0;
        foreach ($_POST['myCheckboxes'] as $key => $record)
        {
            $data = (explode("/", $record));
            $id = $data[0];
            $account_id = $data[1];
            $type = $data[2];
            $sku = $data[3];
            $AOR_data['case_detail'][$key] = $this->reimbursement_model->getInventoryAor($id, $account_id, $type,$sku);
        }

        if(!empty($AOR_data))
        {
            foreach($AOR_data['case_detail'] as $key => $value)
            {
                foreach($value as $record)
                {
                    $data = array('status' => '2');
                    $this->reimbursement_model->updateAORorderDetailsData($record['sku'],$data);
                    $data = array('case_id' => $_POST['case_id'], 'status' => '1', 'date' => date('Y-m-d H:i:s'));
                    $effectedRows = $this->reimbursement_model->updateAORDetails($record['id'],$record['ID_ACCOUNT'],$record['case_type'], $data);
                    if ($effectedRows > 0)
                    {
                        echo $flag;
                        return true;
                    }
                    else
                    {
                        echo $flag = 0;
                        return false;
                    }
                }
            }
        }
    }

    public function getdata($accountId){
        $case = array();
        $RwfCount = $OnrCount = $CorCount = $flag = 0;
        $usercase = $this->reimbursement_model->getUserwisegeneratePaymentDetails($accountId);
        $RwfCount = $OnrCount = $CorCount =0;
        $aorQuantity = $this->Order_removal_model->getTotalOrderRemovalCaseCount( $accountId );

        foreach ($usercase as $key=>$rescord){
            if($rescord['case_type']=='RWF'){
                $RwfCount++;
                $flag =1;
            }
            if($rescord['case_type']=='ONR'){
                $OnrCount++;
                $flag =1;
            }
            if($rescord['case_type']=='COR'){
                $CorCount++;
                $flag =1;
            }
        }
        if($flag==1){
            $case[0] = array('case_type'=> 'RWF','totCount' =>$RwfCount);
            $case[1] = array('case_type'=> 'ONR','totCount' =>$OnrCount);
            $case[2] = array('case_type'=> 'COR','totCount' =>$CorCount);
            $case[3] = array( 'case_type' => 'AOR', 'totCount' => $aorQuantity );
        }
        else{
            $case[0] = array('case_type'=> 'RWF','totCount' =>$RwfCount);
            $case[1] = array('case_type'=> 'ONR','totCount' =>$OnrCount);
            $case[2] = array('case_type'=> 'COR','totCount' =>$CorCount);
            $case[3] = array( 'case_type' => 'AOR', 'totCount' => $aorQuantity );
        }
        return $case;
    }
    public function generateCasesByIdAndType($accountId,$caseType)
    {
        $settings = $this->feedback_setting_model->get_feedback_setting($accountId);
        $data = array();
        $user['accountId'] = $accountId;
        $user['email']=$settings['link_amazon_email'];
        $user['pass'] = $settings['link_amazon_password'];
        $user['type'] = $caseType;

        if($user['type'] == 'ONR')
        {
            $user['msg_top'] = "Our records indicate that the following Orders were  refunded but not returned with no records of reimbursement. Please check the following units and reimburse where necessary.";
        }
        else if($user['type'] == 'RWF')
        {
            $user['msg_top'] = "When this customer returned orders";
        }
        else if($user['type'] == 'COR')
        {
            $user['msg_top'] = "Our records indicate that the following Orders were  refunded but not returned with no records of reimbursement. Please check the following units and reimburse where necessary.";
        }

        $userCases = $this->reimbursement_model->getCasesByAcountIdAndCaseType($accountId,$caseType);
        foreach ($userCases as $key=>$record)
        {
            $user['orderId'][$key] = $record;
        }
        $this->template->loadContent("amazon_case/orderCaseGenerate.php",$user);
    }

    public function generatedCase(){
        error_reporting(0);
        $details = array();
        $this->template->loadData("activeLink", array("amazon_case" => array("generate_amazon_case" => 1)));
        if (isset($this->user->info->admin) && !empty($this->user->info->admin))
        {
            $userDetails = $this->user_model->getAllUsers();
            foreach($userDetails as $key=>$record)
            {
                $caseDetails = $this->reimbursement_model->getAllgeneratedCasesByUserId($record['ID_ACCOUNT']);
                if($caseDetails)
                {
                    $details[$key]['userInfo'] = $record;
                    $details[$key]['caseDetails'] = $caseDetails ;
                }
            }
        }
        else
        {
            $days=0;
            $details['caseDetails'] =$this->reimbursement_model->getCaseIdStatusByUserId($this->user->info->ID_ACCOUNT,$days);
        }
        $case_change_status = $this->reimbursement_model->get_case_change_status();
        $this->template->loadContent("amazon_case/generatedCaseIndex.php",array('caseRecord'=>$details,'caseStatus'=>$case_change_status));
    }
    public function AjaxgeneratedCase()
    {

        if (isset($this->user->info->admin) && !empty($this->user->info->admin)) {
            $details= array();
            $start_date = $end_date = '';
            if(!empty($_POST)){
               if(!empty($_POST['start_date'])){
                   $start_date = date('Y-m-d 00:00:00',strtotime($_POST['start_date']));
               }
                if(!empty($_POST['end_date'])){
                    $end_date = date('Y-m-d 23:59:59',strtotime($_POST['end_date']));
                }
                if(!empty($_POST['accountId']))
                {
                    $details['caseRecord']['userInfo']= $this->reimbursement_model->getUserDetailsByAccountId($_POST['accountId']);
                    $details['caseRecord']['caseDetails']= $this->reimbursement_model->getAllgeneratedCasesByUserId($_POST['accountId'],$start_date,$end_date);
                }else{
                    $userDetails = $this->user_model->getAllUsers();
                    foreach($userDetails as $key=>$record){
                        $caseDetails = $this->reimbursement_model->getAllgeneratedCasesByUserId($record['ID_ACCOUNT'],$start_date,$end_date);
                        if($caseDetails){
                            $details[$key]['userInfo'] = $record;
                            $details[$key]['caseDetails'] = $caseDetails ;
                        }
                    }
                }
                $this->load->view("amazon_case/AjaxgeneratedCaseIndex.php",array('caseRecord' => $details));
            }
        }
        else {
            $days =$_POST['days'];
            $current = strtotime(date('Y-m-dT23:59:59'));
            if ($days == 1)
                $days_ago = date('Y-m-d h:i:s', strtotime('-5 days', $current));
            else if ($days == 2)
                $days_ago = date('Y-m-d h:i:s', strtotime('-10 days', $current));
            else if ($days == 3)
                $days_ago = date('Y-m-d h:i:s', strtotime('-15 days', $current));
            else if ($days == 4)
                $days_ago = date('Y-m-d h:i:s', strtotime('-20 days', $current));
            else if ($days == 5)
                $days_ago = date('Y-m-d h:i:s', strtotime('-25 days', $current));
            else if ($days == 6)
                $days_ago = date('Y-m-d h:i:s', strtotime('-30 days', $current));
            else
                $days_ago = 0;

            $details['caseDetails'] =$this->reimbursement_model->getCaseIdStatusByUserId($this->user->info->ID_ACCOUNT,$days_ago);
            $this->load->view("amazon_case/AjaxgeneratedCaseIndex.php", array('caseRecord' => $details));
        }
    }
    public function createCaseId(){
        $flag = 0;
        $countChkBox = count($_POST['myCheckboxes']);

        if($countChkBox>0)
        {
            for($count=0;$count<count($_POST['myCheckboxes']);$count++){
                $data = array('status' => '2');
                $this->reimbursement_model->updatePaymentDetailsData($data, $_POST['myCheckboxes'][$count], $_POST['accountId']);
                $data = array('ID_ACCOUNT' => $_POST['accountId'], 'case_id' => $_POST['caseId'], 'order_id' => $_POST['myCheckboxes'][$count], 'date' => date('Y-m-d h:i:s', strtotime($_POST['date'])),'status'=>'1');
                $this->reimbursement_model->updateAmazonCase($data,$_POST['myCheckboxes'][$count],$_POST['accountId']);
                $flag = 1;
        }
            if($flag>0){
                echo $flag;
                return true;
            }else {
                echo $flag = 0;
                return true;
            }
        }
    }

    public function exportCsv(){
        $fileName = $this->user->info->ID_ACCOUNT."_RefundGuardian".date('Y-m-d').".csv";
        $this->load->helper('download');
        if (!file_exists("Downloads")) {
            mkdir("Downloads");
        }
        error_reporting(0);
        $fp = fopen("Downloads/".$fileName,"w");
        if (isset($this->user->info->admin) && !empty($this->user->info->admin)) {
            $head = array("User Name","Product Name", "Case Type", "Order Id", "Case Id", "Field", "Refund Status", "Potential", "Actual");
            fputcsv($fp, $head);
            $write_info = array();
            $userDetails = $this->user_model->getAllUsers();
            foreach($userDetails as $key=>$user){
                $caseDetails = $this->reimbursement_model->getAllgeneratedCasesByUserId($user['ID_ACCOUNT']);
                foreach ($caseDetails as $index=>$case){
                    $write_info['username'] = $user['username'];
                    $write_info['productname'] = $case['productname'];
                    $write_info['case_type'] = $case['case_type'];
                    $write_info['order_id'] = $case['order_id'];
                    $write_info['case_id'] = $case['case_id'];
                    $write_info['date'] = date('Y/m/d', strtotime($case['date']));
                    $write_info['status'] = (($case['status']==1)? 'Pending':(($case['status']==2)?'Success' :''));
                    $write_info['amount'] = "$".abs($case['amount']);
                    $write_info['amount_total'] = (!empty($case['amount_total']))? "$".$case['amount_total'] : '';
                    fputcsv($fp, $write_info);
                }
            }
        }
        else
        {
            $days=0;
            $caseDetails =$this->reimbursement_model->getCaseIdStatusByUserId($this->user->info->ID_ACCOUNT,$days);
            $head = array("Product Name", "Case Type", "Order Id", "Case Id", "Field", "Refund Status", "Potential", "Actual");
            fputcsv($fp, $head);
            $write_info = array();
            foreach ($caseDetails as $key=>$case )
            {
                $write_info['productname'] = $case['productname'];
                $write_info['case_type'] = $case['case_type'];
                $write_info['order_id'] = $case['order_id'];
                $write_info['case_id'] = $case['case_id'];
                $write_info['date'] = date('Y/m/d', strtotime($case['date']));
                $write_info['status'] = (($case['status']==1)? 'Pending':(($case['status']==2)?'Success' :''));
                $write_info['amount'] = "$".abs($case['amount']);
                $write_info['amount_total'] = (!empty($case['amount_total']))? "$".$case['amount_total'] : '';
                fputcsv($fp, $write_info);
            }
        }
        fclose($fp);
        echo $url = base_url().'Downloads/'.$fileName;exit;
    }

    public function edit_case_account($id){
        $result =  $this->reimbursement_model->edit_case_by_account_id($id);
        echo json_encode($result); exit;
    }
    public function updateCaseId()
    {
        $detail = $_POST['case_status_detail'];

        if($detail == 3)
        {
            $id = $_POST['logId'];

            $case_id = $this->reimbursement_model->allRecordDisplay($id);

            foreach($case_id as $key=>$value)
            {
                $insert_data = array(
                    'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                    'case_id'=>$value->case_id,
                    'order_id'=>$value->order_id,
                    'date'=>$value->date,
                    'status'=>$value->status,
                    'Case_Type'=>$value->Case_Type,
                    'deleted'=>$value->deleted,
                    'Reason'=>$value->Reason
                );

                $this->reimbursement_model->add_case_data($insert_data);
            }

            $data = array('case_id' => $_POST['case__Id'], 'date' => $_POST['caseDate'], 'status' => $_POST['case_status_detail'], 'Reason' => $_POST['amazon']);
            $affected_row = $this->reimbursement_model->updateCaseBYId($_POST['logId'], $data);

            if ($affected_row > 0) {
                $this->session->set_flashdata("success", "Case Detail succsssfully Update.");
                redirect('Amazon_case/generatedCase');
            } else {
                redirect('Amazon_case/generatedCase');
            }
        }
        else if($detail == 0)
        {
            $id = $_POST['logId'];
            $case_id = $this->reimbursement_model->allRecordDisplay($id);

            foreach($case_id as $key=>$value)
            {
                $insert_data = array(
                    'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                    'case_id'=>$value->case_id,
                    'order_id'=>$value->order_id,
                    'date'=>$value->date,
                    'status'=>$value->status,
                    'Case_Type'=>$value->Case_Type,
                    'deleted'=>$value->deleted,
                    'Reason'=>$value->Reason
                );

                $this->reimbursement_model->add_case_data($insert_data);
            }

            $data = array('case_id' => NULL, 'date' => $_POST['caseDate'], 'status' => $_POST['case_status_detail'], 'Reason' =>NULL);
            $this->reimbursement_model->updateCaseBYId($_POST['logId'], $data);


            $id = $_POST['logId'];
            $data = array('status'=>'1');
            $affected_row = $this->reimbursement_model->updateCaseStatusToDo($id,$data);

            $data = array('status'=>'1');
            $this->reimbursement_model->updateCaseStatusToDoAOR($id,$data);

            if ($affected_row > 0) {
                $this->session->set_flashdata("success", "Case Detail succsssfully Update.");
                redirect('Amazon_case/generatedCase');
            } else {
                redirect('Amazon_case/generatedCase');
            }
        }
        else
        {

            $id = $_POST['logId'];

            $case_id = $this->reimbursement_model->allRecordDisplay($id);

            foreach($case_id as $key=>$value)
            {
                $insert_data = array(
                    'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                    'case_id'=>$value->case_id,
                    'order_id'=>$value->order_id,
                    'date'=>$value->date,
                    'status'=>$value->status,
                    'Case_Type'=>$value->Case_Type,
                    'deleted'=>$value->deleted,
                    'Reason'=>$value->Reason
                );

                $this->reimbursement_model->add_case_data($insert_data);
            }

            $data = array('case_id' => $_POST['case__Id'], 'date' => $_POST['caseDate'], 'status' => $_POST['case_status_detail'], 'Reason'=>NULL);
            $affected_row = $this->reimbursement_model->updateCaseBYId($_POST['logId'], $data);

            if ($affected_row > 0) {
                $this->session->set_flashdata("success", "Case Detail succsssfully Update.");
                redirect('Amazon_case/generatedCase');
            } else {
                redirect('Amazon_case/generatedCase');
            }
        }
    }

    public function remove_user($ID_ACCOUNT)
    {
        $this->reimbursement_model->delete_case_by_account_id($ID_ACCOUNT);
        $this->session->set_flashdata("danger", "Case Detail succsssfully Deleted.");
        redirect('Amazon_case/index');
    }
    public function user_display_data($id){
        $result = $this->reimbursement_model->getAllgeneratedCasesByUserId_Detail($id);
        echo json_encode($result); exit;
    }

    public function updateCaseStatusUpdate()
    {
       $casestatus = $_POST['txtbox'];

       if($casestatus == 0)
       {
           $caseDetailId = $_POST['selected'];
           $id = explode(',', $caseDetailId);

           $case_id = $this->reimbursement_model->allRecordDisplay($id);

           foreach($case_id as $key=>$value)
           {
               $insert_data = array(
                   'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                   'case_id'=>$value->case_id,
                   'order_id'=>$value->order_id,
                   'date'=>$value->date,
                   'status'=>$value->status,
                   'Case_Type'=>$value->Case_Type,
                   'deleted'=>$value->deleted,
                   'Reason'=>$value->Reason
               );

               $this->reimbursement_model->add_case_data($insert_data);
           }

           $CaseStatus = array('status' => $_POST['txtbox'],'case_id'=>NULL,'Reason'=>NULL);
           $this->reimbursement_model->updateCaseStatus($id,$CaseStatus);
           $data = array('status'=>'1');
           $this->reimbursement_model->updateCaseStatusToDo($id,$data);
           $data = array('status'=>'1');
           $this->reimbursement_model->updateCaseStatusToDoAOR($id,$data);
       }
       else if($casestatus == 3)
       {
           $caseDetailId = $_POST['selected'];
           $id = explode(',', $caseDetailId);

           $case_id = $this->reimbursement_model->allRecordDisplay($id);

           foreach($case_id as $key=>$value)
           {
               $insert_data = array(
                   'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                   'case_id'=>$value->case_id,
                   'order_id'=>$value->order_id,
                   'date'=>$value->date,
                   'status'=>$value->status,
                   'Case_Type'=>$value->Case_Type,
                   'deleted'=>$value->deleted,
                   'Reason'=>$value->Reason
               );

               $this->reimbursement_model->add_case_data($insert_data);
           }

           $CaseStatus = array('status' => $_POST['txtbox'],'Reason'=>NULL);
           $this->reimbursement_model->updateCaseStatus($id,$CaseStatus);

       }
       else
       {
           $caseDetailId = $_POST['selected'];
           $id = explode(',', $caseDetailId);

           $case_id = $this->reimbursement_model->allRecordDisplay($id);

           foreach($case_id as $key=>$value)
           {
               $insert_data = array(
                                    'ID_ACCOUNT'=>$value->ID_ACCOUNT,
                                    'case_id'=>$value->case_id,
                                    'order_id'=>$value->order_id,
                                    'date'=>$value->date,
                                    'status'=>$value->status,
                                    'Case_Type'=>$value->Case_Type,
                                    'deleted'=>$value->deleted,
                                    'Reason'=>$value->Reason
                                   );

              $this->reimbursement_model->add_case_data($insert_data);
           }

           $CaseStatus = array('status' => $_POST['txtbox'],'Reason'=>NULL);
           $this->reimbursement_model->updateCaseStatus($id,$CaseStatus);
       }
       return true;
    }


    public function UserTrackingInfo()
    {
        error_reporting(0);
        $user[] = $userCase = array();
        $result = $this->reimbursement_model->orderTrackingInfo($this->user->info->ID_ACCOUNT);
        echo json_encode($result); exit;
    }

    public function UserTrackingInfoAjax($start_date,$end_date)
    {
        error_reporting(0);
        $user[] = $userCase = array();
        $result = $this->reimbursement_model->orderTrackingInfoUser($this->user->info->ID_ACCOUNT,$start_date,$end_date);
        echo json_encode($result); exit;
    }

}

?>