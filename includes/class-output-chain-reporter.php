<?php
/**
* 
*/
class Output_Chiain_Reporter
{
/**
     * The single instance of Output_Chiain_Reporter.
     * @var     object
     * @access  private
     * @since   1.0.0
     */
    private static $_instance = null;

    /**
     * The main plugin object.
     * @var     object
     * @access  public
     * @since   1.0.0
     */
    public $parent = null;

   /**
     * The workload details.
     * @var     array
     * @access  public
     * @since   1.0.0
     */
    public  $job;     

    /**
     * Current number of lines proccessed.
     * @var     integer
     * @access  public
     * @since   1.0.0
     */ 
    public  $count;   

    /**
     * The file resource.
     * @var     resource
     * @access  private
     * @since   1.0.0
     */
    private $the_file;

    // private $report_id;
    
    /**
     * Constructor
     * @param object $parent Main plugin class
     */
    public function __construct ( $parent ) {
        $this->parent = $parent;
        add_action( 'ocm_product_report', array( $this, 'proccess_workload' ), 10, 1 );
    }


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
    ///////////////////////////////
    ///////////////////////////////
    ////////                ///////
    ////////   job setup    ///////
    ////////                ///////
    ///////////////////////////////
    ////////                ///////
    ///        ocm_report       ///
    ////////                ///////
    ///////////////////////////////
    ///////////////////////////////
    function proccess_workload( $job )
    {
        $new = (isset($_REQUEST['new']))?$_REQUEST['new'] :null;

        //New job

        if( $new ) {

            $work           = OCM_WORK_NAME;
            $start          = 1;
            $end            = 500;
            $count          = 0;
            $refresh        = true;
            $processing     = true;

        } else {

        //Current Job

            if( ! is_array( $job ) ) die();

            extract($job);

            error_log('['.date('dmY').']got:'.json_encode( $job )."\n", 3, "./activity.log");

        }

        settype($work,          "string" );
        settype($start,         "integer");
        settype($end,           "integer");
        settype($count,         "integer");
        settype($refresh,       "boolean");
        settype($processing,    "boolean");

        $this->count = $count;

        $this->job   = $this->write_job_file( array( 'work' => $work, 'start' => $start, 'end' => $end, 'count' => $count, 'refresh' => $refresh, 'processing' => $processing, 'error' => ( $work !== OCM_WORK_NAME ) ? 'Invalid Workload' : null ) );

        ob_end_clean();

        //DO JOB
        ////////

        $do_job      = ( $work == OCM_WORK_NAME && $end !== 0 && $processing ) ? $this->do_job() : $this->job;

        //CHECK RESULT
        //////////////
        if( $this->job['start'] > OCM_TOTAL ) {
            $this->archive_zip();
        }

        if( $do_job['refresh'] ) {
            wp_schedule_single_event( time(), 'ocm_product_report', array( $this->job ) );

        } else {

            $this->job = $this->write_job_file( array( 'work' => OCM_WORK_NAME, 'start' => 1, 'end' => 500, 'count' => 0, 'refresh' => false, 'processing' => false ) ); 
            wp_schedule_single_event( time(), 'ocm_product_report', array( $this->job ) );
            error_log('['.date('dmY').']got:'.json_encode($do_job)."\n", 3, "./activity.log");
        }

    }

    ///////////////////////////////
    ///////////////////////////////
    ////////                ///////
    ////////   file mgnt    ///////
    ////////                ///////
    ///////////////////////////////
    ////////                ///////
    ///    string array bool   ////
    ////////                ///////
    ///////////////////////////////
    ///////////////////////////////
    function write_job_file($the_job) {
        set_transient( OCM_WORK_NAME, (array)$the_job, HOUR_IN_SECONDS * 1 );
        return $the_job;
    }

