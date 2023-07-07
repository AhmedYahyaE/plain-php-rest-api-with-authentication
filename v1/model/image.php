<?php
// the image model represents/models the `tblimages` table in the database
// The image model represents an image and will be responsible for saving (and validation work) and deleting the physical image file
// This class will be used for validation (of data grabbed from database, and also data submitted to insert into database (whether create or update))
// We use this class as a bridge to validate (BOTH cases when we grab data from database, or when we insert data from client to database (and validation work))


class ImageException extends Exception {} // create an ImageException class which is extending the PHP built-in Exception class to throw valid image exceptions (Check "throw new ImgaeException" expressions here in this class), so we use this to handle different types of possible validation errors within the image


class Image {
    private $_id;
    private $_title;
    private $_filename;
    private $_mimetype;
    private $_taskid; // the FOREIGN KEY to `tbltasks`.`id`

    private $_uploadFolderLocation; // This property is assigned its value inside the Constructor function    // We'll place images of a certain task in a folder named with the task id



    public function __construct($id, $title, $filename, $mimetype, $taskid) {
        // Call the setter methods (which does the VALIDATION work):
        $this->setID($id);
        $this->setTitle($title);
        $this->setFilename($filename);
        $this->setMimetype($mimetype);
        $this->setTaskID($taskid);

        $this->_uploadFolderLocation = '../taskimages/'; // We'll place images of a certain task in a folder named with the task id
    }


    
    // Getters (for the private properties):
    
    public function getID () {
        return $this->_id;
    }

    public function getTitle () {
        return $this->_title;
    }

    public function getFilename () {
        return $this->_filename;
    }

    public function getFileExtension() { // A Helper Function
        // e.g. 'imagename.jpg'
        $filenameParts = explode('.', $this->_filename); // e.g. ['imagename', 'jpg']
        $lastArrayElement = count($filenameParts) - 1; // because    length = index + 1    i.e.    index = length - 1
        $fileExtension = $filenameParts[$lastArrayElement];

        return $fileExtension;
    }

    public function getMimetype () {
        return $this->_mimetype;
    }

    public function getTaskID() {
        return $this->_taskid;
    }

    public function getUploadFolderLocation() {
        return $this->_uploadFolderLocation; // Which is determined by the Constructor Function
    }



