# Contact manager management module. 

## Designed for service based small enterprise.

Use this module to manage your contacts and assign them to groups. You can setup simple email templates and 
create messages with attachments for processing in the email queue. Import your contacts from Google or Outlook CSV dumps.  

## Requires Seriti Slim 3 MySQL Framework skeleton

This module integrates seamlessly into [Seriti skeleton framework](https://github.com/seriti/slim3-skeleton).  
You need to first install the skeleton framework and then download the source files for the module and follow these instructions.

It is possible to use this module independantly from the seriti skeleton but you will still need the [Seriti tools library](https://github.com/seriti/tools).  
It is strongly recommended that you first install the seriti skeleton to see a working example of code use before using it within another application framework.  
That said, if you are an experienced PHP programmer you will have no problem doing this and the required code footprint is very small.  

## Install the module

1.) Install Seriti Skeleton framework(see the framework readme for detailed instructions) :   
    **composer create-project seriti/slim3-skeleton [directory-for-app]**.  
    Make sure that you have thsi working before you proceed.

2.) Download a copy of Contact manager module source code directly from github and unzip,  
or by using **git clone https://github.com/seriti/contact-manager** from command line.  
Once you have a local copy of module code check that it has following structure:  

/Contact/(all module implementation classes are in this folder)  
/setup_add.php  
/routes.php  

3.) Copy the **Contact** folder and all its contents into **[directory-for-app]/app** folder.

4.) Open the routes.php file and insert the **$this->group('/contact', function (){}** route definition block
within the existing  **$app->group('/admin', function () {}** code block contained in existing skeleton **[directory-for-app]/src/routes.php** file.

5.) Open the setup_app.php file and  add the module config code snippet into bottom of skeleton **[directory-for-app]/src/setup_app.php** file.
Please check the **table_prefix** value to ensure that there will not be a clash with any existing tables in your database.

6.) Now in your browser goto URL:  

"http://localhost:8000/admin/contact/dashboard" if you are using php built in server  
OR   
"http://www.yourdomain.com/admin/contact/dashboard" if you have configured a domain on your server  

Now click link at bottom of page "Setup Database": This will create all necessary database tables with table_prefix as defined above.  
Thats it, you are good to go. Add some contacts or import from Google or Outlook CSV dumps.   
Then you can setup contact groups and email templates, and add groups to the email queue before processing.  
You can also use the seriti skeleton customise module to add Contact module routes to application menu  .
