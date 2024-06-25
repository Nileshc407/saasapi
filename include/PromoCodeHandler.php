<?php
class PromoCodeHandler {

    private $conn;
    private $decrypt;
    private $encrypt;

    function __construct() {

		// echo"---in---DbHandler--inside class--11--";
        require_once dirname(__FILE__) . '/DbConnect.php';
		// echo"---in---DbHandler---111-inside class--22--";
        // opening db connection
        $db = new DbConnect();
        $this->conn = $db->connect();

		require_once dirname(__FILE__) . '/PassHash.php';
		$this->phash = new PassHash();
		
			
    }
    public function getPomocodeDetails($promocode)
	{
		// echo"---getPomocodeDetails----";
		$today=date('Y-m-d H:i:s');
		$stmt = $this->conn->prepare("SELECT igain_promo_campaign_child.Promo_code,igain_promo_campaign_child.Promo_code_status,igain_promo_campaign.From_date,igain_promo_campaign.To_date,igain_promo_campaign_child.Points FROM igain_promo_campaign_child JOIN igain_promo_campaign ON igain_promo_campaign.Campaign_id =igain_promo_campaign_child.Campaign_id  WHERE igain_promo_campaign_child.Active_flag=1 AND igain_promo_campaign_child.Company_id = ?  AND igain_promo_campaign_child.Promo_code =? AND '".$today."' BETWEEN From_date  AND To_date");


		/*  echo "SELECT igain_promo_campaign_child.Promo_code,igain_promo_campaign_child.Promo_code_status,igain_promo_campaign.From_date,igain_promo_campaign.To_date FROM igain_promo_campaign_child JOIN
		igain_promo_campaign ON igain_promo_campaign.Campaign_id =igain_promo_campaign_child.Campaign_id WHERE
		igain_promo_campaign_child.Active_flag=1 AND igain_promo_campaign_child.Company_id = ".$_SESSION['company_id']." AND igain_promo_campaign_child.Promo_code ='".$promocode."' AND '2021-08-25 09:45:29'
		BETWEEN From_date AND To_date";  */ 



        $stmt->bind_param("ss", $_SESSION['company_id'],$promocode);		
        if ($stmt->execute()) {

            $stmt->bind_result($Promo_code, $Promo_code_status, $From_date, $To_date,$Points);			
            $stmt->fetch();
			
            $res = array();
            $res["Promo_code"] = $Promo_code;
            $res["Promo_code_status"] = $Promo_code_status;
            $res["From_date"] = $From_date;
            $res["To_date"] = $To_date;
            $res["Points"] = $Points;
			// print_r($res); 
			return $res;
			
        } else {

            return NULL;
        }
	}
    public function updateMemberhobbies($MemberPara,$where)
	{
			
		$cols = array(); 
		foreach($MemberPara as $key=>$val) {
			$cols[] = "$key = '$val'";
		}
		
		$sql = "UPDATE igain_hobbies_interest SET " . implode(', ', $cols) . " WHERE Hobbie_id=$where";		
		// print_r($sql);		
		$stmt = $this->conn->prepare($sql);
        // $stmt->bind_param("ssi", $MemberPara['Total_topup'],$MemberPara['Total_topup'], $MemberPara['id']);
		$stmt->execute();
		$num_affected_rows = $stmt->affected_rows;
        $stmt->close();
        return $num_affected_rows > 0;
	}
    public function insertMemberhobbies($TransPara)
	{
			
		$key = array_keys($TransPara);
		$val = array_values($TransPara);
		$sql = "INSERT INTO igain_hobbies_interest (" . implode(', ', $key) . ") "
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
	}
    public function getHistory($Enrollement_id,$Card_id,$Company_id)
	{
		$stmt = $this->conn->prepare("SELECT Topup_amount,Trans_type,Trans_date,remark3 FROM igain_transaction WHERE Card_id = ? and Enrollement_id = ? and Company_id = ? and Trans_type = 7 ORDER BY Trans_id DESC");
		
		$stmt->bind_param("sss",$Card_id,$Enrollement_id,$Company_id);
		if($stmt->execute())
		{ 
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc())
			{
				$history[]=$row;
			}
			return $history;
			
		}
		else
		{
			return Null;
		}
	}
}
?>