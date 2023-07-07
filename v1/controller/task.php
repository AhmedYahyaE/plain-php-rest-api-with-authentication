<?php
// This is Tasks API (tasks controller)
// Note: When dealing with an API with authentication, we send the 'access token' in the HTTP header with every request, but for the 'refresh token', we ever send it in the HTTP body only when we want to refresh a session.

require_once('db.php'); // database connection
require_once('../model/Task.php'); // task model
require_once('../model/Response.php'); // Response model
require_once('../model/image.php'); // Image model



function retrieveTaskImages($dbConn, $taskid, $returned_userid) {
    // Here we use SQL 'JOIN' because we're grabbing data from TWO tables
    $imageQuery = $dbConn->prepare(
        'SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
        FROM tblimages, tbltasks
        WHERE tbltasks.id = :taskid AND tbltasks.userid = :userid
        AND tbltasks.id = tblimages.taskid'
    );

    $imageQuery->bindParam(':taskid' , $taskid         , PDO::PARAM_INT);
    $imageQuery->bindParam(':userid' , $returned_userid, PDO::PARAM_INT);

    $imageQuery->execute();

    $imageArray = array();

    while ($imageRow = $imageQuery->fetch(PDO::FETCH_ASSOC)) {
        $image = new Image($imageRow['id'], $imageRow['title'], $imageRow['filename'], $imageRow['mimetype'], $imageRow['taskid']); // Pass in the image details to the Imgae model for validation and getting them back again
        $imageArray[] = $image->returnImageAsArray(); 
    }

    return $imageArray;
}



