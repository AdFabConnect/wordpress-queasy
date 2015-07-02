<?php

/* Administration */

add_action('init', 'create_post_type_queasy_group' );

function create_post_type_queasy_group() {
    register_post_type('queasy_group', array(
        'label'               => 'queasy_group',
        'description'         => 'Group management',
        'labels'              => array(
            'name'                => 'Groups',
			'singular_name'       => 'Group',
			'menu_name'           => 'Queasy',
			'parent_item_colon'   => 'Parent:',
			'all_items'           => 'Manage groups',
			'view_item'           => 'View group',
			'add_new_item'        => 'Add group',
			'add_new'             => 'Add group',
			'edit_item'           => 'Update group',
			'update_item'         => 'Update group',
			'search_items'        => 'Search',
			'not_found'           => 'No group found',
			'not_found_in_trash'  => 'No group found in trash',
        ),
        'supports'            => array('title'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => true,
        'show_in_admin_bar'   => true,
        'menu_position'       => 50,
        'menu_icon'           => 'dashicons-editor-help',
        'can_export'          => true,
        'has_archive'         => false,
        'exclude_from_search' => true,
        'publicly_queryable'  => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
    ));
}

add_action('pre_get_posts', 'queasy_group_filter_admin_grid_by_parent');

function queasy_group_filter_admin_grid_by_parent($query)
{
    if(!is_admin()) {
        return $query;
    }
    
    global $pagenow;
    if ('edit.php' != $pagenow) {
        return $query;
    }
    
    if( isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == 'queasy_group' ) {
        if (isset($query->query['suppress_filters']) && $query->query['suppress_filters']) {
            return $query;
        }
        $query = queasy_group_filter_query_by_parent($query, isset($_GET['queasy_group_parent']) ? $_GET['queasy_group_parent'] : null);
    }

    return $query;

}

function queasy_group_add_admin_dropdown()
{
    global $typenow;
    global $wp_query;


    if ($typenow == 'queasy_group') {

        $value = isset($_GET['queasy_group_parent']) ? $_GET['queasy_group_parent'] : '';
        $posts = new WP_Query(array(
            'posts_per_page'   => -1,
            'offset'           => 0,
            'orderby'          => 'menu_order',
            'order'            => 'ASC',
            'post_type'        => 'queasy_group',
            'post_status'      => 'publish',
            'suppress_filters' => true,
        ));
        echo '<select name="queasy_group_parent">'."\n";
        echo '    <option value=""'.($value == '' ? ' selected' : '').'> - </option>'."\n";
        foreach ($posts->get_posts() as $post) {
            echo '    <option value="'.$post->ID.'"'.($value == $post->ID ? ' selected' : '').'>'.htmlentities($post->post_title).'</option>'."\n";
        }
        echo '</select>'."\n";

    }
}

add_action('restrict_manage_posts','queasy_group_add_admin_dropdown');

function queasy_group_add_admin_columns($columns) {
    $columns = array_merge(
        array_slice($columns, 1, 1),
        array('queasy_group_children' => ''),
        array_slice($columns, 2)
    );
    return $columns;
}

function queasy_group_fill_admin_columns($column, $post_id) {

    switch ($column) {
        case 'queasy_group_children' :
            echo '<a href="?post_status=all&post_type=queasy_group&queasy_group_parent='.$post_id.'">Voir les sous étapes</a>';
            echo ' | <a href="?post_status=all&post_type=queasy_question&queasy_question_group='.$post_id.'">Voir les questions</a>';
            break;
    }
}

add_filter('manage_queasy_group_posts_columns', 'queasy_group_add_admin_columns');
add_action('manage_queasy_group_posts_custom_column', 'queasy_group_fill_admin_columns', 10, 2 );

/* Utility functions */

/**
 * 
 * @param array $args
 * @return array<WP_Post>
 */
function queasy_group_get_first_level(array $args = array())
{
    return queasy_group_get_from_parent(null, $args);
}

/**
 * 
 * @param int $id
 * @param array $args
 * @return array<WP_Post>
 */
function queasy_group_get_from_parent($id = null, array $args = array())
{
    $key = 'sub_step_' . $id . '_' . serialize($args);
    if (null === ($data = queasy_cache_get($key))) {
        $query = new WP_Query(array_merge(array(
            'posts_per_page'   => -1,
            'offset'           => 0,
            'orderby'          => 'menu_order',
            'order'            => 'ASC',
            'post_type'        => 'queasy_group',
            'post_status'      => 'publish',
            'suppress_filters' => true,
        ), $args));
        $query = queasy_group_filter_query_by_parent($query, $id);
        if ($query->have_posts()) {
            $data = $query->get_posts();
        } else {
            $data = false;
        }
        queasy_cache_set($key, $data, 86400);
    }
    return $data;
}

/**
 * 
 * @param WP_Query $query
 * @param int $id
 * @return WP_Query
 */
function queasy_group_filter_query_by_parent(WP_Query $query, $id = null)
{
    $meta = array();
    if (!empty($id)) {
        $meta[] = array(
            array(
                'key'	  	=> 'queasy_group_parent',
                'value'	  	=> $id,
                'compare' 	=> '=',
            ),
        );
    } else {
        $meta[] = array(
            'relation' => 'OR',
            array(
                'key'	 	=> 'queasy_group_parent',
                'compare' 	=> 'NOT EXISTS',
            ),
            array(
                'key'	  	=> 'queasy_group_parent',
                'value'	  	=> '',
                'compare' 	=> '=',
            ),
            array(
                'key'	  	=> 'queasy_group_parent',
                'value'	  	=> 'null',
                'compare' 	=> '=',
            )
        );
    }
    if (count($meta)) {
        $meta['relation'] = 'AND';
        $query->set('meta_query', $meta);
    }
    return $query;
}

/**
 * returns the percentage of completion for a user, for a given group, between 0 and 100
 * 
 * - performs percentage of questions answered by group
 * - averages the groups percentages
 * 
 * returns false if no question is found
 * recursively search all questions in the sub groups
 * 
 * @param int $userId
 * @param int $id
 * @param bool $recursive 
 * @return float
 */
function queasy_group_get_group_percentage($id = null, $userId = null, $recursive = true)
{
    if (!is_numeric($userId)) {
        $user = wp_get_current_user();
        if ($user && isset($user->ID)) {
            $userId = $user->ID;
        }
    }
    if (!is_numeric($userId)) {
        return 0.0;
    }
    
    $percentage = array();
    if (!is_numeric($id)) {
        if (!$recursive) {
            throw new Exception('Can not retrieve percentage : no id and not recursive');
        }
        $groups = queasy_group_get_first_level();
        foreach ($groups as $group) {
            if (is_numeric($group->ID)) {
                if (false !== ($p = queasy_group_get_group_percentage($group->ID, $userId))) {
                    $percentage[] = $p;
                }
            }
        }
    } else {
        $questions = queasy_question_get_from_group($id);
        $questionPercentage = array();
        foreach ($questions as $question) {
            $questionPercentage[] = queasy_question_is_answered($question->ID, $userId) ? 100 : 0;
        }
        if (count($questionPercentage)) {
            $percentage[] =  array_sum($questionPercentage) / count($questionPercentage);
        }
        $groups = queasy_group_get_from_parent($id);
        foreach ($groups as $group) {
            if (is_numeric($group->ID)) {
                if (false !== ($p = queasy_group_get_group_percentage($group->ID, $userId))) {
                    $percentage[] = $p;
                }
            }
        }
    }
    return count($percentage) ? array_sum($percentage) / count($percentage) : false;
}

function queasy_cache_get($key)
{
    if (!defined('CACHE_VERSION')) {
        return null;
    }
    return cache_get($key);
}

function queasy_cache_set($key, $data, $ttl)
{
    if (!defined('CACHE_VERSION')) {
        return null;
    }
    return cache_set($key, $data, $ttl);
}
