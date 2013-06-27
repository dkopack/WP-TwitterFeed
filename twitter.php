<?php
/*
 * Plugin Name: Twitter Feed
 * Description: Fetches and caches twitter feeds
 * Version: 1.1
 * Author: David Kopack
 * Author URI: http://www.terralever.com
 * */

require_once('lib/twitteroauth/twitteroauth/twitteroauth.php');

add_shortcode('get_tweets', 'get_tweets_handler');
add_action('admin_menu' , 'twitter_admin_menu');

function get_tweets_handler($atts,$content = null) {
    extract(shortcode_atts(array(
        'search' => get_option('wp_twitter_default_search_string'), 
        'count' => '5',
        'twitter_handle' => ''
    ), $atts));

    return get_tweets($count,$search,$twitter_handle);
}

// fetch and cache latest tweets
function get_tweets($count,$search,$twitter_handle = null) {
    $cache_path = TEMPLATEPATH . '/cache/';

    if($twitter_handle) {
        $cache_file = $cache_path . 'twitter_result_' . $twitter_handle . '.data';
    }
    else {
        $cache_file = $cache_path . 'twitter_result.data';
    }

    if(file_exists($cache_file)) {
        $data = unserialize(file_get_contents($cache_file));
        if ($data['timestamp'] > time() - 10 * 60) {
            $twitter_result = $data['twitter_result'];
        }
    }
    if (!$twitter_result) {
        $twitter_consumer_key = get_option('wp_twitter_consumer_key');
        $twitter_consumer_secret = get_option('wp_twitter_consumer_secret');
        $twitter_access_key = get_option('wp_twitter_access_key');
        $twitter_access_secret = get_option('wp_twitter_access_secret');

        $connection = new TwitterOAuth($twitter_consumer_key,$twitter_consumer_secret,$twitter_access_key,$twitter_access_secret);
        $connection->host = "https://api.twitter.com/1.1/";

        if($twitter_handle) {
            $twitter_result = $connection->get('statuses/user_timeline', array('screen_name' => $twitter_handle, 'count' => $count));
        }
        else {
            $twitter_result = $connection->get('search/tweets', array('q' => $search, 'count' => $count));
        }

        $data = array ('twitter_result' => $twitter_result, 'timestamp' => time());
        file_put_contents($cache_file, serialize($data));
    }


    if ($twitter_result) {
        if ($twitter_handle) {
            return outputSingle($twitter_result);
        }
        else {
            return outputList($twitter_result);
        }
    }
    return 'No Tweets';
}

// HTML for Single Tweet
function outputSingle($twitter_result) {
    foreach ($twitter_result as $key => $val) {
        $tweet_time = strtotime($val->created_at);
        $html .=  addHyperlinks($val->text);
    }
    return $html;
}

// HTML for List of Tweets
function outputList($twitter_result) {
    $html = '<ul class="feat twitter clearfix">';
    foreach ($twitter_result->statuses as $key => $val) {
        $tweet_time = formatInterval(strtotime($val->created_at));
        $html .= '<li>';
        $html .= '<div class="tmb"><span><img src="' . $val->user->profile_image_url . '"></span></div>';
        $html .= '<div class="txt">';
        $html .= '<h3>@' . $val->user->screen_name . '</h3>';
        $html .= '' . addHyperlinks($val->text);
        $html .= '<div class="misc">' . $tweet_time . '</div>';
        $html .= '</div>';
        $html .= '</li>';
    }
    $html .= "</ul>";
    return $html;
}

function addHyperlinks($str) {
    return preg_replace('/https?:\/\/[\w\-\.!~?&+\*\'"(),\/]+/','<a href="$0">$0</a>',$str);
}

function formatInterval($timestamp, $granularity = 1) {
    $timestamp = time() - $timestamp;
    $units = array('1 year|@count years' => 31536000, '1 week|@count weeks' => 604800, '1 day|@count days' => 86400, '1 hour|@count hours' => 3600, '1 min|@count min' => 60, '1 sec|@count sec' => 1);
    $output = '';
    foreach ($units as $key => $value) {
        $key = explode('|', $key);
        if ($timestamp >= $value) {
            $floor = floor($timestamp / $value);
            $output .= ($output ? ' ' : '') . ($floor == 1 ? $key[0] : str_replace('@count', $floor, $key[1]));
            $timestamp %= $value;
            $granularity--;
            $output .= ' ago';
        }

        if ($granularity == 0) {
            break;
        }
    }

return $output ? $output : '0 sec';
}


// admin interface

function twitter_admin_menu() {
    add_options_page('Twitter Settings', 'Twitter Settings', 'edit_themes', __FILE__, 'twitter_admin');
}

function twitter_admin() {
    if ( strtoupper( $_POST['Action'] ) == 'UPDATE' ) {
        if ( !wp_verify_nonce( $_POST['twitter_admin'], plugin_basename(__FILE__) ) ) {
            echo '<div class="updated"><p>Something went wrong. Please try again.</p></div>';
        }
        else {
            $twitter_default_search = esc_html( $_POST['twitter_default_search'] );
            $twitter_consumer_key = esc_html( $_POST['twitter_consumer_key'] );
            $twitter_consumer_secret = esc_html( $_POST['twitter_consumer_secret'] );
            $twitter_access_key = esc_html( $_POST['twitter_access_key'] );
            $twitter_access_secret = esc_html( $_POST['twitter_access_secret'] );

            update_option('wp_twitter_default_search_string', $twitter_default_search );
            update_option('wp_twitter_consumer_key', $twitter_consumer_key );
            update_option('wp_twitter_consumer_secret', $twitter_consumer_secret );
            update_option('wp_twitter_access_key', $twitter_access_key );
            update_option('wp_twitter_access_secret', $twitter_access_secret );
        };
    }

    $twitter_default_search = get_option('wp_twitter_default_search_string');
    $twitter_consumer_key = get_option('wp_twitter_consumer_key');
    $twitter_consumer_secret = get_option('wp_twitter_consumer_secret');
    $twitter_access_key = get_option('wp_twitter_access_key');
    $twitter_access_secret = get_option('wp_twitter_access_secret');
?>
    <h2>Twitter Settings</h2>
    <form method="post" action="">
        <?php wp_nonce_field( plugin_basename( __FILE__ ), 'twitter_admin', false, true ); ?>
        <label for="twitter_admin_search_string">Default Search String</label>
        <input type="text" name="twitter_default_search" id="twitter_default_search" value="<?php echo $twitter_default_search; ?>" /><br />
        <label for="twitter_consumer_key">Consumer Key</label>
        <input type="text" name="twitter_consumer_key" id="twitter_consumer_key" value="<?php echo $twitter_consumer_key; ?>"/><br />
        <label for="twitter_consumer_secret">Consumer Secret</label>
        <input type="text" name="twitter_consumer_secret" id="twitter_consumer_secret" value="<?php echo $twitter_consumer_secret; ?>"/><br />
        <label for="twitter_access_key">Access Token</label>
        <input type="text" name="twitter_access_key" id="twitter_access_key" value="<?php echo $twitter_access_key; ?>"/><br />
        <label for="twitter_access_secret">Access Secret</label>
        <input type="text" name="twitter_access_secret" id="twitter_access_secret" value="<?php echo $twitter_access_secret; ?>"/><br />

        <input type="submit" name="Action" value="Update" class="button-primary"/>
    </form>
<?php } ?>
