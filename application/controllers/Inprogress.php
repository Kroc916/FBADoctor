<?php

/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Inprogress extends CI_Controller
{

    function __construct()
    {
        parent::__construct();
        if (!$this->user->loggedin)
            redirect(site_url("login"));
        $this->load->model('subscription_model');
        $paymentinfo = $this->subscription_model->get_payment_info_by_accountid($this->user->info->ID_ACCOUNT);
        if (($this->user->info->feedbackfromemailaddress=='' || $this->user->info->mws_sellerid=='' ||  $this->user->info->mws_authtoken=='' || empty($paymentinfo['ID_PAYMENT'])) && !$this->user->info->admin)
            redirect(site_url("feedback_setting/setup"));
    }

    /*
     * Listing of accounts
     */

    function index()
    {
        $this->template->loadContent("progress/index.php");
    }
}