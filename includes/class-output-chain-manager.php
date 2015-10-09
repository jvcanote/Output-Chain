<?php //HYAH!
if ( ! defined( 'ABSPATH' ) ) exit;

class Output_Chain_Manager
{   
    /**
     * The single instance of Output_Chain_Manager.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    /**
     * Tools class object
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $reports = null;

    /**
     * The version number.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_version;

    /**
     * The token.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $_token;

    /**
     * The main plugin file.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $file;

    /**
     * The main plugin directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $dir;

    /**
     * The plugin assets directory.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_dir;

    /**
     * The plugin assets URL.
     * @var     string
     * @access  public
     * @since   1.0.0
     */
    public $assets_url;


    /**
     * Constructor function.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function __construct ( $file = '', $version = '1.0.0' ) {
        $this->_version = $version;
        $this->_token = 'ocm_report';

        // Load plugin environment variables
        $this->file         = $file;
        $this->dir          = dirname( $this->file );
        $this->assets_dir   = trailingslashit( $this->dir ) . 'assets';
        $this->assets_url   = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );


        ///////////////////////////////
        /////////////      ////////////
        ////////                ///////
        ////////   constants    ///////
        ////////                ///////
        //////////////   //////////////
        ///////////////////////////////
        define( 'OCM_TITLE',           "Product Reports" );

        define( 'OCM_NAME',            "ocm_report_" );

        define( 'OCM_TODAY',            date("Ymd") );

        define( 'OCM_DIR_PATH',         WP_CONTENT_DIR . "/inventory-csv/" );
        define( 'OCM_DIR_URL',          WP_CONTENT_URL . "/inventory-csv/" );

        define( 'OCM_WORK_NAME',        OCM_NAME.OCM_TODAY );
        define( 'OCM_WORK_DIR',         OCM_DIR_PATH . OCM_WORK_NAME . "/" );
        define( 'OCM_WORK_URL',         OCM_DIR_URL . OCM_WORK_NAME . "/" );

        define( 'OCM_JOB_PATH',         OCM_DIR_PATH . "job.json" );

                                        $total_items = absint( $this->ocm_get_woocommerce_total_products() );
        define( 'OCM_TOTAL',            $total_items);

        define( 'OCM_PER_FILE',         5000 );
        define( 'OCM_COL_KEYS',         "SKU,Name,WarehouseLocation,WeightOz,Category,Tag1,Tag2,Tag3,Tag4,Tag5,CustomsDescription,CustomsValue,CustomsTariffNo,CustomsCountry,ThumbnailUrl,UPC,FillSKU,Length,Width,Height,UseProductName,Active" );


        if( ! function_exists('is_plugin_active') ) 
        {
            include_once( ABSPATH . 'wp-admin/includes/plugin.php');
        }

        register_activation_hook( $this->file, array( $this, 'install' ) );


        // Modify custom post type arguments
        add_filter( 'output_configuration_register_args',   array(&$this, 'output_configuration_post_type_args' ), 10, 1 );

        add_action( 'admin_init',                           array(&$this, 'set_filesystem'                  ));

        add_action( 'wp_dashboard_setup',                   array(&$this, 'ocm_get_woocommerce_total_products'   ));
    
        add_action( 'wp_dashboard_setup',                   array(&$this, 'ocm_add_dashboard_widget'         ));
    
        add_action( 'admin_enqueue_scripts',                array(&$this, 'ocm_enqueue_scripts'              ));
        
        add_action( 'wp_ajax_ocm_result_dashboard',         array(&$this, 'ocm_result_dashboard_callback'    )); 
    
        add_action( 'wp_ajax_ocm_run_report',               array(&$this, 'ocm_run_report_callback'          )); 


        // Handle localisation
        $this->load_plugin_textdomain();
        add_action( 'init', array( $this, 'load_localisation' ), 0 );

    }

    /**
     * initialize Direct WP_Filesystem API.
     */
    function set_filesystem( ){

        $access_type = get_filesystem_method();
        
        if ($access_type === 'direct') {
            $creds = request_filesystem_credentials( site_url() . '/wp-admin/', '', false, false, array());
            /* initialize the API */
            if ( ! WP_Filesystem($creds) ) return false;

            global $wp_filesystem;

            if ( ! $wp_filesystem->is_dir( OCM_WORK_DIR ) ) {
                $wp_filesystem->mkdir( OCM_WORK_DIR );
            }

        } else {
            /* don't have direct write access. Prompt user with our notice */
            // add_action('admin_notice', 'you_admin_notice_function');    
        }
    }

