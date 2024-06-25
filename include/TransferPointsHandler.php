<?php
class TransferPointsHandler {

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
	public function getPointhistory($Enrollement_id,$Card_id,$Company_id) 
	{
		$Todays_date=date("Y-m-d");
		
        $stmt = $this->conn->prepare("SELECT CONCAT(E.First_name,' ',E.Last_name) as Transfer_to_member,T.Card_id2,Transfer_points,T.Trans_date,T.Trans_type FROM igain_transaction as T JOIN igain_enrollment_master as E ON T.Card_id2 = E.Card_id WHERE T.Company_id = ? and T.Card_id = ? AND T.Trans_type  = 8 ORDER BY T.Trans_id DESC");
		
        $stmt->bind_param("ss", $Company_id,$Card_id);
		
        if ($stmt->execute()) 
		{
			$result = $stmt->get_result();
			while($row = $result->fetch_assoc()) 
			{
				$Pointhistory[] = $row;
			}
			return $Pointhistory;
        }
		else 
		{
            return NULL;
        } 
    }
    

}
?>