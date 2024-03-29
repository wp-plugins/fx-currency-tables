<?php
/*
Plugin Name: FX-Currency cross table
Plugin URI: http://www.fx-foreignexchange.com/wordpress-currency-table-plugin/2009/07/03/
Description: FX-ForeignExchange 6 currency cross table plugin for Wordpress. This easy to use tool adds a horizontal 6 currency table to posts and pages, and the widget adds a 3 column portrait table to sidebars. The 6 currencies can be selected by the user from a list of 3 over 180 worldwide. The rates are based on a 12 minute delay feed and are live ECB interbank rates. An ideal tool for forex, currency trading and commodities sites and a very attractive addition to any e-commerce site where buyers are likely to originate across more than one currency zone.
Author: Andy Stevenson
Author URI: http://www.fx-foreignexchange.com
Version: 0.2.0
*/

error_reporting(0);
//#################
// Stop direct call
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('...'); }
//#################  

define('CURRENCY_TABLE_FOLDER', plugin_basename( dirname(__FILE__)) );

define('CURRENCY_TABLE_JSON_URL', 'http://gatehouseinternational.com/wp-content/plugins/fx-currency-tables_server/currency-table_feed.php' );
define('CURRENCY_TABLE_FREQ', 12 );

//action to have WordPress load the widget
add_action('widgets_init', 'widget_currency_table_init');
//action to add styles to the header
add_action("wp_head", "fx_currency_table_styles");
add_action("admin_head", "fx_currency_table_styles");

$fx_currency_table_styles=false;

//action to allow shortcode
add_shortcode('currency_table', 'fx_currency_table_shortcode');

//currencytable api key
global $ct_api_key;
$ct_api_key = get_option("currency_table_api");

/**
 * shows options page
 */
function currency_table_main()
{
  global $ct_api_key;
    trigger_currency_table_actions();


  if ($ct_api_key=="")    
    currency_table_sign();
  else  
   currency_table_options_page();
}
function trigger_currency_table_actions()
{
  switch ($_POST["action"])
  {
    case "currency_table_api_sign":
      global $ct_api_key;

      $api=$_POST["currency_table_api"];
      
      //check if correct api            
      if (get_json(CURRENCY_TABLE_JSON_URL."?action=api_check&api=".$api)=="OK") $ct_api_key=$api;
      else  { $ct_api_key=""; echo "<p class='error'>Incorrect Currency Table API Key</p>";}
      //update CT api key
      update_option("currency_table_api",$ct_api_key);
    break;
    case "currency_table_api_clear":
      global $ct_api_key;
      $ct_api_key="";
      //update CT api key
      update_option("currency_table_api",$ct_api_key);      
    break;
  }  
}
function currency_table_sign()
{
?>
  <div class="wrap">
  <h2>Currency Table</h2>
  
  <form action="options-general.php?page=currency-table" method="post">
    Insert your Currency Tables Plugin API key here:<br />
    <div style="margin-left:20px;">
      <input type="text" name="currency_table_api" value=""/>
      
    <!--p class="submit"-->
    
      <input name="currency_table_save" class="button-primary" value="<?php _e('Save Changes'); ?>" type="submit"/>
      <input type="hidden" name="action" value="currency_table_api_sign"  />
     </div> 
    <!--/p-->
  </form>
  
  <p>Don't have a Currency Table API Key? <a href="http://www.gatehouseintl.com/wordpress-plugin-currency-table-api-sign-up/" target="_blank">Get one here</a></p>
  
  <div class="ct_description">
  <p class="ct_bold">Why we ask you for an API key?</p>

  <p><b>Currency Table Plugin</b> operates by installing a file on your web hosting which calls a feed on our server to provide you with live currency rates. We ask you to sign up for an API key in order that we can make clear the <a href="http://www.gatehouseintl.com/wordpress-plugin-currency-table-api-sign-up/" target="_blank">terms of use</a> of the file we provide you that connects to our servers.</p> 
</div>
<?

}

