<?php
// This is Images API (images controller)    // All the logic happens/occur here in the Controller
// We're depending on Exceptions here, so we do everything as possible in a try {} catch() {} statements, where we catch TaskException (for thrown errors of the Task.php model which is related to the sanity (validation) of the submitted data), ImageException (for thrown errors form the image.php model which validates image data) and PDOException (which throws errors that's related to querying the database)

require_once('db.php'); // database connection
require_once('../model/Response.php'); // Response model
require_once('../model/image.php'); // image model



// Helper Functions:

function sendResponse($statusCode, $success, $message = null, $toCache = false, $data = null) { // A helper function to send the whole response in one line of code only
    $response = new Response();

    $response->setHttpStatusCode($statusCode);
    $response->setSuccess($success);

    if ($message != null) {
        $response->addMessage($message);
    }

    $response->toCache($toCache);
    if ($data != null) {
        $response->setData($data);
    }

    $response->send();
    exit; // YOU MUST USE exit HERE TO EXIT OUT OF THE SCRIPT AFTER YOU SEND THE RESPONSE OF THE DESIRED ENDPOINT OF YOUR API (to not continue the script after the proper response has been sent)
}



function uploadImageRoute($readDB, $writeDB, $taskid, $returned_userid) {
    // We're going to query the database and use our image model for validation of inserted data and grabbing data, so we need to use a try ... catch ... statement, to BOTH catch BOTH PODException errors AND catch ImageException errors:
    try {
        // Do some validation:
        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data; boundary=') === false) { // Note: 'boundary' is sort of a random character string (a delimiter)    // strpos() is used to check if a string contains a certain word or sentence
            sendResponse(400, false, 'Content type header not set to multipart/form-data with a boundary'); // 400 means client error 
        }

        // Check in the database if the task (that we want upload its image) of the task id exists (comes from the URL query string parameters) and user id (comes from the checkAuthStatusAndReturnUserID() function) exists (meaning Check if there's a task with the provided task id and it belongs to the authenticated/logged-in user)
        $query = $readDB->prepare('SELECT id FROM tbltasks WHERE id = :taskid AND userid = :userid');

        $query->bindParam(':taskid', $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) { // if it doesn't find the task with the specific criteria
            sendResponse(404, false, 'Task Not Found'); // 404 means Not Found
        }



        // Important Note: To upload an image: In Postman, Go "Body", then "form-data", then you'll use TWO "Key" and "Values" fields: the first one: from the drop-down menu, choose "Text" and then enter "attributes" in the "Key" field, and then enter the "attributes" filed as JSON Text as follows:    {"title": "cat", "filename":"beautiful_cat"}    ("title" will be used to fill in the `title` column, and "filename" will be used to fill in the `filename` column in `tblimages` table (Note: We'll disregard the original uploaded file name! We'll take the file name that is with the "filename" attribute)) (N.B. Don't enter the file extension, we'll automatically determine it!) in the , and the second one: from the drop-down menu, choose "File" and enter "imagefile" in the "Key" field, and upload the file in "Value" field.



        // Perform some additional validation on the 'attributes' provided while uploading an image in the response body:
        // Note: User must enter the filename without its file extension
        // Note: Using "form-data" as a "Body" in Postman mimics/imitates the HTML Form body HTML <input> elements/fields (and the HTML <form> element "enctype" attribute is set to enctype="multipart/form-data"     (Check the "Content-Type" Header in Postman after entering the "form-data" fields (in "Body" in Postman) and their "Keys" and "Values") in Postman). And to access/inspect the submitted data in PHP in the backend, as expected, we use the $_POST Supergloabl array.
        if (!isset($_POST['attributes'])) { // 'attributes' are sent in Postman from 'Body' tab, then choose 'form-data', then write 'attributes' in the 'Key' field (Choode 'Text' from drop-down menu), and the 'attributes' JSON value in the 'Value' field
            sendResponse(400, false, 'Attributes missing from body of request'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Make sure that the passed in "attributes" Value are in a JSON format
        if (!$jsonImageAttributes = json_decode($_POST['attributes'])) {
            sendResponse(400, false, 'Attributes field is not valid JSON'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Perform some basic validation on the submitted attributes within the valid JSON (Requiring the mandatory fields: title and filename)
        // (like an HTML <form> <input> element "name" attribute)    attributes: {"title": "title value", "filename": "filename value without extension"}
        // Note: Using "form-data" as a "Body" in Postman mimics/imitates the HTML Form body HTML <input> elements/fields (and the HTML <form> element "enctype" attribute is set to enctype="multipart/form-data"     (Check the "Content-Type" Header in Postman after entering the "form-data" fields (in "Body" in Postman) and their "Keys" and "Values") in Postman). And to access/inspect the submitted data in PHP in the backend, as expected, we use the $_POST Supergloabl array.
        if (!isset($jsonImageAttributes->title) || !isset($jsonImageAttributes->filename) || $jsonImageAttributes->title == '' || $jsonImageAttributes->filename == '') {
            sendResponse(400, false, 'Title and Filename are mandatory'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Make sure the filename doesn't contain a file extension (user must enter the file name without its file extension), as we're going to AUTOMATICALLY determine the file extension from the file type as we're uploading the image
        if (strpos($jsonImageAttributes->filename, '.') > 0) { // if the filename contains a dot (e.g. xxxxxx.jpg)
            sendResponse(400, false, 'Filename must not contain a file extension'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Make sure the image file itself is provided (uploaded)
        // We're going to check three things: check if the file is successfully uploaded, file size (we're going to limit the file size to less than < 5 Megabytes) and determine the MIME type of the file
        // Uploaded files are found in the superglobal $_FILES (not $_POST)    // When the file is first uploaded to server using PHP web server, the web server stores it temporarily in a location (you can access the file path/location using $_FILES['file attribute name']['tmp_name']), and if you don't do anything with that file, the web server deletes that file automatically once the script ends (so we don't have to deal with the tidy up), so we have to move the file where we want it to go and name it what we want
        if (!isset($_FILES['imagefile']) || $_FILES['imagefile']['error'] !== 0) { // Make sure the file is provided (uploaded) and there're no errors while uploading the file    // 'imagefile' is the attribute that we're going to use in Postman    // $_FILES['imagefile']['error'] should be zero 0 if it's been uploaded successfully to server
            sendResponse(500, false, 'Image file upload unsuccessful - make sure you selected a file'); // 500 means Internal Server Error
        }

        // Do some validation on the uploaded image
        // getimagesize() function takes in the image location as an argument, and checks the uploaded file to make sure it's an image file and provides a lot of metadata about the image itself (image dimensions, type of image (jpg, png, gif, ...) (MIME type))
        $imageFileDetails = getimagesize($_FILES['imagefile']['tmp_name']); // 'tmp_name' is the temporary location of the uploaded file on the server
        
        if (isset($_FILES['imagefile']['size']) && $_FILES['imagefile']['size'] > 5242880) { // size in bytes (5 MB = 5 * 1024 * 1024 = 5242880 bytes)    // Note: 1048576 (mega) = 1024 * 1024
            sendResponse(400, false, 'File must be under 5MB'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        $allowedImageFileTypes = array('image/jpeg', 'image/gif', 'image/png'); // allowd MIME types

        if (!in_array($imageFileDetails['mime'], $allowedImageFileTypes)) { // make sure that the uploaded image MIME type are one of the three MIME types we allow only
            sendResponse(400, false, 'File type not supported'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        $fileExtension = '';

        // Determine the uploaded file extension based on the MIME type
        switch ($imageFileDetails['mime']) {
            case 'image/jpeg': $fileExtension = '.jpg';
            break;

            case 'image/gif':  $fileExtension = '.gif';
            break;

            case 'image/png':  $fileExtension = '.png';
            break;

            default: break; // if the MIME type is not one of the past three, just drop out of it (then the $fileExtension would be blank)
        }

        if ($fileExtension == '') { // if the $fileExtension is blank
            sendResponse(400, false, 'No valid file extension found for mimetype'); // 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Validation
        // After passing all that validation, we use image model now to build the uploaded image up to perform validation on it, and move it where we want, and save its path into the database
        $image = new Image(null, $jsonImageAttributes->title, $jsonImageAttributes->filename . $fileExtension, $imageFileDetails['mime'], $taskid); // This will perform validation on the image, and if there're any errors thrown (ImageException class errors), it will be caught (catch-ed) a bit down using the catch statement (  catch (ImageException $ex)  )

        $title       = $image->getTitle();
        $newFileName = $image->getFilename();
        $mimetype    = $image->getMimetype();

        // We need to query the database to make sure that the file name that the user provided $newFileName (which is the $jsonImageAttributes->filename) does NOT already exist in our database for that set (specific) task, because on filesystem (in a folder), we can only store a unique file name (a one folder can't contain two files with the same name)
        $query = $readDB->prepare('SELECT tblimages.id FROM tblimages, tbltasks
            WHERE tblimages.taskid = tbltasks.id
            AND tbltasks.id        = :taskid
            AND tbltasks.userid    = :userid
            AND tblimages.filename = :filename'
        ); // We use the $readDB (not $writeDB) because we're obviosuly not writing anything now

        $query->bindParam(':taskid'  , $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid'  , $returned_userid, PDO::PARAM_INT);
        $query->bindParam(':filename', $newFileName    , PDO::PARAM_STR);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount !== 0) { // if there's a filename, with the same filename that the user submitted, already exists in our database
            sendResponse(409, false, 'A file with that filename already exists for this task - try a different filename'); // 409 means Conflict (the data provided is conflicting with something else)
        }

        // Now that the image has passed all that validation, we need to BOTH rename it and move it where we want AND save its path into the database, so here now we need to use a Database Transaction, to gurantee that we BOTH moved the image and added its path into database (and if one of the two operations fails, the whole transaction fails, meaning the two operations fail)
        // If the transaction fails at any point, so we need to write what would happen in such case inside BOTH the catch (PDOException $ex) statement and catch (ImageException $ex) statement down below, which will be that we end the transaction and roll back (and it must be placed after the error_log() to allow the error to be logged to administrator)
        // Database Transaction
        $writeDB->beginTransaction(); // We use the $writeDB (not $readDB) because we're writing now

        // Insert the file (image) information into the database
        $query = $writeDB->prepare('INSERT INTO tblimages (title, filename, mimetype, taskid) VALUES (:title, :filename, :mimetype, :taskid)');

        $query->bindParam(':title'   , $title      , PDO::PARAM_STR); // $title       is "title"    field inside the JSON entered as a Value for the "attributes" field/Key
        $query->bindParam(':filename', $newFileName, PDO::PARAM_STR); // $newFileName is "filename" field inside the JSON entered as a Value for the "attributes" field/Key
        $query->bindParam(':mimetype', $mimetype   , PDO::PARAM_STR);
        $query->bindParam(':taskid'  , $taskid     , PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) { // if inserting the image fails
            // roll back in case of insertion failure
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            sendResponse(500, false, 'Failed to upload image'); // 500 means Internal Server Error
        }

        // Respond to the user who uploaded the image with a JSON response of the image attributes:

        $lastImageID = $writeDB->lastInsertId();

        // Combining/JOIN-ing two tables (`tblimages` and `tbltasks`) to grab information from both of them (grab all the image attributes to send them to the user in a JSON response)
        // Retrieving the image with that $lastImageID for a given task, for the authenticated/logged-in user
        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
            FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid
            AND tblimages.taskid = tbltasks.id'
        ); // We use $writeDB (not $readDB), because if 'replicated slaves' for MySQL are used, it can take a bit time to push across the updates from $writeDB to $readDB, so wee're going to use $writeDB to make sure we're getting the uploaded image successfully

        $query->bindParam(':imageid', $lastImageID    , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) { // if grabbing the image details fails
            // roll back in case of failure
            if ($writeDB->inTransaction()) {
                $writeDB->rollBack();
            }

            sendResponse(500, false, 'Failed to retrieve image attributes after upload - try uploading image again'); // 500 means Internal Server Error
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']); // Pass in the image details to the Imgae model for validation and getting them back again
            $imageArray[] = $image->returnImageAsArray(); 
        }

        // Note: We'll place images of a certain task in a folder named with the task id
        // Note: If moving the file using move_uploaded_file() function fails, we roll back the Database Transaction

        // Upload the image to server
        $image->saveImageFile($_FILES['imagefile']['tmp_name']);

        $writeDB->commit(); // Commit the transaction query to database

        // Send back to user a successful CREATED response with the uploaded image attribute
        sendResponse(201, true, 'Image uploaded successfully', false, $imageArray); // 201 means Created    // We never cache the uploaded image response



        // We catch TWO catch-es (PDOException and ImageException)
    } catch (PDOException $ex) { // In case there's an error querying the database
        echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
        echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

        error_log('Database Query Error: - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
        
        // In case that the transaction fails (one of the two operations fails) (We must place this after the error_log() function to allow the error to be logged for the administrator)
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }

        sendResponse(500, false, 'Failed to upload the image'); // 500 means Internal Server Error
    } catch (ImageException $ex) {
        // In case that the transaction fails (one of the two operations fails) (We must place this after the error_log() function to allow the error to be logged for the administrator)
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }

        sendResponse(500, false, $ex->getMessage()); // 500 means Internal Server Error    // here we get the Exception class message (that we created inside the image.php model) using getMessage()
    }
}



function getImageAttributesRoute($readDB, $taskid, $imageid, $returned_userid) { // Get a certain task image attributes which are "title" and "filename" (not getting the image itself (not downloading image))    // e.g. GET /tasks/1/images/5/attributes
    // We perform our logic with try ... catch ... statement to be able to catch errors (we catch two exception classes: ImageExceptin and PDOException)
    try {
        // We need to combine/JOIN the two tables: `tblimages` and `tbltasks` because we need to grab data from both of them to make sure that for a given $taskid and imageid, we make sure it belongs to the user that's authenticated/logged-in:
        $query = $readDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
            FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid
            AND tblimages.taskid = tbltasks.id'
        );

        $query->bindParam(':imageid', $imageid         , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid          , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid , PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(404, false, 'Image Not Found'); // 404 means Not Found
        }

        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->returnImageAsArray();
        }
        
        sendResponse(200, true, null, true, $imageArray); // 200 means Success or OK

    } catch (ImageException $ex) {
        sendResponse(500, false, $ex->getMessage()); // 500 means Internal Server Error    // here we get the Exception class message (that we created inside the image.php model) using getMessage()
        error_log('Database Query Error: ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
        sendResponse(500, false, 'Failed to get image attributes'); // 500 means Internal Server Error
    }
}



// Download an actual physical image
function getImageRoute($readDB, $taskid, $imageid, $returned_userid) { // Get a certain task physical image itself (the binary image file) (download a task image)    // e.g. GET /tasks/1/images/5
    try {
        // We need to combine/JOIN the two tables: `tblimages` and `tbltasks` because we need to grab data from both of them to make sure that for a given $taskid and imageid, we make sure it belongs to the user that's authenticated/logged in:
        $query = $readDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
            FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid
            AND tblimages.taskid = tbltasks.id'
        );
        $query->bindParam(':imageid', $imageid         , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid          , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid , PDO::PARAM_INT);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(404, false, 'Image Not Found'); // 404 means Not Found
        }

        $image = null;

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        }

        if ($image == null) {
            sendResponse(500, false, 'Image not found'); // 500 means Internal Server Error
        }

        // Return (Download) the actual physical image itself
        $image->returnImageFile();


    } catch (ImageException $ex) {
        sendResponse(500, false, $ex->getMessage()); // 500 means Internal Server Error    // here we get the Exception class message (that we created inside the image.php model) using getMessage()
    } catch (PDOException $ex) {
        error_log('Database Query Error: ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
        sendResponse(500, false, 'Error getting Image (downloading)'); // 500 means Internal Server Error
    }
}



function updateImageAttributesRoute($writeDB, $taskid, $imageid, $returned_userid) { // Route (URL): UPDATE /tasks/{taskid}/images/{imageid}/attributes    // e.g. PATCH /tasks/7/images/15/attributes
    try {
        // Make sure 'Content-Type' header is 'application/json'
        if ($_SERVER['CONTENT_TYPE'] != 'application/json') {
            sendResponse(400, false, 'Content type header not set to JSON');// 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Get the contents of the HTTP request Body
        $rawPatchData = file_get_contents('php://input'); // Retrieve the raw HTTP request body data

        // Check if the Body is JSON type
        if (!$jsonData = json_decode($rawPatchData)) {
            sendResponse(400, false, 'Request body is not valid JSON');// 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // Keep track of which fields are asked to be updated: (initially set all of them to 'false')
        $title_updated    = false;
        $filename_updated = false;

        // Formatting the SQL statement: (SQL statement e.g. 'UPDATE tbltasks SET title = :title, description = :description, deadline = :deadline, completed = :completed WHERE id = :taskid')
        // $queryFields will contain the previous fields that are set to 'true'
        $queryFields = '';

        // Check which fields are asked to be updated, and set them to 'true' and add them to $queryFields
        if (isset($jsonData->title)) {
            $title_updated = true; // set/convert it from 'false' to 'true'
            $queryFields .= 'tblimages.title = :title, ';
        }

        if (isset($jsonData->filename)) {
            // We make sure that the file extension is NOT provided by user in the file name (we DON'T want the file extension to be provided by user), because the file extension is automatically (and dynamically) determined (is not part of the file name), so if the file name has extension, we send an error response back
            if (strpos($jsonData->filename, '.') !== false) { // if the filename has an extension // e.g. image.jpg
                sendResponse(400, false, 'Filename cannot contain any dots or file extensions');// 400 means client error (because the client hasn't submitted the right/proper data)
            }
            
            $filename_updated = true; // set/convert it from 'false' to 'true'
            $queryFields .= 'tblimages.filename = :filename, ';
        }

        // All the previous $queryFields have a comma and a space at the end of them, so we need to strip the comma and the space of the LAST queryField off:
        $queryFields = rtrim($queryFields, ', '); // strip off the LAST comma and space

        // Check if there's any data (title, filename) provided to be updated (set to 'true'), other than that, there's no an UPDATE query:
        if ($title_updated === false && $filename_updated === false) {
            sendResponse(400, false, 'No image fields provided');// 400 means client error (because the client hasn't submitted the right/proper data)
        }

        // We'll use a Database Transaction here, because We'll query the database with SELECT query to make sure the image exists and the image id exists, and we'll physically rename the image file as well. For example, if physically renaming the file fails and we've provided a new filename, then we mustn't update the database with that new filename, and then we do a rollback
        $query = $writeDB->beginTransaction(); // Because we use beginTransaction() function, we must use rollBack() function inside the TWO catch() statements (i.e.  catch (ImageException $ex) and catch (PDOException $ex)  ) and inside the if statement that checks if the query is successful
        
        // We need to combine/JOIN the two tables: `tblimages` and `tbltasks` because we need to grab data from both of them to make sure that for a given $taskid, we make sure the image exists and belongs to the user that's authenticated/logged-in:
        // We query the database to get the task image, to make sure it exists, before we update it
        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
            FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tblimages.taskid = :taskid
            AND tblimages.taskid = tbltasks.id
            AND tbltasks.userid = :userid'
        );

        $query->bindParam(':imageid', $imageid        , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) { // If we're in the middle of a Database Transaction, roll back
                $writeDB->rollBack();
            }

            sendResponse(404, false, 'No image found to update'); // 404 means Not Found
        }

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        }

        // Build the UPDATE SQL query:
        // Because we're linking that to a $taskid, we need to JOIN tblimages to tbltasks table
        $queryString = 'UPDATE tblimages INNER JOIN tbltasks ON tblimages.taskid = tbltasks.id
        SET ' . $queryFields . ' WHERE tblimages.id = :imageid AND tblimages.taskid = tbltasks.id AND tblimages.taskid = :taskid AND tbltasks.userid = :userid';
        $query = $writeDB->prepare($queryString); // Using the $writeDB (not $readDB) because we're UPDATE -ing

        // Bind the UPDATE parameters if they have been set
        // Overriding the new updated values in the $image class object which is the record that is being updated: (to be validated)    // Set the updated new value, and get it again back out from the model
        if ($title_updated === true) {
            $image->setTitle($jsonData->title); // overriding (updating) - overwriting the new value over the old one in the object
            $up_title = $image->getTitle(); // Getting the title again, after having had been validated
            $query->bindParam(':title', $up_title, PDO::PARAM_STR);
        }

        if ($filename_updated === true) {
            $originalFilename = $image->getFilename(); // We need to store the file name before we've updated it temporarily

            $image->setFilename($jsonData->filename . '.' . $image->getFileExtension()); // overriding (updating) - overwriting the new value over the old one in the object
            $up_filename = $image->getFilename(); // Getting the filename again, after having had been validated
            $query->bindParam(':filename', $up_filename, PDO::PARAM_STR);
        }

        // Bind the rest of the parameters:
        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid , PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) { // if it's 0 zero, this means the update submitted data are the same as the already existing data    // Because the number of the rows affected resulting from the UPDATE query are 0 zero, this means that the submitted data to be updated are exactly the same as the already existing data
            if ($writeDB->inTransaction()) { // If we're in the middle of a Database Transaction, roll back
                $writeDB->rollBack();
            }

            sendResponse(400, false, 'Image attributes not updated - the given values may be the same as the stored values'); // Because the number of the rows affected resulting from the UPDATE query are 0 zero, this means that the submitted data to be updated are exactly the same as the already existing data    // 400 Status Code means client error
        }

        // Return the updated record back to the client after having been updated:
        // Using the $writeDB (not $readDB) here because by the time that record has been UPDATE-ed, we immediately try to return it from the database, and it may not have had time to populate or push this record data from the writeDB to readDB, because readDB-s are Asynchronous (while writeDB-s are Synchronous), so we must use the writeDB here in this case even though it's just a READ statement:
        $query = $writeDB->prepare('SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid
            AND tbltasks.id = tblimages.taskid
            AND tbltasks.userid = :userid'
        );

        $query->bindParam(':imageid', $imageid, PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid , PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            if ($writeDB->inTransaction()) { // If we're in the middle of a Database Transaction, roll back
                $writeDB->rollBack();
            }

            sendResponse(404, false, 'No Image Found'); // 404 means Not Found
        }

        // Put the image back into the image model, and return it back to the user as a JSON response
        $imageArray = array();

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
            $imageArray[] = $image->returnImageAsArray();
        }
        
        // Rename the physical image file:
        if ($filename_updated === true) { // if the filename was updated (in the database), we need to update the actual file name on the server (on the filesystem)
            $image->renameImageFile($originalFilename, $up_filename);
        }

        // Save (commit) the Database Transaction changes:
        $writeDB->commit();

        sendResponse(200, true, 'Image attributes updated', false, $imageArray); // 200 means Success or OK

    } catch (PDOException $ex) {
        echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
        echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

        error_log('Database Query Error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
        
        // In case that the transaction fails (one of the two operations fails) (We must place this after the error_log() function to allow the error to be logged for the administrator)
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }

        sendResponse(500, false, 'Failed to update image attributes - check your data for errors'); // 500 means Internal Server Error
    } catch (ImageException $ex) {
        echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
        echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

        // In case that the transaction fails (one of the two operations fails) (We must place this after the error_log() function to allow the error to be logged for the administrator)
        if ($writeDB->inTransaction()) {
            $writeDB->rollBack();
        }

        sendResponse(400, false, $ex->getMessage());// 400 means client error (because the client hasn't submitted the right/proper data)    // here we get the Exception class message (that we created inside the image.php model) using getMessage()
    }
}



function deleteImageRoute($writeDB, $taskid, $imageid, $returned_userid) { // Delete the physical image file on the server (on the filesystem) and delete the image row out of the `tblimages` database table    // Route (URL): DELETE /tasks/{taskid}/images/{imageid}    // e.g. DELETE /tasks/1/images/5
    try {
        // Here we need a Database Transaction because we need BOTH delete the actual physical image file AND delete the image row in the database table AT THE SAME TIME, so we want if one the two operations fails, the other one gets undone
        // Because we use beginTransaction() method, we need to use rollBack() inside all of the catch statements blocks
        $writeDB->beginTransaction();

        // Make sure that the $taskid, $imageid and $returned_userid that are passed in in the URL query string parameters all exist
        // Make sure the owner of the task is the authenticated user (logged-in user)
        $query = $writeDB->prepare(
            'SELECT tblimages.id, tblimages.title, tblimages.filename, tblimages.mimetype, tblimages.taskid
            FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid AND tbltasks.userid = :userid
            AND tblimages.taskid = tbltasks.id'
        );

        $query->bindParam(':imageid', $imageid        , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) { // if there's a filename, with the same filename that the user submitted, already exists in our database
            $writeDB->rollBack();

            sendResponse(404, false, 'Image not found'); // 404 means Not Found
        }

        // Get the image back:
        $image = null;

        while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
            $image = new Image($row['id'], $row['title'], $row['filename'], $row['mimetype'], $row['taskid']);
        }

        if ($image == null) {
            $writeDB->rollBack();

            sendResponse(500, false, 'Failed to get Image'); // 500 means Internal Server Error
        }

        // Delete the image row in `tblimages` database table
        // Using 'JOIN' with DELETE query
        $query = $writeDB->prepare(
            'DELETE tblimages FROM tblimages, tbltasks
            WHERE tblimages.id = :imageid AND tbltasks.id = :taskid
            AND tblimages.taskid = tbltasks.id
            AND tbltasks.userid = :userid'
        );

        $query->bindParam(':imageid', $imageid        , PDO::PARAM_INT);
        $query->bindParam(':taskid' , $taskid         , PDO::PARAM_INT);
        $query->bindParam(':userid' , $returned_userid, PDO::PARAM_INT);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            $writeDB->rollBack();

            sendResponse(404, false, 'Image Not Found'); // 404 means Not Found
        }

        // Delete the actual physical image file from the server (from the filesystem):
        $image->deleteImageFile();

        // Save (commit) the Database Transaction changes:
        $writeDB->commit();

        sendResponse(200, true, 'Image Deleted'); // 200 means Success or OK

    } catch (PDOException $ex) { // In case there's an error querying the database
        error_log('Database Query Error: - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file

        $writeDB->rollBack();

        sendResponse(500, false, 'Failed to delete image'); // 500 means Internal Server Error
    } catch (ImageException $ex) {
        $writeDB->rollBack();

        sendResponse(500, false, $ex->getMessage()); // 500 means Internal Server Error    // here we get the Exception class message (that we created inside the image.php model) using getMessage()
    }
}



// This method return-s the `userid` of the authenticated/logged-in user
function checkAuthStatusAndReturnUserID($writeDB) { // A helper function to check the authorization status (logged in and using access token) and return-s the user id `userid` of the authenticated/logged-in user
    // Before deciding which route is used (BEFORE anything and everything), we add the authorization (authentication) script here (to make sure user has provided the 'access token' and check it to make sure it's valid and hasn't expired and that the user is valid):

    // Start authorization (authentication) script:
    // Make sure the access token from the HTTP header (the authorization header) is provided and it's not empty (left blank)
    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        // Doing some Code Refactoring:
        $message = null;

        if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $message = 'Access token is missing from the header';
        } else { // if the access token exits but no value provided for it
            if (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
                $message = 'Access token cannot be blank';
            }
        }
        sendResponse(401, false, $message); // 401 means Unauthorized
    }

    $accesstoken = $_SERVER['HTTP_AUTHORIZATION'];

    // We're going to query the database, so we should use try ... catch ... statement:
    try {
        // Perform a database query based on that $accesstoken to bring back its user details and session details so we can check `useractive`, `loginattempts` and the access token hasn't expired, ...etc
        $query = $writeDB->prepare(
            'SELECT tblsessions.userid, tblsessions.accesstokenexpiry, tblusers.useractive, tblusers.loginattempts
            FROM tblsessions, tblusers WHERE tblsessions.userid = tblusers.id
            AND accesstoken = :accesstoken'
        );

        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);

        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            sendResponse(401, false, 'Invalid Access Token'); // 401 means Unauthorized
        }

        // Here, we're not doing anything with the 'refresh toke' as it's handled by the Authentication API (in sessions.php page) only. Here, we just check if the 'Access Token' is still valid (i.e. not expired)

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_userid            = $row['userid'];
        $returned_accesstokenexpiry = $row['accesstokenexpiry'];
        $returned_useractive        = $row['useractive'];
        $returned_loginattempts     = $row['loginattempts'];

        // Making sure the user is active:
        if ($returned_useractive !== 'Y') { // if the user is not active ('Y' means yes)
            sendResponse(401, false, 'User account not active'); // 401 Status Code means Unauthorized
        }

        // Making sure the user is not currently locked out:
        if ($returned_loginattempts >= 3) { // if number of login attemps is equal to or greater than 3, user is locked out of their account and they need a reset
            sendResponse(401, false, 'User account is currently locked out'); // 401 means Unauthorized
        }

        // Checking if the access token is still valid or has expired:
        if (strtotime($returned_accesstokenexpiry) < time()) { // if the time stored in the database (the access token expiry date) is less than the current time, this means the access token has expired
            sendResponse(401, false, 'Access token has expired'); // 401 Status Code means Unauthorized
        }



        // Returning the user ID (based on the 'Access Token' that they provided):
        return $returned_userid;


    } catch (PDOException $ex) { // If querying the database fails
        echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
        echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message
        sendResponse(500, false, 'There was an issue authenticating - please try again'); // 500 means Internal Server Error
    }
    // End authorization (authentication) script

    // NEXT: FOR EACH SQL QUERY IN THAT PAGE (FOR EACH ROUTE), WE MUST MAKE SURE IT HAS TAKEN INTO ACCOUNT THE `userid`! (in order to limit that a user can only view their own images only and not other people images (to view only the images that belong to a user only, and not other users/people images)) (user-centric or per-user basis)
}


