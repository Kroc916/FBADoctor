<?php /* Generated by Webdimensions  www.webdimensions.co.in */

class Get_amazon_finance_refund_report extends CI_Controller {

	function __construct() {
		parent::__construct();
		if (!$this->input->is_cli_request()) {
			show_404();
		}
		$this->load->library( 'Amazonfinancemws' );
		$this->load->model( 'amazon_feedback_report_model' );
		$this->load->model( 'feedback_setting_model' );
		$this->load->model( 'amazon_report_request_log_model' );
		$this->load->model( 'amazon_finance_refund_model' );
		$this->load->model( 'amazon_api_log_model' );
		$this->load->model( 'account_model' );
		$this->load->model( 'feedback_asin_model' );
		$this->load->model( 'feedback_history_model' );
		$this->load->model( 'feedback_order_model' );
		$this->load->model( 'feedback_fba_order_model' );
		$this->load->model( 'Customer_shipment_sales_model' );
		$this->load->helper( 'email' );
		$this->load->model( 'User_model' );
		$this->load->library( 'rat' );
		$this->logging  = new Rat();
		$this->cronName = "Get Finance Refund report Cron";
		$this->template->loadData( "activeLink", array( "get_amazon_feedback_report" => array( "general" => 1 ) ) );
	}

	function index() {
		$content = date( "d-m-Y H:i:s" ) . " " . "Get Finance Refund report Cron Start";
		$this->logging->write_log( $this->cronName, $content, 1, 0 );
		$result = $this->amazon_feedback_report_model->get_finance_report_setting();
		$this->getrefundeventsamazon( $result );
	}

	public function getrefundeventsamazon( $result ) {
		global $store, $AMAZON_SERVICE_URL;
		foreach ( $result->result_array() as $userAccountDetailArray ) {
			$t                  = time();
			$amzdateiteration   = $this->feedback_fba_order_model->getamzdateiterationdata( 'FinanceStatus', $userAccountDetailArray['ID_ACCOUNT'] );
			$AMAZON_SERVICE_URL = $userAccountDetailArray['host'] . '/Finances/2015-05-01';
			if ( empty( $amzdateiteration ) ) {
				$addReportRequestLogParams = array(
					'ID_ACCOUNT' => $userAccountDetailArray['ID_ACCOUNT'],
					'logdate'    => $t,
					'reporttype' => 'Amazon Finance Refund Report',
					'requestid'  => '',
					'status'     => ''
				);
				$this->amazon_report_request_log_model->add_amazon_report_request_log( $addReportRequestLogParams );
				$config = array(
					'ServiceURL'    => $AMAZON_SERVICE_URL,
					'ProxyHost'     => null,
					'ProxyPort'     => - 1,
					'ProxyUsername' => null,
					'ProxyPassword' => null,
					'MaxErrorRetry' => 3,
				);

				$service = new MWSFinancesService_Client( $userAccountDetailArray['access_key'], $userAccountDetailArray['secret_key'], $userAccountDetailArray['amazonstorename'], 'V1', $config );
				$endDate = date( 'Y-m-d 00:00:00' );
				$startDate               = date( 'Y-m-d 23:59:59', strtotime( $endDate . ' - 4 days' ) );

				$financialEventRequest = new MWSFinancesService_Model_ListFinancialEventsRequest();
				$financialEventRequest->setSellerId( $userAccountDetailArray['mws_sellerid'] );
				$financialEventRequest->setMWSAuthToken( $userAccountDetailArray['mws_authtoken'] );
				$financialEventRequest->setPostedAfter( str_replace( ' ', 'T', $startDate . 'Z' ) );
				$financialEventRequest->setPostedBefore( str_replace( ' ', 'T', $endDate . 'Z' ) );

				$financialEventByNextTokenRequest = new MWSFinancesService_Model_ListFinancialEventsByNextTokenRequest();
				$financialEventByNextTokenRequest->setSellerId( $userAccountDetailArray['mws_sellerid'] );
				$financialEventByNextTokenRequest->setMWSAuthToken( $userAccountDetailArray['mws_authtoken'] );
				$arrorderId = array();
				$financialEventResponse = $this->invokeListFinancialEvents( $service, $financialEventRequest );
				if ( ! empty( $financialEventResponse->ListFinancialEventsResult ) ) {
					$financialEventRefundResult = $financialEventResponse->ListFinancialEventsResult->FinancialEvents->RefundEventList;
					if ( isset($financialEventRefundResult->ShipmentEvent )) {
						$arrorderId = $this->insertFinanceRefundRecord( $financialEventRefundResult, $userAccountDetailArray['ID_ACCOUNT'] );
						if ( ! empty( $arrorderId ) ) {
							$strCases = implode( ",", $arrorderId );
							$content  = "Finance Refund Id's Added :" . $strCases;
							$this->logging->write_log( $this->cronName, $content, 2, $userAccountDetailArray['ID_ACCOUNT'] );
						}
					}
					$nextToken = (isset($financialEventResponse->ListFinancialEventsResult->NextToken) ? $financialEventResponse->ListFinancialEventsResult->NextToken : '');
					while ( ! empty( $nextToken ) ) {
						echo "<pre/ >";
						print_r( $nextToken );
						$financialEventByNextTokenRequest->setNextToken( $nextToken );
						$financialEventByNextTokenResponse = $this->invokeListFinancialEventsByNextToken( $service, $financialEventByNextTokenRequest );
						$nextToken                         = ( isset( $financialEventByNextTokenResponse->ListFinancialEventsByNextTokenResult->NextToken ) ? $financialEventByNextTokenResponse->ListFinancialEventsByNextTokenResult->NextToken : '' );
						if ( isset( $financialEventByNextTokenResponse->ListFinancialEventsByNextTokenResult->FinancialEvents->RefundEventList->ShipmentEvent ) ) {
							$financialEventByNextTokenResult = $financialEventByNextTokenResponse->ListFinancialEventsByNextTokenResult->FinancialEvents->RefundEventList;
							if ( ! empty( $financialEventByNextTokenResult ) ) {
								$arrorderId = $this->insertFinanceRefundRecord( $financialEventByNextTokenResult, $userAccountDetailArray['ID_ACCOUNT'] );
							}
						}
						if ( ! empty( $arrorderId ) ) {
							$strCases = implode( ",", $arrorderId );
							$content  = "Finance Refund Id's Added :" . $strCases;
							$this->logging->write_log( $this->cronName, $content, 2, $userAccountDetailArray['ID_ACCOUNT'] );
						}
					}
					$this->feedback_setting_model->update_feedback_setting( $userAccountDetailArray['ID_ACCOUNT'], array(
						'loadfirstfinancereport' => '1',
						'hourlimit_returnorder'  => "hourlimit_orderdata+1",

					) );
					$amzstatus = array( 'FinanceStatus' => '1' );
					$this->feedback_fba_order_model->updateamzdateiteration( $amzstatus, $amzdateiteration['logId'] );
				}
			}
		}
		$content = date( "d-m-Y H:i:s" ) . " " . "Get Finance Refund report Cron End.";
		$this->logging->write_log( $this->cronName, $content, 3, 0 );
	}

