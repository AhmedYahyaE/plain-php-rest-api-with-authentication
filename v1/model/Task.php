<?php
// the Task model represents/models the `tbltasks` table in the database
// This model class is used to store the task data when you retrieve it from database Or when you create a new task (we can have multiple task objects depending on how many tasks will return. Each task will be in its own object), also, this model will handle the validation of a task (for example manager fields and valid data values)
// This class will be used for validation (of data grabbed from database, and also data submitted to insert into database (whether create or update))
// We use this class as a bridge to validate (BOTH cases when we grab data from database, and when we insert data from client to database)


class TaskException extends Exception {} // NOTE: Extending the PHP built-in Exception class to throw valid task exceptions (Check "throw new TaskException" expressions here in this class), so we use this to handle different types of possible validation errors within the task, for example mandatory fields



class Task {
    private $_id; // generated automatically by database (NOT NULL AUTO_INCREMENT in database)
    private $_title; // mandatory (NOT NULL in database)
    private $_description; // optional (NULL in database)
    private $_deadline; // optional (NULL in database)
    private $_completed; // optional (NULL in database and DEFAULT-s to 'N')

    private $_images; // the task images array (optional)



    public function __construct($id, $title, $description, $deadline, $completed, $images = array()) {
        // Call the setter methods (which does the VALIDATION work):
        $this->setID($id);
        $this->setTitle($title);
        $this->setDescription($description);
        $this->setDeadline($deadline);
        $this->setCompleted($completed);
        $this->setImages($images);
    }



    // Getters (for the private properties):

    public function getID() {
        return $this->_id;
    }

    public function getTitle() {
        return $this->_title;
    }

    public function getDescription() {
        return $this->_description;
    }
    
    public function getDeadline() {
        return $this->_deadline;
    }

    public function getCompleted() {
        return $this->_completed;
    }

    public function getImages() {
        return $this->_images;
    }



    // Setters (for the private properties): (contains the Validation for the values for example for mandatory fields and their data value types)

    public function setID($id) { // $id is generated automatically by database (NOT NULL AUTO_INCREMENT in database)
        // Validation:
        if (($id !== null) && (!is_numeric($id) || $id <= 0  || $id > 9223372036854775807 || $this->_id !== null)) { // 1 to 9223372036854775807 is the id column size in database    //    $this->_id !== null    is to make sure we're not overriding a task id that's already populated (already existing) in the database table in case of UPDATE SQL queries (N.B. We pass in 'null' to the setID($id) method in case of INSERT SQL queries)
            throw new TaskException('Task ID error'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_id = $id;
    }

    public function setTitle($title) { // mandatory (NOT NULL in database)
        // Validation:
        if (strlen($title) < 1 || strlen($title) > 255) { // Maximum character size of the title column in database is 255 characters
            throw new TaskException('Task title error'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_title = $title;
    }

    public function setDescription($description) { // optional (NULL in database)
        // Validation:
        if (($description !== null) && (strlen($description) > 16777215)) { // Maximum character size of the description column in database is 16777215 characters
            throw new TaskException('Task description error'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_description = $description;
    }

    public function setDeadline($deadline) { // optional (NULL in database)
        // echo '<pre>', var_dump(date_create_from_format("m/Y/d H:i:s", "02/2019/12 07:30:25")), '</pre>';
        // echo '<pre>', var_dump(date_format(date_create_from_format("m/Y/d H:i:s", "02/2019/12 07:30:25"), 'Y/m/d H:i')), '</pre>';

        // Validation:
        if (($deadline !== null) && date_format(date_create_from_format('d/m/Y H:i', $deadline), 'd/m/Y H:i') != $deadline) { // date_create_from_format() takes a time string and returns a DateTime ojbect    // date_format() returns a formatted date string on success    // converting the coming in date string into an object and then converting it to string again, then checking the converted string if it matches the coming in string    // 'd/m/Y H:i' means for example 15/01/2019 13:30
            throw new TaskException('Task deadline date time error'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_deadline = $deadline;
    }

    public function setCompleted($completed) { // optional (NULL in database and DEFAULT to 'N')
        // Validation:
        if (strtoupper($completed) !== 'Y' && strtoupper($completed !== 'N')) { // 
            throw new TaskException('Task completed must be Y or N'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_completed = $completed;
    }

    public function setImages($images) { // $images array
        // Validation:
        if (!is_array($images)) {
            throw new TaskException('Images is not an array'); // new TaskException() means we create a TaskException class object and pass in that message to its constructor    // We can get that message we passed in to the class using the Exception class getMessage() method
        }

        $this->_images = $images;
    }



    public function returnTaskAsArray() {
        $task = array();

        // Use the Getters:
        $task['id']          = $this->getID();
        $task['title']       = $this->getTitle();
        $task['description'] = $this->getDescription();
        $task['deadline']    = $this->getDeadline();
        $task['completed']   = $this->getCompleted();
        $task['images']      = $this->getImages();

        return $task;
    }

}