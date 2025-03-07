<?php
/**
 * Class to handle all Company operations
 * This class will have CRUD methods for database tables
 *
 * @author Ravi Tamada
 * @link URL Tutorial link
 */
class CompHandler {

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

    public function getCompanyDetails($api_key) 
	{
		// $api_key1 = $this->phash->string_encrypt($api_key);	
		$api_key = $this->phash->string_decrypt($api_key);		
		// echo "----api_key------".$api_key."-----<br>";
		
		 
        $stmt = $this->conn->prepare("SELECT Company_id,Company_name,Redemptionratio,Alise_name,card_decsion,next_card_no,Joining_bonus,Joining_bonus_points,Website,phonecode,Facebook_link,Twitter_link,Linkedin_link,Googlplus_link,Cust_apk_link,Cust_ios_link,Country,Currency_name FROM igain_company_master JOIN igain_country_master ON igain_country_master.id=igain_company_master.Country WHERE Company_id = ? and Activated = 1");
	// echo "----stmt------".$stmt."-----<br>";
		
        //$stmt = $this->conn->prepare("SELECT id FROM users WHERE api_key = ?");
		
        $stmt->bind_param("s", $api_key);	
		
        if ($stmt->execute()){
			
			$res = array();
            $stmt->bind_result($Company_id,$Company_name,$Redemptionratio,$Alise_name,$card_decsion,$next_card_no,$Joining_bonus,$Joining_bonus_points,$Website,$phonecode,$Facebook_link,$Twitter_link,$Linkedin_link,$Googlplus_link,$Cust_apk_link,$Cust_ios_link,$Country,$Currency_name);
            $stmt->fetch();
			
            // $stmt->bind_result($id, $task, $status, $created_at);
            // TODO
            // $task = $stmt->get_result()->fetch_assoc();
            //$stmt->fetch();
         
			$_SESSION["company_id"] = $Company_id;
            $_SESSION["company_name"] = $Company_name;
            $_SESSION["redemption_ratio"] = $Redemptionratio;
            $_SESSION["loyalty_program_name"] = $Company_name;
            $_SESSION["Alise_name"] = $Alise_name;
            $_SESSION["card_decsion"] = $card_decsion;
            $_SESSION["next_card_no"] = $next_card_no;
            $_SESSION["Joining_bonus"] = $Joining_bonus;
            $_SESSION["joining_points"] = $Joining_bonus_points;
            $_SESSION["website"] = $Website;
            $_SESSION["phonecode"] = $phonecode;
            $_SESSION["facebook_link"] = $Facebook_link;
            $_SESSION["twitter_link"] = $Twitter_link;
            $_SESSION["linkedin_link"] = $Linkedin_link;
            $_SESSION["googlplus_link"] = $Googlplus_link;
            $_SESSION["Cust_apk_link"] = $Cust_apk_link;
            $_SESSION["Cust_ios_link"] = $Cust_ios_link;
            $_SESSION["Country_id"] = $Country;
			$_SESSION["Currency_name"] = $Currency_name;


           /*  $_SESSION["company_id"] = $result["Company_id"];
            $_SESSION["company_name"] = $result["Company_name"];
            $_SESSION["card_decsion"] = $result["card_decsion"];
            $_SESSION["next_card_no"] = $result["next_card_no"];
            $_SESSION["joining_bonus"] = $result["Joining_bonus"];
            $_SESSION["joining_points"] = $result["Joining_bonus_points"];
            $_SESSION["website"] = $result["Website"];
            $_SESSION["phonecode"] = $result["phonecode"];

            $_SESSION["company_id"] = $Company_id; */


            // $stmt->close();
			
			 $stmt->close();
            // return $Company_id;
            // return $res;
            return true;
        } else {
            return NULL;
        }
    }
    /**
     * Validating user api key
     * If the api key is there in db, it is a valid key
     * @param String $api_key user api key
     * @return boolean
     */
    public function isValidApiKey($api_key) 
	{		
		$api_key = $this->phash->string_decrypt($api_key);		
		// echo "----api_key------".$api_key."-----<br>"; die;
		 
        $stmt = $this->conn->prepare("SELECT Company_id FROM igain_company_master WHERE Company_id = ?");
        $stmt->bind_param("s", $api_key);
        $stmt->execute();
	
		$stmt->store_result();
		$num_rows = $stmt->num_rows; 
		//echo $num_rows; exit;
		return $num_rows;	
    }

    /**
     * Generating random Unique MD5 String for user Api key
     */
    private function generateApiKey() {
        return md5(uniqid(rand(), true));
    }
}
?>