function currency_table_options_page()
{	
  global $wpdb, $ct_api_key;
  if (!current_user_can('manage_options'))
    wp_die(__('Sorry, but you have no permissions to change settings.'));

  $table_name = $wpdb->prefix . 'currency_table';
  $query = 'UPDATE `' . $table_name . '` SET `old_cur1` = `cur1`';

  $wpdb->query($query);

  // save data
  if ( isset($_POST['currency_table_save']) ) {

    $query = 'UPDATE `' . $table_name . '` SET `cur1` = \'\'';
    $wpdb->query($query);

    for ($i = 1; $i - 7 < 0; $i++) {
      $name = 'CLS' . $i;
      if (strlen($_POST[$name]) == 0) {
        break;
      }
      $cur = $_POST[$name];

      if (strlen($cur) - 3 == 0) {
        $query = 'UPDATE `' . $table_name . '` SET `cur1` = \'' . $cur . '\' WHERE `id` = ' . $i;
        $wpdb->query($query);
      }
    }

    //
    // Delete some old rartes
    $query = 'UPDATE `' . $table_name . '` SET old_rate = 0 WHERE old_cur1 <> cur1';
    $wpdb->query($query);
  }
  else {
  //
  // Check cron. May be it failed.
  //
  
  currency_table_check_schedule(1);

  }


  // show page
  
  ?>

<div class="wrap">
  <h2>Currency Table</h2>
  <form action="options-general.php?page=currency-table" method="post">
      Clear API key
      <input name="currency_table_save" class="button-primary" value="<?php _e('Submit'); ?>" type="submit" />
      <input type="hidden" name="action" value="currency_table_api_clear" />
  </form>
  <form action="options-general.php?page=currency-table" method="post">
  <input type="hidden" value="currency-table-submit" value="1" />
  <?php

$url=CURRENCY_TABLE_JSON_URL."?action=get_feed&api=".$ct_api_key;
$myjson = get_json($url);

$query = 'SELECT `cur1`, `old_cur1`, `old_rate`, `new_rate`, `cron_update` FROM `' . $table_name . '` ORDER BY id';

//print $query; exit;

$curs = $wpdb->get_results($query);

$currencies_selected_list    = array();
$currencies_rates_list       = array();
$currencies_old_rates_list   = array();
$currencies_cron_update_list = array();

if ($curs) {
  foreach($curs as $cur) {

    $currencies_selected_list[]    = $cur->cur1;
    $currencies_rates_list[]       = $cur->new_rate;
    $currencies_old_rates_list[]   = $cur->old_rate;
    $currencies_cron_update_list[] = $cur->cron_update;
  }
}

if (preg_match_all("/(\"([A-Z]{3})\"\:([0-9\.]+))/", $myjson, $matches)) {
  $currencies = $matches[2];
  $rates = $matches[3];

  for ($j = 0; $j - count($currencies) < 0; $j++) {

    $val = $currencies[$j];
    $rate = $rates[$j];

    for ($i = 1; $i - 7 < 0; $i++) {

      if ($currencies_selected_list[$i - 1] == $val) {
        $currencies_rates_list[$i - 1] = $rate;
        $query = 'UPDATE `' . $table_name . '` SET `new_rate` = \'' . $rate . '\' WHERE `id` = ' . $i;
        $wpdb->query($query);
      }
    }
  }


  // 0000-00-00 00:00:00
  ?>

  Please, select up to 6 currencies<br/><br/>

  <table border="0">
  <tr>
  <td><b>Currency</b></td>
  <td style="width: 150px"><b>Rate<b></td>
  <td style="width: 150px"><b>Old Rate<b></td>
  <td style="width: 150px"><b>Rate updated<b></td>
  </tr>
  <?
  for ($i=0;$i<7;$i++)
  {
  ?>
  <tr>
  <td>
  <select name="CLS<?=$i+1;?>">
    <option value="">Not selected</option>
  <?php 
    foreach ($currencies as $val) {

    echo '<option vlaue="' . $val . '"';
    
    if ($currencies_selected_list[$i] == $val) {

      echo ' selected="selected"';
    }
    
    echo '>' . $val . '</option>' . "\n";
  }
  ?>
  </select>
  </td>
  <td>
  &nbsp;<?print ($currencies_selected_list[$i]=="")?0:$currencies_rates_list[$i]; ?>
  </td>
  <td>
  &nbsp;<?print ($currencies_selected_list[$i]=="")?0:$currencies_old_rates_list[$i]; ?>
  </td>
  <td>
  &nbsp;<? if (( $currencies_cron_update_list[$i] == '0000-00-00 00:00:00' )||($currencies_selected_list[$i]=="")) { print 'never'; } else { print $currencies_cron_update_list[$i]; } ?>
  </td>
  </tr>
  <?
  }

  ?>
  </table>
  <br/>
  <table border="0">
  <tr><td align="right">Current date and time: </td><td><?php print date("F j, Y, g:i:s a"); ?></td></tr>
  <tr><td align="right">Next update: </td><td><?php print date("F j, Y, g:i:s a", wp_next_scheduled ('currency_table_cron_event')); ?></td></tr>
  </table>
<?php if (1) { ?>
<style>

.dataSmall {
	font-size: 11px;
}

.currencyDataTable {
	font-size: 12px;
}

.currencyDataTable th {
	background-color: #BFD6E0;
	font-weight: normal;
	font-size: 11px;
	padding-left: 4px;
	padding-right: 4px;
	vertical-align: bottom;
}

.currencyDataTable tr:hover {
	background-color: #FC6;
}

.currencyDataTable td {
	padding-left: 3px;
	padding-right: 3px;
	vertical-align: top;
	text-align: left;
}

.currencyDataTable .currencyData {
	text-align: right;
}

.currencyDataTable tr {
	background-color: #FFF;
}

.currencyDataTable .currencyStripe {
	background-color: #F3F3F3;
}

.currencyDataTable tr.currencyDataSlick td {
	border-bottom: 1px solid #EEE;
}

.currencyDataTable tr.currencyDataSlick th {
	background-color: #FFF;
	border-bottom: 1px solid #EEE;
}

.currencyDataTable th.subHeader {
	background-color: #FFF;
	padding: 0px;
	padding-top: 10px;
}

/* widget */

.changeUp {
	color: #090;
}

.changeDown {
	color: #A00;
}

.module {
	margin-top: 5px;
	overflow: hidden;
	width: auto;
}

</style>
<?php } ?>

  <?php print currency_table_show(); ?>
  
    <p class="submit">
      <input name="currency_table_save" class="button-primary" value="<?php _e('Save Changes'); ?>" type="submit" />
    </p>
    </form>
</div>

<?php
  }
}


