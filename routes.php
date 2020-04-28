<?php  
/*
NB: This is not stand alone code and is intended to be used within "seriti/slim3-skeleton" framework
The code snippet below is for use within an existing src/routes.php file within this framework
copy the "/contact" group into the existing "/admin" group within existing "src/routes.php" file 
*/

//required if you use unsubscribe links in emails, must be outside /admin group
$app->get('/contact', \App\Contact\ContactPublicController::class);

$app->group('/admin', function () {

    $this->group('/contact', function () {
        $this->post('/ajax', \App\Contact\Ajax::class);
        $this->any('/dashboard', \App\Contact\DashboardController::class);
        $this->any('/contact', \App\Contact\ContactController::class);
        $this->any('/group', \App\Contact\GroupController::class);
        $this->any('/group_link', \App\Contact\GroupLinkController::class);
        $this->any('/message', \App\Contact\MessageController::class);
        $this->any('/message_file', \App\Contact\MessageFileController::class);
        $this->any('/template', \App\Contact\TemplateController::class);
        $this->any('/template_image', \App\Contact\TemplateImageController::class);
        $this->any('/import', \App\Contact\ImportWizardController::class);
        $this->any('/queue', \App\Contact\QueueController::class);
        $this->get('/setup_data', \App\Contact\SetupDataController::class);
        $this->any('/task', \App\Contact\TaskController::class);
    })->add(\App\Contact\Config::class);


})->add(\App\User\ConfigAdmin::class);



