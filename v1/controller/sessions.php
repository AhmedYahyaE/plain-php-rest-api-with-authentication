<?php
// This is the Authentication API (where a user can log in (POST), log out (DELETE) and update/refresh (PATCH) their 'access token' and 'refresh token')
// API Endpoints:    POST /v1/sessions    ,    PATCH /v1/sessions/{sessionid}    ,    DELETE /v1/sessions/{sessionid}
// This is a controller to handle the sessions: create a session (i.e. logging in or login), delete a session (i.e. logging out or logout), refresh a session (i.e. update a session) to get a new 'access token'
// This is 'Create user session API' or 'login or logging in' where we allow a user to use their username and password they have to create a session. This will return an 'access token' for the request and a 'refresh token' for when the 'access token' has expired. This allows you to get a new 'access token':
// Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script that runs every one or two days to clear out (delete) the expired sessions.
// Note: When dealing with an API with authentication, we send the 'access token' in the HTTP header with every request, but for the 'refresh token', we ever send it in the HTTP body only when we want to refresh a session.

require_once('db.php');
require_once('../model/Response.php');


// For anything to do with authentication (like creating user accounts, loggin in, logging out), we should use the $writeDB (not $readDB) (master database) to connect to database:
try {
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


// 'if statements' that will select the logic based on the route:
// Our routes here are two (two routes and three HTTP request methods (POST (create), DELETE (delete), PATCH (update)): /sessions which will be a POST request because it's going to be used to CREATE a session (i.e. login), or /sessions/{sessionid} e.g. /sessions/3 which will be a DELETE request because it's going to be used to DELETE a session (i.e. logging out or logout) Or a PATCH request to UPDATE/refresh a session (i.e. refresh the 'access token' to get a new one):
/*
    Our routes/endpoints:
        v1/sessions      is for    POST   - CREATE a session/log in
        v1/sessions/3    is for    DELETE - Log out a user
        v1/sessions/3    is for    PATCH  - Refresh session
*/


// If the {sessionid} Query String Parameter is provided / exists in the URL, the we handle either DELETE or PATCH requests (log out (DELETE) and refresh tokens (PATCH))    // API Endpoint:    DELETE or PATCH v1/sessions/34
if (array_key_exists('sessionid', $_GET)) { // the route/endpoint has a query string which contains '/sessions.php/?sessionid=5' which maps to /sessions/5 in our .htaccess file, for example /sessions.php?sessionid=3 (equivalent to or maps to /sessions/3 in .htaccess file) which is either DELETE request (logging out) or PATCH request (refresh session or access token)
    // Getting the session id from the URL:    // API Endpoint:    DELETE or PATCH v1/sessions/34
    $sessionid = $_GET['sessionid'];

    // Do some Validation check (make sure it's not blank and it's a numerical number (not text)):
    if ($sessionid === '' || !is_numeric($sessionid)) {
        $response = new Response();
        $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
        $response->setSuccess(false);
        ($sessionid === ''       ? $response->addMessage('Session ID cannot be blank') : false);
        (!is_numeric($sessionid) ? $response->addMessage('Session ID must be numeric') : false);
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Do some validation on the 'access token' that the user is going to send in the HTTP request header:
    // Note: Apache web server doesn't allow sending authentication HTTP request header out of the box (by default), so we need to explicitly enable that in the .htaccess file.
    // Making sure the authorization header with the access token is submitted/provided by the client and not blank:
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response();
        $response->setHTTPStatusCode(401); // 401 means Unauthorized (because client didn't provide the authorization token)
        $response->setSuccess(false);
        (!isset($_SERVER['HTTP_AUTHORIZATION'])     ? $response->addMessage('Access token is missing from the header') : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage('Access token cannot be blank')            : false);

        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];
    
    // We'll handle either a DELETE request to delete a session (logout) or a PATCH request to update a session (refresh an access token):
    
    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') { // Handling the DELETE to delete a session (log out)    // API Endpoint:    DELETE /v1/sessions/{sessionid}
        // Delete the session row from the `tblsessions` database table:
        try {
            $query = $writeDB->prepare('DELETE FROM tblsessions WHERE id = :sessionid AND accesstoken = :accesstoken');
            $query->bindParam(':sessionid',   $sessionid,   PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error
                $response->setSuccess(false);
                $response->addMessage('Failed to log out of this session using access token provided');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            // Send a successful response (successful logout):
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->addMessage('Logged out');
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response


        } catch (PDOException $ex) { // if there's someting wrong with querying the database:
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('There was an issue logging out - please try again');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }


        // Handling the PATCH to refresh session / refresh an access token    // API Endpoint:    PATCH /v1/sessions/{sessionid}
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') { // Handling the PATCH to update a session (refresh the 'access token')    // this will allow a client to refresh an 'access token' when it's expired (or just before it expires), in return, this will allow a client to get a new 'access token' that will be valid for the next 20 minutes. Within this functionality, we'll be performing a check on the passed in 'refresh token' (that was passed in the request body using JSON format) to make sure that 'refresh token' hasn't expired. If the 'refresh token' has also expired, then we won't be able to refresh the 'access token' and the user would be required to fully log in again.
        // Both "Access Token" and "Refresh Token" are required    // "Access Token" is required to be sent as an "Authorization" HTTP Request Header, and "Refresh Token" is required to be sent as JSON in the HTTP Request Body     // The 'refresh token' will be passed in in the HTTP request body (unlike the 'access token' that's passed in an 'Authorizatin' HTTP request header, not in body)    // the 'access token' will be required too, which must be sent as an "Authorization" HTTP Request Header
        // Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script (Cron jobs) that runs every one or two days to clear out (delete) the expired sessions.

        // Making sure that the content type of the HTTP request of the data sent is JSON type:
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') { // if the HTTP request header content type is not JSON or not provided in the HTTP Request
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data (the right type of header))
            $response->setSuccess(false);
            $response->addMessage('Content Type header not set to JSON');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Do some validation checks:

        // making sure that the 'refresh token' exists and is not empty:
        // Inspect the HTTP request Body (not the HTTP headers):
        $rawPatchData = file_get_contents('php://input');

        // Making sure the data that are submitted are of JSON type:
        if (!$jsonData = json_decode($rawPatchData)) { // If it doesn't succeed to decode the data (JSON) sent, this means the sent/submitted body data is not valid JSON
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage('Request body is not valid JSON');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Making sure the 'refresh token' has been submitted/provided and not empty (not blank) in the HTTP Request Body (not as an "Authorizatin" HTTP Request Header):
        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            (!isset($jsonData->refresh_token)     ? $response->addMessage('Refresh token not supplied')    : false);
            (strlen($jsonData->refresh_token) < 1 ? $response->addMessage('Refresh token cannot be blank') : false);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }


        try {
            $refreshtoken = $jsonData->refresh_token;

            // We need to perfrom an SQL query using JOIN statement because we're going to bring back data from two tables: `tblsessions` and `tblusers` through the common column `tblsessions`.`userid` which is a FOREIGN KEY in the `tblsessions` table which REFERENCES the `id` column in the `tblusers` table. That's because we need to bring back the session details to validate the 'refresh token' and 'access token' and things like that, and we also need to bring the `users` table row back or the user details because we need to perform `useractive` and `loginattempts` because these are still valid if we want to refresh the token. We need to perform validation checks.
            $query = $writeDB->prepare(
                'SELECT tblsessions.id AS sessionid, tblsessions.userid AS userid, tblsessions.accesstoken, tblsessions.refreshtoken, tblusers.useractive, tblusers.loginattempts, tblsessions.accesstokenexpiry, tblsessions.refreshtokenexpiry
                FROM tblsessions, tblusers
                WHERE tblusers.id = tblsessions.userid
                AND tblsessions.id = :sessionid AND tblsessions.accesstoken = :accesstoken AND tblsessions.refreshtoken = :refreshtoken'
            );

            $query->bindParam(':sessionid'   , $sessionid   , PDO::PARAM_INT);
            $query->bindParam(':accesstoken' , $accesstoken , PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('Access token or refresh token is incorrect for session id'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Bring back the row with that specific session id, access token and refresh token:
            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid          = $row['sessionid']; // The SQL Alias
            $returned_userid             = $row['userid'];    // The SQL Alias
            $returned_accesstoken        = $row['accesstoken'];
            $returned_refreshtoken       = $row['refreshtoken'];
            $returned_useractive         = $row['useractive'];
            $returned_loginattempts      = $row['loginattempts'];
            $returned_accesstokenexpiry  = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

            if ($returned_useractive !== 'Y') {
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('User account is not active');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            if ($returned_loginattempts >= 3) { // if the account is locked out
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('User account is currently locked out');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Checking if the refresh token is still valid or has expired:
            if (strtotime($returned_refreshtokenexpiry) < time()) { // if the time stored in the database (the refresh token expiry date) is less than the current time (i.e. the NOW!), this means the refresh token has expired
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('Refresh token has expired - please log in again');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Regenerate a new access token with a new access token expiry time: (and every time when we generate a new access token, we regenrate a new refresh token too)
            // Every time we generate a new 'access token', we must generate a new 'refresh token' too
            $accesstoken  = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            $access_token_expiry_seconds  = 1200; // 20 minutes
            $refresh_token_expiry_seconds = 1209600; // 14 days (2 weeks)

            // Update our current session (we update the access token for the current session):
            // We must make sure we're updating the right session, because a user might have multiple sessions (using another browser or using other devices like mobile phone, laptop, table, ...), so must use 'WHERE' in the SQL query with the right access token:
            $query = $writeDB->prepare(
                'UPDATE tblsessions SET
                accesstoken = :accesstoken, accesstokenexpiry = DATE_ADD(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = DATE_ADD(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND)
                WHERE id = :sessionid AND userid = :userid AND accesstoken = :returnedaccesstoken AND refreshtoken = :returnedrefreshtoken'
            );

            $query->bindParam(':userid'                   , $returned_userid             , PDO::PARAM_INT);
            $query->bindParam(':sessionid'                , $returned_sessionid          , PDO::PARAM_INT);
            $query->bindParam(':accesstoken'              , $accesstoken                 , PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds' , $access_token_expiry_seconds , PDO::PARAM_INT);
            $query->bindParam(':refreshtoken'             , $refreshtoken                , PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken'      , $returned_accesstoken        , PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken'     , $returned_refreshtoken       , PDO::PARAM_STR);
            $query->execute();
            // Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script that runs every one or two days to clear out (delete) the expired sessions.

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('Access token could not be refreshed - please log in again');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Return the new session details to the user:
            // Every time we generate a new 'access token', we must generate a new 'refresh token' too
            $returnData = array();
            $returnData['sessionid']            = $returned_sessionid;
            $returnData['access_token']         = $accesstoken;
            $returnData['access_token_expiry']  = $access_token_expiry_seconds;
            $returnData['refresh_token']        = $refreshtoken;
            $returnData['refresh_token_expiry'] = $refresh_token_expiry_seconds;

            // Send a successful response with newly updated session details:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->addMessage('Token refreshed');
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (PDOException $ex) {
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('There was an issue refreshing access token - please log in again');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script that runs every one or two days to clear out (delete) the expired sessions.

    } else { // anything other than DELETE or PATCH request methods, we send an error response:
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // If the URL doesn't contain the {sessionid} Query String Parameter, then it's Log in: Create a new session or log in (a new access token and refresh token)    // API Endpoint:    POST /v1/sessions
} elseif (empty($_GET)) { // check if the route doesn't have a query string of '?sessionid=' (equivalent to or maps to /sessions.php) which means it's a POST request
    // Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script that runs every one or two days to clear out (delete) the expired sessions.
    // We will hand POST methods only (to create a session (i.e. login a user)):
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') { // We won't allow any method other than 'POST' to create a session (i.e. login)
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    } else {
        // A security measure: if some hacker uses brute force to hit one of our API endpoints (in case we have a powerful server (not a small server that can't handle many requests per second) that could handle like 50 hundred requests per second which means a hacker could try a hundred passwords per second (from a dictionary of username and passwords combinations)), so we're going to delay requests by 1 second between every request using sleep() function (which will affect and delay a hacker with many requests but a normal user logging in almost won't feel delay)
        sleep(1); // sleep or hold or delay for one second


        // Validation
        // Making sure that the content type of the HTTP request of the data sent to Create a session is JSON type:
        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') { // if the HTTP request header content type is not JSON or not provided in the HTTP Request      
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data (JSON))
            $response->setSuccess(false);
            $response->addMessage('Content Type header not set to JSON');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Getting and handling the data that is coming from the POST request:
        $rawPostData = file_get_contents('php://input'); // Retrieve the raw HTTP request body data    // getting the POST request BODY

        // Validation
        // Making sure the data submitted is valid JSON:
        if (!$jsonData = json_decode($rawPostData)) {
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage('Request body is not valid JSON');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Validation
        // Making sure the mandatory fields (username and password) are filld in or provided (submitted) with the POST request:
        if (!isset($jsonData->username) || !isset($jsonData->password)) {
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error
            $response->setSuccess(false);
            (!isset($jsonData->username) ? $response->addMessage('Username not supplied')  : false);
            (!isset($jsonData->password) ? $response->addMessage('Password not supplied')  : false);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Validation: Making sure the POST submitted data is valid (not a blank string, doesn't exceed 255 characters (as we specified while we created the `users` database table)):
        if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) { // Check if username is an empty string (i.e.    {"username": ""}    ), or exceeds 255 characters (as we specified when we created the database table)
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error
            $response->setSuccess(false);
            (strlen($jsonData->username) < 1   ? $response->addMessage('Username cannot be blank')                  : false);
            (strlen($jsonData->username) > 255 ? $response->addMessage('Username must be less than 255 characters') : false);
            (strlen($jsonData->password) < 1   ? $response->addMessage('Password cannot be blank')                  : false);
            (strlen($jsonData->password) > 255 ? $response->addMessage('Password must be less than 255 characters') : false);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Querying the database to look for the username trying to log in our database `users` table (to retrieve a row based on a valid username):
        try {
            $username = $jsonData->username;
            $password = $jsonData->password;

            $query = $writeDB->prepare('SELECT id, fullname, username, password, useractive, loginattempts FROM tblusers WHERE username = :username');
            $query->bindParam(':username', $username, PDO::PARAM_STR);
            $query->execute();

            // Note: To get rid of the `tblsessions` database table rows that have EXPIRED sessions, on the server you should create a scheduled script that runs every one or two days to clear out (delete) the expired sessions.

            $rowCount = $query->rowCount(); // it should find 1 row only at maximum (user logging in exists), or zero 0 rows (user doesn't exist), because username column is UNIQUE in the `users` database table

            if ($rowCount === 0) { // If username trying to login doesn't exist in our `users` database table
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized (there's no username identical to the user provided, so they're unauthorized to login)
                $response->setSuccess(false);
                $response->addMessage('Username or password is incorrect! (User doesn\'t exist in our database!)'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            $row = $query->fetch(PDO::FETCH_ASSOC); // We won't use a while loop here like we did in the previous ones because threre's only ever going to be 0 or 1 rows

            $returned_id            = $row['id'];
            $returned_fullname      = $row['fullname'];
            $returned_username      = $row['username'];
            $returned_password      = $row['password'];
            $returned_useractive    = $row['useractive'];
            $returned_loginattempts = $row['loginattempts'];


            // Doing some validation:

            // Making sure the user is active:
            if ($returned_useractive !== 'Y') { // if the user is not active ('Y' means yes)
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('User account not active'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Making sure the user is not currently locked out:
            if ($returned_loginattempts >= 3) { // if number of login attemps is equal to or greater than 3, user is locked out of their account and they need a reset
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('User account is currently locked out'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Verifying password:
            if (!password_verify($password, $returned_password)) { // comparing the trying to login password to the database stored hashed password
                // increase the loginattemps by 1: (user would get locked out of their account if they fail login 3 times i.e. they get locked out of their account if they enter wrong password for 3 times during login)
                $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = loginattempts + 1 WHERE id = :id'); // increment the alreay existing `loginattempts` by +1
                $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
                $query->execute();

                // Send a failed login response:
                $response = new Response();
                $response->setHTTPStatusCode(401); // 401 means Unauthorized
                $response->setSuccess(false);
                $response->addMessage('Username or password is incorrect'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Creating the 'access token' and 'refresh token' (generate some random text (random characters) then return them to the client):
            // openssl_random_pseudo_bytes() function generates random bytes value that has NEVER been used before. Then we need to convert those bytes to hexadecimal using bin2hex() function then we need to base64-encode it to get a character string that we can pass in and out an HTTP request header
            $accesstoken  = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time()); // 24 is the length of the bytes (characters) (of the 'access token'). We need to convert it to hexadecimal to be able to deal with it using bin2hex() function. Then we need to base64-encode it using base64_encode() function. In order to add more security (to prevent what's called a 'stale token' sitting on a client device), we suffix the bin2hex() value with time() function to be encoded with it as well.
            $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time()); // 24 is the length of the bytes (characters) (of the 'access token'). We need to convert it to hexadecimal to be able to deal with it using bin2hex() function. Then we need to base64-encode it using base64_encode() function. In order to add more security (to prevent what's called a 'stale token' sitting on a client device), we suffix the bin2hex() value with time() function to be encoded with it as well.

            $access_token_expiry_seconds  = 1200;    // the 'access token'  will last 20 minutes before it's expired
            $refresh_token_expiry_seconds = 1209600; // the 'refresh token' will last 14 days    before it's expired

            // At this point here, this is a SUCCESSFUL login, so we reset the loginattempts counter back to zero 0 (to prevent from user getting locked out)
            // We here use a new separate try ... catch ... statement because we're going to perform two different database queries (a Database Transaction). The first is UPDATE to reset the loginattempts back to zero 0, and the second is INSERT to insert a new row in the tblsessions table to save the generated 'access token' and 'refresh token', so we need to catch ... if either of those two queries fails. And then in case of either one of those two queries fails, we need to ROLL BACK (for example, if the the query of resetting the loginattempts to zero 0 is successful, but the query of INSERT in the tblsessions table fails, we need to do a rollback to roll back any changes we've done, so we need to get the loginattempts back to its old value again before resetting to zero 0)

        } catch (PDOException $ex) { // If the database query fails
            // Important Note: We're not going to log the exception error in the error log (a plaintext file) in this case, because if someone (hacker) gets access to the server, then they could read potential log files and passwords in plaintext
            // error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('There was an issue logging in');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }


        // At this point here, this is a SUCCESSFUL login, so we reset the loginattempts counter back to zero 0 (to prevent from user getting locked out)
        // We here use a new separate try ... catch ... statement because we're going to perform two different database queries (a transaction). The first is UPDATE to reset the loginattempts back to zero 0, and the second is INSERT to insert a new row in the tblsessions table to save the generated 'access token' and 'refresh token', so we need to catch ... if either of those two queries fails. And then in case of either one of those two queries fails, we need to ROLL BACK (for example, if the the query of resetting the loginattempts to zero 0 is successful, but the query of INSERT in the tblsessions table fails, we need to do a rollback to roll back any changes we've done, so we need to get the loginattempts back to its old value again before resetting to zero 0)
        try {
            // We're going to use Database Transaction (atomic) and committing and Rollback. This means if you're performing multiple database queries, they all have to succeed before the data is saved into the database (by committing them). If there's an error with anyone of the queries halfway through, the catch statement will catch the error and then ROLLBACK any changes happened with the queries.
            // Creating the transaction:
            $writeDB->beginTransaction(); // Note: We'll rollback changes in the catch block!

            // Resetting the loginattempts to zero 0 after the successful login:
            $query = $writeDB->prepare('UPDATE tblusers SET loginattempts = 0 WHERE id = :id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            // INSERT the session and 'accesstoken' and 'refreshtoken' into the `tblsessions` table:
            $query = $writeDB->prepare('INSERT INTO tblsessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) VALUES (:userid, :accesstoken, DATE_ADD(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, DATE_ADD(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND))'); // for `accesstokenexpiry` and `refreshtokenexpiry` columns we need to get the current DATE/Time and then add the period (amount of seconds) that we previously specified up there
            $query->bindParam(':userid'                   , $returned_id                 , PDO::PARAM_INT);
            $query->bindParam(':accesstoken'              , $accesstoken                 , PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds' , $access_token_expiry_seconds , PDO::PARAM_INT);
            $query->bindParam(':refreshtoken'             , $refreshtoken                , PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiryseconds', $refresh_token_expiry_seconds, PDO::PARAM_INT);
            $query->execute();

            // Getting the session `id` and returning it to the user because the user should know it because they'll going to use it in a route like /sessions/3, and they'll use it to logout or DELETE a session (DELETE HTTP request with a route like DELETE /sessions/3    ) or when they refresh it they'll need to know the session id
            $lastSessionID = $writeDB->lastInsertId(); // it's the session `id`

            // Because we're using a transaction, we need to commit these data (save them into the database), because till now they are not saved into the database yet, they're just hold in a session:
            $writeDB->commit(); // committing the transaction     // Note: We'll rollback changes in the catch block!

            // Sending the user's 'access token' and the access token expiry seconds, session id, ...etc in a response:
            $returnData = array();
            $returnData['session_id']               = intval($lastSessionID);
            $returnData['access_token']             = $accesstoken;
            $returnData['access_token_expires_in']  = $access_token_expiry_seconds;
            $returnData['refresh_token']            = $refreshtoken;
            $returnData['refresh_token_expires_in'] = $refresh_token_expiry_seconds;

            // Send a successful response for the Successful login with the $returnData array so that the user can use them on future requests to access the Tasks API (AT THE MOMENT we're dealing with the AUTHENTICATION API):
            $response = new Response();
            $response->setHttpStatusCode(201); // 201 means CREATED successfully and then returned
            $response->setSuccess(true);
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (PDOException $ex) { // If there's any error while performing any of the two queries

            // If there's an error querying the database, rollback the successful query again of the two queries, because the other one has failed:
            $writeDB->rollBack();

            $response = new Response();
            $response->setHttpStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('There was an issue logging in - please try again');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

    }

} else { // if the route is anything other than the ones we've stated before (for example /sessions/test)
    $response = new Response();
    $response->setHttpStatusCode(404); // 404 means Not Found
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}