function currency_table_show() {
  //try to load css
  fx_currency_table_styles();
  global $wpdb;

  $table_name = $wpdb->prefix . 'currency_table';

  $query = 'SELECT `cur1`, `old_cur1`, `old_rate`, `new_rate`, `cron_update` FROM `' . $table_name . '` ORDER BY `id`';

  //print $query; exit;

  $curs = $wpdb->get_results($query);

  $currencies_selected_list    = array();
  $currencies_rates_list       = array();
  $currencies_old_rates_list   = array();
  $currencies_cron_update_list = array();

  if ($curs) {
    foreach($curs as $cur) {

      $currencies_selected_list[]    = $cur->cur1;
      $currencies_rates_list[]       = $cur->new_rate;
      $currencies_old_rates_list[]   = $cur->old_rate;
      $currencies_cron_update_list[] = $cur->cron_update;
    }
  }


  $s = '';

  for ($i = 0; $i - 6 < 0; $i++) {
    $s .= $currencies_selected_list[$i];
  }

  if ($s == '') {
    // Do nothing
  }
  else {
    $s = '';
    $s .= '<br/><br/>' . "\n";
    $s .= '<h3>Currencies - Cross Rates</h3>' . "\n";
    $s .= '<table cellspacing="0" cellpadding="0" style="width: 100%" class="currencyDataTable currencyDataTableMD">';
    for ($i = 0; $i - 6 < 0; $i++) {
      if ($currencies_selected_list[$i]=="") continue;
      
      if ($i == 0) {
        $s .= '<thead><tr><th style="width: 25px;">&nbsp;</th>';
        for ($j = 0; $j - 6 < 0; $j++) {
          if ($currencies_selected_list[$j]=="") continue;
          $s .= '<th align="right"><b>' . $currencies_selected_list[$j] . '</b></th>';
        }
        $s .= '</tr></thead>' . "\n";
        $s .= '<tbody class="currencyDataSmall">';
      }

      if ($i % 2 == 0) {

        $s .= '<tr class="currencyStripe">';
      }
      else {

        $s .= '<tr>';
      }

      for ($j = 0; $j - 6 < 0; $j++) {
        if ($currencies_selected_list[$j]=="") continue;

        if ($j == 0) {
          $s .= '<td class="currencyDataBold">' . $currencies_selected_list[$i] . '</td>';
        }

        if ($i == $j) {
          $s .= '<td class="currencyData"><b>1</b></td>';
        }
        else {
          $rate = '&nbsp;';
          if ($currencies_rates_list[$i] == 0) {
          }
          else {
            $rate = round($currencies_rates_list[$j] / $currencies_rates_list[$i], 5);
          }
          $s .= '<td class="currencyData">' . $rate . '</td>';
        }
      }
      $s .= '</tr>' . "\n";
    }

    $s .= '</tbody>';
    $s .= '</table>';
  }

  return $s;

} // end of function;

