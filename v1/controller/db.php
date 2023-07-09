<?php
// Connection to database
// Note: When dealing with an API with authentication, we send the 'access token' in the HTTP header with every request, but for the 'refresh token', we ever send it in the HTTP body only when we want to refresh a session.



class DB { // Read about scaling the MySQL databases for large projects (splitting the database to TWO databases: A one master write database (read) and multiple read databases (write))
    private static $writeDBConnection; // Master database (Write database) (synchronous  databases)
    private static $readDBConnection;  // Slave  database (Read  database) (asynchronous databases) cluster, could point to a DNS entry that contain lots of read-only databases (to split the load on databases in case of very high number of records or users in the database)


    public static function connectWriteDB() {
        // database connection muse be a singleton object (there must be a one connection only across your whole project/application, and you just reuse it whenever you need it)

        if (self::$writeDBConnection === null) { // to start initiation of the connection
            self::$writeDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', ''); // Create the database connection
            
            // Setting some attributes on the connection:
            self::$writeDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // the error mode is Exception. They are good because you can catch them, deal with them, and they are a good way to error handling in PHP
            self::$writeDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // means emulate prepare() statements because not every database management system can handle prepared statements
        }


        return self::$writeDBConnection;
    }


    public static function connectReadDB() {
        // Database connection must be a singleton object (there must be a one connection only across/throughout your whole project/application, and you just reuse it whenever you need it)
        if (self::$readDBConnection === null) { // to start initiation of the connection
            self::$readDBConnection = new PDO('mysql:host=localhost;dbname=tasksdb;charset=utf8', 'root', ''); // Create the database connection
            
            // Setting some attributes on the connection:
            self::$readDBConnection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); // the error mode is Exception. They are good because you can catch them, deal with them, and they are a good way to error handling in PHP
            self::$readDBConnection->setAttribute(PDO::ATTR_EMULATE_PREPARES, false); // means emulate prepare() statements because not every database management system can handle prepared statements
        }


        return self::$readDBConnection;
    }

}