// Connect to the TWO databases:
try {
    $writeDB = DB::connectWriteDB();
    $readDB  = DB::connectReadDB();

    // If connection to one of the two databases fail:
} catch (PDOException $ex) { // Because in db.php we used this line of code: self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    and    self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);    // $ex is a PDOException class object
    // echo $ex->getMessage() . '<br>'; // Display the thrown Exception message
    echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
    echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

    error_log('Connection error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file

    $response = new Response();
    $response->setHTTPStatusCode(500); // 500 is Internal Server Error
    $response->setSuccess(false);
    $response->addMessage('Database connection error');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}


// Before deciding which route is used (BEFORE anything and everything), we add the authorization (authentication) script here (to make sure the user has provided the 'access token' as an 'Authorization' HTTP Request Header and check it to make sure it's valid and hasn't expired and that the user is valid):


// Begin authorization (authentication) script:
// Make sure the Access Token from the HTTP Request Header (the 'Authorization' HTTP Request Header) is provided and it's not empty (left blank)
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

try {
    // Perform a database query based on that $accesstoken to bring back its user details and session details so we can check `useractive`, `loginattempts` and also check that the 'access token' hasn't expired, ...etc
    // We need to combine/JOIN the two of tables: `tblsessions` and `tblusers` because we need to grab data from both of them
    $query = $writeDB->prepare(
        'SELECT tblsessions.userid, tblsessions.accesstokenexpiry, tblusers.useractive, tblusers.loginattempts
        FROM tblsessions, tblusers WHERE tblsessions.userid = tblusers.id
        AND accesstoken = :accesstoken'
    );

    $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) {
        $response = new Response();
        $response->setHTTPStatusCode(401); // 401 means Unauthorized
        $response->setSuccess(false);
        $response->addMessage('Invalid Access Token');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Here, we're not doing anything with the 'Refresh Toke' as it's handled by the Authentication API (in sessions.php page) only. Here, we just check if the 'Access Token' is still valid

    $row = $query->fetch(PDO::FETCH_ASSOC);

    $returned_userid            = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive        = $row['useractive'];
    $returned_loginattempts     = $row['loginattempts'];

    // Making sure the user is active:
    if ($returned_useractive !== 'Y') { // if the user is not active ('Y' means yes)
        $response = new Response();
        $response->setHTTPStatusCode(401); // 401 means Unauthorized
        $response->setSuccess(false);
        $response->addMessage('User account not active'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Making sure the user is not currently locked out (the `loginattempts` column):
    if ($returned_loginattempts >= 3) { // if number of login attemps is equal to or greater than 3, user is locked out of their account and they need a reset
        $response = new Response();
        $response->setHTTPStatusCode(401); // 401 means Unauthorized
        $response->setSuccess(false);
        $response->addMessage('User account is currently locked out'); // For security reasons, don't give very specific messages about what's wrong (username specifically or password specifically) because that could allow a potential hacker to work out based on your logic of what's right and what's wrong
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Checking if the access token is still valid or has expired:
    if (strtotime($returned_accesstokenexpiry) < time()) { // if the time stored in the database (the 'Access Token' expiry date) is less than the current time, this means the 'Access Token' has expired
        $response = new Response();
        $response->setHTTPStatusCode(401); // 401 means Unauthorized
        $response->setSuccess(false);
        $response->addMessage('Access token expired');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

} catch (PDOException $ex) { // If querying the database fails
    $response = new Response();
    $response->setHTTPStatusCode(500); // 500 is Internal Server Error
    $response->setSuccess(false);
    $response->addMessage('There was an issue authenticating - please try again');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}
// End authorization (authentication) script



// NEXT: FOR EACH SQL QUERY IN THAT PAGE (FOR EACH ROUTE/ENDPOINT), WE MUST MAKE SURE IT HAS TAKEN INTO ACCOUNT THE `userid`! (in order to limit (to limit the tasks that a user can view i.e. to make the user able to view their tasks only, not All tasks) that a user can only view their own tasks only and not other users/people tasks i.e. Not All tasks (to view only the tasks that belong to the user only, and not other users/people tasks)) (user-centric or per-user basis)


// If the {taskid} Query String Paramter is provided in the URL, we handle Get a single task (GET), Delete a task (DELETE) or Update a task (PATCH)
if (array_key_exists('taskid', $_GET)) { // if taskid exists as a query string parameter in the URL (query string parameters are accessed using the $_GET superglobal array)
    $taskid = $_GET['taskid'];

    // Some validation:
    if ($taskid == '' || !is_numeric($taskid)) {
        $response = new Response();
        $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
        $response->setSuccess(false);
        $response->addMessage('Task ID cannot be blank or must be numeric');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }


    // HTTP request methods and their uses: GET (read or retrieve), POST (create), PATCH (update), PUT (replace/update), DELETE (delete)

    // Handling getting a single task:
    // Get a single task with its id:    e.g. URL is like:    GET /task.php?taskid=1    or    v1/tasks/1  for Clean URLs using the .htaccess file
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        try {
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE id = :taskid
                AND userid = :userid'
            ); // Using 'AS' is Aliasing in SQL (so as to be able to use    $row['deadline']    )    // Using the $readDB (not $writeDB) because we're reading database here

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) { // If there's no task found in database with that id
                $response = new Response();
                $response->setHttpStatusCode(404); // 404 means Not Found
                $response->setSuccess(false);
                $response->addMessage('Task not found');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $taskid, $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array(); // will be passed to the $response->setData()
            $returnData['rows_returned'] = $rowCount; // no. of returned rows
            $returnData['tasks'] = $taskArray;
            
            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        // Catch multiple things (exceptions):
        } catch (ImageException $ex) {
            echo '<pre>', var_dump($ex), '</pre>';
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage()); // Get every message we throw new Exception in Task.php (e.g.    throw new TaskException('Task description error');    )
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (TaskException $ex) {
            echo '<pre>', var_dump($ex), '</pre>';
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage()); // Get every message we throw new Exception in Task.php (e.g.    throw new TaskException('Task description error');    )
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex), '</pre>';
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error: - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to get Task');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

    // Handling deleting a single task (the tasks must belong to the authenticated/logged-in user): (URL example: DELETE /tasks/2)
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        try {
            // First we'll delete all the images of a task (delete the actual image file and delete the image row in the database table `tblimages`), then we'll delete the task itself from the database table `tbltasks`, then we delete the task folder (on the server i.e. filesystem) inside the 'taskimages' folder
            // SELECT all the images of the task to be deleted
            $imageSELECTQuery = $readDB->prepare(
                'SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
                FROM tblimages, tbltasks
                WHERE tbltasks.id = :taskid AND tbltasks.userid = :userid
                AND tblimages.taskid = tbltasks.id'
            );

            $imageSELECTQuery->bindParam(':taskid', $taskid         , PDO::PARAM_INT);
            $imageSELECTQuery->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

            $imageSELECTQuery->execute();

            while ($imageRow = $imageSELECTQuery->fetch(PDO::FETCH_ASSOC)) {
                // We need to use a database transaction here, because we're performing two operations which are: deleting the actual physical image files on the server (filesystem) AND deleting the image row in the database table `tblimages`
                $writeDB->beginTransaction(); // Because we use beginTransaction(), we need to use rollBack() inside all catch statements blocks


                $image = new Image($imageRow['id'], $imageRow['title'], $imageRow['filename'], $imageRow['mimetype'], $imageRow['taskid']); // Pass in the image details to the Imgae model for validation and getting them back again

                $imageID = $image->getID();

                // Delete the image row inside the database table `tblimages`    // Using 'JOIN' with DELETE query
                $query = $writeDB->prepare(
                    'DELETE tblimages FROM tblimages, tbltasks
                    WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid AND tbltasks.userid = :userid
                    AND tblimages.taskid = tbltasks.id'
                );

                $query->bindParam(':imageid', $imageID         , PDO::PARAM_INT);
                $query->bindParam(':taskid' , $taskid          , PDO::PARAM_INT);
                $query->bindParam(':userid' , $returned_userid , PDO::PARAM_INT);

                $query->execute();

                $image->deleteImageFile(); // delete the actual physical image file on the server (filesystem)


                // If the deleting the image row in the database table and deleting the actual physical image file on the server (filesystem) is successful, we save (commit) the database changes
                $writeDB->commit();
            }



            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $query = $writeDB->prepare('DELETE FROM tbltasks WHERE id = :taskid
                AND userid = :userid'
            ); // Using the $writeDB (not $readDB) because we're deleting

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT); // Binding the `taskid`
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();
            if ($rowCount === 0) { // If there's no record in the database with that id:
                $response = new Response();
                $response->setHttpStatusCode(404); // 404 means Not Found
                $response->setSuccess(false);
                $response->addMessage('Task not found');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // After the task has been deleted successfully from the database table `tbltasks`, we need to delete that task's images (physical) folder inside 'taskimages' folder
            $taskImageFolder = '../../../taskimages/' . $taskid;

            if (is_dir($taskImageFolder)) { // check if the task image folder exists
                rmdir($taskImageFolder);
            }

            // If deletion is successful:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->addMessage('Task deleted');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (ImageException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            // 
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            // We're inside an Exception! So if $writeDB is in a Transaction, rollback with the thrown exception
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to delete Task');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

    // Handling updating a single task task:    // API Endpoint:    PATCH /v1/tasks/{taskid}
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        try {
            // Check the submitted data to update is of type JSON:
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') { // if the HTTP request header content type is not JSON or not provided in the HTTP Request  
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('Content Type header not set to JSON');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Inspect the HTTP request Body (not the HTTP headers):
            $rawPATCHData = file_get_contents('php://input'); // Retrieve the raw HTTP request body data

            // Making sure the data that are passed in are of JSON type:
            if (!$jsonData = json_decode($rawPATCHData)) { // If it doesn't succeed to decode the data (JSON) sent, this means the sent/submitted body data is not valid JSON
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('Request body is not valid JSON');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Keep track of which fields that are being asked to be updated: (initially set all of them to 'false')
            $title_updated       = false;
            $description_updated = false;
            $deadline_updated    = false;
            $completed_updated   = false;

            // Formatting the SQL statement: (SQL statement e.g. 'UPDATE tbltasks SET title = :title, description = :description, deadline = :deadline, completed = :completed WHERE id = :taskid')
            // $queryFields will contain the previous fields that are set to 'true'
            $queryFields = '';

            // Check which fields are asked to be updated, and set them to 'true' and add them to $queryFields
            if (isset($jsonData->title)) {
                $title_updated = true; // set/convert it from 'false' to 'true'
                $queryFields .= 'title = :title, ';
            }

            if (isset($jsonData->description)) {
                $description_updated = true; // set/convert it from 'false' to 'true'
                $queryFields .= 'description = :description, ';
            }

            if (isset($jsonData->deadline)) {
                $deadline_updated = true; // set/convert it from 'false' to 'true'
                $queryFields .= 'deadline = STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), ';
            }

            if (isset($jsonData->completed)) {
                $completed_updated = true; // set/convert it from 'false' to 'true'
                $queryFields .= 'completed = :completed, ';
            }

            // All queryFields have a comma and a space at the end of it, so we need to remove the comma and the space of the LAST queryField:
            $queryFields = rtrim($queryFields, ', ');

            // Check if there's any data provided to be updated, other than that, there's no an UPDATE query:
            if ($title_updated === false && $description_updated === false && $deadline_updated === false && $completed_updated == false) {
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('No task fields provided');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Get the taskid of the task that is being asked to be updated from the URL: (e.g. GET /tasks/7)
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE id = :taskid
                AND userid = :userid'
            ); // Using the $writeDB here although it's just a READ statement because the last thing we want is to pull back an old version of a task (from the $readDB because it's asynchronous) and update and override any new data:

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();
            
            $rowCount = $query->rowCount();

            if ($rowCount === 0) { // if nothing returned from the database query
                $response = new Response();
                $response->setHttpStatusCode(404); // 404 means Not Found
                $response->setSuccess(false);
                $response->addMessage('No task found to update');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }
            
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);
            }

            // The UPDATE query string:
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $queryString = 'UPDATE tbltasks SET ' . $queryFields . ' WHERE id = :taskid
                AND userid = :userid';
            $query = $writeDB->prepare($queryString); // Using the $writeDB (not $readDB) because we're UPDATE -ing

            // Overriding the new updated values in the $task class object which is the record that is being updated: (to be validated)    // Set the updated new value, and get it again back out from the model

            if ($title_updated === true) {
                $task->setTitle($jsonData->title); // overriding (updating) - overwriting the new value over the old one
                $up_title = $task->getTitle(); // Getting the title again, after having had been validated
                $query->bindParam(':title', $up_title, PDO::PARAM_STR);
            }

            if ($description_updated === true) {
                $task->setDescription($jsonData->description); // overriding (updating) - overwriting the new value over the old one
                $up_description = $task->getDescription(); // Getting the description again, after having had been validated
                $query->bindParam(':description', $up_description, PDO::PARAM_STR);
            }

            if ($deadline_updated === true) {
                $task->setDeadline($jsonData->deadline); // overriding (updating) - overwriting the new value over the old one
                $up_deadline = $task->getDeadline(); // Getting the deadline again, after having had been validated
                $query->bindParam(':deadline', $up_deadline, PDO::PARAM_STR);
            }

            if ($completed_updated === true) {
                $task->setCompleted($jsonData->completed); // overriding (updating) - overwriting the new value over the old one
                $up_completed = $task->getCompleted(); // Getting the completed again, after having had been validated
                $query->bindParam(':completed', $up_completed, PDO::PARAM_STR);
            }

            // Bind the :taskid placeholder in the $query:
            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('Task not updated');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Return the updated record back to the client after being updated:

            // Using the $writeDB (not $readDB) here because by the time that record has been UPDATE-ed, we immediately try to return it from the database, and it may not have had time to populate or push this record data from the writeDB to readDB, because readDB-s are Asynchronous, so we must use the writeDB here in this case even though it's just a READ statement:
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid
                AND userid = :userid'
            );

            $query->bindParam(':taskid', $taskid, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response();
                $response->setHttpStatusCode(404); // 404 means Not Found
                $response->setSuccess(false);
                $response->addMessage('No task found after update');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($writeDB, $taskid, $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->addMessage('Task updated');
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (ImageException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (TaskException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHttpStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to update task - check your data for errors');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

    } else { // If the method used is other than GET, DELETE or PATCH, this is not allowed
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // If the {complete} or {incomplete} Query String Parameters are provided in the URL, we handle returning all 'completed' 'Y' tasks or 'incompleted' 'N' tasks: (Check aliases and redirections in the .htaccess file)
} elseif (array_key_exists('completed', $_GET)) { // Check aliases and redirections in .htaccess file    // e.g.    GET /v1/tasks/complete    Or   GET /v1/tasks/incomplete    BUT the TRUE URL is like: /v1/task.php?completed=Y (matches complete)    or    /v1/task.php?completed=N (matches incomplete)
    $completed = $_GET['completed'];

    if ($completed !== 'Y' && $completed !== 'N') {
        $response = new Response();
        $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
        $response->setSuccess(false);
        $response->addMessage('Completed filter must be Y or N');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {// e.g.    GET /v1/tasks/complete    Or   GET /v1/tasks/incomplete    BUT the TRUE URL is like: /v1/task.php?completed=Y (matches complete)    or    /v1/task.php?completed=N (matches incomplete)
        try {
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks WHERE completed = :completed
                AND userid = :userid'
            ); // Using the $readDB (not $writeDB) because we're reading database here

            $query->bindParam(':completed', $completed,       PDO::PARAM_STR); // Binding the `completed`
            $query->bindParam(':userid',    $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (ImageException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage()); // Get every message we throw new Exception in Task.php (e.g.    throw new TaskException('Task description error');    )
            $response->send();
            exit; // to guarantee exiting out of script after sending the response  
        } catch (TaskException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage()); // Get every message we throw new Exception in Task.php (e.g.    throw new TaskException('Task description error');    )
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks'); // Get every message we throw new Exception in Task.php (e.g.    throw new TaskException('Task description error');    )
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }
    } else { // If the HTTP request method is not 'GET' (returning a classified type of tasks is through GET method only from the URL)
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

    // Pagination (of the Get ALL tasks): URL is going to look like: GET /tasks/page/1   , and the real URL is: task.php?page=1 (Check the .htaccess file)
    // Pagination here depends on SQL LIMIT and OFFSET
} elseif (array_key_exists('page', $_GET)) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $page = $_GET['page']; // task.php?page=8
        
        // Some validation:
        if ($page == '' || !is_numeric($page)) { // checking the $page in the URL
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage('Page number cannot be blank and must be numeric');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        $limitPerPage = 20; // number of records per one page    // the SQL LIMIT

        try {
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):            
            $query = $readDB->prepare('SELECT COUNT(id) as totalNoOfTasks FROM tbltasks
                WHERE userid = :userid'
            ); // Using SQL Aliasing ('AS')    // Using the $readDB (not $writeDB) because we're reading database here

            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();
            
            $row = $query->fetch(PDO::FETCH_ASSOC);

            $tasksCount = intval($row['totalNoOfTasks']); // Type Casting

            $numOfPages = ceil($tasksCount / $limitPerPage); // e.g. if result is 1.1, it'll be 2

            if ($numOfPages == 0) { // We need to have at minimum 1 $numberOfPages in case there're no any tasks (totalNoOfTasks = 0) to show a successful response without retrieved tasks from database (there's no error here in such case)
                $numOfPages = 1;
            }

            if ($page > $numOfPages || $page == 0) { // Making sure the URL is proper/correct (if $page in the URL is greater than $numOfPages or is zero, this means it's incorrect)
                $response = new Response();
                $response->setHttpStatusCode(404); // 404 means Not Found
                $response->setSuccess(false);
                $response->addMessage('Page not found');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // OFFSET: For example, in page 1, OFFSET is from 0 to 19, and in page 2, OFFSET is from 20 to 39, ... etc
            $offset = ($page == 1 ? 0 : ($limitPerPage * ($page - 1))); // the SQL OFFSET (if it's the first page i.e. $page = 1, start from OFFSET zero 0, )

            // Using SQL LIMIT & OFFSET keywords: 
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):                        
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks
                WHERE userid = :userid
                LIMIT :pglimit OFFSET :offset'
            ); // Using 'AS' is Aliasing in SQL (so as to be able to use    $row['deadline']    )    // Using the $readDB (not $writeDB) because we're reading database here

            $query->bindParam(':pglimit', $limitPerPage, PDO::PARAM_INT); // Binding the `pglimit`
            $query->bindParam(':offset' , $offset      , PDO::PARAM_INT); // Binding the `offset`
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();
            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);
                
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();

            $returnData['rows_returned'] = $rowCount;
            $returnData['total_rows']    = $tasksCount;
            $returnData['total_pages']   = $numOfPages;

            ($page < $numOfPages ? $returnData['has_next_page']     = true : $returnData['has_next_page']     = false); // for example, to be used by frontend to show a button to click to go to the next page
            ($page > 1           ? $returnData['has_previous_page'] = true : $returnData['has_previous_page'] = false); // for example, to be used by frontend to show a button to click to go to the previous page

            $returnData['tasks'] = $taskArray;

            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (ImageException) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (TaskException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }
    } else { // Any other HTTP methods are not allowed
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

// if there's no {taskid} provided, then we handle getting ALL tasks that belong to the authenticated/logged-in user (with the URL: GET /v1/tasks) with GET method (the `userid` is determined through/based on the 'Authorization' Header that the user has provided i.e. 'Access Token'), or creating a new task with POST method: (Check the .htaccess file)
} elseif (empty($_GET)) { // $_GET will be empty, doesn't have 'taskid' or 'complete' or 'incomplete'
    if ($_SERVER['REQUEST_METHOD'] === 'GET') { // Get All tasks of the authenticated/logged-in user (Check the .htaccess file)    // Endpoint:    GET /v1/tasks
        try {
            // Taking the `userid` into account in the query (to restrict that a user can only grab their own tasks only, and not other users/people tasks i.e. user-centric or per-user basis):                        
            $query = $readDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") AS deadline, completed FROM tbltasks
                WHERE userid = :userid'
            ); // Using 'AS' is Aliasing in SQL (so as to be able to use    $row['deadline']    )    // Using the $readDB (not $writeDB) because we're reading database here

            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $imageArray = retrieveTaskImages($readDB, $row['id'], $returned_userid);

                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed'], $imageArray);
                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_count'] = $rowCount;
            $returnData['tasks']      = $taskArray;

            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(200); // 200 means Success or OK
            $response->setSuccess(true);
            $response->toCache(true);
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (ImageException $ex) {
            // echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            // echo $ex->getMessage() . '<br>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (TaskException $ex) {
            // echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            // echo $ex->getMessage() . '<br>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHTTPStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to get tasks');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }

        // Here, user is creating a task (using POST method): (Check the .htaccess file)    // API Endpoint:    POST /v1/tasks/
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            // Validation
            // Checking if the Content type of the HTTP request header is JSON: (check the submitted data to create a record (task) is in JSON format)
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') { // if the HTTP request header content type is not JSON or not provided in the HTTP Request
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('Content Type header not set to JSON');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Validation
            // Inspect the HTTP request Body (not the HTTP headers):
            $rawPOSTData = file_get_contents('php://input'); // Retrieve the raw HTTP request body data

            if (!$jsonData = json_decode($rawPOSTData)) { // If it doesn't succeed to decode the JSON sent, this means the sent body data is not valid JSON
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                $response->addMessage('Request body is not valid JSON');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Validation
            // Check if the client provided the mandatory fields (title and completed status (both are NOT NULL in the database table))
            if (!isset($jsonData->title) || !isset($jsonData->completed)) {
                $response = new Response();
                $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
                $response->setSuccess(false);
                (!isset($jsonData->title)     ? $response->addMessage('Title field is mandatory and must be provided')     : false);
                (!isset($jsonData->completed) ? $response->addMessage('Completed field is mandatory and must be provided') : false);
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // Give the submitted data to the Task class, to do the validation work:
            $newTask = new Task(null, $jsonData->title, (isset($jsonData->description) ? $jsonData->description : null), (isset($jsonData->deadline) ? $jsonData->deadline : null), $jsonData->completed); // Note: `description` and `deadline` are OPTIONAL fields as we specified in the database table (can be NULL), so you must use Conditinal Ternary Operator with those two fields

            $title       = $newTask->getTitle();
            $description = $newTask->getDescription();
            $deadline    = $newTask->getDeadline();
            $completed   = $newTask->getCompleted();

            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):                        
            $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, \'%d/%m/%Y %H:%i\'), :completed, :userid)'); // Using the $writeDB (not $readDB) because we're creating data
            $query->bindParam(':title',       $title,       PDO::PARAM_STR);
            $query->bindParam(':description', $description, PDO::PARAM_STR);
            $query->bindParam(':deadline',    $deadline,    PDO::PARAM_STR);
            $query->bindParam(':completed',   $completed,   PDO::PARAM_STR);

            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) { // if there's no data was successfully inserted into database
                $response = new Response();
                $response->setHttpStatusCode(500); // 500 is Internal Server Error
                $response->setSuccess(false);
                $response->addMessage('Failed to create task');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            // After successfully inserting the data into database, any API would return the same data back that have been submitted to the user
            $lastTaskID = $writeDB->lastInsertId();

            // Using the $writeDB (not $readDB) here because by the time that record has been CREATE-ed, we immediately try to return it from the database, and it may not have had engouh time to populate or push this record data from the writeDB to readDB, because readDB-s are Asynchronous, so we must use the writeDB here in this case even though it's just a READ statement:
            // Taking the `userid` into account in the query (to restrict that a user can only grab their tasks only, and not other people tasks):                        
            $query = $writeDB->prepare('SELECT id, title, description, DATE_FORMAT(deadline, "%d/%m/%Y %H:%i") as deadline, completed FROM tbltasks WHERE id = :taskid
                AND userid = :userid'
            );

            $query->bindParam(':taskid', $lastTaskID, PDO::PARAM_INT);
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT); // Binding the `userid`

            $query->execute();

            $rowCount = $query->rowCount();
            
            if ($rowCount === 0) { // If it doesn't find the record in the database
                $response = new Response();
                $response->setHttpStatusCode(500); // 500 is Internal Server Error
                $response->setSuccess(false);
                $response->addMessage('Failed to retrieve task after creation');
                $response->send();
                exit; // to guarantee exiting out of script after sending the response
            }

            $taskArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $task = new Task($row['id'], $row['title'], $row['description'], $row['deadline'], $row['completed']);

                $taskArray[] = $task->returnTaskAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['tasks'] = $taskArray;

            // Send a successful response:
            $response = new Response();
            $response->setHttpStatusCode(201); // Note: 201 means CREATED successfully and then returned
            $response->setSuccess(true);
            $response->addMessage('Task created');
            $response->setData($returnData);
            $response->send();
            exit; // to guarantee exiting out of script after sending the response

        } catch (TaskException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
            $response = new Response();
            $response->setHttpStatusCode(400); // 400 means client error (because the client hasn't submitted the right type of data)
            $response->setSuccess(false);
            $response->addMessage($ex->getMessage());
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        } catch (PDOException $ex) {
            echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
            echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

            error_log('Database query error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
            $response = new Response();
            $response->setHttpStatusCode(500); // 500 is Internal Server Error
            $response->setSuccess(false);
            $response->addMessage('Failed to insert task into database - check submitted data for errors');
            $response->send();
            exit; // to guarantee exiting out of script after sending the response
        }
    } else { // any other HTTP method is not allowed (for example we don't want the user to be able to delete all tasks in one shot (they must be able to delete only one item a time), although we can make this happen)
        $response = new Response();
        $response->setHttpStatusCode(405); // 405 means Request Method Not Allowed
        $response->setSuccess(false);
        $response->addMessage('Request method not allowed');
        $response->send();
        exit; // to guarantee exiting out of script after sending the response
    }

} else { // if the URL were random like: /v1/ghag254152
    $response = new Response();
    $response->setHttpStatusCode(404); // 404 means Not Found
    $response->setSuccess(false);
    $response->addMessage('Endpoint not found');
    $response->send();
    exit; // to guarantee exiting out of script after sending the response
}