function currency_table_check_schedule($run) {

  global $wpdb;

  $table_name = $wpdb->prefix . 'currency_table';

  $query = 'SELECT UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(MIN(`cron_update`)) FROM `' . $table_name . '`';

  $tmp = $wpdb->get_var($query);

  $currency_table_check_interval = (int)get_option('currency_table_check_interval');

  if ($currency_table_check_interval - 1 < 0) 
    $currency_table_check_interval = CURRENCY_TABLE_FREQ;

  $tmp2 = ($currency_table_check_interval + 3)*60;

  if ($tmp - $tmp2 > 0) {
  
    //
    // Run now. Schedule again.
    //
    if ($run) {
      currency_table_cron_run();
    }
    else {
      currency_table_cron();
    }

  }
}

/**
 * adds admin menu
 */
function currency_table_add_options_page()
{
  add_options_page('Currency Table', 'Currency Table', 9, 'currency-table', 'currency_table_main');
}

add_action('admin_menu', 'currency_table_add_options_page');
//register hook for cron functions
add_action('currency_table_cron_event','currency_table_cron');


//plugin activation
function currency_table_install()
{
  global $wpdb, $wp_version;
	
  // Check for capability
  if ( !current_user_can('activate_plugins') ) 
    return;

  // upgrade function changed in WordPress 2.3	
  if (version_compare($wp_version, '2.3', '>='))		
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
  else
    require_once(ABSPATH . 'wp-admin/upgrade-functions.php');
	
  // add charset & collate like wp core
  //$charset_collate = '';
  //
  //if ( version_compare(mysql_get_server_info(), '4.1.0', '>=') ) {
  //  if ( ! empty($wpdb->charset) )
  //    $charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
  //  if ( ! empty($wpdb->collate) )
  //    $charset_collate .= " COLLATE $wpdb->collate";
  //}

  $table_name = $wpdb->prefix . 'currency_table';

  if($wpdb->get_var('show tables like \'$table_name\'') != $table_name) {
      
    $sql = 'CREATE TABLE ' . $table_name . ' (
      `id` tinyint(4) NOT NULL ,
      `cur1` varchar(3) NOT NULL default \'\',
      `old_cur1` varchar(3) NOT NULL default \'\',
      `old_rate` varchar(20) NOT NULL default \'0\',
      `new_rate` varchar(20) NOT NULL default \'0\',
      `cron_update` DATETIME NOT NULL,
      PRIMARY KEY `id` (`id`)
      )'; //  ' . $charset_collate . ';

    //print $sql; exit;

    dbDelta($sql);

    //
    // Insert dummy data
    //
    $sql = 'INSERT INTO `' . $table_name . '` (`id`) VALUES (1), (2), (3), (4), (5), (6);';

    $wpdb->query($sql);
  }

  if(!get_option('currency_table_check_interval'))
    add_option('currency_table_check_interval', CURRENCY_TABLE_FREQ); // every 12 minutes

  $options['currency_table_title'] = 'Currency Table';

  if(!get_option('widget_currency_table'))
    add_option('widget_currency_table', $options);
  currency_table_reschedule();  
}

