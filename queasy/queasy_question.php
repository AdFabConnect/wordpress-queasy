<?php

/* Administration */

add_action('init', 'create_post_type_queasy_question' );

function create_post_type_queasy_question() {
    register_post_type('queasy_question', array(
        'label'               => 'queasy_question',
        'description'         => 'Question Management',
        'labels'              => array(
            'name'                => 'Questions',
			'singular_name'       => 'Question',
			'menu_name'           => 'Queasy',
			'parent_item_colon'   => 'Parent:',
			'all_items'           => 'View questions',
			'view_item'           => 'View question',
			'add_new_item'        => 'Add question',
			'add_new'             => 'Add question',
			'edit_item'           => 'Update question',
			'update_item'         => 'Update question',
			'search_items'        => 'Search',
			'not_found'           => 'No question found',
			'not_found_in_trash'  => 'No question found in trash',
        ),
        'supports'            => array('title'),
        'hierarchical'        => false,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => 'edit.php?post_type=queasy_group',
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

add_action('pre_get_posts', 'queasy_question_filter_admin_grid_by_group');

function queasy_question_filter_admin_grid_by_group($query)
{
    if(!is_admin()) {
        return $query;
    }

    global $pagenow;
    if ('edit.php' != $pagenow) {
        return $query;
    }

    if( isset($query->query_vars['post_type']) && $query->query_vars['post_type'] == 'queasy_question' ) {
        if (isset($query->query['suppress_filters']) && $query->query['suppress_filters']) {
            return $query;
        }
        $query = queasy_question_filter_query_by_group($query, isset($_GET['queasy_question_group']) ? $_GET['queasy_question_group'] : null);
    }

    return $query;

}

function queasy_question_add_admin_dropdown()
{
    global $typenow;
    global $wp_query;


    if ($typenow == 'queasy_question') {

        $value = isset($_GET['queasy_question_group']) ? $_GET['queasy_question_group'] : '';
        $posts = new WP_Query(array(
            'posts_per_page'   => -1,
            'offset'           => 0,
            'orderby'          => 'menu_order',
            'order'            => 'ASC',
            'post_type'        => 'queasy_group',
            'post_status'      => 'publish',
            'suppress_filters' => true,
        ));
        echo '<select name="queasy_question_group">'."\n";
        echo '    <option value=""'.($value == '' ? ' selected' : '').'> - </option>'."\n";
        foreach ($posts->get_posts() as $post) {
            echo '    <option value="'.$post->ID.'"'.($value == $post->ID ? ' selected' : '').'>'.htmlentities($post->post_title).'</option>'."\n";
        }
        echo '</select>'."\n";

    }
}

add_action('restrict_manage_posts','queasy_question_add_admin_dropdown');

/* Utility functions */

/**
 * back questions associated with a particular group
 * not recursive, not gonna look in the sub-groups
 *
 * @param int $id
 * @param array $args
 * @return array<WP_Post>
 */
function queasy_question_get_from_group($id, array $args = array())
{
    $key = 'questions_step_' . $id . '_' . serialize($args);
    if (null === ($data = queasy_cache_get($key))) {
        $query = new WP_Query(array_merge(array(
            'posts_per_page'   => -1,
            'offset'           => 0,
            'orderby'          => 'menu_order',
            'order'            => 'ASC',
            'post_type'        => 'queasy_question',
            'post_status'      => 'publish',
            'suppress_filters' => true,
        ), $args));
        $query = queasy_question_filter_query_by_group($query, $id);
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
 * filter on a particular group
 *
 * @param WP_Query $query
 * @param int $id
 * @return WP_Query
 */
function queasy_question_filter_query_by_group(WP_Query $query, $id)
{
    $meta = array();
    if (!empty($id)) {
        $meta[] = array(
            array(
                'key'	  	=> 'queasy_question_group',
                'value'	  	=> $id,
                'compare' 	=> '=',
            ),
        );
    }
    if (count($meta)) {
        $meta['relation'] = 'AND';
        $query->set('meta_query', $meta);
    }
    return $query;
}

/**
 * return the user's answer
 * beware of the return type of the function, as a question can have one, or several answers
 * returns false if the question was not answered
 *
 * @param int $userId
 * @param int $questionId
 * @return string|array
 */
function queasy_question_get_answer($questionId, $userId = null)
{
    if ($post = queasy_question_get_answer_post($questionId, $userId)) {
        return @unserialize(get_post_meta($post->ID, 'queasy_answer_answer', true));
    }
    return false;
}

function queasy_question_get_answer_post($questionId, $userId = null)
{
    if (!is_numeric($userId)) {
        $user = wp_get_current_user();
        if ($user && isset($user->ID)) {
            $userId = $user->ID;
        }
    }
    if (!is_numeric($userId)) {
        return false;
    }
    $query = new WP_Query(array(
        'posts_per_page'   => -1,
        'offset'           => 0,
        'orderby'          => 'menu_order',
        'order'            => 'ASC',
        'post_type'        => 'queasy_answer',
        'post_status'      => 'publish',
        'suppress_filters' => true,
        'post_author'      => $userId, // the user who answered
        'post_parent'      => $questionId // the question answered
    ));
    if (!$query->have_posts()) {
        return false;
    }
    if (! $post = current($query->get_posts())) {
        return false;
    }
    return $post;
}

/**
 * The user does he answered this question ?
 *
 * @param int $userId
 * @param int $questionId
 * @return boolean
 */
function queasy_question_is_answered($questionId, $userId = null)
{
    if (!is_numeric($userId)) {
        $user = wp_get_current_user();
        if ($user && isset($user->ID)) {
            $userId = $user->ID;
        }
    }
    if (!is_numeric($userId)) {
        return false;
    }
    $answer = queasy_question_get_answer($questionId, $userId);
    if (is_array($answer)) {
        return count($answer) ? true : false;
    }
    return !empty($answer);
}

/**
 * Are the conditions met for this question appears ?
 *
 * @param int $questionId
 * @param int $userId
 */
function queasy_question_can_show($questionId, $userId = null)
{
    if (!is_numeric($userId)) {
        $user = wp_get_current_user();
        if ($user && isset($user->ID)) {
            $userId = $user->ID;
        }
    }
    if (!is_numeric($userId)) {
        return false;
    }

    $questionConditionId = get_post_meta($questionId, 'queasy_question_conditional_question', true);
    if (!$questionConditionId) {
        return true;
    }
    $values = get_post_meta($questionId, 'queasy_question_conditional_values', true);
    $values = queasy_question_parse_values($values);
    $answers = queasy_question_get_answer($questionConditionId);
    if (!is_array($answers)) {
        $answers = array($answers);
    }
    return count(array_intersect($answers, $values)) ? true : false;
}

/**
 * answer a question
 * if it is already answered, it is overwritten with the new value
 * return the post id associated with the answer
 *
 * @param int $questionId
 * @param int $userId
 * @param string|array $values
 * @return int
 */
function queasy_question_answer($questionId, $userId, $values)
{
    $post = queasy_question_get_answer_post($questionId, $userId);
    if ($post) {
        update_post_meta($id = $post->ID, 'queasy_answer_answer', serialize($values));
    } else {
        $id = wp_insert_post(array(
            'post_title'    => 'answer question ' . $questionId . ' user '. $userId,
            'post_content'  => '',
            'post_status'   => 'publish',
            'post_author'   => $userId,
            'post_parent'   => $questionId,
            'post_type'     => 'queasy_answer'
        ));
        add_post_meta($id, 'queasy_answer_answer', serialize($values));
    }
    return $id;
}

/**
 * render and return the template html associated with the type of the question
 *
 * @param int|WP_Post $question
 * @param int $userId
 * @param string|array $values
 * @return string
 */
function queasy_question_render_template($question, $userId = null, $values = null)
{
    if ($question instanceof WP_Post) {
        $questionId = $question->ID;
    } else {
        $questionId = $question;
        $question = queasy_question_get($question);
    }
    $type = get_post_meta($questionId, 'queasy_question_type', true);
    $type = str_replace('..', '', $type); // no hacker please

    if (!file_exists($template = get_template_directory() . '/partials/bisuness_plan/question/'.$type.'.php')) {
        return false;
    }

    if (!isset($question->metas)) {
        $question = queasy_question_get($question);
    }

    ob_start();
    include $template;
    return ob_get_clean();
}

/**
 * get question post with metas
 * can also pass question as WP_Post to get metas
 *
 * @param int|WP_Post $question
 * @return WP_Post
 */
function queasy_question_get($question, $withMetas = true)
{
    if (!$question) {
        return false;
    }
    if (is_numeric($question)) {
        $question = get_post($question);
    }

    if ($question && $withMetas && !isset($question->metas)) {
        $question->metas = array(
            'group' => get_post_meta($question->ID, 'queasy_question_group', true),
            'type' => get_post_meta($question->ID, 'queasy_question_type', true),
            'choices' => queasy_question_parse_values(get_post_meta($question->ID, 'queasy_question_choices', true)),
            'conditional_question' => get_post_meta($question->ID, 'queasy_question_conditional_question', true),
            'conditional_values' => queasy_question_parse_values(get_post_meta($question->ID, 'queasy_question_conditional_values', true)),
            'placeholder' => get_post_meta($question->ID, 'queasy_question_placeholder', true),
            'poppin_conseil' => get_post_meta($question->ID, 'queasy_question_poppin_conseil', true),
            'description' => get_post_meta($question->ID, 'queasy_question_description', true),
        );
    }
    return $question;
}

/**
 * parse choices (1 line = 1 choice)
 *
 * @param string $values
 * @return array
 */
function queasy_question_parse_values($values)
{
    return explode("\n", str_replace("\r", '', rtrim($values, "\r\n\ \t")));
}

/**
 *
 * @param int|WP_Post $question
 * @return bool
 */
function queasy_question_is_conditional($question)
{
    if (!$question) {
        return false;
    }
    if (is_numeric($question) || !isset($question->metas)) {
        $question = queasy_question_get($question);
    }
    return is_numeric($question->metas['conditional_question']);
}

/**
 * save an answer
 *
 * @param array $post
 */
function queasy_question_save_from_post($post)
{
    $user = wp_get_current_user();
    if (!$user || !$user->ID) {
        echo json_encode(array('status' => 'no_user'));
        die();
    }

    if (isset($post['questions']) && count($questions = $post['questions'])) {
        foreach ($questions as $question) {
            $id = isset($question['id']) ? $question['id'] : false;
            if (!$id) continue;

            $answer = isset($question['answer']) ? $question['answer'] : false;
            $questionPost = queasy_question_get($id);
            if (!$questionPost) {
                continue;
            }

            queasy_question_answer($id, $user->ID, $answer);
        }
    }
    echo json_encode(array('status' => 'ok'));
    die();
}
