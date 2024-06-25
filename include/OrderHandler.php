<?php
class OrderHandler
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
	 public function getCompanyDetails($api_key) 
	 {
		// $api_key1 = $this->phash->string_encrypt($api_key);	
		$api_key = $this->phash->string_decrypt($api_key);		
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Redemptionratio,Currency_name,Points_used_gift_card,Minimum_gift_card_amount ,Gift_card_validity_days,Stamp_voucher_validity,Ecommerce_flag,First_trans_bonus_flag,Bday_bonus_flag,Block_points_flag from igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$phonecode,$Redemptionratio,$Currency_name,$Points_used_gift_card,$Minimum_gift_card_amount,$Gift_card_validity_days,$Stamp_voucher_validity,$Ecommerce_flag,$First_trans_bonus_flag,$Bday_bonus_flag,$Block_points_flag);
            $stmt->fetch();
			
            // $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            $stmt->fetch();
            $res["Company_id"] = $Company_id;
            $res["Company_name"] = $Company_name;
            $res["phonecode"] = $phonecode;
            $res["Redemptionratio"] = $Redemptionratio;
            $res["Currency_name"] = $Currency_name;
            $res["Points_used_gift_card"] = $Points_used_gift_card;
            $res["Minimum_gift_card_amount"] = $Minimum_gift_card_amount;
            $res["Gift_card_validity_days"] = $Gift_card_validity_days;
            $res["Stamp_voucher_validity"] = $Stamp_voucher_validity;
            $res["Company_ecommerce_flag"] = $Ecommerce_flag;
            $res["Company_first_trans_bonus_flag"] = $First_trans_bonus_flag;
            $res["Company_bday_bonus_flag"] = $Bday_bonus_flag;
            $res["Company_block_points_flag"] = $Block_points_flag;
            $stmt->close();
            return $res;
        } 
		else 
		{
            return NULL;
        }
    }
	public function superSellerDetails() 
	{ 
		$stmt = $this->conn->prepare("SELECT Enrollement_id,First_name, Last_name,Country,State,City,Topup_Bill_no,Purchase_Bill_no,timezone_entry,Seller_Redemptionratio FROM igain_enrollment_master WHERE Company_id = ? AND Super_seller=1 AND Sub_seller_admin=1 AND User_id=2");
		
        $stmt->bind_param("s", $_SESSION['company_id']);
		
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function getMemberDetails($membershipid,$phoneno,$Company_id) 
	{
		$phoneno=$this->phash->string_encrypt($phoneno);
	
      $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry ,Tier_id,Zipcode,District,Sex,Date_of_birth,Age,joined_date,Country,State,City FROM igain_enrollment_master WHERE (Card_id = ? OR Phone_no = ? ) and Company_id = ? and User_id = 1 and User_activated = 1");
		 
        $stmt->bind_param("sss", $membershipid,$phoneno,$Company_id);
		
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_enrollment_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ?");
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_outlet_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Phone_no,Company_id ,timezone_entry,State,City,Zipcode,Country,timezone_entry,Super_seller,Sub_seller_admin,Sub_seller_Enrollement_id,Purchase_Bill_no,Topup_Bill_no,Seller_Redemptionratio FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ? AND User_id = 2 AND User_activated = 1");
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_brand_details($Enrollement_id,$Company_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Phone_no,Company_id ,timezone_entry,State,City,Zipcode,Country,timezone_entry,Super_seller,Sub_seller_admin,Sub_seller_Enrollement_id,Purchase_Bill_no,Seller_Redemptionratio FROM igain_enrollment_master WHERE Enrollement_id = ? AND Company_id = ? AND User_id = 2 AND User_activated = 1");
        $stmt->bind_param("ss", $Enrollement_id,$Company_id);
        if ($stmt->execute()) 
		{
            $user = $stmt->get_result()->fetch_assoc();
            return $user;
        } 
		else 
		{
            return NULL;
        }
    }
	public function get_all_items($Company_id) 
	{
		$Todays_date=date("Y-m-d");
        $stmt = $this->conn->prepare("SELECT * FROM igain_company_merchandise_catalogue WHERE Company_id = ? AND Valid_from <= ? AND Valid_till >= ? AND show_item = 1 AND Active_flag = 1 AND Link_to_Member_Enrollment_flag = 0 AND Send_once_year = 0 AND Send_other_benefits = 0 AND Billing_price != 0"); //AND Ecommerce_flag = 0 AND Billing_price_in_points != 0
		
        $stmt->bind_param("sss", $Company_id,$Todays_date,$Todays_date);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items[] = $row;
			}
			return $items;
        }
		else 
		{
            return NULL;
        } 
    }
	public function Get_item_details($ItemCode,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_company_merchandise_catalogue WHERE Company_merchandize_item_code = ? AND Company_id = ? AND Valid_from <= ? AND Valid_till >= ? AND Active_flag = 1");
        $stmt->bind_param("ssss", $ItemCode,$Company_id,$Todays_date,$Todays_date);
        if ($stmt->execute()) 
		{
            $item_details = $stmt->get_result()->fetch_assoc();
            return $item_details;
        } 
		else 
		{
            return NULL;
        }
    }
	public function GetItemsDetails($Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId) 
	{
        $stmt = $this->conn->prepare("SELECT * FROM igain_pos_temp_cart WHERE Company_id = ? AND Enrollment_id = ? AND Item_code = ? AND Seller_id = ? AND Channel_id = ?");
		
        $stmt->bind_param("sssss", $Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId);
        if ($stmt->execute()) 
		{
            $temp_item_details = $stmt->get_result()->fetch_assoc();
            return $temp_item_details;
        } 
		else 
		{
            return NULL;
        }
    }
	public function Insert_online_purchase_transaction($insert_data) 
	{
		// print_r($insert_data); die;
		 $key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_transaction (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		
		if($result) 
		{
			 return SUCCESS;
			// return $result->insert_id();
		} 
		else
		{
			return FAIL;			
		} 
	}
	public function insert_loyalty_transaction_child($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_transaction_child (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function insert_voucher_in_gift_card($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_giftcard_tbl (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function update_member_balance($MemberPara,$Enrollment_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master t set t.Current_balance = ?,t.Total_reddems = ?,t.total_purchase = ? WHERE t.Enrollement_id = ? ");
        $stmt->bind_param("sssi", $MemberPara['Current_balance'],$MemberPara['Total_reddems'],$MemberPara['total_purchase'],$Enrollment_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function update_voucher($giftData1,$redeemed_discount_voucher,$Company_id,$CardId)
	{
		$stmt = $this->conn->prepare("UPDATE igain_giftcard_tbl g set g.Card_balance = ?,g.Update_user_id = ?,g.Update_date = ? WHERE g.Gift_card_id = ? AND g.Company_id = ? AND g.Card_id = ?");
		
        $stmt->bind_param("sssiii", $giftData1['Card_balance'],$giftData1['Update_user_id'],$giftData1['Update_date'],$redeemed_discount_voucher,$Company_id,$CardId);
		
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function update_giftcard($giftData2,$redeemed_giftcard,$Company_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_giftcard_tbl g set g.Card_balance = ?,g.Update_user_id = ?,g.Update_date = ? WHERE g.Gift_card_id = ? AND g.Company_id = ?");
		
        $stmt->bind_param("sssii", $giftData2['Card_balance'],$giftData2['Update_user_id'],$giftData2['Update_date'],$redeemed_giftcard,$Company_id);
		
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function update_Free_item_quantity($updateData1,$Bill_no,$FreeItemCode)
	{
		$stmt = $this->conn->prepare("UPDATE igain_transaction t set t.Free_item_quantity = ? WHERE t.Bill_no = ? AND t.Item_code = ?");
		
        $stmt->bind_param("sii", $updateData1['Free_item_quantity'],$Bill_no,$FreeItemCode);
		
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function updatePurchaseBillno($billno_withyear,$seller_id)
	{
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master set Purchase_Bill_no = ? WHERE Enrollement_id = ? ");
        $stmt->bind_param("si", $billno_withyear, $seller_id);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function Get_discount_vouchers($Card_id,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? AND Card_id = ? AND Valid_till >= ? AND Card_balance > 0 AND  Payment_Type_id = 99"); //Payment_Type_id = 99 Discount Vouchers
		
        $stmt->bind_param("sss", $Company_id,$Card_id,$Todays_date);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$vouchers[] = $row;
			}
			return $vouchers;
        }
		else 
		{
            return NULL;
        } 
    }
	public function insert_item($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_pos_temp_cart (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function insert_scan_item($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_scan_cart (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function Get_items($Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId) 
	{	
        $stmt = $this->conn->prepare("SELECT * FROM igain_pos_temp_cart WHERE Company_id = ? AND Enrollment_id = ? AND Seller_id = ? AND Channel_id = ?"); 
		
        $stmt->bind_param("ssss", $Company_id,$Cust_enrollement_id,$Outlet_no,$ChannelCompanyId);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items[] = $row;
			}
			return $items;
        }
		else 
		{
            return NULL;
        } 
    }
	public function delete_pos_temp_cart_data($Company_id,$Enrollement_id,$Outlet_no,$ChannelCompanyId)
	{
		$stmt = $this->conn->prepare("DELETE FROM igain_pos_temp_cart WHERE Company_id = ? AND Enrollment_id = ? AND Seller_id = ? AND Channel_id = ?"); 
		
        $stmt->bind_param("iiii", $Company_id,$Enrollement_id,$Outlet_no,$ChannelCompanyId);
		if ($stmt->execute()) 
		{
			return true;
		}
		else 
		{
			return false;
		}
	}
	public function Get_temp_cart_items($Company_id,$Enrollement_id,$Outlet_no,$ChannelCompanyId)
	{
		$stmt = $this->conn->prepare("SELECT Item_code as Item_Num,Item_qty as Item_Qty,Item_price as Item_Rate FROM igain_pos_temp_cart WHERE Company_id = ? AND Enrollment_id = ? AND Seller_id =? AND Channel_id = ?"); 
		
		$stmt->bind_param("ssss", $Company_id,$Enrollement_id,$Outlet_no,$ChannelCompanyId);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$data[] = $row;
			}
			return $data;
        }
		else
		{
			return false;
		}	
	}
	public function get_items_branches($Company_merchandize_item_code,$Merchandize_partner_id,$Company_id)
	{
		 $stmt = $this->conn->prepare("SELECT * FROM igain_merchandize_item_child WHERE Partner_id = ? AND Merchandize_item_code = ? AND Company_id = ?");
		 
		$stmt->bind_param("sss", $Merchandize_partner_id,$Company_merchandize_item_code,$Company_id);
		
        if ($stmt->execute()) 
		{
            $branch_details = $stmt->get_result()->fetch_assoc();
            return $branch_details;
        } 
		else 
		{
            return NULL;
        }
	}
	public function get_tierbased_loyalty($Company_id,$Seller_id,$TierID,$Todays_date)
	{
		$stmt = $this->conn->prepare("SELECT distinct(Loyalty_name) FROM igain_loyalty_master WHERE Company_id = ? AND Seller = ? AND '".$Todays_date."' BETWEEN From_date AND Till_date AND Tier_id IN ('0','".$TierID."') AND Active_flag = 1"); 
		
		$stmt->bind_param("ss", $Company_id,$Seller_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$Loyalty_name[] = $row;
			}
			return $Loyalty_name;
        }
		else 
		{
            return false;
        } 
	}
	public function get_loyalty_program_details($Company_id,$seller_id,$Loyalty_names,$Todays_date)
	{
		$stmt = $this->conn->prepare("SELECT * FROM igain_loyalty_master Loyalty_at_value WHERE Company_id = ? AND Seller = ? AND Loyalty_name = ? AND '".$Todays_date."' BETWEEN From_date AND Till_date AND Active_flag = 1"); //ORDER BY Loyalty_at_value
		
		$stmt->bind_param("sss", $Company_id,$seller_id,$Loyalty_names);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$Loyalty_details[] = $row;
			}
			return $Loyalty_details;
        }
		else 
		{
            return false;
        } 
	}
	public function edit_segment_id($Company_id,$Segment_code)
	{
		$stmt = $this->conn->prepare("SELECT * FROM igain_segment_master LEFT JOIN igain_segment_type_master ON igain_segment_master.Segment_type_id = igain_segment_type_master.Segment_type_id WHERE Company_id = ? AND Segment_code = ?");
		
		$stmt->bind_param("ss", $Company_id,$Segment_code);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$segment_details[] = $row;
			}
			return $segment_details;
        }
		else 
		{
            return false;
        } 
	}
	public function Fetch_country($Country_id)
	{
		 $stmt = $this->conn->prepare("SELECT name as Country_name FROM igain_country_master WHERE id = ?");
		 
		$stmt->bind_param("s", $Country_id);
		
        if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }
	} 
	public function Fetch_state($State_id)
	{
		 $stmt = $this->conn->prepare("SELECT name as State_name FROM igain_state_master WHERE id = ?");
		 
		$stmt->bind_param("s", $State_id);
		
        if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }
	}
	public function Fetch_city($City_id)
	{
		 $stmt = $this->conn->prepare("SELECT name as City_name FROM igain_city_master WHERE id = ?");
		 
		$stmt->bind_param("s", $City_id);
		
        if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }
	} 
	public function get_cust_trans_summary_all($Company_id,$Enrollement_id,$start_date,$end_date,$transaction_type_id,$Tier_id,$start,$limit)
	{
		$start_date=date("Y-m-d",strtotime($start_date));
		$end_date=date("Y-m-d",strtotime($end_date));
               
		$stmt = $this->conn->prepare("SELECT SUM(Loyalty_pts) as Total_Gained_Points FROM igain_transaction WHERE Company_id = ? AND Enrollement_id = ? AND  Trans_type = ? AND Trans_date BETWEEN '".$start_date."' AND '".$end_date."' GROUP BY Enrollement_id"); 
		
		$stmt->bind_param("sss", $Company_id,$Enrollement_id,$transaction_type_id);
		
		if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }	
	}
	public function get_cust_trans_details($Company_id,$From_date,$To_date,$Enrollement_id,$transaction_type_id,$Tier_id,$start,$limit)
	{ 
		$From_date=date("Y-m-d",strtotime($From_date));
		$To_date=date("Y-m-d",strtotime($To_date));
		
		$stmt = $this->conn->prepare("SELECT MAX(Purchase_amount) as Purchase_amount FROM igain_transaction WHERE Company_id = ? AND Enrollement_id = ? AND  Trans_type = ? AND Trans_date BETWEEN '".$From_date."' AND '".$To_date."' GROUP BY Enrollement_id");
		
		$stmt->bind_param("sss", $Company_id,$Enrollement_id,$transaction_type_id);
		
		if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }	
	}
	public function Get_loyalty_details_for_online_purchase($Loyalty_id)
	{
		$stmt = $this->conn->prepare("SELECT Loyalty_at_transaction,discount,Loyalty_name FROM igain_loyalty_master WHERE Loyalty_id = ?"); 
		$stmt->bind_param("s", $Loyalty_id);
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$rule_details[] = $row;
			}
			return $rule_details;
        }
		else 
		{
            return false;
        } 
	}
	public function get_product_offers($product_id,$Merchandize_category_id,$Company_id,$Brand_id)
	{ 
		$Todays_date=date('Y-m-d');
		
		$stmt = $this->conn->prepare("SELECT A.Offer_id,A.Offer_code,A.Company_merchandise_item_id,A.Buy_item,A.Free_item,A.Free_item_id,From_date,Till_date,Offer_name FROM igain_offer_master as A LEFT JOIN igain_company_merchandise_catalogue as B ON A.Company_merchandise_item_id=B.Company_merchandise_item_id WHERE A.Company_id = ? AND A.Seller_id = ? AND A.Buy_item_category = ? AND '".$Todays_date."' BETWEEN From_date AND Till_date AND A.Company_merchandise_item_id IN ('0','".$product_id."') AND A.Active_flag = 1"); 
		
		$stmt->bind_param("sss", $Company_id,$Brand_id,$Merchandize_category_id);
		
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$offers_details[] = $row;
			}
			return $offers_details;
        }
		else 
		{
            return false;
        }
	}
	public function get_offer_selected_items($Offer_code,$Company_id)
	{
		$stmt = $this->conn->prepare("SELECT A.Company_merchandise_item_id,B.Company_merchandize_item_code FROM igain_offer_master as A LEFT JOIN igain_company_merchandise_catalogue as B ON A.Company_merchandise_item_id=B.Company_merchandise_item_id AND A.Company_id=B.Company_id WHERE A.Company_id = ? AND A.Offer_code = ? GROUP BY B.Company_merchandize_item_code");
		
		$stmt->bind_param("ss", $Company_id,$Offer_code);
		
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items_details[] = $row;
			}
			return $items_details;
        }
		else 
		{
            return false;
        }
	}
	public function get_item_purchase_count($ItemCode,$Company_id,$enroll_id,$From_date,$Till_date)
	{
		$Todays_date=date('Y-m-d');
		
		$stmt = $this->conn->prepare("SELECT SUM(Quantity),SUM(Free_item_quantity),SUM(Quantity-Free_item_quantity) as product_qty FROM igain_transaction WHERE Company_id = ? AND Enrollement_id = ? AND Item_code = ? AND Trans_date BETWEEN '".$From_date."' AND '".$Till_date."'"); 	
		
		$stmt->bind_param("sss", $Company_id,$enroll_id,$ItemCode);
		
		if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }
	}
	public function member_sent_offers($Company_id,$enroll_id,$Offer_code,$Free_item_id)
	{
		$stmt = $this->conn->prepare("SELECT * FROM igain_company_send_voucher WHERE Company_id = ? AND Enrollement_id = ? AND Offer_code = ? AND Company_merchandise_item_id = ?");
		
		$stmt->bind_param("ssss", $Company_id,$enroll_id,$Offer_code,$Free_item_id);
		$stmt->execute();
		$stmt->store_result();
		$num_rows = $stmt->num_rows; 
		return $num_rows ;
	}
	public function get_offer_free_items($Offer_code,$Company_id)
	{
		$stmt = $this->conn->prepare("SELECT A.Company_merchandise_item_id,B.Company_merchandize_item_code,A.Free_item_id,B.Merchandize_item_name,B.Billing_price FROM igain_offer_master as A JOIN igain_company_merchandise_catalogue as B ON A.Free_item_id=B.Company_merchandise_item_id AND A.Company_id=B.Company_id WHERE A.Company_id = ? AND A.Offer_code = ? GROUP BY A.Free_item_id");
		
		$stmt->bind_param("ss", $Company_id,$Offer_code);
		
		if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$items_details[] = $row;
			}
			return $items_details;
        }
		else 
		{
            return false;
        }
	}
	public function insert_product_voucher($insert_data) 
	{
		$key = array_keys($insert_data);
		$val = array_values($insert_data);
		$sql = "INSERT INTO igain_company_send_voucher (" . implode(', ', $key) . ") " . "VALUES ('" . implode("', '", $val) . "')";
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		
		if($result) 
		{
			return SUCCESS;
		} 
		else
		{
			return FAIL;			
		}
	}
	public function Validate_gift_card($Company_id,$Giftcard_No,$CardId)
	{
		$today = date("Y-m-d");
		
		$stmt = $this->conn->prepare("SELECT * FROM igain_giftcard_tbl WHERE Company_id = ? AND Gift_card_id = ? AND Valid_till >= ? AND Card_id = ? AND Card_balance > 0 AND Payment_Type_id IN(1,2,3,4,5,6,7)"); 	
		
		$stmt->bind_param("ssss", $Company_id,$Giftcard_No,$today,$CardId);
		
		if ($stmt->execute()) 
		{
            $details = $stmt->get_result()->fetch_assoc();
            return $details;
        } 
		else 
		{
            return NULL;
        }		
	}
	public function update_pos_temp_cart($TempCartData,$Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId)
	{
		$stmt = $this->conn->prepare("UPDATE igain_pos_temp_cart t set t.Item_qty = ? WHERE t.Company_id = ? and t.Enrollment_id = ? and t.Item_code = ? and t.Seller_id = ? and t.Channel_id = ?");
									
		$stmt->bind_param("siiiii", $TempCartData["Item_qty"],$Company_id,$Cust_enrollement_id,$ItemCode,$Outlet_no,$ChannelCompanyId);
		
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function check_order_bill_no($Bill_no,$Pos_outlet_id,$Company_id,$Bill_date_time)
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($Bill_date_time)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($Bill_date_time));
		
		$stmt = $this->conn->prepare("SELECT Manual_billno FROM igain_transaction WHERE Manual_billno = ? AND Seller = ? AND Company_id = ? AND Trans_date BETWEEN '".$start_date."' AND '".$end_date."' AND Trans_type = 2");
		
		$stmt->bind_param("sss", $Bill_no,$Pos_outlet_id,$Company_id);

		$stmt->execute();
	
		$stmt->store_result();
		
		$num_rows = $stmt->num_rows; 
		return $num_rows;
	}
	public function check_scan_item($Company_id,$Enrollment_id,$Item_code,$Bill_date_time)
	{
		$stmt = $this->conn->prepare("SELECT Item_code FROM igain_transaction WHERE Company_id = ? AND Enrollement_id = ? AND Item_code = ? AND Trans_type = 12");
		
		$stmt->bind_param("sss", $Company_id,$Enrollment_id,$Item_code);

		$stmt->execute();
	
		$stmt->store_result();
		
		$num_rows = $stmt->num_rows; 
		return $num_rows;
	}
	public function Get_order_details($order_no,$Company_id,$Card_id,$Enrollement_id,$outlet_no,$order_date) 
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($order_date)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($order_date));
		
		$stmt = $this->conn->prepare("SELECT B.Bill_no,B.Manual_billno,B.Purchase_amount,B.Loyalty_pts,B.Redeem_points,B.Trans_date,B.Voucher_status FROM igain_transaction as B JOIN igain_enrollment_master as E ON B.Enrollement_id=E.Enrollement_id AND B.Company_id=E.Company_id AND B.Card_id=E.Card_id WHERE B.Manual_billno = ? and B.Card_id = ? and B.Enrollement_id = ? and B.Company_id = ? and B.Seller = ? and B.Trans_date BETWEEN '".$start_date."' AND '".$end_date."' and B.Trans_type = 12 and B.Voucher_status = 18");
						
		$stmt->bind_param("sssss", $order_no,$Card_id,$Enrollement_id,$Company_id,$outlet_no);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$order_details[] = $row;
			}
			return $order_details;
        }
		else 
		{
            return NULL;
        }
    }
	public function Update_Order_Status($OrderPara,$Card_id,$Enrollement_id,$Company_id,$Order_no,$Outlet_no,$order_date)
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($order_date)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($order_date));	
			
		$stmt = $this->conn->prepare("UPDATE igain_transaction t set t.Voucher_status = ?,t.Update_User_id = ?,t.Update_date = ? WHERE t.Card_id = ? and t.Enrollement_id = ? and t.Company_id = ? and t.Manual_billno = ? and t.Seller = ? and t.Trans_date BETWEEN '".$start_date."' AND '".$end_date."'");
        $stmt->bind_param("sssiiiii", $OrderPara['Voucher_status'],$OrderPara['Update_User_id'],$OrderPara['Update_date'],$Card_id,$Enrollement_id,$Company_id,$Order_no,$Outlet_no);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}
	public function get_closed_bill_count($Enrollement_id,$Company_id)
	{
		
		$stmt = $this->conn->prepare("SELECT Trans_id FROM igain_transaction WHERE Enrollement_id = ? AND Company_id = ? AND Trans_type = 12 AND Voucher_status = 20");
		
		$stmt->bind_param("ss", $Enrollement_id,$Company_id);

		$stmt->execute();
	
		$stmt->store_result();
		
		$num_rows = $stmt->num_rows; 
		return $num_rows;
	}
	public function insertTopup($TransPara) 
	{
		$stmt = $this->conn->prepare("INSERT INTO igain_transaction (Trans_type,Company_id,Trans_date,Topup_amount,Remarks,Card_id,Seller_name,Seller,Enrollement_id,Bill_no,Order_no,remark2) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssssssss",$TransPara['Trans_type'],$TransPara['Company_id'],$TransPara['Trans_date'],$TransPara['Topup_amount'],$TransPara['Remarks'],$TransPara['Card_id'],$TransPara['Seller_name'],$TransPara['Seller'],$TransPara['Enrollement_id'],$TransPara['Bill_no'],$TransPara['Order_no'],$TransPara['remark2']);			
		$result = $stmt->execute();
        
		$stmt->close();
		
		if($result) {
			return true;
			
		} else {
			return false;			
		}			
    }
	public function get_bill_count($Enrollement_id,$Company_id)
	{
		
		$stmt = $this->conn->prepare("SELECT Trans_id FROM igain_transaction WHERE Enrollement_id = ? AND Company_id = ? AND Trans_type = 12");
		
		$stmt->bind_param("ss", $Enrollement_id,$Company_id);

		$stmt->execute();
	
		$stmt->store_result();
		
		$num_rows = $stmt->num_rows; 
		return $num_rows;
	}
	public function get_block_points_details($order_no,$outlet_no,$Company_id,$Enrollement_id,$points,$lv_date_time) 
	{
		$start_date=date("Y-m-d 00:00:00", strtotime($lv_date_time)); 
		$end_date=date("Y-m-d 23:59:59", strtotime($lv_date_time));
		
		//AND Creation_date BETWEEN '".$start_date."' AND '".$end_date."'
		
        $stmt = $this->conn->prepare("SELECT Enrollment_id,Outlet_id,Order_no,Points,Points_value,Status,Status_dec,Company_id,Creation_date FROM igain_block_points WHERE Enrollment_id = ? AND Company_id = ? AND Outlet_id = ? AND Order_no = ? AND Points = ? AND Status = 0");
        $stmt->bind_param("sssss", $Enrollement_id,$Company_id,$outlet_no,$order_no,$points);
        if ($stmt->execute()) 
		{
            $block_points = $stmt->get_result()->fetch_assoc();
            return $block_points;
        } 
		else 
		{
            return NULL;
        }
    }
}
?>