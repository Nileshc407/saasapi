<?php

class PassHash {

    // blowfish
    private static $algo = '$2a';
    // cost parameter
    private static $cost = '$10';

    // mainly for internal use
    public static function unique_salt() {
        return substr(sha1(mt_rand()), 0, 22);
    }

    // this will be used to generate a hash
    public static function hash($password) {

        return crypt($password,self::$algo.self::$cost.'$'.self::unique_salt());
    }	
    // this will be used to compare a password against a hash
    public static function check_password($hash, $password) {
        $full_salt = substr($hash, 0, 29);
        $new_hash = crypt($password, $full_salt);
        return ($hash == $new_hash);
    }	
	public static function string_decrypt($string) {
       // echo"----string_decrypt----";
	   $cipher = "aes-256-gcm";
		$message = 'opensesame';		
		// echo "strlen--".strlen($string);
		$components = explode( ':', $string );

		//var_dump($components);

		$salt          = $components[0];
		$iv            = $components[1];
		$encrypted_msg = $components[2];
		$decrypted_msg = openssl_decrypt(
		  "$encrypted_msg", 'aes-256-cbc', "$salt:$message", null, $iv
		);
		if ($decrypted_msg === false ) {
			
			return false;
			// die;
		  // die("Unable to decrypt message! (check password) \n");
		}
		$msg = substr( $decrypted_msg, 41 );
		return $decrypted_msg;
	   
	   
    }
	public static function string_encrypt($string) {
       
	    // echo"----string_encrypt----";
	   $cipher = "aes-256-gcm";
		$message = 'opensesame';
		// Salt to add entropy to users' supplied passwords
		// Make sure to add complexity/length requirements to users passwords!
		// Note: This does not need to be kept secret
		$salt = "033ebc1f7e02174e4b386ee7981de53fa6adea5f";//sha1(mt_rand());

		// Initialization Vector, randomly generated and saved each time
		// Note: This does not need to be kept secret
		$iv = "906dc483564123d3";//substr(sha1(mt_rand()), 0, 16);

		//echo "\n Password: $password \n Salt: $salt \n IV: $iv\n";

		$encrypted = openssl_encrypt(
		  "$string", 'aes-256-cbc', "$salt:$message", null, $iv
		);
		$msg_bundle = "$salt:$iv:$encrypted";		
		return $msg_bundle;   
    }

}
?>
