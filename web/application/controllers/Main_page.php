<?php

use Model\Boosterpack_model;
use Model\Comment_model;
use Model\Login_model;
use Model\Post_model;
use Model\User_model;
use System\Libraries\Core;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{
    public function __construct()
    {
        parent::__construct();

        if (is_prod())
        {
            die('In production it will be hard to debug! Run as development environment!');
        }
    }

    public function index()
    {
        $user = User_model::get_user();

        App::get_ci()->load->view('main_page', ['user' => User_model::preparation($user, 'default')]);
    }

    public function get_all_posts()
    {
        $posts =  Post_model::preparation_many(Post_model::get_all(), 'default');
        return $this->response_success(['posts' => $posts]);
    }

    public function get_boosterpacks()
    {
        $posts =  Boosterpack_model::preparation_many(Boosterpack_model::get_all(), 'default');
        return $this->response_success(['boosterpacks' => $posts]);
    }

    public function login()
    {
        // get parameters
        $login = App::get_ci()->input->post('login');
        $password = App::get_ci()->input->post('password');

        // check for exist
        if (!$login || !$password) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $user = User_model::find_user_by_email($login);

        // compare pass from db and response and check user exist
        if(!$user || $user->get_password() !== $password) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        //start session
        Login_model::login($user->get_id());

        return $this->response_success([
            'user' => User_model::preparation($user, 'main_page'),
        ]);
    }

    public function logout()
    {
        Login_model::logout();
        redirect('/');
    }

    public function comment()
    {
        if (!User_model::is_logged()) {
            return $this->response_error(Core::RESPONSE_GENERIC_NEED_AUTH,[],401);
        }
        // get params
        $post_id = (int)App::get_ci()->input->post('postId');
        $text = App::get_ci()->input->post('commentText');
        $reply_id = (int) App::get_ci()->input->post('replyId');

        //check params exist(if isset reply id also check reply id)
        if ((!$post_id || !$text) || (isset($reply_id) && !$reply_id)) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS, [], 400);
        }

        Comment_model::create([
            'user_id' => User_model::get_session_id(),
            'assign_id'=>$post_id,
            'text'=> htmlentities($text),
            'reply_id' => $reply_id
        ]);

        return $this->response_success();
    }

    public function like_comment($comment_id)
    {
        if (!User_model::is_logged()) {
            return $this->response_error(Core::RESPONSE_GENERIC_NEED_AUTH,[],401);
        }

        $comment_id = (int)$comment_id;
        if (!$comment_id) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $user = User_model::get_user();

        // check user like balance
        if($user->get_likes_balance()===0) {
            return $this->response_error(Core::RESPONSE_GENERIC_LIKE_BALANCE,[],400);
        }

        $comment = new Comment_model($comment_id);

        $decrement_result = $user->decrement_likes(__FUNCTION__,$comment->get_id());
        $increment_result = $comment->increment_likes();

        if (!$decrement_result || !$increment_result) {
            return $this->response_error(Core::RESPONSE_GENERIC_TRY_LATER, [], 500);
        }

        return $this->response_success(['likes' => $comment->get_likes()]);
    }

    public function like_post($post_id)
    {
        if (!User_model::is_logged()) {
            return $this->response_error(Core::RESPONSE_GENERIC_NEED_AUTH,[],401);
        }

        $post_id = (int)$post_id;
        if (!$post_id) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $user = User_model::get_user();

        // check user like balance
        if($user->get_likes_balance()===0) {
            return $this->response_error(Core::RESPONSE_GENERIC_LIKE_BALANCE,[],401);
        }

        $post = new Post_model($post_id);

        $decrement_result = $user->decrement_likes(__FUNCTION__,$post->get_id());
        $increment_result = $post->increment_likes();

        if (!$decrement_result || !$increment_result) {
            return $this->response_error(Core::RESPONSE_GENERIC_TRY_LATER, [], 500);
        }
        return $this->response_success(['likes' => $post->get_likes()]);
    }

    public function add_money()
    {
        if (!User_model::is_logged()) {
            return $this->response_error(Core::RESPONSE_GENERIC_NEED_AUTH,[],401);
        }

        $sum = (float)App::get_ci()->input->post('sum');

        if (!$sum) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $userId = User_model::get_user()->get_id();

        $response = (new User_model($userId))->add_money($sum);

        if (!$response) {
            return $this->response_error(Core::RESPONSE_GENERIC_TRY_LATER, [], 500);
        }
        return $this->response_success();
    }

    public function get_post($post_id) {
        $post_id = (int)$post_id;
        if (!$post_id) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $postOblect = new Post_model($post_id);
        $post =  Post_model::preparation($postOblect, 'full_info');
        return $this->response_success(['post' => $post]);
    }

    public function buy_boosterpack()
    {
        if (!User_model::is_logged()) {
            return $this->response_error(Core::RESPONSE_GENERIC_NEED_AUTH,[],401);
        }

        $boosterpack_id = (int) App::get_ci()->input->post('id');

        if (!$boosterpack_id) {
            return $this->response_error(Core::RESPONSE_GENERIC_WRONG_PARAMS,[],400);
        }

        $boosterpack = new Boosterpack_model($boosterpack_id);
        $open_result = $boosterpack->open();

        if(!$open_result) {
            return $this->response_error(Core::RESPONSE_GENERIC_TRY_LATER, [], 500);
        }

        return $this->response_success(['amount' => $open_result]);
    }
}
