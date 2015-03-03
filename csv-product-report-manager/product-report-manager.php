<?php
/*
Plugin Name: CSV Product Report Manager
Plugin URI: http://webdogs.com/
Description: Use this Dashboard Widget to output and manage CSV reports. (Output customized for ShipStation.)
Version: 1.0
Author: WEBDOGS JVC
Author URI: http://webdogs.com/
License: WDLv1
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


// If class WEBDOGS already exists, return and let's not procede. That would cause errors.
if (!class_exists('ProductReportManagerCSV')) return;

// The class is useless if we do not start it, when plugins are loaded let's start the class.
add_action ('plugins_loaded', 'ProductReportManagerCSV');
function ProductReportManagerCSV() { $ProductReportManagerCSV = new ProductReportManagerCSV; }

add_action( 'prm_product_report', 'prm_run_product_report');
function prm_run_product_report($new=0)  { $ProductReportCSV = new ProductReportCSV; }

class ProductReportManagerCSV
{

    function __construct() 
    {
        ///////////////////////////////
        /////////////      ////////////
        ////////                ///////
        ////////   constants    ///////
        ////////                ///////
        //////////////   //////////////
        ///////////////////////////////
        define( 'PRM_TITLE',            "Product Reports" );

        define( 'PRM_TODAY',            date("Ymd") );

        define( 'PRM_DIR_PATH',         WP_CONTENT_DIR . "/inventory-csv/" );
        define( 'PRM_DIR_URL',          WP_CONTENT_URL . "/inventory-csv/" );

        define( 'PRM_WORK_NAME',        "pacwave_inventory_".PRM_TODAY );
        define( 'PRM_WORK_DIR',         PRM_DIR_PATH . PRM_WORK_NAME . "/" );
        define( 'PRM_WORK_URL',         PRM_DIR_URL . PRM_WORK_NAME . "/" );

        define( 'PRM_JOB_PATH',         PRM_DIR_PATH . "job.json" );

                                        $total_items = absint( $this->prm_get_woocommerce_total_products() );
        define( 'PRM_TOTAL',            $total_items);

        define( 'PRM_PER_FILE',         5000 );
        define( 'PRM_COL_KEYS',         "SKU,Name,WarehouseLocation,WeightOz,Category,Tag1,Tag2,Tag3,Tag4,Tag5,CustomsDescription,CustomsValue,CustomsTariffNo,CustomsCountry,ThumbnailUrl,UPC,FillSKU,Length,Width,Height,UseProductName,Active" );


        if( ! function_exists('is_plugin_active') ) 
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php');
        }

        
        add_action( 'init',                             array(&$this, 'register_taxonomies'             ), 10);

        add_action( 'wp_dashboard_setup',               array(&$this,'prm_get_woocommerce_total_products'   ));

        add_action( 'wp_dashboard_setup',               array(&$this,'prm_add_dashboard_widget'         ));

        add_action( 'admin_enqueue_scripts',            array(&$this,'prm_enqueue_scripts'              ));
    
        add_action( 'wp_ajax_prm_result_dashboard',     array(&$this,'prm_result_dashboard_callback'    )); 

        add_action( 'wp_ajax_prm_run_report',           array(&$this,'prm_run_report_callback'          )); 


        load_plugin_textdomain('prm', false, basename( dirname( __FILE__ ) ) . '/languages' );
    }

    /**
     * Register WooCommerce taxonomies.
     */
    public static function register_taxonomies() {
        // if ( taxonomy_exists( 'product_type' ) ) {
        //     return;
        // }

        register_taxonomy( 'product_shelving_location',
            array( 'product' ),
            array(
                'hierarchical'          => false,
                'update_count_callback' => '_wc_term_recount',
                'label'                 => __( 'Shelving Location', 'prm' ),
                'labels'                => array(
                        'name'                       => __( 'Shelving Location', 'prm' ),
                        'singular_name'              => __( 'Shelving Locations', 'prm' ),
                        'menu_name'                  => _x( 'Shelving Locations', 'Admin menu name', 'prm' ),
                        'search_items'               => __( 'Search Shelving Location', 'prm' ),
                        'all_items'                  => __( 'All Shelving Location', 'prm' ),
                        'edit_item'                  => __( 'Edit Shelving Locations', 'prm' ),
                        'update_item'                => __( 'Update Shelving Location', 'prm' ),
                        'add_new_item'               => __( 'Add New Shelving Location', 'prm' ),
                        'new_item_name'              => __( 'New Product Shelving Location', 'prm' ),
                        'popular_items'              => __( 'Popular Shelving Locations', 'prm' ),
                        'separate_items_with_commas' => __( 'Separate Shelving Locations with commas', 'prm'  ),
                        'add_or_remove_items'        => __( 'Add or remove Shelving Location', 'prm' ),
                        'choose_from_most_used'      => __( 'Choose from the most used Shelving Locations', 'prm' ),
                        'not_found'                  => __( 'No Shelving Location found', 'prm' ),
                    ),
                'show_ui'               => true,
                'show_in_nav_menus'     => true,
                'query_var'             => is_admin(),
                'capabilities'          => array(
                    'manage_terms' => 'manage_product_terms',
                    'edit_terms'   => 'edit_product_terms',
                    'delete_terms' => 'delete_product_terms',
                    'assign_terms' => 'assign_product_terms',
                ),
                'rewrite'               => false,
            )
        );

    }

    /**
     * Get the total of published products.
     *
     * @since 1.0
     */
    function prm_get_woocommerce_total_products() {
        $args = array( 'post_type' => 'product', 'post_status' => array( 'publish' ), 'numberposts' => -1, 'orderby'  => 'title', 'order' => 'asc', 'fields' => 'ids');
    
        $post_products = get_posts( $args ); 

        return sizeof($post_products);
    }
	/**
     * Run report
     * @return void
     */
	function ProductReportCSV() { $ProductReportCSV = new ProductReportCSV; }

    function write_job_file($the_job) {
        $job_file  = fopen( PRM_JOB_PATH, "w");
        fwrite($job_file, json_encode($the_job, true));
        fclose($job_file);
        return $the_job;
    }
    /**
     * File extendion
     * @return string
     */
    function file_extension($file_name) {
        return substr(strrchr($file_name,'.'),1);
    }

    /**
     * File lists
     * @return array
     */
    function file_list($directory = PRM_DIR_PATH, $file_name = PRM_WORK_NAME, $file_extension = 'zip') {
        $file_list = array();

        $files     = array_diff(scandir($directory), array('..', '.'));

        foreach ($files as $file) {
            if( $this->file_extension( $file ) == $file_extension )
                $file_list[] = $file;
        }
        return $file_list;
    }
    function get_file_list() 
    {

        // ob_start();
        $file_data = array();
        $file_list_zip = $this->file_list();
        $file_list_zip = (sizeof($file_list_zip) > 1) ? array_reverse( $file_list_zip ) : $file_list_zip;
        $z = 0;
        foreach ($file_list_zip as $archive_zip) {

            $archive_name = str_replace(".zip", "", $archive_zip);

            $file_list_csv = $this->file_list( PRM_DIR_PATH.$archive_name."/", $archive_name, "csv");

            chdir(PRM_DIR_PATH.$archive_name."/");

            $i = 0;
            $n = 0;
            $x = (int) (sizeof($file_list_csv) - 1);
            $linecount = 0;

            // echo '<a class="files" href="'.PRM_DIR_URL.$archive_zip.'" ><span class="files">'.$archive_zip.'</span><br />';
            $link = '<a class="download" style="white-space:nowrap;" href="'.PRM_DIR_URL.$archive_zip.'" >'.$archive_zip.'</a>';
            
            foreach ($file_list_csv as $report_csv) {

                $n++; 
                $this_linecount = ($i === $x ) ? count( file( $report_csv ) ) : PRM_PER_FILE;
                $linecount = $linecount + ( $this_linecount - 1 );
                $i++;
            }

            // echo '<span class="file">'.$n.' Files : <small>'.$linecount.' Records</small></span> ';
            
            //echo "</a>";
            $file_data[]    = array(
                'ID'        =>  (int) $z,
                'title'     =>  "<strong style='white-space:nowrap'>". strftime("%b %e, %Y", strtotime( str_replace("pacwave_inventory_", '', $archive_name) ) )."</strong>",
                'files'     =>  (int) $n,
                'entries'   =>  (int) $linecount,
                'link'      =>  PRM_DIR_URL.$archive_zip
            );
            $z++;
        }
        return $file_data;
        // return ob_get_clean();
    }

    /**
     * Register dashboard widget
     * @return void
     */
    function prm_add_dashboard_widget() {
        wp_add_dashboard_widget( 'prm_widget', PRM_TITLE, array(&$this,'prm_dashboard_widget_function' ));
    }

    /**
     * Display widget
     * @return void
     */
    function prm_dashboard_widget_function() {
        
        $current_files = $this->get_file_list();

        //Create an instance of our package class...
        $fileListTable = new PRM_File_List_Table( );
        //Fetch, prepare, sort, and filter our data...
        $fileListTable->prepare_items( $current_files ); ?>

        <style type="text/css">
        #prm_widget .widefat td, #prm_widget .widefat th {text-align: center; vertical-align: middle; }
        #prm_widget .widefat .column-title {width: 55%; text-align: left; }
        #prm_widget .widefat .row-actions {visibility: visible; } 
        #prm_widget .widefat .row-actions a {font-size: smaller; } 
        #prm_progress {padding: 0; height: 3px; }
        #prm_form_wrapper {margin-bottom: 12px; }
        #prm_form_wrapper .tablenav {display: none; }
        </style>
        <div id="prm_form_wrapper">
                <?php $fileListTable->display(); ?>
        </div>
        <input id='prm_form_reset' type='button' class='button' value='Run Report' />
        <img src="<?php echo WP_PLUGIN_URL ."/". basename( dirname( __FILE__ ) ); ?>/img/shipstation.png" height="30" style="position:absolute;right:14px;bottom:8px;" />

        <?php
        // $current_files = $this->get_file_list(); 
        // include_once('prm-support-form.php');
    }

    /**
     * load JS and the data (admin dashboard only)
     * @param  string $hook current page
     * @return void
     */
    function prm_enqueue_scripts( $hook ) {

        if( 'index.php' != $hook ) {
            add_action( 'admin_head', array(&$this,'prm_dashboard_notification'));
        } else {
            add_action( 'admin_footer', array(&$this,'prm_dashboard_javascript'));
        }
    }

    //* Add alert to Dashboard home 
    function prm_dashboard_notification() { 
        if (! is_admin()) : ?>
            <script type="text/javascript">
            $j = jQuery; $j().ready(function(){$j('.wrap > h2').parent().prev().after('<div class="update-nag">Please contact <a href="mailto:support@webdogs.com?subject=PACWAVE.com" target="_blank">support@webdogs.com</a> for assistance with updating WordPress or making changes to your website.</div>'); });
            </script><?php
        endif;
    } 

    function prm_dashboard_javascript() { ?>

        <script type="text/javascript">

            var prm_init = 1;

            jQuery(function($) {


                function startPRMInterval(new_report) {

                    var report = new_report;
                    var init = prm_init;

                    if(!report) 
                    {

                        if(init) {                                                   //style="background:#D0F2FC"
                            $('<thead id="prm_progress_head" style="display:none;"><tr><th colspan="3" id="prm_progress" class="progress active"><span id="prm_progress_bar" class="bar"></span></th></tr></thead>').insertBefore('#prm_widget #the-list');
                            prm_init = 0;
                        }


                        var data = 
                        {
                            'action':   'prm_run_report',
                            'new'   :   0,
                            'json'  :   1
                        };

                        $.post(ajaxurl, data, function(processing) {

                            if(processing=="finished")
                            {
                                data = 
                                {
                                    'action':   'prm_run_report',
                                    'new'   :   0
                                };
                                $.post(ajaxurl, data, function(response) 
                                {
                                    $('#prm_widget #prm_form_wrapper').html( response );
                                });
                                stopPRMInterval();
                            } 
                            else 
                            if(processing=="error") {

                                $('#prm_widget #the-list tr:first-child').addClass('alert').find('.row-actions').html('<span class="processing" style="color:crimson;">Output Error</span>');
                                stopPRMInterval();

                            } else {
                                $('#prm_progress_head:hidden').show( 'fast' );
                                $('#prm_widget #the-list tr:first-child').replaceWith(processing);

                                var progress_data = $('#the-list tr:first-child').attr('data-progress');
                                var progress_int  = ( progress_data / <?php echo PRM_TOTAL; ?> )*100;
                                var progress      = progress_int+"\%"; 

                                $('#prm_progress_bar').width(progress);
                            }
                        });

                    } else {

                        var data = 
                        {
                            'action':    'prm_run_report',
                            'new'   :    1,
                            'count' :    0,
                            'refresh':   true,
                            'processing': true
                        };
                        $.post(ajaxurl, data, function(response) 
                        {   
                            $('#prm_progress_head:hidden').show( 'fast' );                                                //style="background:#D0F2FC"
                            $('#prm_widget #prm_form_wrapper').html( response );
                            $('#prm_widget #the-list tr:first-child').replaceWith('<tr class="processing_row highlight" data-progress="0"><td class="title column-title"><strong style="white-space:nowrap"><?php echo strftime("%b %e, %Y", time() ); ?></strong> <div class="row-actions" style="visibility: visible;"><span class="processing" style="color:#377A9F">Processing</span></div></td><td class="files column-files">0</td><td class="entries column-entries">0</td></tr>'); 
                            $('<thead id="prm_progress_head"><tr><th colspan="3" id="prm_progress" class="progress active"><span id="prm_progress_bar" class="bar"></span></th></tr></thead>').insertBefore('#prm_widget #the-list');
                        });
                            
                        PRMInterval = setInterval( function(){ startPRMInterval( false ) }, 4200);
                    }
                    prm_init = 0;
                    init = 0;
                }

                function stopPRMInterval() {
                    clearInterval(PRMInterval);
                }

                $('#prm_widget').find('.hndle').css({"background-color":"#666", "font-family":"'Helvetica Neue',Helvetica,Arial,sans-serif"}).html('<span style="color:#FFFFFF;text-decoration:none;font-weight:bold;text-transform:uppercase;padding-top: 0;padding-bottom: 0;display: inline-block;position: relative; vertical-align: middle;margin-top: -3px;line-height: 13px;" title="WEBDOGS" href="http://webdogs.com/" target="_blank"><b><img style="border-width:0px;margin-right:5px;margin-left:0px;margin-top: -13px;margin-bottom: 0;display: inline;vertical-align: middle;line-height: 26px;top: 5px;position: relative;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACMAAAAjCAQAAAC00HvSAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAQjJfA44AAAAHdElNRQfeDBYLLDqnCm9AAAADv0lEQVRIx5VWCU8TQRTe31aQQ9BqlcuYCBRRbpRGDIcWjJVoUJFLBFEUFSUqigoFBQkYwUA0GAyJByB4EVAwSjAIn99Op8tuu21wJu3uvvfm25l3fG8VixJ8RsKKLQhDcKuAilRUox1dcKOTv27+bqEUNmwYJoTmbjxFPXIQLZeFIoHS2xhAC5KxAZh0POf77cI0gkscKEYBMrEDHjgXRnBNgw8A04hhZNAkDOUYwjzWxxLe4yYSxfJavEIaAsCE0BcPEUp1Babl4kXMaUCr/C3jEeJpkYXXOAxTmHY60aLE4IVuD/08kncU0y/q+A4n7RIwhoPwg6nHXQqT8Q368ZKySXG3wLtTmrxBAL2Rh9RgsnnaTdiNWRjHAsJxTNy95YLjOk01n/PxTA8TxuikMsXG4D9UD0zxOij2uqrzVC4lrTgNDaYMD/hwBWajiRoXr9eF+X2dZgZRiMEo/yVMH3YxP5dMYXLgcX+SuFp1kQNqKGtmJgmYdKa8RblkAjGBo9gmUmB91ur0XxGJPejzwDThLG8++0C8wwnGoYQ+syMfF5EnwQ4YrAopHWShUNFDw0SsGdQddHgCK8vO9/0QkklZ5YUGuzuUtaEIjFI/DVwG5UeEkB7GsY9G4fDkzRr3pcIYLUcpO0kfKTYTzwyKUBbJY+wX+/klSSLbYDlDWR6uQonjAdRF3rHMzD3H/WXqHFtA+R9GU70PZyKujzkmbSZuQIk1wIyI9HaSHoyVX4EVOtrr5FUdTCiyVBgrHlPdIMVlwvQCqgwwDpLHXx10h86Lakk0gfQwQBIqleICUYBfEKctiUYvpsguDsbMK4vBT13p1qACAjuL51YDvsIgJ8h3eGcyflNyxOeQzRJGpZZOEgZFlWiU5TfPN0ayenqYu+tLXJIY9DOaMVKHg6kxzORQVN5Q07mKwllEmNB1HDUVfvIsSqcZp1xypixNdVvRJMwVVog5TKuJvI2JYVHcggNlCFX6qaZ5t4m5nflcaSIP417SSLkh0NivHcf5MESgAaLH87RRWmWHB+mYfZJItBCOM8hWfJCZvEg/TdBn+UGbbiMboA+lO5jBm7GTcMbxBNsDQJWwk0XBr8GcISNvZay6fIAmJfMZp5NNIA6mXbOMTSwFaika9/SJnGsEOc/8tSFg880gUIMkhHam2LIEcuuW7OWu23wyzG+zUbjMaNXJ99vIfxmkr3h4QuwgeJ+ovA1838SSewfYz+vYcNPpmRQmQTnpoJd+c+K/PpMsShKptYVgbs57BD4U8CPJovwDRRo5ALFcUX0AAAAldEVYdGRhdGU6Y3JlYXRlADIwMTQtMTItMjJUMTE6NDQ6NTgrMDE6MDBej4ixAAAAJXRFWHRkYXRlOm1vZGlmeQAyMDE0LTEyLTIyVDExOjQ0OjU4KzAxOjAwL9IwDQAAAABJRU5ErkJggg==" alt="" width="26" height="26"><span style="text-decoration:none;color:#FFFFFF;vertical-align:middle;display: inline-block;font-size: 13px;margin-right: 4px;">WEBDOGS </span></b></span><span style="color: #D0F2FC; text-decoration: none;display: inline-block; vertical-align: middle;margin: -1px 0 0 0;padding: 0;font-size: 13px;line-height: 13px;"><?php echo PRM_TITLE; ?></span></h3>');

                $('#prm_form_reset').live('click', function(e) {
                    startPRMInterval( true );
                    e.preventDefault();
                });
                var PRMInterval = setInterval( function(){ startPRMInterval( false ) }, 4200);

                startPRMInterval( false );

            });
        </script><?php
    }
    function prm_result_dashboard_callback() {
        die();
    }

    function prm_run_report_callback() {
        
        $processing = json_decode( file_get_contents(PRM_JOB_PATH), true );

    
        if($_REQUEST['new']==1){

            $job = $this->write_job_file( array( 'work' => PRM_WORK_NAME, 'start' => 1, 'end' => 2500, 'count' => 0, 'refresh' => true, 'processing' => true ) );

            $current_files = $this->get_file_list();

            if( $current_files[0]['link'] != PRM_DIR_URL.PRM_WORK_NAME.".zip" ) {
                $current_files[0]     = array(
                    'ID'        =>  null,
                    'title'     =>  null,
                    'files'     =>  null,
                    'entries'   =>  null,
                    'link'      =>  null
                );
            }

            //Create an instance of our package class...
            $fileListTable = new PRM_File_List_Table( );
            //Fetch, prepare, sort, and filter our data...
            $fileListTable->prepare_items( $current_files ); ?>

                    <?php $fileListTable->display(); ?>
            <?php
            wp_clear_scheduled_hook( 'prm_product_report', array( 0 ) );
            wp_clear_scheduled_hook( 'prm_product_report', array( 1 ) );
            wp_schedule_single_event( time(), 'prm_product_report', array( 1 ) );

        }

        elseif(isset($_REQUEST['json']) && $_REQUEST['json']==1 && $_REQUEST['new']==0) 
        {

            $processing = json_decode( file_get_contents(PRM_JOB_PATH), true );

            if($processing['refresh']==0) {

                wp_clear_scheduled_hook( 'prm_product_report', array( 0 ) );
                wp_clear_scheduled_hook( 'prm_product_report', array( 1 ) );
                echo "finished";
                die();
            } 
            elseif( isset($processing['error']) && $processing['error'] == "Invalid Workload" ) {

                wp_clear_scheduled_hook( 'prm_product_report', array( 0 ) );
                wp_clear_scheduled_hook( 'prm_product_report', array( 1 ) );
                echo "error";
                die();

            } else {
                $processing_date = strftime("%b %e, %Y", strtotime( str_replace("pacwave_inventory_", '', $processing['work']) ) );
                
                $per = floor($processing['count']/PRM_PER_FILE)+1; //style="background:#D0F2FC"  
                $progress =  $processing['start']; ?>

                    <tr class="processing_row highlight" data-progress="<?php echo $progress; ?>"><td class="title column-title"><strong style="white-space:nowrap"><?php echo $processing_date; ?></strong> <div class="row-actions" style="visibility: visible;"><span class="processing" style="color:#377A9F">Processing</span></div></td><td class="files column-files"><?php echo $per; ?></td><td class="entries column-entries"><?php echo $processing['count']; ?></td></tr>
                
                <?php
            }
        } else {

            $current_files = $this->get_file_list();
            //Create an instance of our package class...
            $fileListTable = new PRM_File_List_Table( );
            //Fetch, prepare, sort, and filter our data...
            $fileListTable->prepare_items( $current_files ); ?>

            <?php $fileListTable->display(); ?>
            
            <?php
            wp_clear_scheduled_hook( 'prm_product_report', array( 0 ) );
            wp_clear_scheduled_hook( 'prm_product_report', array( 1 ) );
            wp_schedule_single_event( time(), 'prm_product_report', array( 1 ) );

        }

        die();
    }

}


