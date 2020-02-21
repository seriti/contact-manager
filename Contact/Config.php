<?php 
namespace App\Contact;

use Psr\Container\ContainerInterface;
use Seriti\Tools\BASE_URL;
use Seriti\Tools\SITE_NAME;

class Config
{
    
    protected $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;

    }

    /**
     * Example middleware invokable class
     *
     * @param  \Psr\Http\Message\ServerRequestInterface $request  PSR7 request
     * @param  \Psr\Http\Message\ResponseInterface      $response PSR7 response
     * @param  callable                                 $next     Next middleware
     *
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke($request, $response, $next)
    {
        
        $module = $this->container->config->get('module','contact');
        $menu = $this->container->menu;
        
        define('TABLE_PREFIX',$module['table_prefix']);
        define('EMAIL_LIMIT_HOUR',1000);
        //can have semi-colon separated list of email addresses
        define('EMAIL_FROM_RESTRICT',MAIL_FROM);
        
        define('MODULE_ID','CONTACT');
        define('MODULE_LOGO','<span class="glyphicon glyphicon-send"></span> Contact manager');
        define('MODULE_PAGE',URL_CLEAN_LAST);      
        
        //define('MODULE_NAV',$menu->buildNav($module['route_list'],MODULE_PAGE));
        $submenu_html = $menu->buildNav($module['route_list'],MODULE_PAGE);
        $this->container->view->addAttribute('sub_menu',$submenu_html);

        $response = $next($request, $response);
        
        return $response;
    }
}
