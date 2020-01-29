<?php
namespace App\Contact;

use Seriti\Tools\Table;

class GroupLink extends Table 
{
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'contact','col_label'=>'name','pop_up'=>true];
        parent::setup($param);       

        $access['add'] = false;
        $this->modifyAccess($access);

        //NB: master_col_idX shoudl be replaced by suitable master table cols you wish to use
        $this->setupMaster(array('table'=>TABLE_PREFIX.'group','key'=>'group_id','child_col'=>'group_id','label'=>'name', 
                                'show_sql'=>'SELECT CONCAT("Group:",name) FROM '.TABLE_PREFIX.'group WHERE group_id = "{KEY_VAL}" '));  

        $this->addTableCol(array('id'=>'link_id','type'=>'INTEGER','title'=>'Link ID','key'=>true,'key_auto'=>true,'list'=>false));
        $this->addTableCol(array('id'=>'contact_id','type'=>'INTEGER','title'=>'Linked Contact','join'=>'CONCAT(surname,", ",name,"(",email,")") FROM '.TABLE_PREFIX.'contact WHERE contact_id'));
               
        $this->addSql('JOIN','LEFT JOIN '.TABLE_PREFIX.'contact AS C ON(T.contact_id = C.contact_id)');

        $this->addAction(array('type'=>'check_box','text'=>''));
        //$this->addAction(array('type'=>'edit','text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','pos'=>'R'));

        $this->addSearch(array('link_id'),array('rows'=>2));
        $this->addSearchXtra('C.name','Contact name');
        $this->addSearchXtra('C.email','Contact email');
        //$this->addSearchXtra('C.surname','Contact surname');

        //$this->addSelect('contact_id','SELECT contact_id, CONCAT(surname,", ",name,"(",email,")") FROM '.TABLE_PREFIX.'contact ORDER BY surname,name');

    }  
}
?>
