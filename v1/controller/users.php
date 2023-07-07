<?php
// Signing UP/Register a new user API    // API Endpoint:    POST /v1/users
// This class is used as an API to allow any user to sign up (register), in other words, create a user using a POST request (POST/users)
// Check the .htaccess file for the route
// Note: When dealing with an API with authentication, we send the 'access token' in the HTTP header with every request, but for the 'refresh token', we ever send it in the HTTP body only when we want to refresh a session.
require_once('db.php');
require_once('../model/Response.php');



// For anything to do with authentication (like creating user accounts, loggin in, logging out), we should use the $writeDB (not $readDB) (master database) to connect to database:
try { // We're going to use the $writeDB here because we're registering a new user into the database table i.e. We'll use the INSERT SQL statement, which means it's a WRITING operation to the database
    $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) { // If database connection fails
    error_log('Connection error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
    $response = new Response();
    $response->setHTTPStatusCode(500); // 500 is Internal Server Error
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}


// Handling OPTIONS HTTP request method (preflight request) for CORS:
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Methods: POST, OPTIONS'); // we define what HTTP methods to allow only for that endpoint (i.e.    POST /users    )
    header('Access-Control-Allow-Headers: Content-Type');  // we define what HTTP headers to allow only for that endpoint (i.e.    POST /users    )    // If it were the /tasks endpoint, we would add the 'Authorization' header too:    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Max-Age: 86400'); // 86400 seconds is 1 day, which is 24 hours
    $response = new Response();
    $response->setHTTPStatusCode(200); // 200 means Success or OK
    $response->setSuccess(true);
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}


// Route is:    POST /users    (Check the .htaccess file)
// Check if the HTTP request method is POST only, because we're going to handle CREATE (which matches to the method POST only) a new user only here, or else Request Method Is Not Allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // Any HTTP Request Method other than 'POST' is not allowed for this route/endpoint
    $response = new Response();
    $response->setHTTPStatusCode(405); // 405 means Request Method Not Allowed
    $response->setSuccess(false);
    $response->addMessage('Request method not allowed');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}


// Making sure that the content type of the HTTP request of the data sent to create a user (username, password, ...) is JSON type
if ($_SERVER['CONTENT_TYPE'] !== 'application/json') { // if the HTTP request header content type is not JSON or not provided in the HTTP Request
    $response = new Response();
    $response->setHttpStatusCode(400); // 400 means Bad Request/client error (because the client hasn't submitted the right type of data)
    $response->setSuccess(false);
    $response->addMessage('Content Type header not set to JSON');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}


// Getting and handling the data that is coming from the POST request:
$rawPostData = file_get_contents('php://input'); // Retrieve the raw HTTP request body data    // getting the POST HTTP Request BODY


// Validation
// Making sure the data submitted is valid JSON:
if (!$jsonData = json_decode($rawPostData)) {
    $response = new Response();
    $response->setHttpStatusCode(400); // 400 means Bad Request/client error (because the client hasn't submitted the right type of data)
    $response->setSuccess(false);
    $response->addMessage('Request body is not valid JSON');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}

// Validation
// Making sure the mandatory fields are filld in or provided (submitted) with the POST request:
// Mandatory fields are: fullname, username, password
if (!isset($jsonData->fullname) || !isset($jsonData->username) || !isset($jsonData->password)) {
    $response = new Response();
    $response->setHttpStatusCode(400); // 400 means client error
    $response->setSuccess(false);
    (!isset($jsonData->fullname) ? $response->addMessage('Full name not supplied') : false);
    (!isset($jsonData->username) ? $response->addMessage('Username not supplied')  : false);
    (!isset($jsonData->password) ? $response->addMessage('Password not supplied')  : false);
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}

