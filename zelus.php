<?php
/*
  Plugin Name: Zelus
  Plugin URI: http://clarknikdelpowell.com
  Description: Lets you enforce maximum image dimensions and resize existing images in batch.
  Author: Samuel Mello
  Author URI: http://clarknikdelpowell.com/agency/people/sam/
  Version: 1.1


  Copyright 2013+ Clark/Nikdel/Powell (email : sam@clarknikdelpowell.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2 (or later),
  as published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/



/*
    DECLARE FILTER AND ACTION HOOKS
*/
add_filter('wp_handle_upload','zelus_enforce_limits');
add_action('admin_init','zelus_reg_opts');
add_action('admin_footer','zelus_ajax');
add_action('wp_ajax_zelus_batch','zelus_batch_callback');


/*
    REGISTERS SETTINGS AND FIELDS WITH WORDPRESS
*/
function zelus_reg_opts() {
  add_settings_field('zelus_opts','Maximum size','zelus_show_opts','media');
  register_setting('media','zelus_mwidth','intval');
  register_setting('media','zelus_mheight','intval');
}



/*
    DECIDES IF UPLOAD NEEDS TO BE FILTERED
*/
function zelus_decide_init($file) {
  // check file path
  $file = zelus_get_path($file);
  $exc = zelus_check_dims($file);
  // only proceed if image
  if ($exc==true) $file = zelus_enforce_limits($file);
  // send file info back
  return $file;
}



/*
    DETECT IF FILE EXISTS AND MODIFY PATH IF NEEDED
*/
function zelus_get_path($file) {
  // if file doesn't exist, append uploads base dir path
  if (!is_file($file['file'])) {
    // get upload dir info from WP
    $updir = wp_upload_dir();
    $basedir = $updir['basedir'];
    // adds trailing folder slash if needed
    if (substr($basedir,(strlen($basedir)-1))!='/')
      $basedir .= '/';
    // sets the new path with base dir + the file path
    $newpath = $basedir.$file['file'];
    // if the file exists, update the file info
    if (is_file($newpath)) $file['file'] = $newpath;
  }
  return $file;
}



/*
    CHECKS DIMENSIONS AGAINST SETTINGS
*/
function zelus_check_dims($file) {
  // get settings
  $mw = get_option('zelus_mwidth');
  $mh = get_option('zelus_mheight');
  // check dimensions against settings
  if ($file['width'] > $mw || $file['height'] > $mh) return true;
  else return false;
}



/*
    GETS UPLOADED FILE AND RESIZES IF IMAGE
*/
function zelus_enforce_limits($file){
  // get max width and heigth from settings
  $mw = get_option('zelus_mwidth');
  $mh = get_option('zelus_mheight');
  // load image into wordpress image resizer
  $image = wp_get_image_editor($file['file']);
  // only continue if no error
  if (!is_wp_error($image)) {
    // resize using WP's built-in editor
    $image->resize($mw,$mh);
    // save over original file
    $image->save($file['file']);
    // get new file size
    $dim = getimagesize($file['file']);
    // modify return info
    $file['width'] = $dim[0];
    $file['height'] = $dim[1];
  }
  return $file;
}



/*
    AJAX RESPONSE PROCESSING
*/
function zelus_batch_callback() {
  // set time limit for long batches
  set_time_limit(300);
  // set args to get images from posts
  $args = array('post_type' => 'attachment' ,'posts_per_page' => '-1');
  // get all images
  $posts = get_posts($args);
  // if there are any images to process
  if ($posts) {
    // begin counter
    $i=0;
    // for each image found
    foreach ($posts as $post) {
      // if this is indeed an image post
      if (wp_attachment_is_image($post->ID)) {
        // get meta data for image
        $image = wp_get_attachment_metadata($post->ID,true);
        // run enforcement policy on image
        $process = zelus_decide_init($image);
        // if process was successful
        if ($process) {
          // modify meta data with new dimensions
          $image['width'] = $process['width'];
          $image['height'] = $process['height'];
          // submit changes
          wp_update_attachment_metadata($post->ID,$image);
          // incriment success
          $i++;
        }
      }
    }
    // announce completions
    echo $i.' image(s) processed.';
  }
  else echo 'No images to process.';
  // die (required for WP ajax)
  die();
}



/*
    AJAX FOR BUTTON CLICK
*/
function zelus_ajax() {
?>
<script type="text/javascript" >
jQuery(document).ready(function($) {
  $('#batchConvert').on('click',function(){
    var confirmation = confirm("Are you sure you want to resize all existing images? This could take several moments.\n\n(Images with dimensions less than the maximum will be skipped)")
    if (confirmation==true) {
      var data = { action: 'zelus_batch', confirm: true };
      $('#batchConvert').prop('disabled',true)
      $('#batchLoad').css('display','inline');
      $.ajaxSetup({ timeout: 300000 });
      $.post(ajaxurl, data, function(response) { 
        $('#batchResponse').html(response);
        $('#batchLoad').css('display','none');
        $('#batchConvert').prop('disabled',false)
      });
    }
  });
});
</script>
<?php
}



/*
    SHOWS OPTIONS ON SETTINGS PAGE
*/
function zelus_show_opts() {
?>
  <label for="zelus_mwidth">Width</lable>
  <input type="number" step="1" min="0" name="zelus_mwidth" id="zelus_mwidth" value="<?=get_option('zelus_mwidth') ?>" class="small-text" />
  <label for="zelus_mheight">Height</label>
  <input type="number" step="1" min="0" name="zelus_mheight" id="zelus_mheight" value="<?=get_option('zelus_mheight') ?>" class="small-text" />
  <input type="button" name="batchConvert" id="batchConvert" value="Resize All Existing Images" class="button button-primary" style="margin-top:-2px;margin-left:5px;margin-right:5px;"/>
  <div id="batchLoad" style="display:none"><img src="<?php echo includes_url() ?>images/spinner.gif" /></div>
  <div id="batchResponse" style="display:inline"></div>
<?php
}
?>