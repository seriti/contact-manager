<?php
namespace App\Contact;

use Seriti\Tools\Dashboard AS DashboardTool;

class Dashboard extends DashboardTool
{
     

    //configure
    public function setup($param = []) 
    {
        $this->col_count = 2;  

        $login_user = $this->getContainer('user'); 

        //(block_id,col,row,title)
        $this->addBlock('ADD',1,1,'Capture new data');
        $this->addItem('ADD','Add a new Contact',['link'=>"contact?mode=add"]);
        $this->addItem('ADD','Add a new Message',['link'=>"message?mode=add"]);
                
        if($login_user->getAccessLevel() === 'GOD') {
            $this->addBlock('IMPORT',1,2,'Import data');
            $this->addItem('IMPORT','Import contact data',['link'=>'import']);

            $this->addBlock('CONFIG',1,3,'Module Configuration');
            $this->addItem('CONFIG','Setup Database',['link'=>'setup_data','icon'=>'setup']);
        }    
        
    }

}

?>