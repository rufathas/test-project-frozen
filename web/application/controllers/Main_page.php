<?php

use Model\Analytics_model;
use Model\Boosterpack_model;
use Model\Comment_model;
use Model\Login_model;
use Model\Post_model;
use Model\User_model;

/**
 * Created by PhpStorm.
 * User: mr.incognito
 * Date: 10.11.2018
 * Time: 21:36
 */
class Main_page extends MY_Controller
{
    private $responseParams;

    public function __construct()
    {
        parent::__construct();

        $this->responseParams = new stdClass();
        //$this->checkUserAuth();
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
        $this->responseParams->login = App::get_ci()->input->post('login');
        $this->responseParams->password = App::get_ci()->input->post('password');
        if (!$this->responseParams->login || !$this->responseParams->password) {
            return $this->response(['status' => 'Write correct data'],403);
        }
        $user = User_model::find_user_by_email($this->responseParams->login);
        if($user->get_password() !== $this->responseParams->password) {
            return $this->response(['status' => 'Incorrect login or password'],403);
        }
        Login_model::login($user->get_id());
        return $this->response_success();
    }

    public function logout()
    {
        Login_model::logout();
        redirect('/');
    }

    public function comment()
    {
        $this->responseParams->post_id = App::get_ci()->input->post('postId');
        $this->responseParams->text = App::get_ci()->input->post('commentText');
        $this->responseParams->reply_id = App::get_ci()->input->post('replyId');
        if (!$this->responseParams->post_id || !$this->responseParams->text) {
            $this->response(['status' =>'invalid params'],400);
        }
        Comment_model::create([
            'user_id'=>User_model::get_session_id(),
            'assign_id'=>$this->responseParams->post_id,
            'text'=>$this->responseParams->text,
            'reply_id' => $this->responseParams->reply_id
        ]);
        $this->response_success();
    }

    public function like_comment(int $comment_id)
    {
        $user = User_model::get_user();
        if($user->get_likes_balance()===0) {
            return $this->response("You don't have enough points to like",400);
        }
        $comment = new Comment_model($comment_id);
        $answer = $user->decrement_likes();
        if (!$answer) {
            return $this->response_error('Technical problems', [], 400);
        }
        $comment->increment_likes();
        $this->response_success();
    }

    public function like_post(int $post_id)
    {
        $user = User_model::get_user();
        if($user->get_likes_balance()===0) {
            return $this->response("You don't have enough points to like",400);
        }
        $post = new Post_model($post_id);
        $answer = $user->decrement_likes();
        if (!$answer) {
            return $this->response_error('Technical problems', [], 400);
        }
        $post->increment_likes();
        $this->response_success();
    }

    public function add_money()
    {
        $sum = (float)App::get_ci()->input->post('sum');
        if (!$sum || !is_float($sum)) {
            return $this->response(['status' => 'invalid params'], 400);
        }
        $userId = User_model::get_user()->get_id();
        $logArray = [
            'user_id' => $userId,
            'object' => 'wallet',
            'action' => 'replenishment',
            'amount' => $sum
        ];
        $response = (new User_model($userId))->add_money($sum);
        if (!$response) {
            $logArray['action'] = 'error replenishment';
            Analytics_model::create($logArray);
            return $this->response_error('problems with adding funds to your account', [], 400);
        }
        Analytics_model::create($logArray);
        return $this->response_success();
    }

    public function get_post(int $post_id) {
        $postOblect = new Post_model($post_id);
        $post =  Post_model::preparation($postOblect, 'full_info');
        return $this->response_success(['post' => $post]);
    }

    public function buy_boosterpack()
    {
        $boosterpackId = App::get_ci()->input->post('id');
        if (!$boosterpackId) {
            return $this->response(['status' => 'invalid params'], 400);
        }
        $boosterpack = new Boosterpack_model($boosterpackId);
        $response = $boosterpack->open();

        if(!$response) {
            return $this->response_error(123);
        }

        return $this->response_success(['amount' => $response]);
    }





    /**
     * @return object|string|void
     */
    public function get_boosterpack_info(int $bootserpack_info)
    {
        // Check user is authorize



        //TODO получить содержимое бустерпака
    }

    private function checkUserAuth(): void
    {
        if (!User_model::is_logged()) {
            http_response_code(401);
            die('Log in before performing the operation');
        }
    }
}