// re-read rates
//
function currency_table_cron()
{
  currency_table_reschedule();
  currency_table_cron_run();
}

function currency_table_cron_run() {
  global $wpdb;
  //
  // Read JSON and update table
  //
  $table_name = $wpdb->prefix . 'currency_table';
  $myjson = get_json(CURRENCY_TABLE_JSON_URL);

  $query = 'SELECT `cur1` FROM `' . $table_name . '` ORDER BY `id`';

  $curs = $wpdb->get_results($query);

  if ($curs) {
    foreach($curs as $cur) {

      if (preg_match_all("/(\"" . $cur->cur1 . "\"\:([0-9\.]+))/", $myjson, $matches)) {

        $rates = $matches[2];
        
        $query = 'SELECT  `new_rate` FROM `' . $table_name . '` where `cur1` = \'' . $cur->cur1 . '\'';
        $tmp = $wpdb->get_var($query);

        //update if new rate not the same
        $query = 'UPDATE `' . $table_name . '` ' .
        'SET `old_rate` = `new_rate` ' .
        'WHERE `new_rate` <> \'' . $rates[0] . '\' and `cur1` = \'' . $cur->cur1 . '\'';
        $wpdb->query($query);

        $query = 'UPDATE `' . $table_name . '` ' .
        'SET `new_rate` = \'' . $rates[0] . '\', `cron_update` = NOW() ' .
        'WHERE `new_rate` <> \'' . $rates[0] . '\' and `cur1` = \'' . $cur->cur1 . '\'';

        $wpdb->query($query);
      }
    }
  }
}


//reschedule currency_table
//
function currency_table_reschedule()
{
  $currency_table_check_interval = (int)get_option('currency_table_check_interval');

  if ($currency_table_check_interval - 1 < 0) 
    $currency_table_check_interval = CURRENCY_TABLE_FREQ;

  $next_run = time() + $currency_table_check_interval*60;

  wp_clear_scheduled_hook('currency_table_cron_event');
  wp_schedule_single_event($next_run, 'currency_table_cron_event');
}

//plugin deactivation
//
function currency_table_deactivate()
{
  currency_table_uninstall();
  wp_clear_scheduled_hook('currency_table_cron_event');
}
/**
 * clean up when uninstall
 */
function currency_table_uninstall()
{
  global $wpdb;

  //
  // Drop table
  //
  $table_name = $wpdb->prefix . 'currency_table';

  $sql = 'DROP table `' . $table_name . '`';

  $wpdb->query($sql);

  //remove the widget_settings
  delete_option('widget_currency_table');
  delete_option("currency_table_api");
}

// since WordPress 2.7
if ( function_exists('register_uninstall_hook') )
  register_uninstall_hook(__FILE__, 'currency_table_uninstall'); 

//register activate/deactivate hooks
//
register_activation_hook(CURRENCY_TABLE_FOLDER.'/currency-table.php','currency_table_install');
register_deactivation_hook(CURRENCY_TABLE_FOLDER.'/currency-table.php','currency_table_deactivate');

