<?php
// This class is responsible for the return of the JSON response to the end user or client
// Note: When dealing with an API with authentication, we send the 'access token' in the HTTP header with every request, but for the 'refresh token', we ever send it in the HTTP body only when we want to refresh a session.


class Response {
    // Our response components (private properties that will be set using setter functions/methods):
    private $_success; // (A Boolean i.e true or false) successful response or not
    private $_httpStatusCode; // e.g. 200, 500, ...
    private $_messages = array(); // (an array)
    private $_data; // data themselves returned from the response

    // For our internal processes:
    private $_toCache = false; // e.g. We can cache the response to our request (e.g. if a client request to return all of the their tasks, we can cache the response, so if then the client refreshes or requests their list again within, say, 20 seconds or 30 seconds, we don't have to go back to the server and call the database and return the details from there, but we just return the cached response from the client. That saves any sort of additional load on the server.)
    // Note: Things that should NEVER be cached are: credentials, access tokens, ... because it's a security risk

    private $_responseData = array(); // The array that will contain all the response (that will be converted to JSON using json_encode() function)    // The whole response data array ITSELF that we will send as a JSON response    // After we built all the previous things up, we will use json_encode() to turn all that into JSON to send them as a JSON response



    // Setters (Setter Functions) of our private properties: (it's a good practice)
    public function setSuccess($success) {
        $this->_success = $success;
    }

    public function setHttpStatusCode($httpStatusCode) {
        $this->_httpStatusCode = $httpStatusCode;
    }

    public function addMessage($message) { // Add a message to the $_messages array
        $this->_messages[] = $message;
    }

    public function setData($data) {
        $this->_data = $data;
    }

    public function toCache($toCache) { // The question is: to cache or not to cache?
        $this->_toCache = $toCache;
    }



    public function send() { // use all the previous data to send the response to the user/client
        // Send a raw HTTP "Content-Type" Header
        header('Content-type: application/json;charset=utf-8'); // define what we're returning as a response. We're returning JSON, with a character set of type utf-8


        if ($this->_toCache == true) { // to cache the respone or not
            header('Cache-control: max-age=60'); // cache or store the response for a maximum of 60 seconds
        } else { // You must explicitly declare the else statement (You must write the ELSE STATEMENT, you can't ingore it!!), or rather it would result in an error
            header('Cache-control: no-cache, no-store'); // tells the client you can't store any response on the browser (client), you have to always come back to the server to get a response
        }


        if (($this->_success !== false && $this->_success !== true) || !is_numeric($this->_httpStatusCode)) { // If $_succes is something other than a Boolean (Not a true or false) or the $_httpStatusCode is not numeric    // We want to make sure if the response that we're creating is valid before we send it to the client, and make sure it's a standard response
            http_response_code(500); // set 500 as a status code, which means: Internal Server Error    // This appears in the Network tab in Inspect tools in browser
            $this->_responseData['statusCode'] = 500;
            $this->_responseData['success']    = false;
            $this->addMessage('Response creation error');
            // $this->addMessage('Test message'); // Add another message
            $this->_responseData['messages']   = $this->_messages;
        } else { // Send a successful response
            http_response_code($this->_httpStatusCode);

            // Fill in the $_responseData array with the previous gathered information like $_httpStatusCode, $_success, $_messages and $_data
            $this->_responseData['statusCode'] = $this->_httpStatusCode;
            $this->_responseData['success']    = $this->_success;
            $this->_responseData['messages']   = $this->_messages;
            $this->_responseData['data']       = $this->_data;
        }


        echo json_encode($this->_responseData); // encode/convert the PHP array into JSON and echo it
    }

}