/**
* 
*/
class ProductReportRowCSV
{
    public $SKU 					="";
    public $Name 					="";
    public $WarehouseLocation 		="";
    public $WeightOz 				="";
    public $Category 				="";
    public $Tag1 					="";
    public $Tag2 					="";
    public $Tag3 					="";
    public $Tag4 					="";
    public $Tag5 					="";
    public $CustomsDescription 		="";
    public $CustomsValue 			="";
    public $CustomsTariffNo 		="";
    public $CustomsCountry 			="US";
    public $ThumbnailUrl 			="";
    public $UPC 					="";
    public $FillSKU 				="";
    public $Length 					="";
    public $Width 					="";
    public $Height 					="";
    public $UseProductName 			="";
    public $Active 					="TRUE";
}

// If class WEBDOGS already exists, return and let's not procede. That would cause errors.
// if (!class_exists('ProductReportCSV')) return;

// The class is useless if we do not start it, when plugins are loaded let's start the class.
// da_action('plugins_loaded', 'InventoryCSV');
// if (! isset($InventoryCSV)) {  InventoryCSV(); }

/**
* 
*/
class ProductReportCSV
{
	// public 	$the_query = global $the_query;
    public  $job;   
	public 	$count;
	private $the_file;
	///////////////////////////////
	///////////////////////////////
	////////                ///////
	////////  what's this?  ///////
	////////                ///////
	///////////////////////////////
	////////                ///////
	//  who what when where why  //
	////////                ///////
	///////////////////////////////
	///////////////////////////////
	