//function to display friends using shortcode
function fx_currency_table_shortcode($atts) {
  //try to load css
	fx_currency_table_styles();
  //
  // Check cron. May be it failed.
  //
  currency_table_check_schedule(0);

  //use wpdb
  global $wpdb;

  $currency_table_output = currency_table_show();

  $myHTML  = '';
  $myHTML .= '<table width="100%" cellspacing="1" cellpadding="3" border="0">' . "\n";

  $myHTML .= '<tr>' . "\n";

  /*
  $myHTML .= '<td align="left" width="119px">' . "\n";

  $myHTML .= '<a href="http://www.gatehouseintl.com/wordpress-plugin-currency-converter/">';

  $myHTML .= '<img src="' . WP_PLUGIN_URL . '/fx-currency-tables/images/get_widget2.gif" width="90" height="23" alt="Get Widget" /></a>' . "\n";

  $myHTML .= '</td>' . "\n";
  */

  $myHTML .= '<td align="right">' . "\n";

  //$myHTML .= '<a href="http://www.gatehouseintl.com/wordpress-plugin-currency-converter/" style="font-size: 10px;">';

  $myHTML .= '<a href="http://www.fx-foreignexchange.com/currency_widget.php?value=1&from=EUR&to=GBP&r=813" rel="nofollow" onClick="window.name=\'exchange_rates_todayNew\';window.open(this.href,\'converter\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,width=660,height=880,resizable=yes,scrollbars=yes\');return false;" style="font-size: 10px;">';
  
  $myHTML .= 'Other&nbsp;Currencies'; // Other Currencies

  $myHTML .= '</a>' . "\n";

  $myHTML .= '</td>' . "\n";

  $myHTML .= '</tr>' . "\n";

  $myHTML .= '</table>' . "\n";

  $currency_table_output .= "\n" . $myHTML;
	
  //return the finished table to the shortcode macro handler
  return $currency_table_output;	
}

