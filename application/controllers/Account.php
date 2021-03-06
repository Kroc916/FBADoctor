    <?php

/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Account extends My_controller {

    function __construct() {
        parent::__construct();
        if (!$this->user->loggedin)
            redirect(site_url("login"));
        $this->load->model('Account_model');
        $this->load->model('user_model');
        $this->load->model('register_model');
        $this->load->model("account_model");
        $this->load->model('subscription_model');
        $this->load->helper('email');
        $this->load->model("home_model");
        $this->load->model("feedback_setting_model");
        $this->load->model('subscription_model');
        $paymentinfo = $this->subscription_model->get_payment_info_by_accountid($this->user->info->ID_ACCOUNT);
        if (($this->user->info->feedbackfromemailaddress=='' || $this->user->info->mws_sellerid=='' ||  $this->user->info->mws_authtoken=='' || empty($paymentinfo['ID_PAYMENT'])) && !$this->user->info->admin)
            redirect(site_url("feedback_setting/setup"));
        $this->load->library('pagination');
        $this->template->loadData("activeLink", array("admin" => array("account" => 1)));
        $this->template->loadExternal(
                '<script type="text/javascript" src="'
                . base_url() . 'scripts/custom/formvalidation.js" /></script>'
        );
         $this->common->stripe_setting();
    }

    /*
     * Listing of accounts
     */

    function index()
    {
        $data['accounts'] = $this->Account_model->get_all_accounts();
        $this->template->loadContent("account/index.php", array("accounts" => $data['accounts']));
    }

    public function accountEnableDisable($id,$checkedValue)
    {
        $data = array('activate_code'=>'');
        $this->account_model->updateUserActivationCodeByAccountId($id,$data);
        $flag = 0;
        $data = array('enabled' => $checkedValue);
        $updateUserData = array('active' => $checkedValue);
        $rowEffected = $this->account_model->accountEnableDisableByAccountId($id,$data,$updateUserData);
        if($rowEffected>0){
            $flag = 1;
        }else{
            $flag = 0;
        }
        echo $flag;exit;
    }

    public function stripeChargedEnableDisable($id,$checkedValue){
        $stripecharge = array('stripe_charge'=>$checkedValue);
        $updatedValue = $this->Reimbursement_model->updateStripePaymentFailUser($id,$stripecharge);
        echo $updatedValue;exit;
    }

    /*
     * Adding a new account By Admin
     */

    public function addAccountByAdmin()
	{
        $userid = 0;
        if(!empty($_POST))
		{
            $fail = "";
            $storename = $this->common->nohtml($this->input->post("storename", true));
            $email = $this->input->post("email", true);
            $first_name = $this->common->nohtml($this->input->post("first_name", true));
            $last_name = $this->common->nohtml($this->input->post("last_name", true));
            $pass = $this->common->nohtml($this->input->post("password", true));
            $pass2 = $this->common->nohtml($this->input->post("password2", true));
            $captcha = $this->input->post("captcha", true);
            $username = $this->common->nohtml($this->input->post("username", true));
		
			if (strlen($username) < 3)
                $fail = "error_31";
            if (!preg_match("/^[a-z0-9_]+$/i", $username)) {
                $fail = lang("error_15");
            }
            if (!$this->register_model->check_username_is_free($username)) {
                $fail = lang("error_16");
            }
            if ($pass != $pass2)
                $fail = lang("error_22");
            if (strlen($pass) <= 5) {
                $fail = lang("error_17");
            }
            if (strlen($first_name) > 25) {
                $fail = lang("error_56");
            }
            if (strlen($last_name) > 30) {
                $fail = lang("error_57");
            }
            if (empty($first_name) || empty($last_name)) {
                $fail = lang("error_58");
            }
            if (empty($email)) {
                $fail = lang("error_18");
            }
            if (!valid_email($email)) {
                $fail = lang("error_19");
            }
            if (!$this->register_model->checkEmailIsFree($email)) 
			{
                $fail = lang("error_20");
                echo $fail; exit;
            }
            if (empty($fail)) {
                $activate_code = md5(rand(1, 10000000000) . "fhsf" . rand(1, 100000));
                $password = $pass;
                $pass = $this->common->encrypt($pass);
                $autorenew = $perm_support = $perm_feedback = $affID = 1;
                $active = $perm_api = $signup = $billingaccountid = $parent = $group = 0;
                $datecreated = time();
                $accounid = $this->account_model->add_account(array(
                        "company" => $storename,"autorenew" => $autorenew,"ID_PARENT" => $parent,"billingaccountid" => $billingaccountid,
                        "ID_GROUP" => $group,"datecreated" => $datecreated,"address" => '',"city" => '',"region" => '',"zip" => '',"country" => '',
                        "phone" => '',"pending_email" => '',"signupid" => $signup,"perm_support" => $perm_support,"perm_api" => $perm_api,
                        "perm_feedback" => $perm_feedback,"loginsent" => 1,"ID_REFERRED_BY" => $affID));
                $userid = $this->register_model->add_user(array(
                        "ID_ACCOUNT" => $accounid,"username" => $username,"email" => $email,"first_name" => $first_name,"last_name" => $last_name,
                        "password" => $pass,"user_role" => 2,"IP" => $_SERVER['REMOTE_ADDR'],"joined" => time(),"joined_date" => date("n-Y"),
                        "active" => $active,"activate_code" => $activate_code));
                $this->feedback_setting_model->add_feedback_setting(array(
                        "ID_ACCOUNT" => $accounid,"amazonstorename" => $storename,"set_secondfollowupemail" => 1,"feedbackfromemailaddress" => $email,"mws_marketplaceid" => 1,'apiactive' => 1));
                $this->feedback_setting_model->add_historical_cron_settings(array(
                    "ID_ACCOUNT" => $accounid
                ));
                // Check for any default user groups
                $default_groups = $this->user_model->get_default_groups();
                foreach ($default_groups->result() as $r) {
                    $this->user_model->add_user_to_group($userid, $r->ID);
                }
                $subject = "Activation Email";
                $message = '<!DOCTYPE HTML>
                        <html>
                            <head>
                                <style>
                                     .btn-primary
                                     {
                                            background-color:  #3498db ;
                                            border: none;
                                            color: white;
                                            padding: 15px 32px;
                                            text-align: center;
                                            text-decoration: none;
                                            display: inline-block;
                                            font-size: 20px;
                                            margin: 4px 2px;
                                            cursor: pointer;
                                            border-radius: 5px;
                                     }                               
                                </style>
                                <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
                                <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"/>
                                <!-- <link href="' .base_url().'images/favicon.png" rel="icon" type="image/x-icon"/> -->
                                <title>FBADoctor</title>
                               
                            </head>
                            <body bgcolor="#CCCCCC" color:#999;>
                                <table width="600" border="0" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" align="center" style="font-family:Arial, Helvetica, sans-serif;">
                                    <tr>
                                        <td align="center"><br>
                                            <h3>FBADoctor</h3><br><br>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                            <p style="color:#000000; padding-left: 25px; margin-top:70px;">Hello <strong>'.$first_name .' '.$last_name.'</strong>, Your almost finished!</p>
                                      </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                           <p style="color:#000000; padding-left: 25px; margin-top:20px;">We just need to verfiy your email address to complete your FBADoctor Signup.</p>
                                         </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff" align="center">
                                            <a href="'.site_url("register/activate_account/" . $activate_code ."/" . $username).'" class="btn btn-primary" style="margin-top:50px;">Verify Your Email</a>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                               <p style="color:#000000; padding-left: 25px;padding-top: 20px; margin-top:70px;">Please note that link will expire in 5 days.</p>
                                     </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                            <p style="color:#000000; padding-left: 25px; margin-top:20px;">After activation you can login by UserName or Email address : </p><br>
                                            <p style="color:#000000; padding-left: 25px; margin-top:20px;">
                                                <b>User Name :</b>'.$username.'<br>
                                                <b>Email     :</b>'.$email.'<br>
                                                <b>Password  :</b>'.$password.'
                                            </p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                               <p style="color:#000000; padding-left: 25px; margin-top:10px;">If you have not signed up for FBADoctor, Please ignore this email.</p>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td bgcolor="#ffffff">
                                          <p style="color:#000000; padding-left: 25px; margin-top:25px;">-FBADoctor Team</p>
                                         </td>
                                    </tr>
                                    <tr>
                                         <td align="center"><p style="font-size:12px; color:#000000; line-height:20px; margin-top:50px;"><a style="color:#000000;" href="support@FBADoctor.com">support@FBADoctor.com</a> | <a style="color:#000000;" href="http://FBADoctor.com">www.FBADoctor.com</a> <br>
                                            Copyright 2017 FBADoctor - All Rights Reserved.</p>
                                        </td>
                                    </tr>
                                </table>
                            </body>
                        </html>';

                $result = $this->common->send_email($subject, $message, $email);


                if ($result == 1){
                    $success = lang("success_33");
                }
                echo $userid; exit;
            }
        }
    }
    /*
     * Adding a new account
     */
    function add_account() {
        if (isset($_POST['save'])) {
            $storename = $this->common->nohtml($this->input->post("company", true));
            $email = $this->input->post("email", true);
            $first_name = $this->common->nohtml($this->input->post("first_name", true));
            $last_name = $this->common->nohtml($this->input->post("last_name", true));
            $pass = $this->common->nohtml($this->input->post("password", true));
            $pass2 = $this->common->nohtml($this->input->post("password2", true));
            $username = $this->common->nohtml($this->input->post("username", true));
            if (empty($storename))
                $this->template->error(lang("error_77"));
            if (strlen($username) < 3)
                $this->template->error(lang("error_14"));
            if (!preg_match("/^[a-z0-9_]+$/i", $username)) {
                $this->template->error(lang("error_15"));
            }
            if (!$this->register_model->check_username_is_free($username)) {
                $this->template->error(lang("error_16"));
            }
            if (!preg_match("/^[a-z0-9_]+$/i", $username)) {
                $this->template->error(lang("error_15"));
            }
            if ($pass != $pass2)
                $this->template->error(lang("error_22"));
            if (strlen($pass) <= 5) {
                $this->template->error(lang("error_17"));
            }
            if (empty($email)) {
                $this->template->error(lang("error_18"));
            }
            if (!valid_email($email)) {
                $this->template->error(lang("error_19"));
            }
            if (!$this->register_model->checkEmailIsFree($email)) 
            {
                $fail = lang("error_20");
            }
            if (empty($first_name) || empty($last_name)) {
                $this->template->error(lang("error_58"));
            }
            if (!preg_match("/^[0-9]+$/", $this->input->post('phone')))
                $this->template->error(lang("error_80"));
            $password = $pass;
            $pass = $this->common->encrypt($pass);
            $datecreated = time();
            $active = 1;
            $activate_code = "";
            if ($this->settings->info->activate_account) {
                $active = 0;
                $activate_code = md5(rand(1, 10000000000) . "fhsf" . rand(1, 100000));
                // Send email
                $subject = "User Registered, Activation Link";
                $message = "<html>
                            <head>User Account Activation Link</head>
                                <body>
                                    <h3>Hello<strong>".$username."</strong>,</h3><br>
                                    <h3>Welcome to FBADoctor</h3><br>
                                    <p>You are being added to our site <a href='"."#"."'>RobinHoodRetunrs</a></p><br>
                                    <p>Please visit our site and Activate your account</p>  <a href='". site_url("register/activate_account/" . $activate_code ."/" . $username)."'>Actication Link</a><br>
                                    <p>After activation you can login by UserName or Email</p><br>
                                    <strong>User Name :</strong>".$username."<br>
                                    <strong>Email     :</strong>".$email."<br>
                                    <strong>Password  :</strong>".$password."<br>
                                </body>
                            </html>";
                $result = $this->common->send_email($subject, $message, $email);
                if (!empty($result))
                    $success = lang("success_48");
            }

            $params = array(
                'company' => $this->input->post('company'),
                'address' => $this->input->post('address'),
                'city' => $this->input->post('city'),
                'region' => $this->input->post('state'),
                'zip' => $this->input->post('zip'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
                'loginsent' => $this->input->post('loginsent'),
                'paybytype' => $this->input->post('paybytype'),
                'perm_feedback' => $this->input->post('perm_feedback'),
                'perm_support' => $this->input->post('perm_support'),
                'allowoverage' => $this->input->post('allowoverage'),
                'nocreditcardallowed' => $this->input->post('nocreditcardallowed'),
                "datecreated" => $datecreated
            );
            $account_id = $this->Account_model->add_account($params);
            if (!empty($account_id)) {
                $userid = $this->register_model->add_user(array(
                    "ID_ACCOUNT" => $account_id,
                    "username" => $username,
                    "email" => $email,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "password" => $pass,
                    "user_role" => 2,
                    "IP" => $_SERVER['REMOTE_ADDR'],
                    "joined" => time(),
                    "joined_date" => date("n-Y"),
                    "active" => $active,
                    "activate_code" => $activate_code
                        )
                );
                $this->load->model('feedback_setting_model');
                $this->feedback_setting_model->add_feedback_setting(array(
                    "ID_ACCOUNT" => $account_id,
                    "amazonstorename" => $storename,
                    "set_secondfollowupemail" => 1
                        )
                );
            }
            $success .=lang("success_45");
            $this->session->set_flashdata("globalmsg", $success);
            redirect('account/index');
        }
    }

    /*
     * listing of Edit a account
     */

    public function edit_account($id) {
        $account = $this->Account_model->get_account($id);
        $user = $this->user_model->get_user_by_Account_id($id);
         $this->template->loadContent("account/edit_account.php", array(
            "account" => $account,
            "user" => $user,
                )
        );
    }

    /*
     * update a account
     */

    function update_account($ID_ACCOUNT) {
        $account = $this->Account_model->get_account($ID_ACCOUNT);
        if (isset($_POST['update'])) {
            $companyname = $this->input->post('company');
            if (empty($companyname))
                $this->template->error(lang("error_77"));
            if (!preg_match("/^[0-9]+$/", $this->input->post('phone')))
                $this->template->error(lang("error_80"));
            $params = array(
                'company' => $companyname,
                'address' => $this->input->post('address'),
                'city' => $this->input->post('city'),
                'region' => $this->input->post('state'),
                'zip' => $this->input->post('zip'),
                'country' => $this->input->post('country'),
                'phone' => $this->input->post('phone'),
            );
            $this->Account_model->update_account($ID_ACCOUNT, $params);
            $this->session->set_flashdata("globalmsg", lang("success_46"));
            redirect('account/index');
        }
    }

    # This function use to soft delete user account
    function remove_account($ID_ACCOUNT)
    {
        $account = $this->Account_model->get_account($ID_ACCOUNT);
        $paymentinfo = $this->subscription_model->get_payment_info_by_accountid($ID_ACCOUNT);

        if (isset($account['ID_ACCOUNT']))
        {
            $this->Account_model->delete_account($ID_ACCOUNT);
            try
            {
                $cu = \Stripe\Customer::retrieve($paymentinfo['customerid']);
                $cu->delete();
            }
            catch(Exception $e)
            {
            }
            redirect('account/index');
        }
        else
        {
            $this->session->set_flashdata("globalmsg", lang("error_76"));
        }
    }

    /*
     * Serach account by name
     */

    public function search_account() {

        $search = $this->common->nohtml($this->input->post("search"));
        $option = intval($this->input->post("option"));
        if ($option == 0) {
            $members = $this->Account_model->search_by_account($search);
        } elseif ($option == 1) {
            $members = $this->Account_model->search_by_city($search);
        }
        $this->template->loadContent("account/search_account.php", array(
            "members" => $members,
                )
        );
    }

    function edit_account_penalty($id)
    {
        $result =  $this->Account_model->get_account_charge($id);
        echo json_encode($result); exit;
    }

    public function updatePenalty()
    {

        $data = array('charges'=>$_POST['penalty']);
        $affected_row = $this->Account_model->updateAccountId($_POST['account_Id'],$data);
        if($affected_row > 0)
        {
            $this->session->set_flashdata("globalmsg", "Charge Add Successfully");
            redirect('account/index');
        }
        else
        {
            redirect('account/index');
        }
    }

    public function linkAmaoznUserEnableDisable($id,$checkedValue)
    {
        $flag = 0;
        $data = array('LinkAmazonEnabledisable' => $checkedValue);
        $rowEffected = $this->account_model->accountEnableDisableByAccountId($id,$data);
        if($rowEffected>0){
            $flag = 1;
        }else{
            $flag = 0;
        }
        echo $flag;exit;
    }

}
