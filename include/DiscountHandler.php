<?php
class DiscountHandler
{
    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() 
	{
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
		// print_r($db);
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
    }
	public function get_discount_value($Itemcategory_id,$ItemCode,$Item_price,$Company_id,$delivery_outlet,$cust_enroll,$Tier_id,$grandTotal,$API_flag_call)
	{
		$channel = 12;
		$today = date("Y-m-d");
		$discountAmt = 0;
		$discountVoucherAmt = 0;
		$discount_Amt = 0;
		
		$stmt = $this->conn->prepare("SELECT A.*,B.Item_code,B.Discount_percentage_or_value FROM igain_new_discount_rule_master as A LEFT JOIN igain_new_discount_rule_child as B ON A.Discount_code = B.Discount_code  WHERE  
        A.Company_id = ? AND A.Active_flag = 1 AND '".$today."' BETWEEN Valid_from  AND Valid_till");
		
		$stmt->bind_param("s",$Company_id);
        // $stmt->execute();
	
		if($stmt->execute())
		{
			// $result = $stmt->get_result();
			foreach ($stmt->get_result() as $row)
			{
				$step = 0;
				$discount_Percentage = 0;

				$validTill = date("Y-m-d",strtotime($row['Valid_till']));
				$valid = $row['Valid_till'];
				$percent = $row['Discount_Percentage_Value'];
				if(in_array($row['Tier_id'],array(0,$Tier_id)))
				{
					$step++;	
				}

				if(in_array($row['Seller_id'],array(0,$delivery_outlet)))
				{
					$step++;
				}
				
				if($row['Discount_rule_for'] == 4 && $step == 2 && $grandTotal > 0 && $channel > 0 && $channel == $row['Channel_id']) // on transaction channel
				{
					if($row['Discount_rule_set'] == 2)
					{
						$discount_Amt = (int)$row['Discount_Percentage_Value'];
					
						if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
						{
							$discount_Amt = $row['Maximum_limit'];
						}
					}
					
					if($row['Discount_rule_set'] == 1) // in %
					{
						if($row['Discount_voucher_applicable'] == 0 )
						{
							$discount_Amt = (int)($row['Discount_Percentage_Value']*$grandTotal)/100;
						}
						
						if($row['Discount_voucher_applicable'] == 1 ) // send voucher
						{
							$discount_Percentage = (int)$row['Discount_Percentage_Value'];
						}
						
						if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
						{
							$discount_Amt = $row['Maximum_limit'];
						}
					}
					$step++;
				}
				
				if($row['Discount_rule_for'] == 1 && $step == 2 && $grandTotal > 0) // on billing amt
				{
					$opretorFlag = $this->operator_validation($row['Operator'],$row['Criteria_value'],$grandTotal);
					
					if( $opretorFlag > 0)
					{
						if($row['Discount_rule_set'] == 2)
						{
							$discount_Amt = (int)$row['Discount_Percentage_Value'];
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
						
						if($row['Discount_rule_set'] == 1) // in %
						{
							if($row['Discount_voucher_applicable'] == 0 )
							{
								$discount_Amt = (int)($row['Discount_Percentage_Value']*$grandTotal)/100;
							}
							
							if($row['Discount_voucher_applicable'] == 1 ) // send voucher
							{
								$discount_Percentage = (int)$row['Discount_Percentage_Value'];
							}
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
					}
					$step++;
				}
				
				if($row['Discount_rule_for'] == 3 && $step == 2 && $grandTotal == 0) // item level
				{
					if($row['Item_code'] == $ItemCode)
					{
						if($row['Discount_rule_set'] == 2)
						{
							$discount_Amt = (int)$row['Discount_percentage_or_value'];
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
						
						if($row['Discount_rule_set'] == 1) // in %
						{
							if($row['Discount_voucher_applicable'] == 0 )
							{
								$discount_Amt = (int)($row['Discount_Percentage_Value']*$Item_price)/100;
							}
							
							if($row['Discount_voucher_applicable'] == 1 ) // send voucher
							{
								$discount_Percentage = (int)$row['Discount_Percentage_Value'];
							}
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
					}
					$step++;
				}
				if($row['Discount_voucher_applicable'] == 0 )
				{
					$discountAmt = $discountAmt + floor($discount_Amt);
					
					if($discount_Amt > 0)
					{
						$discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_amt"=>number_format(floor($discount_Amt),2));
							
						$discountsArray2[] = array("Discount_code"=>$row['Discount_code'],"Discount_amt"=>number_format(floor($discount_Amt),2));
					}
				}
				
				if($row['Discount_voucher_applicable'] == 1 ) // send voucher
				{
					// $gift_cardid = $this->getGiftCard($Company_id);
					$gift_cardid = $this->getVoucher();
					
					$discountVoucherAmt = $discountVoucherAmt + floor($discount_Amt);
					
					if($discount_Amt > 0 || $discount_Percentage > 0)
					{
						if($API_flag_call == 90) //Pos Trans fourth api call
						{
							$discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_voucher_code"=>$gift_cardid,"Discount_voucher_amt"=>number_format(floor($discount_Amt),2),"Discount_voucher_percentage" =>$discount_Percentage,"Discount_voucher_validity"=>$validTill);
						}
					}
				}

				$discount_Amt = 0;  
			}
		}
		return	json_encode(array("DiscountAmt"=>number_format(floor($discountAmt),2),"discountsArray"=>$discountsArray,"discountsArray2"=>$discountsArray2));
		
		//"voucherAmt"=>number_format(floor($discountVoucherAmt),2),
	}
	public function get_category_discount_value($Itemcategory_id,$ItemCode,$Item_price,$Company_id,$delivery_outlet,$cust_enroll,$Tier_id,$grandTotal,$channel,$API_flag_call)
	{
		$today = date("Y-m-d");
		$discountAmt = 0;
		$discountVoucherAmt = 0;
		$discount_Amt = 0;
		
		$DiscountPercentageValue=0;
		$DiscountRuleSet=0;

		$stmt = $this->conn->prepare("SELECT A.*,B.Item_code,B.Discount_percentage_or_value FROM igain_new_discount_rule_master as A LEFT JOIN igain_new_discount_rule_child as B ON A.Discount_code = B.Discount_code  WHERE  
        A.Company_id = ? AND A.Active_flag = 1 AND A.Discount_rule_for = 2 AND '".$today."' BETWEEN Valid_from  AND Valid_till");
		
		$stmt->bind_param("s",$Company_id);

		if ($stmt->execute())
		{
			// $result = $stmt->get_result();
			foreach ($stmt->get_result() as $row)
			{
				$step = 0;
				$discount_Percentage = 0;
				
				$validTill = date("Y-m-d",strtotime($row['Valid_till']));
				
				if(in_array($row['Tier_id'],array(0,$Tier_id)))
				{
					$step++;	
				}

				if(in_array($row['Seller_id'],array(0,$delivery_outlet)))
				{
					$step++;
				}
				
				if($row['Discount_rule_for'] == 2 && $step == 2 && $grandTotal == 0) // category level
				{
					if($row['Category_id'] == $Itemcategory_id)
					{
						if($row['Discount_rule_set'] == 2)
						{
							$discount_Amt = (int)$row['Discount_Percentage_Value'];
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
						
						if($row['Discount_rule_set'] == 1) // in %
						{
							if($row['Discount_voucher_applicable'] == 0 )
							{
								$discount_Amt = (int)($row['Discount_Percentage_Value']*$Item_price)/100;
							}
							
							if($row['Discount_voucher_applicable'] == 1 ) // send voucher
							{
								$discount_Percentage = (int)$row['Discount_Percentage_Value'];
							}
							
							if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
							{
								$discount_Amt = $row['Maximum_limit'];
							}
						}
					}
					$step++;
				}
				if($row['Discount_voucher_applicable'] == 0 )
				{
					$discountAmt = $discountAmt + floor($discount_Amt);
					
					if($discount_Amt > 0)
					{
						$discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_amt"=>number_format(floor($discount_Amt),2));
						
						$discountsArray2[] = array("Discount_code"=>$row['Discount_code'],"Discount_amt"=>number_format(floor($discount_Amt),2));
					}
				}
				
				if($row['Discount_voucher_applicable'] == 1 ) // send voucher
				{
					// $gift_cardid = $this->getGiftCard($Company_id);
					$gift_cardid = $this->getVoucher();
					
					$discountVoucherAmt = $discountVoucherAmt + floor($discount_Amt);
					
					if($discount_Amt > 0 || $discount_Percentage > 0)
					{
						if($API_flag_call == 90) 
						{
							
						$discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_voucher_code"=>$gift_cardid,"Discount_voucher_amt"=>number_format(floor($discount_Amt),2),"Discount_voucher_percentage" =>$discount_Percentage,"Discount_voucher_validity"=>$validTill);
						
						}
					}
				}

				$discount_Amt = 0;
			}
		
		}
	
		return	json_encode(array("DiscountAmt"=>number_format(floor($discountAmt),2),"discountsArray"=>$discountsArray,"discountsArray2"=>$discountsArray2));
	}
	public function operator_validation($Operator_id,$Criteria_value,$Transaction_amount)
	{	
		$allow_transaction = 0;
		
		if($Operator_id == "<")
		{
			if($Transaction_amount < $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		if($Operator_id == "<=")
		{
			if($Transaction_amount <= $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		if($Operator_id == ">")
		{
			if($Transaction_amount > $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		if($Operator_id == ">=")
		{
			if($Transaction_amount >= $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		if($Operator_id == "==")
		{
			if($Transaction_amount == $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		if($Operator_id == "!=")
		{
			if($Transaction_amount != $Criteria_value)
			{
			$allow_transaction = 1;
			}
		}
		
		return $allow_transaction;
	}
	public function Validate_discount_voucher($Card_id,$Company_id,$Discount_voucher_code,$Voucher_amount)
	{
		$today = date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Card_id = ? and Company_id = ? and Gift_card_id = ? and Valid_till >= ? and Card_balance > 0");
		
		$stmt->bind_param("ssss", $Card_id,$Company_id,$Discount_voucher_code,$today);
		if ($stmt->execute()) 
		{
            $VoucherDetails = $stmt->get_result()->fetch_assoc();
            return $VoucherDetails;
        } 
		else 
		{
            return NULL;
        }			
	}
	public function Get_Product_Voucher_Details($Gift_card_id,$Cust_enrollement_id,$Company_id)
	{
		$stmt = $this->conn->prepare("SELECT * FROM igain_company_send_voucher WHERE Enrollement_id = ? and Company_id = ? and Voucher_code = ?");
		
		$stmt->bind_param("sss", $Cust_enrollement_id,$Company_id,$Gift_card_id);
		if ($stmt->execute()) 
		{
            $Voucher = $stmt->get_result()->fetch_assoc();
            return $Voucher;
        } 
		else 
		{
            return NULL;
        }	
	}
	public function Get_lowest_sent_vouchers($Card_id,$Company_id,$Voucher_code)
	{
		$today = date("Y-m-d");
	
		$stmt = $this->conn->prepare("SELECT G.Gift_card_id,G.Card_balance,G.Valid_till,G.Discount_percentage,B.Voucher_code,B.Company_merchandize_item_code, B.Quantity as Voucher_Qty,B.Cost_price as Voucher_Cost_price,Offer_description,Offer_name FROM igain_giftcard_tbl as G LEFT JOIN igain_company_send_voucher as B ON G.Gift_card_id = B.Voucher_code AND G.Company_id = B.Company_id JOIN igain_offer_master as of ON of.Offer_code = B.Offer_code and of.Company_id = B.Company_id WHERE G.Card_id = ? AND G.Company_id = ? AND G.Gift_card_id = ? AND G.Valid_till >= ? AND G.Payment_Type_id = 997 AND B.Voucher_id = 0 AND B.Offer_code != '' AND G.Card_balance > 0 GROUP BY B.Voucher_code,B.Company_merchandize_item_code ORDER BY B.Voucher_code ASC,B.Cost_price ASC ");
		
		$stmt->bind_param("ssss",$Card_id,$Company_id,$Voucher_code,$today);
		
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items_vouchers[] = $row;
			}
			return $items_vouchers;
        }
		else 
		{
            return NULL;
        } 	
	}
	public function get_payment_type_discount_value($PaymentType,$Company_id,$delivery_outlet,$cust_enroll,$Tier_id,$grandTotal)
	{
		$today = date("Y-m-d");
		
		$discountAmt = 0;
		$discountVoucherAmt = 0;
		$discount_Amt = 0;
		$discount_Percentage = 0;
		$DiscountPercentageValue=0;
		
		$stmt = $this->conn->prepare("SELECT A.* FROM igain_new_discount_rule_master as A WHERE A.Company_id = ? AND Payment_type_id = ? AND A.Active_flag= 1 AND '".$today."' BETWEEN A.Valid_from AND A.Valid_till");
		
		$stmt->bind_param("ss", $Company_id,$PaymentType);
			
		if ($stmt->execute())
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$step = 0;
				
				$validTill = date("Y-m-d",strtotime($row['Valid_till']));
				
				if(in_array($row['Tier_id'],array(0,$Tier_id)))
				{
					$step++;	
				}

				if(in_array($row['Seller_id'],array(0,$delivery_outlet)))
				{
					$step++;
				}
				
				if($row['Discount_rule_for'] == 5 && $step == 2 && $grandTotal > 0 ) // on payment type
				{
					if($row['Discount_rule_set'] == 2)
					{
						
						$discount_Amt = (int)$row['Discount_Percentage_Value'];
						
					//*****************************
						if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
						{
							$discount_Amt = $row['Maximum_limit'];
						}
					//*****************************
					}
					
					if($row['Discount_rule_set'] == 1) // in %
					{
				
					//*****************************
						if($row['Discount_voucher_applicable'] == 0 )
						{
							$discount_Amt = (int)($row['Discount_Percentage_Value']*$grandTotal)/100;
						}
						
						if($row['Discount_voucher_applicable'] == 1 ) // send voucher
						{
							$discount_Percentage = $row['Discount_Percentage_Value'];
						}
						
						if($row['Maximum_limit'] > 0 && $row['Maximum_limit'] < $discount_Amt)
						{
							$discount_Amt = $row['Maximum_limit'];
						}
					//*****************************
					}
					$step++;
				}
				if($row['Discount_voucher_applicable'] == 0 )
				{
					$discountAmt = $discountAmt + floor($discount_Amt);
					
					if($discount_Amt > 0)
					{
						$payment_discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_amt"=>number_format(floor($discount_Amt),2));
					}
				}				
				if($row['Discount_voucher_applicable'] == 1 ) // send voucher
				{
					$gift_cardid = $this->getVoucher();
					
					$discountVoucherAmt = $discountVoucherAmt + floor($discount_Amt);
				
					if($discount_Amt > 0 || $discount_Percentage > 0)
					{
						$payment_discountsArray[] = array("Discount_code"=>$row['Discount_code'],"Discount_voucher_code"=>$gift_cardid,"Discount_voucher_amt"=>number_format(floor($discount_Amt),2),"Discount_voucher_percentage" =>$discount_Percentage,"Discount_voucher_validity"=>$validTill);
					}
				}
				$discount_Amt = 0;
			}	
		}
		if(count($payment_discountsArray) > 0)
		{
			return $payment_discountsArray;			
		} else {
			return null;
		}
	}
	public function getVoucher()
	{
		/********************************/
			$characters = '123456789';
			$string = '';
			$Voucher_no="";
			for ($i = 0; $i < 10; $i++) 
			{
				$Voucher_no .= $characters[mt_rand(0, strlen($characters) - 1)];
			}
			
			return $Voucher_no;
		/*************************************/
	}	
}
?>