	function __construct($new=0)
	{
		
		$new = (isset($_REQUEST['new']))?$_REQUEST['new'] :null;

		if( $new ) {

			$work 			= PRM_WORK_NAME;
			$start 			= 1;
			$end 			= 2500;
			$count  		= 0;
			$refresh 		= true;
			$processing 	= true;

		} else {

			$work 			= PRM_WORK_NAME;
			$start 			= (isset($_REQUEST['start']))		?$_REQUEST['start']		 :1;
			$end 			= (isset($_REQUEST['end']))			?$_REQUEST['end']		 :2500;
			$count  		= (isset($_REQUEST['count'])) 		?$_REQUEST['count'] 	 :0;
			$refresh 		= (isset($_REQUEST['refresh'])) 	?$_REQUEST['refresh']	 :true;
			$processing 	= (isset($_REQUEST['processing']))	?$_REQUEST['processing'] :true;

			if( is_file(PRM_JOB_PATH) ) {

				$this->job = json_decode( file_get_contents(PRM_JOB_PATH), true );

				$work 		= $this->job['work'];
				$start 		= (isset($_REQUEST['start']))		?$_REQUEST['start']		 :$this->job['start'];
				$end 		= (isset($_REQUEST['end']))			?$_REQUEST['end']		 :$this->job['end'];
				$count  	= (isset($_REQUEST['count'])) 		?$_REQUEST['count'] 	 :$this->job['count'];
				$refresh 	= (isset($_REQUEST['refresh'])) 	?$_REQUEST['refresh']	 :$this->job['refresh'];
				$processing = (isset($_REQUEST['processing']))	?$_REQUEST['processing'] :$this->job['processing'];
			}
		}

		settype($work, 			"string" );
		settype($start, 		"integer");
		settype($end, 			"integer");
		settype($count, 		"integer");
		settype($refresh, 		"boolean");
		settype($processing,	"boolean");

		$this->count = $count;
		$this->job 	 = $this->write_job_file( array( 'work' => $work, 'start' => $start, 'end' => $end, 'count' => $count, 'refresh' => $refresh, 'processing' => $processing, 'error' => ( $work !== PRM_WORK_NAME ) ? 'Invalid Workload' : null ) );

		wp_reset_postdata();

        //Schedule the reminder
        wp_clear_scheduled_hook( 'prm_product_report', array( 1 ) );
        wp_clear_scheduled_hook( 'prm_product_report', array( 0 ) );


		$do_job = ( $work == PRM_WORK_NAME && $end !== 0 && $refresh && $processing ) ? $this->do_job() : json_encode($this->job, true ) ;
		
        if( $this->job['refresh'] ) {
            wp_schedule_single_event( time(), 'prm_product_report', array( 0 ) );
        }

        // echo $do_job;
	}
	///////////////////////////////
	///////////////////////////////
	////////                ///////
	////////   file mgnt   	///////
	////////                ///////
	///////////////////////////////
	////////                ///////
	///    string array bool   ////
	////////                ///////
	///////////////////////////////
	///////////////////////////////
	function write_job_file($the_job) {
		$job_file  = fopen( PRM_JOB_PATH, "w");
		fwrite($job_file, json_encode($the_job, true));
		fclose($job_file);
		return $the_job;
	}