// Note: Every task has one or multiple images

/*
Our Routes/Endpoints are:
    Get a certain task's certain image attributes:    GET /tasks/{taskid}/images/{imageid}/attributes
    Get (Download) an image itself of a certain task: GET /tasks/{taskid}/images/{imageid}
    Create (upload) an image for a certain task:      POST /tasks/{taskid}/images
    Delete an image itself of a certain task:         DELETE /tasks/{taskid}/images/{imageid}
*/



// Connect to the database:
try {
    $writeDB = DB::connectWriteDB();
    $readDB  = DB::connectReadDB();
} catch (PDOException $ex) { // If there's an error connecting to one of the two databases
    echo '<pre>', var_dump($ex), '</pre>'; // Display the thrown Exception Object
    echo '<pre>', var_dump($ex->getMessage()), '</pre>'; // Display the thrown Exception message

    error_log('Connection Error - ' . $ex, 0); // Send the real error to the system administrator    // 0 means it stores/logs the error in the PHP error log file
    sendResponse(500, false, 'Database connectin error'); // 500 means Internal Server Error
}



// Doing Authentication and Getting the user id of the user that's trying to access our API endpoints (or routes):
$returned_userid = checkAuthStatusAndReturnUserID($writeDB); // Authentication should be done always against the master database (write database) because it's synchronous and up-to-date, as apposed to the read database which is asynchronous



