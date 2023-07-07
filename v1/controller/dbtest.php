<?php

require_once('db.php');
require_once('../model/Response.php');
// var_dump(php_ini_loaded_file());

// Because we're using Exceptions:
try {
    $writeDB = DB::connectWriteDB();
    $readDB  = DB::connectReadDB();
} catch(PDOException $ex) {
    $response = new Response();
    $response->setHttpStatusCode(500); // A server error
    $response->setSuccess(false);
    $response->addMessage('Database Connection error');
    $response->send();
    exit; // exit; is a good practice here
}
// catch(MyException $ex) {
//     $response = new Response();
//     $response->setHttpStatusCode(500); // A server error
//     $response->setSuccess(false);
//     $response->addMessage('Database Connection error');
//     $response->send();
//     exit; // exit; is a good practice here
// }