	function file_name($file_num = null) {
		$per = ($file_num) ? $file_num : floor($this->count/PRM_PER_FILE);
	 	return PRM_WORK_DIR.PRM_WORK_NAME."_".$per.".csv"; 
	}

	/////////////////////// fputcsv
	///////////////////////////////
	////////                ///////
	////////    make row    ///////
	////////                ///////
	///////////////////////////////
	////////                ///////
	////////   write file   ///////
	////////                ///////
	///////////////////////////////
	///////////////////////////////
	function make_row( $position, $primary, $alternate ) {

		$alternate 		 = ( $alternate ) ? $alternate : $primary;

		$post_categories = wp_get_post_terms( $primary, 'product_cat' );

		$thumb_orig_url	 = ( has_post_thumbnail( $primary ) && preg_match('~\bsrc="([^"]++)"~', get_the_post_thumbnail( $primary, 'shop_thumbnail' ), $matches ) ) ? $matches[1]  : "";


		$out[$position] = new ProductReportRowCSV;

		$out[$position]->SKU 					= html_entity_decode( get_post_meta( $alternate, '_sku', true ), ENT_COMPAT, 'UTF-8' );
		$out[$position]->Name 					= html_entity_decode( get_the_title( $primary ), ENT_COMPAT, 'UTF-8' );
		$out[$position]->WarehouseLocation 		= html_entity_decode( get_post_meta( $alternate, '_shelving_location', true ), ENT_COMPAT, 'UTF-8' );
		$out[$position]->WeightOz 				= floatval(get_post_meta( $alternate, '_weight', true) )*16;
		$out[$position]->Category 				= $post_categories[0]->name;
		$out[$position]->CustomsDescription		= $out[$position]->Name;
		$out[$position]->CustomsValue 			= floatval(get_post_meta( $alternate, '_price', true ));
		$out[$position]->ThumbnailUrl 			= ( ! empty( $thumb_orig_url) ) ? $thumb_orig_url : "";// && $new_thumb  $thumb_orig_url
		$out[$position]->UPC 					= html_entity_decode( get_post_meta( $alternate, 'UPC', true ), ENT_COMPAT, 'UTF-8' );

		$line_string = json_encode( $out[$position], true );	$line_array  = json_decode( $line_string, true );

		return $line_array;
	}

