<?php

namespace BNETDocs\Controllers\Comment;

use \BNETDocs\Libraries\Comment as CommentLib;
use \BNETDocs\Libraries\Exceptions\CommentNotFoundException;
use \BNETDocs\Libraries\Logger;
use \BNETDocs\Libraries\User;
use \BNETDocs\Libraries\UserSession;
use \BNETDocs\Models\Comment\Create as CreateModel;
use \CarlBennett\MVC\Libraries\Common;
use \CarlBennett\MVC\Libraries\Controller;
use \CarlBennett\MVC\Libraries\Router;
use \CarlBennett\MVC\Libraries\View;
use \UnexpectedValueException;

class Create extends Controller {

  public function &run(Router &$router, View &$view, array &$args) {

    $model = new CreateModel();

    $model->user_session = UserSession::load($router);
    $model->user         = (isset($model->user_session) ?
                            new User($model->user_session->user_id) : null);

    $model->acl_allowed = ($model->user &&
      $model->user->getOptionsBitmask() & User::OPTION_ACL_COMMENT_CREATE
    );

    $code = 500;
    if (!$model->user_session) {
      $model->response = ["error" => "Unauthorized"];
      $code = 403;
    } else if ($router->getRequestMethod() !== "POST") {
      $router->setResponseHeader("Allow", "POST");
      $model->response = ["error" => "Method Not Allowed","allow" => ["POST"]];
      $code = 405;
    } else {
      $code = $this->createComment($router, $model);
    }

    $view->render($model);

    $model->_responseCode = $code;
    $model->_responseHeaders["Content-Type"] = $view->getMimeType();
    $model->_responseTTL = 0;

    if (!empty($model->origin) && $code >= 300 && $code <= 399) {
      $model->_responseHeaders['Location'] = $model->origin;
    }

    return $model;

  }

  protected function createComment(Router &$router, CreateModel &$model) {
    $query   = $router->getRequestBodyArray();
    $p_id    = (isset($query["parent_id"  ]) ? $query["parent_id"  ] : null);
    $p_type  = (isset($query["parent_type"]) ? $query["parent_type"] : null);
    $content = (isset($query["content"    ]) ? $query["content"    ] : null);

    if (!$model->acl_allowed) {
      $success = false;
    } else {

      if ($p_id   !== null) $p_id   = (int) $p_id;
      if ($p_type !== null) $p_type = (int) $p_type;

      switch ($p_type) {
        case CommentLib::PARENT_TYPE_DOCUMENT:  $origin = "/document/"; break;
        case CommentLib::PARENT_TYPE_COMMENT:   $origin = "/comment/";  break;
        case CommentLib::PARENT_TYPE_NEWS_POST: $origin = "/news/";     break;
        case CommentLib::PARENT_TYPE_PACKET:    $origin = "/packet/";   break;
        case CommentLib::PARENT_TYPE_SERVER:    $origin = "/server/";   break;
        case CommentLib::PARENT_TYPE_USER:      $origin = "/user/";     break;
        default: throw new UnexpectedValueException("Parent type: " . $p_type);
      }
      $origin = Common::relativeUrlToAbsolute($origin . $p_id . "#comments");
      $model->origin = $origin;

      if (empty($content)) {
        $success = false;
      } else {
        $success = CommentLib::create(
          $p_type, $p_id, $model->user_session->user_id, $content
        );
      }

    }

    $model->response = [
      "content"     => $content,
      "error"       => ($success ? false : true),
      "origin"      => $origin,
      "parent_id"   => $p_id,
      "parent_type" => $p_type
    ];

    Logger::logEvent(
      "comment_created_news", $model->user_session->user_id,
      getenv("REMOTE_ADDR"), json_encode($model->response)
    );

    return 303;
  }

}
