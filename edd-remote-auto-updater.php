<?php

class edd_remote_auto_updater
{
	var $settings;
	
	function __construct( $options )
	{
		$this->settings = $options;
		add_filter('pre_set_site_transient_update_plugins', array($this, 'check_update'));
		add_filter('plugins_api', array($this, 'check_info'), 10, 3);
	}

	function get_remote()
	{
		 // vars
        $info = false;

		// Get the remote info
		$ping_url = trailingslashit($this->settings['remote']) . "?edd_au_check_plugin=true";
		$ping_url .= "&email=" . urlencode( $this->settings['email'] );
		$ping_url .= "&version=" . urlencode( $this->settings['version'] );
		$ping_url .= "&slug=" . urlencode( $this->settings['slug'] );

        $request = wp_remote_post( $ping_url );
        if( !is_wp_error($request) || wp_remote_retrieve_response_code($request) === 200)
        {
            $info = @unserialize($request['body']);
            if($info)
            {
            	$info->slug = $this->settings['slug'];
            }
        }
        
        
        return $info;
	}
	
	function check_update( $transient )
	{
	    if( empty($transient->checked) ) return $transient;
        
        $info = $this->get_remote();
        if( !$info ) return $transient;
        if( version_compare($info->version, $this->settings['version'], '<=') ) return $transient;
        
        $obj = new stdClass();
        $obj->slug 				= $info->slug;
        $obj->new_version 		= $info->version;
        $obj->url 				= $info->homepage;
        $obj->package 			= $info->download_link;
        $transient->response[ $this->settings['basename'] ] = $obj;

        return $transient;
	}
	
    function check_info( $false, $action, $arg )
    {
    	if( !isset($arg->slug) || $arg->slug != $this->settings['slug'] ) return $false;
    	if( $action == 'plugin_information' ) $false = $this->get_remote();     
        return $false;
    }
}

