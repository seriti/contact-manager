<?php
namespace App\Contact;

use Psr\Container\ContainerInterface;
use App\Contact\Contact;

class ContactController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $table_name = TABLE_PREFIX.'contact'; 
        $table = new Contact($this->container->mysql,$this->container,$table_name);

        $table->setup();
        $html = $table->processTable();
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO;
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}