    function file_name($file_num = null) {
        $per = ($file_num) ? $file_num : floor($this->count/OCM_PER_FILE);
        return OCM_WORK_DIR.OCM_WORK_NAME."_".$per.".csv"; 
    }
    function archive_zip() {
        global $wp_filesystem;
        $wp_filesystem->chdir(OCM_WORK_DIR);

        $archive = OCM_DIR_PATH.OCM_WORK_NAME . ".zip";
        $files   = array_diff(scandir(OCM_WORK_DIR), array('..', '.'));

        foreach ($files as $file) {

            $ziph = new ZipArchive();

            if( file_exists( $archive ) ) { 
              if( $ziph->open( $archive, ZIPARCHIVE::CHECKCONS ) !== TRUE ) { } } else {
              if( $ziph->open( $archive, ZIPARCHIVE::CM_PKWARE_IMPLODE ) !== TRUE ) { } }
            if( ! $ziph->addFile( $file ) ) { }
            
            $ziph->close();
        }
    /////////////////////////////////////////
    //LOG FINISH ///////////////////////////
    ///////////////////////////////////////
        error_log('['.date('dmY').']got:'.json_encode($this->job)."\n", 3, "./activity.log");
    //CLEANUP ///////////////////////////
    ////////////////////////////////////
        delete_transient( OCM_WORK_NAME );
    //////////////////////////////////
        die(); // END PROCCESS //////
    ////////////////////////////////
       
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

        $alternate       = ( $alternate ) ? $alternate : $primary;

        $post_categories = wp_get_post_terms( $primary, 'product_cat' );

        $thumb_orig_url  = ( has_post_thumbnail( $primary ) && preg_match('~\bsrc="([^"]++)"~', get_the_post_thumbnail( $primary, 'shop_thumbnail' ), $matches ) ) ? $matches[1]  : "";


        $out[$position] = new Output_Chain_Row_Columns;

        $out[$position]->SKU                    = html_entity_decode( get_post_meta( $alternate, '_sku', true ), ENT_COMPAT, 'UTF-8' );
        $out[$position]->Name                   = html_entity_decode( get_the_title( $primary ), ENT_COMPAT, 'UTF-8' );
        $out[$position]->WarehouseLocation      = html_entity_decode( get_post_meta( $alternate, '_shelving_location', true ), ENT_COMPAT, 'UTF-8' );
        $out[$position]->WeightOz               = floatval(get_post_meta( $alternate, '_weight', true) )*16;
        $out[$position]->Category               = $post_categories[0]->name;
        $out[$position]->CustomsDescription     = $out[$position]->Name;
        $out[$position]->CustomsValue           = floatval(get_post_meta( $alternate, '_price', true ));
        $out[$position]->ThumbnailUrl           = ( ! empty( $thumb_orig_url) ) ? $thumb_orig_url : "";// && $new_thumb  $thumb_orig_url
        $out[$position]->UPC                    = html_entity_decode( get_post_meta( $alternate, 'UPC', true ), ENT_COMPAT, 'UTF-8' );

        $line_string = json_encode( $out[$position], true );    $line_array  = json_decode( $line_string, true );

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
        global $wp_filesystem;

        $this->count = $advance_count + absint($add); 

        if ( $advance_count % OCM_PER_FILE === 0 ) {

            $this->count++;

            $file_name = $this->file_name();

            ob_end_flush();

            if (is_resource( $this->the_file ) ) {
                     fclose( $this->the_file ) ;
            }

            if ( is_file( $file_name ) ) {
                  unlink( $file_name ) ;
            }

            $this->the_file = fopen($file_name, "w" );

            ob_start();

            fputcsv($this->the_file, explode(',', OCM_COL_KEYS ) );
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
        global $wp_filesystem;

        ob_start();

        $position    = 0;
        $rangeStart  = $this->job['start'] - 1;
        $args        = array( 'post_type' => 'product', 'post_status' => array( 'publish' ), 'posts_per_page' => $this->job['end'], 'numberposts' => $this->job['end'], 'orderby' => 'title', 'order' => 'asc', 'offset' => $rangeStart, 'fields' => 'ids');
        $product_ids = get_posts( $args );

        $file_name   = $this->file_name();

        if ( ! is_resource( $this->the_file ) ) $this->the_file = fopen( $file_name, "a");

        for ( $index = 0; $index < $this->job['end']; $index++ ) 
        { 
            $this->count = $this->advance( $this->count, false );
            $product_id  = $product_ids[ $index ];

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

         // fix //////////////////////// fclose
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
                ob_end_flush();
                fclose( $this->the_file );

                $this->archive_zip();

                // $this->job = $this->write_job_file( array( 'work' => $this->job['work'], 'start' => 1, 'end' => 500, 'count' => $this->count, 'refresh' => false, 'processing' => false ) );         
                // break;
            }
        }
        ob_end_flush();
        if ( is_resource( $this->the_file ) ) {
            fclose( $this->the_file );
        }
        return $this->job;
    }

    /**
     * Main Output_Chain_Reporter Instance
     *
     * Ensures only one instance of Output_Chain_Reporter is loaded or can be loaded.
     *
     * @since 1.0.0
     * @static
     * @see Output_Chiain_Reporter()
     * @return Main Output_Chain_Reporter instance
     */
    public static function instance ( $parent ) {
        if ( is_null( self::$_instance ) ) {
            self::$_instance = new self( $parent );
        }
        return self::$_instance;
    } // End instance()

    /**
     * Cloning is forbidden.
     *
     * @since 1.0.0
     */
    public function __clone () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
    } // End __clone()

    /**
     * Unserializing instances of this class is forbidden.
     *
     * @since 1.0.0
     */
    public function __wakeup () {
        _doing_it_wrong( __FUNCTION__, __( 'Cheatin&#8217; huh?' ), $this->parent->_version );
    } // End __wakeup()
}

?>