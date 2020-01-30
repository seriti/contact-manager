<?php
namespace App\Contact;

use Psr\Container\ContainerInterface;

use Seriti\Tools\TABLE_AUDIT;
use Seriti\Tools\SITE_NAME;
use Seriti\Tools\MAIL_FROM;
use Seriti\Tools\Secure;

use App\Contact\Helpers;

//call this class from cronjob for regular automated backups 
class ContactPublicController
{
    protected $container;
    protected $db;
    

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->db = $this->container->mysql;
    }


    public function __invoke($request, $response, $args)
    {
        $error = '';
        $message = '';
        $html = '<!DOCTYPE html><html><body><h1>'.SITE_NAME.'</h1>';

        $module = $this->container->config->get('module','contact');
        define('TABLE_PREFIX',$module['table_prefix']);

        $mode = 'none';
        if(isset($_GET['mode'])) $mode = Secure::clean('basic',$_GET['mode']); 

        if($mode === 'unsubscribe') {
            $guid = Secure::clean('basic',$_GET['guid']);
            $contact = Helpers::unsubscribeContact($this->db,$guid,$error); 
            if($error === '') {
                $message = 'You have been unsubscribed from all messages for email address: '.$contact['email'];
            }    
        }  

        if($mode === 'none') $error .= 'No contact mode specified.'; 

        if($error !== '') {
            $html .= '<h2>ERROR: '.$error.'</h2>'.
                     '<p>Please email your instruction to: <a href="mailto:'.MAIL_FROM.'">'.MAIL_FROM.'</a>';
        } else {
            $html .= '<h2>SUCCESS: '.$message.'</h2>';
        }   
        
        $html .= '</body></html>';

        return $html;
    }
}