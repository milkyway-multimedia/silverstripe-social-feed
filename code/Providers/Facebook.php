<?php namespace Milkyway\SocialFeed\Providers;

use Milkyway\SocialFeed\Providers\Model\Oauth;
use Milkyway\SocialFeed\Utilities;

/**
 * Milkyway Multimedia
 * Facebook.php
 *
 * @package reggardocolaianni.com
 * @author Mellisa Hankins <mell@milkywaymultimedia.com.au>
 */
class Facebook extends Oauth {
    protected $endpoint = 'https://graph.facebook.com';
    protected $url = 'http://facebook.com';

    public function all($settings = []) {
        $all = [];

        try {
            $response = $this->http()->get(
                $this->endpoint($settings['username'], 'feed'),
                [
                    'query' => isset($settings['query']) ? $settings['query'] : [],
                ]
            );

            if(!$this->isError($response)) {
                $body = $response->json();

                if(!isset($body['data']))
                    throw new \Exception('Data not received from Facebook. Please check your credentials.');

                foreach($body['data'] as $post) {
                    if($this->allowed($post))
                        $all[] = $this->handlePost($post, $settings);
                }
            }
        } catch (\Exception $e) {
            \Debug::show($e->getMessage());
        }

        return $all;
    }

    protected function handlePost(array $data, $settings = []) {
        list($userId, $id) = explode('_', $data['id']);

        if(isset($settings['username']))
            $userId = $settings['username'];

        $post = array(
            'ID' => $id,
            'Link' => \Controller::join_links($this->url . '/' . $userId . '/posts/' . $id),
            'Author' => isset($data['from']) && isset($data['from']['name']) ? $data['from']['name'] : '',
            'AuthorID' => isset($data['from']) && isset($data['from']['id']) ? $data['from']['id'] : '',
            'AuthorURL' => isset($data['from']) && isset($data['from']['id']) ? \Controller::join_links($this->url, $data['from']['id']) : '',
            'Avatar' => isset($data['from']) && isset($data['from']['id']) ? \Controller::join_links($this->endpoint, $data['from']['id'], 'picture') : '',
            'Content' => isset($data['message']) ? Utilities::auto_link_text(nl2br($data['message'])) : '',
            'Picture' => isset($data['picture']) ? $data['picture'] : '',
            'ObjectName' => isset($data['name']) ? $data['name'] : '',
            'ObjectURL' => isset($data['link']) ? $data['link'] : '',
            'Description' => isset($data['description']) ? Utilities::auto_link_text(nl2br($data['description'])) : '',
            'Icon' => isset($data['icon']) ? $data['icon'] : '',
            'Type' => isset($data['type']) ? $data['type'] : '',
            'StatusType' => isset($data['status_type']) ? $data['status_type'] : '',
            'Priority' => $data['created_time'],
            'Posted' => isset($data['created_time']) ? \DBField::create_field('SS_Datetime', $data['created_time']) : null,
            'LikesCount' => isset($data['likes']) && isset($data['likes']['data']) ? count($data['likes']['data']) : 0,
            'CommentsCount' => isset($data['comments']) && isset($data['comments']['data']) ? count($data['comments']['data']) : 0,
        );

        $post['Created'] = $post['Posted'];

        $post['LikesDescriptor'] = $post['LikesCount'] == 1 ? _t('SocialFeed.LIKE', 'like') : _t('SocialFeed.LIKES', 'likes');
        $post['CommentsDescriptor'] = $post['CommentsCount'] == 1 ? _t('SocialFeed.COMMENT', 'comment') : _t('SocialFeed.COMMENTS', 'comments');

        if (!$post['Content'] && isset($data['story']) && $data['story'])
            $post['Content'] = Utilities::auto_link_text(nl2br($data['story']));

        if (isset($data['likes']) && isset($data['likes']['data']) && count($data['likes']['data'])) {
            $post['Likes'] = [];

            foreach ($data['likes']['data'] as $likeData) {
                $post['Likes'][] = [
                    'Author' => isset($likeData['name']) ? $likeData['name'] : '',
                    'AuthorID' => isset($likeData['id']) ? $likeData['id'] : '',
                    'AuthorURL' => isset($likeData['id']) ? \Controller::join_links($this->url, $likeData['id']) : '',
                ];
            }
        }

        if (isset($data['comments']) && isset($data['comments']['data']) && count($data['comments']['data'])) {
            $post['Comments'] = [];

            foreach ($data['comments']['data'] as $commentData) {
                $comment = array(
                    'Author' => isset($commentData['from']) && isset($commentData['from']['name']) ? $commentData['from']['name'] : '',
                    'AuthorID' => isset($commentData['from']) && isset($commentData['from']['id']) ? $commentData['from']['id'] : '',
                    'AuthorURL' => isset($commentData['from']) && isset($commentData['from']['id']) ? \Controller::join_links($this->url, $post['from']['id']) : '',
                    'Content' => isset($commentData['message']) ? $commentData['message'] : '',
                    'Posted' => isset($commentData['created_time']) ? \DBField::create_field('SS_Datetime', $commentData['created_time']) : null,
                    'ReplyByPoster' => isset($commentData['from']) && isset($commentData['from']['id']) ? $commentData['from']['id'] == $post['AuthorID'] : false,
                    'Likes' => isset($commentData['user_likes']) ? $commentData['user_likes'] : false,
                    'LikesCount' => isset($commentData['like_count']) ? count($commentData['like_count']) : 0,
                );

                $comment['LikesDescriptor'] = $comment['LikesCount'] == 1 ? _t('SocialFeed.LIKE', 'like') : _t('SocialFeed.LIKES', 'likes');
                $post['Comments'][] = $comment;
            }
        }

        return $post;
    }

    protected function allowed(array $post) {
        if (isset($post['is_hidden']) && $post['is_hidden'])
            return false;

        return true;
    }

    protected function endpoint($username, $type = 'feed') {
        return \Controller::join_links($this->endpoint, $username, $type);
    }
}