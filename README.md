# Plain PHP REST/RESTful API with Token-based Authentication and Image Uploading
A comprehensive Plain PHP REST/RESTful API with Token-based Authentication and Image Uploading feature. Totally Object-oriented design (OOP).

## Screenshots:
***REST/REST API Constraints:***

![REST-API-Constraints](https://github.com/AhmedYahyaE/plain-php-rest-api-with-authentication/assets/118033266/36e5c1ff-10f3-49d9-a6d6-638227d6ab78)

## Features:
1- MVC Design Pattern.

2- Advanced use of the Apache configuration .htaccess file for routing control.

3- Totally Object-oriented design.

4- Custom Autoloading Class (No external Composer Autoloader).

5- Custom DotENV file reader Class (No external DotENV file reader package).

6- Middlewares implementation.

7- Protected Routes (using a custom Authentication Middleware Class).

8- Entry Point/Script [index.php](public/index.php) file for the whole application.

9- Login System utilizing a custom Session Class.

10- Session Flash Messages.

11- AJAX Live Search.

12- Multilingual Support.

13- Custom Pagination implementation.

14- Database CRUD Operations Classes.

15- File Upload.

16- Registration, Validation, Authentication and Authorization.

17- Responsive / Mobile first Design using Bootstrap.

## Application Routes:
All the application routes are defined in the [index.php](public/index.php) file inside the "public" folder.

## API Endpoints:
> ***\*\* Check the API Collection on my Postman Profile: https://www.postman.com/ahmed-yahya/workspace/my-public-portfolio-postman-workspace/collection/28181483-41805882-779b-42f7-a246-e96e32633ff5***

GET /api/produtcts &nbsp;&nbsp;&nbsp;&nbsp;&nbsp; : Get/Retrieve All produtcs

GET /api/products/{id} : Get/Retrieve a Single product

***\*\* Note: You can test the API Endpoints using Postman. Here is the Postman Collection .json file [Postman Collection](<Postman Collection of API Endpoints/Plain PHP REST API with Token-based Authentication and Image Uploading.postman_collection.json>) you can download and import in your Postman.***

## Installation & Configuration:
1- Clone the project or download it.

2- Create a MySQL database named **\`my_plain_php_mvc_oop_framework\`** and import the database SQL Dump file [Database SQL Dump file](<Database - my_plain_php_mvc_oop_framework/my_plain_php_mvc_oop_framework database - SQL Dump File - PhpMyAdmin Export.sql>).

3- Navigate to the ***.env*** file **[.env](.env)** and configure/edit/update it with your MySQL database credentials and other configuration settings.

4- Navigate to the project "public" folder/directory (where the Entry Point [index.php](public/index.php) file is placed) using the **`cd`** terminal command, and then start your PHP built-in Development Web Server by running the command: **`php -S localhost:8000`**.

***\*\*Note: Whatever your Web Server is, you must configure its Web Root Directory to be the application "public" folder which contains the [index.php](public/index.php) file (Entry Point) in order for the Routing System to function properly.***

5- In your browser, go to http://localhost:8000/.

6- Credentials of a ready-to-use registered user account are:

> **Email**: **ahmed.yahya@example.com**, **Password**: **12345678**

## Contribution:
Contributions to my personal backend Plain PHP MVC OOP Framework are most welcome! If you find any issues or have suggestions for improvements, want to add new features or want to contribute code or documentation, please open an issue or submit a pull request.
