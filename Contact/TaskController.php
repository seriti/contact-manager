<?php
namespace App\Contact;

use Psr\Container\ContainerInterface;
use App\Contact\Task;

class TaskController
{
    protected $container;
    
    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function __invoke($request, $response, $args)
    {
        $param = [];
        $task = new Task($this->container->mysql,$this->container,$param);

        $task->setup();
        $html = $task->processTasks();
        //need for ajax task processes
        $html .= '<div id="div_ajax"></div>';
        
        $template['html'] = $html;
        $template['title'] = MODULE_LOGO;
        
        return $this->container->view->render($response,'admin.php',$template);
    }
}