	/////////////////////// fputcsv
	///////////////////////////////
	////////                ///////
	////////    ADVANCE     ///////
	////////                ///////
	///////////////////////////////
	////////                ///////
	////////   write file   ///////
	////////                ///////
	///////////////////////////////
	///////////////////////////////
	function advance( $advance_count, $add = false ) 
	{

		$this->count = $advance_count + absint($add); 

		if ( $advance_count % PRM_PER_FILE === 0 ) {

			$this->count++;

			$file_name = $this->file_name();

			if (is_resource( $this->the_file ) ) {
					 fclose( $this->the_file ) ;
			}

			if ( is_file( $file_name ) ) {
				  unlink( $file_name ) ;
			}

			$this->the_file = fopen($file_name, "w" );
			fputcsv($this->the_file, explode(',', PRM_COL_KEYS ) );
		}
		return $this->count;
	}

	///////////////////////////////
	///////////////////////////////
	////////                ///////
	////////  START OUTPUT  ///////
	////////                ///////
	///////////////////////////////
	////////                ///////
	////////                ///////
	////////                ///////
	///////////////////////////////
	///////////////////////////////
	function do_job() 
	{	
		if ( ! is_dir( PRM_WORK_DIR ) ) {
			mkdir( PRM_WORK_DIR, 0777);
		}

		$rangeStart	= $this->job['start'] - 1;

		$args = array( 'post_type' => 'product', 'post_status' => array( 'publish' ), 'posts_per_page' => $this->job['end'], 'numberposts' => $this->job['end'], 'orderby' => 'title', 'order' => 'asc', 'offset' => $rangeStart, 'fields' => 'ids');

		$product_ids = get_posts( $args );
		// echo "<pre>"; 
		// print_r($post_products);

		$position = 0;

		$file_name  = $this->file_name();

		if ( ! is_resource( $this->the_file ) ) {
		   $this->the_file  = fopen( $file_name, "a");
		}

		for ( $index = 0; $index < $this->job['end']; $index++ ) 
		{ 
			
			$this->count = $this->advance( $this->count, false );

			$product_id = $product_ids[$index];

			if ( $product_id ) 
			{

				////////////////// get products
				///////////////////////////////
				////////                ///////
				////////   LETS START   ///////
				////////                ///////
				///////////////////////////////
				////////                ///////
				////////  WOOOOOOOOOO0  ///////
				////////                ///////
				///////////////////////////////
				///////////////////////// break
				if ( function_exists( 'get_product' ) ) { 	
					$product = get_product( $product_id );
				} else {
					$product = new WC_Product( $product_id );
				}

				////////////////  MAIN FUNCTION
				///////////////////////////////
				///////////////////////////////
				////////                ///////
				/////   variable product   ////
				////////                ///////
				///////////////////////////////
				////////                ///////
				////////                ///////
				////////                ///////
				///////////////////////////////
				/////////////////  MOVE POINTER
		        if ( $product->is_type('variable') ) {

		        	$args = array('post_type' => 'product_variation', 'post_status' => array( 'private', 'publish' ), 'numberposts' => -1, 'orderby'=> 'title', 'order' => 'asc', 'post_parent' => $product_id );
					
					$post_variations = get_posts( $args ); 
		        		
		            foreach ( $post_variations as $variation ) 
			        {		

						$row = $this->make_row( $position, $product_id, $variation->ID );
						fputcsv( $this->the_file, $row );

						$position++;
						$this->count = $this->advance( $this->count, true );		

						$this->job = $this->write_job_file( array( 'work' => $this->job['work'], 'start' => $rangeStart+1+$index+1, 'end' => $this->job['end'], 'count' => $this->count, 'refresh' => $this->job['refresh'], 'processing' => $this->job['processing'] ) );			
					}

				} else {

					///////////////////////////////
					///////////////////////////////
					////////                ///////
					/////    simple product    ////
					////////                ///////
					///////////////////////////////
					////////                ///////
					////////  output count  ///////
					////////                ///////
					///////////////////////////////
					///////////////////////////////
					$row = $this->make_row( $position, $product_id, false );
					fputcsv( $this->the_file, $row );

					$position++;
					$this->count = $this->advance( $this->count, true );

					$this->job = $this->write_job_file( array( 'work' => $this->job['work'], 'start' => $rangeStart+1+$index+1, 'end' => $this->job['end'], 'count' => $this->count, 'refresh' => $this->job['refresh'], 'processing' => $this->job['processing'] ) );			
				}
			} else {

				//////////////////////// fclose
				//////////////////// ZipArchive
				////////                ///////
				////////    ADVANCE     ///////
				////////                ///////
				///////////////////////////////
				////////                ///////
				////////   we're done   ///////
				////////                ///////
				///////////////////////////////
				///////////////////////// break
			 	fclose( $this->the_file );

				chdir(PRM_WORK_DIR);
				$archive = PRM_DIR_PATH.PRM_WORK_NAME . ".zip";
				$files = array_diff(scandir(PRM_WORK_DIR), array('..', '.'));
				foreach ($files as $file) {

					$ziph = new ZipArchive();
					if( file_exists( $archive ) )
					{
						if($ziph->open($archive, ZIPARCHIVE::CHECKCONS) !== TRUE) {
							 }
					} else {

						if($ziph->open($archive, ZIPARCHIVE::CM_PKWARE_IMPLODE) !== TRUE) {
							 }
					}
					if(!$ziph->addFile($file)) {
						}
					$ziph->close();
				}

				$this->job = $this->write_job_file( array( 'work' => $this->job['work'], 'start' => 1, 'end' => 1000, 'count' => $this->count, 'refresh' => false, 'processing' => false ) );			
				break;
			}
		}
		if ( is_resource( $this->the_file ) ) {
			fclose( $this->the_file );
		}

		return json_encode($this->job, true);
	}
}