    // Setters (for the private properties): (contains the Validation for the values)
    public function setID($id) {
        // Validation:
        if (($id !== null) && (!is_numeric($id) || $id <= 0  || $id > 9223372036854775807 || $this->_id !== null)) { // 1 to 9223372036854775807 is the id column size in database    // $this->_id !== null    is to make sure we're not overriding an image id that's already populated in the database table
            throw new ImageException('Image ID Error'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_id = $id;
    }

    public function setTitle($title) { // mandatory (NOT NULL in database)
        // Validation:
        if (strlen($title) < 1 || strlen($title) > 255) { // Maximum character size of the title column in database is 255 characters
            throw new ImageException('Image title error'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_title = $title;
    }

    public function setFilename($filename) { // mandatory (NOT NULL in database)
        // Validation:
        if (strlen($filename) < 1 || strlen($filename) > 30 || preg_match('/^[a-zA-Z0-9_-]+(.jpg|.gif|.png)$/', $filename) != 1) { // Maximum character size of the filename column in database is 30 characters    // Regular Expression to make sure the image name contains an image name and the image extension of one of OUR CHOICE file extensions
            throw new ImageException('Image filename error - must be between 1 and 30 characters, only be .jpg .png .gif and without spaces'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_filename = $filename;
    }

    public function setMimetype($mimetype) { // mandatory (NOT NULL in database)
        // Validation:
        if (strlen($mimetype) < 1 || strlen($mimetype) > 255) { // Maximum character size of the mimetype column in database is 255 characters
            throw new ImageException('Image mimetype error'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_mimetype = $mimetype;
    }

    public function setTaskID($taskid) {
        // Validation:
        if (($taskid !== null) && (!is_numeric($taskid) || $taskid <= 0  || $taskid > 9223372036854775807 || $this->_taskid !== null)) { // 1 to 9223372036854775807 is the id column size in database    // $this->_id !== null    is to make sure we're not overriding an image id that's already populated in the database table
            throw new ImageException('Image Task ID Error'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_taskid = $taskid;
    }

    public function getImageURL() {
        // e.g. http://localhost:8888/v1/tasks/2/images/1
        // We build up the URL as follows:
        $httpOrHttps = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http'); // Check whether the "HTTPs" or "HTTP" protocol is used with the HTTP Request
        $host = $_SERVER['HTTP_HOST']; // hostname and port number (e.g.    wwe.com:80    )
        $url = '/v1/tasks/' . $this->getTaskID() . '/images/' . $this->getID(); // Check our API routes (e.g. /tasks/3/images/2)
        return $httpOrHttps . '://' . $host . $url; // e.g.    http://localhost:8888/v1/tasks/2/images/1
    }



    public function returnImageFile() { // return the physical image file (the binary file itself) of a certain task
        $filepath = $this->getUploadFolderLocation() . $this-> getTaskID() . '/' . $this->getFilename(); // e.g. '../taskimages/' . '3' . 'car.jpg'
        
        // Check if this file with those critera exists
        if (!file_exists($filepath)) {
            throw new ImageException('Image file not found'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        // Now, all our current HTTP responses at the minute are of JSON type, but we're going to return an actual binary file here, so we need to switch "Content-Type" now to the content type of the mimetype of the file we're downloading (dynamically)
        // Check https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition
        header('Content-Type: ' . $this->getMimetype()); // e.g. 'Content-Type: image/jpge'
        header('Content-Disposition: inline; filename="' . $this->getFilename() . '"'); // "Content-disposition" could be either 'attachment' (will force the attachment to download) or 'inline' (browser/client (Postman) will try to open the file within itself (within the client) without downloading)    // If you don't use the 'filename' parameter to provide the file name, file would get a random name     // Check https://stackoverflow.com/questions/1395151/content-dispositionwhat-are-the-differences-between-inline-and-attachment and https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Content-Disposition and https://www.geeksforgeeks.org/http-headers-content-disposition/

        if (!readfile($filepath)) { // Here we read the file back to client (stream the binary file back to the client) and, AT THE SAME TIME, if it can't stream it to the client, it sends a 404 response back
            http_response_code(404); // 404 Not Found    // We HERE can't send an error response back as a JSON response, because the trouble is HERE we already switched headers from "Content-Type" 'JSON' to the 'mime type' of the file we're downloading
            exit;
        }
        exit; // If it successfully streams and reads the file, please exit the script
    }



    public function saveImageFile($tempFileName) { // we'll place images of a certain task in a folder named with the task id    // $tempFileName is the uploaded file temporary location on the server, which we can get from using $_FILES['imagefile']['tmp_name']
        // If moving the file using move_uploaded_file() function fails, we roll back the database transaction

        // Build the file path inside the main uploaded files folder ('taskimages' folder) that we're going to upload the file of a specific task
        // We're going to name the folder which contains the files of a specific task with the task id e.g. folder name is '29'
        $uploadedFilePath = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $this->getFilename(); // $this->getFilename() will return the complete file name with its extension

        if (!is_dir($this->getUploadFolderLocation() . $this->getTaskID())) { // check if the uploaded files folder OF A SPECIFIC TASK exists (NOT the main uploaded files folder), and if not, we create it
            if (!mkdir($this->getUploadFolderLocation() . $this->getTaskID())) {
                throw new ImageException('Failed to create image upload folder for task'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
            }
        }

        if (!file_exists($tempFileName)) { // check if the temporary file on the server exists
            throw new ImageException('Failed to upload image file'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        if (!move_uploaded_file($tempFileName, $uploadedFilePath)) { // check (and PRACTICALLY MOVES the uploaded file) if moving the temporary uploaded file to our path is successful
            throw new ImageException('Failed to upload image file (to path)'); // new ImageException() means we create an ImageException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }
    }



    public function renameImageFile($oldFileName, $newFileName) { // Rename the actual physical file name on the server (on the filesystem)
        $originalFilePath = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $oldFileName;
        $renamedFilePath  = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $newFileName;

        if (!file_exists($originalFilePath)) { // Check if the old file exists
            throw new ImageException('Cannot find image file to rename');
        }

        if (!rename($originalFilePath, $renamedFilePath)) { // Rename the file and check if this fails AT THE SAME TIME
            throw new ImageException('Failded to update the filename');
        }
    }



    public function deleteImageFile() { // delete the actual physical file on the server (on the filesystem)
        $filepath = $this->getUploadFolderLocation() . $this->getTaskID() . '/' . $this->getFilename();

        if (file_exists($filepath)) { // Check if the old file exists
            if (!unlink($filepath)) { // checks success and deletes the file at the same time
                throw new ImageException('Failded to delete image file');
            }
        }
    }



    // Return an image Attributes:
    public function returnImageAsArray() { // A helper function
        $image = array();

        // Use the getters:
        $image['id']       = $this->getID();
        $image['title']    = $this->getTitle();
        $image['filename'] = $this->getFilename();
        $image['mimetype'] = $this->getMimetype();
        $image['taskid']   = $this->getTaskID();
        $image['imageurl'] = $this->getImageURL();

        return $image;
    }

}