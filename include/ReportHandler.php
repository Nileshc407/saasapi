<?php
class ReportHandler
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
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,phonecode,Redemptionratio,Currency_name FROM igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ?");
		
        $stmt->bind_param("s", $api_key);		
        if ($stmt->execute()) 
		{
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$phonecode,$Redemptionratio,$Currency_name);
            $stmt->fetch();
			
            $res["Company_id"] = $Company_id;
            $res["Company_name"] = $Company_name;
            $res["phonecode"] = $phonecode;
            $res["Redemptionratio"] = $Redemptionratio;
            $res["Currency_name"] = $Currency_name;
            $stmt->close();
            return $res;
        } 
		else 
		{
            return NULL;
        }
    }
	public function getMemberDetails($membershipid,$phoneno,$Company_id) 
	{
		$phoneno=$this->phash->string_encrypt($phoneno);
	
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE (Card_id = ? OR Phone_no = ? ) and Company_id = ? and User_id=1");
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
	public function get_enrollment_details($Enrollement_id) 
	{
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Company_id ,timezone_entry FROM igain_enrollment_master WHERE Enrollement_id = ?");
        $stmt->bind_param("s", $Enrollement_id);
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
	public function get_online_purchase_report($Enrollement_id,$Company_id,$From_date,$To_date) 
	{
        // $stmt = $this->conn->prepare("SELECT T.Trans_date,T.Bill_no,T.Order_no,T.Seller,T.Seller_name,T.Item_code,M.Merchandize_item_name,T.Quantity,T.Purchase_amount,T.Redeem_amount,T.Total_discount,T.Paid_amount,T.Loyalty_pts FROM igain_transaction as T JOIN igain_company_merchandise_catalogue as M ON T.Item_code = M.Company_merchandize_item_code AND T.Company_id = M.Company_id WHERE T.Enrollement_id = ? AND T.Company_id = ? AND T.Trans_date >= ? AND T.Trans_date <= ? AND T.Trans_type = 12");
		
        $stmt = $this->conn->prepare("SELECT T.Trans_date,T.Bill_no,T.Order_no,T.Seller,T.Seller_name,SUM(T.Purchase_amount) as Purchase_amount,SUM(T.Redeem_amount) as Redeem_amount,T.Total_discount,SUM(T.Paid_amount) as Paid_amount,SUM(T.Loyalty_pts) as Loyalty_pts FROM igain_transaction as T WHERE T.Enrollement_id = ? AND T.Company_id = ? AND T.Trans_date >= ? AND T.Trans_date <= ? AND T.Trans_type = 12 GROUP BY T.Bill_no");
		
        $stmt->bind_param("ssss", $Enrollement_id,$Company_id,$From_date,$To_date);
		
       	if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$purchase_report[] = $row;
			}
			return $purchase_report;
        }
		else 
		{
            return NULL;
        } 
    }
	public function get_redemption_report($Enrollement_id,$Company_id,$From_date,$To_date) 
	{
        $stmt = $this->conn->prepare("SELECT T.Trans_date,T.Bill_no,T.Seller,T.Seller_name,T.Item_code,M.Merchandize_item_name,T.Quantity,T.Redeem_points,T.Redeem_amount,T.Voucher_no,T.Voucher_status FROM igain_transaction as T JOIN igain_company_merchandise_catalogue as M ON T.Item_code = M.Company_merchandize_item_code AND T.Company_id = M.Company_id WHERE T.Enrollement_id = ? AND T.Company_id = ? AND T.Trans_date >= ? AND T.Trans_date <= ? AND T.Trans_type = 10 ORDER BY T.Trans_id DESC");
		
        $stmt->bind_param("ssss", $Enrollement_id,$Company_id,$From_date,$To_date);
		
       	if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$redemption_report[] = $row;
			}
			return $redemption_report;
        }
		else 
		{
            return NULL;
        } 
    }
}
?>