/*************************** LOAD THE BASE CLASS *******************************
 *******************************************************************************
 * The WP_List_Table class isn't automatically available to plugins, so we need
 * to check if it's available and load it if necessary. In this tutorial, we are
 * going to use the WP_List_Table class directly from WordPress core.
 *
 * IMPORTANT:
 * Please note that the WP_List_Table class technically isn't an official API,
 * and it could change at some point in the distant future. Should that happen,
 * I will update this plugin with the most current techniques for your reference
 * immediately.
 *
 * If you are really worried about future compatibility, you can make a copy of
 * the WP_List_Table class (file path is shown just below) to use and distribute
 * with your plugins. If you do that, just remember to change the name of the
 * class to avoid conflicts with core.
 *
 * Since I will be keeping this tutorial up-to-date for the foreseeable future,
 * I am going to work with the copy of the class provided in WordPress core.
 */
if(!class_exists('WP_List_Table')){
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}




/************************** CREATE A PACKAGE CLASS *****************************
 *******************************************************************************
 * Create a new list table package that extends the core WP_List_Table class.
 * WP_List_Table contains most of the framework for generating the table, but we
 * need to define and override some methods so that our data can be displayed
 * exactly the way we need it to be.
 * 
 * To display this example on a page, you will first need to instantiate the class,
 * then call $yourInstance->prepare_items() to handle any data manipulation, then
 * finally call $yourInstance->display() to render the table to the page.
 * 
 * Our theme for this list table is going to be movies.
 */