// If the three of {taskid}, {imageid} and {attributes} Query String Parameters are provided in the URL, we handle either Get a certain image Attributes (of a certain task that belongs to the authenticated/logged-in user) (GET), or Update a certain image Attributes (of a certain task that belongs to the authenticated/logged-in user) (PATCH):    GET /tasks/{taskid}/images/{imageid}/attributes    // e.g. GET /tasks/3/images/5/attributes    Or    PATCH /tasks/{taskid}/images/{imageid}/attributes    // e.g. PATCH /tasks/3/images/5/attributes
if (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET) && array_key_exists('attributes', $_GET)) {
    $taskid     = $_GET['taskid'];
    $imageid    = $_GET['imageid'];
    $attributes = $_GET['attributes'];

    // Do some Validation:
    if ($taskid == '' || !is_numeric($taskid) || $imageid == '' || !is_numeric($imageid)) {
        sendResponse(400, false, 'Image ID or Task ID cannot be blank and must be numeric'); // 400 means client error (because the client hasn't submitted the right/proper data)
    }

    // Route: Get a certain image attributes of a certain task:    GET /tasks/{taskid}/images/{imageid}/attributes    // e.g. GET /tasks/1/images/5/attributes
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImageAttributesRoute($readDB, $taskid, $imageid, $returned_userid);

    // Route: Update a certain image attributes of a certain task:    PATCH /tasks/{taskid}/images/{imageid}/attributes    // e.g. PATCH /tasks/1/images/5/attributes
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        updateImageAttributesRoute($writeDB, $taskid, $imageid, $returned_userid);

    } else { // We don't allow any request methods other than GET or PATCH for this route
        sendResponse(405, false, 'Request method not allowed'); // 405 means Request Method Not Allowed
    }

    // If both {taskid} and {imageid} Query String Parameters are provided in the URL, we handle either Get (Download) an actual physical image of a certain task (GET) or Delete an actual physical image of a certain task (of the authenticated/logged-in user):    GET /tasks/{taskid}/images/{imageid}    // e.g.    GET /tasks/3/images/5    or    DELETE /tasks/{taskid}/images/{imageid}    // e.g.    DELETE /tasks/3/images/5
} elseif (array_key_exists('taskid', $_GET) && array_key_exists('imageid', $_GET)) {
    $taskid  = $_GET['taskid'];
    $imageid = $_GET['imageid'];

    // Do some validation:
    if ($taskid == '' || !is_numeric($taskid) || $imageid == '' || !is_numeric($imageid)) {
        sendResponse(400, false, 'Image ID or Task ID cannot be blank and must be numeric'); // 400 means client error (because the client hasn't submitted the right/proper data)
    }

    // Get (Download) an actual physical image (the binary image file) of a certain task: GET /tasks/{taskid}/images/{imageid}    // e.g. GET /tasks/5/images/2
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        getImageRoute($readDB, $taskid, $imageid, $returned_userid);

    //  Delete an actual physical image of a certain task: DELETE /tasks/{taskid}/images/{imageid}    // e.g. DELETE /tasks/5/images/2
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
        deleteImageRoute($writeDB, $taskid, $imageid, $returned_userid);

    } else { // We don't allow any request methods other than GET or DELETE for this route
        sendResponse(405, false, 'Request method not allowed'); // 405 means Request Method Not Allowed
    }

    // If the {taskid} Query String Parameter is provided in the URL while the {imageid} is NOT provided, we handle Create (upload) an image for a certain task (that belongs to the authenticated/logged-in user):    POST /tasks/{taskid}/images    // e.g. POST /tasks/3/images
} elseif (array_key_exists('taskid', $_GET) && !array_key_exists('imageid', $_GET)) { // making sure that 'imageid' does NOT exist in the URL
    $taskid = $_GET['taskid'];

    // Do some validation:
    if ($taskid == '' || !is_numeric($taskid)) {
        sendResponse(400, false, 'Task ID cannot be blank and must be numeric'); // 400 means client error (because the client hasn't submitted the right/proper data)
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') { // Create (upload) an image belonging to a certain task
        uploadImageRoute($readDB, $writeDB, $taskid, $returned_userid);

    } else { // We don't allow any request methods other than POST for this route
        sendResponse(405, false, 'Request method not allowed'); // 405 means Request Method Not Allowed
    }


} else { // if the URL were random like /v1/ghag254152 or anything other than that what we previously specified
    sendResponse(404, false, 'Endpoint not found'); // 404 means Not Found
}