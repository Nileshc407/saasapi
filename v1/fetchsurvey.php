<?php
require_once '../include/DbHandler.php';
require_once '../include/UserHandler.php';
require_once '../include/CompHandler.php';
require_once '../include/PassHash.php';
require '.././libs/Slim/Slim.php';


use lib\Slim\Middleware\SessionCookie;
//session_start();
error_reporting(0);
\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim(
	array(
		'cookies.encrypt' => true,
		'cookies.secret_key' => 'my_secret_key',
		'cookies.cipher' => MCRYPT_RIJNDAEL_256,
		'cookies.cipher_mode' => MCRYPT_MODE_CBC
    )
);

$app->add(new \Slim\Middleware\SessionCookie(array(
    'expires' => '20 minutes',
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => false,
    'name' => 'slim_session',
    'secret' => '',
    'cipher' => MCRYPT_RIJNDAEL_256,
    'cipher_mode' => MCRYPT_MODE_CBC
)));



// User id from db - Global Variable
$user_id = NULL;
$Company_id = NULL;

/**
 * Adding Middle Layer to authenticate every request
 * Checking if the request has valid api key in the 'Authorization' header
 */
function authenticate(\Slim\Route $route) {
	
	
    // Getting request headers
    $headers = apache_request_headers();
    $response = array();
    $app = \Slim\Slim::getInstance();
	
	$app->config('debug', true);
	
	
	 

    // Verifying Authorization Header
    if (isset($headers['Authorization'])) {
		
        $comp = new CompHandler();

        // get the api key
        $api_key = $headers['Authorization'];
        // validating api key
        if (!$comp->isValidApiKey($api_key)) {
           
			// api key is not present in users table
            $response["error"] = true;
            $response["message"] = "Access Denied. Invalid Api key";
            // $response["message"] =INVALID_KEY;
            echoRespnse(401, $response);
            $app->stop();			
			
        } else {
			
            global $Company_id;
            // get user primary key id
            // $Company_id = $comp->getCompanyDetails($api_key);
            $result = $comp->getCompanyDetails($api_key);
			// print_r($result);
			// fetch task
            // $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
				
				// $response["error"] = false;
				$_SESSION["company_id"] = $result["Company_id"];
				$_SESSION["company_name"] = $result["Company_name"];
				$_SESSION["card_decsion"] = $result["card_decsion"];
				$_SESSION["next_card_no"] = $result["next_card_no"];
				$_SESSION["joining_bonus"] = $result["Joining_bonus"];
				$_SESSION["joining_points"] = $result["Joining_bonus_points"];
				$_SESSION["Website"] = $result["Website"];
				
                // echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Invalid API Username";
                echoRespnse(404, $response);
            }
			
				session_cache_limiter(false);			
				// $_SESSION['Company_id'] =  $Company_id;			
				// die;
				
        }
		
    } else {
		
        // api key is missing in header
        $response["error"] = true;
        $response["message"] = "Api key is misssing";
        echoRespnse(400, $response);
        $app->stop();
    }
	
	
	// echo"----Company_id--in authenticate---".$_SESSION['company_id'];
}

/**
 * ----------- METHODS WITHOUT AUTHENTICATION ---------------------------------
 */
/**
 * User Registration
 * url - /register
 * method - POST
 * params - name, email, password
 */
