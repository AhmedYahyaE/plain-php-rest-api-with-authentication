# Plain PHP REST/RESTful API with Token-based Authentication and Image Uploading
A comprehensive Plain PHP & MySQL REST/RESTful API with Token-based Authentication and Image Uploading feature. The API is built following the MVC (Model-View-Controller) Design Pattern, and is totally Object-oriented (OOP). The idea is an API for Tasks or To-Do Lists and their associated Images. This project script is written entirely in plain PHP (OOP) and aims to demonstrate the implementation of an API with a Token-based Authentication without relying on any external libraries or frameworks.

## Screenshots:
***REST/REST API Constraints:***

![REST-API-Constraints](https://github.com/AhmedYahyaE/plain-php-rest-api-with-authentication/assets/118033266/36e5c1ff-10f3-49d9-a6d6-638227d6ab78)

## Features:
1- MVC Design Pattern.

2- Advanced use of the Apache configuration .htaccess file for routing control.

3- Token-based Authentication using a short-lived "Access Token" (20 minutes) and a longer-term "Refresh Token" (2 weeks).

3- Totally Object-oriented design.

4- Advanced SQL INNER JOIN clauses.

5- HTTP Responses with Pagination.

6- File Upload.

7- Registration, Validation, Authentication and Authorization.

## Application Routes:
All the application routes are defined in the [index.php](public/index.php) file inside the "public" folder.

## API Endpoints:
> ***\*\* Check the API Collection on my Postman Profile: https://www.postman.com/ahmed-yahya/workspace/my-public-portfolio-postman-workspace/collection/28181483-41805882-779b-42f7-a246-e96e32633ff5***

1- Register/Sign up/Create a new user (POST):

**POST /v1/users**

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** Mandatory fields in the JSON HTTP Requet Body: fullname, username and password.

2- Log in and Create a new session with a new Access Token and a new Refresh Token (POST):

**POST /v1/sessions**

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** Mandatory fields in the JSON HTTP Request Body: username and password.

3- Log out and delete a session (DELETE):

**DELETE /v1/sessions/{sessionid}**

** {sessionid} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

4- Refresh a session (update a session to get a new access token and a new refresh token instead of the expired access token) (PATCH):

**PATCH /v1/sessions/{sessionid}**

** {sessionid} Query String Parameter in the URL must be provided.

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** "Refresh Token" must be provided as JSON in the HTTP Request Body (Not as an 'Authorization' HTTP Request Header).

** "Access Token" must be provided as an "Authorization" HTTP Request Header.

5- Create a new task (POST):

**POST /v1/tasks**

** "Authorization" HTTP Request Header (Access Token) must be provided.

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** Mandatory fields in the JSON HTTP Request Body: `title` and `completed`.

6- Get ALL tasks that belong to the authenticated/logged-in user (GET):

**GET /v1/tasks**

** "Authorization" HTTP Request Header (Access Token) must be provided.

7- Get a Single task (GET):

**GET /v1/tasks/{taskid}**

** {taskid} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

8- Delete a single task (DELETE) (that belongs to the authenticated/logged-in user): (this also deletes all of the associated images and as well deletes the task images folder inside the 'taskimages' folder)

**DELETE /v1/tasks/{taskid}**

** {taskid} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

9- Update a single task (PATCH):

**PATCH /v1/tasks/{taskid}**

** {taskid} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** Mandatory fields in the JSON HTTP Request Body: At least one of the fields: `title`, `description`, `deadline` and `completed`.

10- Get all 'Complete' tasks (GET):

**GET /v1/tasks/complete**

** {complete} or {incomplete} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

11 - Get all 'Incomplete' tasks (GET):

**GET /v1/tasks/incomplete**

** {complete} or {incomplete} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

12- Get All tasks with Pagination (tasks that belong to the authenticated/logged-in user) (GET):

**GET /v1/tasks/page/{pagenumber}**

** {page} Query String Parameter and its value {pagenumber} in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

13- Create (Upload) an image for a certain task (of the authenticated/logged-in user):

**POST /tasks/{taskid}/images**

** {taskid} Query String Parameter in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

** "multipart/form-data; boundary=" HTTP Request Header must be provided.

** In Postman, click on "Body", then "form-data", then enter two fields: "attributes" and "imagefile" fields. For the "attributes" field, set it to "Text" and enter the Value as JSON (Example: {"title": "Image Title 1", "filename": "carimage"}) and don't mention the file extension in the file name. For the"imagefile" field, set it to "File", and upload an image file in the Value. Only .jgp, .gif or .png images are allowed.

14- Get (Download) an actual physical image of a certain task (of the authenticated/logged-in user):

**GET /tasks/{taskid}/images/{imageid}**

** {taskid} and {imageid} Query String Parameters in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

15- Delete an actual physical image of a certain task (of the authenticated/logged-in user):

**DELETE /tasks/{taskid}/images/{imageid}**

** {taskid} and {imageid} Query String Parameters in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

16- Get a certain image Attributes (of a certain task that belongs to the authenticated/logged-in user):

**GET /tasks/{taskid}/images/{imageid}/attributes**

** The three of {taskid}, {imageid} and {attributes} Query String Parameters in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

17- Update a certain image Attributes (of a certain task that belongs to the authenticated/logged-in user):

**PATCH /tasks/{taskid}/images/{imageid}/attributes**

** The three of {taskid}, {imageid} and {attributes} Query String Parameters in the URL must be provided.

** "Authorization" HTTP Request Header (Access Token) must be provided.

** "Contetn-Type" HTTP Request Header must be set to "application/json".

** Mandatory fields in the JSON HTTP Request Body: At least one of the two fields: `title` and `filename`. N.B. File Name must be provided WITHOUT the file extension.

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