	public function invokeListFinancialEvents( MWSFinancesService_Interface $service, $request ) {
		try {
			$response = $service->ListFinancialEvents( $request );
			$dom      = new DOMDocument();
			$dom->loadXML( $response->toXML() );
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput       = true;
			ini_set('memory_limit', '-1');
			return json_decode( json_encode( simplexml_load_string( $dom->saveXML() ) ) );

		} catch ( MWSFinancesService_Exception $ex ) {
			echo( "Caught Exception: " . $ex->getMessage() . "\n" );
			echo( "Response Status Code: " . $ex->getStatusCode() . "\n" );
			echo( "Error Code: " . $ex->getErrorCode() . "\n" );
			echo( "Error Type: " . $ex->getErrorType() . "\n" );
			echo( "Request ID: " . $ex->getRequestId() . "\n" );
			echo( "XML: " . $ex->getXML() . "\n" );
			echo( "ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n" );
		}
	}

	public function invokeListFinancialEventsByNextToken( MWSFinancesService_Interface $service, $request ) {
		try {
			$response = $service->ListFinancialEventsByNextToken( $request );
			$dom      = new DOMDocument();
			$dom->loadXML( $response->toXML() );
			$dom->preserveWhiteSpace = false;
			$dom->formatOutput       = true;
			ini_set('memory_limit', '-1');
			return json_decode( json_encode( simplexml_load_string( $dom->saveXML() ) ) );

		} catch ( MWSFinancesService_Exception $ex ) {
			echo( "Caught Exception: " . $ex->getMessage() . "\n" );
			echo( "Response Status Code: " . $ex->getStatusCode() . "\n" );
			echo( "Error Code: " . $ex->getErrorCode() . "\n" );
			echo( "Error Type: " . $ex->getErrorType() . "\n" );
			echo( "Request ID: " . $ex->getRequestId() . "\n" );
			echo( "XML: " . $ex->getXML() . "\n" );
			echo( "ResponseHeaderMetadata: " . $ex->getResponseHeaderMetadata() . "\n" );
		}
	}

