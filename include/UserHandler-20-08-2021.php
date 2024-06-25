<?php
// echo"---in---UserHandler---111--";
class UserHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {
        // echo"---in---UserHandler---222--";
        require_once dirname(__FILE__) . '/DbConnect.php';
       
        // opening db connection
        $db = new DbConnect();
		// print_r($db);
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
    }
	public function getRandomString($length = 4) 
	{
		$characters = '0123456789';
		$string = '';
		for ($i = 0; $i < $length; $i++) 
		{
			$string .= $characters[mt_rand(0, strlen($characters) - 1)];
		}
		return $string;
	}
	public function getFirstLevelTier() 
	{
		$stmt = $this->conn->prepare("SELECT Tier_id FROM igain_tier_master WHERE Company_id = ? AND Tier_level_id = 1");
        $stmt->bind_param("s", $_SESSION['company_id']);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Tier_id);			
            $stmt->fetch();
            // $user = array();
            $Tier_id = $Tier_id;
            $stmt->close();
            return $Tier_id;
        } else {
            return NULL;
        }		
	}
	public function setNextMembershipID($next_card_no1)
	{
			
		$next_card_no=$next_card_no1+1;
		$stmt = $this->conn->prepare("UPDATE igain_company_master t set t.next_card_no = ? WHERE t.Company_id = ? ");
        $stmt->bind_param("si", $next_card_no, $_SESSION['company_id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;		
	}		
    public function createUser($param) {
        // require_once 'PassHash.php';
		
		// echo "---createUser---";
		// $email = $this->phash->string_decrypt($param['email']);
		$email = $this->phash->string_encrypt($param['email']);
		$password = $this->phash->string_encrypt($param['password']);
		$phone = $this->phash->string_encrypt($param['phone']);
		$name = $param['name'];
		$next_card_no = $param['next_card_no'];
		
        $response = array();

		// echo "---email---".$email."--<br>";
		// echo "---password---".$password."--<br>";		
		// echo "---email---".$email."--<br>";
        // First check if user already existed in db
		
        if (!$this->isUserExists($email)){
           
			// echo "---name---".$param['name']."--<br>";
		   
			// print_r($param);
			
			
				$name=explode(" ",$name);
				$First_name=$name[0];
				$Last_name=$name[1];				
				$source='API';
				$today=date('Y-m-d H:i:s');
				
				$pin = $this->getRandomString(4);
				$Tier_id = $this->getFirstLevelTier();
			
			$stmt = $this->conn->prepare("INSERT INTO igain_enrollment_master(First_name,Last_name,Phone_no,User_email_id,User_pwd,User_activated,Company_id,User_id,joined_date,source,pinno,Tier_id,Card_id) values(?, ?, ?, ?, ?, 1, ?, 1, ?, ?, ?, ?, ?)");
            // $stmt->bind_param("ssssssss", $First_name,$Last_name,$phone,$email,$password, $_SESSION['Company_id'],$today,'API');
            $stmt->bind_param("sssssssssss", $First_name,$Last_name,$phone,$email,$password,$_SESSION['company_id'],$today,$source,$pin,$Tier_id,$next_card_no);
			
            $result = $stmt->execute();
			// echo debugPDO($stmt, $param);

			// print_r($result);
            $stmt->close();

            // Check for successful insertion
            if ($result) {
				
				
				
                // User successfully inserted
                return USER_CREATED_SUCCESSFULLY;
				
            } else {
				
                // Failed to create user
                return USER_CREATE_FAILED;
				
            }
			
        } else {
			
            // User with same email already existed in the db
            return USER_ALREADY_EXISTED;
        }

        return $response;
    }
	
    public function checkLogin($email, $password) {
		
		
		$email = $this->phash->string_encrypt($email);		
		$password = $this->phash->string_encrypt($password);		
		// echo "----email------".$email."-----<br>";
		// echo "----password------".$password."-----<br>";
		// echo "--checkLogin--company_id------".$_SESSION['company_id']."-----<br>";
		
		
		
        // fetching user by email
        $stmt = $this->conn->prepare("SELECT User_email_id FROM igain_enrollment_master WHERE User_email_id = ? AND User_pwd = ? AND Company_id = ?");

        $stmt->bind_param("sss", $email,$password,$_SESSION['company_id']);
		
        $stmt->execute();
       // $stmt->debugDumpParams();
	   

        $stmt->bind_result($User_email_id);

        $stmt->store_result();
		// echo "----last_query------".$stmt->execute;
		// echo "----last_query------".print_r($stmt->execute);

        if ($stmt->num_rows > 0) {
            // Found user with the email
            // Now verify the password

            $stmt->fetch();

            $stmt->close();

			 return TRUE;
			 
            /* if (PassHash::check_password($password_hash, $password)) {
                // User password is correct
                return TRUE;
            } else {
                // user password is incorrect
                return FALSE;
            } */
			
        } else {
            $stmt->close();

            // user not existed with the email
            return FALSE;
        }
    }
    private function isUserExists($email) {
		// echo "---isUserExists---";
        // $stmt = $this->conn->prepare("SELECT id from users WHERE email = ?");
		$stmt = $this->conn->prepare("SELECT Enrollement_id FROM igain_enrollment_master WHERE User_email_id = ? and Company_id = ? and User_id=1");
        $stmt->bind_param("ss", $email,$_SESSION['company_id']);
        $stmt->execute();
        $stmt->store_result();
        $num_rows = $stmt->num_rows;
		// echo "---num_rows---".$num_rows."----<br>";
        $stmt->close();
        return $num_rows > 0;
    }
    public function getUserByEmail($email) {
		// echo"-----getUserByEmail-------";
		$email = $this->phash->string_encrypt($email);	
		// SELECT password_hash FROM igain_enrollment_master WHERE User_email_id,User_pwd = ?,?
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,total_purchase,Total_topup_amt,Total_reddems FROM igain_enrollment_master WHERE User_email_id = ? and Company_id = ? and User_id=1");
        $stmt->bind_param("ss", $email,$_SESSION['company_id']);
        print_r($stmt->error);
        if ($stmt->execute()) {
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name, $User_email_id,$Card_id,$User_pwd,$pinno,$Current_balance,$Blocked_points,$Debit_points,$total_purchase,$Total_topup_amt,$Total_reddems);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["email"] = $this->phash->string_decrypt($User_email_id);
            $user["Membership_ID"] = $Card_id;
            $user["Password"] = $this->phash->string_decrypt($User_pwd);
            $user["Pin"] = $pinno;           
            $user["Current_balance"] = $Current_balance;
            $user["Blocked_points"] = $Blocked_points;
            $user["Debit_points"] = $Debit_points;
            $user["total_purchase"] = $total_purchase;
            $user["Total_topup_amt"] = $Total_topup_amt;
            $user["Total_reddems"] = $Total_reddems;

            /* -($Blocked_points+$Debit_points) */

            $stmt->close();
            return $user;
        } else {
            return NULL;
        }
    }
	public function getUserByMembership($membershipid,$phoneno) {
		
		$phoneno=$this->phash->string_encrypt($phoneno);
		
		/* echo "---membershipid---".$membershipid."----<br>";
		echo "---phoneno---".$phoneno."----<br>";
		echo "---company_id---".$_SESSION['company_id']."----<br>"; */
		
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt FROM igain_enrollment_master WHERE Card_id = ?  AND Company_id = ? AND User_id=1");
        $stmt->bind_param("ss", $membershipid,$_SESSION['company_id']);

		$stmt->execute();
        $stmt->store_result();

            /* echo "---num_rows---".$stmt->num_rows."----<br>"; */
		
		  /* echo "SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt FROM igain_enrollment_master WHERE Card_id = ".$membershipid." and Company_id = ".$_SESSION['company_id']." and User_id=1";  */

        if ($stmt->num_rows > 0) {
            
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name, $User_email_id,$Card_id,$User_pwd,$pinno,$Current_balance,$Blocked_points,$Debit_points,$Phone_no,$Total_reddems,$total_purchase,$Total_topup_amt);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["Membership_ID"] = $Card_id;
            $user["Password"] = $this->phash->string_decrypt($User_pwd);
            $user["Pin"] = $pinno;
            $user["Current_balance"] = $Current_balance;
            $user["Blocked_points"] = $Blocked_points;
            $user["Debit_points"] = $Debit_points;
            $user["Total_reddems"] = $Total_reddems;
            $user["total_purchase"] = $total_purchase;
            $user["Total_topup_amt"] = $Total_topup_amt;
            $user["email"] = $this->phash->string_decrypt($User_email_id);
            $user["phoneno"] = $this->phash->string_decrypt($Phone_no);
            $stmt->close(); 
            return $user;
        } else {
            return NULL;
        }
    }
	public function validateBonus($id,$Membership_ID) {
		// echo "-validateBonus--id---".$id."----<br>";
		// echo "---Membership_ID---".$Membership_ID."----<br>";
        $stmt = $this->conn->prepare("SELECT Trans_id FROM igain_transaction WHERE Trans_type = 1 and Company_id = ? and Enrollement_id=? and Card_id=?");
        $stmt->bind_param("sss", $_SESSION['company_id'],$id,$Membership_ID);
        $stmt->execute();
		// echo"---queryString----".$stmt->queryString();
		
        $stmt->store_result();
		// var_dump($stmt->getTrace());
        $num_rows = $stmt->num_rows;
		// echo "---num_rows---".$num_rows."----<br>";
		
        $stmt->close();
        return $num_rows > 0;
    }
	public function insertTopup($TransPara) {

		/* print_r($TransPara);
        echo"------<@@@@@@@@@@@>-----"; */

		$stmt = $this->conn->prepare("INSERT INTO igain_transaction (Trans_type,Company_id,Trans_date,Topup_amount,Remarks,Card_id,Seller_name,Seller,Enrollement_id,Bill_no,remark2) values(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
		$stmt->bind_param("sssssssssss",$TransPara['Trans_type'],$TransPara['Company_id'],$TransPara['Trans_date'],$TransPara['Topup_amount'],$TransPara['Remarks'],$TransPara['Card_id'],$TransPara['Seller_name'],$TransPara['Seller'],$TransPara['Enrollement_id'],$TransPara['Bill_no'],$TransPara['remark2']);			
		$result = $stmt->execute();
        // $stmt->store_result();
		$stmt->close();
		/* print_r($result); */
		if($result) {
			return true;
			
		} else {
			return false;			
		}			
        // return $response;
    }
    public function getMemberDetails($membershipid,$phoneno) {
		
		$phoneno=$this->phash->string_encrypt($phoneno);
		
		/* echo "---membershipid---".$membershipid."----<br>";
		echo "---phoneno---".$phoneno."----<br>";
		echo "---company_id---".$_SESSION['company_id']."----<br>"; */
		
        $stmt = $this->conn->prepare("SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Tier_name,igain_state_master.name as state_name,igain_city_master.name as city_name,igain_country_master.name as country_name FROM igain_enrollment_master  
        JOIN igain_tier_master ON igain_tier_master. Tier_id =igain_enrollment_master.Tier_id  
        LEFT JOIN igain_country_master ON igain_enrollment_master.Country = igain_country_master.id
        LEFT JOIN igain_state_master ON igain_enrollment_master.State = igain_state_master.id
        LEFT JOIN igain_city_master ON igain_enrollment_master.City = igain_city_master.id
        WHERE igain_enrollment_master.Card_id = ?  AND igain_enrollment_master.Company_id = ? AND User_id=1");
        $stmt->bind_param("ss", $membershipid,$_SESSION['company_id']);

		$stmt->execute();
        $stmt->store_result();

        //  echo "---num_rows---".$stmt->num_rows."----<br>";
		
		/*  echo "SELECT Enrollement_id, First_name, Last_name, User_email_id,Card_id,User_pwd,pinno,Current_balance,Blocked_points,Debit_points,Phone_no,Total_reddems,total_purchase,Total_topup_amt,Tier_name,igain_state_master.name as state_name,igain_city_master.name as city_name,igain_country_master.name as country_name FROM igain_enrollment_master  
         JOIN igain_tier_master ON igain_tier_master. Tier_id =igain_enrollment_master.Tier_id  
         LEFT JOIN igain_country_master ON igain_enrollment_master.Country = igain_country_master.id
         LEFT JOIN igain_state_master ON igain_enrollment_master.State = igain_state_master.id
         LEFT JOIN igain_city_master ON igain_enrollment_master.City = igain_city_master.id
         WHERE igain_enrollment_master.Card_id = 2019000000187  AND igain_enrollment_master.Company_id = 3 AND User_id=1";  */

        if ($stmt->num_rows > 0) {
            
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt->bind_result($Enrollement_id, $First_name, $Last_name, $User_email_id,$Card_id,$User_pwd,$pinno,$Current_balance,$Blocked_points,$Debit_points,$Phone_no,$Total_reddems,$total_purchase,$Total_topup_amt,$Tier_name,$state_name,$city_name,$country_name);			
            $stmt->fetch();
            $user = array();
            $user["id"] = $Enrollement_id;
            $user["fname"] = $First_name;
            $user["lname"] = $Last_name;
            $user["Membership_ID"] = $Card_id;
            $user["Password"] = $this->phash->string_decrypt($User_pwd);
            $user["Pin"] = $pinno;
            $user["Current_balance"] = $Current_balance;
            $user["Blocked_points"] = $Blocked_points;
            $user["Debit_points"] = $Debit_points;
            $user["Total_reddems"] = $Total_reddems;
            $user["total_purchase"] = $total_purchase;
            $user["Total_topup_amt"] = $Total_topup_amt;
            $user["Tier_name"] = $Tier_name;
            $user["state_name"] = $state_name;
            $user["city_name"] = $city_name;
            $user["country_name"] = $country_name;
            $user["email"] = $this->phash->string_decrypt($User_email_id);
            $user["phoneno"] = $this->phash->string_decrypt($Phone_no);
            $stmt->close(); 
            return $user;
        } else {
            return NULL;
        }
    }  
    public function get_cust_total_gain_points($Enrollement_id,$membershipid){

       /*  echo "--get_cust_total_gain_points -Enrollement_id---".$Enrollement_id."----<br>";
        echo "---membershipid---".$membershipid."----<br>";
        echo "---company_id---".$_SESSION['company_id']."----<br>"; */

        $stmt1 = $this->conn->prepare("SELECT SUM(Loyalty_pts) as Total_gained_points FROM igain_transaction as IT  
        JOIN igain_enrollment_master as IE ON IT.Enrollement_id=IE.Enrollement_id 
        WHERE IT.Trans_type IN('2','12') AND IT.Voucher_status NOT IN('18','19','21','22','23') AND IT.Enrollement_id = ?  AND IT.Company_id = ? AND IT.Card_id=?");
       
        $stmt1->bind_param("sss", $Enrollement_id,$_SESSION['company_id'],$membershipid);
		$stmt1->execute();
        $stmt1->store_result();
       
       /*  echo " SUM(Loyalty_pts) as Total_gained_points FROM igain_transaction as IT  
        JOIN igain_enrollment_master as IE','IT.Enrollement_id=IE.Enrollement_id 
        WHERE IT.Trans_type IN('2','12') AND IT.Voucher_status NOT IN('18','19','21','22','23') AND IT.Enrollement_id = ?  AND IT.Company_id = ? AND IT.Card_id=?----<br>";
	
         echo "---num_rows--2222---".$stmt1->num_rows."----<br>";
         print_r($stmt1); */
         
        if ($stmt1->num_rows > 0) {
            
            // $user = $stmt->get_result()->fetch_assoc();
            $stmt1->bind_result($Total_gained_points);			
            $stmt1->fetch();
            $points = array();
            $points["Total_gained_points"] = $Total_gained_points;
            $stmt1->close(); 
            return $points;
        } else {
            return NULL;
        }

    }

}

?>
