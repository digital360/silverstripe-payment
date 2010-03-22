<?php

class DPSRecurringPayment extends RecurringPayment{
	
	//Note: as a DPS Recurring Payment, its TxnType should be always Auth
	public static $db = array(
		'TxnRef' => 'Text',
		'AuthCode' => 'Varchar(22)',
		'MerchantReference' => 'Varchar(64)',
		'DPSHostedRedirectURL' => 'Text',
		'DPSBillingID' => "Varchar(16)",
		'AuthAmount' => 'Decimal',
	);
	
	private static $input_elements = array(
		'Amount',
		'CardHolderName',
		'CardNumber',
		'BillingId',
		'Cvc2',
		'DateExpiry',
		'DpsBillingId',
		'DpsTxnRef',
		'EnableAddBillCard',
		'InputCurrency',
		'MerchantReference',
		'PostUsername',
		'PostPassword',
		'TxnType',
		'TxnData1',
		'TxnData2',
		'TxnData3',
		'TxnId',
		'EnableAvsData',
		'AvsAction',
		'AvsPostCode',
		'AvsStreetAddress',
		'DateStart',
		'IssueNumber',
		'Track2',
	);
	
	private static $dpshosted_input_elements = array(
		'PxPayUserId',
		'PxPayKey',
		'AmountInput',
		'CurrencyInput',
		'EmailAddress',
		'EnableAddBillCard',
		'MerchantReference',
		'TxnData1',
		'TxnData2',
		'TxnData3',
		'TxnType',
		'TxnId',
		'UrlFail',
		'UrlSuccess',
	);

	protected static $testable_form = array(
		"DPSHostedRecurringForm" => "DPS Hosted Authorise Recurring Payment Form",
		"MerchantHostedRecurringForm" => "Direct Authorise Recorring Payment Form",
	);
	
	static $default_sort = "ID DESC";
	
	function getTestableForms(){
		return self::$testable_form;
	}
	
	function getForm($formType){
		$adapter = new DPSAdapter();
		return $adapter->getFormByName($formType);
	}
	
	function recurringAuth($data){
		$adapter = new DPSAdapter();
		$inputs = $this->prepareDPSHostedRecurringAuthRequest($data);
		$adapter->doDPSHosedPayment($inputs, $this);
	}
	
	function prepareDPSHostedRecurringAuthRequest($data){
		//never put this loop after $inputs['AmountInput'] = $amount, since it will change it to an array.
		foreach($data as $element => $value){
			if(in_array($element, self::$dpshosted_input_elements)){
				$inputs[$element] = $value;
			}
		}
		
		$inputs['TxnId'] = $this->ID;
		$inputs['TxnType'] = 'Auth';
		$inputs['EnableAddBillCard'] = 1;
		$inputs['AmountInput'] = $this->AuthAmount;
		$inputs['InputCurrency'] = $this->Amount->Currency;
		$inputs['MerchantReference'] = $this->MerchantReference;

		$postProcess_url = Director::absoluteBaseURL() ."DPSAdapter/processDPSHostedResponse";
		$inputs['UrlFail'] = $postProcess_url;
		$inputs['UrlSuccess'] = $postProcess_url;
		
		return $inputs;
	}
	
	function merchantRecurringAuth($data){
		$adapter = new DPSAdapter();
		$inputs = $this->prepareMerchantHostedRecurringAuthInputs($data);
		$adapter->doPayment($inputs, $this);
	}
	
	function prepareMerchantHostedRecurringAuthInputs($data){
		//never put this loop after $inputs['AmountInput'] = $this->Amount->Amount;, since it will change it to an array.
		foreach($data as $element => $value){
			if(in_array($element, self::$input_elements)){
				$inputs[$element] = $value;
			}
		}
		$inputs['TxnId'] = $this->ID;
		$inputs['TxnType'] = 'Validate';
		$inputs['EnableAddBillCard'] = 1;
		$inputs['AmountInput'] = $this->AuthAmount;
		$inputs['InputCurrency'] = $this->Amount->Currency;
		$inputs['MerchantReference'] = $this->MerchantReference;
		//special element
		$inputs['CardNumber'] = implode('', $data['CardNumber']);
		
		return $inputs;
	}
	
	function payNext(){
		if($next = $this->getNextPayment()){
			$next->payAsRecurring();
		}
	}
	
	function getNextPayment(){
		$next = parent::getNextPayment();
		$next->ClassName = 'DPSPayment';
		$next->RecordClassName = 'DPSPayment';
		$next->TxnType = 'Purchase';
		$next->MerchantReference = $this->MerchantReference;
		$next->write();
		return DataObject::get_by_id('DPSPayment', $next->ID);
	}
}

?>