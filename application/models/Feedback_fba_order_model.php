<?php

/*
 * Generated by Webdimensions
 * www.webdimensions.co.in
 */

class Feedback_fba_order_model extends CI_Model {

    function __construct() {
        parent::__construct();
    }

    /*
     * Get feedback_fba_setting
     */

    function get_feedback_setting() {
        $t = time();
        $last4hours = $t - (1 * 4 * 60 * 60);
        $result = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadedfirstorderdata, s.amazonstorename,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT and s.apiactive = 1 AND a.removed = 0 AND a.enabled = 1 and a.deleted='0'
        ");
        return $result;
    }

    /*
     * get_fba_order_setting
     */

    function get_fba_order_feedback_setting() {
        $t = time();
        $last4hours = $t - (1 * 4 * 60 * 60);
        $result = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadedfirstorderfba, s.amazonstorename,s.importorderdays,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT  and s.apiactive = 1 AND a.removed = 0 AND a.enabled = 1 and a.deleted='0' AND s.hourlimit_orderfba < 6 AND (s.api_fbaorderdate  = 0 OR s.api_fbaorderdate < $last4hours)
        ");

        return $result;
    }
    /*
     * get_fba_order_setting
     */

    function get_fba_order_feedback_setting_historical() {
        $result = $this->db->query("
	SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadedfirstorderfba, s.amazonstorename,s.importorderdays,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m
	WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT  and s.apiactive = 1 AND a.removed = 0 AND a.enabled = 1 and a.deleted='0' ");
        return $result;
    }


    # This function use to get all inbound shipment data
    function get_inbound_shipment_historical_data()
    {
        $shipmentData = $this->db->query("
        SELECT a.ID_ACCOUNT, s.mws_sellerid, s.mws_authtoken, s.loadedfirstorderfba, s.amazonstorename,s.importorderdays,
        d.marketplace_id, d.access_key, d.secret_key, m.marketplace_id, m.host, m.id
        FROM accounts AS a, feedback_settings AS s, dev_accounts AS d, marketplaces AS m  
        WHERE m.id= d.marketplace_id AND s.mws_marketplaceid = m.id AND s.ID_ACCOUNT = a.ID_ACCOUNT  and s.apiactive = 1 AND a.removed = 0 AND a.enabled = 1 and a.deleted='0'
      ");
        return $shipmentData;
    }


    function get_product_image() {
        $result = $this->db->query("
	SELECT o.*, s.amazonstorename, s.mws_sellerid,
	n.amazonasin,n.ID_ASIN,s.feedbackfromemailaddress,s.totalexclusions, a.ID_REFERRED_BY, s.mws_marketplaceid,s.emailsentbillingperiod
	FROM (feedback_orders as o, accounts as a)
		LEFT JOIN feedback_settings as s ON (s.ID_ACCOUNT = a.ID_ACCOUNT)
		LEFT JOIN feedback_asin as n ON (n.sku = o.sku AND n.ID_ACCOUNT = a.ID_ACCOUNT)
	WHERE o.ID_ACCOUNT = a.ID_ACCOUNT and   a.enabled = 1 and a.deleted='0' ");
        return $result;
    }

    /*
     * Get feedback_setting by ID_ACCOUNT
     */

    function add_feedback_order($params) {
        $this->db->insert('feedback_orders', $params);
        return $this->db->insert_id();
    }

    /*
     * Get feedback_order count by ID_ACCOUNT
     */

    function custom_count_feedback_order($params) {
        $countTotal = $this->db->get_where('feedback_orders', $params);
        return $countTotal->num_rows();
    }


    function  getamzdateiterationdata($status,$accountid)
    {
        return $this->db->select("*")->where($status,'0')->where("accountId",$accountid)->order_by("logId")->get("amzdateiteration")->row_array();
    }

    function updateamzdateiteration($data,$id)
    {
        $this->db->where('logId',$id)->update('amzdateiteration',$data);
    }

}