$app->post('/fetchsurvey','authenticate', function() use ($app) {
           
		   // echo "register";
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			// check for required params
            verifyRequiredParams(array('name', 'email', 'password'),$request_array);

            $response = array();

            // reading post params
            /* $name = $app->request->post('name');
            $email = $app->request->post('email');
            $password = $app->request->post('password'); */
			$param['name'] = $request_array['name'];
			$param['phone'] = $request_array['phone'];
            $param['email'] = $request_array['email'];
            $param['password'] = $request_array['password'];
            $param['next_card_no'] = $_SESSION["next_card_no"];
			$param['Company_name'] = $_SESSION["company_name"];
			$param['Website'] = $_SESSION["Website"];
			$param['Loyalty_program_name'] = $_SESSION["company_name"];
			
			
			
			
			// require_once dirname(__FILE__) . '/PassHash.php';
			$phash = new PassHash();
			$dbHandlerObj = new DbHandler();
			
			// echo "email---".$param['email']."----<br>";
		
			// $email = $phash->string_decrypt($param['email']);
			
			 // echo "email---".$email."----<br>";
            // validating email address
            validateEmail($param['email']);
			
			

            $userObj = new UserHandler();
            $res = $userObj->createUser($param);
			
			
			
			
            if ($res == USER_CREATED_SUCCESSFULLY) {
				
					
					$NextMembership = $userObj->setNextMembershipID($param['next_card_no']);
					$user = $userObj->getUserByEmail($param['email']);
					
					
					 if ($user != NULL) {
					
						 // echo "login-Company_id--".$_SESSION['company_id'];					 
						
						$response["error"] = false;
						$response['id'] = $user['id'];
						$response['fname'] = $user['fname'];
						$response['lname'] = $user['lname'];
						$response['email'] = $user['email'];
						$response['User_name'] = $user['email'];
						$response['Membership_ID'] = $user['Membership_ID'];
						$response['Password'] = $user['Password'];	
						$response['Pin'] = $user['Pin'];
						
						
						
						$param["error"] = false;
						$param['id'] = $user['id'];
						$param['First_name'] = $user['fname'];
						$param['Last_name'] = $user['lname'];
						$param['email'] = $user['email'];
						$param['User_name'] = $user['email'];
						$param['Membership_ID'] = $user['Membership_ID'];
						$param['Password'] = $user['Password'];	
						$param['Pin'] = $user['Pin'];
						$param['Email_template_id'] =1; 
						
						$email = $dbHandlerObj->sendEmail($param); 
						
						
						
					} else {
						
						// unknown error occurred
						$response['error'] = true;
						$response['message'] = "An error occurred. Please try again";
					}
						
								
					
								
					
					
					
						
					 
					 // var_dump($res);
					// die;
					 
					
				
				
				
                $response["error"] = false;
                $response["message"] = "You are successfully registered";
            } else if ($res == USER_CREATE_FAILED) {
                $response["error"] = true;
                $response["message"] = "Oops! An error occurred while registereing";
            } else if ($res == USER_ALREADY_EXISTED) {
                $response["error"] = true;
                $response["message"] = "Sorry, this email already existed";
            }
            // echo json response
            echoRespnse(201, $response);
        });

/**
 * User Login
 * url - /login
 * method - POST
 * params - email, password
 */
$app->post('/login','authenticate', function() use ($app) {			
			
			// echo "login---";
			
			
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
            // check for required params
            verifyRequiredParams(array('email', 'password'),$request_array);

            // reading post params
           /*  $email = $app->request()->post('email');
            $password = $app->request()->post('password'); */
			
			// reading post params
			$email = $request_array['email'];
            $password = $request_array['password'];
		
            $response = array();

            $userObj = new UserHandler();
			  
            // check for correct email and password
            if ($userObj->checkLogin($email, $password)) {
                // get the user by email
                $user = $userObj->getUserByEmail($email);

                if ($user != NULL) {
					
					 // echo "login-Company_id--".$_SESSION['company_id'];					 
					
                    $response["error"] = false;
                    $response['id'] = $user['id'];
                    $response['fname'] = $user['fname'];
                    $response['lname'] = $user['lname'];
                    $response['email'] = $user['email'];
					
                } else {
					
                    // unknown error occurred
                    $response['error'] = true;
                    $response['message'] = "An error occurred. Please try again";
                }
				
            } else {
                // user credentials are wrong
                $response['error'] = true;
                $response['message'] = 'Login failed. Incorrect credentials';
            }

            echoRespnse(200, $response);
    });
		
		
		

/*
 * ------------------------ METHODS WITH AUTHENTICATION ------------------------
 */

/**
 * Listing all tasks of particual user
 * method GET
 * url /tasks          
 */
$app->get('/tasks', 'authenticate', function() use ($app) {
	
			
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
			
			
			/* print_r($request_array);			
			die; */
	
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetching all user tasks
            $result = $db->getAllUserTasks($user_id);

            $response["error"] = false;
            $response["tasks"] = array();

            // looping through result and preparing tasks array
            while ($task = $result->fetch_assoc()) {
                $tmp = array();
                $tmp["id"] = $task["id"];
                $tmp["task"] = $task["task"];
                $tmp["status"] = $task["status"];
                $tmp["createdAt"] = $task["created_at"];
                array_push($response["tasks"], $tmp);
            }

            echoRespnse(200, $response);
        });