	public function insertFinanceRefundRecord( $financeRefundRecord, $accountId ) {
		$arrorderId = array();
		if ( $financeRefundRecord->ShipmentEvent ) {
			$RefundRecord = $financeRefundRecord->ShipmentEvent;
			if ( empty( $RefundRecord->AmazonOrderId ) ) {
				foreach ( $RefundRecord as $recordArray ) {
					if ( ! empty( $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList ) ) {
						if ( ! empty( $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType ) ) {
							if ( $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Principal' || $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Goodwill' ) {
								$charge_type   = $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType;
								$charge_amount = $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeAmount->CurrencyAmount;
							}
							if ( $charge_type != '' && $charge_amount != 0 ) {
								$orderDetails    = array(
									'ID_ACCOUNT'            => $accountId,
									'order_id'              => $recordArray->AmazonOrderId,
									'charge_type'           => $charge_type,
									'amount'                => $charge_amount,
									'OrderAdjustmentItemId' => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->OrderAdjustmentItemId,
									'quantity_shipped'      => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->QuantityShipped,
									'seller_sku'            => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU,
									'date'                  => $recordArray->PostedDate,
								);
								$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $recordArray->AmazonOrderId, $recordArray->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU, $charge_type );
								echo "<pre />";
								print_r( $orderDetails );
								echo "<pre />";
								print_r( $checkOrderExist );
								if ( ! empty( $checkOrderExist ) ) {
									$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
								} else {
									$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
								}
							}
						} else {
							$charge_type = $charge_amount = '';
							foreach ( $recordArray->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent as $charge_list ) {
								if ( $charge_list->ChargeType == 'Principal' || $charge_list->ChargeType == 'Goodwill' ) {
									$charge_type   = $charge_list->ChargeType;
									$charge_amount = $charge_list->ChargeAmount->CurrencyAmount;
								}
							}
							if ( $charge_type != '' && $charge_amount != 0 ) {
								$orderDetails    = array(
									'ID_ACCOUNT'            => $accountId,
									'order_id'              => $recordArray->AmazonOrderId,
									'charge_type'           => $charge_type,
									'amount'                => $charge_amount,
									'OrderAdjustmentItemId' => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->OrderAdjustmentItemId,
									'quantity_shipped'      => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->QuantityShipped,
									'seller_sku'            => $recordArray->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU,
									'date'                  => $recordArray->PostedDate,
								);
								$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $recordArray->AmazonOrderId, $recordArray->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU, $charge_type );
								echo "<pre />";
								print_r( $orderDetails );
								echo "<pre />";
								print_r( $checkOrderExist );
								if ( ! empty( $checkOrderExist ) ) {
									$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
								} else {
									$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
								}
							}
						}
					} else {
						$OrderAdjItemId = $QtyShipped = $OrderAdjItemId = $OrderAdjItemId = '';
						$charge_type    = $charge_amount = '';
						foreach ( $recordArray->ShipmentItemAdjustmentList->ShipmentItem as $itemadjustment_list ) {
							if ( ! empty( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType ) ) {
								if ( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Principal' || $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Goodwill' ) {
									$charge_type   = $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType;
									$charge_amount = $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeAmount->CurrencyAmount;
								}
							} else {
								foreach ( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent as $charge_list ) {
									if ( $charge_list->ChargeType == 'Principal' || $charge_list->ChargeType == 'Goodwill' ) {
										$charge_type   = $charge_list->ChargeType;
										$charge_amount = $charge_list->ChargeAmount->CurrencyAmount;
									}
								}
							}
							$OrderAdjItemId = $itemadjustment_list->OrderAdjustmentItemId;
							$QtyShipped     = $itemadjustment_list->QuantityShipped;
							$sellerSKU      = $itemadjustment_list->SellerSKU;
						}
						if ( $charge_type != '' && $charge_amount != 0 ) {
							$orderDetails    = array(
								'ID_ACCOUNT'            => $accountId,
								'order_id'              => $recordArray->AmazonOrderId,
								'charge_type'           => $charge_type,
								'amount'                => $charge_amount,
								'OrderAdjustmentItemId' => $OrderAdjItemId,
								'quantity_shipped'      => $QtyShipped,
								'seller_sku'            => $sellerSKU,
								'date'                  => $recordArray->PostedDate,
							);
							$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $recordArray->AmazonOrderId, $sellerSKU, $charge_type );
							echo "<pre />";
							print_r( $orderDetails );
							echo "<pre />";
							print_r( $checkOrderExist );
							if ( ! empty( $checkOrderExist ) ) {
								$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
							} else {
								$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
							}
						}
					}
				}
			} else {
				echo $RefundRecord->AmazonOrderId;
				if ( ! empty( $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList ) ) {
					if ( ! empty( $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType ) ) {
						$charge_type = $charge_amount = '';
						if ( $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Principal' || $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Goodwill' ) {
							$charge_type   = $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeType;
							$charge_amount = $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent->ChargeAmount->CurrencyAmount;
						}
						if ( $charge_type != '' && $charge_amount != 0 ) {
							$orderDetails    = array(
								'ID_ACCOUNT'            => $accountId,
								'order_id'              => $RefundRecord->AmazonOrderId,
								'charge_type'           => $charge_type,
								'amount'                => $charge_amount,
								'OrderAdjustmentItemId' => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->OrderAdjustmentItemId,
								'quantity_shipped'      => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->QuantityShipped,
								'seller_sku'            => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU,
								'date'                  => $RefundRecord->PostedDate,
							);
							$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $RefundRecord->AmazonOrderId, $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU, $charge_type );
							echo "<pre />";
							print_r( $orderDetails );
							echo "<pre />";
							print_r( $checkOrderExist );
							if ( ! empty( $checkOrderExist ) ) {
								$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
							} else {
								$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
							}
						}
					} else {
						$charge_type = $charge_amount = '';
						foreach ( $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->ItemChargeAdjustmentList->ChargeComponent as $charge_list ) {
							if ( $charge_list->ChargeType == 'Principal' || $charge_list->ChargeType == 'Goodwill' ) {
								$charge_type   = $charge_list->ChargeType;
								$charge_amount = $charge_list->ChargeAmount->CurrencyAmount;
							}
						}

						if ( $charge_type != '' && $charge_amount != 0 ) {
							$orderDetails    = array(
								'ID_ACCOUNT'            => $accountId,
								'order_id'              => $RefundRecord->AmazonOrderId,
								'charge_type'           => $charge_type,
								'amount'                => $charge_amount,
								'OrderAdjustmentItemId' => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->OrderAdjustmentItemId,
								'quantity_shipped'      => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->QuantityShipped,
								'seller_sku'            => $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU,
								'date'                  => $RefundRecord->PostedDate,
							);
							$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $RefundRecord->AmazonOrderId, $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem->SellerSKU, $charge_type );
							echo "<pre />";
							print_r( $orderDetails );
							echo "<pre />";
							print_r( $checkOrderExist );
							if ( ! empty( $checkOrderExist ) ) {
								$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
							} else {
								$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
							}
						}
					}
				} else {
					$charge_type = $charge_amount = '';
					foreach ( $RefundRecord->ShipmentItemAdjustmentList->ShipmentItem as $itemadjustment_list ) {
						if ( ! empty( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType ) ) {
							if ( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Principal' || $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType == 'Goodwill' ) {
								$charge_type   = $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeType;
								$charge_amount = $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent->ChargeAmount->CurrencyAmount;
							}
						} else {
							foreach ( $itemadjustment_list->ItemChargeAdjustmentList->ChargeComponent as $charge_list ) {
								if ( $charge_list->ChargeType == 'Principal' || $charge_list->ChargeType == 'Goodwill' ) {
									$charge_type   = $charge_list->ChargeType;
									$charge_amount = $charge_list->ChargeAmount->CurrencyAmount;
								}
							}
						}
						$OrderAdjItemId = $itemadjustment_list->OrderAdjustmentItemId;
						$QtyShipped     = $itemadjustment_list->QuantityShipped;
						$sellerSKU      = $itemadjustment_list->SellerSKU;
					}
					if ( $charge_type != '' && $charge_amount != 0 ) {
						$orderDetails    = array(
							'ID_ACCOUNT'            => $accountId,
							'order_id'              => $RefundRecord->AmazonOrderId,
							'charge_type'           => $charge_type,
							'amount'                => $charge_amount,
							'OrderAdjustmentItemId' => $OrderAdjItemId,
							'quantity_shipped'      => $QtyShipped,
							'seller_sku'            => $sellerSKU,
							'date'                  => $RefundRecord->PostedDate,
						);
						$checkOrderExist = $this->amazon_finance_refund_model->getPaymentDetailbyOrderIDandAccountID( $accountId, $RefundRecord->AmazonOrderId, $sellerSKU, $charge_type );
						echo "<pre />";
						print_r( $orderDetails );
						echo "<pre />";
						print_r( $checkOrderExist );
						if ( ! empty( $checkOrderExist ) ) {
							$this->amazon_finance_refund_model->update_finance_refund_orders( $orderDetails, $checkOrderExist['id'] );
						} else {
							$arrorderId[] = $this->amazon_finance_refund_model->insert_finance_refund_orders( $orderDetails );
						}
					}
				}
			}
		}
		return $arrorderId;
	}
}