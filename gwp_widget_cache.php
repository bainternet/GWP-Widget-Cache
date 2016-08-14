<?php
/*
Plugin Name: GWP Widget Cache (Cached!)
Plugin URI:
Description: A plugin to cache WordPress Widgets using the Transients API, based on this tutorial http://generatewp.com/?p=10132
Version: 1.1
Author: Ohad Raz
Author URI: http://generatewp.com
/**
* GWP_Widget_cache
*/
class GWP_Widget_cache{
    /**
     * $cache_time
     * transient exiration time
     * @var int
     */
    public $cache_time = 43200; // 12 hours in seconds
     
    /**
     * __construct
     *
     * Class constructor where we will call our filter and action hooks.
     */
    function __construct(){
        add_filter( 'widget_display_callback', array($this,'_cache_widget_output'), 10, 3 );
        add_action('in_widget_form', array($this,'in_widget_form'),5,3);
        add_filter('widget_update_callback', array($this,'widget_update_callback'),5,3);
    }
     
    /**
     * get_widget_key
     *
     * Simple function to generate a unique id for the widget transient
     * based on the widget's instance and arguments
     *
     * @param  array $i widget instance
     * @param  array $a widget arguments
     * @return string md5 hash
     */
    function get_widget_key($i,$a){
        return 'WC-' . md5( serialize( array( $i, $a ) ) );
    }
 
    /**
     * _cache_widget_output
     * @param array     $instance The current widget instance's settings.
     * @param WP_Widget $widget     The current widget instance.
     * @param array     $args     An array of default widget arguments.
     * @return mixed array|boolean
     */
    function _cache_widget_output($instance, $widget, $args){
        if ( false === $instance )
            return $instance;
        
        //skip cache on preview
        if ( $widget->is_preview() ){
            return $instance;
        }
        //check if we need to cache this widget?
        if(isset($instance['wc_cache']) && $instance['wc_cache'] == true)
            return $instance;
 
        //simple timer to clock the widget rendring
        $timer_start = microtime(true);
         
        //create a uniqe transient ID for this widget instance
        $transient_name = $this->get_widget_key($instance,$args);
 
        //get the "cached version of the widget"
        if ( false === ( $cached_widget = get_transient( $transient_name ) ) ){
            // It wasn't there, so render the widget and save it as a transient
            // start a buffer to capture the widget output
            ob_start();
            //this renders the widget
            $widget->widget( $args, $instance );
            //get rendered widget from buffer
            $cached_widget = ob_get_clean();
            //save/cache the widget output as a transient
            set_transient( $transient_name, $cached_widget, $this->cache_time);
        }
 
        //output the widget
        echo $cached_widget;
        //output rendering time as an html comment
        echo '<!-- From widget cache in '.number_format( microtime(true) - $timer_start, 5 ).' seconds -->';
 
        //after the widget was rendered and printed we return false to short-circuit the normal display of the widget
        return false;  
    }
 
    /**
     * in_widget_form
     * this method displays a checkbox in the widget panel
     *
     * @param WP_Widget $t     The widget instance, passed by reference.
     * @param null      $return   Return null if new fields are added.
     * @param array     $instance An array of the widget's settings.
     *
     */
    function in_widget_form($t,$return,$instance){
        $instance = wp_parse_args(
            (array) $instance,
            array(
                'title' => '',
                'text' => '',
                'wc_cache' => null
            )
        );
 
        if ( !isset($instance['wc_cache']) )
            $instance['wc_cache'] = null;
        ?>
        <p>
            <input id="<?php echo $t->get_field_id('wc_cache'); ?>" name="<?php echo $t->get_field_name('wc_cache'); ?>" type="checkbox" <?php checked(isset($instance['wc_cache']) ? $instance['wc_cache'] : 0); ?> />
            <label for="<?php echo $t->get_field_id('wc_cache'); ?>"><?php _e('don\'t cache this widget?'); ?></label>
        </p>
        <?php
    }
 
    /**
     * widget_update_callback
     * @param array     $instance     The current widget instance's settings.
     * @param array     $new_instance Array of new widget settings.
     * @param array     $old_instance Array of old widget settings.
     * @return array    $instance
     */
    function widget_update_callback($instance, $new_instance, $old_instance){
        //save the checkbox if its set
        $instance['wc_cache'] = isset($new_instance['wc_cache']);
        return $instance;
    }
}//end GWP_Widget_cache class
 
 
//initiate the class
add_action( 'plugins_loaded', 'GWP_Widget_cache_init' );
function GWP_Widget_cache_init() {
    $GLOBALS['GWP_Widget_cache'] = new GWP_Widget_cache();
}