/**
 * Listing single task of particual user
 * method GET
 * url /tasks/:id
 * Will return 404 if the task doesn't belongs to user
 */
$app->get('/tasks/:id', 'authenticate', function($task_id) {
            global $user_id;
            $response = array();
            $db = new DbHandler();

            // fetch task
            $result = $db->getTask($task_id, $user_id);

            if ($result != NULL) {
                $response["error"] = false;
                $response["id"] = $result["id"];
                $response["task"] = $result["task"];
                $response["status"] = $result["status"];
                $response["createdAt"] = $result["created_at"];
                echoRespnse(200, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "The requested resource doesn't exists";
                echoRespnse(404, $response);
            }
        });

/**
 * Creating new task in db
 * method POST
 * params - name
 * url - /tasks/
 */
$app->post('/tasks', 'authenticate', function() use ($app) {
	
	
			$json=$app->request->getbody();
			// to get an array so try following..
			$request_array=json_decode($json,true);
		
            // check for required params
            verifyRequiredParams(array('task'),$request_array);

            $response = array();
            /* $task = $app->request->post('task'); */
			
			$task = $request_array['task'];

            global $user_id;
            $db = new DbHandler();

            // creating new task
            $task_id = $db->createTask($user_id, $task);

            if ($task_id != NULL) {
                $response["error"] = false;
                $response["message"] = "Task created successfully";
                $response["task_id"] = $task_id;
                echoRespnse(201, $response);
            } else {
                $response["error"] = true;
                $response["message"] = "Failed to create task. Please try again";
                echoRespnse(200, $response);
            }            
        });

/**
 * Updating existing task
 * method PUT
 * params task, status
 * url - /tasks/:id
 */
$app->put('/tasks/:id', 'authenticate', function($task_id) use($app) {
            // check for required params
            verifyRequiredParams(array('task', 'status'));

            global $user_id;            
            $task = $app->request->put('task');
            $status = $app->request->put('status');

            $db = new DbHandler();
            $response = array();

            // updating task
            $result = $db->updateTask($user_id, $task_id, $task, $status);
            if ($result) {
                // task updated successfully
                $response["error"] = false;
                $response["message"] = "Task updated successfully";
            } else {
                // task failed to update
                $response["error"] = true;
                $response["message"] = "Task failed to update. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * Deleting task. Users can delete only their tasks
 * method DELETE
 * url /tasks
 */
$app->delete('/tasks/:id', 'authenticate', function($task_id) use($app) {
            global $user_id;

            $db = new DbHandler();
            $response = array();
            $result = $db->deleteTask($user_id, $task_id);
            if ($result) {
                // task deleted successfully
                $response["error"] = false;
                $response["message"] = "Task deleted succesfully";
            } else {
                // task failed to delete
                $response["error"] = true;
                $response["message"] = "Task failed to delete. Please try again!";
            }
            echoRespnse(200, $response);
        });

/**
 * Verifying required params posted or not
 */
function verifyRequiredParams($required_fields,$request_array) {
    $error = false;
    $error_fields = "";
    $request_params = array();
    // $request_params = $_REQUEST;
    $request_params = $request_array;
    // Handling PUT request params
    if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
        $app = \Slim\Slim::getInstance();
        parse_str($app->request()->getBody(), $request_params);
    }
    foreach ($required_fields as $field) {
        if (!isset($request_params[$field]) || strlen(trim($request_params[$field])) <= 0) {
            $error = true;
            $error_fields .= $field . ', ';
        }
    }

    if ($error) {
        // Required field(s) are missing or empty
        // echo error json and stop the app
        $response = array();
        $app = \Slim\Slim::getInstance();
        $response["error"] = true;
        $response["message"] = 'Required field(s) ' . substr($error_fields, 0, -2) . ' is missing or empty';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Validating email address
 */
function validateEmail($email) {
    $app = \Slim\Slim::getInstance();
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response["error"] = true;
        $response["message"] = 'Email address is not valid';
        echoRespnse(400, $response);
        $app->stop();
    }
}

/**
 * Echoing json response to client
 * @param String $status_code Http response code
 * @param Int $response Json response
 */
function echoRespnse($status_code, $response) {
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>