 * @author		Ronaldo Barbachano http://www.redcapmedia.com
 * @copyright  (c) May 2011
 * @license		http://www.fsf.org/licensing/licenses/agpl-3.0.html
 * @link		http://www.myparse.org
 
 Here is a preview of my new in-the works framework myparse and its form handling/generation features.
 
 This class can display/edit/delete records in a mysql table using special coded syntax that can be generated with class 'sqwizard.'
 
 All of these classes and more exist and are implemented with web-based interfaces for configuration inside of myparse framework
 
 sqlee Supports
 Form Validations (urls, emails, unique, required, and much much more)
 File Uploads
 Dynamically cross linked field selections
 Rhobust field type support (enum's, textareas, varchars, etc.)
 Low queries
 
 Usage
 
 $form = new sqlee($mysqli_link);
 
 or
 
 $form = new sqlee($mysqli_link,'config_file_path');
 
 and then
 
 $form->record('config string 1', 'config string 2', mode,'sql record filter')
 
 
 The last two options are optional, mode switches between a record display, and record display. Better explained inside of myparse.