// Validation: Making sure the POST submitted data is valid (not blank string, doesn't exceed 255 characters (as we specified while we created the `users` database table)):
if (strlen($jsonData->fullname) < 1 || strlen($jsonData->fullname) > 255 || strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) { // Check if fullname is not an empty string (i.e.    {"fullnanme": ""}    ), or exceeds 255 characters (as we specified when we created the database table)
    $response = new Response();
    $response->setHttpStatusCode(400); // 400 means client error
    $response->setSuccess(false);
    (strlen($jsonData->fullname) < 1   ? $response->addMessage('Full name cannot be blank')                       : false);
    (strlen($jsonData->fullname) > 255 ? $response->addMessage('Full name cannot be greater than 255 characters') : false);
    (strlen($jsonData->username) < 1   ? $response->addMessage('Username cannot be blank')                        : false);
    (strlen($jsonData->username) > 255 ? $response->addMessage('Username cannot be greater than 255 characters')  : false);
    (strlen($jsonData->password) < 1   ? $response->addMessage('Password cannot be blank')                        : false);
    (strlen($jsonData->password) > 255 ? $response->addMessage('Password cannot be greater than 255 characters')  : false);
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}

// Doing some tidying up for the data submitted (trimming extra spaces, stripping whitespaces off, ...)
$fullname = trim($jsonData->fullname);
$username = trim($jsonData->username);
$password = trim($jsonData->password);

// Making sure the username doesn't already exist and is not currently used by another user i.e. not repeated (also, we specified that username is UNIQUE key when we created the database table `users`):
try {
    $query = $writeDB->prepare('SELECT id FROM tblusers WHERE username = :username'); // We're using the $writeDB here because we're registering a new user into the database table i.e. We'll use the INSERT SQL statement, which means it's a WRITING operation to the database
    // $query = $writeDB->prepare('SELECT COUNT(username) FROM tblusers WHERE username = :username'); // We're using the $writeDB here because we're registering a new user into the database table i.e. We'll use the INSERT SQL statement, which means it's a WRITING operation to the database
    $query->bindParam(':username', $username, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount !== 0) { // If it's anything other than zero, this means the username already exists (used by another user)
        $response = new Response();
        $response->setHTTPStatusCode(409); // 409 means Conflict (the data provided is conflicting with something else)
        $response->setSuccess(false);
        $response->addMessage('Username already exists');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT); // hash the password

    // Inserting the user request into database:
    $query = $writeDB->prepare('INSERT INTO tblusers (fullname, username, password) VALUES (:fullname, :username, :password)'); // We're using the $writeDB here because we're registering a new user into the database table i.e. We'll use the INSERT SQL statement, which means it's a WRITING operation to the database
    $query->bindParam(':fullname', $fullname,        PDO::PARAM_STR);
    $query->bindParam(':username', $username,        PDO::PARAM_STR);
    $query->bindParam(':password', $hashed_password, PDO::PARAM_STR);

    $query->execute();

    $rowCount= $query->rowCount();

    if ($rowCount === 0) { // if the query fails
        // echo '<pre>', var_dump($ex), '</pre>';
        $response = new Response();
        $response->setHTTPStatusCode(500); // 500 Status Code is Internal Server Error
        $response->setSuccess(false);
        $response->addMessage('There was an issue creating a user account - please try again');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Returning the same data (except the hashed password) submitted by the user back to them again after being inserted into the database:
    $lastUserID = $writeDB->lastInsertId();

    $returnData = array(); // Create an empty array

    $returnData['user_id']  = $lastUserID;
    $returnData['fullname'] = $fullname;
    $returnData['username'] = $username;

    // Send a successful response:
    $response = new Response();
    $response->setHttpStatusCode(201); // Note: 201 means CREATED successfully and then returned
    $response->setSuccess(true);
    $response->addMessage('User created');
    $response->setData($returnData);
    $response->send();
    exit; // to guarantee exiting out of script after sending the response

} catch (PDOException $ex) { // If there's an error querying the database:
    // echo '<pre>', var_dump($ex), '</pre>'; // Display the exception
    error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
    $response = new Response();
    $response->setHTTPStatusCode(500); // 500 is Internal Server Error
    $response->setSuccess(false);
    $response->addMessage('There was an issue creating a user account - please try again');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}