class PRM_File_List_Table extends WP_List_Table {
    
    /** ************************************************************************
     * Normally we would be querying data from a database and manipulating that
     * for use in your list table. For this example, we're going to simplify it
     * slightly and create a pre-built array. Think of this as the data that might
     * be returned by $wpdb->query().
     * 
     * @var array 
     **************************************************************************/
    var $file_list_data = null;


    /** ************************************************************************
     * REQUIRED. Set up a constructor that references the parent constructor. We 
     * use the parent reference to set some default configs.
     ***************************************************************************/
    function __construct(){
        global $status, $page;
        //Set parent defaults
        parent::__construct( array(
            'singular'  => 'archive',     //singular name of the listed records
            'plural'    => 'archives',    //plural name of the listed records
            'ajax'      => false        //does this table support ajax?
        ) );
        
    }


    /** ************************************************************************
     * Recommended. This method is called when the parent class can't find a method
     * specifically build for a given column. Generally, it's recommended to include
     * one method for each column you want to render, keeping your package class
     * neat and organized. For example, if the class needs to process a column
     * named 'title', it would first see if a method named $this->column_title() 
     * exists - if it does, that method will be used. If it doesn't, this one will
     * be used. Generally, you should try to use custom column methods as much as 
     * possible. 
     * 
     * Since we have defined a column_title() method later on, this method doesn't
     * need to concern itself with any column with a name of 'title'. Instead, it
     * needs to handle everything else.
     * 
     * For more detailed insight into how columns are handled, take a look at 
     * WP_List_Table::single_row_columns()
     * 
     * @param array $item A singular item (one full row's worth of data)
     * @param array $column_name The name/slug of the column to be processed
     * @return string Text or HTML to be placed inside the column <td>
     **************************************************************************/
    function column_default($item, $column_name){
        switch($column_name){
            case 'title':
            case 'files':
            case 'entries':
                return $item[$column_name];
            default:
                return print_r($item,true); //Show the whole array for troubleshooting purposes
        }
    }


