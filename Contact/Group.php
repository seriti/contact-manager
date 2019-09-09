<?php 
namespace App\Contact;

use Seriti\Tools\Table;

class Group extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Group','col_label'=>'name'];
        parent::setup($param);

        $this->info['EDIT']='Enter any additional group data into notes text field. All fields are searchable.'.
                            'Finally you need to click [Submit] button at bottom of page to save group data to server.';                         


        $this->addTableCol(array('id'=>'group_id','type'=>'INTEGER','title'=>'Group ID','key'=>true,'key_auto'=>true,'list'=>true));
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Group name'));
        $this->addTableCol(array('id'=>'notes','type'=>'TEXT','title'=>'Additional notes','required'=>false));
        
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        //$this->addAction(array('type'=>'view','text'=>'view','icon_text'=>'view'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addAction(array('type'=>'popup','text'=>'Linked contacts','url'=>'group_link','mode'=>'view','width'=>600,'height'=>600)); 

        $this->addSearch(array('name','notes'),array('rows'=>1));

    }    
}
?>