    /**
     * Get the total of published products.
     *
     * @since 1.0
     */
    function ocm_get_woocommerce_total_products() {
        $args = array( 'post_type' => 'product', 'post_status' => array( 'publish' ), 'numberposts' => -1, 'orderby'  => 'title', 'order' => 'asc', 'fields' => 'ids' );
    
        $post_products = get_posts( $args ); 

        return sizeof( $post_products );
    }

    /**
     * Set job
     * @return array
     */
    function write_job_file( $the_job ) {
        set_transient( OCM_WORK_NAME, (array)$the_job, HOUR_IN_SECONDS * 1 );
        return $the_job;
    }

    /**
     * File extendion
     * @return string
     */
    function file_extension( $file_name ) {
        return substr( strrchr( $file_name,'.' ), 1 );
    }

    /**
     * File lists
     * @return array
     */
    function file_list ($directory = OCM_DIR_PATH, $file_name = OCM_WORK_NAME, $file_extension = 'zip' ) {
        global $wp_filesystem;

        $file_list = array();
        $files     = array_diff( scandir($directory), array('..', '.'));

        foreach ($files as $file) {
            if( $this->file_extension( $file ) == $file_extension )
                $file_list[] = $file;
        }
        return $file_list;
    }

    function get_file_list() 
    {
        global $wp_filesystem;

        $file_data     = array();
        $file_list_zip = $this->file_list();
        $file_list_zip = (sizeof($file_list_zip) > 1) ? array_reverse( $file_list_zip ) : $file_list_zip;
        $z = 0;
        foreach ($file_list_zip as $archive_zip) {

            $archive_name = str_replace(".zip", "", $archive_zip);

            $file_list_csv = $this->file_list( OCM_DIR_PATH.$archive_name."/", $archive_name, "csv");

            $wp_filesystem->chdir(OCM_DIR_PATH.$archive_name."/");

            $i = 0;
            $n = 0;
            $x = (int) (sizeof($file_list_csv) - 1);
            $linecount = 0;

            $link = '<a class="download" style="white-space:nowrap;" href="'.OCM_DIR_URL.$archive_zip.'" >'.$archive_zip.'</a>';
            
            foreach ($file_list_csv as $report_csv) {

                $n++; 
                $this_linecount = ($i === $x ) ? count( file( $report_csv ) ) : OCM_PER_FILE;
                $linecount = $linecount + ( $this_linecount - 1 );
                $i++;
            }
            
            $file_data[]    = array(
                'ID'        =>  (int) $z,
                'title'     =>  "<strong style='white-space:nowrap'>". strftime("%b %e, %Y", strtotime( str_replace( OCM_NAME, '', $archive_name) ) )."</strong>",
                'files'     =>  (int) $n,
                'entries'   =>  (int) $linecount,
                'link'      =>  OCM_DIR_URL.$archive_zip
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
    function ocm_add_dashboard_widget() {
        wp_add_dashboard_widget( 'ocm_widget', OCM_TITLE, array(&$this,'ocm_dashboard_widget_function' ));
    }

    /**
     * Display widget
     * @return void
     */
    function ocm_dashboard_widget_function() {
        
        $current_files = $this->get_file_list();

        //Create an instance of our package class...
        $fileListTable = new OCM_File_List_Table();
        //Fetch, prepare, sort, and filter our data...
        $fileListTable->prepare_items( $current_files ); ?>

        <style type="text/css">
        #ocm_widget .widefat td, #ocm_widget .widefat th {text-align: center; vertical-align: middle; }
        #ocm_widget .widefat .column-title {width: 55%; text-align: left; }
        #ocm_widget .widefat .row-actions {visibility: visible; } 
        #ocm_widget .widefat .row-actions a {font-size: smaller; } 
        #ocm_progress {padding: 0; height: 3px; }
        #ocm_form_wrapper {margin-bottom: 12px; }
        #ocm_form_wrapper .tablenav {display: none; }
        </style>
        <div id="ocm_form_wrapper">
            <?php $fileListTable->display(); ?>
        </div>
        <input id='ocm_form_reset' type='button' class='button' value='Run Report' />
        <img src="<?php echo WP_PLUGIN_URL ."/". basename( dirname( __FILE__ ) ); ?>/img/shipstation.png" height="30" style="position:absolute;right:14px;bottom:8px;" />
        <?php
    }

    /**
     * load JS and the data (admin dashboard only)
     * @param  string $hook current page
     * @return void
     */
    function ocm_enqueue_scripts( $hook ) {

        if( 'index.php' != $hook ) {
            add_action(   'admin_head', array(&$this,'ocm_dashboard_notification'));
        } else {
            add_action( 'admin_footer', array(&$this,'ocm_dashboard_javascript'  ));
        }
    }

    //* Add alert to Dashboard home 
    function ocm_dashboard_notification() { 
        if (! is_admin()) : ?>
            <script type="text/javascript">
            $j = jQuery; $j().ready(function(){$j('.wrap > h2').parent().prev().after('<div class="update-nag">Please contact <a href="mailto:support@webdogs.com?subject=PACWAVE.com" target="_blank">support@webdogs.com</a> for assistance with updating WordPress or making changes to your website.</div>'); });
            </script><?php
        endif;
    } 

    function ocm_dashboard_javascript() { 
        $run = ( true === ( $processing = get_transient( OCM_WORK_NAME ) ) ); 
        $ajax_nonce = wp_create_nonce( "ocm-run-report" ); ?>

        <script type="text/javascript">

            var ocm_init = 1;

            jQuery(function($) {


                function startOCMInterval(new_report) {

                    var report = new_report;
                    var init = ocm_init;

                    if(!report) 
                    {
    
                        if(init) {                                                   //style="background:#D0F2FC"
                            $('<thead id="ocm_progress_head" style="display:none;"><tr><th colspan="3" id="ocm_progress" class="progress active"><span id="ocm_progress_bar" class="bar"></span></th></tr></thead>').insertBefore('#ocm_widget #the-list');
                            ocm_init = 0;
                        }

                        var data = 
                        {
                            'action'  :   'ocm_run_report',
                            'security':   '<?php echo $ajax_nonce; ?>',
                            'new'     :   0,
                            'json'    :   1
                        };

                        $.post(ajaxurl, data, function(processing) {

                            if(processing=="finished")
                            {
                                data = 
                                {
                                    'action'  :   'ocm_run_report',
                                    'security':   '<?php echo $ajax_nonce; ?>',
                                    'new'     :   0
                                };
                                $.post(ajaxurl, data, function(response) 
                                {
                                    $('#ocm_widget #ocm_form_wrapper').html( response );
                                });
                                stopOCMInterval();
                            } 
                            else 
                            if(processing=="error") {

                                $('#ocm_widget #the-list tr:first-child').addClass('alert').find('.row-actions').html('<span class="processing" style="color:crimson;">Output Error</span>');
                                stopOCMInterval();

                            } else {
                                $('#ocm_progress_head:hidden').show( 'fast' );
                                $('#ocm_widget #the-list tr:first-child').replaceWith(processing);

                                var progress_data = $('#the-list tr:first-child').attr('data-progress');
                                var progress_int  = ( progress_data / <?php echo OCM_TOTAL; ?> )*100;
                                var progress      = progress_int+"\%"; 

                                $('#ocm_progress_bar').width(progress);
                            }
                        });

                    } else {

                        var data = 
                        {
                            'action'    :   'ocm_run_report',
                            'security'  :   '<?php echo $ajax_nonce; ?>',
                            'new'       :   1,
                            'count'     :   0,
                            'refresh'   :   true,
                            'processing':   true
                        };
                        $.post(ajaxurl, data, function(response) 
                        {   
                            $('#ocm_progress_head:hidden').show( 'fast' );                                                //style="background:#D0F2FC"
                            $('#ocm_widget #ocm_form_wrapper').html( response );
                            $('#ocm_widget #the-list tr:first-child').replaceWith('<tr class="processing_row highlight" data-progress="0"><td class="title column-title"><strong style="white-space:nowrap"><?php echo strftime("%b %e, %Y", time() ); ?></strong> <div class="row-actions" style="visibility: visible;"><span class="processing" style="color:#377A9F">Processing</span></div></td><td class="files column-files">0</td><td class="entries column-entries">0</td></tr>'); 
                            $('<thead id="ocm_progress_head"><tr><th colspan="3" id="ocm_progress" class="progress active"><span id="ocm_progress_bar" class="bar"></span></th></tr></thead>').insertBefore('#ocm_widget #the-list');
                        });
                            
                        OCMInterval = setInterval( function(){ startOCMInterval( false ) }, 4200);
                    }
                    ocm_init = 0;
                    init = 0;
                }

                function stopOCMInterval() {
                    clearInterval(OCMInterval);
                }

                $('#ocm_widget').find('.hndle').css({"background-color":"#666", "font-family":"'Helvetica Neue',Helvetica,Arial,sans-serif"}).html('<span style="color:#FFFFFF;text-decoration:none;font-weight:bold;text-transform:uppercase;padding-top: 0;padding-bottom: 0;display: inline-block;position: relative; vertical-align: middle;margin-top: -3px;line-height: 13px;" title="WEBDOGS" href="http://webdogs.com/" target="_blank"><b><img style="border-width:0px;margin-right:5px;margin-left:0px;margin-top: -13px;margin-bottom: 0;display: inline;vertical-align: middle;line-height: 26px;top: 5px;position: relative;" src="data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAACMAAAAjCAQAAAC00HvSAAAABGdBTUEAALGPC/xhBQAAACBjSFJNAAB6JgAAgIQAAPoAAACA6AAAdTAAAOpgAAA6mAAAF3CculE8AAAAAmJLR0QAQjJfA44AAAAHdElNRQfeDBYLLDqnCm9AAAADv0lEQVRIx5VWCU8TQRTe31aQQ9BqlcuYCBRRbpRGDIcWjJVoUJFLBFEUFSUqigoFBQkYwUA0GAyJByB4EVAwSjAIn99Op8tuu21wJu3uvvfm25l3fG8VixJ8RsKKLQhDcKuAilRUox1dcKOTv27+bqEUNmwYJoTmbjxFPXIQLZeFIoHS2xhAC5KxAZh0POf77cI0gkscKEYBMrEDHjgXRnBNgw8A04hhZNAkDOUYwjzWxxLe4yYSxfJavEIaAsCE0BcPEUp1Babl4kXMaUCr/C3jEeJpkYXXOAxTmHY60aLE4IVuD/08kncU0y/q+A4n7RIwhoPwg6nHXQqT8Q368ZKySXG3wLtTmrxBAL2Rh9RgsnnaTdiNWRjHAsJxTNy95YLjOk01n/PxTA8TxuikMsXG4D9UD0zxOij2uqrzVC4lrTgNDaYMD/hwBWajiRoXr9eF+X2dZgZRiMEo/yVMH3YxP5dMYXLgcX+SuFp1kQNqKGtmJgmYdKa8RblkAjGBo9gmUmB91ur0XxGJPejzwDThLG8++0C8wwnGoYQ+syMfF5EnwQ4YrAopHWShUNFDw0SsGdQddHgCK8vO9/0QkklZ5YUGuzuUtaEIjFI/DVwG5UeEkB7GsY9G4fDkzRr3pcIYLUcpO0kfKTYTzwyKUBbJY+wX+/klSSLbYDlDWR6uQonjAdRF3rHMzD3H/WXqHFtA+R9GU70PZyKujzkmbSZuQIk1wIyI9HaSHoyVX4EVOtrr5FUdTCiyVBgrHlPdIMVlwvQCqgwwDpLHXx10h86Lakk0gfQwQBIqleICUYBfEKctiUYvpsguDsbMK4vBT13p1qACAjuL51YDvsIgJ8h3eGcyflNyxOeQzRJGpZZOEgZFlWiU5TfPN0ayenqYu+tLXJIY9DOaMVKHg6kxzORQVN5Q07mKwllEmNB1HDUVfvIsSqcZp1xypixNdVvRJMwVVog5TKuJvI2JYVHcggNlCFX6qaZ5t4m5nflcaSIP417SSLkh0NivHcf5MESgAaLH87RRWmWHB+mYfZJItBCOM8hWfJCZvEg/TdBn+UGbbiMboA+lO5jBm7GTcMbxBNsDQJWwk0XBr8GcISNvZay6fIAmJfMZp5NNIA6mXbOMTSwFaika9/SJnGsEOc/8tSFg880gUIMkhHam2LIEcuuW7OWu23wyzG+zUbjMaNXJ99vIfxmkr3h4QuwgeJ+ovA1838SSewfYz+vYcNPpmRQmQTnpoJd+c+K/PpMsShKptYVgbs57BD4U8CPJovwDRRo5ALFcUX0AAAAldEVYdGRhdGU6Y3JlYXRlADIwMTQtMTItMjJUMTE6NDQ6NTgrMDE6MDBej4ixAAAAJXRFWHRkYXRlOm1vZGlmeQAyMDE0LTEyLTIyVDExOjQ0OjU4KzAxOjAwL9IwDQAAAABJRU5ErkJggg==" alt="" width="26" height="26"><span style="text-decoration:none;color:#FFFFFF;vertical-align:middle;display: inline-block;font-size: 13px;margin-right: 4px;">WEBDOGS </span></b></span><span style="color: #D0F2FC; text-decoration: none;display: inline-block; vertical-align: middle;margin: -1px 0 0 0;padding: 0;font-size: 13px;line-height: 13px;"><?php echo OCM_TITLE; ?></span></h3>');

                $('#ocm_form_reset').live('click', function(e) {
                    startOCMInterval( true );
                    e.preventDefault();
                });

            <?php if( $run ) : ?>
                var OCMInterval = setInterval( function(){ startOCMInterval( false ) }, 4200);
                startOCMInterval( false );
            <?php endif; ?>

            });
        </script><?php
    }
    function ocm_result_dashboard_callback() {
        die();
    }

    function ocm_run_report_callback() {

        check_ajax_referer( 'ocm-run-report', 'security', true );

        $processing = get_transient( OCM_WORK_NAME );
    
        if( $_REQUEST['new']==1 ){

                      $job = $this->write_job_file( array( 'work' => OCM_WORK_NAME, 'start' => 1, 'end' => 500, 'count' => 0, 'refresh' => true, 'processing' => true ) );
            $current_files = $this->get_file_list();

            if( $current_files[0]['link'] != OCM_DIR_URL.OCM_WORK_NAME.".zip" ) {
                $current_files[0]          = array(
                    'ID'        =>  null,
                    'title'     =>  null,
                    'files'     =>  null,
                    'entries'   =>  null,
                    'link'      =>  null
                );
            }

            //Create an instance of our package class...
            $fileListTable = new OCM_File_List_Table();
            //Fetch, prepare, sort, and filter our data...
            $fileListTable->prepare_items( $current_files ); ?>

                    <?php $fileListTable->display(); ?>
            <?php
            wp_schedule_single_event( time(), 'ocm_product_report', array( $job ) );

        }

        elseif( isset( $_REQUEST['json'] ) && $_REQUEST['json']==1 && $_REQUEST['new']==0) 
        {
            
            if( ! isset($processing['refresh'] ) || $processing['refresh']==0 ) {
                echo "finished";
                die();
            } 
            elseif( isset($processing['error'] ) && $processing['error'] == "Invalid Workload" ) {          
                echo "error";
                die();

            } else {
                $processing_date = strftime("%b %e, %Y", strtotime( str_replace( OCM_NAME, '', $processing['work']) ) );
                
                $per = floor($processing['count']/OCM_PER_FILE)+1; //style="background:#D0F2FC"  
                $progress =  $processing['start']; ?>

                    <tr class="processing_row highlight" data-progress="<?php echo $progress; ?>"><td class="title column-title"><strong style="white-space:nowrap"><?php echo $processing_date; ?></strong> <div class="row-actions" style="visibility: visible;"><span class="processing" style="color:#377A9F">Processing</span></div></td><td class="files column-files"><?php echo $per; ?></td><td class="entries column-entries"><?php echo $processing['count']; ?></td></tr>
                
                <?php
                // if ($progress>OCM_TOTAL) {
                //     $job = $this->write_job_file( array( 'work' => OCM_WORK_NAME, 'start' => 1, 'end' => 500, 'count' => 0, 'refresh' => false, 'processing' => false ) );
                //     wp_schedule_single_event( time(), 'ocm_product_report', array( $job ) );
                //     $this->archive_zip();
                // }
            }
        } else {

            $current_files = $this->get_file_list();
            //Create an instance of our package class...
            $fileListTable = new OCM_File_List_Table( );
            //Fetch, prepare, sort, and filter our data...
            $fileListTable->prepare_items( $current_files ); ?>

            <?php $fileListTable->display(); ?>
            
            <?php wp_schedule_single_event( time(), 'ocm_product_report', array( $processing ) );
        }

        die();
    }
    
     /**
     * Register WooCommerce taxonomies.
     */
    // public static function register_taxonomies() {

    //     register_post_type( 'ocm_report', 
    //         array(
    //             'label'               => __( 'ocm_report', 'ocm' ),
    //             'description'         => __( 'Product Report', 'ocm' ),
    //             'labels'              => array(
    //                 'name'                => _x( 'OCM Reports', 'Post Type General Name', 'ocm' ),
    //                 'singular_name'       => _x( 'OCM Report', 'Post Type Singular Name', 'ocm' ),
    //                 'menu_name'           => __( 'OCM Reports', 'ocm' ),
    //                 'parent_item_colon'   => __( 'Parent Report:', 'ocm' ),
    //                 'all_items'           => __( 'All Reports', 'ocm' ),
    //                 'view_item'           => __( 'View Report', 'ocm' ),
    //                 'add_new_item'        => __( 'Add New Report', 'ocm' ),
    //                 'add_new'             => __( 'Add New', 'ocm' ),
    //                 'edit_item'           => __( 'Edit Report', 'ocm' ),
    //                 'update_item'         => __( 'Update Report', 'ocm' ),
    //                 'search_items'        => __( 'Search Reports', 'ocm' ),
    //                 'not_found'           => __( 'Not found', 'ocm' ),
    //                 'not_found_in_trash'  => __( 'Not found in Trash', 'ocm' ),
    //             ),
    //             'supports'            => array( 'title', 'custom-fields', ),
    //             'hierarchical'        => false,
    //             'public'              => true,
    //             'show_ui'             => true,
    //             'show_in_menu'        => true,
    //             'show_in_nav_menus'   => true,
    //             'show_in_admin_bar'   => true,
    //             'menu_position'       => 5,
    //             'menu_icon'           => 'dashicons-media-spreadsheet',
    //             'can_export'          => true,
    //             'has_archive'         => true,
    //             'exclude_from_search' => false,
    //             'publicly_queryable'  => true,
    //             'query_var'           => 'report',
    //             'rewrite'             => false,
    //             'capability_type'     => 'post',
    //         )   
    //     );
   

    //     // if ( taxonomy_exists( 'product_type' ) ) {
    //     //     return;
    //     // }

    //     register_taxonomy( 'product_shelving_location',
    //         array( 'product' ),
    //         array(
    //             'hierarchical'          => false,
    //             'update_count_callback' => '_wc_term_recount',
    //             'label'                 => __( 'Shelving Location', 'ocm' ),
    //             'labels'                => array(
    //                     'name'                       => __( 'Shelving Location', 'ocm' ),
    //                     'singular_name'              => __( 'Shelving Locations', 'ocm' ),
    //                     'menu_name'                  => _x( 'Shelving Locations', 'Admin menu name', 'ocm' ),
    //                     'search_items'               => __( 'Search Shelving Location', 'ocm' ),
    //                     'all_items'                  => __( 'All Shelving Location', 'ocm' ),
    //                     'edit_item'                  => __( 'Edit Shelving Locations', 'ocm' ),
    //                     'update_item'                => __( 'Update Shelving Location', 'ocm' ),
    //                     'add_new_item'               => __( 'Add New Shelving Location', 'ocm' ),
    //                     'new_item_name'              => __( 'New Product Shelving Location', 'ocm' ),
    //                     'popular_items'              => __( 'Popular Shelving Locations', 'ocm' ),
    //                     'separate_items_with_commas' => __( 'Separate Shelving Locations with commas', 'ocm'  ),
    //                     'add_or_remove_items'        => __( 'Add or remove Shelving Location', 'ocm' ),
    //                     'choose_from_most_used'      => __( 'Choose from the most used Shelving Locations', 'ocm' ),
    //                     'not_found'                  => __( 'No Shelving Location found', 'ocm' ),
    //                 ),
    //             'show_ui'               => true,
    //             'show_in_nav_menus'     => true,
    //             'query_var'             => is_admin(),
    //             'capabilities'          => array(
    //                 'manage_terms' => 'manage_product_terms',
    //                 'edit_terms'   => 'edit_product_terms',
    //                 'delete_terms' => 'delete_product_terms',
    //                 'assign_terms' => 'assign_product_terms',
    //             ),
    //             'rewrite'               => false,
    //         )
    //     );

    // }


    /**
     * Register post type for registrations
     * @return void
     */
    public function register_post_types () {
        $this->register_post_type( 'output_configutation', __( 'Configurations', 'output-chain-manager' ), __( 'Configurations', 'output-chain-manager' ) );
        // $this->register_post_type( 'registration_form', __( 'Registration Forms', 'output-chain-manager' ), __( 'Registration Form', 'output-chain-manager' ) );
        // $this->register_post_type( 'registration_email', __( 'Registration Emails', 'output-chain-manager' ), __( 'Registration Email', 'output-chain-manager' ) );
    }

    /**
     * Change settings for registration post type
     * @param  array  $args Default args
     * @return array        Modified args
     */
    public function output_configuration_post_type_args ( $args = array() ) {

        $args['publicly_queryable']     = false;
        $args['exclude_from_search']    = true;
        $args['show_in_nav_menus']      = false;
        $args['menu_position']          = 56;
        $args['menu_icon']              = 'dashicons-media-spreadsheet';
        $args['supports']               = array( 'title' );

        return $args;
    }

    /**
     * Change settings for registration form post type
     * @param  array  $args Default args
     * @return array        Modified args
     */
    public function registration_form_post_type_args ( $args = array() ) {

        $args['publicly_queryable']     = false;
        // $args['exclude_from_search']    = true;
        // $args['show_in_nav_menus']      = false;
        // $args['menu_position']          = 56;
        // $args['menu_icon']              = 'dashicons-clipboard';
        // $args['supports']               = array( 'title');

        return $args;
    }

    /**
     * Change settings for registration email post type
     * @param  array  $args Default args
     * @return array        Modified args
     */
    public function registration_email_post_type_args ( $args = array() ) {

        // $args['publicly_queryable']     = false;
        // $args['exclude_from_search']    = true;
        // $args['show_in_nav_menus']      = false;
        // $args['menu_position']          = 56;
        // $args['menu_icon']              = 'dashicons-email';
        // $args['supports']               = array();

        return $args;

    }

    /**
     * Wrapper function to register a new post type
     * @param  string $post_type   Post type name
     * @param  string $plural      Post type item plural name
     * @param  string $single      Post type item single name
     * @param  string $description Description of post type
     * @return object              Post type class object
     */
    public function register_post_type ( $post_type = '', $plural = '', $single = '', $description = '' ) {

        if( ! $post_type || ! $plural || ! $single ) return;

        $post_type = new Output_Chain_Post_Type( $post_type, $plural, $single, $description );

        return $post_type;
    }   


    /**
     * Add Ourput Chain pages to WooCommerce screen IDs
     * @param  array $screen_ids Existing IDs
     * @return array             Modified IDs
     */
    public function screen_ids( $screen_ids = array() ) {
        // $screen_ids[] = 'edit-event_registration';
        // $screen_ids[] = 'event_registration';
        // $screen_ids[] = 'registration_email';
        // $screen_ids[] = 'edit-registration_email';
        // $screen_ids[] = 'event_registration_page_registration_tools';

        return $screen_ids;
    }

    /**
     * Remove 'Add New' menu item from Registrations
     * @return void
     */
    public function hide_registration_add () {
        global $submenu;
        // unset( $submenu['edit.php?post_type=event_registration'][10] );
        // unset( $submenu['edit.php?post_type=registration_email'][10] );
    }

    /**
     * Prevent access to specific admin pages
     * @return void
     */
    public function block_admin_pages () {

        // if( ! is_admin() ) {
        //     return;
        // }

        // global $pagenow;

        // $type = '';
        // if( isset( $_GET['post_type'] ) ) {
        //     $type = esc_attr( $_GET['post_type'] );
        // }

        // if( ! $type ) {
        //     return;
        // }

        // $url = '';

        // if( 'post-new.php' == $pagenow && 'event_registration' == $type ) {
        //     $url = admin_url( 'edit.php?post_type=event_registration' );

        // } elseif( 'post-new.php' == $pagenow && 'registration_email' == $type ) {
        //     $url = admin_url( 'edit.php?post_type=event_registration&page=registration_tools&tab=email' );

        // // } elseif( 'edit.php' == $pagenow && 'registration_email' == $type ) {
        // //  return;
            
        // } elseif( 'post-new.php' == $pagenow && 'registration_form' == $type ) {
        //     return;

        // } elseif( 'edit.php' == $pagenow && 'registration_form' == $type ) {
        //     return;
        // }

        // if( $url ) {
        //     wp_safe_redirect( $url );
        //     exit;
        // }
    }

    /**
     * Load plugin localisation
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_localisation () {
        load_plugin_textdomain( 'output-chain-manager', false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
    }

    /**
     * Load plugin textdomain
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function load_plugin_textdomain () {
        $domain = 'output-chain-manager';

        $locale = apply_filters( 'plugin_locale', get_locale(), $domain );

        load_textdomain( $domain, WP_LANG_DIR . '/' . $domain . '/' . $domain . '-' . $locale . '.mo' );
        load_plugin_textdomain( $domain, false, dirname( plugin_basename( $this->file ) ) . '/lang/' );
    }

    /**
     * Main Output_Chain_Manager Instance
     *
     * Ensures only one instance of Output_Chain_Manager is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see Output_Chain_Manager()
     * @return Main Output_Chain_Manager instance
     */
    public static function instance ( $file = '', $version = '1.0.0' ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $file, $version );
        }
        return self::$_instance;
    }

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    }

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->_version );
    }

    /**
     * Installation. Runs on activation.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    public function install () {
        $this->register_post_types();
        flush_rewrite_rules();
        $this->_log_version_number();
    }

    /**
     * Log the plugin version number.
     * @access  public
     * @since   1.0.0
     * @return  void
     */
    private function _log_version_number () {
        update_option( $this->_token . '_version', $this->_version );
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
class OCM_File_List_Table extends Output_Chain_List_Table {
    
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