//wrap widget functions in an init
function widget_currency_table_init() {
  //check that WP can use widgets
  if (!function_exists('register_sidebar_widget')) {
    return;
  } //close if

  function widget_currency_table_display($pmcArgs) {
    //try to load css
    fx_currency_table_styles();
    //
    // Check cron. May be it failed.
    //
    currency_table_check_schedule(0);

    //use wpdb
    global $wpdb;

    //extract the Widget display settings
    extract($pmcArgs);

    //get the widget options
    $currency_table_options = get_option('widget_currency_table');
    $currency_table_title = $currency_table_options['currency_table_title'];


    //start building the widget output
    echo $before_widget . $before_title;

    echo $currency_table_title;

    //close the widget title
    echo $after_title;

    $myHTML = '';

    //$myHTML .= 'widget currency table';

    //$myHTML .= '<div id="currFlatRates">' . "\n";
    //$myHTML .= '<div class="NONE">' . "\n";

    //$myHTML .= '<div class="module">' . "\n";
    //$myHTML .= '<div>' . "\n";
    $myHTML .= '<table width="100%" cellspacing="1" cellpadding="1" border="0" class="currencyDataTable">' . "\n";

    $myHTML .= '<tbody><tr>' . "\n";
    $myHTML .= '<th width="80">Currency</th>' . "\n";
    $myHTML .= '<th width="100" class="data">Last</th>' . "\n";
    $myHTML .= '<th class="data">%&nbsp;Change</th>' . "\n";

    $myHTML .= '</tr>' . "\n";

    $table_name = $wpdb->prefix . 'currency_table';

    $query = 'SELECT `cur1`, `old_cur1`, `old_rate`, `new_rate`, `cron_update` FROM `' . $table_name . '` ORDER BY `id`';

    //print $query; exit;

    $curs = $wpdb->get_results($query);

    $currencies_selected_list    = array();
    $currencies_rates_list       = array();
    $currencies_old_rates_list   = array();
    $currencies_cron_update_list = array();

    if ($curs) {
      foreach($curs as $cur) {
        $currencies_selected_list[]    = $cur->cur1;
        $currencies_rates_list[]       = $cur->new_rate;
        $currencies_old_rates_list[]   = $cur->old_rate;
        $currencies_cron_update_list[] = $cur->cron_update;
      }
    }

    $k++;

    for ($i = 0; $i - 6 < 0; $i++) {
      if ($currencies_selected_list[$i]=="") continue;
      for ($j = 1; $j - 6 < 0; $j++) {

        if ($i == $j) {

          // skip
        }
        else {

          $cur1 = $currencies_selected_list[$i];
          $cur2 = $currencies_selected_list[$j];
          if ($currencies_selected_list[$j]=="")  continue;
          $old_rate1 = 0.0 + $currencies_old_rates_list[$i];
          $old_rate2 = 0.0 + $currencies_old_rates_list[$j];

          $new_rate1 = 0.0 + $currencies_rates_list[$i];
          $new_rate2 = 0.0 + $currencies_rates_list[$j];

          if ($old_rate1 == 0) {

            $old_rate1 = $new_rate1;
          }
          if ($old_rate2 == 0) {

            $old_rate2 = $new_rate2;
          }

          if (($old_rate1 > 0) && ($old_rate2 > 0) && ($new_rate1 > 0) && ($new_rate2 > 0)) {

            if ($k % 2 == 0) {

              $myHTML .= '<tr class="currencyStripe">' . "\n";
            }
            else {

              $myHTML .= '<tr>' . "\n";
            }

            $k++; // row color changer

            $myHTML .= '<td>' . $cur1 . '/' . $cur2 . '</td>' . "\n";
            

            $old_rate = $old_rate1 / $old_rate2;
            $new_rate = $new_rate1 / $new_rate2;

            $changeCSS       = '';
            $changeImage     = '';
            $changeSign      = '';
            $display_percent = '';

            if ($old_rate == $new_rate) {

              $display_percent = '0%';
            }
            else {

              $display_percent = (100 * abs($old_rate - $new_rate)) / $old_rate;

              if ($display_percent - 10 > 0) {

                $display_percent = round($display_percent, 1);
              }
              else {

                $display_percent = round($display_percent, 2);
              }

              $display_percent .= '%';

              if ($old_rate - $new_rate > 0) {

                $changeCSS = ' changeDown';
                $changeImage = '<img width="9" height="10" src="' . WP_PLUGIN_URL . '/fx-currency-tables/images/changeDown.gif" alt="down" />';
                $changeSign  = '-';
              }
              else {

                $changeCSS = ' changeUp';
                $changeImage = '<img width="9" height="10" src="' . WP_PLUGIN_URL . '/fx-currency-tables/images/changeUp.gif" alt="up" />';
                $changeSign  = '+';
              }
            }

            $display_new_rate = $new_rate;

            if ($new_rate - 10 > 0) {

              $display_new_rate = round($new_rate, 2);
            }
            else {

              $display_new_rate = round($new_rate, 3);
            }

            $myHTML .= '<td class="data' . $changeCSS . '"><nobr>' . $changeImage . $display_new_rate . '</nobr></td>' . "\n";

            $myHTML .= '<td class="data' . $changeCSS . '">' . $changeImage . $changeSign . $display_percent . '</td>' . "\n";

            $myHTML .= '</tr>' . "\n";
          }
        }

      } // for
    }   // for

    $myHTML .= '</tbody></table>' . "\n";

    $myHTML .= '<br /><table width="100%" cellspacing="1" cellpadding="3" border="0">' . "\n";

    $myHTML .= '<tr>' . "\n";

    $myHTML .= '<td align="left" width="119px">' . "\n";

    $myHTML .= '<a href="http://www.gatehouseintl.com/wordpress-plugin-currency-converter/">';

    $myHTML .= '<img src="' . WP_PLUGIN_URL . '/fx-currency-tables/images/get_widget2.gif" width="90" height="23" alt="Get Widget" /></a>' . "\n";

    $myHTML .= '</td>' . "\n";

    $myHTML .= '<td>' . "\n";

    //$myHTML .= '<a href="http://www.gatehouseintl.com/wordpress-plugin-currency-converter/" style="font-size: 10px;">';

    $myHTML .= '<a href="http://www.fx-foreignexchange.com/currency_widget.php?value=1&from=EUR&to=GBP&r=813" rel="nofollow" onClick="window.name=\'exchange_rates_todayNew\';window.open(this.href,\'converter\',\'toolbar=no,location=no,directories=no,status=no,menubar=no,width=660,height=880,resizable=yes,scrollbars=yes\');return false;" style="font-size: 10px;">';
    
    $myHTML .= 'Other<br/>Currencies'; // Other Currencies

    $myHTML .= '</a>' . "\n";

    $myHTML .= '</td>' . "\n";

    $myHTML .= '</tr>' . "\n";

    $myHTML .= '</table>' . "\n";

    //$myHTML .= '</div>' . "\n";

    //$myHTML .= '</div>' . "\n";

    //$myHTML .= '</div>' . "\n";

    //display the HTML
    echo $myHTML;
    
    //close the widget
    echo $after_widget;
  }

  //function to display widget control
  function widget_currency_table_control() {

    //print 'currency table control';

    //get the options from the WordPress database
    $options = $newoptions = get_option('widget_currency_table');

    //check if the settings have been saved
    if ($_POST['currency_table_widget_submit']) {
      //remove anything that shouldn't be there
      $newoptions['currency_table_title'] = strip_tags(stripslashes($_POST['currency_table_title']));
    } //close if

    //check if there has been an update
    if ($options != $newoptions) {

      //if there has been a change, save the changes in the WordPress database
      $options = $newoptions;
      update_option('widget_currency_table', $options);

    } // close if

    //build the control panel
    echo '<p><label style="display: block; text-align: left;" for="currency_table_title">' .
     __('Title:', 'widgets') . 
     ' <input class="widefat" style="display: block;text-align: left;" id="currency_table_title" ' .
     'name="currency_table_title" type="text" value="' . $options['currency_table_title'] . '" /></label></p>';
    echo '<input type="hidden" id="currency_table_widget_submit" name="currency_table_widget_submit" value="1" />';

  }

  //register widget and widget control
  //
  register_sidebar_widget('Currency Table', 'widget_currency_table_display');
  register_widget_control('Currency Table', 'widget_currency_table_control');
}

//function to write the style info to the header
function fx_currency_table_styles() {
  global $fx_currency_table_styles;
  //check if loaded styles
  //wp_head ation doesnot work. dunno why. may be on old WP sites
  if ($fx_currency_table_styles) return false;
  $fx_currency_table_styles=true;

  print '<link rel="stylesheet" href="' . WP_PLUGIN_URL . '/fx-currency-tables/style.css" type="text/css" media="screen" />' . "\n";
}


function get_json($url) {

  $res = '';

  //ob_start(); // start output buffering. To hide visible errors

  if (ini_get('allow_url_fopen')) {
  
    $res = @file_get_contents($url);
    //print ini_get('allow_url_fopen');
  }

  //$res = '';

  if (function_exists('curl_version') && (strlen($res) == 0)) {
    //
    // try CURL
    //
    
    //Initialize the Curl session
    $ch = curl_init();

    //Set curl to return the data instead of printing it to the browser.
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    //Set the URL
    curl_setopt($ch, CURLOPT_URL, $url);
    //Execute the fetch
    $res = curl_exec($ch);
    //Close the connection
    curl_close($ch);
    
    //print 'Curl working: ' . $res . ' :: ' . $url;
  }

  //ob_end_clean();

  return $res;
}
?>