    /** ************************************************************************
     * Recommended. This is a custom column method and is responsible for what
     * is rendered in any column with a name/slug of 'title'. Every time the class
     * needs to render a column, it first looks for a method named 
     * column_{$column_title} - if it exists, that method is run. If it doesn't
     * exist, column_default() is called instead.
     * 
     * This example also illustrates how to implement rollover actions. Actions
     * should be an associative array formatted as 'slug'=>'link html' - and you
     * will need to generate the URLs yourself. You could even ensure the links
     * 
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
    function column_title($item){
        
        //Build row actions
        $actions = array(
            'download'      => sprintf('<a href="%s">Download CSV Archive</a>',$item['link']),
            // 'delete'    => sprintf('<a href="?page=%s&action=%s&file=%s">Delete</a>',@$_REQUEST['page'],'delete',$item['name']),
        );
        
        //Return the title contents
        return sprintf('%1$s %2$s',
            /*$1%s*/ $item['title'],
            // /*$2%s*/ $item['ID'],
            /*$3%s*/ $this->row_actions($actions)
        );
    }


    /** ************************************************************************
     * REQUIRED if displaying checkboxes or using bulk actions! The 'cb' column
     * is given special treatment when columns are processed. It ALWAYS needs to
     * have it's own method.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @param array $item A singular item (one full row's worth of data)
     * @return string Text to be placed inside the column <td> (movie title only)
     **************************************************************************/
    function column_cb($item){
        return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            /*$1%s*/ $this->_args['singular'],  //Let's simply repurpose the table's singular label ("movie")
            /*$2%s*/ $item['link']                //The value of the checkbox should be the record's id
        );
    }


    /** ************************************************************************
     * REQUIRED! This method dictates the table's columns and titles. This should
     * return an array where the key is the column slug (and class) and the value 
     * is the column's title text. If you need a checkbox for bulk actions, refer
     * to the $columns array below.
     * 
     * The 'cb' column is treated differently than the rest. If including a checkbox
     * column in your table you must create a column_cb() method. If you don't need
     * bulk actions or checkboxes, simply leave the 'cb' entry out of your array.
     * 
     * @see WP_List_Table::::single_row_columns()
     * @return array An associative array containing column information: 'slugs'=>'Visible Titles'
     **************************************************************************/
    function get_columns(){
        $columns = array(
            // 'cb'        => '<input type="checkbox" />', //Render a checkbox instead of text
            
            'title'     => 'Archive',
            'files'    => 'Files',
            'entries'  => 'Entries'
        );
        return $columns;
    }


    /** ************************************************************************
     * Optional. If you want one or more columns to be sortable (ASC/DESC toggle), 
     * you will need to register it here. This should return an array where the 
     * key is the column that needs to be sortable, and the value is db column to 
     * sort by. Often, the key and value will be the same, but this is not always
     * the case (as the value is a column name from the database, not the list table).
     * 
     * This method merely defines which columns should be sortable and makes them
     * clickable - it does not handle the actual sorting. You still need to detect
     * the ORDERBY and ORDER querystring variables within prepare_items() and sort
     * your data accordingly (usually by modifying your query).
     * 
     * @return array An associative array containing all the columns that should be sortable: 'slugs'=>array('data_values',bool)
     **************************************************************************/
    // function get_sortable_columns() {
    //     $sortable_columns = array(
    //         'name'     => array('name',true)     //true means it's already sorted
    //         // 'rating'    => array('rating',false),
    //         // 'director'  => array('director',false)
    //     );
    //     return $sortable_columns;
    // }


    /** ************************************************************************
     * Optional. If you need to include bulk actions in your list table, this is
     * the place to define them. Bulk actions are an associative array in the format
     * 'slug'=>'Visible Title'
     * 
     * If this method returns an empty value, no bulk action will be rendered. If
     * you specify any bulk actions, the bulk actions box will be rendered with
     * the table automatically on display().
     * 
     * Also note that list tables are not automatically wrapped in <form> elements,
     * so you will need to create those manually in order for bulk actions to function.
     * 
     * @return array An associative array containing all the bulk actions: 'slugs'=>'Visible Titles'
     **************************************************************************/
    // function get_bulk_actions() {
    //     $actions = array(
    //         'delete'    => 'Delete'
    //     );
    //     return $actions;
    // }


    /** ************************************************************************
     * Optional. You can handle your bulk actions anywhere or anyhow you prefer.
     * For this example package, we will handle it in the class to keep things
     * clean and organized.
     * 
     * @see $this->prepare_items()
     **************************************************************************/
    // function process_bulk_action() {
        
    //     //Detect when a bulk action is being triggered...
    //     if( 'delete'===$this->current_action() ) {
    //         wp_die('Items deleted (or they would be if we had items to delete)!');
    //     }
        
    // }


    /** ************************************************************************
     * REQUIRED! This is where you prepare your data for display. This method will
     * usually be used to query the database, sort and filter the data, and generally
     * get it ready to be displayed. At a minimum, we should set $this->items and
     * $this->set_pagination_args(), although the following properties and methods
     * are frequently interacted with here...
     * 
     * @global WPDB $wpdb
     * @uses $this->_column_headers
     * @uses $this->items
     * @uses $this->get_columns()
     * @uses $this->get_sortable_columns()
     * @uses $this->get_pagenum()
     * @uses $this->set_pagination_args()
     **************************************************************************/
    function prepare_items($file_list) 
    {
        $this->file_list_data = (array) $file_list;

        // global $wpdb; //This is used only if making any database queries

        /**
         * First, lets decide how many records per page to show
         */
        $per_page = 3;
        
        
        /**
         * REQUIRED. Now we need to define our column headers. This includes a complete
         * array of columns to be displayed (slugs & titles), a list of columns
         * to keep hidden, and a list of columns that are sortable. Each of these
         * can be defined in another method (as we've done here) before being
         * used to build the value for our _column_headers property.
         */
        $columns = $this->get_columns();
        $hidden = array();
        $sortable = array();
        
        
        /**
         * REQUIRED. Finally, we build an array to be used by the class for column 
         * headers. The $this->_column_headers property takes an array which contains
         * 3 other arrays. One for all columns, one for hidden columns, and one
         * for sortable columns.
         */
        $this->_column_headers = array($columns, $hidden, $sortable);
        
        
        /**
         * Optional. You can handle your bulk actions however you see fit. In this
         * case, we'll handle them within our package just to keep things clean.
         */
        // $this->process_bulk_action();
        
        
        /**
         * Instead of querying a database, we're going to fetch the example data
         * property we created for use in this plugin. This makes this example 
         * package slightly different than one you might build on your own. In 
         * this example, we'll be using array manipulation to sort and paginate 
         * our data. In a real-world implementation, you will probably want to 
         * use sort and pagination data to build a custom query instead, as you'll
         * be able to use your precisely-queried data immediately.
         */
        $data = $this->file_list_data;
                
        
        /**
         * This checks for sorting input and sorts the data in our array accordingly.
         * 
         * In a real-world situation involving a database, you would probably want 
         * to handle sorting by passing the 'orderby' and 'order' values directly 
         * to a custom query. The returned data will be pre-sorted, and this array
         * sorting technique would be unnecessary.
         */
        // function usort_reorder($a,$b){
        //     $orderby = (!empty($_REQUEST['orderby'])) ? $_REQUEST['orderby'] : 'name'; //If no sort, default to title
        //     $order = (!empty($_REQUEST['order'])) ? $_REQUEST['order'] : 'asc'; //If no order, default to asc
        //     $result = strcmp($a[$orderby], $b[$orderby]); //Determine sort order
        //     return ($order==='asc') ? $result : -$result; //Send final sort direction to usort
        // }
        // usort($data, 'usort_reorder');
        
        
        /***********************************************************************
         * ---------------------------------------------------------------------
         * vvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvvv
         * 
         * In a real-world situation, this is where you would place your query.
         *
         * For information on making queries in WordPress, see this Codex entry:
         * http://codex.wordpress.org/Class_Reference/wpdb
         * 
         * ^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^^
         * ---------------------------------------------------------------------
         **********************************************************************/
        
                
        /**
         * REQUIRED for pagination. Let's figure out what page the user is currently 
         * looking at. We'll need this later, so you should always include it in 
         * your own package classes.
         */
        $current_page = $this->get_pagenum();
        
        /**
         * REQUIRED for pagination. Let's check how many items are in our data array. 
         * In real-world use, this would be the total number of items in your database, 
         * without filtering. We'll need this later, so you should always include it 
         * in your own package classes.
         */
        $total_items = 3;
        // $total_items = count($data);
        
        
        /**
         * The WP_List_Table class does not handle pagination for us, so we need
         * to ensure that the data is trimmed to only the current page. We can use
         * array_slice() to 
         */
        $data = array_slice($data,(($current_page-1)*$per_page),$per_page);
        
        
        
        /**
         * REQUIRED. Now we can add our *sorted* data to the items property, where 
         * it can be used by the rest of the class.
         */
        $this->items = $data;
        
        
        /**
         * REQUIRED. We also have to register our pagination options & calculations.
         */
        $this->set_pagination_args( array(
            'total_items' => $total_items,                  //WE have to calculate the total number of items
            'per_page'    => $per_page,                     //WE have to determine how many items to show on a page
            'total_pages' => ceil($total_items/$per_page)   //WE have to calculate the total number of pages
        ) );
    }


}





/** ************************ REGISTER THE TEST PAGE ****************************
 *******************************************************************************
 * Now we just need to define an admin page. For this example, we'll add a top-level
 * menu item to the bottom of the admin menus.
 */
// function tt_add_menu_items(){
//     add_menu_page('Example Plugin List Table', 'List Table Example', 'activate_plugins', 'tt_list_file', 'tt_render_list_page');
// } add_action('admin_menu', 'tt_add_menu_items');




?>