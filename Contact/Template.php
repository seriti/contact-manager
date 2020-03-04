<?php 
namespace App\Contact;

use Seriti\Tools\Table;
use Seriti\Tools\Form;
use Seriti\Tools\Secure;
use Seriti\Tools\Validate;
use Seriti\Tools\Html;

class Template extends Table 
{
    
    //configure
    public function setup($param = []) 
    {
        $param = ['row_name'=>'Template','col_label'=>'name'];
        parent::setup($param);

        $this->info['EDIT'] = 'You can use markdown and or raw html in template text field. '.
                              'The <a href="https://www.markdownguide.org/basic-syntax" target="_blank">markdown</a> interpreter is '.
                              '<a href="http://parsedown.org" target="_blank">Parsedown</a> and this allows you to simply create many '.
                              'standard html elements like headings,lists,bold,italic,underline and also more complex layouts like tables.'.
                              'After any changes you need to click [submit] button at bottom of form to save changes. ';
        
        //widens value column
        $this->classes['col_value'] = 'col-sm-9 col-lg-10 edit_value';
        
        $this->addTableCol(array('id'=>'template_id','type'=>'INTEGER','title'=>'Template ID','key'=>true,'key_auto'=>true,'list'=>true));
        
        $this->addTableCol(array('id'=>'name','type'=>'STRING','title'=>'Template name'));
        $this->addTableCol(array('id'=>'template_markdown','type'=>'TEXT','secure'=>false,'title'=>'Template','rows'=>20,
                                 'hint'=>'Uses <a href="http://parsedown.org/tests/" target="_blank">parsedown</a> extended <a href="https://www.markdownguide.org/basic-syntax" target="_blank">markdown</a> format, or raw html','list'=>false));
        //shows markdown as converted to html
        $this->addTableCol(array('id'=>'template_html','type'=>'TEXT','html'=>true,'secure'=>false,'title'=>'Template','edit'=>false));
        
        $this->addAction(array('type'=>'check_box','text'=>''));
        $this->addAction(array('type'=>'edit','text'=>'edit','icon_text'=>'edit'));
        $this->addAction(array('type'=>'delete','text'=>'delete','icon_text'=>'delete','pos'=>'R'));

        $this->addSearch(array('name','template_markdown'),array('rows'=>1));

        $this->setupImages(array('table'=>TABLE_PREFIX.'file','location'=>'TMP','max_no'=>10,
                                  'icon'=>'<span class="glyphicon glyphicon-picture" aria-hidden="true"></span>&nbsp;manage',
                                  'list'=>true,'list_no'=>3,'storage'=>STORAGE,
                                  'link_page'=>'template_image','link_data'=>'SIMPLE','width'=>'700','height'=>'600'));
    }

    protected function afterUpdate($id,$edit_type,$form) 
    {
        //converts page markdown into html and save 
        $text = $form['template_markdown'];
        if($text !== '') {
            $html = Html::markdownToHtml($text);      
            $sql='UPDATE '.TABLE_PREFIX.'template SET template_html = "'.$this->db->escapeSql($html).'" '.
                 'WHERE template_id = "'.$this->db->escapeSql($id).'"';
            $this->db->executeSql($sql,$error_tmp);
        }  
    }  
    
}
?>
