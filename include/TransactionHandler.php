<?php
class TransactionHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();
		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
        // $this->decrypt = $phash->string_decrypt();
        // $this->encrypt = $phash->string_encrypt(); 
    }

    public function getMemberTransaction($billno,$membershipid) {
		
		$stmt = $this->conn->prepare("SELECT Company_id,Trans_date,Purchase_amount,Loyalty_pts,Redeem_points,Bill_no,Manual_billno,Seller,Seller_name from igain_transaction WHERE Trans_type = 2  AND Company_id = ? AND Manual_billno = ? AND Card_id = ? ");
		
		$stmt->bind_param("sss", $_SESSION['company_id'],$billno,$membershipid);		
		
		if ($stmt->execute()){
			
			
			$res = $stmt->get_result();
			$stmt->close();
			return $res;
			
		} else {
			return NULL;
		}
    }
	public function getMemberDebitTransaction($billno,$membershipid) {
		
		$stmt = $this->conn->prepare("SELECT Company_id,Trans_date,Purchase_amount,Loyalty_pts,Redeem_points,Bill_no,Seller,Seller_name from igain_transaction WHERE Trans_type = 26  AND Company_id = ? AND Manual_billno = ? AND Card_id = ? ");
		
		$stmt->bind_param("sss", $_SESSION['company_id'],$billno,$membershipid);		
		
		if ($stmt->execute()){
			
			
			$res = $stmt->get_result();
			$stmt->close();
			 return $res;
		} else {
			return NULL;
		}
    }
	public function insertDebitTransaction($TransPara) {
		
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO igain_transaction (" . implode(', ', $key) . ") "
			 . "VALUES ('" . implode("', '", $val) . "')";
	 
		
		// var_dump($sql);
		
		
		$stmt = $this->conn->prepare($sql);
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}
		
        // return $response;
		
		// die;
        
		/* $stmt = $this->conn->prepare("INSERT INTO igain_transaction (Trans_type,Company_id,Trans_date,Topup_amount,Remarks,Card_id,Seller_name,Seller,Enrollement_id,Bill_no,remark2) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssssssss",$TransPara['Trans_type'],$TransPara['Company_id'],$TransPara['Trans_date'],$TransPara['Topup_amount'],$TransPara['Remarks'],$TransPara['Card_id'],$TransPara['Seller_name'],$TransPara['Seller'],$TransPara['Enrollement_id'],$TransPara['Bill_no'],$TransPara['remark2'],);			
		$result = $stmt->execute();
		$stmt->close();
		// print_r($result);
		if($result) {
			return SUCCESS;
			
		} else {
			return FAIL;			
		}			
        return $response; */
    }
	
	public function updateMemberBalance($MemberPara,$where)
	{
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
		$sql = "UPDATE igain_enrollment_master SET " . implode(', ', $cols) . " WHERE Enrollement_id=$where";		
		// print_r($sql);		
		$stmt = $this->conn->prepare($sql);
        // $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
		
		/* // $next_card_no=$next_card_no1+1;
		$stmt = $this->conn->prepare("UPDATE igain_enrollment_master t set t.Current_balance = ?,t.Total_topup_amt = ? WHERE t.Enrollement_id = ? ");
        $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0; */
	}
}
?>
