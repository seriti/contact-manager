<?php
namespace App\Contact;

use Psr\Container\ContainerInterface;
use App\Contact\MessageFile;

class MessageFileController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table = TABLE_PREFIX.'file'; 
        $upload = new MessageFile($this->container->mysql,$this->container,$table);

        $upload->setup();
        $html = $upload->processUpload();
        
        $template['html'] = $html;
        //$template['title'] = MODULE_LOGO;
        
        return $this->container->view->render($response,'admin_popup.php',$template);
    }
}